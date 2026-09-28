@echo off
cd /d "%~dp0"
set "SS_PHP=php"
if exist "C:\xampp\php\php.exe" set "SS_PHP=C:\xampp\php\php.exe"
echo Open http://127.0.0.1:3100/employee/db/
echo Press Ctrl+C to stop the server.
"%SS_PHP%" -S 127.0.0.1:3100 tools/local-router.php
pause
