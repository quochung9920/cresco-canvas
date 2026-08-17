# Project Rules

> **Scope:** Cresco Canvas repository-wide engineering rules for AI Coding Agents and developers.
>
> **Purpose:** Keep architecture, runtime, data, CSS, build artifacts, tests, and documentation aligned while Cresco Canvas evolves.
>
> **Precedence:** Direct user instructions take priority. If a requested change conflicts with these rules or with a canonical architecture contract, explain the risk before implementing it.

---

## 1. Read this before changing code

Before editing Cresco Canvas:

1. Read this file.
2. Read the canonical document for the subsystem being changed.
3. Inspect the current source before assuming behavior.
4. Search for existing owners, adapters, tests, and compatibility paths.
5. Reuse or extend the existing contract instead of creating a parallel system.
6. Patch the smallest safe surface.
7. Run the relevant verification commands after the change.

Do not infer current architecture from an old design document, stale branch, old screenshot, or legacy class name.

---

## 2. Project reality

Cresco Canvas is a WordPress visual builder with a standalone Studio editor and a portable AI-readable document model.

Current repository baseline:

- Package: `1.0.0-rc.1`
- WordPress: `6.7+`
- PHP: `8.1+`
- Node.js: `20.19+`
- npm: `10+`
- Composer: `2`
- License: GPL-2.0-or-later

For Pages with a saved Cresco document, `cresco-session/v1` is the visual source of truth. WordPress remains the host for authentication, permissions, media, routing, REST, Page records, previews, and frontend delivery.

Existing user-authored `post_content` is preserved as a fallback and must not be destructively rewritten merely because Cresco Session data exists.

The project is still release-candidate software. Do not describe it as production/commercially ready unless all required release gates have objective evidence for the exact release commit and ZIP.

---

## 3. Canonical architecture

Cresco Canvas uses a modular-monolith, contract-first architecture.

Stable dependency direction:

```text
Contracts -> Core -> Application -> Modules / Infrastructure / Presentation
```

Canonical responsibilities:

- **Contracts:** portable document, scope, command, transaction, patch, interchange, and AI envelopes.
- **Core:** document model, scope/context, commands/patches, responsive inheritance, design tokens, Widget Registry, Inspector/UI Registry, dependency policy, migrations.
- **Application:** editor workflows such as export, transaction preview/commit, render preview, save, history, and component operations.
- **Rendering:** one HTML/CSS boundary through `RenderEngine/v2` and the canonical render/compiler stack.
- **Modules:** Theme, Loop, Forms, WooCommerce, Components, AI, and future modules register capabilities rather than modifying Core contracts directly.
- **WordPress infrastructure:** storage, REST, media, users/capabilities, WP_Query, WooCommerce, ACF integration.
- **Editor presentation:** a client of Core/Application, not an independent persistence authority.

### Hard rule

Do not create a new parallel builder generation such as `V4`, `V5`, or another standalone document/render/state system.

Version contracts and migrations, not service names.

---

## 4. One document model

`cresco-session/v1` remains the authoritative editable Website Builder document model.

`cresco-document/v1` may wrap Session data with `documentType` without forcing destructive migration.

Supported/future document types share the same Session node tree and rendering architecture rather than introducing separate builders.

Do not:

- create a second page document store;
- keep a hidden DOM-only state that overrides Session after reload;
- mutate persisted Session outside validated command/transaction or approved compatibility save paths;
- rewrite user `post_content` as a side effect of saving Cresco Session data.

---

## 5. One mutation path

New editor, AI, import, clipboard, and component mutations should converge on:

```text
UI / AI / Import / Clipboard / Component
  -> CommandBus
  -> PatchValidator
  -> candidate Session
  -> Diff
  -> TransactionManager
  -> verified repository save
  -> History
```

`DocumentRepository` owns persistence. `WordPressDocumentRepository` is the current WordPress adapter.

When concurrency protection is available, preserve checksum/`ifMatch` semantics. Do not silently overwrite a newer document state.

---

## 6. Canonical rendering and WYSIWYG

The canonical visual path is:

```text
Session + Architecture v2
  -> RenderEngine/v2
  -> WebsiteRendererV2
  -> root styles + Part styles + Component styles
  -> HTML/CSS
```

The canonical Studio iframe and frontend must derive from the same normalized Session/Architecture state.

Do not create a second Page frontend renderer or CSS compiler that competes with Core Platform v2.

Compatibility renderers may translate legacy data but must not regain permanent frontend ownership.

A visual preview that differs from canonical render output is a defect; do not paper over it with Studio-only CSS.

---

## 7. Studio runtime ownership

`WebsiteBuilderStudio` is the canonical active Website Builder runtime owner.

The existing `cresco-canvas-website-builder` handle is intentionally retained for compatibility while its implementation evolves.

### Required invariants

- Exactly one Studio shell owns the editor screen.
- Optional modules never replace the core runtime.
- A failed optional feature degrades that feature only.
- Runtime context and endpoints come from the canonical server-side runtime/config owners.
- Long-lived branches must not silently lag behind `main` during runtime/UI work.

### Forbidden patterns

Do not:

- enqueue an older core Website Builder runtime after Studio;
- mount a competing `.cc-builder-app` or second `.cc-studio-app`;
- recover from optional-module failure by switching the canonical script handle to another runtime;
- let compatibility code mutate the canonical handle after ownership is enforced;
- build a second editor shell around Studio using DOM observers.

When debugging Studio startup, verify runtime ownership before debugging CSS.

---

## 8. React DOM ownership

React owns the DOM it renders.

`.cc-studio-*` classes are implementation selectors, not a public permission to restructure the React tree.

Extension order of preference:

1. `window.CrescoStudioSDK` registration.
2. Explicit Studio event/state bridge.
3. React portal into a stable host.
4. Explicit additive sibling mount point.
5. Narrow DOM enhancer only when no supported extension point exists.

Do not use against React-owned Studio content unless ownership is explicitly delegated:

```text
innerHTML = ...
replaceWith(...)
replaceChildren(...)
removing React-owned parents
reparenting canonical controls
cloning controls and hiding originals
changing input values and assuming React state changed
unbounded MutationObserver enhancement loops
```

A `MutationObserver` may discover a mount point; it must not become a second UI owner.

Observers must be narrow, idempotent, scheduled/coalesced, and tear down cleanly.

---

## 9. State ownership

Authority order for Studio-owned state:

1. Server model/sanitizer defines valid persisted state.
2. Studio React state defines current editable browser state.
3. DOM reflects state; DOM is not a competing source of truth.
4. Optional module local state is presentation/transient only unless a contract grants ownership.

Never let two modules independently own the same semantic value.

Reset/unset is a model operation. Clearing an input visually is not sufficient if an override key remains persisted.

For responsive controls, define:

- inherited display behavior;
- explicit value vs missing override semantics;
- reset/unset behavior;
- parent/base resolution;
- save/reload verification.

---

## 10. Responsive contract

`ResponsiveResolver` is the canonical Core Platform responsive inheritance authority.

Default inheritance direction:

```text
wide base -> desktop -> laptop -> tablet -> mobile
```

A smaller viewport receives larger buckets in source order followed by its more-specific override.

Do not implement a second breakpoint cascade in a widget, optional module, AI adapter, or CSS compiler.

Preview widths and effective values should come from the same published responsive contract.

---

## 11. Inspector and Widget Catalog

The Inspector is schema-driven from `WidgetCatalog` / `InspectorSchema`.

A visible control should exist because the widget contract declares the capability, and renderer/validator code must consume the same contract.

Before adding a widget control:

1. Check `WidgetCatalog` capability/schema.
2. Check validation/sanitization.
3. Check renderer/compiler consumption.
4. Check responsive/state semantics.
5. Check AI/interchange contract if applicable.
6. Add tests.

Do not create widget-specific Inspector systems that bypass the catalog.

Prefer shared control primitives for dimensions, units, responsive values, border, radius, states, and tokens when the underlying data contract is actually shared.

---

## 12. Page Settings contract

`includes/Page/PageSettings.php` is the canonical persistence/model owner for Page Settings.

The Studio Page panel and any standalone/alternate Page Settings UI are views over the same backend model. They must not diverge in persistence semantics.

### Current spacing constraints

Page Settings v2 currently uses:

- `desktop`, `tablet`, `mobile` spacing buckets;
- four sides: `top`, `right`, `bottom`, `left`;
- one shared unit for all Margin sides/buckets;
- one shared unit for all Padding sides/buckets;
- a `linked` flag;
- allowed units including `px`, `%`, `em`, `rem`, `vh`, `vw`.

Do not expose per-side/per-breakpoint units until the backend schema, sanitizer, compiler, REST contract, migrations, tests, and all UI surfaces support them.

Do not implement a UI-only `custom` unit and assume persistence supports it.

### Atomic Page Settings evolution

A persisted Page Settings feature is complete only when all relevant owners change together:

```text
defaults
-> sanitizer/validation
-> inheritance/effective-value logic
-> compiler/frontend output
-> REST payload
-> Studio Page UI
-> alternate Page Settings UI
-> AI/import-export contracts where applicable
-> tests
-> save/reload browser verification
-> docs
```

A CSS-only visual patch is not a schema upgrade.

---

## 13. Global Design and tokens

Global settings are stored in validated `cresco_canvas_settings` data.

Prefer design tokens in this order:

1. Global Design token.
2. Widget prop.
3. Structured widget style.
4. Scoped Custom CSS for the remaining special case.

Known token references compile to stable `--cc-*` CSS variables.

Do not create a second site-token store or an optional-module copy of canonical design state.

Token analysis/replacement should use the Design System/Core contract rather than scanning arbitrary HTML.

---

## 14. Custom CSS

Custom CSS is a first-class fallback, not a replacement for structured controls.

Widget Custom CSS must remain scoped to the widget contract.

Server validation rejects unsafe/out-of-contract patterns such as global selectors, raw `@media`, `@import`, external `url()`, JavaScript expressions, or markup escapes.

Responsive Custom CSS should use Cresco device buckets, not handwritten media-query ownership that bypasses the responsive model.

Do not move a reusable design-system capability into Custom CSS merely because it is faster than implementing the structured control correctly.

---

## 15. CSS cascade and file ownership

`assets/css/cresco-foundation.css` declares canonical Cresco cascade layer order and must be loaded before stylesheets that open Cresco layers.

Canonical order:

```css
@layer cresco.base, cresco.legacy, cresco.components, cresco.utilities, cresco.overrides;
```

Responsibilities:

- `cresco.base`: canonical base/editor structure and primitives.
- `cresco.legacy`: compatibility rules.
- `cresco.components`: reusable component presentation.
- `cresco.utilities`: narrow utilities.
- `cresco.overrides`: final visual polish only.

A layer does not eliminate conflicts inside the same layer. Source order and specificity still matter.

### CSS rules

- One semantic rule should have one canonical owner.
- Do not copy selectors into later files just to force the desired appearance.
- Fix the actual owner when possible.
- Check layer, load order, specificity, and duplicate ownership before adding `!important`.
- `website-builder-premium-polish.css` is presentation-only; it must not own shell structure, runtime behavior, data semantics, or DOM mount behavior.
- If polish CSS is required for the UI to function, the rule belongs elsewhere.

---

## 16. Source/build ownership

Checked-in runtime/build assets are part of the product and release process.

Where source/build runtime files are documented as byte-identical mirrors, update them together.

Do not hand-edit one generated/runtime mirror and leave its canonical partner stale.

Run the repository's build-integrity checks after changes to generated/runtime assets.

Do not assume a successful source edit means the browser is running that source file; verify the enqueued build asset and content-hashed version.

---

## 17. Runtime modules

`WebsiteBuilderModuleRegistry` is the authoritative required/optional module catalog.

Required modules must not depend on optional presentation modules.

Optional modules must:

- be additive;
- fail independently;
- expose useful diagnostics;
- avoid taking over core state/runtime/rendering;
- use bounded observers/listeners;
- provide teardown/guard behavior.

Diagnostic/isolation modes must derive from the same registry rather than a second hard-coded module list.

---

## 18. AI, interchange, import/export

AI uses the same document/contracts as the editor; it does not own a separate builder schema.

Stable scope model includes:

- `widget`
- `subtree`
- `selection`
- `document`

AI/import changes must be validated before applying and must not bypass server sanitization.

Applying imported/AI output must not silently save the document; persistence remains an explicit editor/user action unless a documented workflow states otherwise.

Prefer `cresco-patch/v1`/command/transaction semantics for scoped mutations.

Do not allow an AI response to mutate outside the exported scope boundary.

---

## 19. WordPress / PHP rules

Use WordPress APIs and project service boundaries.

For server-side writes, apply as appropriate:

- capability checks;
- nonce verification;
- validation;
- sanitization;
- output escaping;
- permissioned REST callbacks;
- safe WordPress database/storage APIs;
- slash-safe JSON persistence where required.

Do not edit WordPress Core or third-party plugin core to implement Cresco behavior.

Do not leak WordPress persistence calls across Core/Application when `DocumentRepository` or an existing infrastructure port owns the responsibility.

---

## 20. JavaScript / TypeScript rules

Prefer the existing Studio/Core registries and APIs over direct DOM coupling.

Do not create global mutable state when the Session/Studio state contract already exists.

Avoid:

- repeated DOM queries in hot loops;
- duplicate event listeners;
- unbounded scroll/resize handlers;
- observer feedback loops;
- optional modules that block core startup;
- DOM mutation as a substitute for state mutation.

For behavior that touches the canonical canvas, Inspector, Structure, Page panel, Global Design, or runtime startup, first identify the documented owner.

---

## 21. Accessibility

Accessibility is a release gate, not optional polish.

Preserve or improve:

- keyboard access;
- focus visibility;
- semantic control names;
- panel/navigation semantics;
- touch targets;
- contrast;
- reduced motion;
- screen-reader state for expandable/selectable controls;
- drag/drop alternatives where required.

Do not remove focus outlines without an equivalent visible `:focus-visible` treatment.

A configured accessibility test is not evidence that the exact release build passed.

---

## 22. Performance and reliability

Changes must avoid regressions in editor startup, responsiveness, memory, and frontend output.

Pay special attention to:

- MutationObservers;
- event-loop stalls;
- repeated rendering/compilation;
- large Session operations;
- unnecessary optional modules;
- duplicate CSS/JS owners;
- repeated DOM mutation;
- startup requests without bounds/timeouts.

Optional modules must degrade without blocking Session loading or core Studio mount.

---

## 23. Repository structure and important entry points

Important root areas include:

```text
cresco-canvas.php        Plugin bootstrap
includes/                PHP application/core/infrastructure/runtime services
contracts/               Stable machine-readable contracts
src/                     Compiled editor/block source
runtime-src/             Runtime source mirrors/contracts where applicable
build/                   Checked-in production/runtime assets
assets/css/              Studio/frontend/module CSS
blocks/                   Block metadata/styles/assets
docs/                     Architecture, behavior, release, and audit contracts
scripts/                  Build/check/release verification scripts
tests/                    Unit/integration/e2e/release evidence
```

Do not assume a similarly named `legacy`, `V2`, or `V3` file is canonical. Check ownership docs and registration code.

---

## 24. Canonical documentation authority

For current Studio/Core work, consult at minimum:

- `README.md`
- `docs/CORE_ARCHITECTURE.md`
- `docs/STUDIO_RUNTIME_OWNERSHIP_AND_CONFLICT_PREVENTION.md`
- `docs/STUDIO_EDITOR_EXPERIENCE_2.md`
- `docs/DECISIONS.md`
- `docs/CRESCO_SESSION_V1.md`
- subsystem-specific contracts such as responsive/unset/release docs

`docs/ARCHITECTURE.md` contains historical Gutenberg-native architecture. It remains useful for migration/history but must not override current Studio ownership where the documents explicitly conflict.

When architecture intentionally changes, update the relevant ADR/contract in the same change.

Do not leave old documentation phrased as current fact after it has been superseded.

---

## 25. Branch discipline

Before runtime/UI/core architecture work:

1. Confirm the working branch base against `main`.
2. Rebase/merge/fast-forward as appropriate before implementing against stale code.
3. After canonical changes land on `main`, resynchronize long-lived feature branches.
4. Re-run ownership/build checks after synchronization.

Do not diagnose a browser regression from a stale branch until branch parity is confirmed.

---

## 26. Refactoring rules

Do not refactor solely because another structure looks cleaner.

A meaningful refactor should improve at least one of:

- maintainability;
- reuse;
- accessibility;
- performance;
- consistency;
- reliability;
- security;
- ownership clarity.

For a significant refactor, record:

```text
Problem
Root cause
Canonical owner
Proposed change
Compatibility impact
Affected contracts
Migration path
Regression risk
Verification
```

Prefer consolidation behind existing Core APIs over a rewrite.

---

## 27. Delete-code rule

Do not delete code because it appears unused from one search result.

Before deletion:

1. Search PHP registration/hooks.
2. Search runtime/module registry references.
3. Search build/source mirrors.
4. Search REST routes/contracts.
5. Search tests and release allowlists.
6. Search compatibility adapters and migrations.
7. Search docs/ADR references.

If ownership is uncertain, mark technical debt rather than deleting blindly.

---

## 28. No over-engineering

Do not:

- create another builder framework;
- create another Session/document schema for the same problem;
- create another token system;
- create another responsive inheritance engine;
- create another Inspector architecture;
- create another Page Settings backend;
- create another frontend render pipeline;
- add a dependency for a small local problem;
- rewrite stable contracts for cosmetic code style reasons.

Prefer:

```text
Existing contract -> shared primitive -> compatibility adapter -> migration
```

---

## 29. Pre-change checklist

```text
[ ] I read PROJECT_RULES.md.
[ ] I identified the canonical owner of this subsystem.
[ ] I checked current source, not only docs/screenshots.
[ ] I searched for existing contracts/adapters/tests.
[ ] I checked whether my branch is current with main.
[ ] I am not creating a second source of truth.
[ ] I am not creating a competing runtime/render/state system.
[ ] UI capabilities match backend persistence/validation.
[ ] Responsive behavior uses the canonical resolver/contract.
[ ] CSS ownership/layer/source order is understood.
[ ] React-owned DOM is not being taken over by an optional module.
[ ] Source/build mirrors that must match are identified.
[ ] Accessibility impact is understood.
[ ] Performance/startup impact is understood.
[ ] Compatibility/migration impact is understood.
```

---

## 30. Post-change checklist

```text
[ ] Relevant syntax/type/lint checks pass.
[ ] Relevant unit/PHP tests pass.
[ ] Architecture/runtime ownership checks pass.
[ ] Source/build integrity passes where applicable.
[ ] No competing Studio runtime/root is mounted.
[ ] No new console/PHP errors in the affected flow.
[ ] Save -> reload preserves the intended model change.
[ ] Reset/unset removes the actual persisted override when applicable.
[ ] Canonical renderer/frontend matches intended Studio output.
[ ] Desktop/tablet/mobile responsive behavior is verified.
[ ] Keyboard/focus/accessibility behavior is verified.
[ ] Optional-module failure still degrades safely.
[ ] Documentation/ADR/contracts were updated if architecture changed.
[ ] Release claims are not stronger than available evidence.
```

---

## 31. Quality commands

Use the smallest relevant checks during development, then broader checks before merge/release.

Core commands include:

```bash
npm ci
composer install
npm run build
npm run typecheck
npm run lint:js
npm run lint:css
npm run lint:md
npm run test:unit
npm run test:php
npm run check:hygiene
npm run check:editor-runtime
npm run check:website-builder
npm run check:startup-hardening
npm run check:runtime-modules
npm run check:studio
npm run check:studio-ui
npm run check:studio-premium
npm run check:studio-unset-styles
npm run check:canonical-preview-owner
npm run check:known-defects
npm run check:architecture
npm run check:production-hardening
npm run check:build-integrity
npm run check:version
```

`npm run check:quality` aggregates the repository's main static/unit quality checks.

Browser, accessibility, performance, exact-ZIP installation, upgrade/rollback, compatibility matrices, and commercial-readiness evidence remain separate gates where documented.

A skipped/configured check is not a pass.

---

## 32. Release rules

Release packaging must remain deterministic and strict-allowlist based.

Typical release flow:

```bash
composer install --no-dev --optimize-autoloader
rm -rf build
npm run build
npm run check:build-integrity
npm run package
node scripts/verify-package.mjs
```

Do not manually add development-only source, tests, secrets, or tooling to the production ZIP outside the release ownership manifest/allowlist.

Do not call an RC build commercially ready without exact artifact evidence required by the release documents.

---

## 33. AI Coding Agent behavior

Every AI Coding Agent working in this repository must:

1. Read this file first.
2. Inspect source before assuming.
3. Search before creating.
4. Reuse before duplicating.
5. Respect canonical ownership.
6. Patch before rewriting.
7. Preserve compatibility unless a migration is explicit.
8. Keep backend/UI/render/test/docs changes atomic when a persisted contract changes.
9. Never claim a visual patch fixed persistence without save/reload evidence.
10. Never claim a model change fixed UI without browser evidence.
11. Never use stale documentation to override current runtime registration/source.
12. Never silently resolve architecture conflicts by adding another layer/system.
13. Report unverified claims as unverified.
14. Update this file if project-wide engineering rules intentionally change.

---

## 34. Non-negotiable summary

When in doubt, preserve these invariants:

```text
One Studio runtime.
One Session document model.
One canonical render path.
One responsive inheritance authority.
One backend owner per persisted setting domain.
One semantic CSS owner per rule.
React owns React-rendered DOM.
Optional modules are additive and degradable.
Source/build mirrors do not drift.
Architecture docs and code must agree.
Branches must not silently drift from main.
Release claims require evidence.
```
