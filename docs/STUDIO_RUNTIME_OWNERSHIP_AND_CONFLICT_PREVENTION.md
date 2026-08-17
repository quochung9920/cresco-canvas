# Cresco Studio Runtime Ownership and Conflict Prevention

Status: **Canonical engineering contract for the current Cresco Studio Website Builder runtime**

Audited baseline: `00be3f489dfef530d31394d951b3ea4d261cc7d3` (2026-08-17)

This document exists to prevent a class of regressions that Cresco Canvas has already encountered: a feature appears implemented in one file or branch, but the browser still renders an older UI; two runtimes or two CSS owners compete; a DOM enhancement mutates React-owned nodes; a visual control exposes values the persistence model cannot store; generated assets drift from their source mirror; or historical architecture documentation contradicts the runtime that actually ships.

The goal is not merely to avoid merge conflicts. The goal is to prevent **ownership conflicts** across runtime, DOM, state, CSS, data schema, build artifacts, optional modules, documentation, and long-lived branches.

When another document conflicts with this contract for the current Cresco Studio Website Builder, use the precedence rules in [Documentation authority](#documentation-authority-and-precedence).

---

## 1. Core invariants

The following rules are non-negotiable unless a new ADR explicitly supersedes them.

1. **One active Website Builder runtime owns the Studio shell.** `WebsiteBuilderStudio` is the canonical browser runtime owner for the current Studio experience.
2. **One Session model owns the editable Website Builder document.** `cresco-session/v1` remains the authoritative Studio document model.
3. **React owns React-rendered Studio DOM.** Optional modules may extend it through explicit extension points, portals, bridges, or stable mount hosts; they must not replace, reparent, clone, or rewrite React-owned nodes.
4. **The server-side schema and sanitizer define what can be persisted.** UI controls may not advertise unsupported values or structures.
5. **A persisted feature must evolve atomically.** Defaults, sanitizer, compiler/renderer, REST payload, Studio UI, alternate UI surfaces, tests, AI/export contracts, and docs must change together when the data model changes.
6. **CSS ownership is explicit.** Foundation declares cascade layer order first. Structural rules and presentation polish have different owners.
7. **Same-layer source order still matters.** Cascade layers reduce accidental precedence, but they do not eliminate conflicts between selectors in the same layer.
8. **Generated/runtime mirrors do not drift.** Studio source/build runtime files that are expected to be byte-identical must be updated together.
9. **Optional modules are additive.** They may degrade or fail independently without taking ownership of the core Studio shell.
10. **A long-lived feature branch must not silently lag behind `main`.** Runtime/UI work starts from a verified current base and is re-synchronized after canonical changes land.
11. **Historical documentation is never allowed to silently override current code ownership.** Superseded decisions remain as history but must be labeled as such.
12. **A visual change is not proof of a model change, and a model change is not proof of a visual change.** Both must be verified independently.

---

## 2. Canonical runtime map

The current Studio stack is intentionally split into owners with narrow responsibilities.

| Area | Canonical owner | Responsibility | Must not do |
| --- | --- | --- | --- |
| Studio browser shell | `build/website-builder-studio.js` + its source mirror | React app, primary state, panels, Canvas, Structure, Inspector, Page panel, commands, save flow | Allow another runtime to mount a competing Studio app |
| Runtime registration | `includes/Builder/WebsiteBuilderStudio.php` | Canonical script handle, dependencies, content-hashed versions, support assets, runtime diagnostics | Register a second competing core editor handle for the same screen |
| Runtime context | `WebsiteBuilderRuntimeContext`, `WebsiteBuilderEditorConfig` | Resolve page/editor context and endpoint configuration | Let optional modules invent independent document identity or endpoints |
| Module activation | `WebsiteBuilderModuleRegistry` | Decide whether core/optional modules are active for the context | Let modules bypass registry ownership rules |
| Responsive Inspector enhancement | `build/website-builder-responsive-properties.js` | Per-property responsive UI, grouping/accordion, widget-aware control enhancement | Become the source of truth for widget data or rewrite React state directly |
| UI structural correction | `build/website-builder-ui-correction.js`, `assets/css/website-builder-ui-correction.css` | Canonical structural corrections around Studio controls/layout | Become a second data model or styling theme |
| Explicit unset/reset semantics | `build/website-builder-unset-styles.js` + `docs/STYLE_UNSET_SEMANTICS.md` | Clear/remove responsive style keys through an explicit model bridge | Fake an unset by only blanking a DOM input |
| Responsive inheritance extension | `build/studio-responsive-inheritance.js`, `assets/css/studio-responsive-inheritance.css` | Expose inheritance semantics without replacing the Studio runtime | Persist a second responsive schema |
| Foundation/layer order | `assets/css/cresco-foundation.css` | Declare global Cresco cascade layer order and shared foundation | Be loaded after stylesheets that already establish an incompatible layer order |
| Base Studio presentation | `assets/css/website-builder-studio.css` | Core Studio structural/base styles | Become a place for unrelated feature overrides |
| Premium polish | `assets/css/website-builder-premium-polish.css` | Presentation-only refinement in the override layer | Own layout structure, DOM visibility contracts, runtime behavior, or data semantics |
| Page Settings persistence | `includes/Page/PageSettings.php` | Page Settings defaults, sanitization, effective values, frontend compilation and REST persistence | Accept a UI-only schema that cannot compile consistently |
| Studio Page Settings view | `pagePanel()` in Studio runtime | Edit the canonical Page Settings model from the Studio Page rail | Create a different Page Settings schema |
| Alternate/standalone Page Settings view | standalone Page Settings runtime | Present another view over the same Page Settings backend | Diverge in persistence semantics from the Studio Page panel |
| Global Design Pro UI | Global Design Pro module/bridge | Extend the canonical Global Design surface using a safe mount/portal bridge | Replace/reparent the legacy/canonical React-owned panel or create competing site-token state |
| Legacy Website Builder assets | legacy builder files, where still retained | Compatibility only when explicitly selected/required | Load after Studio and visually/runtime-take over the same editor surface |

A new module must be added to this map, or to an equally explicit successor contract, before it is allowed to modify a Studio-owned surface.

---

## 3. Single runtime ownership

### 3.1 Canonical handle

The Studio runtime owns the existing `cresco-canvas-website-builder` script handle through `WebsiteBuilderStudio`. This is intentional: optional code that already depends on the canonical Website Builder handle remains compatible while the active script implementation can evolve.

The owner is responsible for:

- setting the canonical script `src`;
- setting dependencies;
- applying a content-addressed version;
- enqueueing the script once;
- attaching `window.crescoWebsiteBuilderSettings` before execution;
- declaring the expected runtime as `studio`;
- enqueueing only support assets that belong to the same runtime family.

### 3.2 Forbidden competing-runtime patterns

Do not:

- enqueue an older Website Builder core script after Studio on the same editor screen;
- mount a second `.cc-studio-app` or legacy `.cc-builder-app` as a fallback after Studio has mounted;
- recover from an optional module failure by re-registering the core handle to another runtime;
- let a compatibility service mutate the canonical handle after `WebsiteBuilderStudio::enforce_runtime_ownership()`;
- use a DOM observer to detect Studio and then mount a second editor shell around it.

A failure in an optional feature must result in **feature degradation**, not runtime replacement.

### 3.3 Runtime diagnostics

During browser troubleshooting, verify at minimum:

- `window.crescoExpectedWebsiteBuilderRuntime === 'studio'`;
- `window.crescoWebsiteBuilderEditorBoot` reaches the expected Studio phase;
- `window.crescoStudioRuntimeOwnership` reports a Studio mount and no legacy competing mount;
- exactly one `.cc-studio-app` exists;
- no unexpected legacy root is mounted inside `#cresco-canvas-standalone-editor`;
- the loaded Studio asset URL carries the expected content-hashed version.

If these conditions are not true, fix runtime ownership before investigating visual CSS.

---

## 4. React DOM ownership

### 4.1 React-rendered DOM is not an extension API

Classes such as `.cc-studio-*` are implementation selectors, not permission to mutate the node tree arbitrarily. React expects the DOM shape it rendered to remain stable.

Optional modules must prefer, in order:

1. `window.CrescoStudioSDK` extension registration;
2. an explicit event/state bridge owned by Studio;
3. a React portal into a stable host that remains owned by the canonical panel;
4. an additive sibling mount point expressly reserved for the module;
5. a narrowly scoped DOM enhancer only when no React extension point exists.

### 4.2 Forbidden DOM operations

An optional module must not use any of the following against React-owned Studio content unless the core runtime explicitly delegates ownership:

- `innerHTML = ...`;
- `replaceWith(...)`;
- `replaceChildren(...)`;
- removing a React-rendered parent;
- reparenting React-rendered controls into another container;
- cloning canonical controls and hiding the originals;
- changing input values and assuming React state changed;
- repeatedly appending controls on every `MutationObserver` callback without idempotency.

### 4.3 MutationObserver rules

A `MutationObserver` may be used for **discovery**, not ownership.

A safe observer:

- observes the narrowest stable root;
- has an idempotent mount marker;
- schedules/debounces work;
- disconnects or becomes inert after its mount is stable when continuous observation is unnecessary;
- does not create mutations that recursively retrigger an unbounded enhancement loop;
- does not treat text labels as the only durable identity when an explicit id/data attribute can be provided.

### 4.4 Portal/bridge pattern

The stabilized Global Design Pro mount is the preferred pattern when an enhancement must appear inside an existing React-owned panel:

- discover a stable host;
- do not replace the host;
- create one additive mount container;
- render/portal the enhancement into that container;
- read/write through the canonical state bridge;
- unmount cleanly when the host disappears;
- remount idempotently if React recreates the host.

This pattern must be reused rather than reinventing panel takeover logic.

---

## 5. State ownership

### 5.1 Sources of truth

For Studio-owned data, authority is layered:

1. **Server sanitizer/model** defines valid persisted state.
2. **Studio React state** defines the current editable browser state.
3. **Rendered DOM** reflects React state; it is not a competing source of truth.
4. **Optional module local state** may hold presentation/transient state only, unless an explicit contract grants ownership.

### 5.2 No silent duplicate state

Do not let two modules independently own the same semantic value. Examples of prohibited duplicates:

- Studio Page panel stores one Margin value while Page Settings Pro stores another shape;
- an Inspector enhancer keeps a `sessionStorage` value that overrides the Session model after reload;
- a Global Design enhancement has a separate token object that is not reconciled with the canonical settings state;
- a DOM input is visually cleared while the responsive override key remains in the Session.

### 5.3 Reset/unset is a model operation

Responsive style reset must remove the correct override from the canonical model. It must not be implemented as a visual blank input only. The exact contract is documented in `docs/STYLE_UNSET_SEMANTICS.md`.

When adding a new responsive control, define all of the following:

- how an inherited value is displayed;
- how an explicit empty value differs from a missing override, if the property permits that distinction;
- how Reset/Unset is dispatched;
- how the parent/base value is resolved;
- how save/reload proves the override was actually removed.

---

## 6. CSS cascade and ownership contract

### 6.1 Foundation must declare layer order first

`assets/css/cresco-foundation.css` declares the canonical order:

```css
@layer cresco.base, cresco.legacy, cresco.components, cresco.utilities, cresco.overrides;
```

Layer order is fixed by first declaration. Therefore the foundation stylesheet must be enqueued before any stylesheet that opens a Cresco layer.

Do not create another stylesheet that declares a different order first.

### 6.2 Layer responsibilities

| Layer | Intended responsibility |
| --- | --- |
| `cresco.base` | Canonical base/editor structure and primitives |
| `cresco.legacy` | Compatibility rules that must remain isolated from current owners |
| `cresco.components` | Reusable component-level presentation |
| `cresco.utilities` | Narrow utility rules with explicit scope |
| `cresco.overrides` | Final presentation polish only; not structural ownership |

### 6.3 A layer is not a conflict shield

Two stylesheets inside `cresco.base` can still conflict. Within the same layer, normal cascade rules, specificity, and source order still apply.

Therefore:

- one semantic rule should have one canonical owner;
- if two base stylesheets target the same property on the same element, document which one is expected to win and why;
- avoid copying a selector into a later file just to “force” an appearance;
- prefer moving the rule to the actual owner rather than accumulating patches;
- before adding `!important`, verify whether the declaration is compensating for a duplicate owner.

### 6.4 Structural CSS vs polish CSS

`assets/css/website-builder-premium-polish.css` is presentation-only. It may refine:

- colors;
- shadows;
- gradients;
- visual borders;
- focus appearance;
- subtle hover motion;
- non-structural radii.

It must not become the owner of:

- grid/flex architecture of the Studio shell;
- whether a core panel/control exists;
- display/hide contracts required for runtime behavior;
- source ordering of interactive controls;
- responsive data semantics;
- DOM mount behavior.

If a polish rule is required for the UI to function, the rule is in the wrong file.

### 6.5 `!important` policy

Use `!important` only when the rule represents a deliberate hard boundary, such as suppressing WordPress admin chrome on the dedicated editor screen or enforcing a documented compatibility guarantee.

Do not use `!important` as the first response to a Studio-vs-legacy conflict. First identify:

1. which stylesheet owns the selector;
2. which layer each rule is in;
3. load order;
4. specificity;
5. whether both rules should exist at all.

---

## 7. Page Settings: canonical model contract

Page Settings is the area most vulnerable to UI/schema drift because more than one UI surface can edit the same backend object.

### 7.1 Canonical persistence owner

`includes/Page/PageSettings.php` owns the persisted Page Settings model.

The current model is version 2 and includes, at minimum:

```json
{
  "version": 2,
  "layout": "full-width",
  "pageTitle": "hide",
  "header": "inherit",
  "footer": "inherit",
  "contentRoot": "viewport",
  "bodyStyle": {
    "margin": {
      "unit": "px",
      "linked": true,
      "desktop": { "top": "", "right": "", "bottom": "", "left": "" },
      "tablet":  { "top": "", "right": "", "bottom": "", "left": "" },
      "mobile":  { "top": "", "right": "", "bottom": "", "left": "" }
    },
    "padding": {
      "unit": "px",
      "linked": true,
      "desktop": { "top": "", "right": "", "bottom": "", "left": "" },
      "tablet":  { "top": "", "right": "", "bottom": "", "left": "" },
      "mobile":  { "top": "", "right": "", "bottom": "", "left": "" }
    },
    "background": {}
  },
  "customCSS": "",
  "scrollSnap": {}
}
```

This example is schematic. The PHP defaults/sanitizer remain authoritative for the complete object.

### 7.2 Current spacing semantics

The current Page Settings spacing contract has important limits:

- Margin has **one shared unit** for all four sides and all responsive buckets.
- Padding has **one shared unit** for all four sides and all responsive buckets.
- Allowed units are currently `px`, `%`, `em`, `rem`, `vh`, `vw`.
- Values are stored as numbers/number strings separate from the shared unit.
- `linked` is part of the control model, but every UI surface must implement its meaning consistently if it exposes the link control.
- Responsive buckets are `desktop`, `tablet`, `mobile`.
- Missing tablet/mobile side values inherit through the backend resolution logic.

**Do not implement per-side units in the UI while the backend still stores one shared unit.** A UI that visually allows Top=`2rem` and Left=`24px` would be lying to the user because the current persistence/compiler cannot faithfully store that distinction.

### 7.3 Responsive resolution

The backend resolves spacing in order:

- desktop uses desktop values;
- tablet begins with desktop and overrides with non-empty tablet values;
- mobile begins with desktop, then tablet, then overrides with non-empty mobile values.

Resetting a tablet/mobile side therefore means removing that device override so inheritance resumes. It is not equivalent to persisting `0`.

### 7.4 Multiple views, one model

The Studio `Page` rail panel and any standalone/classic Page Settings UI are **views over the same backend model**, not independent Page Settings products.

If Page Settings Pro enhances an alternate Page Settings surface, that does not automatically upgrade `pagePanel()` in the Studio runtime. Conversely, changes to Studio `pagePanel()` do not change the backend schema unless `PageSettings.php` changes.

Any Page Settings feature must explicitly list every UI surface it affects.

### 7.5 Atomic Page Settings schema change protocol

A persisted Page Settings capability is complete only when the same change set updates all applicable layers:

1. `PageSettings::defaults()`;
2. sanitizer/validation;
3. effective/inheritance logic;
4. frontend compiler;
5. REST read/write behavior;
6. Studio `pagePanel()` controls;
7. standalone/alternate Page Settings view, if it exposes the same field;
8. import/export/AI context if the field is portable;
9. unit tests in `tests/php/PageSettingsTest.php` or a dedicated successor test;
10. browser save/reload verification;
11. this contract or the feature-specific documentation.

A PR that changes only step 6 is a UI experiment, not a complete persisted Page Settings feature.

### 7.6 Border and radius warning

The Widget Inspector currently has style properties for Border and Border Radius. That does **not** mean Page Settings has the same persistence contract.

Do not expose Page-level Border/Radius controls merely by copying Widget Inspector controls. First design the Page Settings schema, sanitizer, compiler, inheritance behavior, allowed units, linked/unlinked semantics, reset semantics, and tests.

### 7.7 Shared control primitives, separate adapters

The long-term UI architecture should reuse control primitives while retaining domain-specific adapters:

```text
Control primitives
  ├─ UnitSelect
  ├─ ResponsiveSelector
  ├─ LinkedSidesControl
  ├─ DimensionControl
  ├─ BorderControl
  └─ Reset/Inherit affordance
        ↓
Widget style adapter            Page Settings adapter
(Session style/responsive)      (PageSettings v2 or successor)
```

Do not create a third independent control engine with its own semantics. Reuse presentation/interaction primitives, but adapt them to the canonical storage model of each domain.

---

## 8. Widget responsive style contract

Widget responsive styling is not the same schema as Page Settings spacing.

The Studio uses the device sequence:

- `wide` / base;
- `desktop`;
- `laptop`;
- `tablet`;
- `mobile`.

The responsive Inspector enhancer groups properties into canonical categories such as Display & Size, Spacing & Gaps, Alignment, Flexbox, Grid, Typography, Background, Border, Effects, Margin & Padding, Position & Layer, Overflow & Visibility, Transform & Effects, Media & Cursor, and Custom CSS.

Rules:

1. The enhancer may organize or proxy controls, but the Session remains authoritative.
2. A property reset must remove the correct override through the explicit unset contract.
3. The same property must not be simultaneously owned by a React control and a second hidden input with independent state.
4. Device/state interactions must be documented. State styling must not accidentally create a second breakpoint cascade.
5. If a composite control represents several style keys, the adapter must define exactly which keys it reads/writes/resets.
6. Border controls should operate on the existing Widget style keys; do not reuse those keys for Page Settings without an explicit adapter.

---

## 9. Global Design and optional module ownership

Global Design Pro established an important safe-extension pattern: **mount into a stable host without taking ownership of the host**.

For optional modules:

- use module-registry activation;
- depend on the canonical Studio handle when load order matters;
- never register another core Website Builder runtime;
- fail closed to the feature, not to the editor;
- use canonical settings/session bridges;
- keep local state transient where possible;
- mount once and unmount cleanly;
- do not assume a panel's current text/DOM shape is a permanent API if a formal SDK hook exists.

If an optional module cannot operate without replacing a core Studio node, the core needs a new explicit extension point before the module ships.

---

## 10. Build/source parity and cache safety

### 10.1 Studio runtime parity

Where the repository keeps a source/runtime mirror and a production `build/` copy of the same Studio runtime, they must remain byte-identical when that is the declared release invariant.

A change is incomplete if it updates only one copy.

Recommended verification:

```text
sha256(source-runtime-file) == sha256(build-runtime-file)
```

Do not “fix production quickly” by editing only `build/` and planning to synchronize source later.

### 10.2 Content-addressed asset versions

`WebsiteBuilderAsset` versions canonical Studio assets using their content hash. This is the preferred cache invalidation boundary.

When an old UI appears after a deployment:

1. inspect the actual script/style URL in DevTools;
2. confirm the query/version changed with file content;
3. confirm the expected file path is registered on the canonical handle;
4. only then investigate browser/proxy caches.

Do not assume every old-looking UI is a cache problem. If the current React source still renders the old control shape, no cache clear can create a newer structure that is not implemented.

---

## 11. Branch and merge discipline

Runtime ownership bugs are frequently branch-age bugs disguised as frontend bugs.

### 11.1 Before starting Studio/runtime work

Verify the target base branch and compare it with `main`.

Required questions:

- What commit is `main` on?
- What commit is the feature branch on?
- Is the feature branch an ancestor of `main`, ahead of it, or diverged?
- Did `main` recently change Studio runtime ownership, CSS foundation/layers, Page Settings, Global Design, responsive inheritance, or optional module mounting?

If a long-lived branch is simply behind and can fast-forward safely, fast-forward it before editing.

### 11.2 During work

- Keep runtime ownership changes small and explicit.
- Do not mix unrelated UI takeover fixes into visual-polish commits.
- Avoid no-op “retry” commits as a substitute for a diagnosed change.
- Do not force-update a shared branch merely to make a comparison disappear.
- If the branch must diverge, document why and what must be reconciled before merge.

### 11.3 Before merge

Re-read the latest `main` versions of:

- `includes/Builder/WebsiteBuilderStudio.php`;
- Studio runtime JS;
- responsive-properties JS;
- UI-correction JS/CSS;
- foundation CSS;
- premium polish CSS;
- Page Settings backend and tests;
- any module that mounts into the same panel being changed.

Then perform a semantic conflict review even if Git reports no textual conflict.

A **semantic conflict** includes:

- two files both believing they own the same DOM node;
- two CSS files targeting the same property for different reasons;
- two controls representing the same setting differently;
- two runtime modules registering the same handle;
- a new UI value that the sanitizer discards;
- a source/build mirror mismatch.

### 11.4 After canonical changes land

Long-lived integration branches should be synchronized again. Do not leave a branch pointing at an old runtime for days while continuing UI work on top of it.

---

## 12. Documentation authority and precedence

The repository contains documents from multiple architecture generations. They are useful history but cannot all be simultaneously normative.

For the current Cresco Studio Website Builder, use this precedence:

1. current executable code + tests;
2. current ADRs that explicitly apply to Studio-owned Website Builder documents;
3. this document;
4. `docs/CORE_ARCHITECTURE.md` and `docs/WEBSITE_BUILDER_CORE.md` where they describe the current Studio stack;
5. feature-specific current docs such as `docs/STUDIO_EDITOR_EXPERIENCE_2.md`, `docs/STYLE_UNSET_SEMANTICS.md`, and `docs/STUDIO_PREMIUM_POLISH.md`;
6. historical Gutenberg-native architecture docs for their original scope only.

If code and this document diverge accidentally, treat that as a defect: update the code or the documentation in the same change that resolves the discrepancy.

### 12.1 ADR policy

Do not delete old ADRs merely because architecture evolved. Instead:

- preserve the decision as history;
- label its scope or superseded status;
- add a new ADR that names what it supersedes;
- link the new canonical contract.

This prevents a future agent from reading an older confident statement such as “no custom workbench shell” and incorrectly removing the current Studio runtime.

---

## 13. Change protocols

### 13.1 Visual-only Studio polish

Allowed scope:

- premium polish CSS;
- non-structural visual tokens;
- hover/focus/shadow/color/radius refinements that do not affect behavior.

Required verification:

- no DOM ownership change;
- no data/schema change;
- no hidden/visible behavior required for functionality;
- keyboard focus remains visible;
- reduced-motion behavior remains acceptable.

### 13.2 Structural Inspector/Page UI change

Required review:

- identify the React owner;
- identify all enhancers observing the same DOM;
- decide whether the change belongs in React, UI-correction, or an SDK extension;
- verify selectors used by responsive/unset modules still resolve;
- verify no module depends on text labels that the change renames;
- add stable data attributes/extension hooks instead of increasing DOM scraping.

### 13.3 New Page Settings field

Required in one coordinated change:

- schema/default;
- sanitization;
- persistence;
- effective/inheritance semantics;
- frontend compile/render;
- every editing surface that should expose it;
- tests;
- AI/export/import contract if applicable;
- docs.

### 13.4 New optional Studio module

Before implementation, define:

- module id and registry dependency;
- mount target or SDK hook;
- state source of truth;
- failure behavior;
- cleanup/unmount behavior;
- CSS layer/file ownership;
- tests/diagnostics.

### 13.5 Core runtime replacement

A core runtime replacement is an architectural migration, not an ordinary feature. It requires a new ADR and must explicitly address:

- canonical handle ownership;
- optional module dependency compatibility;
- Session compatibility/migration;
- CSS ownership;
- diagnostics;
- rollback/recovery;
- source/build migration;
- stale historical docs.

---

## 14. Verification matrix

No single test proves the Studio is conflict-free. Use layered verification.

### 14.1 Static/code verification

- PHP syntax passes for changed PHP files.
- JavaScript syntax/build checks pass for changed runtime files.
- Studio source/build parity passes.
- no duplicate canonical runtime registration was introduced.
- CSS foundation remains first in Cresco layer declaration order.
- optional module dependencies point toward the canonical runtime, not around it.

### 14.2 PHP tests

At minimum, review/run the suites relevant to the change. Important existing coverage includes:

- `tests/php/PageSettingsTest.php` for Page Settings defaults, sanitization, inheritance, background/custom CSS and frontend behavior;
- `tests/php/StudioHardeningTest.php` for Studio persistence/concurrency isolation behavior;
- `tests/php/WebsiteBuilderTest.php` for Website Builder contracts;
- feature/module tests for the optional module being modified.

A UI schema change is not accepted because JavaScript renders successfully; its PHP persistence tests must prove the value survives sanitization and compilation.

### 14.3 Browser smoke matrix

For Studio-affecting changes, verify at least:

- first load;
- save;
- reload;
- dirty-state guard;
- Desktop/Tablet/Mobile behavior relevant to the feature;
- switch away from and back to the panel;
- mount/unmount of optional panel enhancement;
- keyboard access/focus;
- no duplicate controls after repeated navigation;
- no console error or MutationObserver loop;
- no legacy runtime mount.

For Page Settings specifically:

1. set a desktop value;
2. set/clear a tablet override;
3. set/clear a mobile override;
4. save;
5. reload editor;
6. verify returned UI values;
7. verify frontend computed result;
8. verify reset returns to inherited value rather than zero.

---

## 15. Troubleshooting playbook

### Symptom: “I changed the UI but the old Page Settings still appears”

Check in this order:

1. **Branch SHA** — is the deployed/working branch actually at the intended `main` commit?
2. **Runtime asset** — is `build/website-builder-studio.js` the file being served by the canonical handle?
3. **Actual React implementation** — does `pagePanel()` contain the new structure? If not, CSS/cache cannot create it.
4. **Duplicate runtime** — is a legacy runtime mounting after Studio?
5. **CSS owner** — is the structure correct but visually overridden?
6. **Schema owner** — is the control trying to represent data that Page Settings v2 cannot store?

### Symptom: “CSS is loaded but nothing changes”

Verify:

- selector matches the current DOM;
- the file is in the expected cascade layer;
- a later same-layer rule is not winning;
- an override-layer rule is not intentionally winning;
- the property is not inline/React-controlled;
- the desired change is truly visual rather than structural.

### Symptom: “Reset looks correct until reload”

The DOM was probably changed without removing the model override. Use the explicit unset bridge and verify the persisted Session.

### Symptom: “A Pro panel duplicates itself”

Check mount idempotency, MutationObserver re-entry, host recreation, and unmount cleanup. One host must have at most one module mount instance.

### Symptom: “A control accepts a value, but save changes/removes it”

Inspect the PHP sanitizer before touching CSS. The UI is probably ahead of the schema or using a different shape/unit contract.

### Symptom: “A fix exists in GitHub but not in my branch”

Compare branch heads. If the feature branch is an ancestor of current `main`, fast-forward it. Do not debug stale code as if it were current code.

---

## 16. Pre-merge conflict checklist

A Studio/UI PR is not ready until the author/reviewer can answer **yes** to all applicable items.

### Runtime

- [ ] Exactly one core Studio runtime owns the editor screen.
- [ ] No optional module re-registers or replaces the canonical handle.
- [ ] Failure of the new feature does not block core Studio startup.

### DOM

- [ ] React-owned nodes are not replaced/reparented/cloned.
- [ ] Any observer is discovery-only and idempotent.
- [ ] Any portal/mount has deterministic cleanup.
- [ ] Stable ids/data attributes are used instead of fragile label scraping where possible.

### State

- [ ] There is one source of truth for every persisted field.
- [ ] Reset/unset changes the model, not merely the DOM.
- [ ] Local module state is transient or explicitly synchronized.

### CSS

- [ ] Foundation layer order remains canonical.
- [ ] Structural rules live with the structural owner.
- [ ] Premium polish remains presentation-only.
- [ ] Same-layer selector collisions were reviewed.
- [ ] New `!important` usage has a documented hard-boundary reason.

### Page Settings / schema

- [ ] The UI only exposes values the backend can store.
- [ ] Defaults, sanitizer, compiler, REST, UI and tests agree on the data shape.
- [ ] All relevant Page Settings views use the same semantics.
- [ ] Responsive inheritance and reset behavior survive save/reload.

### Build and branch

- [ ] Source/build mirrors are synchronized.
- [ ] The branch was compared with latest `main` before merge.
- [ ] No recent runtime/CSS/module owner on `main` was missed.
- [ ] The change contains diagnosed modifications rather than no-op retry commits.

### Documentation

- [ ] A new architectural ownership rule has an ADR or updates this contract.
- [ ] Superseded docs are labeled, not silently left contradictory.
- [ ] Feature docs describe the current UI surface(s), not a similarly named legacy surface.

---

## 17. “Never do this” list

Do not:

- assume a screenshot mismatch is cache before checking the actual React source;
- fix an old branch without first checking whether `main` already changed the same runtime;
- create a second editor runtime as a fallback;
- make premium-polish CSS responsible for functional layout/visibility;
- solve CSS ownership conflicts by endlessly increasing specificity;
- directly rewrite React-owned DOM to create a “new panel”;
- write a DOM input and assume the React/Session state changed;
- expose per-side Page Settings units while the backend stores one shared unit;
- copy Widget Border controls into Page Settings without creating a Page Settings border schema;
- let Studio Page Settings and standalone Page Settings use different persistence shapes;
- edit only one side of a source/build mirror;
- preserve a contradicted historical architecture statement without a superseded warning;
- use no-op commits as evidence that a feature was retried/fixed;
- call a feature complete until save/reload and the compiled/frontend result agree.

---

## 18. Current audit findings recorded by this contract

The 2026-08-17 audit that produced this document found the following important facts:

1. A long-lived Studio branch had lagged behind `main`, which contained later runtime/CSS/Global Design fixes. The branch was fast-forwarded before continuing diagnosis.
2. The current Studio Page panel still renders its own Page Settings 2.0 control structure. A separately implemented “Page Settings Pro” surface must not be assumed to replace that Studio panel automatically.
3. Page Settings v2 currently persists one shared unit per Margin control and one shared unit per Padding control. UI redesign must respect that until the schema changes.
4. The Widget Inspector has a richer Border/Radius and per-property responsive system, but those controls cannot be copied directly into Page Settings without a backend schema/adapter change.
5. The repository now has an explicit CSS foundation/layer order plus UI-correction and premium-polish layers, but same-layer source ordering still needs ownership discipline.
6. The stabilized Global Design Pro mount demonstrates the preferred portal/bridge pattern for extending a React-owned panel.
7. Historical documentation still described Gutenberg as the only Page editor and prohibited a custom workbench. Those statements are historical for the old Gutenberg-native architecture and are superseded for Studio-owned Website Builder documents by current ADRs and this contract.

These findings are intentionally recorded so a future agent does not have to rediscover the same conflict chain from screenshots and commit history.

---

## 19. Definition of “conflict-free enough to ship”

No documentation can guarantee that future code will never conflict. For Cresco Studio, “conflict-free enough to ship” means:

- ownership is unambiguous;
- there is one runtime and one persisted model per domain;
- extension mechanisms are additive;
- CSS precedence is intentional;
- UI capabilities match storage/compiler capabilities;
- source/build and branch state are current;
- automated tests cover persistence contracts;
- browser smoke tests prove save/reload and responsive behavior;
- historical docs cannot plausibly direct a future contributor to reintroduce the superseded architecture.

If any one of those conditions is unknown, the change is not yet verified; record it as unverified rather than assuming success.
