# Quy tắc dự án Cresco Canvas

> **Phạm vi:** Quy tắc kỹ thuật áp dụng cho toàn bộ repository Cresco Canvas, dành cho developer và AI Coding Agent.
>
> **Mục tiêu:** Giữ kiến trúc, runtime, dữ liệu, CSS, build artifact, kiểm thử và tài liệu đồng bộ trong suốt quá trình phát triển.
>
> **Ưu tiên:** Yêu cầu trực tiếp của người dùng có ưu tiên cao nhất. Nếu yêu cầu xung đột với contract kiến trúc canonical hoặc có nguy cơ gây mất dữ liệu/phá tương thích, phải nêu rõ rủi ro trước khi triển khai.

---

## 1. Đọc trước khi sửa code

Trước khi chỉnh Cresco Canvas:

1. Đọc file này.
2. Đọc tài liệu canonical của subsystem đang sửa.
3. Đọc source hiện tại; không suy đoán từ ảnh chụp, branch cũ hoặc tên class cũ.
4. Tìm owner, adapter, test, migration và compatibility path đã tồn tại.
5. Mở rộng contract hiện có thay vì tạo một hệ thống song song.
6. Chỉ sửa bề mặt nhỏ nhất đủ an toàn.
7. Chạy các lệnh kiểm tra liên quan sau khi thay đổi.

Không được dùng tài liệu lịch sử để phủ định runtime hoặc source đang thực sự được đăng ký trên `main`.

---

## 2. Thực trạng dự án

Cresco Canvas là visual website builder cho WordPress, sử dụng Studio độc lập và document model có thể đọc/trao đổi với AI.

Baseline hiện tại:

- Package: `1.0.0-rc.1`
- WordPress: `6.7+`
- PHP: `8.1+`
- Node.js: `20.19+`
- npm: `10+`
- Composer: `2`
- License: GPL-2.0-or-later

Với Page đã có Cresco document hợp lệ, `cresco-session/v1` là nguồn dữ liệu hình ảnh/chỉnh sửa chính. WordPress vẫn là host cho authentication, capability, media, routing, REST, Page record, preview và frontend delivery.

`post_content` do người dùng tạo phải được bảo toàn làm fallback. Không được rewrite/xóa `post_content` chỉ vì Session tồn tại.

Đây vẫn là release candidate. Không được mô tả là stable/commercially ready nếu chưa có bằng chứng cho đúng release commit và đúng ZIP.

---

## 3. Kiến trúc canonical

Cresco Canvas dùng kiến trúc **modular monolith, contract-first**.

Hướng dependency ổn định:

```text
Contracts -> Core -> Application -> Modules / Infrastructure / Presentation
```

Trách nhiệm chính:

- **Contracts:** document, scope, command, transaction, patch, interchange và AI envelope có tính portable.
- **Core:** document model, scope/context, command/patch, responsive inheritance, design token, Widget Registry, Inspector/UI Registry, dependency policy và migration.
- **Application:** export, transaction preview/commit, render preview, save, history, component workflow.
- **Rendering:** một ranh giới HTML/CSS thông qua `RenderEngine/v2` và compiler/renderer canonical.
- **Modules:** Theme, Loop, Forms, WooCommerce, Components, AI và module tương lai đăng ký capability; không sửa contract Core tùy tiện.
- **WordPress infrastructure:** storage, REST, media, user/capability, WP_Query, WooCommerce, ACF.
- **Editor presentation:** là client của Core/Application, không phải persistence authority riêng.

### Quy tắc cứng

Không tạo builder generation song song như `V4`, `V5` hoặc một document/render/state stack mới cho cùng bài toán.

Version contract và migration, không version hóa bằng cách nhân bản service.

---

## 4. Một document model

`cresco-session/v1` là editable Website Builder document model authoritative.

`cresco-document/v1` có thể bọc Session với `documentType` mà không ép migration phá dữ liệu.

Page, Theme document, Component, Loop Item, Popup và các document type tương lai phải ưu tiên dùng cùng cây Session + rendering architecture.

Không được:

- tạo page document store thứ hai;
- giữ hidden DOM state có thể ghi đè Session sau reload;
- mutate persisted Session ngoài command/transaction đã validate hoặc compatibility save path được phê duyệt;
- rewrite `post_content` như side effect của Session save.

---

## 5. Một mutation path

Mutation mới từ UI, AI, Import, Clipboard và Component nên hội tụ vào:

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

`DocumentRepository` sở hữu persistence. `WordPressDocumentRepository` là adapter WordPress hiện tại.

Khi có concurrency protection, phải giữ checksum/`ifMatch` semantics. Không được âm thầm ghi đè document mới hơn.

---

## 6. Rendering canonical và WYSIWYG

Luồng hình ảnh canonical:

```text
Session + Architecture v2
  -> RenderEngine/v2
  -> WebsiteRendererV2
  -> root styles + Part styles + Component styles
  -> HTML/CSS
```

Studio preview/iframe và frontend phải xuất phát từ cùng normalized Session/Architecture state.

Không tạo frontend renderer hoặc CSS compiler cạnh tranh với Core Platform v2.

Compatibility renderer chỉ được chuyển đổi legacy data; không được lấy lại ownership frontend lâu dài.

Nếu Studio preview khác canonical render output thì đó là defect. Không che lỗi bằng Studio-only CSS.

---

## 7. Ownership của Studio runtime

`WebsiteBuilderStudio` là owner canonical của Website Builder runtime đang hoạt động.

Handle `cresco-canvas-website-builder` được giữ để tương thích; implementation bên dưới có thể phát triển.

Invariant bắt buộc:

- chỉ một Studio shell sở hữu editor screen;
- optional module không thay thế core runtime;
- optional feature lỗi chỉ làm feature đó degrade;
- runtime context/endpoints lấy từ owner server-side canonical;
- branch UI/runtime dài hạn không được âm thầm tụt sau `main`.

Không được:

- enqueue runtime core cũ sau Studio;
- mount `.cc-builder-app` hoặc `.cc-studio-app` cạnh tranh;
- đổi canonical handle sang runtime khác để “fallback” khi module phụ lỗi;
- để compatibility code mutate canonical handle sau khi ownership đã được enforce;
- dùng DOM observer dựng một editor shell thứ hai quanh Studio.

Khi debug startup, kiểm tra runtime ownership trước khi debug CSS.

---

## 8. React sở hữu DOM do React render

`.cc-studio-*` là implementation selector, không phải API cho phép tái cấu trúc React tree.

Thứ tự ưu tiên khi mở rộng Studio:

1. `window.CrescoStudioSDK`.
2. Event/state bridge được Studio công bố.
3. React portal vào host ổn định.
4. Additive sibling mount point được dành sẵn.
5. DOM enhancer hẹp, chỉ khi không có extension point phù hợp.

Không dùng trên React-owned content nếu chưa được delegate ownership:

```text
innerHTML = ...
replaceWith(...)
replaceChildren(...)
remove React-owned parent
reparent canonical controls
clone control rồi ẩn bản gốc
đổi DOM input rồi giả định React state đã đổi
MutationObserver loop không giới hạn
```

`MutationObserver` chỉ nên dùng để phát hiện mount point, không trở thành UI owner thứ hai. Observer phải hẹp, idempotent, coalesced/debounced và teardown được.

---

## 9. Ownership của state

Thứ tự authority:

1. Server model/sanitizer định nghĩa persisted state hợp lệ.
2. Studio React state định nghĩa editable browser state hiện tại.
3. DOM phản chiếu state, không phải source of truth cạnh tranh.
4. Local state của optional module chỉ dùng cho presentation/transient state trừ khi contract giao ownership rõ ràng.

Không để hai module sở hữu độc lập cùng một semantic value.

Reset/unset là model operation. Xóa trắng input không đủ nếu override key vẫn tồn tại trong Session/Page Settings.

Với responsive control phải định nghĩa rõ:

- cách hiển thị inherited value;
- explicit value so với missing override;
- reset/unset;
- parent/base resolution;
- save/reload verification.

---

## 10. Responsive contract

`ResponsiveResolver` là authority canonical cho responsive inheritance ở Core Platform.

Hướng mặc định:

```text
wide base -> desktop -> laptop -> tablet -> mobile
```

Viewport nhỏ nhận các bucket lớn hơn theo source order rồi áp override cụ thể hơn.

Không viết breakpoint cascade thứ hai trong widget, optional module, AI adapter hoặc CSS compiler.

Preview width và effective value phải dùng cùng responsive contract đã publish.

---

## 11. Inspector, Widget Catalog và control dùng chung

Inspector được điều khiển bằng schema từ `WidgetCatalog` / `InspectorSchema`.

Một control chỉ nên xuất hiện khi widget contract khai báo capability đó; validator/sanitizer và renderer/compiler phải dùng cùng contract.

Trước khi thêm control:

1. Kiểm tra `WidgetCatalog` capability/schema.
2. Kiểm tra validation/sanitization.
3. Kiểm tra renderer/compiler.
4. Kiểm tra responsive/state semantics.
5. Kiểm tra AI/interchange nếu liên quan.
6. Thêm test.

Không tạo Inspector riêng theo từng widget nếu có thể dùng shared primitive.

### Dimension và unit

Dimension control phải dùng primitive/adapter hiện có khi contract tương thích. Các unit như `px`, `%`, `em`, `rem`, `vw`, `vh`, `vmin`, `vmax`, `ch`, keyword hoặc `custom` chỉ được expose khi storage/validator/compiler của domain đó thực sự hỗ trợ.

Không được biến `custom` thành đường bypass sanitizer.

### Border và Border Radius

Widget Border/Radius dùng các style key canonical của Session. Shared Border UI được phép proxy control hiện có nhưng không được tạo một state khác.

Không copy Widget Border control sang Page Settings nếu Page Settings backend chưa có schema tương ứng.

### Typography popup

`StudioTypographyPopup` là presentation layer cho các Typography control canonical; nó không được tạo typography state/store thứ hai. Responsive selector, dimension/unit, state override, reset và save vẫn phải đi qua control/Session owner hiện tại.

Khi thêm property như `wordSpacing`, phải thêm vào contract/style allow-list và compiler trước hoặc cùng lúc với UI.

---

## 12. Page Settings contract

`includes/Page/PageSettings.php` là persistence/model owner của Page Settings.

Studio Page panel và UI Page Settings khác chỉ là các view trên cùng backend model; semantics persistence không được lệch nhau.

### Constraint spacing hiện tại

Page Settings v2 dùng:

- bucket `desktop`, `tablet`, `mobile`;
- `top`, `right`, `bottom`, `left`;
- một shared unit cho toàn bộ Margin;
- một shared unit cho toàn bộ Padding;
- `linked` flag;
- unit gồm `px`, `%`, `em`, `rem`, `vh`, `vw`.

Không expose per-side/per-breakpoint unit cho đến khi defaults, sanitizer, compiler, REST, migration, test và toàn bộ UI surface hỗ trợ.

Không tạo UI-only `custom` unit rồi giả định persistence lưu được.

### Thay đổi Page Settings phải atomic

```text
defaults
-> sanitizer/validation
-> inheritance/effective values
-> compiler/frontend
-> REST
-> Studio Page UI
-> alternate Page Settings UI
-> AI/import-export nếu có
-> tests
-> save/reload browser verification
-> docs
```

CSS-only patch không phải schema upgrade.

---

## 13. Global Design và design token

Global settings được lưu trong dữ liệu `cresco_canvas_settings` đã validate.

Ưu tiên thiết kế:

1. Global Design token.
2. Widget prop.
3. Structured widget style.
4. Scoped Custom CSS cho trường hợp còn lại.

Known token reference compile thành `--cc-*` CSS variable ổn định.

Không tạo site-token store thứ hai hoặc copy canonical design state vào optional module.

Token usage/replacement phải đi qua Design System/Core contract thay vì scan HTML tùy tiện.

---

## 14. Custom CSS

Custom CSS là fallback chính thức, không phải thay thế structured controls.

Widget Custom CSS phải scoped theo widget contract và được server validate.

Không đưa một capability có tính design-system vào Custom CSS chỉ vì triển khai nhanh hơn.

Responsive Custom CSS phải dùng Cresco device bucket, không tự sở hữu media-query system song song.

Các pattern nguy hiểm/out-of-contract như global selector, resource-loading, JavaScript/expression, markup escape phải bị chặn theo sanitizer hiện tại.

---

## 15. CSS cascade và ownership

`assets/css/cresco-foundation.css` là nơi khai báo layer order canonical và phải xuất hiện trước các stylesheet mở Cresco layer.

Thứ tự hiện tại trên `main`:

```css
@layer cresco.base, cresco.tokens, cresco.components, cresco.theme, cresco.overrides, cresco.motion;
```

Trách nhiệm:

- `cresco.base`: cấu trúc/primitives nền tảng.
- `cresco.tokens`: token và alias canonical.
- `cresco.components`: presentation của reusable component.
- `cresco.theme`: theme-level presentation khi có.
- `cresco.overrides`: visual polish cuối cùng, không sở hữu logic/cấu trúc.
- `cresco.motion`: timing và micro-motion, luôn tôn trọng reduced motion.

Layer không loại bỏ conflict trong cùng layer; source order và specificity vẫn có hiệu lực.

Quy tắc:

- một semantic rule nên có một owner;
- không copy selector sang file sau chỉ để “ép thắng”;
- sửa đúng owner nếu có thể;
- kiểm tra layer, load order, specificity và duplicate owner trước khi dùng `!important`;
- `website-builder-premium-polish.css` chỉ presentation;
- nếu polish CSS cần thiết để UI hoạt động, rule đó đang nằm sai owner.

---

## 16. Motion system

Motion dùng token canonical trong `cresco-foundation.css`:

- `--cc-motion-instant`
- `--cc-motion-fast`
- `--cc-motion-base`
- `--cc-motion-slow`
- các easing token canonical.

Không hard-code nhiều timing/easing khác nhau cho cùng interaction family nếu token đã tồn tại.

Không animate layout lớn hoặc site content chỉ vì Studio chrome có micro-motion.

`prefers-reduced-motion: reduce` phải được tôn trọng.

---

## 17. Source/build ownership

Checked-in runtime/build asset là một phần của product/release process.

Nếu source/runtime mirror được quy định byte-identical thì phải cập nhật cùng nhau. Không hand-edit một bản rồi để bản canonical còn lại stale.

Sau thay đổi runtime/build:

- chạy build-integrity check;
- kiểm tra browser thực sự enqueue file nào;
- kiểm tra content-hashed version thay đổi đúng;
- không giả định source edit đồng nghĩa browser đang chạy source đó.

---

## 18. Runtime module

`WebsiteBuilderModuleRegistry` là catalog authoritative cho required/optional browser module.

Required module không được phụ thuộc optional presentation module.

Optional module phải:

- additive;
- fail độc lập;
- có diagnostics hữu ích;
- không takeover state/runtime/rendering core;
- listener/observer có giới hạn;
- có guard/teardown.

Các Studio service đăng ký trực tiếp như Dimension Controls, Typography Popup, Widget State Tabs, Structure Layout, UX Pro, Color Harmony và Global Design Pro vẫn phải tuân thủ cùng ownership contract và không tạo runtime thứ hai.

---

## 19. AI, interchange, import/export

AI dùng cùng document/contract với editor, không có builder schema riêng.

Scope ổn định bao gồm:

- `widget`
- `subtree`
- `selection`
- `document`

Các compatibility scope như `selection-subtrees` chỉ được dùng khi resolver hỗ trợ.

AI/import output phải validate trước khi Apply và không được bypass server sanitization.

Apply không tự động Save trừ khi workflow được document rõ ràng. Persistence vẫn là hành động editor/user riêng.

Ưu tiên `cresco-patch/v1`/command/transaction cho mutation có scope.

AI response không được mutate ngoài scope boundary đã export.

---

## 20. WordPress/PHP

Dùng WordPress API và service boundary của dự án.

Write path phía server phải áp dụng phù hợp:

- capability checks;
- WordPress REST authentication/nonce semantics;
- validation;
- sanitization;
- output escaping;
- permissioned REST callback;
- storage/database API an toàn;
- slash-safe JSON khi contract yêu cầu.

Không sửa WordPress Core hoặc core của plugin bên thứ ba.

Không rải WordPress persistence call qua Core/Application nếu `DocumentRepository` hoặc infrastructure port đã sở hữu trách nhiệm đó.

---

## 21. JavaScript/TypeScript

Ưu tiên Studio/Core registry và API hơn coupling trực tiếp với DOM.

Không tạo global mutable state nếu Session/Studio state đã có contract tương ứng.

Tránh:

- DOM query lặp trong hot loop;
- duplicate listener;
- scroll/resize handler không giới hạn;
- observer feedback loop;
- optional module block core startup;
- DOM mutation thay cho state mutation.

Khi sửa Canvas, Inspector, Structure, Page, Global Design hoặc startup, phải xác định owner trước.

---

## 22. Accessibility

Accessibility là release gate, không phải polish tùy chọn.

Phải giữ hoặc cải thiện:

- keyboard access;
- `:focus-visible`;
- accessible/semantic control name;
- panel/navigation semantics;
- touch target;
- contrast;
- reduced motion;
- screen-reader state cho expandable/selectable control;
- keyboard alternative cho drag/drop khi cần.

Không xóa focus outline nếu không có visible replacement tương đương.

Configured accessibility test không đồng nghĩa exact release build đã pass.

---

## 23. Performance và reliability

Không làm regression startup, responsiveness, memory hoặc frontend output.

Đặc biệt kiểm tra:

- MutationObserver;
- event-loop stall;
- repeated render/compile;
- Session lớn;
- optional module không cần thiết;
- duplicate CSS/JS owner;
- repeated DOM mutation;
- startup request thiếu timeout/bound.

Optional module lỗi không được ngăn Session load hoặc Studio mount.

---

## 24. Cấu trúc repository và entry point quan trọng

```text
cresco-canvas.php        Plugin bootstrap
includes/                PHP Core/Application/Infrastructure/Runtime
contracts/               Machine-readable contracts ổn định
src/                     Source compile cho editor/block
runtime-src/             Runtime source/mirror canonical khi áp dụng
build/                   Checked-in production/runtime assets
assets/css/              Studio/frontend/module CSS
docs/                     Kiến trúc, behavior, release, audit, policy
scripts/                  Build/check/release verification
tests/                    Unit/integration/e2e/release evidence
```

Không giả định file có tên `legacy`, `V2`, `V3` là canonical; phải kiểm tra registration và ownership docs.

---

## 25. Authority của tài liệu

Với Studio/Core hiện tại, tối thiểu đọc:

- `README.md`
- `PROJECT_RULES.md`
- `docs/README.md`
- `docs/CORE_ARCHITECTURE.md`
- `docs/STUDIO_RUNTIME_OWNERSHIP_AND_CONFLICT_PREVENTION.md`
- `docs/STUDIO_EDITOR_EXPERIENCE_2.md`
- `docs/DECISIONS.md`
- `docs/CRESCO_SESSION_V1.md`
- contract subsystem liên quan.

`docs/ARCHITECTURE.md`, audit cũ, roadmap cũ và release report cũ có thể là historical evidence. Chúng không được ghi đè code/runtime/ADR hiện tại nếu scope đã bị supersede.

Khi architecture thay đổi có chủ ý, cập nhật ADR/contract trong cùng change.

---

## 26. Branch discipline

Trước runtime/UI/core architecture work:

1. Kiểm tra branch base so với `main`.
2. Fast-forward/rebase/merge phù hợp trước khi sửa stale code.
3. Sau khi canonical change vào `main`, sync lại long-lived branch.
4. Chạy lại ownership/build checks sau sync.

Không debug regression từ stale branch như thể đó là `main` hiện tại.

---

## 27. Quy tắc refactor

Không refactor chỉ vì cấu trúc khác “đẹp hơn”.

Refactor có ý nghĩa phải cải thiện ít nhất một trong:

- maintainability;
- reuse;
- accessibility;
- performance;
- consistency;
- reliability;
- security;
- ownership clarity.

Refactor lớn cần ghi rõ:

```text
Problem
Root cause
Canonical owner
Proposed change
Compatibility impact
Affected contracts
Migration path
Regression risk
Verification
```

Ưu tiên consolidation phía sau Core API hiện có hơn rewrite.

---

## 28. Quy tắc xóa code

Không xóa code chỉ vì một search không thấy dùng.

Trước khi xóa:

1. Search PHP registration/hook.
2. Search runtime/module registry.
3. Search build/source mirror.
4. Search REST route/contract.
5. Search test/release allowlist.
6. Search compatibility adapter/migration.
7. Search docs/ADR.

Nếu ownership chưa rõ, ghi technical debt thay vì xóa mù.

---

## 29. Không over-engineer

Không tạo:

- builder framework thứ hai;
- Session/document schema thứ hai cho cùng bài toán;
- token system thứ hai;
- responsive engine thứ hai;
- Inspector architecture thứ hai;
- Page Settings backend thứ hai;
- frontend render pipeline thứ hai;
- dependency lớn cho một vấn đề local nhỏ;
- rewrite stable contract chỉ vì style code.

Ưu tiên:

```text
Existing contract -> shared primitive -> compatibility adapter -> migration
```

---

## 30. Checklist trước khi sửa

```text
[ ] Đã đọc PROJECT_RULES.md.
[ ] Đã xác định canonical owner.
[ ] Đã đọc source hiện tại, không chỉ docs/screenshot.
[ ] Đã search contract/adapter/test hiện có.
[ ] Branch đang đồng bộ phù hợp với main.
[ ] Không tạo source of truth thứ hai.
[ ] Không tạo runtime/render/state system cạnh tranh.
[ ] UI capability khớp backend persistence/validation.
[ ] Responsive dùng resolver/contract canonical.
[ ] Đã hiểu CSS layer/load order/specificity/owner.
[ ] Không takeover React-owned DOM.
[ ] Đã xác định source/build mirror phải đồng bộ.
[ ] Đã đánh giá accessibility.
[ ] Đã đánh giá performance/startup.
[ ] Đã đánh giá compatibility/migration.
```

---

## 31. Checklist sau khi sửa

```text
[ ] Syntax/type/lint liên quan pass.
[ ] Unit/PHP test liên quan pass.
[ ] Architecture/runtime ownership checks pass.
[ ] Source/build integrity pass khi áp dụng.
[ ] Không có Studio root/runtime cạnh tranh.
[ ] Không phát sinh console/PHP error trong flow bị ảnh hưởng.
[ ] Save -> reload giữ đúng model change.
[ ] Reset/unset thay đổi persisted override thật sự.
[ ] Canonical renderer/frontend khớp Studio intent.
[ ] Responsive desktop/tablet/mobile được verify.
[ ] Keyboard/focus/accessibility được verify.
[ ] Optional module failure vẫn degrade an toàn.
[ ] Docs/ADR/contract được cập nhật nếu architecture đổi.
[ ] Release claim không mạnh hơn bằng chứng hiện có.
```

---

## 32. Lệnh kiểm tra chất lượng

Dùng check nhỏ nhất trong lúc phát triển, sau đó dùng check rộng hơn trước merge/release.

```bash
npm ci
composer install
npm run build
npm run typecheck
npm run lint:php
npm run lint:runtime
npm run lint:js
npm run lint:css
npm run lint:md
npm run test:unit
npm run test:php
npm run check:hygiene
npm run check:editor-runtime
npm run check:website-builder
npm run check:startup-hardening
npm run check:runtime-modules
npm run check:studio
npm run check:studio-ui
npm run check:studio-premium
npm run check:studio-motion
npm run check:studio-unset-styles
npm run check:canonical-preview-owner
npm run check:known-defects
npm run check:comprehensive-v3
npm run check:architecture
npm run check:production-hardening
npm run check:build-integrity
npm run check:version
```

`npm run check:quality` gom các static/unit quality gate chính của repository.

Browser, accessibility, performance, exact-ZIP install, upgrade/rollback và compatibility matrix là gate riêng. Check bị skip/chưa chạy không được tính là pass.

---

## 33. Quy tắc release

Release package phải deterministic và dùng strict allowlist.

Flow điển hình:

```bash
composer install --no-dev --optimize-autoloader
rm -rf build
npm run build
npm run check:build-integrity
npm run package
node scripts/verify-package.mjs
```

Không thêm source/test/secret/dev tooling vào production ZIP ngoài release ownership manifest/allowlist.

Không gọi RC là commercially ready nếu thiếu exact-artifact evidence được release docs yêu cầu.

---

## 34. Hành vi bắt buộc của AI Coding Agent

Mọi AI Coding Agent làm việc trong repository này phải:

1. Đọc file này trước.
2. Đọc source trước khi giả định.
3. Search trước khi tạo mới.
4. Reuse trước khi duplicate.
5. Tôn trọng canonical ownership.
6. Patch trước khi rewrite toàn bộ.
7. Giữ compatibility trừ khi migration được định nghĩa rõ.
8. Khi persisted contract đổi, backend/UI/render/test/docs phải thay đổi atomic.
9. Không tuyên bố visual patch đã sửa persistence nếu chưa verify save/reload.
10. Không tuyên bố model change đã sửa UI nếu chưa có browser evidence.
11. Không dùng stale docs để override runtime registration/source hiện tại.
12. Không giải quyết conflict kiến trúc bằng cách thêm một layer/system cạnh tranh.
13. Claim chưa verify phải ghi rõ là chưa verify.
14. Nếu quy tắc kỹ thuật toàn dự án thay đổi có chủ ý, cập nhật file này.

---

## 35. Tóm tắt bất biến không được phá

```text
Một Studio runtime.
Một Session document model.
Một canonical render path.
Một responsive inheritance authority.
Một backend owner cho mỗi persisted setting domain.
Một semantic CSS owner cho mỗi rule.
React sở hữu DOM do React render.
Optional module chỉ additive và có thể degrade độc lập.
Source/build mirror không được drift.
UI không được hứa nhiều hơn schema lưu được.
Architecture docs phải khớp code hiện tại.
Branch không được âm thầm drift khỏi main.
Release claim luôn cần bằng chứng.
```
