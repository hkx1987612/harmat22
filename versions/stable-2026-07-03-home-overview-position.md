# Stable Home Overview Position Fix 2026-07-03

Adds a small homepage-only MU plugin:

`wp-mu-plugins/harmat-home-overview-position.php`

Purpose:

- Restore the homepage aerial overview block to the intended content position.
- Keep the existing Elementor block and move it immediately after the hero and before the project introduction.
- Keep the overview image pointed to `wp-content/uploads/2026/03/Start/bld-Start-frame-01.webp`.

Verification:

- Homepage returned HTTP 200 with no fatal error text.
- Apartment search, virtual selector, first-phase selector, and a sample property page returned HTTP 200.
- Browser check confirmed the overview block is before the room cards, the image loads, and there is no horizontal overflow on desktop or mobile.
