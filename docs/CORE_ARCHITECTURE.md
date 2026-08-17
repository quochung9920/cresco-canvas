# Kiến trúc Core của Cresco Canvas

Cresco Canvas dùng kiến trúc **modular monolith, contract-first**. Mục tiêu là để Page Builder, Theme Builder, Loop, Components, Forms, WooCommerce, AI, Import/Export và các module tương lai dùng chung một document model và một mutation/render pipeline thay vì phát triển thành nhiều builder song song.

## Các tầng ổn định

1. **Contracts** định nghĩa document, scope, command, transaction, patch, interchange và AI envelope có tính portable.
2. **Core** sở hữu Document, Scope, Context, Command/Patch, responsive inheritance, design-token analysis, Widget Registry, Inspector/UI Registry, dependency policy và migration.
3. **Application** điều phối scoped export, transaction preview/commit, render preview, save, history và component workflow.
4. **Rendering** là ranh giới HTML/CSS duy nhất. `RenderEngine` dùng `WebsiteRendererV2`, `WebsiteBuilderCssCompiler`, `WidgetPartStyleCompiler` và `ComponentStyleCompiler` trên cùng normalized Session/Architecture snapshot.
5. **Modules** như Theme, Loop, Forms, WooCommerce, Components và AI đăng ký capability thay vì sửa Core contract.
6. **WordPress infrastructure** phụ trách storage, REST, media, users/capabilities, WP_Query, WooCommerce và ACF integration.
7. **Editor presentation** là client của Application/Core; không mutate persisted Session trực tiếp ngoài validated command/transaction hoặc compatibility save path được phê duyệt.

Hướng dependency:

```text
Contracts -> Core -> Application -> Modules / Infrastructure / Presentation
```

Core không phụ thuộc editor DOM, AI provider, WooCommerce hoặc một WordPress screen cụ thể.

## Core Platform v2

`WebsiteBuilderCorePlatform` là consolidation boundary hiện tại. Đây **không phải builder mới**. Nó làm cho một tập Core service hiện có trở thành authoritative trong khi legacy service tiếp tục tồn tại dưới dạng compatibility adapter.

Với Page frontend, Core Platform v2 loại competing Cresco render/CSS callback cũ trước khi render và trở thành owner Cresco Page frontend duy nhất. Output cuối đi qua `RenderEngine/v2` và style contract authoritative hiện hành.

Core Platform manifest công bố:

- widget catalog canonical và Inspector contract theo schema;
- `cresco-responsive/v2` dùng chung cho root/part compiler;
- `cresco-design-system/v2` và token usage count;
- Widget Architecture v2 capability như parts, bindings, nested component slot, Loop/Form engine;
- transaction preview/commit với optimistic checksum concurrency;
- privacy-safe system status và document budget;
- editor facade có node-id index O(1) và shared responsive style resolver.

Compatibility class có thể còn tồn tại trong migration nhưng không được sở hữu Page frontend pipeline thứ hai.

## Một document model

Storage hiện tại vẫn dùng `cresco-session/v1` để giữ backward compatibility. `cresco-document/v1` là envelope ổn định thêm `documentType` mà không ép destructive migration.

Các document type có thể gồm Page, Header, Footer, Single, Archive, Search, 404, Loop Item, Component, Woo Single, Woo Archive và Popup.

Cùng một Session node tree được render dù document được dùng ở đâu. Widget Architecture v2 là sidecar nên document `cresco-session/v1` cũ không cần destructive schema migration.

## Một mutation path

Mutation editor/AI nên quy về `cresco-command/v1`; nhiều thay đổi cùng một intent dùng `cresco-transaction/v1`:

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

Command bus và transaction preview chạy in-memory. Persistence thuộc `DocumentRepository`; WordPress adapter ghi slash-safe JSON và verify persisted checksum.

Transaction commit có thể nhận `ifMatch` checksum và phải reject stale write thay vì silent overwrite.

## Responsive contract

`ResponsiveResolver` là breakpoint/inheritance authority duy nhất của Core Platform v2.

```text
wide base -> desktop -> laptop -> tablet -> mobile
```

Viewport nhỏ nhận override bucket lớn hơn theo thứ tự rồi nhận bucket cụ thể hơn. `WebsiteBuilderCssCompiler` và `WidgetPartStyleCompiler` phải dùng cùng resolver.

Manifest cũng publish preview width và effective-style cascade cho Studio/AI client.

## Design System và Inspector

`DesignSystemAnalyzer` publish token catalog hiện tại và đếm token reference trong Session/Architecture sidecar. Điều này hỗ trợ usage indicator, safe token replacement, cleanup, contrast/preset workflow mà không scan HTML tùy tiện.

`InspectorSchema` lấy tab, control, Part, State và style capability từ `WidgetCatalog::all()`.

Nguyên tắc: control nhìn thấy trên UI phải tồn tại vì widget contract khai báo nó, và render/validation code phải tiêu thụ cùng contract đó.

Shared controls hiện tại có thể bao gồm responsive property UI, dimension/unit, Border/Radius, state tabs và Typography popup. Các lớp presentation này chỉ tổ chức/proxy canonical controls; không được tạo model/state thứ hai.

## Rendering canonical và WYSIWYG

```text
Session + Architecture v2
  -> RenderEngine/v2
  -> WebsiteRendererV2
  -> root styles + Part styles + Component styles
  -> HTML/CSS
```

Studio canonical preview phải dùng cùng normalized state với application/frontend. Legacy React canvas nếu còn dùng trong migration chỉ là interaction/state adapter, không được trở thành visual authority khác với canonical renderer.

Frontend Page CSS phải compile từ cùng saved Session/Architecture snapshot.

## Scoped AI

`ScopeEngine` công bố bốn public scope ổn định:

- `widget`: đúng một widget; chỉ props/style/responsive/scoped Custom CSS.
- `subtree`: target container/section và descendants.
- `selection`: các node được chọn với ancestry context tối thiểu.
- `document`: toàn bộ document.

`ContextEngine` phát `cresco-ai-context/v2`. Optimized mode chỉ chứa widget contract, token dependency, media descriptor, ancestry và selected content cần thiết. Full mode dành cho create/redesign document.

AI nên trả `cresco-patch/v1`; server reject operation vượt exported boundary.

## Studio UX shell

Các vùng dài hạn:

- **Top Bar:** document actions như undo/redo, viewport, zoom, preview, save/publish.
- **Activity Rail:** Add, Inspector/Edit, Components, Global/Site, Page, Data, AI, Theme, Team, History tùy runtime hiện hành.
- **Context Panel:** chọn/navigate resource, không sở hữu node styling.
- **Canvas:** visual result và selection surface.
- **Inspector:** Content, Layout, Style, Advanced.
- **Structure:** Session tree/navigator canonical cho node management.

Feature module nên extend qua registry/SDK thay vì append UI tùy tiện vào shell.

`window.CrescoStudioSDK` cung cấp command, panel, Inspector section, context action và document adapter registration.

## Runtime consolidation boundary

Website Builder browser startup có một policy surface phía server:

- `WebsiteBuilderRuntimeContext`: resolve/authorize editor request và isolation flag.
- `WebsiteBuilderEditorConfig`: shared endpoint/config contract.
- `WebsiteBuilderAsset`: content-addressed asset path/version/report.
- `WebsiteBuilderModuleRegistry`: required/optional browser module catalog.
- `WebsiteBuilderBootstrapResilience`: startup request middleware/state và observer boot guard.
- `WebsiteBuilderRuntimeGuard`: final module policy và fatal startup recovery owner.
- `WebsiteBuilderDiagnostics`: diagnostics dựa trên cùng registry/config contract.

Optional runtime dùng `MutationObserver` phải coalesce work, tránh no-op mutation, có teardown/guard và có diagnostic evidence.

Optional module lỗi phải degrade feature, không ngăn Session load hoặc Studio mount.

## Compatibility policy

Professional UX V2 và Comprehensive V3 là compatibility adapter trong quá trình migration. Feature mới không được tạo `V4`, `V5` hoặc builder layer độc lập khác.

Versioning thuộc contract/migration, không thuộc service name.

Compatibility adapter được phép translate handle, payload, event, route hoặc stored value cũ nhưng không trở thành owner lâu dài của editor config, runtime policy, persistence hoặc rendering.

## Release gate

`npm run check:architecture` kiểm tra contract, syntax, source/build equality, Core Platform registration, responsive resolver, Inspector/Design System contract, transaction/persistence boundary, unified rendering, release ownership và scoped AI.

`npm run check:runtime-modules` kiểm tra runtime module contract và dependency direction.

Source check không phải release evidence. Clean build, exact-ZIP install, compatibility matrix, browser, accessibility, performance, security/integration và upgrade/rollback vẫn cần bằng chứng riêng trước stable commercial release.

## WordPress persistence port

Core/Application đọc/ghi visual document qua `DocumentRepository`. `WordPressDocumentRepository` là adapter hiện tại cho post meta và builder metadata.

Mục tiêu của port này là ngăn WordPress-specific persistence leak qua editor/application layer và giữ đường mở cho cloud storage, collaboration hoặc external document service trong tương lai.

## Document Manager

Architecture endpoint có thể cung cấp document index normalized cho builder-owned Page/Theme document. Page/Header/Footer/Single/Archive là cùng một document family và route về editor phù hợp.

Khi Loop Item và document type khác dùng Session-native storage đầy đủ, chúng có thể gia nhập index mà không cần builder shell mới.
