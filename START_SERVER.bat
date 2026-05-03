@echo off
echo ============================================
echo   SecondChance Mart - Starting Server...
echo ============================================
echo.

:: Start MySQL
echo [1/3] Starting MySQL...
start "" "C:\xampp\mysql_start.bat"
timeout /t 3 /nobreak >nul

:: Open browser
echo [2/3] Opening browser...
timeout /t 2 /nobreak >nul
start "" "http://localhost:8000"

:: Start PHP built-in server (this window stays open - don't close it!)
echo [3/3] Starting PHP server on http://localhost:8000
echo.
echo  DO NOT close this window while using the site!
echo  Press Ctrl+C to stop the server.
echo.
php -S localhost:8000 -t "%~dp0"
