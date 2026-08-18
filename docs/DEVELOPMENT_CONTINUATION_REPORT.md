# Cresco Canvas — Báo cáo Runtime Consolidation và tiếp tục phát triển

> **Tài liệu lịch sử theo branch/snapshot.**  
> **Branch:** `refactor/runtime-consolidation`  
> **Base:** `main` tại `d44d289f27b99ba42586f9ac801d10d8f468f3f1`  
> **Plugin version:** `1.0.0-rc.1`  
> **Date:** 2026-08-11

## 1. Mục tiêu

Refactor này làm Cresco Canvas dễ hiểu hơn và an toàn hơn khi mở rộng mà không thay `cresco-session/v1` hoặc xóa compatibility behavior quá sớm.

Mục tiêu kiến trúc cốt lõi:

> một document model, một mutation path, một render authority, một editor core, một runtime registry và các optional module có thể lỗi mà không làm sập core editor.

“Perfect” được coi là engineering target, không phải release claim. Stable 1.0 vẫn cần browser, accessibility, performance, upgrade, package và clean WordPress evidence.

---

## 2. Những gì thay đổi

### Shared runtime infrastructure mới

Bốn canonical PHP contracts được thêm:

- `WebsiteBuilderRuntimeContext`
- `WebsiteBuilderAsset`
- `WebsiteBuilderEditorConfig`
- `WebsiteBuilderModuleRegistry`

Chúng thay các quyết định lặp từng bị rải trong bootstrap, diagnostics, runtime guard, professional UX, workflow và compatibility modules.

### Runtime context

`WebsiteBuilderRuntimeContext` trở thành nguồn chuẩn cho:

- current Cresco editor screen;
- document ID;
- post type;
- Page vs Theme editor;
- document type;
- current-user edit capability;
- debug mode;
- safe/isolation mode;
- Architecture debug override.

Supported isolation modes:

- `normal`
- `core`
- `controls`
- `professional-ux`
- `architecture`
- `all`

`cresco-safe-mode=1` resolve thành `core`.

### Asset contract

`WebsiteBuilderAsset` sở hữu:

- plugin-relative absolute paths;
- browser URLs;
- readability checks;
- content-addressed cache versions;
- SHA-256/size diagnostics;
- refresh version cho registered WordPress script/style.

Mục tiêu là loại một nguồn stale-cache inconsistency khác.

### Editor configuration

`WebsiteBuilderEditorConfig` tạo một shared Page/Theme configuration shape cho:

- Session REST;
- validation;
- context REST;
- options;
- components;
- Page Settings;
- Global Settings;
- history;
- Theme templates/options;
- preview URL;
- widget catalog;
- preview widths;
- permissions;
- builder/plugin versions.

`WebsiteBuilderRuntimeGuard` ghi canonical config trước core editor runtime, để effective browser configuration không còn phụ thuộc compatibility service nào tình cờ build settings trước.

### Module registry

`WebsiteBuilderModuleRegistry` là authoritative server-side catalog cho runtime modules tại thời điểm refactor:

| Module | Required | Default policy |
| --- | ---: | --- |
| bootstrap | yes | enabled |
| core | yes | enabled |
| controls | no | enabled |
| professional-ux | no | enabled |
| architecture | no | quarantined by default |
| comprehensive-v3 | no | enabled, transitional |
| workflow | no | enabled |

Architecture được quarantine mặc định cho tới khi runtime/browser evidence chứng minh observer fix ổn định trong real editor usage.

---

## 3. Startup ownership sau refactor

### `WebsiteBuilderBootstrapResilience`

Sở hữu:

- bootstrap middleware asset;
- critical/optional request timeouts;
- emergency `wp.apiFetch` guard khi bootstrap middleware không install được;
- lightweight startup-state publication;
- temporary observer boot guards cho optional modules.

Nó không còn sở hữu competing fatal recovery UI.

### `WebsiteBuilderRuntimeGuard`

Sở hữu:

- final content-hash refresh;
- canonical browser editor config injection;
- central optional-module enable/dequeue policy;
- Architecture quarantine policy;
- user-facing fatal startup recovery panel duy nhất.

Đây là simplification quan trọng: nhiều recovery surface không nên race để rewrite cùng editor root.

### Browser runtime state

Browser nhận `window.crescoRuntimeState` nhỏ với các phase như:

- `CORE_LOADED`
- `SESSION_PENDING`
- `READY`
- `FAILED`

Diagnostics có thể đọc cùng state thay vì infer toàn bộ từ DOM text.

---

## 4. Diagnostics sau refactor

`Tools -> Cresco Diagnostics` được giữ độc lập với editor tab.

Diagnostics dùng cùng runtime contracts mà production startup dùng và report:

- WP, PHP, plugin versions;
- document context;
- Session presence, bytes, JSON validity, sanitizer result;
- mọi registered runtime module;
- asset readability, bytes, SHA-256, effective cache version;
- MutationObserver/static scheduling signals;
- default module quarantine state;
- REST endpoint results/timings;
- last persisted browser heartbeat.

Editor probe theo dõi:

- browser global errors;
- unhandled promise rejections;
- `wp.apiFetch` request lifecycle/duration;
- core readiness;
- startup state;
- module script presence;
- Architecture observer diagnostics;
- event-loop stalls;
- last heartbeat trong localStorage.

Dùng `&cresco-debug=1` để tự mở browser diagnostics overlay.

---

## 5. Workflow cô lập module

Ưu tiên diagnostics page thay vì liên tục sửa PHP để disable modules.

Thứ tự được khuyến nghị tại thời điểm đó:

1. Core only.
2. Core + Controls.
3. Core + Professional UX.
4. Architecture riêng với explicit Architecture debug flag.
5. All modules chỉ sau khi các tổ hợp nhỏ ổn định.

Nếu Core-only freeze, điều tra Session/bootstrap/core dependencies trước optional UX modules.

Nếu Core-only ổn nhưng tổ hợp khác freeze, sửa module sở hữu lỗi thay vì thêm global watchdog khác.

---

## 6. Quy tắc `MutationObserver`

Architecture freeze chứng minh observer feedback loop có thể làm nghẽn browser main thread trước khi REST startup ổn định.

Optional runtime dùng `MutationObserver` nên:

1. coalesce callback bằng scheduler như `requestAnimationFrame`;
2. tránh DOM write khi value đã đúng;
3. ignore/isolate self-owned mutations;
4. có lifecycle disconnect behavior;
5. expose diagnostic counters;
6. có guard khi mutation volume runaway.

Architecture runtime đã có fixed `scheduleShell` observer và observer statistics trong cả `build/` và `runtime-src/build/` tại thời điểm report.

---

## 7. Workflow và V3 decoupling

`WebsiteBuilderWorkflowExtensions` không còn phụ thuộc Comprehensive V3 presentation script.

Stable route mới:

`/cresco-canvas/v1/website-builder/woocommerce/templates/single`

Legacy compatibility alias được giữ:

`/cresco-canvas/v1/website-builder/v3/woo-single-template`

Runtime nhận stable feature route.

Đây là migration pattern mong muốn: đưa vào capability-oriented stable owner, giữ old route tạm thời, chỉ xóa alias sau khi có compatibility evidence.

---

## 8. Trạng thái Comprehensive V3

`WebsiteBuilderComprehensiveV3` được coi rõ là transitional compatibility code.

Stable document diagnostics route mới:

`/cresco-canvas/v1/website-builder/document-diagnostics/{postId}`

Legacy compatibility alias được giữ:

`/cresco-canvas/v1/website-builder/v3/diagnostics/{postId}`

Module vẫn xử lý transitional frontend CSS replacement/V3 presentation compatibility. Report nhấn mạnh: **không xây V4/V5 replacement**.

---

## 9. Repository quality gate

Command mới:

`npm run check:runtime-modules`

Nó verify:

- bốn runtime infrastructure files tồn tại;
- expected module keys có mặt;
- Architecture vẫn explicit trong policy;
- RuntimeGuard dùng module registry và canonical editor config;
- Bootstrap dùng canonical bootstrap paths;
- Diagnostics consume registry asset reports;
- stable Workflow/document-diagnostics routes tồn tại;
- Workflow không còn phụ thuộc V3 presentation script;
- Architecture source/build giữ `new MutationObserver(scheduleShell)` và observer stats;
- không tạo mới service `WebsiteBuilder*V4` đến `V9`.

`check:runtime-modules` được đưa vào `check:quality`.

---

## 10. Validation đã hoàn tất cho change này

Trước khi publish, refactor files được check bằng:

- `php -l` cho mọi PHP file mới/thay;
- `node --check scripts/check-runtime-modules.mjs`;
- JSON parse validation cho `package.json`.

Đây chỉ là syntax/contract checks.

Những phần vẫn cần real repository/WordPress environment:

- full `npm run check:quality`;
- exact source/build gates;
- WordPress editor boot;
- save/reload;
- Theme editor boot;
- critical E2E;
- browser matrix;
- accessibility;
- performance;
- package install/upgrade.

---

## 11. Transitional code còn lại tại thời điểm report

Refactor cố ý không xóa mọi compatibility code trong một lần.

### `WebsiteBuilderCompatibility`

Vẫn chứa legacy handle removal, fallback bootstrap behavior, contract bridging và frontend compatibility.

Mục tiêu tiếp theo được ghi:

- chuyển permanent behavior ra ngoài;
- chỉ giữ old-handle/payload/route/token translation;
- chỉ xóa fallback runtime recreation sau khi core startup evidence đủ tin cậy.

### `ThemeSessionBridge`

Vẫn tự reconstruct editor settings và optional asset loading.

Mục tiêu tiếp theo:

- consume `WebsiteBuilderEditorConfig` trực tiếp;
- dùng `WebsiteBuilderAsset` cho runtime asset versions;
- dùng Module Registry cho optional Theme-editor presentation modules.

### `BuilderArchitecture`

Application/core behavior được coi hợp lệ nhưng editor enqueue vẫn có local screen/version logic.

Mục tiêu:

- consume RuntimeContext;
- consume Asset helper;
- để Module Registry là optional-module policy owner duy nhất.

### `WebsiteBuilder`

Core Website Builder vẫn là authoritative Session/component service. Existing Page editor settings array được giữ cho compatibility, trong khi RuntimeGuard inject canonical effective config trước browser execution.

Sau browser verification, duplicate server-side settings construction có thể được remove an toàn.

---

## 12. Rendering rule

Long-term invariant được ghi rõ:

```text
Session -> sanitize -> RenderEngine -> WebsiteRenderer HTML + WebsiteBuilderCssCompiler CSS
```

Optional presentation module không được trở thành final renderer/CSS authority thứ hai.

Compatibility module có thể loại historical fragments nhưng không được invent parallel final CSS pipeline.

---

## 13. Performance policy

Không optimize bằng intuition; đo trước.

Target budgets được đề xuất sau khi có baseline:

- editor shell visible: dưới 1 giây trên controlled local reference hardware;
- critical Session REST: dưới 500 ms local, dưới 1.5 giây production target;
- core ready sau Session: dưới 300 ms;
- node selection: dưới 50 ms median;
- Inspector tab switch: dưới 100 ms;
- idle optional observers: gần zero mutation work;
- optional module failure: không block core;
- public frontend JavaScript: zero trừ khi rendered feature thực sự cần.

Đây là target của report, không phải current measured guarantee.

---

## 14. P0 continuation work được đề xuất

Trước broad feature expansion tiếp theo:

1. pull consolidated `main`;
2. mở known Page với normal policy và `cresco-debug=1`;
3. chạy Tools -> Cresco Diagnostics REST tests;
4. verify Core-only lặp lại;
5. verify Controls;
6. verify Professional UX;
7. verify Architecture explicit;
8. verify All modules;
9. save/reload Session;
10. verify frontend render parity;
11. chạy `npm run check:quality`;
12. capture browser/performance evidence.

---

## 15. P1 cleanup work được đề xuất

Sau P0 evidence:

- slim `WebsiteBuilderCompatibility`;
- migrate `ThemeSessionBridge` sang shared runtime contracts;
- migrate `BuilderArchitecture` enqueue logic;
- remove duplicate asset-version helpers;
- remove duplicate editor config builders;
- chuyển diagnostics presentation JS/CSS khỏi inline PHP sang reviewed assets nếu hợp lý;
- thay V3 route usage trong remaining clients bằng stable feature routes;
- giữ compatibility aliases trong documented deprecation period.

---

## 16. P2 product evolution được đề xuất

Chỉ sau khi runtime consolidation ổn định:

- Session-native Theme documents khi phù hợp;
- synchronized reusable components;
- visual Loop/template designer;
- deeper WooCommerce template controls;
- richer responsive controls;
- extension SDK quanh registries/contracts;
- collaboration/cloud storage adapters qua `DocumentRepository`, không direct editor coupling.

---

## 17. Các invariant không thương lượng

Future work nên giữ:

- `cresco-session/v1` readability cho tới khi có deliberate migration;
- user-authored `post_content` preservation;
- Core independence khỏi editor DOM;
- Core independence khỏi specific AI provider/WooCommerce runtime;
- optional module failure isolation;
- một final rendering authority;
- server-authoritative command/patch validation;
- scoped sanitized Custom CSS;
- không có arbitrary executable Session content;
- không tạo numbered parallel builder generation mới;
- compatibility code phải có explicit exit strategy.

---

## 18. Definition of Done cho future refactor

Refactor chỉ xong khi:

1. behavior được preserve hoặc intentionally document;
2. source/build runtime pairs synchronized;
3. PHP/JS syntax pass;
4. static architecture/runtime gates pass;
5. unit tests pass;
6. clean WordPress editor starts;
7. save/reload preserve Session;
8. frontend match authoritative render;
9. diagnostics không có new fatal/degraded condition;
10. không tạo hidden dependency vào retired compatibility modules;
11. docs đổi trong cùng branch.

---

## 19. Debugging runbook cho freeze

1. Mở `Tools -> Cresco Diagnostics`.
2. Nhập document ID.
3. Run REST tests.
4. Mở Core-only.
5. Thêm từng module một.
6. Inspect persisted heartbeat.
7. Copy full diagnostics report.
8. Xác định smallest failing module combination.
9. Sửa module đó.
10. Thêm regression gate.
11. Chỉ bỏ temporary quarantine sau khi runtime evidence pass.

---

## 20. Mục tiêu kiến trúc cuối cùng

Một developer tương lai cần trả lời được mỗi câu hỏi bằng **một owner**:

- Ai sở hữu document persistence?
- Ai sở hữu rendering?
- Ai sở hữu editor configuration?
- Ai sở hữu module loading?
- Ai sở hữu startup state?
- Ai sở hữu fatal recovery UI?
- Ai sở hữu diagnostics?
- Ai sở hữu legacy compatibility?

Refactor này thiết lập hướng đó cho runtime layer mà không thực hiện big-bang deletion đầy rủi ro.

## Cách dùng report hiện nay

Report mô tả branch/base/date ghi ở đầu file. Một số transitional target có thể đã được xử lý trên `main` sau đó. Luôn kiểm tra current source và canonical ownership docs trước khi tiếp tục một item cũ.