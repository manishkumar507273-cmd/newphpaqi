@echo off
set "PATH=%PATH%;C:\xampp\php"
cd /d "%~dp0php-frontend"
echo Starting AQI Telemetry Platform on http://localhost:8001 ...
php -S localhost:8001 router.php
