# Full requirements traceability

Target: **Wonham Manor House — Full Production Release**. Status labels: implemented = code exists; verified = named checks passed; in progress = required implementation/verification remains; external = requires named infrastructure or business input. A development push is not final delivery.

| Requirement | Implementation / database | Admin / public interface | Tests / verification | Status |
|---|---|---|---|---|
| Reference design, responsive sections | templates/manor/sections; public/manor/style.css; supplied PDF | /admin/manor; / | Safari desktop + 390px inspected; full-page/device review pending | In progress |
| CMS pages, sections, navigation, SEO | Manor Settings; manor_config; page module pending | Structured settings editor; public sections | Content flags and FAQ removal tested; visual editors/pages pending | In progress |
| Images, videos, ordered galleries, safe deletion | Manor Files; config media | Upload editor; venue gallery | MIME rejection/re-encoding tested; deletion/variants pending | In progress |
| Entire/partial/meeting/hall hierarchy | Manor Inventory/Service; migration 043 units/resources | Inventory forms; enquiry selector | Native/Docker integration conflict tests pass | Implemented + core verified |
| Nightly accommodation mode | Existing ResourceCalculator; Manor accommodation integration pending | Hotel flag and unit mode; nightly UI pending | Nightly cross-mode E2E pending | In progress |
| Draft/submitted/review/more-info/reject/cancel/expire/confirm | Manor Rules/Service; manor_requests/history | Request console; private portal | State transitions and payment-gated confirmation tested | Implemented + core verified |
| Configurable documents, protected storage/download | Manor Files/Service; manor_documents | Policies/unit selection; portal upload | HTTP authorization, spoofing and storage isolation pass | Implemented + core verified |
| Shared inventory, holds and concurrent reservations | Manor Inventory; manor_allocations; canonical bookings | Hold/block/reschedule controls | Two-process empty-inventory race and bidirectional exclusivity pass | Implemented + core verified |
| Quotations, base/optional/deposit/quantity/tax/visibility | Manor Rules/Service; manor_quotes | Structured quote builder; customer acceptance | GBP totals, selections, immutable snapshots pass; visual builder/PDF pending | In progress |
| Payment links/wire, receipts/verification/refunds | Manor Service; manor_payments/history | Ledger/payment instructions; private portal | Verification replay/duplicate references/over-refund gates pass | Implemented + core verified |
| Invitations/templates/guest management | New invitation module and migration pending | Template editor/event guest list/public invitation pending | Full E2E pending | In progress |
| RSVP and guest permissions | Invitation module pending | Private invitation / RSVP pending | RSVP/token tests pending | In progress |
| QR token/rendering/scanning/duplicate detection/manual override/report | Invitation module pending | Scanner/check-in and report pending | QR decoding/permissions/concurrency tests pending | In progress |
| Feature flags for every module/mode | Manor Settings and server-side guards | Console flags; conditional public UI | Existing flags verified; invitation/QR gates pending implementation | In progress |
| English + Arabic/RTL | Locale infrastructure; lang/en/manor; lang/ar pending | Language selector/content translations pending | English audit passes; Arabic E2E/RTL pending | In progress |
| Secure customer access/recovery/messages | Hashed capability requests; protected documents | Private portal; recovery/rotation/messages pending | Capability isolation passes; complete lifecycle pending | In progress |
| Working notifications/SMTP/retries | Existing Mailer/Outbox; Manor notifications pending | Notification status/retry controls pending | Local SMTP sink + workflow tests pending; live SMTP external | In progress / external |
| One-click Windows/macOS/Linux | START/STOP files; local-launch.py; Compose PHP8.4/MySQL8.4 | Local launcher | macOS first/repeat launch verified; Windows/Linux execution pending | In progress |
| Portable Docker / conventional hosting | Dockerfile/Compose/.env.example; deployment docs | Host configuration | Local Compose verified; production deployment external | In progress / external |
| Backup/restore | backup.sh; docs; persistent volumes | CLI; restore helper pending | Synthetic local DB/doc restore verified; production drill external | In progress |
| Upstream test failures | Baseline comparison evidence in TEST_RESULTS | N/A | 5 unit + 6 integration past-date failures reproduced upstream; deterministic fixture correction pending | In progress |
| Security / retention / production configuration | Existing auth/CSRF/throttling; Manor file and inventory guards | Operator console / private portal | Core authorization/upload tests pass; full security/retention/deployment audit pending | In progress |
| Source history/licensing/Git delivery | AGPL LICENSE/NOTICE; upstream pinned cbf54db; develop | Footer source link; target GitHub | Secret/path checks pass; incremental pushes pending | In progress |
| Approved property facts/content/media rights | Owner input required | CMS content confirmation | No fabricated capacities/contact details used | External |

Release promotion requires every mandatory workflow verified, the entire public site visually reviewed, all critical/security/concurrency checks passing, launchers verified and production deployment/configuration/operations tested. No unresolved mandatory item is silently deferred.
