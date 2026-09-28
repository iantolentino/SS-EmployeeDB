<?php
declare(strict_types=1);

function logo_file(): ?string {
    foreach (['stratastaff-logo.png', 'logo.svg', 'logo.png', 'logo.webp', 'logo.jpg', 'logo.jpeg'] as $file) {
        if (is_file(__DIR__ . '/assets/' . $file)) return 'assets/' . $file;
    }
    return null;
}
function render_logo(string $class = 'app-logo-link'): void {
    $logo = logo_file();
    if ($logo) echo '<a href="' . e(url()) . '" class="' . e($class) . '"><img class="app-logo" src="' . e(asset($logo)) . '" alt="Company logo"></a>';
}
function page_start(string $title, string $bodyClass = ''): void { ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" href="<?= e(logo_file() ? asset(logo_file()) : 'data:,') ?>"><title><?= e($title) ?> · Employee DB</title><link rel="stylesheet" href="<?= e(asset('employee-db-ui.css')) ?>"><link rel="stylesheet" href="<?= e(asset('employee-db-app.css')) ?>"><script src="<?= e(asset('vendor/htmx.min.js')) ?>" defer></script><script src="<?= e(asset('employee-db-app.js')) ?>" defer></script></head><body class="<?= e($bodyClass) ?>" data-app-url="<?= e(url()) ?>">
<?php }
function page_end(): void { echo '<div id="toast" class="ui-toast" role="status"></div></body></html>'; }
function render_error(string $error): void { echo '<p class="form-error" role="alert">' . e($error) . '</p>'; }
function initials(string $name): string { $parts = preg_split('/\s+/', trim($name)); return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[count($parts) - 1] ?? '', 0, 1)); }
function display_date(string $date): string { return $date === '' ? '—' : (new DateTimeImmutable($date))->format('M j, Y'); }
function status_badge(string $status): string { return '<span class="ui-status ' . ($status === 'Inactive' ? 'ui-danger' : ($status === 'On leave' ? 'ui-warn' : '')) . '">' . e($status) . '</span>'; }
function form_field(string $field, string $label, array $values, string $type = 'text', bool $required = true, string $list = ''): void { ?>
<div class="ui-field <?= $field === 'address' ? 'ui-full' : '' ?>"><label for="field-<?= e($field) ?>"><?= e($label) ?><?= $required ? ' <span aria-hidden="true">*</span>' : ' <span class="optional">(optional)</span>' ?></label><?php if ($field === 'address'): ?><textarea id="field-address" name="address" maxlength="1000" required autocomplete="street-address"><?= e($values['address'] ?? '') ?></textarea><?php else: ?><input id="field-<?= e($field) ?>" name="<?= e($field) ?>" type="<?= e($type) ?>" value="<?= $type === 'password' ? '' : e($values[$field] ?? '') ?>" <?= $required ? 'required' : '' ?> <?= $type === 'date' ? 'max="' . date('Y-m-d') . '"' : 'maxlength="200"' ?> <?= $type === 'password' ? 'minlength="12" autocomplete="new-password"' : ($field === 'email' ? 'autocomplete="email"' : '') ?> <?= $list ? 'list="' . e($list) . '"' : '' ?>><?php endif; ?></div>
<?php }
function render_login(string $error = '', bool $setup = false): void {
    global $demoMode;
    page_start($setup ? 'Create administrator' : 'Log in', 'login'); ?>
<div class="login-shell"><section class="login-art"><?php render_logo('login-brand supplied-logo'); ?><div class="login-art-copy"><div class="eyebrow">PEOPLE. POSITIONS. PROGRESS.</div><h1>Your people,<br>all in one place.</h1><p>Find the right person in seconds. Keep employee details, assignments, and every career move connected.</p><div class="login-art-pills"><span>Employee directory</span><span>Position history</span><span>Client assignments</span></div></div><div class="login-art-footer">A clearer view of your team.</div></section><main class="login-form-zone"><div class="login-form"><?php render_logo('login-mobile-brand supplied-logo'); ?><div class="eyebrow">EMPLOYEE WORKSPACE</div><h2><?= $setup ? 'Set up your workspace' : 'Welcome back' ?></h2><p><?= $setup ? 'Create the first HR administrator to get started.' : 'Sign in to manage your employee directory.' ?></p><form method="post" action="<?= e(url($setup ? 'setup' : 'login')) ?>" <?= !$setup ? 'hx-post="' . e(url('login')) . '" hx-target="#loginError"' : '' ?>><?= csrf_input() ?><div id="loginError"><?php if ($error) render_error($error); ?></div><?php if ($setup): ?><?php form_field('setup_token', 'Setup key from config.php', [], 'password'); ?><?php form_field('name', 'Administrator name', $_POST); ?><?php endif; ?><div class="ui-field"><label for="loginEmail">Work email</label><input id="loginEmail" name="email" type="email" required maxlength="200" autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@company.com"></div><div class="ui-field"><label for="loginPassword">Password</label><input id="loginPassword" name="password" type="password" required maxlength="200" <?= $setup ? 'minlength="12" autocomplete="new-password"' : 'autocomplete="current-password"' ?> placeholder="Enter your password"></div><button class="ui-btn ui-primary login-submit" type="submit"><?= $setup ? 'Create administrator' : 'Log in' ?> <span>→</span></button></form><?php if ($demoMode && !$setup): ?><div class="demo-credentials"><b>Local test account</b><span>Email: <strong>admin@ss.local</strong></span><span>Password: <strong>EmployeeDB2026!</strong></span><small>Sample employees are fictional.</small></div><?php else: ?><div class="login-note"><?= $setup ? 'Your setup key is configured by the person uploading this app. Use a password with at least 12 characters.' : 'Need access or a password reset? Contact your HR administrator.' ?></div><?php endif; ?></div></main></div>
<?php page_end(); }

function distinct_values(string $field, ?array $employees = null): array {
    $values = array_values(array_unique(array_filter(array_column($employees ?? all_employees(), $field))));
    natcasesort($values); return array_values($values);
}
function filtered_employees(): array {
    $employees = all_employees();
    $query = mb_strtolower(text_value($_GET, 'q', false));
    $filters = [];
    foreach (['department', 'client', 'status'] as $field) $filters[$field] = text_value($_GET, $field, false);
    return array_values(array_filter($employees, function ($employee) use ($query, $filters): bool {
        if ($query !== '' && mb_strpos(mb_strtolower($employee['name'] . ' ' . $employee['email']), $query) === false) return false;
        foreach ($filters as $field => $value) if ($value !== '' && $employee[$field] !== $value) return false;
        return true;
    }));
}
function render_metrics(): void {
    $employees = all_employees();
    $active = count(array_filter($employees, fn($employee) => $employee['status'] === 'Active'));
    foreach ([['♙', 'Total employees', count($employees), 'Profiles in your directory'], ['✓', 'Active employees', $active, 'Currently with the team'], ['▦', 'Departments', count(distinct_values('department', $employees)), 'Teams across your workspace'], ['◈', 'Clients', count(distinct_values('client', $employees)), 'Employee assignments']] as [$icon, $label, $number, $note]): ?>
<div class="ui-metric"><small><?= e($icon) ?></small><span><?= e($label) ?></span><b><?= e($number) ?></b><em><?= e($note) ?></em></div>
<?php endforeach; }
function render_filter_options(bool $outOfBand = false): void {
    foreach (['department' => 'departments', 'client' => 'clients', 'status' => 'statuses'] as $field => $plural): ?>
<select id="filter-<?= e($field) ?>" name="<?= e($field) ?>" aria-label="Filter by <?= e($field) ?>" <?= $outOfBand ? 'hx-swap-oob="outerHTML"' : '' ?>><option value="" <?= ($_GET[$field] ?? '') === '' ? 'selected' : '' ?>>All <?= e($plural) ?></option><?php foreach ($field === 'status' ? ['Active', 'On leave', 'Inactive'] : distinct_values($field) as $value): ?><option value="<?= e($value) ?>" <?= ($_GET[$field] ?? '') === $value ? 'selected' : '' ?>><?= e($value) ?></option><?php endforeach; ?></select>
<?php endforeach;
}
function employee_list_page(): array {
    $employees = filtered_employees();
    $perPage = filter_var($_GET['per_page'] ?? 10, FILTER_VALIDATE_INT);
    if (!in_array($perPage, [10, 25, 50, 100], true)) $perPage = 10;
    $total = count($employees);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($pages, (int)(filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1)));
    $offset = ($page - 1) * $perPage;
    return ['employees' => array_slice($employees, $offset, $perPage), 'matchingIds' => array_column($employees, 'id'), 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'pages' => $pages, 'start' => $total ? $offset + 1 : 0, 'end' => min($offset + $perPage, $total)];
}
function render_page_link(string $label, int $page, array $params, bool $current = false, string $ariaLabel = ''): void {
    $params['page'] = $page;
    $fragmentParams = $params;
    unset($fragmentParams['selected']); ?>
<a class="ui-btn pagination-link <?= $current ? 'is-current' : '' ?>" href="<?= e(url('employees', $params)) ?>" hx-get="<?= e(url('directory', $fragmentParams)) ?>" hx-target="#directory" hx-include="#selectedEmployee" hx-sync="#directoryFilters:replace" <?= $current ? 'aria-current="page"' : '' ?> <?= $ariaLabel !== '' ? 'aria-label="' . e($ariaLabel) . '"' : '' ?>><?= e($label) ?></a>
<?php }
function render_directory(): void {
    $listing = employee_list_page();
    $employees = $listing['employees'];
    $filters = [];
    foreach (['q', 'department', 'client', 'status', 'selected'] as $field) if (isset($_GET[$field]) && is_string($_GET[$field]) && $_GET[$field] !== '') $filters[$field] = $_GET[$field];
    $filters['page'] = $listing['page'];
    $filters['per_page'] = $listing['perPage']; ?>
<div data-directory-page="<?= $listing['page'] ?>" data-matching-ids="<?= e(json_encode($listing['matchingIds'], JSON_THROW_ON_ERROR)) ?>">
<div class="directory-caption"><span>Showing <b><?= $listing['start'] ?>–<?= $listing['end'] ?></b> of <b><?= $listing['total'] ?></b> employees</span><span>Click to preview · Double-click for full profile</span></div>
<div class="ui-table-wrap"><table class="ui-table employee-table employee-list"><thead><tr><th class="employee-select-column"><input type="checkbox" data-select-all aria-label="Select all matching employees"></th><th>Employee</th><th>Position / department</th><th>Client / TL</th><th>Status</th></tr></thead><tbody>
<?php foreach ($employees as $employee): ?>
    <tr data-employee-id="<?= e($employee['id']) ?>" data-employee-row data-profile-url="<?= e(url('employee', ['employee' => $employee['id']])) ?>" title="Click to preview; double-click to open full profile">
        <td class="employee-select-column"><input type="checkbox" data-export-employee="<?= e($employee['id']) ?>" aria-label="Select <?= e($employee['name']) ?> for export"></td>
        <td><div class="ui-person"><div class="ui-avatar"><?= e(initials($employee['name'])) ?></div><div><a class="employee-link employee-list-name" data-preview-link href="<?= e(url('employees', array_replace($filters, ['selected' => $employee['id']]))) ?>" hx-get="<?= e(url('preview', ['id' => $employee['id']])) ?>" hx-target="#employeePreview" hx-push-url="<?= e(url('employees', array_replace($filters, ['selected' => $employee['id']]))) ?>" hx-sync="#employeePreview:replace"><?= e($employee['name']) ?></a><span><?= e($employee['email']) ?></span></div></div></td>
        <td><b class="cell-title"><?= e($employee['position']) ?></b><span class="cell-note"><?= e($employee['department']) ?></span></td>
        <td><b class="cell-title"><?= e($employee['client']) ?></b><span class="cell-note"><?= e($employee['teamLeader']) ?></span></td>
        <td><?= status_badge($employee['status']) ?></td>
    </tr>
<?php endforeach; ?>
<?php if (!$employees): ?><tr><td colspan="5" class="empty-state"><b>No employees found</b><p>Try another name or email, or clear your filters.</p></td></tr><?php endif; ?>
</tbody></table></div>
<nav class="directory-pagination" aria-label="Employee list pages">
    <span class="pagination-summary">Page <?= $listing['page'] ?> of <?= $listing['pages'] ?></span>
    <div class="pagination-controls">
    <?php if ($listing['page'] > 1): render_page_link('Previous', $listing['page'] - 1, $filters, false, 'Previous employee page'); else: ?><button class="ui-btn pagination-link" type="button" disabled>Previous</button><?php endif; ?>
    <?php
    $previousPage = 0;
    $pageNumbers = array_unique([1, $listing['page'] - 1, $listing['page'], $listing['page'] + 1, $listing['pages']]);
    sort($pageNumbers);
    foreach ($pageNumbers as $page) {
        if ($page < 1 || $page > $listing['pages']) continue;
        if ($previousPage && $page > $previousPage + 1) echo '<span class="pagination-ellipsis" aria-hidden="true">…</span>';
        render_page_link((string)$page, $page, $filters, $page === $listing['page'], 'Employee page ' . $page);
        $previousPage = $page;
    }
    ?>
    <?php if ($listing['page'] < $listing['pages']): render_page_link('Next', $listing['page'] + 1, $filters, false, 'Next employee page'); else: ?><button class="ui-btn pagination-link" type="button" disabled>Next</button><?php endif; ?>
    </div>
</nav>
</div>
<?php }
function render_audit_value(string $field, $value): void {
    if ($field === 'positionHistory' && is_array($value)) {
        if (!$value) { echo '<span class="audit-empty">No previous positions</span>'; return; }
        foreach ($value as $period) {
            echo '<div class="audit-period"><b>' . e($period['position'] ?? '') . '</b><span>' . e(display_date($period['startDate'] ?? '')) . ' — ' . e(display_date($period['endDate'] ?? '')) . '</span>';
            $assignment = array_filter([$period['department'] ?? '', $period['client'] ?? '', empty($period['teamLeader']) ? '' : 'TL: ' . $period['teamLeader'], empty($period['supervisor']) ? '' : 'Supervisor: ' . $period['supervisor']]);
            if ($assignment) echo '<span>' . e(implode(' · ', $assignment)) . '</span>';
            echo '</div>';
        }
        return;
    }
    if ($value === '' || $value === null) { echo '<span class="audit-empty">Not set</span>'; return; }
    echo e(in_array($field, ['birthday', 'hireDate', 'positionStartDate'], true) ? display_date((string)$value) : $value);
}
function render_audit_entry(array $entry, bool $latest = false): void {
    $timestamp = (new DateTimeImmutable($entry['at']))->setTimezone(new DateTimeZone(date_default_timezone_get()));
    $changes = json_decode($entry['changes'], true, 512, JSON_THROW_ON_ERROR); ?>
<article class="audit-entry <?= $latest ? 'audit-latest' : '' ?>" data-audit-id="<?= e($entry['id']) ?>">
    <div class="audit-entry-head"><b><?= e($entry['action']) ?></b><?php if ($latest): ?><span class="current-label">Latest change</span><?php endif; ?></div>
    <div class="audit-actor">By <strong><?= e($entry['actor']) ?></strong><?php if (!empty($entry['actor_email'])): ?><span><?= e($entry['actor_email']) ?></span><?php endif; ?></div>
    <time datetime="<?= e($entry['at']) ?>"><?= e($timestamp->format('M j, Y · g:i:s A')) ?> <span><?= e($timestamp->format('P')) ?></span></time>
    <?php foreach ($changes as $field => $change): ?>
    <div class="audit-change"><strong><?= e(FIELD_LABELS[$field] ?? $field) ?></strong><div class="audit-diff"><div class="audit-side audit-before"><span class="audit-value-label">Previous value</span><div><?php render_audit_value($field, $change['before']); ?></div></div><div class="audit-side audit-after"><span class="audit-value-label">New value</span><div><?php render_audit_value($field, $change['after']); ?></div></div></div></div>
    <?php endforeach; ?>
</article>
<?php }
function render_edit_log(array $audit): void {
    $latestEdit = null;
    foreach ($audit as $entry) if ($entry['action'] !== 'Employee created') { $latestEdit = $entry; break; } ?>
<section class="profile-audit" aria-labelledby="editLogHeading">
    <div class="profile-section-head"><h3 id="editLogHeading">Edit log</h3><span><?= count($audit) ?> <?= count($audit) === 1 ? 'change' : 'changes' ?></span></div>
    <?php if ($latestEdit): ?>
    <p class="last-edit-summary">Last edited by <strong><?= e($latestEdit['actor']) ?></strong></p>
    <?php else: ?><p class="muted">No edits recorded yet. Changes will appear here after this employee is edited.</p><?php endif; ?>
    <?php if ($audit) render_audit_entry($audit[0], true); ?>
    <?php if (count($audit) > 1): ?><details class="earlier-edits"><summary>Earlier changes <span><?= count($audit) - 1 ?></span></summary><?php foreach (array_slice($audit, 1) as $entry) render_audit_entry($entry); ?></details><?php endif; ?>
</section>
<?php }
function render_employee_details(array $employee, bool $showHistoryAction = true): void { ?>
<div class="profile-identity" data-profile-id="<?= e($employee['id']) ?>">
    <div class="profile-eyebrow">EMPLOYEE PROFILE <span><?= e($employee['id']) ?></span></div>
    <div class="profile-person"><div class="profile-avatar"><?= e(initials($employee['name'])) ?></div><div><h2><?= e($employee['name']) ?></h2><p><?= e($employee['position']) ?></p><p><?= e($employee['department']) ?> · <?= e($employee['client']) ?></p></div></div>
    <?= status_badge($employee['status']) ?>
</div>
<div class="profile-actions">
    <button class="ui-btn ui-primary" type="button" hx-get="<?= e(url('employee-form', ['id' => $employee['id']])) ?>" hx-target="#modalContent">Edit profile</button>
    <?php if ($showHistoryAction): ?><button class="ui-btn" type="button" hx-get="<?= e(url('history-form', ['id' => $employee['id']])) ?>" hx-target="#modalContent">Add past position</button><?php endif; ?>
</div>
<dl class="profile-details">
<?php foreach (['email', 'birthday', 'hireDate', 'positionStartDate', 'department', 'client', 'teamLeader', 'supervisor', 'site', 'address'] as $field): ?>
    <div class="<?= in_array($field, ['email', 'address'], true) ? 'profile-full' : '' ?>"><dt><?= e(FIELD_LABELS[$field]) ?></dt><dd><?= e(in_array($field, ['birthday', 'hireDate', 'positionStartDate'], true) ? display_date($employee[$field]) : ($employee[$field] ?: '—')) ?></dd></div>
<?php endforeach; ?>
</dl>
<?php }
function render_profile(array $employee): void {
    global $db;
    $statement = $db->prepare('SELECT * FROM audit WHERE employee_id = ? ORDER BY id DESC'); $statement->execute([$employee['id']]); $audit = $statement->fetchAll();
    $history = array_merge($employee['positionHistory'], [current_assignment($employee)]);
    usort($history, fn($a, $b) => strcmp($b['startDate'], $a['startDate'])); ?>
<?php render_employee_details($employee); render_edit_log($audit); ?>
<section class="profile-history"><div class="profile-section-head"><h3>Position history</h3><span><?= count($history) ?> <?= count($history) === 1 ? 'position' : 'positions' ?></span></div><ol class="timeline"><?php foreach ($history as $period): ?><li><div class="timeline-dot <?= $period['endDate'] === '' ? 'current' : '' ?>"></div><b><?= e($period['position']) ?></b><?php if ($period['endDate'] === ''): ?><span class="current-label">Current</span><?php endif; ?><time><?= e(display_date($period['startDate'])) ?> — <?= $period['endDate'] === '' ? 'Present' : e(display_date($period['endDate'])) ?></time><p><?= e(implode(' · ', array_filter([$period['department'] ?? '', $period['client'] ?? '']))) ?></p><?php if (!empty($period['teamLeader'])): ?><p>TL: <?= e($period['teamLeader']) ?><?= !empty($period['supervisor']) ? ' · Supervisor: ' . e($period['supervisor']) : '' ?></p><?php endif; ?></li><?php endforeach; ?></ol></section>
<?php }
function modal_head(string $title, string $description): void { ?>
<div class="ui-dialog-head"><div><h2 id="modalTitle"><?= e($title) ?></h2><p><?= e($description) ?></p></div><button type="button" class="ui-close" data-close aria-label="Close dialog">×</button></div>
<?php }
function render_employee_form(array $employee = [], string $error = ''): void {
    $editing = !empty($employee['id']);
    $employee += ['status' => 'Active', 'hireDate' => date('Y-m-d'), 'positionStartDate' => date('Y-m-d')];
    modal_head($editing ? 'Edit employee' : 'Add employee', $editing ? 'Update their details or record a new position and effective date.' : 'Create a profile with their personal details and current assignment.'); ?>
<div class="ui-dialog-body"><?php if ($error) render_error($error); ?><form method="post" action="<?= e(url('save-employee')) ?>" hx-post="<?= e(url('save-employee')) ?>" hx-target="#modalContent"><?= csrf_input() ?><input type="hidden" name="id" value="<?= e($employee['id'] ?? '') ?>"><input type="hidden" name="version" value="<?= e($employee['version'] ?? '') ?>"><h3 class="form-section">Personal details</h3><div class="ui-form-grid"><?php form_field('name', 'Full name', $employee); form_field('email', 'Work email', $employee, 'email'); form_field('birthday', 'Birthday', $employee, 'date'); form_field('hireDate', 'Hire date', $employee, 'date'); form_field('address', 'Address', $employee); ?></div><h3 class="form-section">Current assignment</h3><div class="ui-form-grid"><?php form_field('position', 'Position', $employee); form_field('positionStartDate', 'Position start date', $employee, 'date'); form_field('department', 'Department', $employee, 'text', true, 'departmentOptions'); form_field('client', 'Client name', $employee, 'text', true, 'clientOptions'); form_field('teamLeader', 'TL name', $employee); form_field('supervisor', 'Supervisor', $employee, 'text', false); form_field('site', 'Site location', $employee, 'text', false); ?><div class="ui-field"><label for="field-status">Status</label><select id="field-status" name="status"><?php foreach (['Active', 'On leave', 'Inactive'] as $status): ?><option <?= ($employee['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div></div><?php foreach (['department' => 'departmentOptions', 'client' => 'clientOptions'] as $field => $list): ?><datalist id="<?= e($list) ?>"><?php foreach (distinct_values($field) as $value): ?><option value="<?= e($value) ?>"><?php endforeach; ?></datalist><?php endforeach; ?><p class="form-hint"><?= $editing ? 'Changing a position, department, client, TL, or supervisor requires a later position start date. The previous assignment is saved automatically, ending the day before the new one begins. All detail and date edits appear in the change log.' : 'After saving, use “Add past position” to enter earlier roles and dates.' ?></p><div class="ui-form-actions"><button class="ui-btn" type="button" data-close>Cancel</button><button class="ui-btn ui-primary" type="submit">Save employee</button></div></form></div>
<?php }
function render_history_form(array $employee, string $error = '', array $values = []): void {
    modal_head('Add a past position', 'Record a previous role for ' . $employee['name'] . '.'); ?>
<div class="ui-dialog-body"><?php if ($error) render_error($error); ?><form method="post" action="<?= e(url('save-history')) ?>" hx-post="<?= e(url('save-history')) ?>" hx-target="#modalContent"><?= csrf_input() ?><input type="hidden" name="id" value="<?= e($employee['id']) ?>"><input type="hidden" name="version" value="<?= e($employee['version']) ?>"><div class="ui-form-grid"><?php form_field('position', 'Previous position', $values); ?><div class="ui-field"><label>Employee</label><div class="read-only-field"><?= e($employee['name']) ?></div></div><?php form_field('startDate', 'Start date', $values, 'date'); form_field('endDate', 'End date', $values, 'date'); form_field('department', 'Previous department', $values, 'text', false); form_field('client', 'Previous client', $values, 'text', false); form_field('teamLeader', 'Previous TL', $values, 'text', false); form_field('supervisor', 'Previous supervisor', $values, 'text', false); ?></div><p class="form-hint">Hire date: <?= e(display_date($employee['hireDate'])) ?>. Current position starts <?= e(display_date($employee['positionStartDate'])) ?>. Past positions must start on or after the hire date and end before the current position, without overlapping other entries.</p><div class="ui-form-actions"><button class="ui-btn" type="button" data-close>Cancel</button><button class="ui-btn ui-primary" type="submit">Save past position</button></div></form></div>
<?php }
function render_account_form(string $error = ''): void {
    modal_head('Create login account', 'Give another HR administrator access to this workspace.'); ?>
<div class="ui-dialog-body"><?php if ($error) render_error($error); ?><form method="post" action="<?= e(url('save-account')) ?>" hx-post="<?= e(url('save-account')) ?>" hx-target="#modalContent"><div class="ui-form-grid"><?= csrf_input() ?><?php form_field('name', 'Full name', $_POST); form_field('email', 'Work email', $_POST, 'email'); form_field('password', 'Password', [], 'password'); ?></div><p class="form-hint">Use at least 12 characters. This account can view and edit all employee records. Employee profiles do not automatically create login accounts.</p><div class="ui-form-actions"><button class="ui-btn" type="button" data-close>Cancel</button><button class="ui-btn ui-primary" type="submit">Create account</button></div></form></div>
<?php }
function render_accounts(): void {
    global $db;
    foreach ($db->query('SELECT name, email FROM users ORDER BY name')->fetchAll() as $user): ?>
<div class="account-row"><div class="ui-avatar"><?= e(initials($user['name'])) ?></div><div><b><?= e($user['name']) ?></b><span><?= e($user['email']) ?></span></div><span class="ui-status">HR admin</span></div>
<?php endforeach; }
function export_csv(): void {
    $employees = filtered_employees();
    if (array_key_exists('employee_ids', $_POST)) {
        $encodedIds = $_POST['employee_ids'];
        if (!is_string($encodedIds)) throw new RuntimeException('Invalid employee selection.');
        $ids = json_decode($encodedIds, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($ids)) throw new RuntimeException('Invalid employee selection.');
        foreach ($ids as $id) if (!is_string($id) || strlen($id) > 200) throw new RuntimeException('Invalid employee selection.');
        $selectedIds = array_fill_keys($ids, true);
        $employees = array_filter($employees, fn($employee) => isset($selectedIds[$employee['id']]));
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="employee-directory-' . date('Y-m-d') . '.csv"');
    $stream = fopen('php://output', 'w'); fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, array_merge(['Employee ID'], array_map(fn($field) => FIELD_LABELS[$field], EMPLOYEE_FIELDS)));
    foreach ($employees as $employee) {
        $row = array_merge([$employee['id']], array_map(fn($field) => $employee[$field], EMPLOYEE_FIELDS));
        $row = array_map(fn($value) => preg_match('/^[\s]*[=+@-]/u', $value) ? "'" . $value : $value, $row);
        fputcsv($stream, $row);
    }
    fclose($stream);
}
function render_sidebar(string $active): void { ?>
<aside class="ui-sidebar">
    <?php render_logo(); ?>
    <nav class="ui-nav" aria-label="Main navigation">
        <a href="<?= e(url()) ?>" class="<?= $active === 'dashboard' ? 'ui-active' : '' ?>" <?= $active === 'dashboard' ? 'aria-current="page"' : '' ?> title="Dashboard"><span aria-hidden="true">⌂</span>Dashboard</a>
        <a href="<?= e(url('employees')) ?>" class="<?= $active === 'employees' ? 'ui-active' : '' ?>" <?= $active === 'employees' ? 'aria-current="page"' : '' ?> title="Employees"><span aria-hidden="true">♙</span>Employees</a>
        <a href="<?= e(url('login-accounts')) ?>" class="<?= $active === 'accounts' ? 'ui-active' : '' ?>" <?= $active === 'accounts' ? 'aria-current="page"' : '' ?> title="Login accounts"><span aria-hidden="true">◎</span>Login accounts</a>
    </nav>
    <div class="ui-sidebar-bottom"><div class="ui-user-mini"><div class="ui-avatar"><?= e(initials($_SESSION['user']['name'])) ?></div><div><b><?= e($_SESSION['user']['name']) ?></b><span>HR administrator</span></div></div><form method="post" action="<?= e(url('logout')) ?>"><?= csrf_input() ?><button class="ui-logout" type="submit"><span>⇥</span>Log out</button></form></div>
</aside>
<?php }
function app_layout_start(string $title, string $active, string $bodyClass = ''): void {
    page_start($title, 'employee-app ' . $bodyClass); ?>
<div class="ui-shell">
    <?php render_sidebar($active); ?>
    <div class="ui-main">
        <header class="ui-topbar app-navbar">
            <div class="ui-crumb"><a href="<?= e(url()) ?>">Dashboard</a><span>/</span><b><?= e($title) ?></b></div>
            <div class="ui-top-actions">
                <button class="ui-btn" type="button" id="exportCsv" data-csrf="<?= e($_SESSION['csrf']) ?>"><span aria-hidden="true">↓</span> <span id="exportCsvLabel">Export CSV</span></button>
                <button class="ui-btn ui-primary" type="button" hx-get="<?= e(url('employee-form')) ?>" hx-target="#modalContent"><span aria-hidden="true">＋</span> Add employee</button>
                <button class="ui-theme-toggle" type="button" data-theme-toggle aria-label="Toggle color theme">☀</button>
                <div class="ui-avatar navbar-avatar" title="<?= e($_SESSION['user']['name']) ?>"><?= e(initials($_SESSION['user']['name'])) ?></div>
            </div>
        </header>
        <main class="ui-content">
<?php }
function app_layout_end(): void { ?>
        </main>
    </div>
</div>
<dialog class="ui-dialog employee-dialog" id="employeeDialog" aria-labelledby="modalTitle"><div id="modalContent"></div></dialog>
<?php page_end(); }
function render_dashboard(): void {
    global $db;
    $employees = all_employees();
    $byId = array_column($employees, null, 'id');
    $activity = $db->query('SELECT * FROM audit ORDER BY id DESC LIMIT 10')->fetchAll();
    app_layout_start('Dashboard', 'dashboard', 'dashboard-page'); ?>
<div class="ui-page-head"><div><h1>Dashboard</h1></div></div>
<section class="ui-metrics" id="metrics"><?php render_metrics(); ?></section>
<div class="dashboard-grid">
    <section class="ui-panel activity-panel"><div class="ui-panel-head"><div><h2>Recent activity</h2><p>Employee record updates.</p></div></div>
    <?php if (!$activity): ?><div class="empty-state"><b>No recent updates</b><p>Employee changes will appear here after you start editing records.</p><a class="ui-panel-link" href="<?= e(url('employees')) ?>">View employees →</a></div><?php endif; ?>
    <?php foreach ($activity as $entry): $employee = $byId[$entry['employee_id']] ?? null; $changes = json_decode($entry['changes'], true, 512, JSON_THROW_ON_ERROR); ?>
    <article class="activity-row"><div class="ui-avatar"><?= e(initials($entry['actor'])) ?></div><div class="activity-copy"><b><?= e($entry['actor']) ?></b> <span><?= $entry['action'] === 'Employee created' ? 'added' : 'updated' ?></span> <?php if ($employee): ?><a href="<?= e(url('employee', ['employee' => $employee['id']])) ?>"><?= e($employee['name']) ?></a><?php else: ?><b><?= e($entry['employee_id']) ?></b><?php endif; ?><p><?= e($entry['action']) ?> · <?= e(implode(', ', array_map(fn($field) => FIELD_LABELS[$field] ?? $field, array_keys($changes)))) ?></p><time datetime="<?= e($entry['at']) ?>"><?= e((new DateTimeImmutable($entry['at']))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('M j, Y · g:i A')) ?></time></div></article>
    <?php endforeach; ?></section>
    <section class="ui-panel dashboard-teams"><div class="ui-panel-head"><div><h2>Departments</h2><p>Employee totals by team.</p></div></div><?php foreach (distinct_values('department', $employees) as $department): ?><a class="department-row" href="<?= e(url('employees', ['department' => $department])) ?>"><span><?= e($department) ?></span><b><?= count(array_filter($employees, fn($employee) => $employee['department'] === $department)) ?></b></a><?php endforeach; ?><?php if (!$employees): ?><p class="dashboard-empty-note">Departments appear when you add employees.</p><?php endif; ?></section>
</div>
<?php app_layout_end(); }
function render_employee_preview(array $employee): void { ?>
<div class="preview-profile-link"><a class="ui-panel-link" href="<?= e(url('employee', ['employee' => $employee['id']])) ?>">Open full profile →</a></div>
<?php render_employee_details($employee, false); }
function render_app(): void {
    $selected = !empty($_GET['selected']) ? find_employee(text_value($_GET, 'selected')) : null;
    $listing = employee_list_page();
    app_layout_start('Employees', 'employees', 'employees-page'); ?>
<h1 class="visually-hidden">Employees</h1>
<section class="employee-list-layout">
    <section class="ui-panel employee-directory" id="employees">
        <form id="directoryFilters" class="directory-filters" action="<?= e(url()) ?>" method="get" hx-get="<?= e(url('directory')) ?>" hx-target="#directory" hx-trigger="input delay:300ms, change, submit, employeeSaved from:body" hx-sync="this:replace" hx-indicator="#searchIndicator" hx-params="not action">
            <input type="hidden" name="action" value="employees">
            <input type="hidden" name="selected" id="selectedEmployee" value="<?= e($selected['id'] ?? '') ?>">
            <input type="hidden" name="page" id="directoryPage" value="<?= $listing['page'] ?>">
            <label class="ui-search"><span aria-hidden="true">⌕</span><input type="search" name="q" id="employeeSearch" aria-label="Search employees by name or email" placeholder="Search name or email…" value="<?= e($_GET['q'] ?? '') ?>"></label>
            <div class="filter-selects"><?php render_filter_options(); ?><button class="ui-btn" type="button" id="clearFilters">Clear</button><span class="htmx-indicator" id="searchIndicator">Searching…</span></div>
            <label class="page-size-label" for="employeesPerPage">Rows per page <select name="per_page" id="employeesPerPage"><?php foreach ([10, 25, 50, 100] as $perPage): ?><option value="<?= $perPage ?>" <?= $listing['perPage'] === $perPage ? 'selected' : '' ?>><?= $perPage ?></option><?php endforeach; ?></select></label>
        </form>
        <div class="employee-selection-toolbar">
            <button class="ui-btn" type="button" id="toggleEmployeeSelection" aria-pressed="false">Select employees</button>
            <div class="selection-actions" id="selectionActions" hidden>
                <button class="ui-btn" type="button" id="selectAllEmployees">Select all matching</button>
                <button class="ui-btn" type="button" id="clearEmployeeSelection">Clear selection</button>
                <span id="employeeSelectionCount" role="status">0 selected</span>
            </div>
        </div>
        <div id="directory"><?php render_directory(); ?></div>
    </section>
    <aside class="ui-panel profile-panel employee-preview-card" id="employeePreview" aria-label="Selected employee details" aria-live="polite"><?php if ($selected) render_employee_preview($selected); else echo '<div class="empty-state preview-empty"><b>Select an employee</b><p>Their details will appear here.</p></div>'; ?></aside>
</section>
<?php app_layout_end(); }
function render_employee_page(array $employee): void {
    app_layout_start($employee['name'], 'employees', 'employee-profile-page'); ?>
<div class="profile-page-heading"><a class="ui-panel-link" href="<?= e(url('employees')) ?>">← Back to employees</a></div>
<section class="ui-panel profile-panel" id="profile"><?php render_profile($employee); ?></section>
<?php app_layout_end(); }
function render_accounts_page(): void {
    app_layout_start('Login accounts', 'accounts', 'accounts-page'); ?>
<section class="ui-panel" id="accounts"><div class="ui-panel-head"><div><h1>Login accounts</h1><p>HR administrators with access to employee records.</p></div><button class="ui-btn" type="button" hx-get="<?= e(url('account-form')) ?>" hx-target="#modalContent">＋ Create login</button></div><div id="accountList" hx-get="<?= e(url('accounts')) ?>" hx-trigger="accountSaved from:body"><?php render_accounts(); ?></div></section>
<?php app_layout_end(); }
