<?php
// Copy to config.php before uploading. Use a private folder outside public_html.
return [
    'database_path' => dirname(__DIR__, 3) . '/employee-db-private/employees.sqlite',
    'timezone' => 'Asia/Manila',
    'seed_demo' => false,
    'setup_token' => 'replace-with-a-random-setup-key-at-least-24-characters',
];
