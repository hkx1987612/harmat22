# Harmat22 Interactive Presentation Module

Version 2.1 keeps the interactive Harmat22 project viewer and adds the latest live-site polish from 2026-05-12.

## What It Includes

- 360-degree panorama viewer powered by Pannellum.
- Hungarian front-end copy for the Harmat Lakopark presentation.
- Project videos, visual gallery, location overview, and legal notice panel.
- Local site assets under `assets/harmat-3d/`, so the module does not depend on `nogakft.hu`.
- Project videos use separate playback speeds: the short project overview remains slow, while the longer showcase video plays at normal speed.
- The location overview image is shown cleanly without floating text labels over the buildings.

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
