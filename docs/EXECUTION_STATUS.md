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

### `0.3.1` Gutenberg and Elements stabilization — IN PROGRESS

Implemented:

- Standard Gutenberg remains the only Page editor.
- Native WordPress save, publish, autosave, revisions, undo/redo, locking, media, List View, and preview remain available.
- Cresco Elements Library is visible in the Gutenberg sidebar.
- Search, categories, favorites, recent elements, click insertion, and supported drag-to-canvas insertion exist.
- Initial layout, content, media, marketing, navigation, blog, interactive, and utility element compositions exist.
- Page-level Cresco styling is enabled automatically after element insertion.
- Element registry unit coverage now verifies stable IDs, known categories, and valid native block factories.
- Playwright coverage now verifies that a user can search for Heading, insert it as a native Gutenberg block, select it, and enable Cresco Page styles.

Still required before `0.3.1` can be called complete:

- A successful hosted CI run on the current `main` head.
- WordPress runtime verification of click insertion, drag insertion, save, reload, and frontend output.
- Unavailable/context-restricted block handling for Site Logo, Navigation, and post-context elements.
- Regression testing with unknown and third-party blocks.
- Manual keyboard, screen-reader, RTL, zoom, and forced-colors verification.
- Multisite, role/capability, activation, upgrade, rollback, deactivate/reactivate, and uninstall verification.
- Documented performance evidence for opening and searching the Elements Library.

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
