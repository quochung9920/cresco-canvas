# Cresco Canvas

Cresco Canvas is a native WordPress visual builder built on Gutenberg blocks and WordPress data APIs. The current package is `1.0.0-rc.1`: the planned product scope is present in source and checked-in runtime, while production verification and commercial release gates remain in progress.

Page content remains normal Gutenberg block markup in `post_content`. WordPress continues to own saving, publishing, autosaves, revisions, undo/redo, locking, media, previews, the inserter, and List View. Cresco adds a unified left workspace, an Elements library, a selected-widget inspector, global fluid design tokens, templates, Theme Builder, dynamic data and loops, interactions, and forms.

## Requirements

- WordPress 6.7 or newer.
- PHP 8.1 or newer.
- A browser supported by the installed WordPress version.
- Node.js 20.19+ and npm 10+ for development.
- Composer 2 for optimized release autoloading.

## Editor experience

The desktop workspace is organized into three areas:

1. Cresco Canvas tools and selected-widget settings on the left.
2. The Gutenberg canvas in the center.
3. WordPress List View on the right when opened.

The Cresco workspace includes:

- Elements and reusable compositions.
- Widget settings grouped into Content, Layout, Style, Responsive, Effects, and Advanced.
- Global Design controls for colors, typography, spacing, layout, controls, and device breakpoints.
- Templates, synced components, and site kits.
- Theme Builder and display conditions.
- Dynamic fields, ACF structures, loops, query tools, AJAX filters, and facets.
- Tabs, accordions, modal, slider, off-canvas, disclosure, tooltip, and visibility tools.
- Form fields, conditional logic, calculations, multi-step forms, uploads, submissions, email, CAPTCHA adapters, and signed webhooks.

## Global Design

Global settings are stored in the validated `cresco_canvas_settings` option. Fluid typography and spacing values accept a restricted CSS-value grammar and default to editable `clamp()` expressions. The current settings schema is version 4.

Global values can be saved, reset, exported, and imported. They are emitted as scoped CSS custom properties in the editor and frontend. Structural responsive behavior still requires generated media-query rules; CSS custom properties alone do not change media-query breakpoints.

## Data and portability

- Canonical page content stays in native block markup.
- Global design settings use `cresco_canvas_settings`.
- Page enablement uses `_cresco_canvas_enabled` metadata.
- Form submissions use the private `cresco_submission` post type.
- Cresco-owned uploads are marked with `_cresco_form_upload`.
- Deactivation preserves content and settings.
- Uninstall preserves data by default.
- Explicit uninstall cleanup removes documented plugin-owned settings, private submissions, Cresco uploads, scheduled jobs, and metadata, but never deletes user-authored page `post_content`.

## Development

```bash
npm ci
composer install
npm run build
```

Quality commands:

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

A commercial release must be generated from a clean source build with Composer's optimized autoloader. Do not treat a manually copied repository directory as a production package.

## Release status

`1.0.0-rc.1` is feature-complete by planned scope, but it is not yet certified as production stable. The remaining work is tracked in [Commercial Hardening](docs/COMMERCIAL_HARDENING.md), [Release Checklist](docs/RELEASE_CHECKLIST.md), and [Known Limitations](docs/KNOWN_LIMITATIONS.md).

Do not advertise the package as commercially ready until clean build reproducibility, WordPress/PHP/browser compatibility, security review, accessibility review, upgrade/rollback, and real-site release installation have passed.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
