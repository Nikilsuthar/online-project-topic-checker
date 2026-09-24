@echo off
title Project Topic Checker - keep this window open
cd /d "%~dp0"
set PTC_DB_DRIVER=sqlite
echo.
echo  Online Project Topic Availability Checker
echo  Opening http://localhost:8080  (close this window to stop)
echo.
start "" http://localhost:8080
"%~dp0_tools\php\php.exe" -S localhost:8080 -t "%~dp0."
pause
