# Cresco Canvas

Cresco Canvas is a WordPress visual builder with its own standalone editor and a portable, AI-readable page document model. The current package is `1.0.0-rc.1`; production verification and commercial release gates remain in progress.

For Pages that have a saved Cresco document, **Cresco Session v1** is the visual source of truth. WordPress remains the host for authentication, permissions, media, routing, REST APIs, page records, previews, and frontend delivery. Existing user-authored `post_content` is left untouched as a fallback instead of being rewritten by the Cresco Editor.

## Requirements

- WordPress 6.7 or newer.
- PHP 8.1 or newer.
- A browser supported by the installed WordPress version.
- Node.js 20.19+ and npm 10+ for development.
- Composer 2 for optimized release autoloading.

## Editor experience

The standalone desktop workspace is organized around Widgets, Edit, Settings, AI, the Cresco visual canvas, Structure, responsive preview, undo/redo, and History. These surfaces operate on the same Cresco Session document state. The initial Session widget catalog is intentionally compact: Container, Columns, Heading, Text, Button, Image, List, Divider, and Spacer.

The Inspector favors a small set of useful controls instead of exposing every possible CSS property. When a visual requirement is not represented by a native widget or structured style control, each widget supports scoped Custom CSS. `&` always represents the current widget and stable `data-cresco-part` selectors are published for supported inner parts.

## AI workflow

Cresco is designed for a copy/paste workflow with ChatGPT or another AI tool without giving that tool direct access to WordPress:

```text
Global Design + Widget Contract + Current Cresco Session
                         |
                  Copy AI Context
                         |
                         v
                      ChatGPT
                         |
                  Cresco Session JSON
                         |
                         v
               Validate -> Apply -> Update
                         |
                         v
                    Cresco Editor
```

The **AI** panel can copy the complete AI context, the current Session, or the Widget Catalog. Imported output is validated before it can replace the current editor state and is not persisted until the user presses **Update**.

The format is documented in [Cresco Session v1](docs/CRESCO_SESSION_V1.md).

## Global Design

Global settings are stored in the validated `cresco_canvas_settings` option. The settings schema is version 4 and includes colors, typography, spacing, layout widths, radius, controls, shadows, motion, and responsive breakpoints.

Structured Session styles can reference Global Design tokens such as:

```json
{
  "color": "{colors.text}",
  "paddingTop": "{spacing.xl}",
  "maxWidth": "{layout.contentMax}",
  "borderRadius": "{radius.md}"
}
```

Known references compile to stable `--cc-*` CSS variables. Responsive values are stored by device and compiled against Global Design breakpoints.

## Custom CSS

Custom CSS is a first-class fallback, not a replacement for structured widget controls. The preferred order is:

1. Global Design token.
2. Widget prop.
3. Structured widget style.
4. Scoped Custom CSS only for the remaining special case.

Custom CSS must stay inside the widget contract. Every selector includes `&`; global selectors, `@import`, raw `@media`, external `url()`, JavaScript expressions, and markup escapes are rejected by the server validator. Responsive Custom CSS uses the Cresco device buckets instead of authoring media queries directly.

## Data and portability

- Saved Cresco documents use `_cresco_canvas_document` page metadata and schema `cresco-session/v1`.
- Global design settings use `cresco_canvas_settings`.
- Page enablement uses `_cresco_canvas_enabled` metadata.
- Existing `post_content` remains untouched as a fallback when a Cresco Session is saved.
- Form submissions use the private `cresco_submission` post type.
- Cresco-owned uploads are marked with `_cresco_form_upload`.
- Deactivation preserves content and settings.
- Uninstall preserves data by default.
- Explicit uninstall cleanup may remove documented plugin-owned settings and metadata, but must never delete user-authored page `post_content`.

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
npm run check:hygiene
npm run check:editor-runtime
npm run check:build-integrity
npm run check:version
```

Release packaging:

```bash
composer install --no-dev --optimize-autoloader
rm -rf build
npm run build
npm run check:build-integrity
npm run package
node scripts/verify-package.mjs
```

The release command creates the versioned ZIP, `SHA256SUMS`, a file-level SPDX inventory, and unsigned build provenance. The production ZIP is strict-allowlist based and excludes development source/tests/tools. Exact ZIP installation, two-clean-checkout reproducibility, upgrade, matrices, accessibility, and performance run in the commercial release workflow or an equivalent documented environment.

See [Release Engineering](docs/RELEASE_ENGINEERING.md) for source/build ownership, status semantics, matrix design, artifact verification, and the difference between automated evidence and manual verification.

## Release status

`1.0.0-rc.1` is not production stable. The remaining work is tracked in [Commercial Hardening](docs/COMMERCIAL_HARDENING.md), [Release Checklist](docs/RELEASE_CHECKLIST.md), [Known Limitations](docs/KNOWN_LIMITATIONS.md), [Compatibility Matrix](docs/COMPATIBILITY_MATRIX.md), [Accessibility Audit](docs/ACCESSIBILITY_AUDIT.md), and [Performance Gate](docs/PERFORMANCE_BASELINE.md).

Do not advertise the package as commercially ready until every P0 gate has objective evidence for the exact release commit and ZIP. A configured workflow or skipped check is not a pass, and manual accessibility/browser/cache checks remain manual until recorded.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
