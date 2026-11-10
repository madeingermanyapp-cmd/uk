# Wonham Manor House — Full Production Release

Status: development and acceptance verification on `develop`. `main` is not promoted. No final domain, production server, SMTP credential or media approval is requested during development: business configuration is administered later in the dashboard, and infrastructure bootstrap stays outside it.

Implemented modules include full Admin configuration/setup, CMS/media rights, venue/hour/day/night bookings and shared inventory, documents/review/messages/recovery, quotations/PDFs/payments/refunds, invitations/RSVP/QR/attendance, notifications/worker and portable deployment/backup/restore. English alone is enabled by default; additional languages require an explicit Admin decision. The interface and backend enforce that decision.

Verified evidence and exact scope are in `TEST_RESULTS.md` and `REQUIREMENTS_TRACEABILITY.md`. Remaining acceptance work must be identified honestly: final responsive visual review, complete current-image security/TLS deployment rehearsal and cross-platform launcher evidence (especially actual Windows execution). A local SMTP sink is not proof of final mailbox deliverability. Public DNS, trusted production TLS, off-server backups, payment-provider instructions and business content/media approval remain real readiness checks to configure later. These are deployment gates, not reasons to stop independent implementation.

Promote `main` only after mandatory workflows and current critical/security/concurrency tests pass, the full design is reviewed, launchers/deployment are verified, and the intended release environment's real readiness checks and content approvals are satisfied. An intermediate development push is not final delivery.
