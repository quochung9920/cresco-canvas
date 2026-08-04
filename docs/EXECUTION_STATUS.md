# Cresco Canvas Roadmap Execution Status

Assessment date: 2026-08-04

This document tracks the implementation sequence requested for direct updates to `main`. It does not replace `docs/CODEX_MASTER_IMPLEMENTATION_PROMPT.md`; it records evidence from the actual repository state.

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

### `0.3.1-alpha.1` Gutenberg and Elements stabilization — SOURCE CANDIDATE, NOT YET VERIFIED

Implemented in source and checked-in runtime files:

- Standard Gutenberg remains the only Page editor.
- Native WordPress save, publish, autosave, revisions, undo/redo, locking, media, List View, and preview remain available.
- Cresco Elements Library is visible in the Gutenberg sidebar.
- Search, categories, favorites, recent elements, click insertion, and supported drag-to-canvas insertion exist.
- Initial layout, content, media, marketing, navigation, blog, interactive, and utility element compositions exist.
- Page-level Cresco styling is enabled automatically after element insertion.
- Persisted favorite and recent IDs are sanitized for invalid values, stale element IDs, duplicates, and limits.
- Element insertion resolves the selected container, selected sibling, or document end rather than always appending blindly.
- Element factories are checked for unavailable nested block types before insertion.
- WordPress insertion restrictions are checked at the selected location and surfaced as a warning instead of failing silently.
- Unknown drag payloads, storage failures, factory failures, and insertion failures receive recoverable user feedback.
- Continuous animation-frame canvas polling has been replaced by mutation and iframe-load observers with cleanup.
- Element registry unit coverage verifies stable IDs, known categories, and valid native block factories.
- Library-state unit coverage verifies storage sanitation, search, recent ordering, nested availability checks, and insertion-point resolution.
- Playwright coverage verifies that a user can search for Heading, insert it as a native Gutenberg block, select it, and enable Cresco Page styles.

The source candidate still lacks hosted CI and WordPress runtime evidence. Work on `0.4.0` was explicitly continued by the product owner without reclassifying `0.3.1` as verified.

### `0.4.0-alpha.1` Layout, responsive, and preview — SOURCE CANDIDATE, NOT YET VERIFIED

Implemented in source and checked-in runtime files:

- Container supports Flex, Grid, and Block layouts.
- Flex direction and wrapping, Grid columns, justification, alignment, gap, maximum width, and per-side padding can be set responsively.
- Desktop is the base value set; Laptop inherits Desktop, Tablet inherits Laptop, and Mobile inherits Tablet.
- 4K is an independent override derived from Desktop.
- Numeric layout inputs are bounded and enum-like values are allow-listed before style output.
- Existing Container blocks preserve their legacy inline style output until responsive-only attributes are used.
- Responsive values are emitted through scoped Container custom properties and frontend media queries.
- Gutenberg has five exact logical preview modes: 4K 1920px, Desktop 1440px, Laptop 1024px, Tablet 768px, and Mobile 390px.
- The selected preview device is persisted defensively in browser storage and synchronized with Container controls.
- A dedicated Cresco Preview sidebar opens a permission-aware frontend iframe at the selected logical width.
- The frontend iframe can refresh manually and after WordPress save or autosave completes.
- Preview runtime, CSS, asset manifest, webpack entry, PHP bootstrap, and release packaging integration exist.
- Unit tests cover inheritance, normalization, overrides, safe background output, exact preview widths, and unavailable browser storage.

Important evidence limitations:

- Isolated TypeScript validation for the new source and syntax checks for the checked-in JavaScript/PHP files passed outside the repository dependency installation.
- The checked-in Container and Preview runtimes have not yet been regenerated and byte-compared through a successful repository `npm run build` on the current head.
- Hosted CI has not produced PASS evidence for this source candidate.
- WordPress runtime behavior, save/reload persistence, editor/frontend parity, preview authentication, iframe behavior, and five-mode visual results have not been manually verified.
- Responsive controls currently apply to the Cresco Container; a shared responsive-control layer for supported Core blocks is not complete.

Still required before `0.4.0` can be called complete:

- Passing type checking, JavaScript lint, CSS lint, Markdown lint, unit tests, version check, production build, package reproducibility, PHP tests, coding standards, compatibility matrix, E2E tests, accessibility automation, and Plugin Check.
- WordPress runtime verification for every preview mode, inherited and reset Container values, save/reload, frontend output, and published/draft/private Page previews.
- Regression testing with legacy Container markup, nested Containers, unknown blocks, third-party blocks, and themes with constrained content widths.
- Manual keyboard, screen-reader, RTL, zoom, forced-colors, touch, and browser verification.
- Multisite, role/capability, activation, upgrade, rollback, deactivate/reactivate, and uninstall verification.
- Documented performance evidence for device switching, opening Live Frontend Preview, and editing deeply nested responsive Containers.

### `0.5.0` through `1.0.0` — NOT COMPLETE

The Global Design System, Templates, Components, Site Kits, Theme Builder, Display Conditions, Dynamic Data, ACF, Query/Loop Builder, full interactive catalog, form integrations, WooCommerce scope, diagnostics, beta, RC, and commercial release gates remain future implementation stages.

## Current validation state

- Unit tests were added for the `0.3.1` and `0.4.0` source candidates.
- New `0.4.0` source passed isolated strict TypeScript validation using local declaration stubs.
- Checked-in Container and Preview JavaScript passed `node --check`.
- Changed PHP files and asset manifests passed `php -l`.
- These isolated checks are not substitutes for repository dependency installation, production build, hosted CI, or WordPress runtime verification.
- No production or commercial claim is permitted from repository content alone.
- All eight commercial release gates remain `NOT VERIFIED` until reproducible evidence exists.

## Direct-main policy for this project

Direct `main` updates were explicitly requested by the product owner. To reduce risk, each stage is assembled on a temporary technical branch, checked for source consistency, and then fast-forwarded into `main` as one coherent repository state.

The repository must never claim that the complete roadmap was delivered in one change. Each version gate depends on the previous one, particularly responsive style storage, template entities, dynamic binding security, and release migration safety.
