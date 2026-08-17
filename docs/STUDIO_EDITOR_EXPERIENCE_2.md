# Cresco Studio Editor Experience 2.0

Baseline: `c77fbd0eb166f9cfb9d9bd202ec4e6464cd5511b`

This release keeps `cresco-session/v1` and the existing renderer/storage contracts while replacing the active Website Builder browser shell with a unified Session-native Studio runtime. It does not introduce another numbered builder generation.

> **Architecture authority:** this document describes the Studio 2.0 feature experience and its release baseline. For current runtime ownership, React DOM extension rules, CSS cascade ownership, Page Settings schema/UI parity, branch synchronization, source/build parity, diagnostics, troubleshooting and pre-merge conflict checks, use `docs/STUDIO_RUNTIME_OWNERSHIP_AND_CONFLICT_PREVENTION.md`. Historical Gutenberg-only architecture statements do not override that contract for Studio-owned Website Builder documents.

## P0 - Runtime, Architecture and Diagnostics

- `WebsiteBuilderStudio` replaces the core browser script on the existing `cresco-canvas-website-builder` handle, preserving optional-module dependency compatibility.
- The Studio consumes `WebsiteBuilderRuntimeContext`, `WebsiteBuilderEditorConfig`, `WebsiteBuilderAsset` and `WebsiteBuilderModuleRegistry`.
- Critical Session loading remains first; optional context/settings/platform requests use bounded asynchronous requests.
- Runtime diagnostics record request durations, editor events, event-loop stalls, persisted heartbeat and recovery state.
- Local crash recovery and dirty-document protection are built into the Studio.
- Architecture remains quarantined by default until browser evidence supports enabling it normally.
- Tools -> Cresco Diagnostics remains the independent troubleshooting surface.

## P1 - Editor experience

### Structure Navigator 2.0

The Structure panel is a real Session tree rather than a flattened visual list. It supports per-node expand/collapse, expand/collapse all, search with ancestor reveal, keyboard navigation, inline rename, multi-select, lock/visibility indicators, quick actions, context menu actions and validated before/inside/after drag/drop. Closed containers auto-expand during drag hover.

### Widget Controls 2.0

The Inspector is schema-driven from `WidgetCatalog`. Content controls, shared layout/style/advanced properties, spacing, units, responsive overrides, state overrides, tokens and scoped Custom CSS use one interaction model. Bulk style editing is supported for multi-selection.

### Page Settings 2.0

The Page panel exposes the existing hardened Page Settings schema using visual controls for shell/layout, title/header/footer, classic and gradient backgrounds, background media, responsive body spacing, scroll snap and page-scoped Custom CSS.

The Page panel is a **view over the canonical `PageSettings` backend model**. A similarly named standalone or Pro Page Settings enhancement does not automatically replace this Studio panel. Any new persisted Page Settings control must remain within the backend schema or update defaults, sanitization, compiler, every relevant editing surface and tests atomically as required by the ownership contract.

### Responsive 2.0

Wide/Desktop/Laptop/Tablet/Mobile previews share the same Session. Widget styles inherit from base into breakpoint overrides; individual properties and entire breakpoint overrides can be reset. Previous breakpoint values can be copied forward. Visibility is editable per breakpoint.

## P2 - Professional workflow

- Reusable Components: create from selection, insert, synchronize current-document instances, detach and delete.
- Clipboard: copy/paste nodes with recursive ID remapping, copy/paste style data and paste before/inside/after.
- Dynamic Data: insert safe Dynamic Field, Loop Grid and Woo product widgets using existing widget contracts.
- Command Palette: Ctrl/Cmd+K commands for navigation, save/history, devices and widget insertion; extension commands can be registered.
- History & Recovery: local undo/redo, server revisions, autosave, dirty-state guard, crash recovery and same-browser edit warnings.
- Canvas: synchronized selection, quick actions, responsive preview and context menus.

## P3 - AI, Theme, Loop and WooCommerce

AI uses the existing interchange boundary. Scope can be `widget`, `subtree`, `selection`, `selection-subtrees` or the whole document. `selection-subtrees` exports multiple selected roots together with all descendants while automatically collapsing overlapping ancestor/child selections so the same node is never duplicated in an AI package.

Exported `cresco-interchange/v1` packages can be processed externally; imported Session/interchange payloads are validated and previewed before applying to the editor. Applying never directly saves the document.

Theme Builder remains a shared domain service while the Studio provides the same shell for Theme-document routes supplied by canonical editor config. Loop Grid and WooCommerce widgets use the same Inspector/responsive/style contracts rather than separate presentation systems.

## P4 - Ecosystem foundation

`window.CrescoStudioSDK` exposes stable registration points for commands, panels, Inspector sections, context actions and document adapters. Registered panels and Inspector sections render directly in the Studio.

`WebsiteBuilderPlatform` provides a provider-neutral extension manifest, document-adapter registry, lightweight presence and document/node comments. The built-in WordPress adapter describes the current persistence capabilities. External cloud providers are expected to register adapters rather than modify Core.

Collaboration in this release is intentionally a foundation: presence, comments and same-browser coordination are implemented. It is not a CRDT or Google-Docs-style concurrent document merge engine.

## Release invariants

- One `cresco-session/v1` document model remains authoritative.
- AI/import never bypass server sanitization/validation.
- Optional runtime modules may degrade without blocking core startup.
- Studio source/build runtime files must remain byte-identical.
- New browser runtime assets are part of the release allowlist and build ownership manifest.
- Architecture remains quarantined until real browser testing is completed.

## Verification

Static checks can verify PHP/JavaScript syntax, source/build parity and contract tokens. Full release verification still requires a real WordPress environment for save/reload, browser interaction, accessibility, drag/drop, performance, exact ZIP installation and historical upgrade testing.
