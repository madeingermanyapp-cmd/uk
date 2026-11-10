# Deployment to Ubuntu / compatible PHP hosting

This is an MVP candidate, not a production approval. Resolve `docs/RELEASE_STATUS.md` gates first. Never copy the local Compose environment or local database into production.

## Ubuntu VPS with Docker

1. Install Docker Engine and Compose from Docker's official Ubuntu instructions. Create a restricted deployment account and checkout the reviewed release commit.
2. Create a mode-0600 environment file outside Git containing APP_ENV=production, unique DB_PASSWORD and DB_ROOT_PASSWORD, ADMIN_EMAIL, a 16+ character ADMIN_PASSWORD, LOCAL_PORT, FORCE_HTTPS and SMTP settings. No default production credentials exist. Use a unique Compose project name such as `wonham-production`.
3. `docker compose --project-name wonham-production --env-file /secure/wonham.env up -d --build --wait`
4. `docker compose --project-name wonham-production --env-file /secure/wonham.env exec -T --user www-data app php scripts/manor-init.php`
5. Place Nginx/Caddy in front of the loopback application port with a valid certificate, fixed server_name and request/upload limits. Redirect HTTP to HTTPS. This Apache container does not trust arbitrary forwarded HTTPS headers. For TLS termination on a trusted loopback proxy, keep container FORCE_HTTPS=false and configure an explicit HTTPS environment in the Apache virtual host or pass trusted proxy HTTPS state at the web-server layer. Ensure PHP sees HTTPS so secure session cookies are used; set FORCE_HTTPS=true only once that is true. Test redirects and Secure cookies before launch.
6. Configure SMTP via environment or the existing system settings interface. Verify real delivery independently; local `log` transport sends no email. The current customer portal provides the tracking link directly; automated venue-workflow notification emails are deferred.
7. Add a host cron to back up daily and monitor `/health`, DB connectivity, disk, mail failures and application logs. The shared-inventory queries ignore expired provisional holds without requiring cron. Existing VoxelBooking cron jobs may be configured separately using the upstream cron secret.
8. Restrict operators, confirm content/privacy/terms, review AGPL corresponding-source offer, and perform live acceptance checks. Keep installer routes unavailable after initialization (existing middleware returns 404).

Persistent data: database, `storage` (private documents, payload keys, logs and sessions) and `public/uploads` volumes. Back up keys with data. Keep private storage non-public. Do not enable directory indexes or PHP execution in uploads. Disable/omit capability URLs from access logs; they are bearer secrets. Apply retention/deletion procedures to requests, documents, inbox messages and backups; upstream customer anonymization does not automatically cover the new Manor tables.

## Conventional PHP/MySQL hosting

PHP 8.4 with PDO MySQL, cURL, mbstring, fileinfo, GD, OpenSSL, sodium, zip and MySQL 8.4. Use the repository's committed dependencies and assets. Document root MUST be `public/`. Apache needs mod_rewrite and AllowOverride All; for Nginx, `try_files $uri $uri/ /index.php?$query_string`, FastCGI PHP, a deny rule for dotfiles, no PHP execution under uploads, and client_max_body_size 28m.

Write configuration to an untracked `.env` outside public. Set UTC storage timezone; venue business rules are Europe/London. Grant the app user only its schema privileges. Give the web user write access only to storage and public/uploads. Run `php scripts/manor-init.php` once with first-admin environment configured; rerun on upgrades to apply additive migrations. Never use root database credentials in production.

Release: back up, stop writes, deploy reviewed source, run migrations, smoke-test, reopen traffic. Roll back source only if schema remains backward compatible; otherwise restore DB and files together. Do not automatically deploy upstream updates over this fork.
