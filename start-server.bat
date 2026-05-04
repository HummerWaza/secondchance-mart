@echo off
title SecondChance Mart - Local Server
echo.
echo  ================================
echo   SecondChance Mart Local Server
echo  ================================
echo.
echo  Starting PHP server on http://localhost:8000
echo  Press Ctrl+C to stop the server.
echo.

REM Try common PHP locations
IF EXIST "C:\php\php.exe" (
    start "" "http://localhost:8000"
    "C:\php\php.exe" -S localhost:8000
) ELSE IF EXIST "C:\xampp\php\php.exe" (
    start "" "http://localhost:8000"
    "C:\xampp\php\php.exe" -S localhost:8000
) ELSE (
    start "" "http://localhost:8000"
    php -S localhost:8000
)

pause
