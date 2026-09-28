<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/views.php';

$action = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$error = '';
$userCount = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($userCount === 0 && $demoMode) {
    transaction(function (): void { create_user(['name' => 'HR Admin', 'email' => 'admin@ss.local', 'password' => 'EmployeeDB2026!']); });
    $userCount = 1;
    if ($config['seed_demo'] ?? true) seed_demo();
}
if ($userCount === 0) {
    if ($method === 'POST' && $action === 'setup') {
        try {
            check_csrf();
            $configuredKey = $config['setup_token'] ?? '';
            if (strlen($configuredKey) < 24 || $configuredKey === 'replace-with-a-random-setup-key-at-least-24-characters' || !is_string($_POST['setup_token'] ?? null) || !hash_equals($configuredKey, $_POST['setup_token'])) throw new RuntimeException('Enter the setup key from your config.php. Set a unique key with at least 24 characters first.');
            transaction(function (): void {
                global $db;
                if ((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) throw new RuntimeException('Setup has already been completed.');
                create_user($_POST);
            });
            if ($config['seed_demo'] ?? false) seed_demo();
            redirect(url());
        } catch (Throwable $exception) { $error = $exception instanceof PDOException ? 'Unable to create the administrator. Check the database configuration.' : $exception->getMessage(); }
    }
    render_login($error, true);
    exit;
}
if ($method === 'POST' && $action === 'login') {
    try {
        check_csrf();
        $identity = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local');
        $statement = $db->prepare('SELECT * FROM login_attempts WHERE identity = ?'); $statement->execute([$identity]); $attempt = $statement->fetch();
        if ($attempt && (int)$attempt['expires'] > time() && (int)$attempt['attempts'] >= 10) throw new RuntimeException('Too many login attempts. Try again in 10 minutes.');
        $email = mb_strtolower(text_value($_POST, 'email'));
        $password = $_POST['password'] ?? '';
        if (!is_string($password) || strlen($password) > 72) throw new RuntimeException('Email or password is incorrect.');
        $statement = $db->prepare('SELECT * FROM users WHERE email = ?'); $statement->execute([$email]); $user = $statement->fetch();
        $verified = password_verify($password, $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (!$user || !$verified) {
            $count = $attempt && (int)$attempt['expires'] > time() ? (int)$attempt['attempts'] + 1 : 1;
            $statement = $db->prepare('INSERT INTO login_attempts VALUES (?, ?, ?) ON CONFLICT(identity) DO UPDATE SET attempts = excluded.attempts, expires = excluded.expires');
            $statement->execute([$identity, $count, time() + 600]);
            throw new RuntimeException('Email or password is incorrect.');
        }
        $statement = $db->prepare('DELETE FROM login_attempts WHERE identity = ?'); $statement->execute([$identity]);
        session_regenerate_id(true);
        $_SESSION['user'] = array_intersect_key($user, array_flip(['id', 'name', 'email']));
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        redirect(url());
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        if (htmx()) { render_error($error); exit; }
    }
}
if (empty($_SESSION['user'])) {
    if (htmx()) redirect(url());
    render_login($error);
    exit;
}
try {
    if ($method === 'POST') {
        check_csrf();
        if ($action === 'export') { export_csv(); exit; }
        if ($action === 'logout') {
            $_SESSION = [];
            $params = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $params['path'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Strict']);
            session_destroy();
            redirect(url());
        }
        if ($action === 'save-employee') {
            $employee = transaction(function (): array {
                $values = validate_employee($_POST);
                $id = text_value($_POST, 'id', false);
                if ($id === '') {
                    $employee = stamp_employee_edit($values + ['id' => 'SS-' . strtoupper(bin2hex(random_bytes(4))), 'version' => 1, 'positionHistory' => []]);
                    save_employee($employee);
                    audit_change($employee['id'], 'Employee created', array_map(fn($value) => ['before' => '', 'after' => $value], $values), $employee['updatedAt']);
                    return $employee;
                }
                $existing = find_employee($id);
                if ((string)$existing['version'] !== ($_POST['version'] ?? '')) throw new RuntimeException('This profile changed since you opened it. Close this form, reload the profile, and try again.');
                $changes = [];
                foreach (EMPLOYEE_FIELDS as $field) if ($existing[$field] !== $values[$field]) $changes[$field] = ['before' => $existing[$field], 'after' => $values[$field]];
                if (!$changes) return $existing;
                $employee = stamp_employee_edit(array_replace($existing, $values, ['version' => $existing['version'] + 1]));
                $assignmentChanged = false;
                foreach (ASSIGNMENT_FIELDS as $field) if ($employee[$field] !== $existing[$field]) $assignmentChanged = true;
                if ($assignmentChanged) {
                    if ($employee['positionStartDate'] <= $existing['positionStartDate']) throw new RuntimeException('For a new position or assignment, choose a position start date after the current position started.');
                    $previous = current_assignment($existing);
                    $previous['endDate'] = (new DateTimeImmutable($employee['positionStartDate']))->modify('-1 day')->format('Y-m-d');
                    $employee['positionHistory'][] = $previous;
                }
                validate_timeline($employee);
                if ($assignmentChanged) $changes['positionHistory'] = ['before' => $existing['positionHistory'], 'after' => $employee['positionHistory']];
                save_employee($employee);
                audit_change($id, 'Profile updated', $changes, $employee['updatedAt']);
                return $employee;
            });
            if (!htmx()) redirect(url('employee', ['employee' => $employee['id']]));
            header('HX-Trigger: ' . json_encode(['employeeSaved' => ['id' => $employee['id'], 'message' => 'Employee saved.']]));
            exit;
        }
        if ($action === 'save-history') {
            $employee = transaction(function (): array {
                $existing = find_employee(text_value($_POST, 'id'));
                if ((string)$existing['version'] !== ($_POST['version'] ?? '')) throw new RuntimeException('This profile changed. Close this form, reload the profile, and try again.');
                $period = ['position' => text_value($_POST, 'position'), 'startDate' => valid_date(text_value($_POST, 'startDate'), 'Start date'), 'endDate' => valid_date(text_value($_POST, 'endDate'), 'End date')];
                foreach (['department', 'client', 'teamLeader', 'supervisor'] as $field) $period[$field] = text_value($_POST, $field, false);
                $employee = $existing;
                $employee['positionHistory'][] = $period;
                usort($employee['positionHistory'], fn($a, $b) => strcmp($a['startDate'], $b['startDate']));
                $employee['version']++; $employee = stamp_employee_edit($employee);
                validate_timeline($employee);
                save_employee($employee);
                audit_change($employee['id'], 'Previous position added', ['positionHistory' => ['before' => $existing['positionHistory'], 'after' => $employee['positionHistory']]], $employee['updatedAt']);
                return $employee;
            });
            if (!htmx()) redirect(url('employee', ['employee' => $employee['id']]));
            header('HX-Trigger: ' . json_encode(['employeeSaved' => ['id' => $employee['id'], 'message' => 'Previous position added.']]));
            exit;
        }
        if ($action === 'save-account') {
            create_user($_POST);
            if (!htmx()) redirect(url());
            header('HX-Trigger: ' . json_encode(['accountSaved' => ['message' => 'Login account created.']]));
            exit;
        }
        throw new RuntimeException('That action is unavailable.');
    }
    if ($method !== 'GET') { http_response_code(405); exit; }
    if ($action === 'directory') { render_directory(); render_filter_options(true); exit; }
    if ($action === 'metrics') { render_metrics(); exit; }
    if ($action === 'profile') { render_profile(find_employee(text_value($_GET, 'id'))); exit; }
    if ($action === 'preview') { render_employee_preview(find_employee(text_value($_GET, 'id'))); exit; }
    if ($action === 'employee-form') { render_employee_form(isset($_GET['id']) ? find_employee(text_value($_GET, 'id')) : []); exit; }
    if ($action === 'history-form') { render_history_form(find_employee(text_value($_GET, 'id'))); exit; }
    if ($action === 'account-form') { render_account_form(); exit; }
    if ($action === 'accounts') { render_accounts(); exit; }
    if ($action === 'export') { export_csv(); exit; }
    if ($action === 'employee' || isset($_GET['employee'])) render_employee_page(find_employee(text_value($_GET, 'employee')));
    elseif ($action === 'employees') render_app();
    elseif ($action === 'login-accounts') render_accounts_page();
    else render_dashboard();
} catch (Throwable $exception) {
    $message = $exception instanceof PDOException ? (str_contains($exception->getMessage(), 'UNIQUE constraint') ? 'That email address already exists.' : 'Unable to save the record. Please try again.') : $exception->getMessage();
    if ($exception instanceof PDOException) error_log($exception->getMessage());
    http_response_code(422);
    if ($action === 'save-employee') render_employee_form($_POST, $message);
    elseif ($action === 'save-history') { $existing = find_employee(text_value($_POST, 'id')); render_history_form($existing, $message, $_POST); }
    elseif ($action === 'save-account') render_account_form($message);
    else render_error($message);
}
