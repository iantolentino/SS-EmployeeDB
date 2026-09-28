# cPanel deployment

Target URL: **https://stratastaff.com/employee/db/**

1. In cPanel, select PHP 8.2 or newer and enable **pdo_sqlite** and **mbstring**. No MySQL database is needed.
2. In File Manager, create/open **public_html/employee/db/**. Upload `dist/SS-EmployeeDB-cpanel.zip` and extract it inside this folder. `index.php` should be directly inside `db/`.
3. Open `config.php`. The packaged default stores SQLite in **/home/YOUR_CPANEL_USER/employee-db-private/employees.sqlite**, outside `public_html`. If your domain uses a different document root, set `database_path` to an absolute path to a private writable folder outside that root. The app creates the folder/database automatically. Use owner write permission (normally 700 on the private folder); do not use 777.
4. Open **https://stratastaff.com/employee/db/**, enter the setup key from `dist/SETUP-KEY.txt`, and choose your administrator name, email, and password (12+ characters).
5. Log in and add employees. Once the first administrator exists, the setup form is disabled. The uploaded app starts with an empty employee directory.

No command line installation, dependency installation, asset compilation, URL rewrite configuration, or Node.js hosting is needed. HTMX is included locally. URLs and asset paths adapt automatically to `/employee/db/`.

## Uploading manually

Upload these files only:

- `index.php`, `bootstrap.php`, `views.php`, `.htaccess`
- `employee-db-ui.css`, `employee-db-app.css`, `employee-db-app.js`
- `vendor/htmx.min.js`, `vendor/LICENSE`
- `assets/stratastaff-logo.png`
- `config.php`, made from `config.example.php`

Set a unique `setup_token` with at least 24 characters in `config.php` before visiting the setup screen. Keep `seed_demo` set to `false` for an empty directory.

Do not upload `.git/`, `.local/`, `data/`, `dist/`, development tools, or local sample records. Keep the SQLite database in its private storage folder when updating the app.

## Backups and updates

Back up the private `employees.sqlite` file while the app is idle, together with `config.php`. The database includes employees, users, position history, and the change log. Take the app offline briefly for a consistent backup if people are actively editing records. For an update, replace only the application files and preserve the existing `config.php` and private database.

## Common setup issues

- **Enable pdo_sqlite:** turn on the extension for the PHP version selected for this domain.
- **Database folder is not writable:** create the private folder as your cPanel user and set `database_path` correctly. If hosting limits PHP to public_html, ask the host to allow the private folder for this domain.
- **HTTP 500 after upload:** read cPanel's Errors log; check PHP version, extensions, file ownership, and whether your host allows the supplied `.htaccess` directives.
- **Setup key rejected:** use the key generated with this particular ZIP, or the current `setup_token` in `config.php`.

Sessions use HTTP-only cookies, CSRF tokens, and an eight-hour inactivity timeout. Passwords use PHP's password hashing. Login attempts are rate limited. All accounts have full HR administrator access; create accounts only for people who should see and edit the directory.
