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
- Plugin header, PHP constant, package metadata, Container block metadata, CI artifact name, changelog, and editor asset metadata are set to `0.3.1-alpha.1`.

Important evidence limitation:

- `build/editor.js` was synchronized with the revised source so WordPress does not intentionally load the previous runtime.
- That checked-in runtime has not yet been regenerated and verified by a successful `npm run build` on the current `main` head.
- Source presence and test presence are not test results.

Still required before `0.3.1` can be called complete:

- A successful hosted CI run on the current `main` head.
- Passing type checking, JavaScript lint, CSS lint, Markdown lint, unit tests, version check, production build, package reproducibility, PHP tests, coding standards, compatibility matrix, E2E tests, accessibility automation, and Plugin Check.
- WordPress runtime verification of click insertion, drag insertion, save, reload, frontend output, and editor/frontend parity.
- Regression testing with unknown, unavailable, restricted, and third-party blocks.
- Manual keyboard, screen-reader, RTL, zoom, forced-colors, touch, and browser verification.
- Multisite, role/capability, activation, upgrade, rollback, deactivate/reactivate, and uninstall verification.
- Documented performance evidence for opening, searching, and inserting from the Elements Library.

The `0.4.0` milestone must not start until the applicable `0.3.1` engineering checks pass or a blocking infrastructure failure is explicitly documented and resolved.

### `0.4.0` Layout, responsive, and preview — NOT STARTED AS A COMPLETE MILESTONE

Some initial compositions and responsive helper CSS exist. The required shared layout controls, responsive inheritance, exact five preview modes, safe style generation, and Live Frontend Preview are not complete.

### `0.5.0` through `1.0.0` — NOT COMPLETE

The Global Design System, Templates, Components, Site Kits, Theme Builder, Display Conditions, Dynamic Data, ACF, Query/Loop Builder, full interactive catalog, form integrations, WooCommerce scope, diagnostics, beta, RC, and commercial release gates remain future implementation stages.

## Current validation state

- Unit and E2E regression tests have been added to the repository.
- The tests have not yet produced hosted PASS evidence for the current `main` head.
- No production or commercial claim is permitted from repository content alone.
- All eight commercial release gates remain `NOT VERIFIED` until reproducible evidence exists.

## Direct-main policy for this project

Direct `main` updates were explicitly requested by the product owner. To reduce risk, every stage must still be implemented as small coherent commits, with migrations and rollback considerations, and must not be marked complete until its applicable tests pass.

The repository must never claim that the complete roadmap was delivered in one change. Each version gate depends on the previous one, particularly responsive style storage, template entities, dynamic binding security, and release migration safety.
