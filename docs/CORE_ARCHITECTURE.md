# Cresco Core Architecture

Cresco Canvas uses a modular-monolith, contract-first architecture. The goal is to let Page Builder, Theme Builder, Loop, Components, Forms, WooCommerce, AI, Import/Export, and future modules share one document model and one mutation/render pipeline instead of creating parallel builders.

## Stable layers

1. **Contracts** define portable document, scope, command, patch, interchange, and AI envelopes.
2. **Core** owns Document, Scope, Context, Command/Patch, Widget Registry, UI Registry, dependency policy, and migrations.
3. **Application** orchestrates editor requests such as scoped export, command preview, render preview, save, history, and component workflows.
4. **Rendering** is the single HTML/CSS boundary. `RenderEngine` delegates HTML to `WebsiteRenderer` and structured CSS to the authoritative `WebsiteBuilderCssCompiler`.
5. **Modules** such as Theme, Loop, Forms, WooCommerce, Components, and AI register capabilities rather than modifying Core contracts.
6. **WordPress infrastructure** remains responsible for storage, REST, media, users/capabilities, WP_Query, WooCommerce, and ACF integration.
7. **Editor presentation** is a client of Application/Core. It must not mutate persisted Sessions directly outside validated commands or the existing compatibility save path.

Dependency direction is Contracts -> Core -> Application -> Modules/Infrastructure/Presentation. Core must not depend on editor DOM, AI providers, WooCommerce, or a specific WordPress screen.

## One document model

Existing storage remains `cresco-session/v1` for backward compatibility. `cresco-document/v1` is a stable envelope that adds `documentType` without forcing a destructive migration. Supported types include Page, Header, Footer, Single, Archive, Search, 404, Loop Item, Component, Woo Single, Woo Archive, and Popup.

The same Session node tree is therefore rendered regardless of where the document is used.

## One mutation path

All future editor mutations should resolve to `cresco-command/v1` and then `cresco-patch/v1`:

`UI / AI / Import / Clipboard / Component -> CommandBus -> PatchValidator -> candidate Session -> Diff -> transaction -> save`

The command bus never persists data. This keeps Preview Diff, scope enforcement, history, undo/redo, and collision-safe IDs consistent.

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
- Canvas: visual result and selection surface.
- Inspector: Content, Layout, Style, Advanced.
- Status Bar: breadcrumb, selection, document type, diagnostics/command access.

Feature modules extend the UI through the registry contract (`activity.register`, `panel.register`, `inspector.registerSection`, `contextMenu.register`, `command.register`, `diagnostics.register`) rather than appending arbitrary UI to the shell.

The checked-in architecture runtime exposes `window.crescoBuilderArchitecture` with a browser registry, command palette (`Ctrl/Cmd+K`), scoped-AI dialog, status/breadcrumb layer, authoritative renderer preview, and compatibility bridge hooks. The current Website Builder runtime remains the presentation adapter during consolidation.

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

Compatibility adapters may translate old handles, payloads, events, routes, or stored values, but they must not become the permanent authority for editor configuration, runtime policy, persistence, or rendering.

## Release gates

`npm run check:architecture` verifies contract JSON, PHP/JS syntax, source/build equality, service registration, release-package ownership, UI registry tokens, scoped AI contracts, command bus contracts, and documentation. `npm run check:runtime-modules` verifies the consolidated runtime module contract, stable feature routes, Architecture observer guard, and dependency direction between Workflow and transitional V3 presentation code. Hosted browser/accessibility/performance/upgrade evidence remains required before declaring a stable commercial release.

## WordPress persistence port

Core application code reads/writes visual documents through `DocumentRepository`. `WordPressDocumentRepository` is the current adapter for post meta and builder-version metadata. This prevents future cloud storage, collaboration, revision, or external document services from leaking WordPress persistence calls across the editor/application layer.

## Document Manager

The architecture endpoint exposes a normalized document index for builder-owned Pages and Theme Templates. The editor command palette opens **Cresco Documents**, where Page/Header/Footer/Single/Archive documents are treated as one family and route back into the appropriate compatibility editor. As Loop Item and other document types graduate to Session-native storage, they can join this index without introducing another builder shell.
