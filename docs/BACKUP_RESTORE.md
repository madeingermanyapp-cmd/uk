# Backup and restore

Back up the MySQL database and both persistent application volumes together. Store encrypted copies off-server with restricted access and a retention policy. Do not commit dumps, archives, private documents, SMTP secrets or payload keys.

For consistent backups, enter a maintenance window and stop the application while leaving MySQL running. Run `mysqldump --single-transaction --routines --triggers --no-tablespaces` against the application database using a restricted credential passed via a protected client defaults file, not a command-line password. Archive the `storage` and `uploads` volumes. Include a protected copy of production configuration and deployed commit SHA. Restart the app and verify health.

Restore into an isolated environment first: create a fresh database and volume set, import the dump, restore storage/uploads with the web-user ownership, then configure the matching source commit. Verify authentication, quote snapshots, payment ledger totals, private document access and shared inventory before switching production. Restoring DB without documents/keys is incomplete. Record the recovery point and duration. A successful backup command is not proof that restore works.

Local-only reset is `scripts/reset-demo.sh`, with its explicit confirmation. It is not a production recovery tool. A full production backup/restore drill remains a release gate until evidenced.

A concrete Compose backup helper is provided: `./scripts/backup.sh PROJECT /secure/environment.env /absolute/private/backup-directory`. It stops app writes, takes a transactional dump, copies storage/uploads and records the source commit, then restarts the app through a trap. It refuses to overwrite an existing database dump. The destination contains sensitive data and must be encrypted and excluded from source control. Never use a publicly served directory.
