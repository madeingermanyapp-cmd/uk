# Portable deployment

Target: **Wonham Manor House — Full Production Release**. Code on `develop` remains a development release until the acceptance gates in `RELEASE_STATUS.md` pass. Final domain, server, SMTP and content approvals are configured later; they do not block local implementation. Do not copy preview credentials or synthetic customer fixtures into a public deployment.

## Bootstrap with Docker Compose

Use Docker Engine with Compose on a compatible Linux server. Keep a private mode-0600 environment file outside Git with `APP_ENV=production`, unique `DB_PASSWORD` and `DB_ROOT_PASSWORD`, first-admin `ADMIN_EMAIL`/`ADMIN_PASSWORD` (16+ characters), a loopback `LOCAL_PORT` and optional temporary `APP_URL`. The standard Compose stack contains PHP 8.4/Apache, MySQL 8.4 and the background notification worker. It binds the app to localhost and keeps database/storage/uploads in named volumes.

```sh
docker compose --project-name wonham-production --env-file /secure/wonham.env up -d --build --wait
docker compose --project-name wonham-production --env-file /secure/wonham.env exec -T --user www-data app php scripts/manor-init.php
```

Initialization is idempotent: migrations run, a first operator is created only if none exists, and existing bookings/settings/passwords remain intact. The worker begins processing once its tables exist. Open `/admin/manor/settings/setup`; complete optional content, email and payment settings later. A temporary HTTP hostname/IP works for initial setup. Never expose a public HTTP admin login as the final deployment.

## Optional HTTPS edge

`docker-compose.https.yml` supplies a portable Caddy TLS proxy. Set `SITE_ADDRESS` to the eventual hostname, `APP_URL` to its HTTPS origin and choose unused `PROXY_SUBNET`/`PROXY_ADDRESS` values for this deployment. No provider or public IP is embedded. DNS and firewall/TLS issuance remain infrastructure responsibilities.

```sh
docker compose -f docker-compose.yml -f docker-compose.https.yml --project-name wonham-production --env-file /secure/wonham.env up -d --build --wait
```

The proxy exposes configurable HTTP/HTTPS ports, keeps certificate state in persistent volumes, forwards to Apache and overwrites forwarded headers. The application trusts TLS/client-IP headers only from the configured proxy address; direct clients cannot spoof HTTPS or their rate-limit address. Secure cookies and HTTPS redirects are enabled by the overlay. Internal loopback health checks remain accessible. If using another reverse proxy, set `TRUSTED_PROXIES` to its exact addresses/CIDRs and ensure it overwrites forwarded headers. Leave this empty for direct PHP/web-server TLS. Never trust arbitrary public addresses.

For a local TLS rehearsal, use `SITE_ADDRESS=localhost`, loopback `HTTPS_BIND_ADDRESS`, unused HTTP/HTTPS host ports and Caddy's local CA; explicitly trust its exported CA in the test client. This is a local certificate, not evidence of publicly trusted production TLS. Do not bypass browser certificate warnings.

## Business configuration

Use Admin for canonical public origin, branding, contact/SEO, venue inventory/rules/prices, optional modules, content drafts/rights/publication, bank instructions/payment methods and encrypted SMTP/template/preferences. Changes persist in the database and require no application rebuild. English is the only active language initially; the administrator can explicitly enable Arabic/RTL. Infrastructure database credentials, DNS, server access, TLS keys and container volumes stay outside business settings.

Use **Test Email** to establish transport acceptance; inspect delivery/error records. A local log or SMTP sink does not establish actual mailbox delivery. The Compose worker records real heartbeats and retries queued mail. No host cron is needed for that worker; schedule backups independently. Readiness checks show actual configuration, DNS resolution, HTTPS request state, writable storage, job/backup timestamps, content/media approval and payment/security state.

## Conventional PHP/MySQL hosting

PHP 8.4+, PDO MySQL, cURL, fileinfo, GD (JPEG/PNG/WebP), mbstring, OpenSSL, sodium, zip, DOM/XML, intl and calendar; MySQL 8.4. Use committed dependencies/assets or `composer install --no-dev --no-interaction`. Set document root to `public/`. Apache needs rewrite/headers and AllowOverride; Nginx needs `try_files $uri $uri/ /index.php?$query_string`, dotfile protection, a 28 MB request limit and denial of PHP execution under uploads. Deny all `/uploads/manor/`; current Manor media/documents are served through authorized controllers from storage outside the web root.

Keep `.env` private, disable debug/display_errors, use UTC storage and Europe/London venue rules, restrict DB access to its schema and write access to storage/uploads. Set `FORCE_HTTPS=true` only with working direct or trusted-proxy TLS. Run `php scripts/manor-init.php`; run `php scripts/manor-worker.php` under a process supervisor. Container Apache excludes private request/invitation/document/quotation/recovery paths from access logs; configure the same rule on other web servers and proxies. These URLs are bearer capabilities.

## Operations and upgrades

Back up database, all storage (especially encryption keys and private media/documents) and uploads together. Use `scripts/backup.sh` and `scripts/restore.sh` as documented in `BACKUP_RESTORE.md`. Encrypt backups off-server and test restoration. Retention must cover requests, documents, correspondence, inbox, audit/payment records and backup copies according to the operator's adopted policy; the upstream customer anonymizer does not automatically erase every Manor record.

For each release: back up, stop writes, deploy the reviewed commit, apply migrations, verify health/private files/booking conflicts/notifications, then reopen traffic. Roll back source alone only if the schema is compatible; otherwise restore DB/files/keys together. Retain AGPL notices and provide the exact corresponding application source to network users. Never publish credentials or customer records with source. Do not overwrite this fork with an unreviewed upstream upgrade.
