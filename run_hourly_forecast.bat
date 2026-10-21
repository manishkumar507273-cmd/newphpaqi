@echo off
cd /d "%~dp0"
set KERAS_BACKEND=torch
"C:\Users\deeks\AppData\Local\Programs\Python\Python312\python.exe" php-backend\generate_forecast.py >> forecast_cron.log 2>&1
