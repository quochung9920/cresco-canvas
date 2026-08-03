# Cresco Canvas

Cresco Canvas is an experimental native visual editor for WordPress Pages. Version `0.2.0-alpha.1` establishes the architecture and reliability foundation; it is not a commercial release.

The plugin stores Page content as normal Gutenberg block markup in `post_content`. Deactivation leaves content and settings intact. Uninstall never deletes Page content and removes plugin-owned options and metadata only when an administrator has explicitly enabled that setting.

## Requirements

- WordPress 6.7 or newer.
- PHP 8.1 or newer.
- A modern browser supported by the installed WordPress version.
- Node.js 22 and npm 10 or newer for development.
- Composer 2 for development and release packaging.
- Docker for the `wp-env` compatibility and E2E suites.

## What milestone 0.2 provides

- A TypeScript editor shell built with public `@wordpress/*` packages.
- A native `cresco/container` block plus selected Core blocks.
- Explicit **Edit in Canvas** and **WordPress Editor** Page actions.
- Global, per-Page, and remembered editor preferences.
- Nonce-protected native-editor bypass and no-JavaScript Safe Mode.
- Dirty-state and navigation warnings, clear recovery errors, and exact revision-token conflict detection for the transitional save route.
- Scoped, conditional frontend assets; editor React code is never loaded on the public frontend.
- Versioned, idempotent migrations, feature flags, runtime checks, safe lifecycle hooks, and opt-in uninstall cleanup.
- Deterministic dependency locks, CI matrices, automated tests, and a reproducible release ZIP.

Autosave, native entity editing, revisions UI, undo/redo, post locking, and the full Navigator workflow belong to milestone 0.3 and are intentionally not claimed here.

## Install a release ZIP

1. Download the CI-produced `cresco-canvas.zip` artifact from a reviewed milestone build.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Upload the ZIP and activate Cresco Canvas.
4. Open **Pages** and choose **Edit in Canvas** for a Page.

Do not package the repository directory manually: source checkouts omit Composer's release autoloader.

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
npm run test:unit
phpunit --configuration phpunit.xml.dist
npm run test:e2e
npm run check:version
npm run package:verify
```

`npm run package:verify` requires `vendor/autoload.php`, so run Composer first. The resulting archive and checksum are written to `dist/`.

## Editor selection and recovery

The default setting supports **Cresco Canvas**, **WordPress Editor**, or **Remember last choice**. A Page-level `_cresco_canvas_editor_preference` value overrides the global choice. Every Page row exposes explicit links to both editors.

Safe Mode is a signed, user-specific URL shown by the editor recovery boundary. It disables Cresco editor scripts and generated admin CSS for that request, then offers a signed WordPress Editor link. Safe Mode does not modify the Page.

## Data and rollback

- Canonical content: native block markup in `wp_posts.post_content`.
- Plugin options: `cresco_canvas_settings`, `cresco_canvas_feature_flags`, `cresco_canvas_db_version`, and migration state/lock options.
- Plugin metadata: `_cresco_canvas_enabled`, `_cresco_canvas_editor_preference`, and `cresco_canvas_last_editor` user metadata.
- Rollback from this alpha: deactivate the plugin and use the WordPress Editor. Do not install code with an older schema until its rollback compatibility has been reviewed.

See [Architecture](docs/ARCHITECTURE.md), [Roadmap](docs/ROADMAP.md), [Known limitations](docs/KNOWN_LIMITATIONS.md), and [Release checklist](docs/RELEASE_CHECKLIST.md) for the evidence-backed status.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
