# Cresco Canvas — Báo cáo hệ thống, kiến trúc và mô hình kỹ thuật

> **Trạng thái tài liệu:** mô tả hệ thống tại `main` ở mốc `4cc0b954d3065ed8e500c54e3054bb6426f90fc2`, ngày 2026-08-15.  
> **Phiên bản plugin:** `1.0.0-rc.1`.  
> **Đối tượng đọc:** người mới chưa biết Cresco Canvas, product owner, designer, QA, developer WordPress/PHP/JavaScript và AI agent cần hiểu hệ thống đủ sâu để sửa hoặc mở rộng đúng chỗ.  
> **Mục tiêu:** đọc từ đầu đến cuối có thể hình dung được Cresco Canvas là gì, người dùng thao tác ở đâu, dữ liệu đi như thế nào, code được chia lớp ra sao, điều gì là nguồn sự thật, cách AI import/export hoạt động, các giới hạn bảo mật và cách phát triển an toàn.

---

## 1. Tóm tắt trong 3 phút

Cresco Canvas là một **visual website builder chạy bên trong WordPress**. Người dùng không cần viết HTML/CSS trực tiếp để dựng phần lớn giao diện. Họ chọn widget, kéo thả cấu trúc, chỉnh Content/Layout/Style/Advanced, đổi breakpoint, dùng Global Design, Page Settings, reusable components, dynamic data, forms, Theme Builder và AI Studio.

Điểm quan trọng nhất về kỹ thuật là: **Cresco không coi HTML trên màn hình là dữ liệu gốc**. Dữ liệu gốc là một tài liệu JSON có schema cố định tên `cresco-session/v1`. UI editor chỉ là một cách chỉnh tài liệu đó. Renderer đọc cùng tài liệu để tạo frontend. AI cũng không sửa DOM trực tiếp; AI chỉ nhận context và trả về `cresco-patch/v1` hoặc Session hợp lệ, sau đó server kiểm tra trước khi cho áp dụng.

Có thể hình dung hệ thống như sau:

```text
Người dùng / AI
      |
      v
Cresco Studio (UI editor)
      |
      v
cresco-session/v1  <---- nguồn dữ liệu trang
      |
      +----> Validator / Sanitizer
      |
      +----> Renderer ----> HTML + CSS ----> Preview / Frontend
      |
      +----> WordPress post meta ----> Save / Reload / Revision
```

Mô hình kỹ thuật lớn hơn của Cresco hướng tới chuỗi:

```text
Document
  -> Context
  -> Scope
  -> Command
  -> Transaction
  -> Storage
  -> Renderer
```

Nghĩa là một thay đổi đúng chuẩn phải biết **đang sửa document nào**, **ở ngữ cảnh nào**, **phạm vi nào được phép sửa**, **lệnh gì**, **transaction nào**, **lưu ở đâu** và cuối cùng **render ra sao**.

---

## 2. Cresco Canvas là gì và không phải là gì

### 2.1 Là gì

Cresco Canvas là plugin WordPress cung cấp một editor trực quan riêng cho Page, tập trung vào:

- xây layout responsive bằng Container/Flex/Grid;
- widget nội dung, media, interactive, site, dynamic, forms và WooCommerce;
- Global Design và Page Settings;
- Style/State/Responsive theo schema;
- reusable components;
- History/revisions, undo/redo, clipboard, Navigator;
- canonical preview gần realtime;
- AI context/import theo contract;
- Theme Builder và dynamic query được tích hợp cùng hệ sinh thái Cresco.

### 2.2 Không phải là gì

Cresco Canvas **không** dựa vào việc cho người dùng nhét tùy ý HTML/JavaScript vào document. Nó cũng không coi nội dung HTML hiện tại trong canvas là nguồn sự thật. Các node phải thuộc widget catalog đã đăng ký, props/style phải qua sanitizer, Custom CSS phải được scope, query phải bị giới hạn và AI output không được chạy như code.

Cresco cũng chưa nên được hiểu là một nền tảng collaboration kiểu Google Docs. Studio có foundation cho presence/comments/same-browser coordination, nhưng không phải CRDT multi-user merge engine hoàn chỉnh.

---

## 3. Thuật ngữ quan trọng

| Thuật ngữ | Nghĩa dễ hiểu | Nghĩa kỹ thuật |
|---|---|---|
| **Session** | Toàn bộ nội dung thiết kế của một page | JSON `cresco-session/v1` |
| **Node** | Một phần tử trên trang | Object có `id`, `type`, `props`, `style`, `responsive`, `states`, `customCSS`, `meta`, `children` |
| **Widget** | Loại phần tử có thể thêm vào trang | Schema trong `WidgetCatalog` |
| **Widget Contract** | Bộ luật của một widget | Props, style properties, responsive, states, selector parts, Custom CSS capability |
| **Studio** | Giao diện editor | Browser runtime dùng Session làm state |
| **Canonical Preview** | Preview dùng cùng logic render với frontend | Persistent iframe + local patch + background reconcile |
| **Global Design** | Hệ token/thiết lập chung của site | GlobalStyles + DesignTokens |
| **Page Settings** | Thiết lập riêng của page | Dữ liệu riêng, không nhét trực tiếp vào Session node tree |
| **Scope** | Phạm vi được phép thao tác | page/widget/subtree/selection/... tùy workflow |
| **Patch** | Danh sách thay đổi có cấu trúc | `cresco-patch/v1` |
| **Structured Style** | CSS property được khai báo chính thức trong widget | Style map đã allow-list |
| **Custom CSS** | Escape hatch cho CSS không có control native | CSS phải scoped bằng `&`, qua `ScopedCss` |
| **Stable ID** | ID bền của node | Dùng để selection, diff, patch, render selector và remap |
| **Runtime source** | Nguồn JS/CSS reviewed | `runtime-src/build/` đối với các runtime chuyển tiếp |

---

## 4. Trải nghiệm giao diện: người dùng thấy gì

Studio hiện là shell editor thống nhất cho Website Builder. Về mặt tư duy, UI có 5 vùng chính.

### 4.1 Thanh trên cùng

Thường chứa các hành động cấp tài liệu:

- Save/Update;
- undo/redo;
- breakpoint/device preview;
- history/revision;
- command palette;
- preview hoặc các trạng thái đồng bộ;
- các entry point liên quan Page Settings, AI hoặc tool phụ trợ tùy cấu hình.

Điểm cần nhớ: **Apply trong AI không đồng nghĩa Save WordPress**. Apply chỉ thay state tài liệu trong editor; người dùng vẫn phải Update/Save để persist.

### 4.2 Structure / Navigator

Navigator phản ánh **cây Session thật**, không phải danh sách DOM phẳng. Nó hỗ trợ các thao tác như:

- expand/collapse;
- search và reveal ancestor;
- keyboard navigation;
- rename label;
- select/multi-select;
- lock/visibility;
- quick action/context menu;
- drag before/inside/after với validation.

Cây này rất quan trọng vì `children` trong Session quyết định ownership và scope của subtree.

### 4.3 Canvas / Preview ở giữa

Canvas chính dùng **persistent canonical iframe**. Sau lần render bootstrap đầu tiên, thay đổi style/content có thể được patch ngay vào iframe để phản hồi nhanh; RenderEngine chạy reconcile nền để đưa HTML/CSS về canonical server output mà không reload toàn bộ iframe sau mỗi thao tác.

Do đó UI có hai nhịp:

1. **Local live patch** — phản hồi gần như ngay lập tức.
2. **Background canonical reconcile** — xác nhận lại bằng renderer chuẩn.

Nếu reconcile nền lỗi, preview hiện tại vẫn được giữ thay vì blank canvas; chỉ khi không bootstrap được render ban đầu thì editor mới cần trạng thái blocking/retry.

### 4.4 Inspector

Inspector là **schema-driven**. Nó không đoán control từ label UI; nó đọc contract do `WidgetCatalog` cung cấp.

Các nhóm tư duy chính:

- **Content** — text, URL, media, items, semantic props...
- **Layout** — display, flex/grid, size, alignment, spacing...
- **Style** — typography, color, background, border, effects...
- **Advanced** — position, visibility, states, responsive, tokens, Custom CSS...

Inspector phân biệt:

- base/global value;
- local value;
- breakpoint override;
- inherited value;
- state override;
- token reference.

Multi-selection có thể áp dụng style có cấu trúc cho nhiều node; content editing vẫn nên được hiểu là single-target để tránh cập nhật mơ hồ.

### 4.5 Panel chức năng cấp trang/hệ thống

Ngoài widget inspector, Studio còn cung cấp các domain lớn:

- Page Settings;
- Global Design;
- Components;
- Dynamic Data / Loop Grid;
- Forms;
- Theme Builder;
- AI Studio;
- History & Recovery;
- Commands / SDK extension panels tùy feature.

---

## 5. Mô hình dữ liệu cốt lõi: `cresco-session/v1`

### 5.1 Tư duy đơn giản

Một Page trong Cresco là **một cây node**. Mỗi node là một widget instance.

Ví dụ tối giản:

```json
{
  "schema": "cresco-session/v1",
  "version": 1,
  "documentId": "page-3",
  "nodes": [
    {
      "id": "container-hero",
      "type": "container",
      "props": {},
      "style": {},
      "responsive": {},
      "states": {},
      "customCSS": {},
      "meta": {},
      "children": []
    }
  ]
}
```

### 5.2 Ý nghĩa từng field của node

#### `id`

Stable ID duy nhất trong document. Nó được dùng cho:

- selection;
- Navigator;
- patch target;
- diff;
- CSS stable selector;
- ID remapping khi copy/insert/replace subtree;
- diagnostics.

Không nên dùng DOM index thay stable ID vì index thay đổi khi reorder.

#### `type`

Tên widget, ví dụ `container`, `heading`, `text`, `button`, `image`, `list`...

Chỉ type có trong `WidgetCatalog` mới hợp lệ trong Website Builder.

#### `props`

Dữ liệu mang nghĩa của widget. Ví dụ:

- Heading: text, level, url;
- Button: text, url, target, rel, icon;
- Image: url, alt, caption, link, objectFit, aspectRatio;
- List: items, ordered;
- Container: layout, direction, wrap, align, justify, gridTemplate, tag, ariaLabel.

`props` không phải nơi tùy ý nhét CSS.

#### `style`

Structured style ở base breakpoint. Chỉ property widget cho phép mới được chấp nhận.

Ví dụ:

```json
{
  "backgroundColor": "#0b4f93",
  "paddingTop": "32px",
  "borderRadius": "8px"
}
```

Nếu property không có trong allow-list của widget, validator sẽ báo lỗi kiểu “unsupported structured style property”.

#### `responsive`

Override style theo breakpoint, ví dụ laptop/tablet/mobile. Base style là nền thừa kế; breakpoint chỉ chứa phần khác biệt.

#### `states`

Style theo trạng thái, ví dụ hover/focus/active tùy widget contract.

Ví dụ Button có thể hỗ trợ `hover`, `focus`, `active`, trong khi widget đơn giản có ít state hơn.

#### `customCSS`

CSS riêng của widget, chia theo bucket như `base`, `desktop`, `laptop`, `tablet`, `mobile` tùy pipeline Website Builder.

Custom CSS là fallback, không phải con đường mặc định.

#### `meta`

Metadata editor như label Navigator, component ID, locked, hidden.

#### `children`

Danh sách node con. Chỉ widget `allowsChildren` mới nên chứa children.

---

## 6. Widget Catalog: “bảng tuần hoàn” của Cresco

File nguồn quan trọng nhất cho widget contract là:

```text
includes/Builder/WidgetCatalog.php
```

`WidgetCatalog::all()` là nguồn dữ liệu dùng chung cho REST, editor, AI và rendering. Đây là nguyên tắc kiến trúc rất quan trọng: **không duy trì bốn danh sách widget riêng cho UI, AI, renderer và validator**.

### 6.1 Các nhóm widget chính

#### Layout

- Container
- Columns

Container là phần tử nền tảng: Block/Flex/Grid, direction, wrap, align, justify, columns/grid template, semantic tag và aria label.

#### Content & Media

- Heading
- Text
- Button
- Image
- List
- Divider
- Spacer
- Icon
- Icon Box
- Video
- Gallery
- Testimonial
- Social Icons

#### Interactive

- Accordion
- Tabs
- Counter
- Progress

#### Site

- Site Logo
- Site Title
- Nav Menu
- Breadcrumbs

#### Dynamic

- Post Title
- Post Excerpt
- Featured Image
- Post Content
- Dynamic Field
- Loop Grid

#### Form / Commerce

- Form widgets tích hợp FormBuilder runtime;
- WooCommerce widgets khi WooCommerce khả dụng.

### 6.2 Một widget contract gồm những gì

Một contract về bản chất trả lời các câu hỏi:

1. Widget tên gì và thuộc category nào?
2. Có cho phép children không?
3. Props nào được phép?
4. Prop là string/enum/int/url/list hay kiểu gì?
5. Structured style property nào được phép?
6. Có responsive không?
7. Có state nào?
8. Có những stable selector part nào?
9. Custom CSS có được phép không?

Ví dụ List có `items`, `ordered`, và selector part cho `item`. Vì vậy AI có thể biết nên dùng:

```css
& > [data-cresco-part="item"] { ... }
```

thay vì đoán class HTML có thể thay đổi.

---

## 7. Mô hình styling

### 7.1 Thứ tự tư duy khi tạo giao diện

Cresco nên được author theo thứ tự:

```text
1. Widget props
2. Structured style
3. Responsive overrides
4. States
5. Custom CSS fallback
```

Nguyên tắc này đặc biệt quan trọng với AI. Nếu có native control thì dùng native control trước; chỉ dùng Custom CSS khi contract không biểu đạt được nhu cầu, ví dụ animation marquee phức tạp.

### 7.2 Global Design

Global Design chứa token/setting dùng chung toàn site. Mục tiêu là tránh hard-code cùng một màu, spacing hay typography hàng chục lần.

Token có thể được tham chiếu theo cú pháp semantic như:

```text
{colors.primary}
{spacing.xl}
{radius.md}
```

Renderer/style engine có trách nhiệm biến ý nghĩa semantic đó thành CSS thích hợp.

### 7.3 Local structured style

Local style chỉ nên chứa property widget cho phép. Điều này mang lại:

- validation rõ ràng;
- Inspector có thể dựng control chính xác;
- responsive/state dễ merge;
- AI ít hallucinate hơn;
- renderer dễ kiểm soát bảo mật.

### 7.4 Custom CSS hiện tại

Canonical implementation là:

```text
includes/Styles/ScopedCss.php
```

Custom CSS hiện hỗ trợ:

- arbitrary safe CSS declarations;
- ordinary scoped selectors bắt buộc chứa `&`;
- local `@keyframes` và `@-webkit-keyframes`;
- nested `@media`;
- `@supports`;
- `@container`;
- `@layer`;
- tự namespace tên keyframe theo widget để tránh collision.

Ví dụ hợp lệ:

```css
@keyframes marquee {
  from { transform: translate3d(0, 0, 0); }
  to   { transform: translate3d(-50%, 0, 0); }
}

& {
  animation: marquee 32s linear infinite;
}

&:hover {
  animation-play-state: paused;
}

@media (prefers-reduced-motion: reduce) {
  & { animation: none; }
}
```

Các đường escape nguy hiểm vẫn bị chặn, gồm `@import`, `@charset`, `@namespace`, `@document`, external `url()`, `javascript:`, `expression()`, style/script tag và selector cố thoát global scope như `html`, `body`, `:root`.

> **Lưu ý tài liệu legacy:** một số file cũ trong repo vẫn nói `@media`/`@keyframes` bị cấm. Đối với trạng thái hiện tại, `ScopedCss.php` và contract AI/patch mới là nguồn sự thật cao hơn cho chức năng này.

### 7.5 Website Builder sanitizer

`WebsiteBuilderSessionSanitizer` tách Custom CSS khỏi Session trước khi đi qua legacy structural sanitizer, chạy từng bucket bằng `ScopedCss::sanitize()`, sau đó restore CSS vào node theo stable ID. Cách này giúp giữ structural validation cũ nhưng thay parser CSS bằng canonical parser mới.

---

## 8. Responsive model

Cresco dùng một Session chung cho mọi breakpoint. Không có một document desktop và document mobile riêng.

Mô hình:

```text
Base style
  |
  +--> Laptop overrides
  +--> Tablet overrides
  +--> Mobile overrides
```

Studio hiện trình bày các context Wide/Desktop/Laptop/Tablet/Mobile ở UX, trong khi contract runtime Website Builder cho các override chính desktop/laptop/tablet/mobile. Việc hiển thị “wide” có thể là preview/architecture context, không nên tự thêm một key vào payload nếu contract cụ thể không cho phép.

Người dùng có thể:

- đổi preview device;
- chỉnh property riêng cho breakpoint;
- reset một property;
- reset cả breakpoint;
- copy giá trị từ breakpoint trước;
- điều khiển visibility theo breakpoint nếu contract hỗ trợ.

Điểm quan trọng cho developer: **responsive inheritance phải được xử lý ở resolver/style compiler, không nhân bản toàn bộ style object sang mỗi breakpoint**.

---

## 9. Page Settings và Global Design khác Session thế nào

### 9.1 Session

Session mô tả **nội dung/cây widget của page**.

### 9.2 Page Settings

Page Settings là cấu hình page-level, ví dụ:

- layout/shell;
- page title;
- header/footer;
- content root;
- body margin/padding responsive;
- classic/gradient background;
- background media;
- scroll snap;
- Page Custom CSS.

Nó được lưu qua service riêng và không nên nhét như một widget giả vào node tree.

### 9.3 Global Design

Global Design thuộc site-level và đòi quyền mạnh hơn như `edit_theme_options`. Nó cung cấp design tokens, global style/settings và import/reset workflow.

Cách phân tầng dễ nhớ:

```text
Global Design  -> toàn site
Page Settings  -> một page
Session        -> nội dung/widget của page
Widget Style   -> một node
```

---

## 10. Lưu trữ và vòng đời dữ liệu

### 10.1 WordPress storage

Session được lưu dưới dạng JSON trong post meta của Page. Legacy SessionManager dùng key `_cresco_canvas_document`; Website Builder tiếp tục giữ `cresco-session/v1` làm document contract và đánh dấu builder version bằng `_cresco_canvas_builder_version=website-core/v1` khi page đi qua Website Builder.

Reusable component được lưu dưới private post type `cresco_component` và có subtree JSON được sanitize trước khi lưu.

### 10.2 Save không phải mọi thao tác đều persist

Editor giữ state local trong khi chỉnh. Nhiều thao tác chỉ thay đổi state trong Studio:

- drag/drop;
- edit style;
- AI Apply;
- clipboard paste;
- undo/redo local.

Chỉ khi flow Save/Update chạy thì document mới được persist vào WordPress.

### 10.3 Revision và recovery

Hệ thống có các lớp:

- local undo/redo;
- server revisions/history;
- autosave/recovery;
- dirty-state protection;
- crash recovery;
- same-browser edit warning.

Những lớp này không đồng nghĩa với real-time multi-user merge.

### 10.4 Hai giới hạn Session đang cùng tồn tại

Repo hiện còn hai lớp Session sanitizer lịch sử:

- `SessionManager` legacy: giới hạn 500 nodes, depth 12, Custom CSS 12 KB;
- Website Builder: `MAX_NODES = 1000`, `MAX_DEPTH = 16`, `MAX_CUSTOM_CSS = 16000`.

Đối với Studio Website Builder hiện tại, **Website Builder boundary là boundary cần ưu tiên khi mô tả editor mới**. Legacy endpoint vẫn tồn tại vì backward compatibility, nên developer không được giả định hai path đã hợp nhất hoàn toàn.

---

## 11. Renderer và canonical preview

### 11.1 Một nguyên tắc cốt lõi

**Preview và frontend phải dùng cùng ý nghĩa document.** Nếu editor tự render một phiên bản khác logic frontend thì người dùng sẽ gặp “trong editor đúng, ngoài frontend sai”. Cresco giảm rủi ro này bằng canonical renderer.

### 11.2 Bootstrap

Khi Studio mở:

1. server đọc Session;
2. sanitize/canonicalize;
3. WebsiteRenderer tạo HTML/CSS;
4. kết quả bootstrap được đặt vào persistent iframe.

### 11.3 Local patch

Khi người dùng đổi style/content:

1. Studio cập nhật Session state;
2. local live patch chạy ngay;
3. CSS root/stable selector được compile trong browser;
4. iframe không reload.

### 11.4 Background reconcile

Sau đó RenderEngine gửi/nhận canonical output nền và cập nhật HTML/CSS cần thiết. Mục tiêu là:

- phản hồi nhanh như local editor;
- vẫn giữ canonical parity với server;
- không blank iframe;
- không spinner blocking sau mỗi edit.

### 11.5 Frontend

Frontend đọc document đã lưu và render bằng Website Builder renderer khi page đã được đánh dấu `website-core/v1`; legacy page chưa đi qua Website Builder vẫn có fallback renderer tương ứng.

---

## 12. Mô hình code tổng thể

### 12.1 Bootstrap plugin

Entry point:

```text
cresco-canvas.php
```

Nó làm các việc nền:

- kiểm tra WordPress/PHP requirement;
- định nghĩa constants;
- autoload namespace `CrescoCanvas\`;
- đăng ký activation/deactivation;
- tạo `CrescoCanvas\Plugin` khi `plugins_loaded`.

### 12.2 `Plugin.php` là composition root

`includes/Plugin.php` là nơi ghép các subsystem. Nó không nên chứa logic business chi tiết; nhiệm vụ là khởi tạo/register service theo feature flags.

Các nhóm service chính bao gồm:

- Lifecycle/Migration;
- Session/History;
- Global Styles/Design Tokens/Page Settings;
- Admin/Visual Editor/Studio integration;
- Website Builder runtime/platform/module registry;
- AI Interchange;
- Interactions;
- Theme Builder;
- Dynamic data/query;
- Forms;
- Template Library;
- Commercial services;
- Security hardening.

### 12.3 Core foundation

Thư mục `includes/Core/` biểu diễn nền kiến trúc tổng quát hơn:

- `Document`
- `ContextEngine`
- `ScopeEngine`
- `CommandBus`
- `TransactionManager`
- `ResponsiveResolver`
- `WidgetRegistry`
- `UiRegistry`
- `InspectorSchema`
- `ModuleRegistry`
- Document repository/diagnostics/analyzer.

Định hướng cốt lõi:

```text
Document -> Context -> Scope -> Command -> Transaction -> Storage -> Renderer
```

Website Builder hiện là product/integration layer phía trên foundation này; trong repo vẫn tồn tại các adapter và runtime lịch sử, vì vậy không nên hiểu rằng toàn bộ code cũ đã được rewrite thành Core abstraction.

### 12.4 Builder layer

`includes/Builder/` là vùng có mật độ logic lớn nhất của editor hiện tại. Các trách nhiệm tiêu biểu:

- WebsiteBuilder REST/persistence/frontend hooks;
- WidgetCatalog;
- Session sanitizer;
- WebsiteRenderer;
- canonical preview owner;
- editor config/runtime context;
- module registry;
- component sync;
- lifecycle/runtime guard/bootstrap resilience;
- CSS compiler/style output;
- Studio integration;
- diagnostics/parity/compatibility/concurrency.

### 12.5 AI layer

`includes/AI/` chứa:

- ContextBuilder v1/v2;
- ContractRegistry;
- ScopeResolver;
- VisualContext;
- DependencyResolver;
- OneShotPrompt;
- AIResultNormalizer;
- PatchValidator;
- PatchApplier;
- DiffEngine;
- IdRemapper;
- AIInterchange.

### 12.6 Các domain khác

- `includes/Dynamic/` — dynamic fields, query, facets, ACF/Woo integration;
- `includes/Forms/` — form schema/runtime/storage/submission/security;
- `includes/Interactions/` — browser interactions;
- `includes/Theme/` — Theme Builder/template routing;
- `includes/Styles/` — tokens/global styles/scoped CSS;
- `includes/Page/` — Page Settings;
- `includes/Session/` — legacy/canonical Session services;
- `includes/Security/` — request hardening/security policies;
- `includes/Lifecycle/` + `includes/Migration/` — activate/upgrade/downgrade/uninstall;
- `includes/Templates/` — template library;
- `includes/Commercial/` — commercial feature boundary.

---

## 13. Source tree phía browser

Repo có ba khái niệm dễ nhầm:

### 13.1 `src/`

Nguồn React/TypeScript/JavaScript cho các entry được build bình thường bằng webpack/wp-scripts, bao gồm Studio/editor/preview components và một số lớp lịch sử.

### 13.2 `runtime-src/build/`

Đây là **authoritative reviewed source** cho các JS/CSS/asset manifest runtime chưa thuộc webpack entry graph hiện tại.

Quy tắc:

- sửa file ở `runtime-src/build/`;
- không sửa mirror tương ứng trong `build/` bằng tay;
- `npm run build` copy reviewed runtime sang `build/`;
- `check:build-integrity` kiểm tra byte-for-byte parity và ownership;
- `runtime-src/` không đi vào production ZIP.

### 13.3 `build/`

Đây là output được ship trong plugin. Một phần do webpack sinh, một phần là mirror từ reviewed runtime source.

Mental model:

```text
src/ --------------------> webpack ------> build/
runtime-src/build/ -----> copy/parity ---> build/
```

Nếu sửa nhầm `build/*.js` trực tiếp, lần build sạch có thể ghi đè thay đổi và build-integrity sẽ coi đó là drift.

---

## 14. Command, transaction và thao tác editor

Không phải mọi thao tác UI nên mutate Session tùy tiện. Kiến trúc hướng tới việc action được biểu diễn thành command/transaction có thể:

- validate trước;
- ghi history;
- undo/redo;
- giới hạn scope;
- phát diagnostic/event;
- áp dụng atomically.

Ví dụ conceptual:

```text
User changes padding
    -> Inspector event
    -> command: set style on node X
    -> validate property against WidgetCatalog
    -> transaction updates document
    -> history records change
    -> local preview patch
    -> background canonical reconcile
```

Cách này tốt hơn việc component React tự sửa một object global vì domain rules có một điểm kiểm soát rõ ràng.

---

## 15. AI Studio: mô hình import/export hiện tại

### 15.1 Mục tiêu

AI không cần được quyền chạy code trong WordPress. Thay vào đó Cresco cung cấp một **data contract** để AI hiểu editor và trả về thay đổi có cấu trúc.

### 15.2 AI Context v1 và v2

V1 vẫn tồn tại để tương thích.

V2 (`cresco-ai-context/v2`) dành cho One-Shot authoring và tập trung vào việc cung cấp đủ thông tin để một prompt có xác suất thành công cao hơn.

V2 gồm các ý chính:

- `purpose`;
- `mode`;
- `scopePackage`;
- `authoringPolicy`;
- `returnContract`.

`scopePackage` có thể chứa:

- target;
- environment;
- content + ancestry;
- Design System available/used;
- Page Settings;
- `contracts.current`;
- `contracts.creationCatalog`;
- dependencies;
- capabilities;
- visual context.

### 15.3 Vì sao có `creationCatalog`

Nếu user chọn một Container rỗng, `current` contract chỉ nói về Container là chưa đủ. AI cần biết nó **được phép tạo** Button, List, Image, Text... và mỗi widget có props/style nào. `creationCatalog` giải quyết vấn đề này bằng contract machine-readable từ `ContractRegistry`/`WidgetCatalog`.

### 15.4 One-Shot Prompt

Server có `OneShotPrompt` để tạo envelope gồm:

- yêu cầu người dùng;
- luật authoring;
- package Cresco;
- return contract.

Mục tiêu UX là người dùng chỉ cần:

```text
1. Chọn section
2. Viết 1 yêu cầu
3. Copy for AI
4. Paste sang AI + ảnh tham chiếu
5. Copy JSON AI trả về
6. Validate & Preview
7. Apply
```

### 15.5 Patch hiện tại không dùng checksum để chặn stale

`cresco-patch/v1` hiện **không yêu cầu `baseChecksum`** cho AI interchange. Field cũ nếu còn trong payload legacy không phải cơ chế bắt buộc để chặn patch.

Điều đó làm workflow dễ dùng hơn, nhưng có trade-off: hệ thống không còn dùng whole-session checksum để tự từ chối chỉ vì document đã thay đổi sau export.

Các hàng rào an toàn còn lại mới là thứ quan trọng:

- target phải tồn tại;
- scope phải hợp lệ;
- operation phải được hỗ trợ;
- ID/destination phải hợp lệ;
- widget contract phải hợp lệ;
- structured style phải thuộc allow-list;
- Custom CSS phải sanitize;
- candidate Session phải canonicalize thành công;
- user phải review/Apply;
- Apply không tự Save WordPress.

### 15.6 Các operation của `cresco-patch/v1`

- `setProps`
- `setStyle`
- `setResponsive`
- `setCustomCSS`
- `insertNode`
- `removeNode`
- `moveNode`
- `replaceSubtree`

`replaceSubtree` đặc biệt hữu ích khi AI redesign toàn section. Root target được giữ ổn định, descendant ID có thể được remap để tránh collision.

### 15.7 Scope

Tùy workflow, AI có thể làm việc ở:

- page;
- widget;
- subtree;
- selection;
- selection-subtrees.

Studio simplified workflow thường ưu tiên subtree của node đang chọn để package nhỏ hơn và AI không sửa lan ra ngoài.

### 15.8 Validate và Apply

Pipeline khái niệm:

```text
AI JSON text
   |
   v
AIResultNormalizer
   |
   v
Patch/Session schema detection
   |
   v
PatchValidator
   |
   +--> scope check
   +--> contract check
   +--> CSS sanitize
   +--> apply on in-memory clone
   +--> canonical session sanitize
   |
   v
Diff preview
   |
User Review
   |
Apply to editor state
   |
User Update/Save
```

Kết quả AI luôn được coi là **untrusted input**.

---

## 16. AI source-of-truth map

Đây là bảng cực kỳ quan trọng khi sửa hệ thống AI:

| Khái niệm | Source of truth |
|---|---|
| Widget types / props | `WidgetCatalog` |
| AI contract | `ContractRegistry` |
| Custom CSS syntax | `ScopedCss` |
| Scope resolution | `ScopeResolver` |
| Patch validation | `PatchValidator` |
| Patch application | `PatchApplier` |
| ID remap | `IdRemapper` |
| Responsive semantics | `ResponsiveResolver` + contract registry |
| Visual rendering | `WebsiteRenderer` / `VisualContext` |
| Session canonicalization | `WebsiteBuilderSessionSanitizer` |
| Prompt envelope | `OneShotPrompt` |

Nếu một AI package quảng bá capability không đúng với các implementation trên, đó là **contract drift** và phải sửa ở source-of-truth, không nên bảo user “đổi prompt”.

---

## 17. Dynamic Data

Dynamic subsystem cho phép render dữ liệu WordPress mà không mở cửa cho query tùy ý vô hạn.

Các capability chính:

- post title/excerpt/featured image/content;
- Dynamic Field;
- ACF khi plugin khả dụng;
- Loop Grid;
- public post type/taxonomy query;
- advanced query/facet ở mức bounded;
- Woo product-related widgets khi WooCommerce khả dụng.

Các nguyên tắc bảo mật:

- không render private meta key bắt đầu `_` theo đường Dynamic Field thông thường;
- query phải allow-list;
- số row/filter/term bị giới hạn;
- public interactive query dùng signed server-authored payload;
- response public chỉ chứa dữ liệu public cần thiết.

---

## 18. Forms

Form widget trong Session không tự biến Cresco thành một form runtime mới độc lập. Renderer bridge nó vào FormBuilder/native Cresco Form pipeline.

Backend vẫn chịu trách nhiệm cho:

- signed configuration;
- server-side validation;
- required fields;
- honeypot;
- rate limit;
- CAPTCHA khi cấu hình;
- storage;
- notifications;
- retention;
- upload;
- redirect;
- idempotency.

Nguyên tắc: **browser validation chỉ là UX; server validation mới là authority**.

Upload không nên được coi như media upload chung. Policy production yêu cầu extension/MIME/content checks và private storage/download authorization.

---

## 19. Theme Builder và template

Website Builder không tạo một router theme thứ hai. Nó tích hợp với domain Theme Builder hiện có cho các loại template như:

- Header;
- Footer;
- Single;
- Page;
- Archive;
- Search;
- 404.

Theme Builder condition/priority/routing tiếp tục là authority của domain Theme. Studio cung cấp shell chung để user quản lý/chỉnh phù hợp theo route/config.

Cần phân biệt reusable component với theme template:

- **Component**: subtree tái sử dụng trong content;
- **Theme template**: layout theo điều kiện site/router.

---

## 20. Reusable Components

Component là subtree đã sanitize được lưu dưới private post type.

Workflow:

```text
Select subtree
  -> Save as component
  -> validate/sanitize
  -> store private component
  -> insert into page
  -> remap stable IDs
```

Studio 2.0 có khả năng create/insert/synchronize current-document instances/detach/delete. Tuy nhiên khi làm migration hoặc compatibility phải kiểm tra đúng generation/behavior vì repo đã trải qua giai đoạn copy-on-insert trước khi mở rộng sync.

---

## 21. Feature Flags và cấu hình bật/tắt

`includes/Support/FeatureFlags.php` là một điểm kiểm soát quan trọng.

Các option/flag hiện có gồm:

- templates;
- forms;
- dynamic;
- dynamic alpha;
- editor alpha;
- interactions;
- theme builder;
- global config import;
- upgrade;
- commercial;
- website builder.

Nhóm default-on hiện tập trung vào:

- interactions;
- global config import;
- website builder.

Các flag còn có thể được điều chỉnh qua filter WordPress theo contract của class.

Ý nghĩa kiến trúc: một subsystem được viết trong repo **không có nghĩa nó luôn active ở mọi site**. Khi debug “code có nhưng UI không thấy”, cần kiểm tra feature flag, capability, dependency và runtime asset trước.

---

## 22. REST/API model

Cresco dùng WordPress REST API và permission callback/capability làm authorization boundary cho endpoint authenticated. Cookie-auth REST nonce do WordPress core xử lý; plugin không cần chồng thêm một nonce riêng ở mỗi route.

Các nhóm route chính:

### 22.1 Session / Website Builder

- load/save Session;
- validate Session;
- builder context/options;
- effective settings;
- preview/render related endpoints.

### 22.2 AI

- AI context;
- AI interchange context export;
- AI result validate/preview.

### 22.3 Page / Global Design

- settings;
- import preview/reset;
- design tokens;
- site identity;
- page settings.

### 22.4 History / Components / Templates

- history list/restore;
- components;
- template catalog;
- theme templates/builder options/diagnostics.

### 22.5 Dynamic

- options;
- query preview;
- field inspect;
- ACF fields;
- advanced query;
- public interactive query/facets.

### 22.6 Forms

- submit JSON;
- multipart submit;
- CAPTCHA verify;
- diagnostics.

Public endpoint chỉ xuất hiện ở nơi frontend anonymous thực sự cần. Các endpoint đó dùng signed payload + bounds + rate limits thay vì coi nonce như anonymous authentication.

---

## 23. Security model

### 23.1 Nguyên tắc

Cresco không tin:

- request từ browser;
- AI JSON;
- Custom CSS;
- form submission;
- uploaded file;
- dynamic query input;
- webhook destination.

Mỗi loại input có sanitizer/validator/bound riêng.

### 23.2 Session/Builder security

Website Builder hiện có hard boundaries:

- schema/version;
- fixed widget catalog;
- unique stable IDs;
- max nodes/depth;
- props contract;
- structured style allow-list;
- scoped Custom CSS;
- safe URL/media/query semantics;
- capability checks cho write.

Website Builder constants hiện tại:

```text
MAX_NODES      = 1000
MAX_DEPTH      = 16
MAX_CUSTOM_CSS = 16000 bytes / bucket boundary
```

Legacy SessionManager còn boundary nhỏ hơn; xem phần storage ở trên.

### 23.3 REST request bounds

`SecurityHardening` đặt global bound cho mutating Cresco REST request và route-specific bounds cho forms/dynamic. Khi thêm endpoint public mới, không được chỉ thêm permission callback `__return_true`; phải có threat model riêng về size, rate, signed payload, idempotency và leakage.

### 23.4 Webhook SSRF

Webhook policy production yêu cầu HTTPS, không credential trong URL, hạn chế port, reject localhost/private/link-local/reserved/metadata networks, validate DNS, disable redirects và revalidate khi retry.

### 23.5 File upload

Upload form phải đi qua extension/MIME/content checks và private storage policy. Không coi file visitor upload là Media Library attachment bình thường.

### 23.6 Logging

Không log password/token/CAPTCHA secret/webhook secret/authorization header/cookie/private submission value. Dữ liệu diagnostic arbitrary phải qua redaction.

---

## 24. Lifecycle, migration và compatibility

Plugin có lifecycle layer riêng cho:

- activate;
- migrate theo version;
- snapshot settings trước migration;
- retry migration;
- downgrade detection/compatibility pause;
- multisite batching;
- deactivate không phá dữ liệu;
- uninstall theo ownership;
- historical upgrade fixtures.

Một invariant quan trọng của release policy: **không được phá hoặc tự ý rewrite user-authored `post_content` ngoài ownership của Cresco**.

---

## 25. Build, test và release engineering

### 25.1 Yêu cầu dev cơ bản

Repo khai báo nền hiện tại:

- WordPress 6.4+;
- PHP 8.0+;
- Node 20+ cho toolchain.

### 25.2 Các nhóm script

`package.json` có các nhóm:

- build/watch;
- runtime build;
- build-integrity;
- PHP lint/tests;
- JS/unit tests;
- E2E Playwright;
- performance/accessibility;
- production/release hardening;
- package verification;
- quality gates.

Không nên coi “build thành công” là “release certified”.

### 25.3 Release candidate hiện tại

`1.0.0-rc.1` vẫn là release candidate. Repo tự ghi nhận các hạng mục cần hosted/real-environment evidence như:

- exact ZIP install/edit/save/preview;
- browser matrix;
- accessibility manual review;
- performance baseline;
- ACF/Woo smoke;
- webserver upload/download;
- upgrade/rollback;
- production operational ownership;
- signing/provenance.

Do đó tài liệu hoặc UI không nên tuyên bố “commercially certified stable” khi các gate chưa có evidence.

---

## 26. Mô hình phát triển: sửa gì thì tìm ở đâu

### 26.1 Thêm hoặc sửa widget

Bắt đầu ở:

```text
includes/Builder/WidgetCatalog.php
```

Sau đó kiểm tra:

1. contract props/styles/parts/states;
2. sanitizer;
3. WebsiteRenderer;
4. Studio Inspector control;
5. AI ContractRegistry/creation catalog;
6. responsive/state output;
7. tests PHP + browser;
8. build source ownership nếu có runtime JS mới.

Không chỉ thêm UI control mà quên validator/render/AI.

### 26.2 Thêm structured style property

Cần bảo đảm property:

- xuất hiện ở style schema đúng widget;
- sanitizer chấp nhận;
- compiler render đúng;
- inspector edit/reset đúng;
- responsive/state merge đúng;
- AI contract quảng bá đúng;
- test round-trip.

### 26.3 Thay Custom CSS

Authority là `ScopedCss`. Khi thêm capability mới:

- parser phải hiểu;
- sanitizer phải an toàn;
- compiler phải scope đúng;
- keyframe/at-rule naming phải collision-safe;
- ContractRegistry phải quảng bá đúng;
- AI test phải chứng minh contract = runtime.

### 26.4 Thay AI import/export

Đi theo chuỗi:

```text
ContextBuilder/ContractRegistry/ScopeResolver
    -> OneShotPrompt
    -> external AI
    -> AIResultNormalizer
    -> PatchValidator
    -> PatchApplier
    -> DiffEngine
    -> Studio Apply
```

Không bypass validator để “AI import dễ hơn”. UX dễ dùng nên được giải bằng context tốt và normalizer deterministic, không bằng cách chạy output tùy ý.

### 26.5 Thay preview

Kiểm tra cả:

- local patch;
- canonical iframe;
- RenderEngine reconcile;
- frontend renderer;
- CSS cleanup khi remove/reset property;
- failure/retry state.

### 26.6 Thay runtime JS reviewed

Nếu file thuộc reviewed runtime:

```text
SỬA runtime-src/build/<file>
KHÔNG SỬA trực tiếp build/<file>
```

Sau đó chạy build và build-integrity.

---

## 27. Ba flow end-to-end để hiểu sâu hệ thống

### Flow A — người dùng đổi background của Container

```text
1. User chọn Container
2. Inspector đọc contract của Container
3. User chọn backgroundColor
4. Studio cập nhật structured style trong Session
5. History ghi thay đổi
6. Local preview patch CSS ngay
7. Background reconcile render canonical
8. User nhấn Update
9. Server sanitize Session
10. JSON được persist vào post meta
11. Frontend renderer đọc cùng Session và render background đó
```

Không cần Custom CSS nếu `backgroundColor` là structured style hợp lệ.

### Flow B — người dùng tạo marquee bằng AI

```text
1. User chọn Container
2. Copy for AI export subtree context
3. Context cho AI biết WidgetCatalog + CSS capability
4. AI tạo List/Container và @keyframes scoped
5. User paste patch
6. AIResultNormalizer parse text
7. PatchValidator kiểm tra operation/target/style/CSS
8. PatchApplier tạo candidate Session in-memory
9. SessionSanitizer + ScopedCss xác nhận
10. Diff hiển thị
11. User Apply
12. Studio preview render
13. User Update để persist
```

Nếu AI dùng `whiteSpace` như structured property trên widget không hỗ trợ, validator từ chối. Cách đúng là hoặc dùng property native hợp lệ, hoặc chuyển sang scoped Custom CSS nếu policy cho phép.

### Flow C — render page ngoài frontend

```text
1. WordPress resolve Page
2. Cresco kiểm tra builder ownership/version
3. Load persisted Session
4. Sanitize/canonicalize nếu boundary yêu cầu
5. Resolve global/page settings
6. WebsiteRenderer render node tree
7. Style compiler sinh CSS theo stable selectors
8. Frontend runtime chỉ được enqueue khi cần
9. HTML/CSS trả cho visitor
```

---

## 28. Directory map cho người mới

```text
cresco-canvas/
├── cresco-canvas.php              # plugin bootstrap
├── includes/
│   ├── Plugin.php                 # composition root
│   ├── Core/                      # document/context/scope/command/transaction foundation
│   ├── Builder/                   # Website Builder, renderer, catalog, Studio integration
│   ├── AI/                        # AI context, patch validation/apply, One-Shot
│   ├── Session/                   # Session v1 legacy/core services
│   ├── Styles/                    # tokens, global styles, scoped CSS
│   ├── Page/                      # Page Settings
│   ├── Dynamic/                   # dynamic/query/facets/integrations
│   ├── Forms/                     # form runtime/security/storage
│   ├── Interactions/              # interactions
│   ├── Theme/                     # Theme Builder
│   ├── Templates/                 # template library
│   ├── Security/                  # security hardening
│   ├── Lifecycle/                 # activation/upgrade lifecycle
│   ├── Migration/                 # migrations
│   └── Commercial/                # commercial boundary
├── src/                           # webpack-owned source
├── runtime-src/build/             # authoritative reviewed runtime source
├── build/                         # shipped runtime assets
├── contracts/                     # machine-readable contracts/schema
├── docs/                          # architecture/protocol/release docs
├── tests/
│   ├── php/                       # PHP tests
│   ├── unit/                      # JS/unit tests
│   ├── e2e/                       # Playwright
│   └── performance/               # performance harness
└── scripts/                       # build/release/quality tooling
```

---

## 29. Thứ tự độ tin cậy của tài liệu/code

Repo lớn và đã trải qua nhiều generation nên có tài liệu lịch sử chưa được xóa. Khi hai nguồn mâu thuẫn, dùng thứ tự sau:

```text
1. Current executable code on main
2. Current machine-readable contracts/schema
3. Current protocol docs (PATCH v1, AI Context v2, Session v1 nếu không mâu thuẫn code)
4. Current Website Builder / Studio docs
5. README
6. Historical architecture docs
```

Ví dụ:

- tài liệu cũ nói Custom CSS cấm `@keyframes`;
- `ScopedCss.php` hiện hỗ trợ local keyframes và namespace;
- AI contract hiện cũng quảng bá keyframes.

=> trạng thái đúng hiện tại là **keyframes được hỗ trợ theo ScopedCss policy**.

Tương tự, security doc cũ có thể còn mô tả checksum/AI output hoặc legacy Session limits. Khi báo cáo implementation hiện tại, phải đối chiếu code/protocol mới trước.

---

## 30. Những điểm còn mang tính chuyển tiếp / cần thận trọng

### 30.1 Legacy và new Website Builder cùng tồn tại

`SessionManager` legacy và Website Builder mới đều có route/sanitizer/render history. Đây là lý do một số constant/docs không hoàn toàn giống nhau. Không nên xóa legacy path nếu chưa có migration/backward compatibility plan.

### 30.2 Runtime source đang ở giai đoạn chuyển đổi

Một số runtime đã nằm trong TS/webpack; một số vẫn dùng `runtime-src/build/` authoritative mirror. Build ownership manifest tồn tại để giữ release reproducible trong giai đoạn chuyển tiếp.

### 30.3 Architecture foundation chưa đồng nghĩa mọi code đã thuần Core

`CORE_ARCHITECTURE.md` mô tả hướng và foundation đã được cài vào hệ thống, nhưng product layer còn nhiều adapter/legacy. Refactor nên tiến dần theo port/adapter thay vì Big Bang rewrite.

### 30.4 Commercial certification chưa hoàn tất

Release candidate có nhiều source-level controls nhưng vẫn cần real-environment evidence. Không nên chuyển “đã implement” thành “đã production-certified” nếu CI/browser/manual gate chưa chạy.

---

## 31. Checklist cho developer trước khi merge thay đổi

### Document / Widget

- [ ] Không tạo document format thứ hai nếu Session v1 đủ dùng.
- [ ] Stable IDs duy nhất.
- [ ] Widget/prop/style đúng WidgetCatalog.
- [ ] Responsive/state đúng contract.
- [ ] Custom CSS qua ScopedCss.

### UI

- [ ] Inspector schema-driven, không hard-code capability song song.
- [ ] Multi-select không làm content update mơ hồ.
- [ ] Keyboard/focus/reduced motion được xem xét.
- [ ] Preview không reload iframe vô cớ.

### AI

- [ ] Context quảng bá đúng runtime capability.
- [ ] Scope không bị escape.
- [ ] AI output vẫn được coi là untrusted.
- [ ] Validate -> diff -> explicit Apply.
- [ ] Apply không tự Save.

### Security

- [ ] Capability/permission callback đúng.
- [ ] Input size bounded.
- [ ] Public route có signature/rate/resource policy nếu cần.
- [ ] Không log secret/private data.
- [ ] Query/upload/webhook có threat model phù hợp.

### Build

- [ ] Sửa đúng authoritative source.
- [ ] `npm run build` nếu cần.
- [ ] build-integrity không drift.
- [ ] PHP/JS tests phù hợp.
- [ ] E2E cho flow browser quan trọng.
- [ ] Không tuyên bố release pass nếu job chưa chạy.

---

## 32. Các lệnh chất lượng nên biết

Tùy loại thay đổi, repo cung cấp các script như:

```bash
npm run build
npm run build:runtime
npm run lint:php
npm run lint:runtime
npm run test:php
npm run test
npm run check:build-integrity
npm run check:runtime-modules
npm run check:website-builder
npm run check:studio
npm run check:quality
```

Ngoài ra còn E2E, accessibility, performance và release hardening scripts. Hãy đọc `package.json` trước khi chọn gate; không cần chạy toàn bộ production matrix cho một thay đổi docs-only, nhưng thay đổi runtime/domain quan trọng phải chạy gate tương ứng.

---

## 33. Mô hình tư duy kiến trúc nên giữ khi phát triển tiếp

Cresco sẽ ổn định hơn nếu mọi feature mới trả lời được 10 câu hỏi sau:

1. **Document nào là authority?**
2. **Context hiện tại là page/theme/component gì?**
3. **Scope nào được phép sửa?**
4. **Widget contract nào cho phép dữ liệu này?**
5. **Command nào mô tả thay đổi?**
6. **Transaction/history/undo xử lý ra sao?**
7. **Sanitizer/security boundary ở đâu?**
8. **Storage nào sở hữu dữ liệu?**
9. **Renderer nào tạo output canonical?**
10. **AI/UI/build có dùng cùng source-of-truth hay đang duplicate?**

Nếu một feature không trả lời rõ các câu này, thường sẽ dẫn tới một trong các lỗi quen thuộc:

- editor đúng nhưng frontend sai;
- AI xuất property validator không hiểu;
- UI có control nhưng save mất dữ liệu;
- CSS leak ra toàn page;
- responsive override không reset được;
- component copy trùng ID;
- build local chạy nhưng ZIP thiếu asset;
- public endpoint không được bound;
- tài liệu quảng bá capability mà runtime không hỗ trợ.

---

## 34. Kết luận

Cresco Canvas hiện không chỉ là một bộ widget UI. Nó là một hệ thống gồm:

```text
Schema-driven document
+ visual Studio
+ canonical renderer
+ style/token system
+ responsive/state model
+ WordPress persistence
+ scoped security boundaries
+ reusable components
+ dynamic/forms/theme domains
+ AI interchange contract
+ build/runtime ownership
+ release/diagnostic tooling
```

Ba nguyên tắc quan trọng nhất để hiểu và phát triển Cresco đúng cách là:

### Nguyên tắc 1 — Session là dữ liệu gốc

Đừng để DOM, React component state hoặc AI output trở thành authority song song.

### Nguyên tắc 2 — Contract là luật

WidgetCatalog, ContractRegistry, ScopedCss, ScopeResolver, PatchValidator và renderer phải đồng thuận. Nếu chúng lệch nhau, sửa contract drift thay vì buộc người dùng học workaround.

### Nguyên tắc 3 — Preview, Save và Frontend phải hội tụ

Editor có thể tối ưu bằng local patch, nhưng kết quả cuối cùng phải hội tụ về cùng canonical document + renderer. Đây là nền tảng để visual builder đáng tin cậy.

---

## 35. Tài liệu và source nên đọc tiếp

Đọc theo thứ tự đề xuất:

1. `docs/CRESCO_CANVAS_SYSTEM_REPORT.md` — tài liệu này.
2. `docs/CRESCO_SESSION_V1.md` — document contract.
3. `docs/WEBSITE_BUILDER_CORE.md` — Website Builder capability.
4. `docs/STUDIO_EDITOR_EXPERIENCE_2.md` — Studio UX/runtime.
5. `docs/REALTIME_CANONICAL_PREVIEW.md` — preview architecture.
6. `docs/CRESCO_PATCH_V1.md` — AI patch protocol.
7. `docs/CRESCO_AI_CONTEXT_V2.md` — One-Shot AI context.
8. `docs/CORE_ARCHITECTURE.md` — Core foundation direction.
9. `docs/SECURITY.md` — production security model; đối chiếu current code nếu gặp chi tiết legacy.
10. `docs/KNOWN_LIMITATIONS.md` — những gì chưa được commercial/release-certified.
11. `includes/Plugin.php` — service composition thực tế.
12. `includes/Builder/WidgetCatalog.php` — widget source-of-truth.
13. `includes/Builder/WebsiteBuilder.php` — Website Builder REST/persistence/runtime boundary.
14. `includes/Builder/WebsiteBuilderSessionSanitizer.php` — canonical Session sanitization cho advanced CSS.
15. `includes/Styles/ScopedCss.php` — Custom CSS parser/compiler.
16. `includes/AI/*` — toàn bộ AI flow.
17. `runtime-src/README.md` — runtime source/build ownership.
18. `package.json` — build/test/release scripts.

---

## Phụ lục A — Source-of-truth quick reference

```text
Plugin boot                -> cresco-canvas.php
Service composition        -> includes/Plugin.php
Feature flags              -> includes/Support/FeatureFlags.php
Current builder boundary   -> includes/Builder/WebsiteBuilder.php
Widget schema              -> includes/Builder/WidgetCatalog.php
Session CSS-aware sanitize -> includes/Builder/WebsiteBuilderSessionSanitizer.php
Custom CSS                 -> includes/Styles/ScopedCss.php
Core architecture          -> includes/Core/*
AI context                 -> includes/AI/ContextBuilder*.php
AI widget contract         -> includes/AI/ContractRegistry.php
AI scope                   -> includes/AI/ScopeResolver.php
AI prompt                  -> includes/AI/OneShotPrompt.php
AI result normalization    -> includes/AI/AIResultNormalizer.php
AI validation              -> includes/AI/PatchValidator.php
AI apply                   -> includes/AI/PatchApplier.php
AI diff                    -> includes/AI/DiffEngine.php
Render                     -> includes/Builder/WebsiteRenderer*.php
Page settings              -> includes/Page/*
Global design              -> includes/Styles/GlobalStyles.php + DesignTokens.php
Security                   -> includes/Security/*
Reviewed runtime source    -> runtime-src/build/*
Shipped runtime            -> build/*
Quality scripts            -> package.json + scripts/*
```

## Phụ lục B — Một câu để giải thích Cresco Canvas cho từng vai trò

**Cho người dùng:** “Cresco Canvas là trình dựng trang trực quan trong WordPress, nơi mọi phần tử là widget có control rõ ràng và responsive.”

**Cho designer:** “Design của Cresco được tách thành global tokens, page settings, widget structured styles, responsive/state overrides và scoped Custom CSS fallback.”

**Cho developer:** “Cresco là schema-driven document editor: Session là authority; WidgetCatalog/validator/renderer/AI phải dùng cùng contract.”

**Cho QA:** “Kiểm thử không chỉ UI; phải kiểm tra save/reload, canonical preview/frontend parity, breakpoint, history, AI validate/apply và build/source parity.”

**Cho security reviewer:** “Mọi document/AI/form/query/upload/webhook input là untrusted và phải qua schema, capability, bounds, sanitizer và domain-specific security controls.”

**Cho AI agent:** “Không đoán widget/property; đọc contract, chỉ sửa trong scope, ưu tiên native props/style trước Custom CSS và trả `cresco-patch/v1` hợp lệ.”
