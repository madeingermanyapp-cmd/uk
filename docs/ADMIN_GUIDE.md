# Management guide

Sign in using VoxelBooking at `/admin/login`, then open `/admin/manor`. The console is operator-only; business managers do not inherit access to private venue enquiries. Legacy booking write interfaces are intentionally blocked so they cannot bypass shared inventory. Existing system/email settings remain a separate upstream interface; production SMTP can also be configured through environment variables.

## Enquiries

A customer creates a draft, receives a private tracking link/reference, uploads required documents, then submits. Drafts and pending enquiries do not reserve inventory. Move Submitted → Under Review. For missing information, choose More Information Required; the customer can upload and resubmit. Communicate the specific information needed through your normal correspondence until in-portal messaging is implemented.

A provisional hold is explicit, lasts the configured `hold_hours` (1–168), and is checked during every reservation attempt. Expired holds no longer block. The request status does not automatically expire; management can mark eligible requests Expired. Holds never guarantee a later confirmation: confirmation rechecks inventory transactionally.

## Quotations

In the request detail, expand Prepare / revise quotation. The MVP builder is structured JSON: edit item names/descriptions, GBP amount, integer quantity, mandatory/refundable flags, base flag, visibility, tax basis points and list order. Set a future London expiry and terms. Supply a valid HTTPS payment URL if appropriate. Only the base booking item must universally be mandatory; a positive mandatory non-refundable base is required by the server. Optional items must be visible. Tax rates are explicit; no VAT determination is made by software.

New quotations are versioned. Customers select optional items and accept the latest unexpired version. Totals are recalculated server-side and the accepted snapshot cannot be revised. Download using browser Print → Save as PDF. Native downloadable PDF generation is deferred. Cancel and create a new request if an accepted commercial agreement needs revision.

## Payments and confirmation

Set bank instructions in central configuration. Payment links/instructions are copied into quotation versions. Record bank/provider receipts, deposits and refunds with references. Reported entries are unverified. Review each reported entry as Verified or Rejected; entries cannot be verified twice. Refunds cannot exceed verified net receipts. Refundable quotation deposits are totalled separately from service charges.

Advance Customer Accepted → Awaiting Payment → Payment Verification. Confirmed requires an accepted quote, sufficient verified net funds and free physical resources. Confirmation creates canonical VoxelBooking booking rows. Uploading payment evidence never marks a receipt verified. Cancel/reschedule through this console to preserve locks and audit history. Refunds and cancellations are separate deliberate operations.

## Inventory and content

Property → Building/Floor → leaf rooms. Entire-property/partial nodes consume descendant resources. Separate rooms can coexist; exclusive-property hire conflicts with every descendant room. Existing topology is immutable, and adding inventory while any allocation is active is blocked to avoid changing the meaning of exclusive bookings. Resolve reservations/blocks before extending the hierarchy.

Use unit settings for draft descriptions, capacity (null until verified), minimum/maximum duration, opening hours, pricing mode and document keys. Capacity is an event attendance limit, not an availability counter. Request times use Europe/London; nonexistent/ambiguous DST times are rejected. There is no automated nightly hotel operation in this release.

Central configuration controls website text, FAQ, contact channels, SEO, section visibility, media ordering, pricing and feature flags. The editor uses validated JSON rather than a rich visual CMS. Images are re-encoded; files are randomized. Public images/video are intentionally public. Private enquiry documents are stored outside the document root and require the private enquiry capability or an operator session. Remove public media entries to unpublish them; physical deletion is deferred to avoid destroying referenced assets.

Invitations and QR attendance are deferred and cannot be enabled. Hotel mode is future-ready inventory classification, not a claim of full hotel operations. Newsletter submissions collect consent in the inbox; outbound campaigns/unsubscribe automation are deferred, so keep the newsletter disabled until an operating process is agreed.
