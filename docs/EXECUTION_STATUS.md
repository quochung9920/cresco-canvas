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

- Native WordPress pattern categories and bundled templates.
- Gutenberg Template Library search, filtering, and insertion.
- Synced components using native `wp_block` entities.
- Sanitized Site Kit import/export.
- Checked-in runtime, stylesheet, manifest, packaging requirements, and regression test source.

Important limitations include absent hosted CI/runtime evidence, limited component capture, no remote catalog, and no demonstrated reproducible `npm ci` build.

### `0.7.0-alpha.1` — FUNCTIONAL SOURCE AND CHECKED-IN RUNTIME, NOT VERIFIED

Implemented:

- Native `cresco_template` Theme Builder entities with Gutenberg editing and revisions.
- Header, Footer, Single, Page, Archive, Search, and 404 template types.
- Allow-listed include/exclude display conditions and priority-based resolution.
- Frontend slot/document rendering, REST CRUD, Gutenberg controls, admin columns, and conflict diagnostics.
- Checked-in runtime, renderer, packaging requirements, and regression test source.

Remaining evidence includes hosted CI, theme/plugin compatibility, accessibility, RTL, browser, multisite, role, revision, and frontend runtime verification.

### `0.8.0-alpha.4` — FUNCTIONAL SOURCE AND CHECKED-IN RUNTIME, NOT VERIFIED

Implemented across alpha.1 through alpha.4:

- Dynamic scalar fields for post, site, post meta, and ACF values.
- Dynamic images for featured, meta, and ACF image return formats.
- Bounded standard Loop queries, presets, pagination, and nested-loop protection.
- Dynamic Gallery and Relationship Loop blocks for ACF/meta structured values.
- ACF Repeater rendering with one native block template per row.
- ACF Flexible Content rendering with layout-specific child templates and fallback mapping.
- ACF Sub Field dot-path binding for scalar row values.
- Permission-protected ACF field schema discovery without raw value exposure.
- Advanced Loop queries for post type, author, parent, search, dates, include/exclude IDs, one meta clause, and up to three taxonomy clauses.
- Checked-in Gutenberg runtimes, styles, manifests, release-package requirements, REST discovery/preview endpoints, and regression test source.

Known limitations:

- No AJAX filtering, faceted search, load-more, infinite scroll, or URL history synchronization.
- No WooCommerce query presets.
- Repeater/Flexible Content currently bind scalar sub-fields; dedicated nested image/gallery/relationship/repeater row bindings remain future work.
- Advanced Loop intentionally does not permit arbitrary nested meta/tax query groups or arbitrary SQL.
- The dependency lock/build environment remains unavailable, so checked-in runtimes have not been reproduced from the TypeScript source pipeline.
- Hosted CI and full WordPress/ACF runtime, accessibility, RTL, multisite, browser, security, and performance evidence remain missing.

### `0.9.0` — NOT STARTED AS A COMPLETE MILESTONE

Interactive Tabs, Accordion, Modal, Slider/Carousel, Form Builder, validation, submission handling, spam protection, external form integrations, and interaction controls remain future work.

### `1.0.0` — NOT COMPLETE

Production hardening, complete CI/runtime matrices, reproducible packaging, migrations/rollback validation, accessibility and security certification, beta/RC gates, stable documentation, support policy, and commercial infrastructure remain future work.

## Validation state

- Checked-in PHP, JavaScript, CSS, and asset manifests exist through `0.8.0-alpha.4`.
- Regression test source exists for the main sanitization, catalog, resolver, query, and structured-data boundaries.
- No successful hosted workflow or complete WordPress runtime test result has been observed for the latest `main` commit.
- No production or commercial readiness claim is permitted.
- `0.9.0` and `1.0.0` release gates remain unverified.

## Direct-main policy

Direct `main` updates were explicitly requested. Changes are committed in small coherent units. Every milestone must preserve native Gutenberg content, use capability checks, include migration and packaging considerations, and disclose missing evidence.
