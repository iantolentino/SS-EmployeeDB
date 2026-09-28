<?php
// Local-only router reproduces the cPanel /employee/db/ folder URL.
$root = dirname(__DIR__);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if ($path === '/' || $path === '/employee/db') {
    header('Location: /employee/db/', true, 302);
    exit;
}
$prefix = '/employee/db/';
if (strncmp($path, $prefix, strlen($prefix)) !== 0) { http_response_code(404); exit('Page not found.'); }
$relative = substr($path, strlen($prefix));
if ($relative === '' || $relative === 'index.php') {
    $_SERVER['SCRIPT_NAME'] = '/employee/db/index.php';
    $_SERVER['PHP_SELF'] = '/employee/db/index.php';
    require $root . '/index.php';
    exit;
}
$static = ['employee-db-ui.css', 'employee-db-app.css', 'employee-db-app.js', 'vendor/htmx.min.js'];
if (preg_match('/^assets\/[a-zA-Z0-9_-]+\.(png|svg|webp|jpg|jpeg|ico)$/', $relative, $match) && is_file($root . '/' . $relative)) {
    $types = ['png' => 'image/png', 'svg' => 'image/svg+xml', 'webp' => 'image/webp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'ico' => 'image/x-icon'];
    header('Content-Type: ' . $types[$match[1]]);
    header('X-Content-Type-Options: nosniff');
    readfile($root . '/' . $relative);
    exit;
}
if (in_array($relative, $static, true) && is_file($root . '/' . $relative)) {
    header('Content-Type: ' . (substr($relative, -4) === '.css' ? 'text/css; charset=utf-8' : 'text/javascript; charset=utf-8'));
    header('X-Content-Type-Options: nosniff');
    readfile($root . '/' . $relative);
    exit;
}
http_response_code(404);
echo 'Page not found.';
