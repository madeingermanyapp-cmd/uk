# Wonham design system

Public styling lives exclusively in `public/manor/style.css`; VoxelBooking's styles are retained for its existing admin screens. CSS tokens define ivory/paper/ink/accent, sans-serif typography matching the reference, section spacing, gutters, borders and radius. Breakpoints are 900px and 600px. Forms use visible labels, focus outlines, keyboard-accessible disclosure controls and single-column mobile layouts. Tables scroll horizontally. The print stylesheet supports browser Save as PDF for quotations.

The property photograph from the supplied PDF is retained at `public/manor/property.jpg` and is the fallback cover. No unrelated architecture is represented as Wonham. Upload images through `/admin/manor`, assign `hero`, `heritage` or a unit ID, and adjust the ordered media array in central settings. The first matching entry is the section cover; additional venue entries form an ordered scrollable gallery.

Content is plain text escaped at render time. Do not introduce inline scripts, raw HTML or unvalidated media URLs. English translation keys live in `lang/en/manor.php`; upstream locale formatting remains available. Arabic translation and RTL QA have not been completed. Content translations are reserved in configuration but are not rendered in this MVP.
