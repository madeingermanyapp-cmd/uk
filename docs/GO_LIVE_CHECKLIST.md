# Go-live acceptance checklist

Use this against the intended release commit and deployment. A checked local test does not approve final business content or public infrastructure. Keep `main` unpublished/unpromoted until acceptance is complete; only `develop` currently exists on the target remote.

## Application and administration

- [x] Source, migrations and dependencies retained under their licences; upstream history preserved.
- [x] Local public website and operator-only venue console render.
- [x] Settings persist; English is the only initially active language; URL language selection cannot bypass Admin enablement.
- [x] First-run setup completes and rejects subsequent submissions; ongoing account changes require current password.
- [x] Venue hierarchy supports independent properties and hourly/day/night requests.
- [x] Shared-resource conflicts, provisional holds, turnover adjacency and concurrent reservation attempts tested.
- [x] Required upload policy and protected document access tested.
- [x] Commercial request/review/quote/accept/payment verification/confirmation workflow tested end-to-end.
- [x] Immutable quotation snapshots, exact amounts, deposits, optional services and refund/replay limits tested.
- [x] Authorized PDF output and explicitly enabled Arabic translation/shaping checked in development; final PDF visual review recorded separately.
- [x] Private correspondence, recovery rotation and one-use token checks pass.
- [x] Invitations, RSVP, actual QR decoding, staff-only check-in, duplicate prevention and attendance export tested.
- [x] SMTP sink delivery, encrypted credential storage and password omission tested.
- [x] Background worker and real readiness indicators implemented and exercised.
- [x] Complete functional/security suite green; record final totals in TEST_RESULTS.
- [ ] Complete current responsive public/Admin visual acceptance at desktop/tablet/phone widths; use approved or explicitly marked draft assets.

## Portability and operations

- [x] macOS launcher first/repeat startup tested.
- [x] Linux launcher executed in Linux with an independent Compose deployment and initialization.
- [ ] Windows launcher actually executed with Python/Docker Desktop; batch wrapper inspection alone is insufficient.
- [x] Separate Compose backup/restore rehearsal compares authentication, request references and private document bytes.
- [x] Backup SHA-256 integrity verification rejects altered files; integrity-checked helper restore passed.
- [x] Production-mode local HTTPS proxy verifies certificate trust, secure session cookies, HSTS and disabled installer.
- [x] Untrusted forwarded TLS/IP headers are rejected; trusted-proxy chain tests pass.
- [ ] Final current-source image and integrity-restore helper revisions verified together.

## Later deployment configuration through Admin or secure bootstrap

- [ ] Configure intended public origin/domain and verify DNS from the deployment environment.
- [ ] Configure trusted public TLS and inspect real Secure/HttpOnly/SameSite cookies and redirects.
- [ ] Complete property/unit facts, capacity, opening rules, rates and payment/deposit policies in Admin.
- [ ] Upload and explicitly approve publication rights for final photographs/video/text; publish reviewed content and legal pages.
- [ ] Configure SMTP in Admin and verify actual external mailbox receipt and failure/retry behavior with an authorized recipient.
- [ ] Configure real bank instructions/external payment links and manual verification responsibilities; test a deliberately authorized payment separately.
- [ ] Establish scheduled encrypted off-server backups, retention/restore responsibility, monitoring and recovery instructions.
- [ ] Confirm exact corresponding application source is accessible to network users and excludes customer/runtime secrets.
- [ ] Run Deployment Readiness on the real release environment; investigate every failed/pending check rather than overriding it.
- [ ] Complete release review and protect/promote `main` only after all mandatory acceptance gates pass.

No final domain/server/SMTP credential or content approval is requested to complete development. These remain operator-controlled configuration and publication decisions at deployment time.
