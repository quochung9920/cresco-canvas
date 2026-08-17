# Website Builder Comprehensive V3

Comprehensive V3 là additive professional workflow layer cho `website-core/v1`. Nó **không đổi schema `cresco-session/v1`** và không phải builder generation mới.

## Phase 1 — Rendering parity

- Frontend document style được normalize theo authoritative `WebsiteBuilderCssCompiler` range model.
- Editor virtual breakpoint giữ structured style contract; Pixel 100% có thể dùng cho direct frontend comparison khi runtime hỗ trợ.
- Scoped Custom CSS có unsaved live preview cho selected widget trong editor.
- Native Form rendering vẫn authoritative trên frontend; editor Form controls được normalize để gần visual parity hơn.

## Phase 2 — Portable interchange và AI

`cresco-interchange/v1` package có thể represent entire Page, selected subtree/section, one widget hoặc multi-widget selection.

Export package chứa source checksum, dependency, optimized Design System data và scoped AI context theo contract.

Import non-destructive cho tới khi Preview Diff thành công. Insert/replace dùng existing `cresco-patch/v1`, `PatchValidator`, `PatchApplier`, `IdRemapper`. Preview trả candidate Session, structural diff, ID remap và dependency warning; editor stage candidate qua Validate -> Apply để giữ Undo/History.

Media reference là descriptor, không auto-download. Import UI có thể map Global Design token/media descriptor trước server validation.

## Phase 3 — Builder systems

- Professional Canvas/Inspector tiếp tục là primary visual editing surface; V3 gắn portability/accessibility/production tool vào surface này, không tạo editor thứ hai.
- Reusable component linked instance có thể sync explicit từ published source với collision-safe descendant IDs.
- Loop Grid tiếp tục dùng bounded query control. Reusable Component và section/widget interchange là portable authoring path cho repeated layout.
- Theme Builder template có Session-native bridge khi runtime hiện hành hỗ trợ. Display condition/template resolution vẫn có owner canonical.
- Theme Session template phải dùng cùng authoritative structured style compile path như Page.

## Phase 4 — Commerce và production hardening

- WooCommerce capability detection theo integration contract hiện hành.
- Single Product Template workflow có thể reuse/create Theme Template Session-native với Product Image/Title/Price/Add to Cart và condition phù hợp.
- Production panel có thể report node count, nesting depth, Custom CSS volume, Forms, Loops, Woo usage.
- Canvas accessibility scan có thể phát hiện missing alt, empty/hash link, heading jump, multiple visible H1. Đây là authoring aid, không thay release accessibility gate.
- License/update/migration, ZIP install, browser E2E, accessibility, performance vẫn là release authority.

## Release gate

`npm run check:comprehensive-v3` verify runtime syntax, source/build equality, registration, interchange contract, dependency mapping, Theme Session bridge, Woo workflow, accessibility/performance tool token, manifest ownership và release allowlist.

Plugin vẫn là `1.0.0-rc.1`. V3 có thể bổ sung commercial-grade workflow nhưng **không tự chứng nhận stable release** nếu thiếu hosted/browser/accessibility/release evidence.
