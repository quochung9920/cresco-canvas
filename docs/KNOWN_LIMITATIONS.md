# Known Limitations

## Current milestone boundary

Version `0.2.0-alpha.1` is an architecture foundation, not a production-ready visual builder.

- Page persistence still uses a versioned transitional REST route. Native `@wordpress/core-data`, autosaves, revision browsing, post locking, undo/redo, offline retry, and full crash recovery are 0.3 work.
- The shell exposes a categorized basic inserter, title, device widths, preview, save, global settings, and block inspector. Navigator, drag-and-drop library UX, context actions, multi-select, copy/paste styles, shortcuts, command palette, and resizable panels are incomplete.
- Container is the only custom layout block. Complete Section/Grid/Stack/responsive inheritance and shared controls are 0.4 scope.
- Global settings are a small safe subset, not the complete token system planned for 0.5.
- Templates, components, Theme Builder, dynamic data/query tooling, interactive components, form integrations, WooCommerce, onboarding, diagnostics, translations, and commercial operations are later milestones.

## Verification gaps

- Native PHP, Composer, Docker, WordPress, and browser services were unavailable in the implementation environment. A PHP 8.3 WebAssembly CLI linted all 21 PHP files with zero syntax errors, and a JavaScript PHP parser supplied an independent secondary parse. Authoritative native PHP 8.1–8.5, PHPCS, PHPUnit, WordPress, and E2E evidence must still come from CI.
- Manual keyboard, screen-reader, zoom, forced-colors, RTL, touch, and real-host testing have not been performed.
- Editor timing, save timing, CLS, and 500-block performance have not been measured.
- Upgrade and rollback have no released-schema fixture beyond the idempotent version-one unit test.
- Release signing, provenance attestations, translations, privacy-safe diagnostics, beta, RC, and real staging validation are absent.

## Dependency/tooling limitations

- `npm audit --omit=dev --audit-level=high` reports zero vulnerabilities.
- The full development graph reports 30 transitive advisories (25 moderate, 5 high), primarily through `@wordpress/scripts` tooling such as old `markdownlint-cli`, Lighthouse/OpenTelemetry, and webpack-dev-server. These packages are excluded from the release ZIP. CI reports this audit without suppressing its output while keeping production dependency findings release-blocking.
- `@wordpress/block-editor` does not publish a TypeScript declaration surface matching the installed runtime. Cresco maintains a narrow local declaration for only the public APIs it imports; it must be reviewed when packages change.
- A legacy `react-autosize-textarea` peer range in the current WordPress package graph produces an install warning even though WordPress packages resolve React 18.3.1.

No limitation in this file should be interpreted as a commercial-readiness waiver.
