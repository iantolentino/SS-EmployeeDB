# SS Employee DB

Employee directory built with **PHP, SQLite, HTMX, HTML, CSS, and JavaScript**. No Node.js server, npm installation, Composer, build step, or MySQL database is required.

## Run locally

Requires PHP 8.0+ with `pdo_sqlite` and `mbstring` (PHP 8.2+ recommended for hosting).

On Windows with XAMPP, double-click `start-local.cmd`, then open:

**http://127.0.0.1:3100/employee/db/**

Or run from this repository:

```powershell
& 'C:\xampp\php\php.exe' -S 127.0.0.1:3100 tools/local-router.php
```

Local login: **admin@ss.local** / **EmployeeDB2026!**

The first local visit creates the administrator and eight fictional sample employees. Records persist in `data/employees.sqlite`. These local credentials and sample records are not included in the cPanel package.

## Employee features

- Separate Dashboard, Employees, employee profile, and Login accounts pages. The dashboard shows totals and recent edits. Click an employee once for a compact, editable details card on the right. Double-click the employee row or use Open full profile for complete details, edit logs, and position history. The preview card stays within the desktop viewport.
- Login, logout, and additional HR login accounts.
- Instant, case-insensitive search by employee name or email, plus department, client, and status filters.
- Paginated employee list with 10, 25, 50, or 100 rows per page. Search and filter changes return to page 1. The sidebar stays white to match the supplied logo.
- Add and edit employees: birthday, hire date, address, position and its start date, department, client name, TL name, optional supervisor, and optional site location.
- Automatic position and assignment history with start and end dates. Changing position, department, client, TL, or supervisor requires a later effective date and preserves the earlier assignment.
- Add older positions manually, including dates and previous assignments. Dates cannot overlap or precede the hire date.
- A visible edit log below each profile shows the latest change, previous and new values, the editor's name and account email, and the date/time with timezone. Earlier changes remain available. Birthdays, hire dates, assignments, and position history are logged; saving unchanged details preserves the last actual edit.
- Export CSV exports all employees when no filters or selections are set. Client, department, status, and search filters limit exports to matching employees across all pages. Use Select employees to check individual employees or select all matching employees across pages; exporting then includes only the checked employees. Changing search or filters clears the export selection.
- Light/dark theme and responsive layout.

All login accounts currently have HR administrator access to all employee records. Employee profiles and login accounts are created separately.

## Upload to cPanel

See [DEPLOYMENT.md](DEPLOYMENT.md). The target folder is `public_html/employee/db/`, for **https://stratastaff.com/employee/db/**.

Build a clean upload ZIP with:

```powershell
powershell -ExecutionPolicy Bypass -File tools/build-cpanel-package.ps1
```

The generated `dist/SS-EmployeeDB-cpanel.zip` includes a configured first-run setup key. The key is written separately to `dist/SETUP-KEY.txt` for the person creating the administrator.

HTMX 2.0.11 is bundled in `vendor/`. Your logo is loaded from `assets/stratastaff-logo.png` and included in the upload package. Export CSV and Add employee are in the top navbar. The repository contains the working PHP app and deployment tools.
