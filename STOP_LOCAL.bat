@echo off
cd /d "%~dp0"
docker compose --project-name wonham-local --env-file .local/compose.env stop
