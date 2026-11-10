# Local setup

Install Docker Desktop on macOS/Windows, or Docker Engine with Compose on Linux. Python 3 is required by the launcher. Start Docker before double-clicking `START_LOCAL.command` (macOS) or `START_LOCAL.bat` (Windows), or running `./START_LOCAL.sh` (Linux).

The launcher chooses a free localhost port on first use, generates random credentials in ignored `.local/compose.env`, starts MySQL 8.4 and PHP 8.4/Apache, waits for health checks, runs migrations and initializes the draft property hierarchy and editable invitation designs. The first login opens the protected setup wizard; English is the only active language unless Admin explicitly enables another. No real customers are seeded. It opens the website and prints the public and admin URLs. The local operator is `admin@wonham.test`; retrieve the generated ADMIN_PASSWORD from `.local/compose.env`. Do not share or commit this file.

Repeated starts migrate and preserve data. Stop with the matching STOP_LOCAL file. Data lives in Docker named volumes and survives stopping/rebuilding. Reset requires typing `DELETE LOCAL DEMO` into `scripts/reset-demo.sh`; it affects the explicitly named `wonham-local` Compose project only. Do not use that project name in production.

Troubleshooting: ensure Docker is running; inspect `docker compose --project-name wonham-local --env-file .local/compose.env logs --tail 100`. If the persisted LOCAL_PORT is now occupied, stop the application and edit only LOCAL_PORT to another available port. The host address is loopback-only. Shell launchers must retain executable permissions.

For development checks without Docker, PHP 8.4 with PDO MySQL, DOM/XML, intl, calendar, mbstring, GD, curl, zip, sodium and MySQL 8.4 are required. `php tests/Manor/rules.php` is database-free. `php tests/Manor/integration.php` uses its own random disposable schema on a local MySQL server (MANOR_TEST_SOCKET, MANOR_TEST_PORT, MANOR_TEST_USER, MANOR_TEST_PASSWORD). It never targets DB_DATABASE from the application.

Current verified preview on the implementation machine: http://localhost:55847 (admin: `/admin/manor`). This is a local development instance, not a public deployment. Its generated local password is in `.local/compose.env`. A fresh checkout chooses its own free port; use the URL printed by its launcher.

The independent portable core test entry point is `./scripts/test-manor-ci.sh` (PHP + Docker required). HTTP checks support `MANOR_TEST_URL` and `MANOR_TEST_CONFIG` for the private local credential file; for Compose use `MANOR_TEST_CONFIG=.local/compose.env`.

Configuration HTTP checks create and restore a recoverable local settings/content snapshot. Their CLI fixture must identify the same APP_URL as the HTTP target. For a native preview use MANOR_TEST_PHP to select PHP. For a container, MANOR_FIXTURE_COMMAND may contain a JSON argument array that runs `php tests/Manor/config_fixture.php` inside that same app container. APP_ENV must be local. Do not run verification scripts against customer production data.
