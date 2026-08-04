# Changelog

<!-- markdownlint-disable MD024 -->

All notable changes are documented here. Versions follow Semantic Versioning where practical during the pre-1.0 cycle.

## [0.3.0-alpha.1] - 2026-08-04

### Added

- A native Gutenberg `PluginSidebar` for Page styling and permissioned global design settings.
- Revision-enabled `_cresco_canvas_enabled` metadata saved through WordPress Core's Page workflow.
- Live scoped design-token preview for both iframe and non-iframe Gutenberg canvases.
- Schema version two migration and regression coverage for the retired dual-editor preferences.
- Native Gutenberg E2E coverage for standard Edit entry, Core saving, metadata persistence, asset isolation, removed Page routes, and sidebar accessibility.
- RTL replacement data for the generated editor stylesheet.

### Changed

- Bumped the plugin, package, and Container block to `0.3.0-alpha.1`.
- Made Gutenberg the only Page editor and delegated document loading, saving, publishing, autosaves, revisions, undo/redo, locking, navigation protection, previews, media, and document settings to WordPress Core.
- Reduced the compiled Cresco editor runtime to a sidebar extension instead of a duplicate full-screen editor.
- Restricted the Cresco REST API to genuinely custom site-wide settings.
- Updated the authoritative product specification to prohibit a separate editor screen or dual-editor routing.
- Limited the current sidebar to design controls with visible editor/frontend output; deferred width and muted utility controls stay out of the UI until their layout/token consumers exist.

### Removed

- The **Edit in Canvas / WordPress Editor** split, editor-choice settings, row actions, redirects, and remembered user preference.
- The custom Page REST read/write routes and their duplicate revision-token workflow.
- The proprietary top bar, inserter, inspector, error boundary, Safe Mode screen, and custom React editor shell.

### Security

- Continued capability enforcement for Page metadata and site-wide design settings.
- Preserved strict setting sanitization, scoped output, and non-destructive lifecycle behavior while reducing the custom Page-write attack surface.

## [0.2.0-alpha.1] - 2026-08-03

### Added

- TypeScript and modular React sources built with `@wordpress/scripts`.
- Composer PSR-4 autoloading with a recoverable source-checkout fallback.
- GitHub Actions for JavaScript, CSS, PHP 8.1–8.5, WordPress 6.7/6.9/7.0.1/trunk, Playwright, axe, Plugin Check, packaging, and reproducibility.
- Versioned idempotent migration runner, feature flags, activation requirements, safe deactivation, and opt-in uninstall cleanup.
- Configurable global, per-Page, and remembered editor entry behavior.
- Nonce-protected native-editor bypass and Safe Mode recovery.
- Exact revision-token conflict detection and crash/unsaved-change recovery paths.
- PHP, JavaScript, E2E, compatibility, accessibility, and style-isolation test foundations.
- Reproducible release ZIP generation and SHA-256 checksum.
- Baseline, architecture, roadmap, security, accessibility, performance, compatibility, commercial-readiness, release, and limitations documentation.

### Changed

- Bumped the plugin and Container block from 0.1.1 to `0.2.0-alpha.1`.
- Replaced the monolithic editor source with typed modules and generated production assets.
- Scoped frontend variables and selectors to Canvas Pages and conditionally load frontend CSS only when Canvas is used.
- Preserved legacy `cresco/container` block markup while expanding the block editor implementation.

### Security

- Added explicit REST schemas, capability checks, input normalization, output escaping, nonce-protected editor-choice actions, and same-second concurrent-edit protection.
- Confirmed zero installable production npm vulnerabilities at high severity or above; development-toolchain advisories remain documented.

### Removed

- Unconditional Canvas takeover of every Page edit link.
- Global frontend selectors that changed all site buttons and the unscoped `body` element.
