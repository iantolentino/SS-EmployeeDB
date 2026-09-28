<?php
declare(strict_types=1);

$configFile = __DIR__ . '/config.php';
$config = is_file($configFile) ? require $configFile : [];
date_default_timezone_set($config['timezone'] ?? 'Asia/Manila');
ini_set('display_errors', '0');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

$scriptPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$basePath = rtrim($scriptPath === '.' ? '' : $scriptPath, '/');
$localHost = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    && in_array(strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]), ['localhost', '127.0.0.1'], true);
$demoMode = $localHost && !is_file($configFile);
$databasePath = $config['database_path'] ?? ($localHost
    ? __DIR__ . '/data/employees.sqlite'
    : dirname(__DIR__, 3) . '/employee-db-private/employees.sqlite');
if (isset($_SERVER['DOCUMENT_ROOT']) && !$localHost) {
    $webRoot = realpath($_SERVER['DOCUMENT_ROOT']);
    $storageParent = realpath(dirname($databasePath));
    if ($webRoot && $storageParent && strncmp(strtolower($storageParent . DIRECTORY_SEPARATOR), strtolower($webRoot . DIRECTORY_SEPARATOR), strlen($webRoot . DIRECTORY_SEPARATOR)) === 0) {
        http_response_code(500);
        exit('Choose a database_path outside public_html in config.php.');
    }
}
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    http_response_code(500);
    exit('Enable pdo_sqlite in cPanel: Select PHP Version > Extensions.');
}
if (!extension_loaded('mbstring')) {
    http_response_code(500);
    exit('Enable mbstring in cPanel: Select PHP Version > Extensions.');
}
if (!is_dir(dirname($databasePath)) && !mkdir(dirname($databasePath), 0700, true) && !is_dir(dirname($databasePath))) {
    http_response_code(500);
    exit('The database folder is not writable. Set database_path to a private writable folder in config.php.');
}
if ($localHost && dirname($databasePath) === __DIR__ . '/data') {
    file_put_contents(__DIR__ . '/data/.htaccess', "Require all denied\n");
}
if (!$localHost) {
    $webRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $storageParent = realpath(dirname($databasePath));
    if ($webRoot && $storageParent && strncmp(strtolower($storageParent . DIRECTORY_SEPARATOR), strtolower($webRoot . DIRECTORY_SEPARATOR), strlen($webRoot . DIRECTORY_SEPARATOR)) === 0) {
        http_response_code(500);
        exit('Choose a database_path outside public_html in config.php.');
    }
}
try {
    $db = new PDO('sqlite:' . $databasePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA busy_timeout = 5000; PRAGMA foreign_keys = ON;');
    $db->exec('CREATE TABLE IF NOT EXISTS users (id TEXT PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE COLLATE NOCASE, password_hash TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS employees (id TEXT PRIMARY KEY, email TEXT NOT NULL UNIQUE COLLATE NOCASE, record TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS audit (id INTEGER PRIMARY KEY AUTOINCREMENT, employee_id TEXT NOT NULL, actor TEXT NOT NULL, action TEXT NOT NULL, at TEXT NOT NULL, changes TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS login_attempts (identity TEXT PRIMARY KEY, attempts INTEGER NOT NULL, expires INTEGER NOT NULL);');
    $auditColumns = array_column($db->query('PRAGMA table_info(audit)')->fetchAll(), 'name');
    foreach (['actor_id', 'actor_email'] as $column) {
        if (!in_array($column, $auditColumns, true)) $db->exec("ALTER TABLE audit ADD COLUMN $column TEXT NOT NULL DEFAULT ''");
    }
} catch (Throwable $error) {
    error_log($error->getMessage());
    http_response_code(500);
    exit('Unable to open the employee database. Check pdo_sqlite and the private folder permissions.');
}

session_name('ss_employee_db');
session_set_cookie_params(['lifetime' => 0, 'path' => ($basePath ?: '') . '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Strict']);
if (!session_start()) {
    http_response_code(500);
    exit('PHP sessions are not writable. Check the session storage permissions in your hosting PHP settings.');
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
if (!empty($_SESSION['user']) && (int)($_SESSION['last_activity'] ?? 0) < time() - 28800) {
    unset($_SESSION['user']);
    session_regenerate_id(true);
}
if (!empty($_SESSION['user'])) $_SESSION['last_activity'] = time();

const EMPLOYEE_FIELDS = ['name', 'email', 'birthday', 'hireDate', 'address', 'position', 'department', 'client', 'teamLeader', 'supervisor', 'site', 'status', 'positionStartDate'];
const ASSIGNMENT_FIELDS = ['position', 'department', 'client', 'teamLeader', 'supervisor'];
const FIELD_LABELS = ['name' => 'Name', 'email' => 'Email', 'birthday' => 'Birthday', 'hireDate' => 'Hire date', 'address' => 'Address', 'position' => 'Position', 'department' => 'Department', 'client' => 'Client name', 'teamLeader' => 'TL name', 'supervisor' => 'Supervisor', 'site' => 'Site location', 'status' => 'Status', 'positionStartDate' => 'Position start date', 'positionHistory' => 'Position history'];

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $action = '', array $params = []): string {
    global $basePath;
    if ($action !== '') $params = ['action' => $action] + $params;
    return ($basePath ?: '') . '/index.php' . ($params ? '?' . http_build_query($params) : '');
}
function asset(string $file): string { global $basePath; return ($basePath ?: '') . '/' . $file; }
function htmx(): bool { return ($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true'; }
function redirect(string $destination): void {
    if (htmx()) { header('HX-Redirect: ' . $destination); } else { header('Location: ' . $destination, true, 303); }
    exit;
}
function csrf_input(): string { return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">'; }
function check_csrf(): void {
    if (!isset($_POST['csrf']) || !is_string($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) throw new RuntimeException('Your form expired. Reload the page and try again.');
}
function text_value(array $input, string $field, bool $required = true, int $limit = 200): string {
    $value = $input[$field] ?? '';
    if (!is_string($value) || mb_strlen(trim($value)) > $limit || ($required && trim($value) === '')) throw new RuntimeException((FIELD_LABELS[$field] ?? ucfirst($field)) . ' is required and must be at most ' . $limit . ' characters.');
    return trim($value);
}
function valid_date(string $value, string $label): string {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value || $value > date('Y-m-d')) throw new RuntimeException($label . ' must be a valid date on or before today.');
    return $value;
}
function validate_employee(array $input): array {
    $employee = [];
    foreach (EMPLOYEE_FIELDS as $field) $employee[$field] = text_value($input, $field, !in_array($field, ['supervisor', 'site'], true), $field === 'address' ? 1000 : 200);
    $employee['email'] = mb_strtolower($employee['email']);
    if (!filter_var($employee['email'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email address.');
    foreach (['birthday', 'hireDate', 'positionStartDate'] as $field) valid_date($employee[$field], FIELD_LABELS[$field]);
    if ($employee['birthday'] >= $employee['hireDate']) throw new RuntimeException('Birthday must be before the hire date.');
    if ($employee['positionStartDate'] < $employee['hireDate']) throw new RuntimeException('Position start date cannot be before the hire date.');
    if (!in_array($employee['status'], ['Active', 'On leave', 'Inactive'], true)) throw new RuntimeException('Choose a valid employee status.');
    return $employee;
}
function current_assignment(array $employee): array {
    return array_intersect_key($employee, array_flip(ASSIGNMENT_FIELDS)) + ['startDate' => $employee['positionStartDate'], 'endDate' => ''];
}
function validate_timeline(array $employee): void {
    $periods = array_merge($employee['positionHistory'], [current_assignment($employee)]);
    usort($periods, fn($a, $b) => strcmp($a['startDate'], $b['startDate']));
    foreach ($periods as $index => $period) {
        valid_date($period['startDate'], 'Position start date');
        if ($period['startDate'] < $employee['hireDate']) throw new RuntimeException('A position history entry starts before the hire date.');
        if ($period['endDate'] !== '') {
            valid_date($period['endDate'], 'Position end date');
            if ($period['endDate'] < $period['startDate']) throw new RuntimeException('Position end date must be on or after its start date.');
        }
        if (isset($periods[$index + 1]) && ($period['endDate'] === '' || $period['endDate'] >= $periods[$index + 1]['startDate'])) throw new RuntimeException('Position dates overlap. End the previous position before the next one begins.');
    }
}
function transaction(callable $callback) {
    global $db;
    $db->exec('BEGIN IMMEDIATE');
    try { $result = $callback(); $db->exec('COMMIT'); return $result; }
    catch (Throwable $error) { $db->exec('ROLLBACK'); throw $error; }
}
function all_employees(): array {
    global $db;
    $employees = array_map(fn($row) => json_decode($row['record'], true, 512, JSON_THROW_ON_ERROR), $db->query('SELECT record FROM employees')->fetchAll());
    usort($employees, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    return $employees;
}
function find_employee(string $id): array {
    global $db;
    $statement = $db->prepare('SELECT record FROM employees WHERE id = ?'); $statement->execute([$id]);
    $row = $statement->fetch();
    if (!$row) throw new RuntimeException('Employee was not found.');
    return json_decode($row['record'], true, 512, JSON_THROW_ON_ERROR);
}
function save_employee(array $employee): void {
    global $db;
    $statement = $db->prepare('INSERT INTO employees (id, email, record) VALUES (?, ?, ?) ON CONFLICT(id) DO UPDATE SET email = excluded.email, record = excluded.record');
    $statement->execute([$employee['id'], $employee['email'], json_encode($employee, JSON_THROW_ON_ERROR)]);
}
function stamp_employee_edit(array $employee): array {
    return array_replace($employee, [
        'updatedAt' => date(DATE_ATOM),
        'updatedBy' => $_SESSION['user']['name'],
        'updatedById' => $_SESSION['user']['id'],
        'updatedByEmail' => $_SESSION['user']['email'],
    ]);
}
function audit_change(string $id, string $action, array $changes, ?string $at = null): void {
    global $db;
    $statement = $db->prepare('INSERT INTO audit (employee_id, actor, actor_id, actor_email, action, at, changes) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $statement->execute([$id, $_SESSION['user']['name'] ?? 'System', $_SESSION['user']['id'] ?? '', $_SESSION['user']['email'] ?? '', $action, $at ?? date(DATE_ATOM), json_encode($changes, JSON_THROW_ON_ERROR)]);
}
function create_user(array $input): void {
    global $db;
    $name = text_value($input, 'name'); $email = mb_strtolower(text_value($input, 'email'));
    $password = $input['password'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email address.');
    if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72) throw new RuntimeException('Use a password with 12 to 72 bytes (up to 72 plain characters).');
    $statement = $db->prepare('INSERT INTO users (id, name, email, password_hash) VALUES (?, ?, ?, ?)');
    $statement->execute([bin2hex(random_bytes(16)), $name, $email, password_hash($password, PASSWORD_DEFAULT)]);
}
function seed_demo(): void {
    global $db;
    if ((int)$db->query('SELECT COUNT(*) FROM employees')->fetchColumn() > 0) return;
    $sample = [
        ['NS-0248', 'Maya Chen', 'Product Designer', 'Design', 'Singapore HQ', 'Acme Retail', 'Olivia Bennett', '1995-04-18', '2022-06-01', '2024-01-01', 'Associate Designer', ''],
        ['NS-0184', 'Daniel Kim', 'Engineering Lead', 'Engineering', 'Seoul Studio', 'Acme Retail', 'Jordan Davis', '1990-08-11', '2020-03-16', '2023-07-01', 'Software Engineer', 'Jordan Davis'],
        ['NS-0132', 'Sofia Martinez', 'Marketing Manager', 'Marketing', 'Remote', 'Brightline Health', 'Jordan Davis', '1992-11-03', '2021-02-01', '2021-02-01', '', ''],
        ['NS-0108', 'Noah Thompson', 'Operations Coordinator', 'Operations', 'Sydney Hub', 'Harbor & Co', 'Jordan Davis', '1996-01-23', '2023-04-10', '2023-04-10', '', ''],
        ['NS-0216', 'Amelia Patel', 'Financial Analyst', 'Finance', 'Singapore HQ', 'Brightline Health', 'Jordan Davis', '1994-06-27', '2022-09-05', '2022-09-05', '', ''],
        ['NS-0205', 'Ethan Williams', 'Software Engineer', 'Engineering', 'Singapore HQ', 'Harbor & Co', 'Daniel Kim', '1997-03-14', '2023-08-21', '2023-08-21', '', 'Jordan Davis'],
        ['NS-0199', 'Priya Shah', 'Product Manager', 'Product', 'Remote', 'Acme Retail', 'Olivia Bennett', '1993-09-09', '2021-11-08', '2021-11-08', '', ''],
        ['NS-0097', 'Marcus Lee', 'People Partner', 'People', 'Singapore HQ', 'Internal', 'Jordan Davis', '1991-12-05', '2020-01-06', '2020-01-06', '', '']
    ];
    transaction(function () use ($sample): void {
        foreach ($sample as $index => $row) {
            [$id, $name, $position, $department, $site, $client, $teamLeader, $birthday, $hireDate, $positionStartDate, $previous, $supervisor] = $row;
            $employee = compact('id', 'name', 'position', 'department', 'site', 'client', 'teamLeader', 'birthday', 'hireDate', 'positionStartDate', 'supervisor');
            $employee += ['email' => str_replace(' ', '.', strtolower($name)) . '@northstar.co', 'address' => (10 + $index) . ' Example Street, Singapore', 'status' => $index === 2 ? 'On leave' : ($index === 7 ? 'Inactive' : 'Active'), 'version' => 1, 'positionHistory' => [], 'updatedAt' => date(DATE_ATOM)];
            if ($previous !== '') $employee['positionHistory'][] = array_replace(current_assignment($employee), ['position' => $previous, 'startDate' => $hireDate, 'endDate' => (new DateTimeImmutable($positionStartDate))->modify('-1 day')->format('Y-m-d')]);
            save_employee($employee);
        }
    });
}
