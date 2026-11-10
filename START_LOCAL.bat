@echo off
cd /d "%~dp0"
where py >nul 2>nul
if not errorlevel 1 goto run_py
where python >nul 2>nul
if not errorlevel 1 goto run_python
echo Python 3 and Docker Desktop are required. Install them, start Docker, then retry.
pause
exit /b 1
:run_py
py -3 scripts\local-launch.py
set "MANOR_EXIT=%errorlevel%"
goto finish
:run_python
python scripts\local-launch.py
set "MANOR_EXIT=%errorlevel%"
:finish
if not "%MANOR_EXIT%"=="0" pause
exit /b %MANOR_EXIT%
