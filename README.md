# SS Employee DB

Browser-based employee database prototype for managing:

- employee users and positions
- departments and reporting relationships
- client assignments and copy-ready employee lists
- site locations
- account creation and login
- webhook access to employee database events
- light and dark modes

## Start here

Open `employee-db-login.html` to test the login flow. Successful login redirects directly to the main employee database dashboard.

Open `employee-db-system.html` to go directly to the merged command-center and employee-profile system.

## Copying employee data

The main system can copy selected, filtered, client-specific, or all employee records as tab-separated data suitable for pasting into Excel or Google Sheets. Filtered data can also be exported as CSV.

## Current status

This repository is a front-end prototype with sample data stored in JavaScript. Production use requires a backend database, authentication service, access controls, and a live webhook service.
