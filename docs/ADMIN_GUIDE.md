# Management guide — Wonham Manor House

Sign in at `/admin/login`, then open `/admin/manor`. The venue console requires an operator account. Existing business-user accounts cannot manage private venue enquiries. Use **Settings → Administrator account** to change your name, email or password; the current password is required. The one-click launcher writes your local username and initial password to `.local/DOCKER_ADMIN_ACCESS.txt` (excluded from Git, mode 0600 where supported). This file is for the Docker preview. If you change your password in Admin, use the new password; repeated startup never resets it. Keep the generated bootstrap credential file private.

## Setup and settings

Open **First-run setup** after initialization. Confirm the administrator, website name, temporary public origin, property address, initial booking modes and optional payment instructions. Optional email and content work can be completed later. Completion locks the wizard; ongoing settings remain available. The launcher creates the first administrator and database automatically using random credentials, never a built-in password.

**General** controls branding name, public URL, contacts, social links, currency, additional-language enablement and hold duration. English is the only enabled language initially; Arabic/RTL appears and is selectable only after the administrator enables it. Upload logos and favicons under Media using section `logo` or `favicon`, approve their rights and publish the content draft. Currency cannot change after quotations exist. Booking rules use Europe/London; storage uses UTC. Infrastructure DB credentials, TLS and DNS stay outside the dashboard.

**Features** controls booking modes, public prices, optional services, customer uploads, invitations, QR attendance, contact and newsletter collection. Backend operations enforce disabled flags. Clear mandatory upload policies before disabling documents. Disabling a module preserves records. Newsletter is opt-in collection in the contact inbox; this application does not send marketing campaigns.

## Properties, availability and prices

Under **Venues**, create independent properties, building/floor groups and rentable rooms, suites or halls. Select a parent to share physical inventory. Whole-property bookings consume every descendant resource; independent properties and disjoint rooms can coexist. Moving existing inventory or extending a property with active allocations is conservatively blocked to preserve reservation scope. Deactivate a parent to close all descendants.

Configure capacity, opening hours, allowed hourly/day/night durations, slot increments, minimum/maximum duration, hourly/day/night rates, nightly arrival/departure times, stay limits, seasonal nightly rates, public pricing mode, required documents and included/optional services. Hotel mode must be enabled for accommodation units. Full-day hire uses configured opening hours. Nightly turnover uses actual arrival/departure times. Ambiguous or nonexistent London DST times are rejected. No real capacity or rate is assumed from draft examples.

Use the dashboard availability blocks for owner closures. Holds and confirmed bookings share the same locking and overlap rules. Holds expire after the configured duration; expired allocations stop blocking without a cleanup job. Cancel or reschedule through the venue console.

## Enquiries, documents and messages

Customers create a draft and receive a private capability URL and reference, upload required documents, then submit. A draft or submitted enquiry does not automatically reserve inventory. Review the request, request more information, reject it, create an explicit hold or prepare a quotation. Private messages and documents are available in the customer portal. Downloads require the request capability or an operator session; documents are stored outside the web root.

Customers can recover access with their reference and email. A matching request receives a one-use, 30-minute link; confirming recovery rotates the old private request URL. Treat all request/invitation links as bearer secrets. Do not paste them into public communications or analytics.

## Quotations, payments and refunds

Use the normal quotation form in a request. Enter item names, descriptions, exact decimal amounts, integer quantities, tax basis points and mandatory/refundable/base/visibility flags. A positive, mandatory, visible, non-refundable booking base is required. Unit rates suggest the base; included services appear at zero additional charge, configured optional services can be added and the default refundable deposit is prefilled. Review all amounts before issuing.

Quotations have numbered versions, expiry, terms and optional external HTTPS payment links. Customers choose optional services and accept the latest unexpired version. Acceptance recalculates totals and stores an immutable snapshot. English and Arabic PDF downloads require authorization. Changing settings does not change an accepted quote.

Payment settings control allowed bank-transfer/external-link methods and instructions. Instructions are copied into quotation versions. Record receipts, deposits and refunds using distinct references. Reported evidence is unverified until reviewed. Verification cannot be replayed; refunds cannot exceed verified receipts. Confirmation requires an accepted quotation, sufficient verified net funds and conflict-free inventory. No Stripe Checkout/webhook or automatic provider reconciliation is represented: the revised brief specifies external links and manual verification.

## Invitations and attendance

Enable Invitations and, separately, QR attendance. Choose a confirmed booking under Guests, edit one of the three invitation templates or create another, and add guests within the attendance limit. Configure event/host/venue labels, bilingual copy, colours and template activation. Preview/share each private invitation and optionally send it by email. Guests RSVP until the event starts.

Each QR contains only a separate random check-in capability. A guest link does not authorize attendance scanning. Staff may use their phone's QR camera to open the check-in URL, or open **Scan attendance QR** and capture/upload a JPEG/PNG QR photo. Scanning opens a confirmation screen; attendance changes only after the authenticated staff POST. Duplicate scans do not increase totals. Manual add/remove requires an audit reason. Export CSV and review RSVP/attendance totals. Revoking an invitation or cancelling its booking invalidates guest access.

## Content and media publication

Content editing creates drafts. Preview the current draft as an operator, review English/Arabic text, FAQs, navigation, SEO and visibility, then explicitly approve publication. Internal pages have separate draft/published controls. Upload images or MP4 video (25 MB maximum); images are re-encoded and larger images receive responsive variants. Use section identifiers from `DESIGN_REFERENCE.md` or a venue ID for venue galleries. Reorder/remove media in the dashboard.

Uploads begin protected. Record their source/publication rights and approval; approval alone does not publish. Publishing a content draft checks every selected media asset. Removing or withdrawing approval stops subsequent public media delivery. The supplied PDF and extracted reference image are restricted layout references, not a rights declaration. Missing approved assets use clearly marked draft placeholders. Never mark third-party media approved without authority.

## Email and operations

Configure SMTP, sender and templates in Settings. Passwords are encrypted at rest, are never returned by the UI/API and are retained when the password field is blank. Use the explicit clear control to remove one. Test Email records transport success; log-only mail is not represented as delivered SMTP. Email logs, outbox status and retry controls are available. The Compose worker processes queued messages and records a heartbeat. Optional notification preferences take effect on the backend.

Deployment Readiness runs actual checks for URL/HTTPS, SMTP test results, writable storage, recent jobs, recorded backup, content publication, payment configuration, authentication/debug/installer state and source notices. Pending configuration is expected locally. Green local transport tests do not establish public DNS, mailbox deliverability, business approval or production TLS. Configure these later without rebuilding application code.

## Change a photograph

Open **Settings > Media**. Each placement shows its photograph and a readable section or venue name. Choose a replacement file and click **Replace in draft**, or reuse a library photo with **Use in this section / venue**. Reorder gallery entries with **Move up / Move down**. Replacement creates a draft and retains the current published photograph.

Approve the new asset in **Media library** with its source/usage record, review **Preview current draft**, then open **Website content** and publish the approved draft. The latest published content is the baseline for subsequent edits; older drafts never silently replace live content. Removing a placement keeps the library asset available for reuse. Removing a library asset withdraws that asset everywhere.

Client-requested reference property/gathering photographs and the additional property photograph were uploaded through these normal controls to both local previews on 10 November 2026. Their recorded source is the supplied reference/new photograph and the client's explicit usage instruction, without independently claiming copyright ownership. These are runtime media and database configuration, preserved through the documented backup/restore workflow rather than committed as uploaded files to Git.
