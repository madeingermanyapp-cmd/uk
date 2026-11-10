# Consistent backup and restoration

Run from the repository root with Docker Compose, Python 3 and the same project/env file used for deployment. Backups contain private customer documents, SMTP/outbox encryption keys and authentication material. Use a restricted destination outside Git; encrypt and store an off-server copy.

```sh
scripts/backup.sh wonham-production /secure/wonham.env /secure/backups/2026-11-10
```

The helper stops app/worker writes, takes a transactional MySQL dump, copies storage/uploads, records source identity and creates/verifies a SHA-256 file manifest. It records successful completion in Admin Readiness only after the artifacts succeed and restarts services even if backup fails. Use a fresh directory each time. Hashes detect accidental corruption; they do not establish authenticity against an attacker who can replace the manifest.

Restore first into a separate deployment and verify before replacing live data. The explicit replacement flag authorizes replacing the selected deployment's database/files; take a fresh safety backup beforehand.

```sh
scripts/restore.sh wonham-restore /secure/restore.env /secure/backups/2026-11-10 --replace-selected-deployment
```

The helper checks the complete artifact manifest before stopping services, imports the database, replaces storage/uploads, restores ownership and runs idempotent migrations. It leaves services stopped on failure so a partial restoration is not served. DB/schema credentials belong to the target environment; operator accounts and encrypted business settings come from the backup. Do not lose storage encryption keys. Keep the source version compatible with the snapshot's schema; new unrelated tables on an already-used target should be avoided by restoring into fresh volumes.

Verify operator login, booking/request references, protected document bytes, decryption of stored settings, quotation/payment histories, public approved media and background jobs. A successful SQL import alone is insufficient. The implementation rehearsal restored a separate local Compose project and compared request references/private document bytes and authentication against the source preview. Final infrastructure backup scheduling/off-server retention remains deployment configuration, visible as pending until established.
