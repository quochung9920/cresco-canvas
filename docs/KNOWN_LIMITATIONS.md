# Known Limitations — 1.0.0-rc.1

Cresco Canvas `1.0.0-rc.1` contains the planned product scope in source and checked-in runtime. It is not yet a commercially certified stable release.

## Build integrity

- Several recent editor runtimes were changed directly in checked-in `build/` assets. A clean source-to-build reproduction has not yet proved that every generated file has an authoritative source counterpart.
- `npm ci`, Composer optimized autoloading, PHP tests, JavaScript tests, lint, and reproducible packaging do not yet have passing hosted evidence for the current head.
- The release ZIP has not yet been installed and approved on a clean WordPress site.

## Widget Inspector

- Core-compatible color, typography, spacing, border, and dimension settings can use native block supports where available.
- Some effects and positioning controls currently store Cresco-oriented values that WordPress Core does not automatically render for every third-party or Core block.
- Controls must be capability-aware, and Cresco-only values need a versioned attribute/rendering pipeline before these controls can be considered reliable for all widgets.
- Save/reload, revisions, patterns, copy/paste, and block-validation coverage is incomplete for the full inspector matrix.

## Editor workspace

- The left-tools/center-canvas/right-List-View layout uses feature detection plus Gutenberg DOM adapters. WordPress and Gutenberg can change internal editor markup.
- Post Editor, Page Editor, Site Editor, fullscreen, distraction-free, and narrow-screen fallbacks require an automated compatibility matrix.
- A failed adapter must preserve the native WordPress editor; this fallback still needs destructive testing across supported versions.

## Global Design

- Settings schema 4 adds editable fluid tokens and breakpoint values with sanitized storage.
- Fluid typography and spacing work as CSS values, but CSS custom properties cannot directly redefine media-query boundaries. Editable structural breakpoints require generated media-query output and cache invalidation.
- Live preview, dirty-state recovery, per-group reset, contrast validation, token usage tracking, and import conflict handling remain incomplete.

## Forms and public endpoints

- Forms include public JSON and multipart routes, uploads, CAPTCHA adapters, email, stored submissions, CSV export, and signed webhooks.
- Provider secrets and production CAPTCHA verification depend on site-specific adapters and have not been tested against live providers.
- Webhook delivery still requires a dedicated SSRF, private-network, DNS-rebinding, idempotency, retry, and log-privacy audit.
- Uploads require polyglot, double-extension, MIME-spoofing, ownership, retention, and protected-download tests.
- CSV formula-injection defenses and large-export behavior require verification.

## Dynamic data and queries

- Query payloads are bounded and signed in the current design, but production cost limits, cache behavior, invalidation, large catalogs, ACF, and WooCommerce runtime matrices have not been verified.
- Facet counts, history synchronization, multiple loops, no-JavaScript fallback, and proxy/CDN behavior require end-to-end tests.

## Lifecycle and data

- Schema 4 migration sanitizes settings and stores a pre-migration backup in `cresco_canvas_settings_backup_v3`.
- Uninstall preserves data by default. Explicit cleanup now inventories known Cresco settings, schedules, submissions, uploads, and metadata.
- Real-database upgrade fixtures, migration failure recovery, downgrade guards, rollback, single-site, and multisite lifecycle tests remain incomplete.
- User-authored page `post_content` must never be deleted by uninstall or migration.

## Accessibility

- Keyboard behavior and ARIA markup exist in several components, but WCAG 2.2 AA has not been certified.
- NVDA, VoiceOver, RTL, forced-colors, 200%/400% zoom, touch, and reduced-motion reviews are incomplete.
- Modal/off-canvas focus, slider announcements, AJAX result updates, drag alternatives, and form error focus require manual and automated verification.

## Performance

- Editor overhead has not been measured on 50-, 200-, and 500-block documents.
- Mutation observers, module loading, dynamic queries, facets, forms, email, and webhooks require profiling.
- Frontend assets are not yet proven to load only when their blocks are present across all modules.
- No approved performance budget currently gates release.

## Commercial operations

- Automatic updates, release channels, signed metadata, rollback packages, SBOM/provenance, support policy, security response policy, and privacy documentation are not complete.
- Translation catalogs and localization review are incomplete.
- No limitation in this document is a commercial-readiness waiver. Stable `1.0.0` requires the P0 gates in `docs/COMMERCIAL_HARDENING.md` and `docs/RELEASE_CHECKLIST.md` to pass.
