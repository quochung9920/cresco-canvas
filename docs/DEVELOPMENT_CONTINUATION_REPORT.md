# Cresco Canvas — Runtime Consolidation & Development Continuation Report

**Branch:** `refactor/runtime-consolidation`  
**Base:** `main` at `d44d289f27b99ba42586f9ac801d10d8f468f3f1`  
**Plugin version:** `1.0.0-rc.1`  
**Date:** 2026-08-11

## 1. Objective

This refactor makes Cresco Canvas easier to reason about and safer to extend without replacing the existing `cresco-session/v1` document format or removing compatibility behavior prematurely.

The core design objective is:

> one document model, one mutation path, one render authority, one editor core, one runtime registry, and optional modules that can fail without taking down the core editor.

“Perfect” is treated as an engineering target, not a release claim. Stable 1.0 still requires browser, accessibility, performance, upgrade, package, and clean WordPress evidence.

## 2. What changed

### New shared runtime infrastructure

Four canonical PHP contracts were added:

- `WebsiteBuilderRuntimeContext`
- `WebsiteBuilderAsset`
- `WebsiteBuilderEditorConfig`
- `WebsiteBuilderModuleRegistry`

These replace repeated decisions that were previously scattered across bootstrap, diagnostics, runtime guard, professional UX, workflow, and compatibility-oriented modules.

### Runtime context

`WebsiteBuilderRuntimeContext` is now the standard source for:

- current Cresco editor screen;
- document ID;
- post type;
- Page vs Theme editor;
- document type;
- current-user edit capability;
- debug mode;
- safe/isolation mode;
- Architecture debug override.

Supported isolation modes are:

- `normal`
- `core`
- `controls`
- `professional-ux`
- `architecture`
- `all`

`cresco-safe-mode=1` resolves to `core`.

### Asset contract

`WebsiteBuilderAsset` owns:

- plugin-relative absolute paths;
- browser URLs;
- readability checks;
- content-addressed cache versions;
- SHA-256/size diagnostics;
- refreshing registered WordPress script/style versions.

This eliminates another source of stale-cache inconsistencies.

### Editor configuration

`WebsiteBuilderEditorConfig` produces one shared Page/Theme configuration shape for:

- Session REST;
- validation;
- context REST;
- options;
- components;
- Page Settings;
- Global Settings;
- history;
- Theme templates/options;
- preview URL;
- widget catalog;
- preview widths;
- permissions;
- builder/plugin versions.

`WebsiteBuilderRuntimeGuard` writes this canonical config before the core editor runtime, so the effective browser configuration no longer depends on which compatibility service happened to construct settings first.

### Module registry

`WebsiteBuilderModuleRegistry` is the authoritative server-side catalog for:

| Module | Required | Default policy |
|---|---:|---|
| bootstrap | yes | enabled |
| core | yes | enabled |
| controls | no | enabled |
| professional-ux | no | enabled |
| architecture | no | quarantined by default |
| comprehensive-v3 | no | enabled, transitional |
| workflow | no | enabled |

Architecture remains quarantined by default until runtime/browser evidence proves the observer fix is stable under real editor usage.

## 3. Startup ownership after refactor

Startup responsibilities are now divided deliberately.

### `WebsiteBuilderBootstrapResilience`

Owns:

- bootstrap middleware asset;
- critical/optional request timeouts;
- emergency `wp.apiFetch` guard when bootstrap middleware cannot install;
- lightweight startup state publication;
- temporary observer boot guards for optional modules.

It no longer owns a competing fatal recovery UI.

### `WebsiteBuilderRuntimeGuard`

Owns:

- final content-hash refresh;
- canonical browser editor config injection;
- central optional-module enable/dequeue policy;
- Architecture quarantine policy;
- the only user-facing fatal startup recovery panel.

This is an important simplification. Multiple recovery surfaces should not race to rewrite the same editor root.

### Browser runtime state

The browser receives a small `window.crescoRuntimeState` state object with phases such as:

- `CORE_LOADED`
- `SESSION_PENDING`
- `READY`
- `FAILED`

Diagnostics can read the same state instead of inferring everything from DOM text.

## 4. Diagnostics after refactor

`Tools -> Cresco Diagnostics` remains intentionally independent from the editor tab.

It now consumes the same runtime contracts used by production startup.

The diagnostics page reports:

- WP, PHP, plugin versions;
- document context;
- Session presence, bytes, JSON validity, sanitizer result;
- every registered runtime module;
- asset readability, bytes, SHA-256, effective cache version;
- MutationObserver/static scheduling signals;
- default module quarantine state;
- REST endpoint results and timings;
- last persisted browser heartbeat.

The editor probe tracks:

- browser global errors;
- unhandled promise rejections;
- `wp.apiFetch` request lifecycle and duration;
- core readiness;
- startup state;
- module script presence;
- Architecture observer diagnostics;
- event-loop stalls;
- last heartbeat in localStorage.

Use `&cresco-debug=1` to open the browser diagnostics overlay automatically.

## 5. Module isolation workflow

Use the diagnostics page first instead of repeatedly editing PHP to disable modules.

Recommended order:

1. Core only.
2. Core + Controls.
3. Core + Professional UX.
4. Architecture alone with explicit Architecture debug flag.
5. All modules only after the smaller combinations are stable.

If Core-only freezes, investigate Session/bootstrap/core dependencies before optional UX modules.

If Core-only is stable and another combination freezes, fix the owning module rather than adding another global watchdog.

## 6. MutationObserver rule

The Architecture freeze proved that observer feedback loops can starve the browser main thread before REST startup settles.

Every optional runtime that uses `MutationObserver` should obey these rules:

1. coalesce callbacks using a scheduler such as `requestAnimationFrame`;
2. avoid DOM writes when the value is already correct;
3. ignore or isolate self-owned mutations;
4. provide lifecycle disconnect behavior;
5. expose diagnostics counters;
6. trip a guard under runaway mutation volume.

The Architecture runtime already contains the fixed `scheduleShell` observer and observer statistics in both `build/` and `runtime-src/build/`.

## 7. Workflow and V3 decoupling

`WebsiteBuilderWorkflowExtensions` no longer depends on the Comprehensive V3 presentation script.

New stable route:

`/cresco-canvas/v1/website-builder/woocommerce/templates/single`

Legacy compatibility alias retained:

`/cresco-canvas/v1/website-builder/v3/woo-single-template`

The runtime now receives the stable feature route.

This is the desired migration pattern: introduce stable capability-oriented ownership, keep the old route temporarily, then delete the alias only after compatibility evidence exists.

## 8. Comprehensive V3 status

`WebsiteBuilderComprehensiveV3` is explicitly treated as transitional compatibility code.

New stable document diagnostics route:

`/cresco-canvas/v1/website-builder/document-diagnostics/{postId}`

Legacy compatibility alias retained:

`/cresco-canvas/v1/website-builder/v3/diagnostics/{postId}`

The module still handles transitional frontend CSS replacement and V3 presentation compatibility. Do not build a V4/V5 replacement.

## 9. Repository quality gate

New command:

`npm run check:runtime-modules`

It verifies:

- all four runtime infrastructure files exist;
- expected module keys are present;
- Architecture remains explicit in policy;
- RuntimeGuard uses the module registry and canonical editor config;
- Bootstrap uses canonical bootstrap paths;
- Diagnostics consumes registry asset reports;
- stable Workflow and document-diagnostics routes exist;
- Workflow no longer depends on the V3 presentation script;
- Architecture source/build retain `new MutationObserver(scheduleShell)` and observer stats;
- no new `WebsiteBuilder*V4` through `V9` service is introduced.

`check:runtime-modules` is included in `check:quality`.

## 10. Validation completed for this change

Before publication, the refactor files were checked with:

- `php -l` for every new/replaced PHP file;
- `node --check scripts/check-runtime-modules.mjs`;
- JSON parse validation for `package.json`.

These are syntax/contract checks only.

The following still require the real repository/WordPress environment:

- full `npm run check:quality`;
- exact source/build gates;
- WordPress editor boot;
- save/reload;
- Theme editor boot;
- critical E2E;
- browser matrix;
- accessibility;
- performance;
- package install/upgrade.

## 11. Remaining transitional code

This refactor intentionally does not delete all old compatibility code in one step.

Important remaining targets:

### `WebsiteBuilderCompatibility`

Still contains legacy handle removal, fallback bootstrap behavior, contract bridging, and frontend compatibility work.

Next objective:

- move permanent behavior out;
- keep only old-handle/payload/route/token translation;
- delete fallback runtime recreation only after core startup evidence is reliable.

### `ThemeSessionBridge`

Still reconstructs editor settings and optional asset loading in its own class.

Next objective:

- consume `WebsiteBuilderEditorConfig` directly;
- consume `WebsiteBuilderAsset` for all runtime asset versions;
- use Module Registry for optional Theme-editor presentation modules.

### `BuilderArchitecture`

Application/core behavior is valid, but its editor enqueue function still contains local screen/version logic.

Next objective:

- consume RuntimeContext;
- consume Asset helper;
- let Module Registry be the only optional-module policy owner.

### `WebsiteBuilder`

Core Website Builder remains the authoritative Session/component service. Its existing Page editor settings array is retained for compatibility, while RuntimeGuard now injects the canonical effective config before browser execution.

After browser verification, the duplicate server-side settings construction can be removed safely.

## 12. Rendering rule

The long-term invariant remains:

`Session -> sanitize -> RenderEngine -> WebsiteRenderer HTML + WebsiteBuilderCssCompiler CSS`

No optional presentation module should become a second final renderer or CSS authority.

Compatibility modules may remove historical fragments, but they should not invent a parallel final CSS pipeline.

## 13. Performance policy

Do not optimize by intuition. Measure first.

Recommended target budgets after a baseline exists:

- editor shell visible: under 1 second on controlled local reference hardware;
- critical Session REST: under 500 ms local, under 1.5 seconds production target;
- core ready after Session: under 300 ms;
- node selection: under 50 ms median;
- Inspector tab switch: under 100 ms;
- idle optional observers: near-zero mutation work;
- optional module failure: must not block core;
- public frontend JavaScript: zero unless the rendered feature actually requires it.

## 14. P0 continuation work

Before another broad feature expansion:

1. pull the consolidated `main`;
2. open a known Page with normal policy and `cresco-debug=1`;
3. run Tools -> Cresco Diagnostics REST tests;
4. verify Core-only repeatedly;
5. verify Controls;
6. verify Professional UX;
7. verify Architecture explicitly;
8. verify All modules;
9. save/reload Session;
10. verify frontend render parity;
11. run `npm run check:quality`;
12. capture browser/performance evidence.

## 15. P1 cleanup work

After P0 evidence passes:

- slim `WebsiteBuilderCompatibility`;
- migrate ThemeSessionBridge to shared runtime contracts;
- migrate BuilderArchitecture enqueue logic;
- remove duplicate asset-version helpers;
- remove duplicate editor config builders;
- move diagnostics presentation JS/CSS from inline PHP into reviewed assets if useful;
- replace V3 route usage inside remaining clients with stable feature routes;
- keep compatibility aliases for a documented deprecation period.

## 16. P2 product evolution

Only after runtime consolidation is stable:

- Session-native Theme documents where appropriate;
- synchronized reusable components;
- visual Loop/template designer;
- deeper WooCommerce template controls;
- richer responsive controls;
- extension SDK around registries/contracts;
- collaboration/cloud storage adapters through `DocumentRepository` rather than direct editor coupling.

## 17. Non-negotiable invariants

Future work should preserve:

- `cresco-session/v1` readability until a deliberate migration exists;
- user-authored `post_content` preservation;
- Core independence from editor DOM;
- Core independence from a specific AI provider or WooCommerce runtime;
- optional module failure isolation;
- one final rendering authority;
- server-authoritative command/patch validation;
- scoped sanitized Custom CSS;
- no arbitrary executable Session content;
- no new numbered parallel builder generation;
- compatibility code with an explicit exit strategy.

## 18. Definition of Done for future refactors

A refactor is done only when:

1. behavior is preserved or intentionally documented;
2. source/build runtime pairs are synchronized;
3. PHP/JS syntax passes;
4. static architecture/runtime gates pass;
5. unit tests pass;
6. clean WordPress editor starts;
7. save/reload preserves Session;
8. frontend matches authoritative render;
9. diagnostics show no new fatal/degraded condition;
10. no hidden dependency on retired compatibility modules is introduced;
11. documentation changes in the same branch.

## 19. Debugging runbook

When a user reports a freeze:

1. Open `Tools -> Cresco Diagnostics`.
2. Enter the document ID.
3. Run REST tests.
4. Open Core-only.
5. Add one module at a time.
6. Inspect the persisted heartbeat.
7. Copy the full diagnostics report.
8. Identify the smallest failing module combination.
9. Fix that module.
10. Add a regression gate.
11. Remove temporary quarantine only after runtime evidence passes.

## 20. Final architecture objective

A future developer should be able to answer each of these questions with one owner:

- Who owns document persistence?
- Who owns rendering?
- Who owns editor configuration?
- Who owns module loading?
- Who owns startup state?
- Who owns fatal recovery UI?
- Who owns diagnostics?
- Who owns legacy compatibility?

For the runtime layer, this refactor establishes that direction without performing a risky big-bang deletion of compatibility code.
