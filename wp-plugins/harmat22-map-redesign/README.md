# Harmat22 Map Redesign Plugin

This plugin visually refreshes the homepage map module on harmat22.hu without editing the active theme files.

## What it changes

- Adds a cleaner card layout around the neighborhood map section.
- Adds category chips for nearby places.
- Adds a compact list panel with location names and quick distance/time labels.
- Keeps the existing map embed in place and wraps it with a more structured layout.

## Safe rollback

- To rollback instantly: **Plugins -> Installed Plugins -> Deactivate "Harmat22 Map Redesign"**.
- No database schema changes are made.

## Install on WordPress

1. From this repository, zip the folder `wp-plugins/harmat22-map-redesign`.
2. In WordPress admin, go to **Plugins -> Add New -> Upload Plugin**.
3. Upload the zip and click **Install Now**.
4. Click **Activate Plugin**.
5. Hard-refresh the homepage and verify the map section.

## Packaging command (optional)

From the repository root:

```bash
cd wp-plugins && zip -r harmat22-map-redesign.zip harmat22-map-redesign
```

Then upload `wp-plugins/harmat22-map-redesign.zip` in WordPress admin.
