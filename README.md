# Cresco Canvas

Cresco Canvas is an experimental visual-building extension for the native WordPress Gutenberg editor. Version `0.3.0-alpha.1` removes the separate Canvas editor and integrates Cresco controls directly into the standard Page editor. It is not a commercial release.

Page content remains normal Gutenberg block markup in `post_content`. WordPress Core owns loading, saving, publishing, autosaves, revisions, undo/redo, post locking, previews, media, the inserter, and List View. Cresco adds a native Container block, a Gutenberg sidebar, and scoped design tokens.

## Requirements

- WordPress 6.7 or newer.
- PHP 8.1 or newer.
- A browser supported by the installed WordPress version.
- Node.js 22 and npm 10 or newer for development.
- Composer 2 and Docker for packaging and WordPress/E2E suites.

## What milestone 0.3 changes

- The normal Page title and **Edit** action open Gutenberg—there is no **Edit in Canvas / WordPress Editor** choice.
- Cresco tools appear inside Gutenberg in the **Cresco Canvas** plugin sidebar.
- The Page styling switch is revision-enabled metadata and is saved by Gutenberg's normal **Save**, **Update**, or **Publish** workflow.
- The custom Page REST load/save routes, duplicate editor router, custom shell, Safe Mode route, and editor-choice preferences are removed.
- Existing `cresco/container` markup remains compatible and editable as a native block.
- Site-wide Cresco design settings remain permissioned custom data, saved through the settings endpoint and previewed in the editor.
- Missing Cresco build assets show a non-blocking warning; Gutenberg remains usable.
- Frontend CSS remains conditional and scoped to Pages that enable Cresco or contain a legacy Cresco Container.

## Install a release ZIP

1. Download the CI-produced `cresco-canvas.zip` artifact from a reviewed milestone build.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Upload the ZIP and activate Cresco Canvas.
4. Open **Pages**, select the normal **Edit** action, then open the **Cresco Canvas** sidebar in Gutenberg.

Do not package the repository directory manually: source checkouts omit Composer's optimized release autoloader.

## Develop locally

```bash
npm ci
composer install
npm run build
npx wp-env start
```

Useful checks:

```bash
npm run typecheck
npm run lint:js
npm run lint:css
npm run lint:md
npm run test:unit
phpunit --configuration phpunit.xml.dist
npm run test:e2e
npm run check:version
npm run package:verify
```

`npm run package:verify` requires `vendor/autoload.php`; run Composer first. The archive and checksum are written to `dist/`.

## Data and rollback

- Canonical content: native block markup in `wp_posts.post_content`.
- Plugin option: `cresco_canvas_settings` for validated global tokens and opt-in uninstall cleanup.
- Page metadata: `_cresco_canvas_enabled`, registered for native REST saving and revisions.
- Migration schema 2 removes retired dual-editor preferences without changing Page content.
- Deactivation preserves all content and settings.
- Uninstall preserves data by default; explicit cleanup removes only documented Cresco options and metadata, never `post_content`.
- Rollback from this alpha: deactivate the plugin and continue editing the native blocks in Gutenberg. Review schema compatibility before installing older plugin code.

See [Architecture](docs/ARCHITECTURE.md), [Roadmap](docs/ROADMAP.md), [Known limitations](docs/KNOWN_LIMITATIONS.md), and [Release checklist](docs/RELEASE_CHECKLIST.md) for evidence-backed status.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
