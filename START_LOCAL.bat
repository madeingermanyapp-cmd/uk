@echo off
cd /d "%~dp0"
where py >nul 2>nul
if errorlevel 1 (echo Python 3 is required. Install Python and Docker Desktop, then retry. & pause & exit /b 1)
py -3 scripts\local-launch.py
if errorlevel 1 pause
