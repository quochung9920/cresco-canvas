# Ownership runtime Cresco Studio và phòng ngừa xung đột

Trạng thái: **Contract kỹ thuật canonical cho Website Builder runtime của Cresco Studio hiện tại**

Baseline audit gốc: `00be3f489dfef530d31394d951b3ea4d261cc7d3` (2026-08-17)

Tài liệu này tồn tại để ngăn nhóm regression mà Cresco Canvas đã gặp: một feature có vẻ đã được làm trong một file/branch nhưng browser vẫn chạy UI cũ; hai runtime hoặc hai CSS owner cạnh tranh; DOM enhancer sửa node do React sở hữu; UI expose value backend không lưu được; source/build drift; hoặc tài liệu kiến trúc lịch sử mâu thuẫn runtime thực sự ship.

Mục tiêu không chỉ là tránh merge conflict mà là tránh **ownership conflict** giữa runtime, DOM, state, CSS, data schema, build artifact, optional module, documentation và long-lived branch.

---

## 1. Invariant cốt lõi

Các quy tắc sau không được phá trừ khi ADR mới supersede rõ ràng:

1. **Một Website Builder runtime sở hữu Studio shell.** `WebsiteBuilderStudio` là owner canonical.
2. **Một Session model sở hữu editable document.** `cresco-session/v1` authoritative cho Studio document.
3. **React sở hữu React-rendered DOM.** Optional module không replace/reparent/clone/rewrite React-owned node.
4. **Server schema/sanitizer quyết định persisted state hợp lệ.** UI không được quảng bá cấu trúc/value unsupported.
5. **Persisted feature phải tiến hóa atomic.** Defaults, sanitizer, compiler/renderer, REST, UI, alternate surface, tests, AI/export và docs phải đồng bộ khi data model đổi.
6. **CSS ownership phải explicit.** Foundation khai báo layer order; structural rule và presentation polish có owner khác nhau.
7. **Source order trong cùng layer vẫn quan trọng.** Layer không loại bỏ CSS conflict.
8. **Source/build mirror không drift.** Cặp được quy định byte-identical phải cập nhật cùng nhau.
9. **Optional module chỉ additive.** Lỗi module không được takeover core Studio.
10. **Long-lived branch không được âm thầm tụt sau `main`.**
11. **Historical docs không được override current code ownership.**
12. **Visual change không chứng minh model change và ngược lại.** Phải verify độc lập.

---

## 2. Bản đồ ownership runtime hiện tại

| Khu vực | Owner canonical | Trách nhiệm | Không được làm |
| --- | --- | --- | --- |
| Studio shell | `build/website-builder-studio.js` + source mirror | React app, primary state, panel, Canvas, Structure, Inspector, Page panel, command, save | Cho runtime khác mount app cạnh tranh |
| Runtime registration | `includes/Builder/WebsiteBuilderStudio.php` | Handle canonical, deps, content hash, support assets, diagnostics | Tạo core editor handle thứ hai cho cùng screen |
| Runtime context/config | `WebsiteBuilderRuntimeContext`, `WebsiteBuilderEditorConfig` | Resolve Page/editor context và endpoint | Optional module tự invent document identity/endpoint |
| Module activation | `WebsiteBuilderModuleRegistry` | Required/optional module policy | Module bypass registry ownership |
| Responsive Inspector | `build/website-builder-responsive-properties.js` | Responsive UI, grouping/accordion, widget-aware enhancement | Trở thành source of truth dữ liệu |
| Dimension/Border | `StudioDimensionControls`, `build/studio-dimension-controls.js` | Proxy dimension/unit và Border/Radius trên control canonical | Tạo dimension/border schema thứ hai |
| Typography popup | `StudioTypographyPopup`, `build/studio-typography-popup.js` | Trình bày Typography canonical controls trong popup | Tạo typography state/store thứ hai |
| Widget state tabs | `StudioWidgetStateTabs` + runtime asset liên quan | Presentation cho state được widget contract hỗ trợ | Invent state không có trong contract |
| Structure ownership | Studio Structure + `StudioStructureLayout` | Node management/navigation | Duplicate node-management owner trong Inspector |
| UI correction | `website-builder-ui-correction.js/css` | Structural correction hẹp | Tạo data model/theme khác |
| Unset/reset | `website-builder-unset-styles.js`, `STYLE_UNSET_SEMANTICS.md` | Explicit unset/reset model bridge | Chỉ blank DOM input |
| Responsive inheritance extension | `studio-responsive-inheritance.js/css` | Hiển thị inheritance semantics | Persist responsive schema thứ hai |
| Foundation | `assets/css/cresco-foundation.css` | Token + cascade layer order + motion foundation | Load sau layer declaration cạnh tranh |
| Base Studio CSS | `website-builder-studio.css` | Cấu trúc/base presentation | Chứa unrelated override |
| Premium polish | `website-builder-premium-polish.css` | Presentation refinement | Sở hữu runtime/layout semantics/data |
| Page Settings model | `includes/Page/PageSettings.php` | Defaults, sanitizer, effective value, compile, persistence | Chấp nhận UI-only schema |
| Studio Page Settings | `pagePanel()` trong Studio | View/edit canonical Page Settings | Tạo Page Settings schema khác |
| Global Design Pro | Global Design Pro module/bridge | Extend Global Design bằng mount/bridge an toàn | Replace/reparent panel hoặc token state canonical |
| Legacy builder asset | Legacy file còn giữ | Compatibility khi được chọn rõ | Load sau Studio và takeover same screen |

Feature mới chạm Studio-owned surface phải xác định owner tương tự trước khi triển khai.

---

## 3. Single runtime ownership

### 3.1 Canonical handle

Studio sở hữu handle `cresco-canvas-website-builder` thông qua `WebsiteBuilderStudio`. Việc giữ handle là chủ ý để module phụ cũ vẫn dependency đúng trong khi implementation thay đổi.

Owner chịu trách nhiệm:

- canonical script `src`;
- dependencies;
- content-addressed version;
- enqueue một lần;
- `window.crescoWebsiteBuilderSettings` trước execution;
- expected runtime = `studio`;
- support asset cùng runtime family.

### 3.2 Pattern bị cấm

Không:

- enqueue core runtime cũ sau Studio;
- mount `.cc-studio-app` thứ hai hoặc legacy `.cc-builder-app` sau Studio;
- module phụ lỗi rồi re-register core handle sang runtime khác;
- compatibility service mutate handle sau `WebsiteBuilderStudio::enforce_runtime_ownership()`;
- observer phát hiện Studio rồi dựng editor shell thứ hai.

Optional feature lỗi phải thành **feature degradation**, không phải runtime replacement.

### 3.3 Diagnostics tối thiểu

Khi debug browser, kiểm tra:

- `window.crescoExpectedWebsiteBuilderRuntime === 'studio'`;
- `window.crescoWebsiteBuilderEditorBoot` đi tới Studio phase mong đợi;
- `window.crescoStudioRuntimeOwnership` báo Studio mount và không có competing legacy mount;
- chỉ một `.cc-studio-app` tồn tại;
- không có unexpected legacy root trong `#cresco-canvas-standalone-editor`;
- asset URL có content-hashed version mong đợi.

Nếu sai, sửa runtime ownership trước CSS.

---

## 4. React DOM ownership

### 4.1 DOM do React render không phải extension API

`.cc-studio-*` là implementation selector. Optional module ưu tiên:

1. `window.CrescoStudioSDK`;
2. explicit event/state bridge;
3. React portal vào stable host;
4. additive sibling mount point;
5. DOM enhancer hẹp nếu không có extension point.

### 4.2 Operation bị cấm

Không dùng trên React-owned content nếu Core chưa delegate:

- `innerHTML = ...`;
- `replaceWith(...)`;
- `replaceChildren(...)`;
- remove React parent;
- reparent canonical control;
- clone control rồi hide original;
- đổi input DOM và giả định React state đã đổi;
- append enhancer không idempotent từ `MutationObserver`.

### 4.3 MutationObserver

Observer dùng cho **discovery**, không ownership. Observer an toàn phải:

- theo dõi root hẹp nhất;
- có mount marker idempotent;
- schedule/debounce/coalesce;
- disconnect/inert khi không cần;
- không tự tạo feedback loop;
- ưu tiên stable id/data attribute thay vì label text.

### 4.4 Portal/bridge pattern

Khi enhancement cần xuất hiện trong React-owned panel:

- tìm stable host;
- không replace host;
- tạo một additive mount container;
- render/portal vào container;
- đọc/ghi qua canonical state bridge;
- unmount khi host mất;
- remount idempotent nếu React recreate host.

---

## 5. State ownership

Authority:

1. server sanitizer/model;
2. Studio React state;
3. DOM phản chiếu state;
4. optional module local state chỉ presentation/transient trừ khi được contract giao ownership.

Không để duplicate semantic state, ví dụ:

- Studio Page panel và Page Settings Pro lưu shape khác nhau;
- enhancer dùng `sessionStorage` để override Session sau reload;
- Global Design module giữ token object riêng không reconcile;
- DOM input rỗng nhưng responsive key vẫn còn trong Session.

Reset/unset phải thay model thật sự. Xem `STYLE_UNSET_SEMANTICS.md`.

---

## 6. CSS cascade và ownership

### 6.1 Foundation khai báo layer order trước

`assets/css/cresco-foundation.css` hiện khai báo:

```css
@layer cresco.base, cresco.tokens, cresco.components, cresco.theme, cresco.overrides, cresco.motion;
```

Layer order bị cố định từ lần khai báo đầu tiên. Foundation phải enqueue trước stylesheet mở Cresco layer.

### 6.2 Trách nhiệm layer

| Layer | Trách nhiệm |
| --- | --- |
| `cresco.base` | Base/editor structure và primitive |
| `cresco.tokens` | Canonical token và legacy alias |
| `cresco.components` | Reusable component presentation |
| `cresco.theme` | Theme-level presentation khi có |
| `cresco.overrides` | Final polish, không structural ownership |
| `cresco.motion` | Timing/micro-motion và interaction feedback |

### 6.3 Layer không phải conflict shield

Trong cùng layer vẫn áp specificity/source order. Do đó:

- một semantic rule nên có một owner;
- không copy selector sang file sau để ép appearance;
- sửa owner thật;
- kiểm tra duplicate owner trước `!important`.

### 6.4 Structural CSS và polish CSS

Premium polish có thể refine color, shadow, gradient, visual border, focus, subtle hover, radius không structural.

Nó không được sở hữu shell grid/flex, existence/visibility contract của core control, responsive data semantics hoặc mount behavior.

### 6.5 Motion

Motion timing/easing dùng token trong `cresco-foundation.css`; `cresco.motion` là layer cuối cho interaction timing. `prefers-reduced-motion` phải thắng decorative motion.

---

## 7. Page Settings: canonical model

`includes/Page/PageSettings.php` là persistence owner.

Page Settings v2 có shape khái quát:

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

PHP defaults/sanitizer mới là authority đầy đủ.

### 7.1 Spacing semantics hiện tại

- Margin dùng **một shared unit** cho 4 side và mọi bucket.
- Padding cũng dùng **một shared unit**.
- Unit: `px`, `%`, `em`, `rem`, `vh`, `vw`.
- Value lưu tách khỏi unit.
- `linked` thuộc control model.
- Bucket: `desktop`, `tablet`, `mobile`.
- Tablet/mobile missing value inherit theo backend logic.

Không expose Top=`2rem`, Left=`24px` nếu backend chỉ lưu một unit chung.

### 7.2 Responsive resolution

- desktop = desktop;
- tablet = desktop + non-empty tablet override;
- mobile = desktop + tablet + non-empty mobile override.

Clear tablet/mobile side nghĩa remove override để inheritance quay lại, không phải persist `0`.

### 7.3 Nhiều view, một model

Studio Page rail và standalone/classic Page Settings là nhiều view của cùng backend. Feature mới phải liệt kê mọi UI surface bị ảnh hưởng.

### 7.4 Protocol thay schema atomic

Một persisted capability chỉ hoàn chỉnh khi cập nhật:

1. defaults;
2. sanitizer;
3. effective/inheritance logic;
4. frontend compiler;
5. REST;
6. Studio Page UI;
7. alternate UI nếu expose cùng field;
8. AI/import/export nếu portable;
9. tests;
10. save/reload browser verification;
11. docs.

### 7.5 Border/Radius warning

Widget Inspector có Border/Radius không có nghĩa Page Settings tự động có cùng schema. Muốn Page-level Border/Radius phải thiết kế Page Settings storage, sanitizer, compiler, inheritance, unit, linked/unlinked và tests trước.

### 7.6 Shared primitive, adapter riêng

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
(Session style/responsive)      (PageSettings v2 hoặc successor)
```

Reuse interaction/presentation primitive, nhưng adapt theo canonical storage model từng domain.

---

## 8. Widget responsive style contract

Device sequence:

- `wide` / base;
- `desktop`;
- `laptop`;
- `tablet`;
- `mobile`.

Responsive Inspector có thể group Display & Size, Gaps, Alignment, Flexbox, Grid, Typography, Background, Border, Effects, Margin/Padding, Position, Overflow, Transform, Media, Custom CSS.

Quy tắc:

1. Enhancer tổ chức/proxy control; Session authoritative.
2. Reset phải sửa đúng model override.
3. Không có hidden input thứ hai sở hữu independent state.
4. State styling không được tạo breakpoint cascade thứ hai.
5. Composite control phải khai báo rõ style key đọc/ghi/reset.
6. Border control dùng Widget style keys hiện có.
7. Typography popup chỉ trình bày canonical Typography controls.

---

## 9. Global Design và optional module

Global Design Pro thể hiện pattern extension an toàn: **mount vào stable host mà không lấy ownership của host**.

Optional module phải:

- dùng activation policy canonical;
- depend canonical Studio handle khi cần load order;
- không register core runtime khác;
- fail closed ở mức feature;
- dùng settings/session bridge canonical;
- giữ local state transient;
- mount một lần, cleanup rõ;
- không phụ thuộc label/DOM text nếu SDK hook tồn tại.

Nếu module không thể hoạt động nếu không replace core node, Core cần extension point mới trước khi module ship.

---

## 10. Build/source parity và cache safety

### 10.1 Runtime parity

Source/runtime mirror và production `build/` copy được khai báo byte-identical phải giữ giống nhau.

```text
sha256(source-runtime-file) == sha256(build-runtime-file)
```

Không sửa riêng `build/` để hot-fix rồi “đồng bộ sau”.

### 10.2 Content-addressed version

`WebsiteBuilderAsset` dùng content hash cho canonical asset.

Nếu UI cũ xuất hiện:

1. xem URL asset thực trong DevTools;
2. verify version/hash đổi;
3. verify canonical handle trỏ đúng file;
4. sau đó mới nghi cache.

Nếu React source vẫn render UI cũ thì cache clear không tạo được structure chưa tồn tại.

---

## 11. Branch và merge discipline

Runtime bug thường là branch-age bug giả dạng frontend bug.

Trước khi sửa:

- main ở commit nào?
- feature branch ở commit nào?
- branch behind/ahead/diverged?
- main có vừa đổi runtime, foundation CSS, Page Settings, responsive hoặc mount pattern không?

Nếu branch chỉ behind và fast-forward an toàn, sync trước khi chỉnh.

Trong lúc làm:

- runtime ownership change phải nhỏ và explicit;
- không mix takeover fix với unrelated polish;
- không dùng no-op retry commit thay diagnosis;
- không force-update shared branch chỉ để làm comparison biến mất.

Trước merge, đọc lại latest main của runtime owner, responsive, UI correction, foundation, polish, Page Settings và module cùng panel. Review **semantic conflict** ngay cả khi Git không báo textual conflict.

Semantic conflict gồm:

- hai file cùng nghĩ mình sở hữu một DOM node;
- hai CSS file cùng target property vì lý do khác;
- hai control represent cùng setting khác nhau;
- hai module register cùng handle;
- UI value bị sanitizer bỏ;
- source/build mirror mismatch.

---

## 12. Authority tài liệu

Thứ tự cho Studio hiện tại:

1. current executable code + tests;
2. current ADR áp dụng rõ cho Studio-owned document;
3. `PROJECT_RULES.md`;
4. tài liệu này;
5. `CORE_ARCHITECTURE.md`, `WEBSITE_BUILDER_CORE.md`;
6. feature-specific current docs;
7. historical Gutenberg-native docs trong scope cũ.

Nếu code và contract này lệch ngoài ý muốn, đó là defect; sửa một trong hai trong cùng change.

ADR cũ được giữ làm history nhưng phải ghi superseded/scope rõ.

---

## 13. Protocol theo loại thay đổi

### 13.1 Visual-only polish

Cho phép color/shadow/focus/radius/non-structural motion. Verify không đổi DOM ownership, schema, functional visibility và keyboard focus.

### 13.2 Structural Inspector/Page UI

Phải xác định React owner, observer/enhancer liên quan, nơi thay đổi đúng (React/UI correction/SDK), stable selector/data attribute và compatibility với responsive/unset module.

### 13.3 Page Settings field mới

Một change phối hợp gồm schema/default, sanitization, persistence, inheritance, compile/render, UI surfaces, tests, AI/import/export nếu áp dụng, docs.

### 13.4 Optional Studio module mới

Phải định nghĩa module id/dependency, mount/SDK hook, state authority, failure, cleanup, CSS owner, test/diagnostics.

### 13.5 Core runtime replacement

Đây là architectural migration, cần ADR mới, canonical handle plan, module compatibility, Session migration, CSS ownership, diagnostics, rollback, source/build migration và stale docs update.

---

## 14. Verification matrix

### Static/code

- PHP syntax pass cho file đổi.
- JS syntax/build pass.
- source/build parity pass.
- không duplicate runtime registration.
- foundation layer order đúng.
- module dependency hướng về canonical runtime.

### PHP tests

Review/run suite liên quan như Page Settings, Studio hardening, Website Builder và module-specific test.

UI schema change không được chấp nhận chỉ vì JS render được; backend persistence test phải chứng minh value sống qua sanitizer/compiler.

### Browser smoke

Với Studio change, tối thiểu:

- first load;
- save;
- reload;
- dirty guard;
- breakpoint liên quan;
- rời/quay lại panel;
- optional mount/unmount;
- keyboard/focus;
- không duplicate controls;
- không console error/observer loop;
- không legacy runtime mount.

Page Settings cần test set/clear desktop-tablet-mobile override, save/reload, frontend effective result và reset về inherited value.

---

## 15. Troubleshooting

### “Đã đổi UI nhưng Page Settings cũ vẫn hiện”

Kiểm tra branch SHA -> served runtime asset -> React implementation -> duplicate runtime -> CSS owner -> backend schema.

### “CSS đã load nhưng không đổi gì”

Kiểm tra selector -> layer -> same-layer source order -> override layer -> inline/React control -> liệu yêu cầu có thật sự visual hay structural.

### “Reset đúng cho tới khi reload”

Khả năng cao DOM đổi nhưng model override chưa bị remove. Dùng unset bridge và kiểm tra persisted Session.

### “Pro panel bị duplicate”

Kiểm tra mount idempotency, observer re-entry, host recreation, cleanup.

### “Control nhận value nhưng save đổi/xóa nó”

Đọc PHP sanitizer trước CSS. UI có thể đang đi trước schema.

### “Fix có trên GitHub nhưng branch tôi không có”

Compare branch head; fast-forward nếu an toàn trước khi debug.

---

## 16. Checklist trước merge

### Runtime

- [ ] Một core Studio runtime.
- [ ] Optional module không replace canonical handle.
- [ ] Feature lỗi không block core startup.

### DOM

- [ ] Không replace/reparent/clone React node.
- [ ] Observer discovery-only và idempotent.
- [ ] Portal/mount cleanup deterministic.
- [ ] Dùng stable identity thay label scraping khi có thể.

### State

- [ ] Một source of truth cho persisted field.
- [ ] Reset/unset sửa model.
- [ ] Local state transient/synchronized.

### CSS

- [ ] Foundation layer order đúng.
- [ ] Structural rule nằm ở structural owner.
- [ ] Premium polish chỉ presentation.
- [ ] Same-layer collision đã review.
- [ ] `!important` mới có lý do hard-boundary.

### Schema/Page Settings

- [ ] UI chỉ expose value backend lưu được.
- [ ] Defaults/sanitizer/compiler/REST/UI/test cùng shape.
- [ ] Các view cùng semantics.
- [ ] Responsive/reset sống qua save/reload.

### Build/branch

- [ ] Source/build synchronized.
- [ ] Branch đã compare latest main.
- [ ] Không bỏ sót owner mới trên main.

### Docs

- [ ] Ownership rule mới có ADR/contract update.
- [ ] Superseded docs được đánh dấu.
- [ ] Feature docs nói đúng current surface.

---

## 17. Danh sách tuyệt đối không làm

Không:

- đổ lỗi cache trước khi kiểm tra source/runtime;
- sửa branch cũ mà không compare main;
- tạo fallback runtime thứ hai;
- dùng polish CSS cho functional layout/visibility;
- tăng specificity vô hạn thay vì sửa owner;
- rewrite React DOM để tạo panel mới;
- đổi DOM input rồi giả định state đổi;
- expose per-side Page Settings unit khi backend dùng shared unit;
- copy Widget Border vào Page Settings thiếu schema;
- cho các Page Settings view lưu shape khác nhau;
- sửa một phía source/build mirror;
- giữ historical architecture mâu thuẫn mà không warning;
- dùng no-op commit như bằng chứng fix;
- gọi feature complete nếu save/reload và frontend result chưa khớp.

---

## 18. Kết luận audit ownership

Các phát hiện quan trọng từ audit gốc vẫn có giá trị như bài học:

1. Branch dài hạn từng tụt sau `main`; phải sync trước diagnosis.
2. Studio Page panel có control structure riêng nhưng cùng backend Page Settings; Pro surface không tự thay thế nó.
3. Page Settings v2 lưu shared unit cho Margin/Padding.
4. Widget Inspector Border/Radius giàu hơn Page Settings nhưng không thể copy schema trực tiếp.
5. CSS foundation giúp giảm conflict nhưng source order/specificity vẫn cần owner rõ.
6. Global Design Pro mount là pattern portal/bridge nên reuse.
7. Gutenberg-only docs lịch sử đã bị supersede trong scope Studio-owned document.

Hiện tại còn bổ sung một bài học: Dimension/Border, State Tabs và Typography popup phải tiếp tục là presentation/proxy trên canonical Session controls, không được tiến hóa thành store cạnh tranh.

---

## 19. Định nghĩa “đủ conflict-free để ship”

Không có tài liệu nào đảm bảo tương lai không có conflict. Với Cresco Studio, đủ để ship nghĩa là:

- ownership không mơ hồ;
- một runtime và một persisted model cho mỗi domain;
- extension additive;
- CSS precedence có chủ ý;
- UI capability khớp storage/compiler;
- source/build và branch current;
- test cover persistence;
- browser smoke chứng minh save/reload/responsive;
- historical docs không thể khiến contributor hợp lý nào tái tạo kiến trúc đã supersede.

Điều gì chưa biết thì ghi **chưa verify**, không giả định pass.
