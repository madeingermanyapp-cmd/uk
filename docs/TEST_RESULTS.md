# Verification evidence — 10–11 November 2026

These are development results, not a claim that final production acceptance is complete. Tests use synthetic records and reserved `.test` email recipients. No real external payment or public publication is performed. Local content/feature flags are restored after configuration checks; workflow fixtures are cancelled.

| Check | Result |
|---|---|
| `php tests/Manor/rules.php` | 15 assertions passed in the final native and disposable Docker runs |
| `php tests/Manor/integration.php` | 55 assertions passed in disposable MySQL, including two-process inventory race, nights/day/hour, invitations/QR and access recovery |
| `tests/Manor/config_web_checks.py` | 89 HTTP assertions passed: Admin screens, English-only default, explicit Arabic enable/disable, protected media, rights/publication/removal, password hiding |
| `tests/Manor/workflow_web_checks.py` | 34 HTTP assertions passed against the portable Compose preview: enquiry/review/messages/quotation/English PDF/payment/confirmation/invitation/RSVP/QR scanner/staff check-in/duplicate/CSV; fixture cancelled. Explicitly enabled Arabic PDF is separately verified in the core suite and visually rendered |
| Full upstream unit + integration | **1,978 tests, 29,267 assertions passed** in the isolated runner |
| Focused added proxy + schema/parity compatibility | 20 tests, 55 assertions passed |
| Admin venue HTTP | 19 assertions passed: actual Admin property/suite forms, public two-night request, maintenance closures independent of guest rules, parent deactivation and backend rejection |
| Local SMTP sink | Test Email accepted by Mailpit; encrypted secret storage and omitted password responses verified. Not a claim of live mailbox delivery |
| Compose | Updated app, MySQL and worker healthy; repeated initialization preserves records |
| Linux launcher | Executed the Linux shell/Python launcher in Linux; independent app/db/worker stack initialized and cleaned up |
| HTTPS deployment | 5 local production-mode checks passed: CA-verified TLS, English-only site, Secure cookie, HSTS, disabled installer |
| Separate restore rehearsal | Operator authentication, identical request references and private document SHA-256 matched source preview |
| i18n audit | 0 errors; 13 warnings cover legacy upstream API strings and missing full Arabic dictionaries for unrelated upstream modules |
| CSS token audit | Passed for existing design-token stylesheets |
| PHP/shell/Python | Manor/migration/template PHP lint passed; shell syntax passed; Python compilation passed |
| Composer | Manifest/lock validation passed after production QR-decoder classification; no advisories reported during update |

## Investigated compatibility failures

Pinned untouched upstream reproduced five unit and six integration failures caused by hardcoded October/November 2026 booking fixtures becoming past dates. Added an explicitly test-only Clock and froze the affected historical fixtures; production always uses real time. No tests were skipped. A missing fictional SQLite demo schema was corrected with DemoSchema. Two older locale tests assumed Arabic dictionaries did not exist; they now exercise an untranslated language while asserting Arabic exists. New browser-only Manor routes have explicit parity decisions; no unsupported agent API parity is claimed.

A further reschedule happy-path fixture collided with a shared +3-day booking only after 10:00 UTC: that shared booking is created at the current hour. Removed that unrelated known test fixture from the isolated happy-path scenario; all 16 reschedule endpoint tests passed, followed by the complete 1,978-test suite. Production availability behavior was not weakened. Later focused and complete runs include trusted-proxy security checks. Native and Docker core checks pass. CMS mutation and publication checks restore their configuration snapshot. English-only public layout and Arabic PDF were inspected; remaining full visual acceptance and Windows runtime verification are tracked separately; do not infer successful Windows execution, public DNS/TLS, actual mailbox receipt, media rights or off-server scheduled backup from local checks.

Latest follow-up: internal pages now have separate database drafts, authenticated previews, explicit approved publication and unpublication; editing a draft preserves live content. The 89 configuration checks cover these transitions. Compatible npm security updates pass all 253 frontend tests and regenerate committed production assets. The contact cards and multi-column footer follow the supplied reference; final pixel-level design acceptance remains open. Local operator credentials are provided in ignored `.local/ADMIN_ACCESS.txt`, never in public source.

Image-management follow-up: readable section/venue selectors, library thumbnails/reuse, selected-placement replacement with stale-form protection, and gallery ordering are implemented. The CMS draft baseline now uses the latest publication and archives older drafts, preventing a subsequent image edit from reviving an obsolete draft. Frontend suite: 253 tests passed; database core: 55 assertions passed. Reference/new photographs were verified via public HTTP image responses and desktop browser rendering.

## Estate content and gallery — 11 November 2026

Six supplied original photographs were saved from the actual attachments and uploaded via Admin to both native and Docker previews. Four public pages and nine protected owner-review pages were populated from the supplied Word brief; Plan Your Visit also retains a separate unconfirmed draft. Public HTTP checks confirm all six gallery images and that owner-review pages return 404. The complete original document text is retained in a private CMS draft. See `CONTENT_MAPPING.md`.

The current frontend suite passes 255 tests, including original-photo dialog navigation, modified-click fallback and focus restoration. Current configuration checks pass 89 HTTP assertions and restore the populated site afterward. Desktop gallery layout, original-photo display, Next/Close controls and mobile grounds layout were visually reviewed. Adjusted the dialog grid so the complete photograph, caption and navigation fit within the viewport. The CSS and labels remain isolated from the upstream booking UI.

A core run exposed an intermittent QR decode failure. Synthetic diagnostics reproduced scanner rejection for some generated payload/matrix combinations. QR generation now provides a generous quiet zone and verifies the rendered output with the existing staff decoder, trying alternate correction levels if required; capability-bearing decoder exceptions are never logged. The subsequent full disposable core run passed all 55 assertions, including real QR decoding. No check was skipped. This does not remove the remaining full-release acceptance gates.
