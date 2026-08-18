# Studio Size Control System

> **Trạng thái:** Canonical cho control kích thước trong Cresco Studio.
>
> **Owner:** `WebsiteBuilderStudio.React`.
>
> **Runtime:** `runtime-src/build/studio-dimension-controls.js` (mirror `build/studio-dimension-controls.js`).
>
> **Service PHP:** `includes/Builder/StudioDimensionControls.php`.

---

## 1. DimensionControl canonical là gì

`DimensionControl` là React component dùng chung cho mọi property mang ngữ nghĩa kích thước trong Inspector.

Mỗi control gồm hai phần:

```text
[ value ] [ mode ▼ ]
```

- **mode dropdown**: unit (`px`, `%`, `em`, `rem`, `vw`, `vh`, `vmin`, `vmax`, `ch`), semantic keyword (`Auto`, `Full (100%)`, `Fit content`, …) hoặc `Custom CSS`.
- **value input**: chỉ hiển thị khi mode đang chọn cần số. Với keyword mode, input bị disable và Studio hiển thị nhãn keyword.

Control được đăng ký qua `window.CrescoStudioSDK.registerInspectorSection`, **không** dựng DOM riêng.

---

## 2. Property nào dùng DimensionControl

| Nhóm | Property |
| --- | --- |
| `layout` | `width`, `minWidth`, `maxWidth`, `height`, `minHeight`, `maxHeight`, `gap`, `columnGap`, `rowGap`, `flexBasis` |
| `style` | `fontSize`, `lineHeight`, `letterSpacing`, `borderWidth`, `borderRadius` |
| `advanced` | `top`, `right`, `bottom`, `left`, `inset`, và Margin/Padding bốn cạnh |
| `content` | Widget prop có `schema.type === 'css'` và tên/label mang ngữ nghĩa kích thước |

Danh sách này bám đúng `WidgetCatalog::style_groups()`. Property nào không nằm trong allow-list của sanitizer thì **không** được expose.

`aspectRatio` và các prop có `ratio` trong tên bị loại trừ vì không phải đại lượng kích thước tuyến tính.

---

## 3. Size mode theo property

Không phải property nào cũng nhận mọi unit. `SPECIAL_UNITS` thu hẹp danh sách:

| Property | Unit |
| --- | --- |
| `lineHeight` | `unitless`, `px`, `%`, `em`, `rem` |
| `letterSpacing` | `px`, `em`, `rem` |
| `fontSize` | `px`, `%`, `em`, `rem`, `vw`, `vh`, `vmin`, `vmax` |
| `borderWidth` | `px`, `em`, `rem` |
| `borderRadius` | `px`, `%`, `em`, `rem` |
| còn lại | toàn bộ `UNITS` |

Keyword theo property nằm trong `KEYWORDS`, ví dụ `width` có `Auto / Full (100%) / Fit content / Min content / Max content`, còn `maxWidth` có thêm `None`.

---

## 4. Custom CSS behavior

Chọn `Custom CSS` sẽ đổi input sang text tự do để nhập `calc()`, `clamp()`, `min()`, `max()`, `var()` hoặc Cresco token.

`Custom` **không** phải đường vòng qua sanitizer. Giá trị vẫn đi qua control React canonical rồi tới `WebsiteBuilder::sanitize_css_value()`, nơi:

- giữ nguyên token dạng `{group.key}`;
- cho phép `calc/clamp/min/max/var` và ký tự CSS an toàn;
- loại bỏ `;`, `{`, `}`, `<`, `>`, `url(`, `expression(`, `javascript:`, `behavior:`, `-moz-binding`;
- giới hạn 240 ký tự.

Giá trị không parse được thành unit/keyword sẽ tự động hiển thị ở mode `Custom` thay vì bị ghi đè. Token và `var(--…)` do đó không bao giờ bị mất.

---

## 5. Session representation

`DimensionControl` chỉ là UI abstraction. Session vẫn lưu **một CSS string canonical**:

```text
120 + px            -> "120px"
50 + %              -> "50%"
Full (100%)         -> "100%"
Auto                -> "auto"
Fit content         -> "fit-content"
Custom clamp(...)   -> "clamp(20rem, 50vw, 70rem)"
```

Không có object `{ value, unit, mode }` nào được ghi vào Session. Mode chỉ được suy ra khi đọc, bằng `inferMode()`.

---

## 6. Responsive behavior

Control dùng đúng cascade canonical, không tạo engine thứ hai:

```text
wide base -> desktop -> laptop -> tablet -> mobile
```

- `effective()` gộp `node.style` với các bucket `node.responsive` theo thứ tự trên, rồi mới tới `node.states`.
- `owns()` phân biệt **explicit override** ở device/state hiện tại với **giá trị inherited**.
- Sửa ở breakpoint nào thì tạo override đúng ở breakpoint đó.
- Nút Reset chỉ xuất hiện khi có override thật, và xoá đúng override đó — không reset cả breakpoint.

State `normal / hover / focus / active` đọc-ghi qua `node.states` theo contract sẵn có; không có state store mới.

---

## 7. Margin / Padding

Margin và Padding hiển thị bốn cạnh độc lập (`marginTop`, `marginRight`, …), mỗi cạnh là một `DimensionControl` đầy đủ nên **có thể có unit riêng theo từng cạnh** — điều này hợp lệ vì widget Session lưu bốn CSS string tách biệt.

Nút **Link** là state trình bày, **không** được ghi vào Session. Khi bật, sửa một cạnh sẽ ghi cùng giá trị cho cả bốn cạnh qua đúng control bridge mà thao tác unlinked vẫn dùng.

---

## 8. Page Settings limitation

Page Settings là persistence domain **khác** và hiện vẫn dùng **một unit dùng chung cho cả bốn cạnh**:

- bucket: `desktop`, `tablet`, `mobile`;
- side: `top`, `right`, `bottom`, `left`;
- một `unit` cho Margin, một `unit` cho Padding;
- unit hỗ trợ: `px`, `%`, `em`, `rem`, `vh`, `vw`;
- có cờ `linked` đã được sanitize.

Vì vậy Page Settings **không** dùng `DimensionControl` per-side và **không** có `Custom`/keyword mode. UI ở đây giữ đúng một dropdown unit dùng chung, cộng nút **Link** điều khiển giá trị (không phải unit).

Muốn per-side unit hoặc `custom` cho Page Settings thì phải nâng cấp atomic: `defaults -> sanitizer -> inheritance -> compiler -> REST -> Studio UI -> alternate UI -> AI/import-export -> tests -> docs`. Không được giả lập ở UI.

---

## 9. React ownership rule

`DimensionControl` là React-native và tuân thủ:

- đăng ký qua `CrescoStudioSDK`, không tự mount shell;
- `ownsDom: false`, `childDomMutations: false`;
- không `appendChild`, `insertBefore`, `removeChild`, `replaceChildren`, `replaceWith`, `innerHTML`, `outerHTML`;
- không monkey-patch `wp.element.createElement`;
- control React nguồn vẫn mounted và vẫn là state owner; layer này chỉ ẩn control trùng lặp bằng CSS trong khi bản SDK đang hiển thị.

Ghi giá trị đi qua native value setter + `input`/`change` event trên chính control canonical (`sourceBridge: 'native-input-event'`), nên Session vẫn do React sở hữu.

---

## 10. Legacy đã retired

`studio-dimension-controls-sync.js` là DOM-enhancement runtime cũ. Nó **không** được enqueue và phải nằm trong danh sách retired của `StudioReactOwnershipGuard`. Không bật lại runtime này; logic hữu ích đã được port sang component React ở trên.

---

## 11. Regression gate

`npm run check:react-dom-ownership` (`scripts/check-studio-react-dom-ownership.mjs`) kiểm tra:

- source/build parity của dimension runtime và Studio runtime;
- marker React SDK, `ownsDom: false`, không có DOM mutation token;
- mọi property box-model đều có keyword mode; `Custom CSS` tồn tại;
- `SPECIAL_UNITS` và `lineHeight` unitless;
- Margin/Padding có BoxGroup và nút Link, và linked ghi đủ bốn cạnh;
- widget spacing **không** phát sinh key `linked` trong Session;
- responsive dùng đúng thứ tự `desktop → laptop → tablet → mobile` và có phân biệt override/inherited;
- Page Settings giữ enum unit dùng chung và **không** có `unitTop/unitRight/unitBottom/unitLeft`;
- legacy sync runtime vẫn retired.
