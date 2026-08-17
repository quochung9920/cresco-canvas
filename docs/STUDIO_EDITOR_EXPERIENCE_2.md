# Trải nghiệm Cresco Studio Editor 2.0

Baseline gốc: `c77fbd0eb166f9cfb9d9bd202ec4e6464cd5511b`

Studio 2.0 giữ `cresco-session/v1` và các contract storage/render hiện có, đồng thời thay active Website Builder browser shell bằng một Session-native Studio runtime thống nhất. Nó không tạo thêm một builder generation được đánh số.

> **Authority kiến trúc:** File này mô tả feature/UX của Studio 2.0. Với ownership runtime, React DOM, CSS cascade, Page Settings schema/UI parity, branch synchronization, source/build parity và troubleshooting hiện tại, dùng `docs/STUDIO_RUNTIME_OWNERSHIP_AND_CONFLICT_PREVENTION.md`.

## P0 — Runtime, kiến trúc và diagnostics

- `WebsiteBuilderStudio` sở hữu core browser script qua handle `cresco-canvas-website-builder` để giữ dependency compatibility.
- Studio dùng `WebsiteBuilderRuntimeContext`, `WebsiteBuilderEditorConfig`, `WebsiteBuilderAsset` và `WebsiteBuilderModuleRegistry`.
- Critical Session load đi trước; context/settings/platform request phụ dùng bounded async request.
- Runtime diagnostics ghi request duration, editor event, event-loop stall, heartbeat và recovery state.
- Local crash recovery và dirty-document protection nằm trong Studio.
- Architecture/compatibility module có thể bị quarantine theo policy runtime.
- Tools -> Cresco Diagnostics là troubleshooting surface độc lập.

## P1 — Trải nghiệm editor

### Structure Navigator 2.0

Structure là Session tree thật, không phải visual list phẳng. Nó hỗ trợ expand/collapse, search có ancestor reveal, keyboard navigation, inline rename, multi-select, lock/visibility indicator, quick action, context menu và validated drag/drop trước/trong/sau. Closed container có thể auto-expand khi drag hover.

### Widget Controls 2.0

Inspector lấy schema từ `WidgetCatalog`. Content, Layout, Style, Advanced, spacing, dimension/unit, responsive override, state override, token và scoped Custom CSS dùng cùng interaction model.

Multi-selection hỗ trợ bulk style; content editing vẫn ưu tiên single-selection.

Các enhancement hiện tại như Dimension/Border controls, responsive property grouping, Widget State Tabs và Typography popup phải proxy canonical control/Session state thay vì giữ state cạnh tranh.

### Typography popup

Typography được trình bày như một popup/popover nhẹ từ nhóm Style. Popup chỉ thay đổi presentation của các control canonical như Family, Size, Weight, Transform, Style, Decoration, Line Height, Letter Spacing, Text Color và Alignment.

Responsive selector, unit, reset, state và persistence vẫn đi qua Inspector/Session owner hiện tại. Popup không được reimplement một typography model riêng.

### Page Settings 2.0

Page rail expose backend `PageSettings` model hiện có: shell/layout, title/header/footer, classic/gradient background, background media, responsive body spacing, scroll snap và page-scoped Custom CSS.

Page panel chỉ là **view của canonical `PageSettings` backend model**. UI khác có cùng tên không tự động thay thế panel này.

Persisted Page Settings control mới phải đồng thời cập nhật backend schema, defaults, sanitizer, compiler, các editing surface liên quan và test.

### Responsive 2.0

Wide/Desktop/Laptop/Tablet/Mobile cùng dùng một Session. Widget style inherit từ base qua breakpoint override. Property hoặc breakpoint override có thể reset; previous breakpoint có thể copy forward; visibility có thể thay đổi theo breakpoint.

## P2 — Professional workflow

- **Reusable Components:** tạo từ selection, insert, sync instance trong document, detach/delete theo capability hiện hành.
- **Clipboard:** copy/paste node với recursive ID remap, copy/paste style, paste before/inside/after.
- **Dynamic Data:** Dynamic Field, Loop Grid và Woo product widget dùng widget contract hiện có.
- **Command Palette:** `Ctrl/Cmd+K` cho navigation, save/history, device và insert command; extension command có thể register.
- **History & Recovery:** undo/redo local, server revisions, autosave, dirty guard, crash recovery và same-browser warning.
- **Canvas:** selection đồng bộ, quick action, responsive preview và context menu.

## P3 — AI, Theme, Loop và WooCommerce

AI dùng interchange boundary hiện có. Scope có thể gồm `widget`, `subtree`, `selection`, compatibility `selection-subtrees` hoặc toàn document khi resolver hỗ trợ.

`cresco-interchange/v1` package được xử lý bên ngoài; imported Session/interchange phải validate/preview trước Apply. Apply không tự Save.

Theme Builder dùng shared domain service; Studio cung cấp cùng shell cho Theme-document route từ canonical editor config.

Loop Grid và WooCommerce widget dùng cùng Inspector/responsive/style contract, không tạo presentation system riêng.

## P4 — Ecosystem foundation

`window.CrescoStudioSDK` cung cấp registration point cho command, panel, Inspector section, context action và document adapter.

`WebsiteBuilderPlatform` có provider-neutral extension manifest, document adapter registry, lightweight presence và comments.

Collaboration ở mốc này chỉ là foundation; không phải CRDT hoặc Google-Docs-style concurrent merge engine.

## Release invariant

- Một `cresco-session/v1` document model authoritative.
- AI/import không bypass server validation/sanitization.
- Optional module có thể degrade mà không block core startup.
- Source/build runtime mirror phải đồng bộ theo contract.
- Browser runtime asset mới phải thuộc release allowlist/build ownership.
- Architecture/runtime claim chỉ được nâng mức khi có browser/release evidence tương ứng.

## Verification

Static check có thể chứng minh syntax, source/build parity và contract token. Release đầy đủ vẫn cần WordPress thực để verify save/reload, browser interaction, accessibility, drag/drop, performance, exact-ZIP install và historical upgrade.
