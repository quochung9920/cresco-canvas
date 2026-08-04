# Changelog

All notable changes are documented here. Versions follow Semantic Versioning where practical during the pre-1.0 cycle.

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
