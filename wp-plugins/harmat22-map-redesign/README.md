# Harmat22 Interactive Presentation Module

Version 2.0 replaces the old neighborhood map presentation with an interactive Harmat22 project viewer.

## What It Includes

- 360-degree panorama viewer powered by Pannellum.
- Hungarian front-end copy for the Harmat Lakopark presentation.
- Project videos, visual gallery, location overview, and legal notice panel.
- Local site assets under `assets/harmat-3d/`, so the module does not depend on `nogakft.hu`.

## WordPress Location

Deploy this directory to:

```text
wp-content/plugins/harmat22-map-redesign
```

The plugin automatically loads its CSS, JavaScript, Pannellum, and local media assets.

## Large Assets

The media files in `assets/harmat-3d/` are tracked with Git LFS because the project videos exceed GitHub's regular file size limit.

After cloning, run:

```bash
git lfs pull
```

## Rollback

The previous live plugin version was backed up on the server before deployment at:

```text
/home/harmath2/public_html/wp-content/plugins-backup/harmat22-map-redesign-2026-05-11-181604
```
