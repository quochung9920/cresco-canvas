# PROJECT_RULES.md — Quy tắc bắt buộc của Cresco Canvas

> **Trạng thái:** Canonical cho repository hiện tại.
>
> **Đối tượng:** Developer, reviewer, maintainer và AI Coding Agent.
>
> **Ngôn ngữ tài liệu:** Tiếng Việt. Tên class, function, schema, event, route, handle, CSS variable, JSON key, file path và code literal phải giữ nguyên tiếng Anh.
>
> **Mục tiêu:** Mỗi thay đổi phải mở rộng đúng kiến trúc đang có, không tạo thêm runtime/state/schema/render/CSS owner cạnh tranh và không để UI hứa nhiều hơn backend có thể lưu.

---

## 0. Đọc phần này trước nếu cần làm nhanh

Trước khi sửa code, luôn làm đủ 10 việc sau:

1. Đọc `PROJECT_RULES.md` và `docs/README.md`.
2. Xác định **canonical owner** của phần sắp sửa.
3. Đọc source đang được đăng ký trên `main`; không suy đoán từ ảnh chụp, branch cũ hoặc tên file.
4. Search contract, adapter, registry, migration và test đã tồn tại trước khi tạo mới.
5. Reuse shared primitive trước khi tạo control/service mới.
6. Không tạo source of truth thứ hai.
7. Nếu persisted contract đổi, backend + UI + render/compiler + test + docs phải đổi cùng một change.
8. Nếu runtime/build asset có mirror, cập nhật đồng bộ và kiểm tra browser thực sự load file nào.
9. Chạy check nhỏ nhất liên quan trong lúc phát triển, sau đó chạy gate rộng hơn trước merge/release.
10. Chỉ cập nhật `main` sau khi thay đổi hoàn chỉnh và đã verify ở mức môi trường cho phép.

Nếu chưa biết owner ở đâu, **dừng việc triển khai và tìm owner trước**. Không giải quyết sự mơ hồ bằng cách thêm một layer mới.

---

## 1. Thứ tự authority

Khi code và tài liệu có vẻ mâu thuẫn, dùng thứ tự sau cho Studio hiện tại:

1. Executable code + tests đang chạy trên `main`.
2. ADR hiện hành áp dụng rõ cho Studio-owned document.
3. `PROJECT_RULES.md`.
4. `docs/STUDIO_RUNTIME_OWNERSHIP_AND_CONFLICT_PREVENTION.md`.
5. `docs/CORE_ARCHITECTURE.md` và current feature contract.
6. Compatibility/historical docs trong đúng scope và đúng thời điểm của chúng.

Tài liệu lịch sử không được ghi đè runtime/source hiện tại. Nếu code và canonical docs lệch nhau ngoài ý muốn, đó là defect; sửa code hoặc docs trong cùng change.

---

## 2. Các bất biến kiến trúc không được phá

```text
Một Studio runtime.
Một editable Session document model.
Một canonical render path.
Một responsive inheritance authority.
Một persistence owner cho mỗi setting domain.
Một semantic CSS owner cho mỗi rule.
React sở hữu DOM do React render.
Optional module chỉ additive và có thể degrade độc lập.
Source/build mirror không được drift.
UI không được expose capability mà schema không lưu được.
Release claim luôn cần bằng chứng của đúng commit/artifact.
```

Nếu một giải pháp vi phạm bất kỳ dòng nào ở trên, cần thiết kế lại trước khi merge.

---

## 3. Kiến trúc Core

Cresco Canvas dùng **modular monolith, contract-first**.

Hướng dependency ổn định:

```text
Contracts -> Core -> Application -> Modules / Infrastructure / Presentation
```

- **Contracts:** document, scope, command, transaction, patch, interchange, AI envelope.
- **Core:** document model, resolver, registry, validation, migration, token, responsive inheritance.
- **Application:** export, preview/commit transaction, save, history, component workflow.
- **Rendering:** canonical HTML/CSS thông qua `RenderEngine/v2` và renderer/compiler được chỉ định.
- **Modules:** Theme, Loop, Forms, WooCommerce, Components, AI… đăng ký capability; không fork Core.
- **WordPress infrastructure:** storage, REST, media, capability, WP_Query, WooCommerce, ACF.
- **Presentation:** Studio/Inspector/Page UI là client của Core/Application, không phải persistence authority riêng.

Không tạo `V4`, `V5` hoặc một builder generation song song chỉ để tránh sửa owner hiện tại. Version contract/migration, không nhân bản hệ thống.

---

## 4. Document model và persistence

`cresco-session/v1` là editable Website Builder document model authoritative.

`cresco-document/v1` có thể bọc Session với `documentType`, nhưng không được tạo cây dữ liệu thứ hai cho cùng document.

Page, Theme document, Component, Loop Item, Popup và document type tương lai phải ưu tiên dùng cùng Session/render architecture.

### Không được

- tạo Page document store thứ hai;
- dùng hidden DOM state có thể ghi đè Session sau reload;
- mutate persisted Session ngoài command/transaction đã validate hoặc compatibility path được phê duyệt;
- rewrite/xóa `post_content` chỉ vì Session tồn tại;
- bỏ concurrency/checksum protection nếu repository path đang yêu cầu nó.

`post_content` do người dùng tạo phải được bảo toàn làm fallback.

---

## 5. Mutation path

Mutation từ UI, AI, Import, Clipboard và Component nên hội tụ vào cùng pipeline:

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

`DocumentRepository` sở hữu persistence; `WordPressDocumentRepository` là adapter WordPress hiện tại.

Không để một optional module ghi trực tiếp vào persisted document nếu Core đã có command/transaction path.

---

## 6. Rendering và WYSIWYG

Luồng canonical:

```text
Session + Architecture v2
  -> RenderEngine/v2
  -> canonical renderer/compiler
  -> HTML/CSS
```

Studio preview và frontend phải xuất phát từ cùng normalized Session/Architecture state.

Nếu Studio nhìn đúng nhưng canonical frontend sai, đó vẫn là defect. Không che lỗi renderer bằng Studio-only CSS.

Compatibility renderer chỉ chuyển đổi legacy data; không được trở lại làm frontend owner lâu dài.

---

## 7. Studio runtime ownership

`WebsiteBuilderStudio` là owner canonical của Website Builder runtime hiện tại.

Handle `cresco-canvas-website-builder` được giữ để tương thích, nhưng chỉ một implementation được quyền sở hữu editor screen.

### Bắt buộc

- chỉ một Studio shell;
- required core không phụ thuộc optional presentation module;
- optional feature lỗi chỉ làm feature đó degrade;
- config/endpoints lấy từ server-side owner canonical;
- long-lived branch phải được sync với `main` trước khi debug/merge.

### Cấm

- enqueue runtime core cũ sau Studio;
- mount `.cc-builder-app`/`.cc-studio-app` cạnh tranh;
- đổi canonical handle sang runtime khác để “fallback” khi module phụ lỗi;
- compatibility code mutate canonical handle sau khi ownership đã enforce;
- dựng editor shell thứ hai bằng DOM observer.

Khi startup lỗi, kiểm tra runtime ownership trước CSS.

---

## 8. React DOM và extension

`.cc-studio-*` là implementation selector, không phải API cho phép tái cấu trúc React tree.

Thứ tự ưu tiên khi mở rộng Studio:

1. `window.CrescoStudioSDK`.
2. Event/state bridge được công bố.
3. React portal vào host ổn định.
4. Additive sibling mount point được dành sẵn.
5. DOM enhancer hẹp, idempotent và teardown được — chỉ khi không có extension point phù hợp.

Không `innerHTML`, `replaceChildren`, reparent, clone-and-hide canonical control hoặc dùng DOM input mutation làm state owner trên React-owned content.

`MutationObserver` chỉ dùng để phát hiện/sync presentation hẹp; không được trở thành UI/state owner thứ hai và không được chạy feedback loop vô hạn.

---

## 9. State ownership, reset và responsive

Authority:

1. Server model/sanitizer định nghĩa persisted value hợp lệ.
2. Studio React/Document state định nghĩa editable browser state.
3. DOM phản chiếu state.
4. Optional module chỉ sở hữu transient presentation state trừ khi contract giao rõ quyền khác.

Không để hai module sở hữu cùng một semantic value.

Reset/unset phải xóa persisted override thật sự. Làm input trống không đủ nếu key vẫn còn trong Session/Page Settings.

`ResponsiveResolver` là authority canonical cho responsive inheritance.

Hướng mặc định:

```text
wide base -> desktop -> laptop -> tablet -> mobile
```

Không tạo breakpoint cascade thứ hai trong widget, optional module, AI adapter hoặc compiler.

---

## 10. Inspector, Widget Catalog và shared controls

Inspector phải dựa trên schema/capability từ `WidgetCatalog` / `InspectorSchema` và backend contract tương ứng.

Trước khi thêm control:

1. kiểm tra capability/schema;
2. kiểm tra validation/sanitization;
3. kiểm tra renderer/compiler;
4. kiểm tra responsive/state semantics;
5. kiểm tra AI/interchange nếu liên quan;
6. thêm test;
7. verify save -> reload.

Không tạo Inspector riêng cho từng widget nếu shared primitive đáp ứng được.

### Dimension/unit

Chỉ expose `px`, `%`, `em`, `rem`, `vw`, `vh`, `vmin`, `vmax`, `ch`, keyword hoặc `custom` khi storage + sanitizer + compiler của domain thực sự hỗ trợ.

`custom` không được trở thành đường bypass sanitizer.

### Border/Radius

Widget Border/Radius dùng style key canonical. Shared Border UI có thể proxy control hiện có nhưng không tạo state riêng.

Không copy Widget Border control sang Page Settings nếu Page Settings backend chưa có schema tương ứng.

### Typography Popup

`StudioTypographyPopup` chỉ là presentation layer cho Typography controls canonical.

Popup không được tạo typography store riêng. Responsive selector, dimension/unit, state override, reset và save vẫn phải đi qua Session/control owner hiện tại.

Property mới như `wordSpacing` chỉ được hiện khi allow-list/schema/compiler đã hỗ trợ.

---

## 11. Page Settings

`includes/Page/PageSettings.php` là persistence/model owner của Page Settings.

Mọi Page Settings UI chỉ là view của cùng backend model.

### Spacing contract hiện tại

- bucket: `desktop`, `tablet`, `mobile`;
- side: `top`, `right`, `bottom`, `left`;
- một shared unit cho Margin;
- một shared unit cho Padding;
- có `linked` flag;
- unit hiện hỗ trợ: `px`, `%`, `em`, `rem`, `vh`, `vw`.

Không fake per-side/per-breakpoint unit hoặc `custom` nếu backend chưa hỗ trợ.

Persisted Page Settings feature phải đổi atomic:

```text
defaults
-> sanitizer/validation
-> inheritance/effective values
-> compiler/frontend
-> REST
-> Studio UI
-> alternate UI
-> AI/import-export nếu có
-> tests
-> save/reload verification
-> docs
```

CSS-only patch không phải schema upgrade.

---

## 12. Global Design, token và Custom CSS

Global settings được lưu trong `cresco_canvas_settings` đã validate.

Ưu tiên authoring:

```text
Global Design token
-> widget prop
-> structured widget style
-> scoped Custom CSS
```

Không tạo site-token store thứ hai.

Known token reference nên compile thành `--cc-*` variable ổn định.

Custom CSS là fallback chính thức nhưng không thay structured controls. Widget Custom CSS phải scoped và server-validated. Responsive Custom CSS dùng Cresco device bucket; không tự tạo responsive engine khác.

---

## 13. CSS cascade và motion

`assets/css/cresco-foundation.css` khai báo layer order canonical và phải load trước stylesheet mở Cresco layer.

```css
@layer cresco.base, cresco.tokens, cresco.components, cresco.theme, cresco.overrides, cresco.motion;
```

- `cresco.base`: cấu trúc/primitives.
- `cresco.tokens`: token/alias canonical.
- `cresco.components`: reusable component presentation.
- `cresco.theme`: theme-level presentation.
- `cresco.overrides`: visual polish cuối; không sở hữu logic.
- `cresco.motion`: timing/micro-motion.

Một semantic rule nên có một owner. Không copy selector sang file sau chỉ để thắng specificity.

Trước `!important`, kiểm tra owner, layer, source order và specificity.

`website-builder-premium-polish.css` chỉ presentation. Nếu UI cần polish CSS để hoạt động, rule đang nằm sai owner.

Motion dùng token `--cc-motion-*` trong foundation và phải tôn trọng `prefers-reduced-motion: reduce`.

---

## 14. Source/build ownership

Checked-in runtime/build assets là một phần của product/release process.

Nếu source/runtime mirror được quy định byte-identical, cập nhật cùng nhau. Không hand-edit build rồi để source stale hoặc ngược lại.

Sau thay đổi runtime/build:

- chạy build-integrity check;
- xác nhận browser enqueue file nào;
- xác nhận version/hash thay đổi đúng;
- kiểm tra manifest/allowlist nếu asset mới được thêm;
- không giả định source edit đồng nghĩa browser đang chạy source đó.

`WebsiteBuilderModuleRegistry` là catalog authoritative cho browser modules.

Optional module phải additive, fail độc lập, có diagnostics, không takeover runtime/state/rendering và listener/observer phải bounded + teardown được.

---

## 15. AI / interchange / import-export

AI dùng cùng Session/contract với editor, không có builder schema riêng.

Scope ổn định gồm `widget`, `subtree`, `selection`, `document`.

AI/import output phải normalize + validate trước Apply và không bypass server sanitizer.

Apply không đồng nghĩa Save trừ khi workflow ghi rõ. Persistence vẫn là action riêng của editor/user.

Ưu tiên `cresco-patch/v1` / command / transaction cho mutation có scope.

AI response không được mutate ngoài scope đã export.

---

## 16. WordPress, security và privacy

Dùng WordPress API và service boundary hiện có.

Server write path phải có phù hợp:

- capability check;
- REST auth/nonce semantics;
- validation;
- sanitization;
- output escaping;
- permission callback;
- storage/database API an toàn;
- slash-safe JSON khi contract yêu cầu.

Không sửa WordPress Core hoặc core plugin bên thứ ba.

Không rải persistence call qua Core/Application nếu repository/infrastructure port đã sở hữu trách nhiệm đó.

Security, privacy, accessibility, compatibility và data-safety là release gates; không phải polish tùy chọn.

---

## 17. Accessibility và performance

Phải giữ hoặc cải thiện keyboard access, `:focus-visible`, accessible name, semantic role/state, contrast, touch target và reduced motion.

Không xóa focus outline nếu không có visible replacement tương đương.

Đặc biệt kiểm tra performance/reliability với:

- MutationObserver;
- scroll/resize listener;
- event-loop stall;
- repeated render/compile;
- Session lớn;
- duplicate CSS/JS owner;
- startup request thiếu timeout/bound.

Optional module lỗi không được chặn Session load hoặc Studio mount.

---

## 18. Branch discipline và cách merge

Trước runtime/UI/core architecture work:

1. kiểm tra branch base với `main`;
2. sync/rebase/fast-forward trước khi sửa stale code;
3. triển khai trên branch nhỏ, reviewable;
4. verify relevant checks;
5. re-check `main` chưa di chuyển ngoài ý muốn;
6. chỉ fast-forward/merge khi branch hoàn chỉnh;
7. sync long-lived branch sau canonical change.

Không force-update `main` để né conflict.

Không debug stale branch như thể đó là `main` hiện tại.

---

## 19. Quy tắc refactor và xóa code

Refactor phải cải thiện ít nhất một trong: maintainability, reuse, accessibility, performance, consistency, reliability, security hoặc ownership clarity.

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

Trước khi xóa code, search registration/hook, runtime/module registry, source/build mirror, REST/contract, test/release allowlist, compatibility adapter/migration và docs/ADR.

Nếu ownership chưa rõ, ghi technical debt thay vì xóa mù.

---

## 20. Những anti-pattern bị cấm

Không tạo:

- builder framework thứ hai;
- Session/document schema thứ hai cho cùng bài toán;
- token system thứ hai;
- responsive engine thứ hai;
- Inspector architecture thứ hai;
- Page Settings backend thứ hai;
- frontend render pipeline thứ hai;
- persistence store thứ hai cho cùng setting;
- DOM state có quyền ghi đè canonical state;
- dependency lớn để giải quyết một vấn đề local nhỏ;
- CSS override layer mới chỉ để che owner sai.

Ưu tiên:

```text
Existing contract -> shared primitive -> compatibility adapter -> migration
```

---

## 21. Checklist trước khi sửa

```text
[ ] Đã đọc PROJECT_RULES.md và docs/README.md.
[ ] Đã xác định canonical owner.
[ ] Đã đọc source hiện tại.
[ ] Đã search contract/adapter/test hiện có.
[ ] Branch đang đồng bộ với main.
[ ] Không tạo source of truth thứ hai.
[ ] Không tạo runtime/render/state system cạnh tranh.
[ ] UI capability khớp persistence/validation.
[ ] Responsive dùng resolver canonical.
[ ] Đã hiểu CSS layer/load order/specificity.
[ ] Không takeover React-owned DOM.
[ ] Đã xác định source/build mirror.
[ ] Đã đánh giá accessibility/performance/security/compatibility.
```

---

## 22. Checklist sau khi sửa

```text
[ ] Syntax/type/lint liên quan pass.
[ ] Unit/PHP test liên quan pass.
[ ] Runtime/architecture ownership checks pass.
[ ] Source/build integrity pass khi áp dụng.
[ ] Không có Studio root/runtime cạnh tranh.
[ ] Không phát sinh console/PHP error trong flow bị ảnh hưởng.
[ ] Save -> reload giữ đúng model change.
[ ] Reset/unset xóa persisted override thật sự.
[ ] Canonical frontend/render khớp Studio intent.
[ ] Responsive được verify ở breakpoint liên quan.
[ ] Keyboard/focus/accessibility được verify.
[ ] Optional module failure vẫn degrade an toàn.
[ ] Docs/ADR/contract được cập nhật nếu semantics đổi.
[ ] Release claim không mạnh hơn bằng chứng hiện có.
```

---

## 23. Quality commands

Trong lúc phát triển, chạy check nhỏ nhất có liên quan. Trước merge/release, dùng gate rộng hơn.

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

`npm run check:quality` là gate static/unit tổng hợp chính.

Browser, accessibility, performance, exact-ZIP install, upgrade/rollback và compatibility matrix là gate riêng. **Skip/chưa chạy không được tính là pass.**

---

## 24. Release rule

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

Không đưa source/test/secret/dev tooling vào production ZIP ngoài manifest/allowlist được release process cho phép.

Cresco Canvas hiện là release candidate. Không gọi stable/commercially ready nếu thiếu exact-artifact evidence cho đúng release commit/ZIP.

---

## 25. Hành vi bắt buộc của AI Coding Agent

AI Coding Agent phải:

1. đọc rule + current source trước khi làm;
2. search trước khi tạo mới;
3. reuse trước khi duplicate;
4. patch đúng owner trước khi rewrite;
5. giữ compatibility trừ khi migration được định nghĩa rõ;
6. đổi persisted contract theo kiểu atomic;
7. không tuyên bố persistence đã sửa nếu chưa verify save/reload;
8. không tuyên bố UI đã sửa chỉ dựa trên model change;
9. không dùng stale docs để override runtime registration;
10. không giải quyết conflict bằng system/layer cạnh tranh;
11. ghi rõ phần nào chưa verify;
12. cập nhật docs/ADR khi semantics hoặc ownership thay đổi.

---

## 26. Definition of Done

Một thay đổi chỉ được coi là hoàn tất khi:

- đúng canonical owner;
- không tạo source of truth thứ hai;
- contract/backend/UI/render đồng bộ nếu có persistence;
- source/build/manifest đồng bộ nếu có runtime asset;
- test/check liên quan đã chạy hoặc phần chưa chạy được ghi rõ;
- save/reload/responsive/accessibility được kiểm tra ở mức phù hợp;
- docs cập nhật nếu behavior/architecture đổi;
- branch có thể merge vào `main` mà không cần force hoặc che conflict.

Nếu chưa đạt các điều trên, thay đổi vẫn là **work in progress**, không phải hoàn tất.