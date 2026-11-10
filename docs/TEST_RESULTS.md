# Verification — 10 November 2026

Runtime: PHP 8.4.25; MySQL 8.4.11 (native) and official mysql:8.4 (Docker). macOS desktop with Colima/Docker. No production customer records or production credentials were used.

| Check | Observed result |
|---|---|
| `php tests/Manor/rules.php` | 15 assertions passed |
| `php tests/Manor/integration.php` | 25 assertions passed on disposable native MySQL |
| `scripts/test-manor-ci.sh` | Same 15 rule + 25 database assertions passed using a separately provisioned disposable Docker database |
| `tests/Manor/web_checks.py` against Compose | 20 HTTP assertions passed |
| One-click launcher | Successful first startup and repeat startup; generated private credentials, healthy services, idempotent migrations and browser opening verified on macOS |
| Backup helper | Successful dump + persistent storage/uploads copy; application restarted |
| Restore rehearsal | Synthetic local backup imported into a separate disposable schema; request/document/quote/payment row counts matched; private document file count matched. This does not replace a production restore drill. |
| Browser inspection | Safari desktop and 390 × 844 responsive preview; homepage and enquiry form inspected; no visible clipping at checked viewport |
| PHP syntax / shell / Python | New PHP files parsed; shell syntax checked; Python launcher/tests compiled |
| Agent parity | New browser-only routes explicitly reviewed and rendered ledger regenerated; gate passes |
| Translation audit | 0 errors; 17 inherited API-message warnings |
| CSS token audit | Both upstream stylesheets pass; public styles use isolated Wonham tokens |
| Upstream final unit run | 1,089 tests; 20,571 assertions; 5 failures and 2 warnings |
| Upstream full integration run | 885 tests; 8,495 assertions; 7 failures before demo-schema correction |
| Targeted compatibility after correction | 16 tests / 46 assertions passed (`DemoSchemaParityTest` + `ParityLedgerTest`) |

## Baseline comparisons and correction

The five failing unit cases are in `RescheduleAvailabilityTest`: two October/November overlap reads, an overnight event and two fall-back event variants. Their hardcoded 2026 dates are now past. Untouched pinned upstream produced exactly the same five failures and two warnings (55 tests / 198 assertions in the targeted baseline run).

Six integration failures are in `PublicBookingUtcContractTest`: five persisted-wall-time booking datasets plus the recovery/conflict-alternatives case. They submit past October/November 2026 bookings and receive `too_soon`. Untouched pinned upstream reproduced all six failures (targeted baseline run: 15 tests / 84 assertions, including media recovery coverage).

The seventh integration failure was introduced by the new tables: the fictional SQLite artifact lacked the Manor schema columns. Added `app/Manor/DemoSchema.php` and wired the fictional seeder to it. Its isolated targeted test then passed, followed by the combined 16-test compatibility check. The full 885-test suite has not been repeated after that correction. No claim is made that the upstream suite is green.

## Covered core behaviour

Draft creation and secure capability; submission and management review; mandatory documents; exact GBP totals; required base and selected optional charges; separated deposits; immutable accepted quote; verified-payment gating; duplicate payment references and verification replay; over-refunds; disabled features; independent rooms; whole-property/partial conflicts in both directions; expiring holds; half-open boundaries; two-process contention on empty inventory; operator-only console; FAQ deletion; valid protected PDF upload/download; denied unrelated access; CSRF; MIME spoofing/SVG rejection; valid public image re-encoding; closed installer; blocked legacy writes in initialized Wonham deployment.

## Not verified / not claimed

Windows/Linux launcher execution; Arabic/RTL; invitation/QR check-in (not implemented and cannot be enabled); production SMTP/TLS/deployment; production backup recovery; device/browser matrix beyond the inspected Safari viewport. The GitHub workflow file has been added; a remote CI run is not claimed unless its result is separately observed.

`main` is not promoted by this work because release gates remain open; the tested MVP candidate is delivered on `develop`.
