# Cresco Core Architecture

Cresco Canvas uses a modular-monolith, contract-first architecture. The goal is to let Page Builder, Theme Builder, Loop, Components, Forms, WooCommerce, AI, Import/Export, and future modules share one document model and one mutation/render pipeline instead of creating parallel builders.

## Stable layers

1. **Contracts** define portable document, scope, command, transaction, patch, interchange, and AI envelopes.
2. **Core** owns Document, Scope, Context, Command/Patch, responsive inheritance, design-token analysis, Widget Registry, Inspector/UI Registry, dependency policy, and migrations.
3. **Application** orchestrates editor requests such as scoped export, transaction preview/commit, render preview, save, history, and component workflows.
4. **Rendering** is the single HTML/CSS boundary. `RenderEngine` uses `WebsiteRendererV2`, `WebsiteBuilderCssCompiler`, `WidgetPartStyleCompiler`, and `ComponentStyleCompiler` from the same normalized Session/Architecture snapshot.
5. **Modules** such as Theme, Loop, Forms, WooCommerce, Components, and AI register capabilities rather than modifying Core contracts.
6. **WordPress infrastructure** remains responsible for storage, REST, media, users/capabilities, WP_Query, WooCommerce, and ACF integration.
7. **Editor presentation** is a client of Application/Core. It must not mutate persisted Sessions directly outside validated commands/transactions or the compatibility save path while migration is in progress.

Dependency direction is Contracts -> Core -> Application -> Modules/Infrastructure/Presentation. Core must not depend on editor DOM, AI providers, WooCommerce, or a specific WordPress screen.

## Core Platform v2 consolidation

`WebsiteBuilderCorePlatform` is the current consolidation boundary. It is deliberately **not** a new parallel builder. It makes one set of existing Core services authoritative while legacy services remain available as compatibility adapters.

For Page frontend requests, Core Platform v2 removes the old competing Cresco render/CSS callbacks before rendering and becomes the only Cresco Page frontend owner. The final output is produced by `RenderEngine/v2` and carries style contract `authoritative-v5`. Theme, REST, storage and editor compatibility functions remain registered independently where they still own behavior.

The Core Platform manifest exposes:

- the canonical widget catalog and schema-driven Inspector contract;
- `cresco-responsive/v2`, shared by root and part-style compilers;
- `cresco-design-system/v2` plus token-usage counts;
- Widget Architecture v2 capabilities (parts, bindings, nested component slots, Loop/Form engines);
- transaction preview/commit endpoints with optimistic checksum concurrency;
- a privacy-safe system-status endpoint and document budgets;
- an editor facade with an O(1) node-id index and shared responsive style resolver.

Compatibility classes may still exist during migration, but they must not own a second Page frontend pipeline.

## One document model

Existing storage remains `cresco-session/v1` for backward compatibility. `cresco-document/v1` is a stable envelope that adds `documentType` without forcing a destructive migration. Supported types include Page, Header, Footer, Single, Archive, Search, 404, Loop Item, Component, Woo Single, Woo Archive, and Popup.

The same Session node tree is therefore rendered regardless of where the document is used. Widget Architecture v2 remains a sidecar so existing `cresco-session/v1` documents do not require destructive schema migration.

## One mutation path

All editor/AI mutations should resolve to `cresco-command/v1` and, when several changes belong together, `cresco-transaction/v1`:

`UI / AI / Import / Clipboard / Component -> CommandBus -> PatchValidator -> candidate Session -> Diff -> TransactionManager -> verified repository save -> History`

The command bus and transaction preview are pure in-memory operations. Persistence is owned by `DocumentRepository`; the WordPress adapter writes slash-safe JSON and verifies the persisted checksum. Transaction commit accepts an optional `ifMatch` checksum and rejects stale writes with a conflict instead of silently overwriting newer work.

## Responsive contract

`ResponsiveResolver` is the single breakpoint/inheritance authority for Core Platform v2. The default model is desktop-first downward inheritance:

`wide base -> desktop -> laptop -> tablet -> mobile`

A smaller viewport receives all larger override buckets in source order, followed by its more specific bucket. `WebsiteBuilderCssCompiler` and `WidgetPartStyleCompiler` both use this resolver, eliminating separate breakpoint calculations. The manifest also publishes preview widths and effective-style cascade information to Studio and AI clients.

## Design System and Inspector

`DesignSystemAnalyzer` publishes the current token catalog and counts token references across the Session and Architecture sidecar. This gives future Inspector tooling enough information for usage indicators, safe token replacement, cleanup, and contrast/preset workflows without scanning arbitrary HTML.

`InspectorSchema` derives its widget tabs, controls, Parts, States and style-property capabilities from `WidgetCatalog::all()`. The goal is to remove widget-specific Inspector duplication: a visible control must exist because the widget contract declares it, and render/validation code must consume that same contract.

## Canonical rendering and WYSIWYG

The canonical render path is:

`Session + Architecture v2 -> RenderEngine/v2 -> WebsiteRendererV2 + root styles + Part styles + Component styles -> HTML/CSS`

The Studio canonical iframe consumes the same `/website-builder/render/{postId}` endpoint used by application tooling. The legacy React canvas remains mounted only as an interaction/state adapter during migration (selection, drag/drop, undo/redo and Inspector ownership); it is not the visual authority when the canonical renderer is available.

Frontend Page output is bound to CSS compiled from the same saved Session/Architecture snapshot. Form placeholders are repaired at the render boundary and component-backed nested/loop templates include their component CSS in the same render result.

## Scoped AI

`ScopeEngine` exposes four stable public scopes:

- `widget`: exactly one widget; only props/style/responsive/scoped Custom CSS may change.
- `subtree`: one section/container plus all descendants.
- `selection`: one or more explicitly selected nodes with minimal ancestry context.
- `document`: the complete current document.

`ContextEngine` emits `cresco-ai-context/v2`. Optimized mode includes only required widget contracts, token dependencies, media descriptors, ancestry, and the selected content. Full mode is reserved for create/redesign-document workflows. AI should return `cresco-patch/v1`; the server rejects operations outside the exported boundary.

## Editor UX shell

The long-lived shell contains six stable zones:

- Top Bar: document actions only (undo/redo, viewport, zoom, preview, save/publish).
- Activity Rail: Add, Navigator, Components, Data, Site, AI, Settings.
- Context Panel: resource selection/navigation, never node styling.
- Canvas: canonical visual result and selection surface.
- Inspector: Content, Layout, Style, Advanced.
- Status Bar: breadcrumb, selection, document type, diagnostics/command access.

Feature modules extend the UI through the registry contract (`activity.register`, `panel.register`, `inspector.registerSection`, `contextMenu.register`, `command.register`, `diagnostics.register`) rather than appending arbitrary UI to the shell.

The checked-in architecture runtime exposes `window.crescoBuilderArchitecture` with a browser registry, command palette (`Ctrl/Cmd+K`), scoped-AI dialog, status/breadcrumb layer, authoritative renderer preview, and compatibility bridge hooks. Core Platform v2 additionally exposes `window.crescoBuilderCoreV2` for the manifest, node index, effective responsive style, and transaction helpers.

## Runtime consolidation boundary

Website Builder browser startup has one server-side policy surface:

- `WebsiteBuilderRuntimeContext` resolves and authorizes Page/Theme editor requests and diagnostics/isolation flags.
- `WebsiteBuilderEditorConfig` owns the shared editor endpoint/configuration contract.
- `WebsiteBuilderAsset` owns content-addressed asset paths, versions, and reports.
- `WebsiteBuilderModuleRegistry` is the authoritative catalog for required and optional browser modules.
- `WebsiteBuilderBootstrapResilience` owns startup request middleware/state publication and observer boot guards.
- `WebsiteBuilderRuntimeGuard` owns final module policy and the only user-facing fatal startup recovery panel.
- `WebsiteBuilderDiagnostics` consumes the same registry/config contracts and remains usable outside a frozen editor tab.

Required modules must never depend on optional presentation modules. Normal mode may quarantine an unstable optional module without blocking the core editor. Diagnostic isolation modes must be derived from the same module registry rather than hard-coded in a second system.

Every optional runtime using `MutationObserver` must coalesce work, avoid no-op writes, provide teardown/guard behavior, and expose diagnostic evidence. An optional module failure must degrade the feature, not prevent Session loading or core editor mount.

## Compatibility policy

Professional UX V2 and Comprehensive V3 remain compatibility adapters while their capabilities are migrated behind Core APIs. New features must not create `V4`, `V5`, or another standalone builder layer. Versioning belongs in contracts and migrations, not service names.

Compatibility adapters may translate old handles, payloads, events, routes, or stored values, but they must not become the permanent authority for editor configuration, runtime policy, persistence, or rendering. Core Platform v2 explicitly centralizes Page frontend ownership while those adapters are retired incrementally.

## Release gates

`npm run check:architecture` verifies contract JSON, PHP/JS syntax, source/build equality, Core Platform registration, shared responsive resolution, schema Inspector/Design System contracts, transaction/persistence boundaries, unified V2 rendering, release-package ownership, scoped AI contracts, and documentation. `npm run check:runtime-modules` verifies the consolidated runtime module contract and dependency direction.

Source checks are not release evidence. Hosted clean-build, exact-ZIP install, compatibility matrix, browser, accessibility, performance, security/integration and upgrade/rollback evidence remains required before declaring a stable commercial release.

## WordPress persistence port

Core application code reads/writes visual documents through `DocumentRepository`. `WordPressDocumentRepository` is the current adapter for post meta and builder-version metadata. It stores WordPress-safe escaped JSON and verifies the persisted checksum before a save is reported successful. This prevents future cloud storage, collaboration, revision, or external document services from leaking WordPress persistence calls across the editor/application layer.

## Document Manager

The architecture endpoint exposes a normalized document index for builder-owned Pages and Theme Templates. The editor command palette opens **Cresco Documents**, where Page/Header/Footer/Single/Archive documents are treated as one family and route back into the appropriate compatibility editor. As Loop Item and other document types graduate to Session-native storage, they can join this index without introducing another builder shell.
