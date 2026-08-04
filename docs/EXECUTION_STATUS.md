# Cresco Canvas Roadmap Execution Status

Assessment date: 2026-08-05

This document records the implementation state on `main`. A milestone is not considered production-verified until hosted CI and WordPress runtime evidence exist.

## Release sequence

1. `0.3.1` — Gutenberg and Cresco Elements stabilization.
2. `0.4.0` — Layout, responsive inheritance, and preview.
3. `0.5.0` — Global Design System.
4. `0.6.0` — Templates, components, and site kits.
5. `0.7.0` — Theme Builder and display conditions.
6. `0.8.0` — Dynamic Data, ACF, Query Builder, and Loop Builder.
7. `0.9.0` — Interactive components and form integrations.
8. `1.0.0` — Production and commercial hardening.

## Current implementation state

### `0.3.1-alpha.1` — SOURCE CANDIDATE, NOT VERIFIED

Native Gutenberg integration, Cresco Elements, insertion safeguards, browser-state sanitation, and regression tests exist. Hosted CI and complete WordPress runtime evidence are still missing.

### `0.4.0-alpha.1` — SOURCE CANDIDATE, NOT VERIFIED

Responsive Container controls, inheritance, five logical preview widths, and Live Frontend Preview runtime exist. Shared responsive controls for supported Core blocks and full runtime verification remain incomplete.

### `0.5.0-alpha.1` — FUNCTIONAL SOURCE AND CHECKED-IN RUNTIME, NOT VERIFIED

Implemented:

- Structured global tokens for colors, typography, spacing, layout, radius, shadows, and motion.
- Custom colors and aliases with server sanitization and dependency-aware deletion.
- REST read/save/reset/catalog endpoints.
- Import/export controls and schema version three migration.
- Independent checked-in Gutenberg runtime and release-package requirements.

Hosted CI, manual accessibility, browser, RTL, multisite, lifecycle, and performance evidence remain missing.

### `0.6.0-alpha.1` — FUNCTIONAL SOURCE AND CHECKED-IN RUNTIME, NOT VERIFIED

Implemented:

- Native WordPress pattern categories and seven bundled templates covering Pages, heroes, features, CTA, testimonials, pricing, and contact.
- A Gutenberg Template Library with search, category filtering, and native block insertion.
- Synced component creation from a selected block using `wp_block`.
- Synced component listing and insertion using native block references.
- Site Kit export/import for sanitized Cresco settings and allow-listed bundled template IDs.
- Capability checks for template editing and Site Kit management.
- Rejection of executable or unsupported component block types.
- Independent checked-in runtime, stylesheet, manifest, packaging requirements, and PHP regression tests.

Important limitations:

- Hosted CI and WordPress runtime evidence are absent for the current `main` head.
- Multi-block component capture, Pattern Overrides, content-only mode, thumbnails, favorites, remote catalogs, dependency installation, and template conflict handling are not implemented.
- The repository dependency lock remains unusable, so a reproducible `npm ci` build has not been demonstrated.

### `0.7.0` through `1.0.0` — NOT COMPLETE

Theme Builder, display conditions, Dynamic Data, ACF, Query/Loop Builder, full interactive catalog, form integrations, WooCommerce scope, diagnostics, beta, RC, and all commercial release gates remain future work.

## Validation state

- Checked-in PHP, JavaScript, CSS, and asset manifests exist for the implemented milestones.
- Regression tests are present for Elements, responsive behavior, preview devices, Design Tokens, and Template Library catalog safety.
- No successful hosted workflow or complete WordPress runtime test result has been observed for the latest `main` commit.
- No production or commercial readiness claim is permitted.
- All eight commercial release gates remain `NOT VERIFIED`.

## Direct-main policy

Direct `main` updates were explicitly requested. Changes are therefore committed in small coherent units. Every milestone must still preserve native Gutenberg content, use capability checks, include migration and packaging considerations, and disclose missing evidence.
