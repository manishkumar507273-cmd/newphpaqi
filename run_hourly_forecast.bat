@echo off
cd /d "%~dp0"
set KERAS_BACKEND=torch
where python >nul 2>nul
if %ERRORLEVEL% equ 0 (
    python php-backend\generate_forecast.py >> forecast_cron.log 2>&1
) else if exist "%LOCALAPPDATA%\Programs\Python\Python312\python.exe" (
    "%LOCALAPPDATA%\Programs\Python\Python312\python.exe" php-backend\generate_forecast.py >> forecast_cron.log 2>&1
) else (
    py php-backend\generate_forecast.py >> forecast_cron.log 2>&1
)
