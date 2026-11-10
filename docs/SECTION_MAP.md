# Section map

All sections use `public/manor/style.css`. Shell/navigation: `templates/manor/start.php`; footer: `end.php`. Main order: `home.php`. Each section can be edited independently under `templates/manor/sections/`.

| Section | Template | Central configuration |
|---|---|---|
| Hero | hero.php | hero_title, hero_eyebrow, hero_text, media[section=hero] |
| Introduction | introduction.php | intro_title, intro_text |
| Heritage | heritage.php | heritage_title, heritage_text, media[section=heritage] |
| Why choose | why.php | why_title, why_items |
| Rooms/venues | venues.php | venues_title, manor_units, media[section=unit ID], pricing flags |
| FAQ | faq.php | faq_title, faqs |
| Contact form | contact.php | contact_title, contact_text, flags.contact, privacy |
| Contact information | information.php | address, email, phone, social_links |
| Newsletter | newsletter.php | flags.newsletter |
| Footer | end.php | address, source link |

`sections` toggles visibility. Settings are edited at `/admin/manor` without rebuilding. Booking portal: `enquiry.php`, `request.php`; console: `admin.php`. Business logic: `app/Manor/`; routes: `app/routes.php`; schema: migration 043. Translation keys: `lang/en/manor.php`.
