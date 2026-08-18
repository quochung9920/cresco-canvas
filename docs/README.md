# Tài liệu Cresco Canvas

Đây là điểm bắt đầu để đọc tài liệu Cresco Canvas.

**Quy ước:** tài liệu dùng để phát triển hiện tại được viết bằng tiếng Việt để dễ đọc. Identifier kỹ thuật như class, function, schema, route, event, file path, CSS variable, JSON key và code literal giữ nguyên tiếng Anh để không làm sai contract.

Nếu chỉ cần bắt đầu sửa code, đọc theo thứ tự bên dưới. Không cần đọc toàn bộ lịch sử repository.

---

## 1. Đọc nhanh theo thứ tự

1. [`../PROJECT_RULES.md`](../PROJECT_RULES.md) — quy tắc bắt buộc toàn repository.
2. [`CORE_ARCHITECTURE.md`](CORE_ARCHITECTURE.md) — kiến trúc Core hiện tại.
3. [`STUDIO_RUNTIME_OWNERSHIP_AND_CONFLICT_PREVENTION.md`](STUDIO_RUNTIME_OWNERSHIP_AND_CONFLICT_PREVENTION.md) — runtime, DOM, state, CSS, schema, build và conflict prevention.
4. [`STUDIO_EDITOR_EXPERIENCE_2.md`](STUDIO_EDITOR_EXPERIENCE_2.md) — UX/feature contract của Studio 2.0.
5. [`CRESCO_SESSION_V1.md`](CRESCO_SESSION_V1.md) — document model canonical.
6. [`CRESCO_PATCH_V1.md`](CRESCO_PATCH_V1.md) — mutation/patch protocol.
7. [`CRESCO_AI_CONTEXT_V3.md`](CRESCO_AI_CONTEXT_V3.md) — AI context mặc định hiện tại.
8. [`DECISIONS.md`](DECISIONS.md) — ADR và quyết định kiến trúc.
9. Tài liệu subsystem đang sửa.

Nếu một file lịch sử mâu thuẫn với các tài liệu trên, **không dùng file lịch sử để override code/runtime hiện tại**.

---

## 2. Authority khi tài liệu xung đột

Thứ tự cho Studio hiện tại:

1. executable code + tests trên `main`;
2. current ADR áp dụng rõ cho Studio-owned document;
3. `PROJECT_RULES.md`;
4. `STUDIO_RUNTIME_OWNERSHIP_AND_CONFLICT_PREVENTION.md`;
5. `CORE_ARCHITECTURE.md` và current feature contract;
6. compatibility/historical docs trong đúng scope ban đầu.

Nếu code và canonical docs lệch nhau ngoài ý muốn, coi đó là defect và sửa code/docs trong cùng change.

---

## 3. Tài liệu kiến trúc và Studio hiện tại

| File | Nên đọc khi nào |
| --- | --- |
| `CORE_ARCHITECTURE.md` | Cần hiểu Core/Application/Rendering/Modules, Session, responsive, AI |
| `WEBSITE_BUILDER_CORE.md` | Sửa Website Builder Core, capability và ownership |
| `STUDIO_RUNTIME_OWNERSHIP_AND_CONFLICT_PREVENTION.md` | Sửa runtime, React DOM, state, CSS, Page Settings, build/branch |
| `STUDIO_EDITOR_EXPERIENCE_2.md` | Sửa Studio shell, panel, Inspector, command, workflow |
| `CANONICAL_INSPECTOR_RESPONSIVE_CONTROLS.md` | Sửa Inspector control/responsive UI |
| `CANONICAL_STUDIO_GLOBAL_BREAKPOINTS.md` | Sửa breakpoint/global responsive behavior |
| `WEBSITE_BUILDER_RESPONSIVE_MODEL.md` | Sửa inheritance/resolver/compiler responsive |
| `STYLE_UNSET_SEMANTICS.md` | Sửa reset/unset/inherit style |
| `STUDIO_PREMIUM_POLISH.md` | Chỉ sửa visual polish, không dùng làm logic owner |
| `CSS_LOADING_INTERVAL.md` | Kiểm tra CSS load/cascade interval |
| `DESIGN_PHILOSOPHY_V1.md` | Cần hiểu triết lý thiết kế sản phẩm |

---

## 4. Session, patch và AI

| File | Nội dung |
| --- | --- |
| `CRESCO_SESSION_V1.md` | Session schema, node, style, responsive, state, validation |
| `CRESCO_PATCH_V1.md` | Patch operation, target/scope, validation, diff/apply |
| `CRESCO_AI_CONTEXT_V3.md` | One-shot AI context hiện hành |
| `AI_INGESTION.md` | Cách nhận và normalize dữ liệu AI |
| `AI_INTERCHANGE_V1.md` | Interchange v1 |
| `AI_INTERCHANGE_V2.md` | Interchange v2 |
| `AI_VISUAL_CONTRACT_V2.md` | Visual context/contract |
| `CANVAS_LOADDATA.md` | Load data/context cho Canvas |

`CRESCO_AI_CONTEXT_V1.md` và `CRESCO_AI_CONTEXT_V2.md` là compatibility/historical contract; dùng V3 cho workflow mặc định hiện tại trừ khi cần tương thích phiên bản cũ.

---

## 5. Global Design, Page Settings và controls

| File | Nội dung |
| --- | --- |
| `GLOBAL_DESIGN_SYSTEM_REQUIREMENTS.md` | Requirement tổng thể của Global Design |
| `GLOBAL_DESIGN_STAGE1.md` … `GLOBAL_DESIGN_STAGE5.md` | Các stage triển khai Global Design |
| `COLOR_HARMONY_WORKFLOW.md` | Color Harmony workflow |
| `GLOBAL_CONFIG_IMPORT.md` | Import/export Global Design config |
| `PAGE_SETTINGS_FINALIZATION.md` | Page Settings contract/finalization |
| `STUDIO_SIZE_CONTROL_SYSTEM.md` | DimensionControl canonical, size mode, Custom CSS, responsive/state và giới hạn Page Settings |
| `PROFESSIONAL_WIDGETS.md` | Professional widgets và style capability |
| `WEBSITE_BUILDER_V3.md` | Compatibility/workflow layer V3 |

Nhớ rằng Widget control và Page Settings có persistence domain khác nhau. Không copy UI capability giữa hai domain nếu backend schema chưa hỗ trợ.

---

## 6. Security, privacy, accessibility và release

| File | Nội dung |
| --- | --- |
| `SECURITY.md` | Security boundary và REST inventory hiện tại |
| `PRIVACY.md` | Data ownership, retention, exporter/eraser, uninstall |
| `ACCESSIBILITY_AUDIT.md` | Accessibility gate |
| `PERFORMANCE_BASELINE.md` | Performance baseline |
| `COMPATIBILITY_MATRIX.md` | WordPress/PHP/browser/integration matrix |
| `PRODUCTION_HARDENING_VERIFICATION.md` | Production hardening evidence/runbook |
| `RELEASE_ENGINEERING.md` | Build/package/evidence pipeline |
| `RELEASE_CHECKLIST.md` | Gate trước stable release |
| `COMMERCIAL_HARDENING.md` | P0/P1 hardening plan |
| `KNOWN_LIMITATIONS.md` | Giới hạn đã biết của release candidate |
| `STABLE_READY_CONTRACT.md` | Điều kiện stable-ready |
| `UPGRADE_ROLLBACK.md` | Upgrade/downgrade/rollback/multisite |

Một workflow/check tồn tại không có nghĩa release đã pass. Chỉ exact-artifact evidence của đúng commit/ZIP mới hỗ trợ release claim tương ứng.

---

## 7. Tài liệu lịch sử, audit và báo cáo

Đọc [`LICH_SU_VA_BAO_CAO.md`](LICH_SU_VA_BAO_CAO.md) trước khi mở nhóm này.

Nhóm lịch sử gồm các file như:

- `ARCHITECTURE.md` — kiến trúc Gutenberg-native cũ, đã được Việt hóa nhưng **không canonical** cho Studio hiện tại;
- `ROADMAP.md` — trạng thái cũ, tự ghi stale từ 2026-08-14;
- `COMMERCIAL_READINESS.md` — assessment tại `0.3.0-alpha.1`;
- `SECURITY_THREAT_MODEL.md` — threat model milestone 0.3;
- `CRESCO_AI_CONTEXT_V1.md`, `CRESCO_AI_CONTEXT_V2.md` — AI compatibility contracts cũ;
- `AUDIT_2026-08.md`, `BASELINE_AUDIT.md`, `OLLAMA_SUPER_UPGRADE_AUDIT.md` — audit theo thời điểm;
- `CRESCO_CANVAS_SYSTEM_REPORT.md`, `DEVELOPMENT_CONTINUATION_REPORT.md`, `EXECUTION_STATUS.md`, `FINALIZATION_STATUS.md` — status report theo thời điểm;
- `UPGRADE_2026-08-COMMERCIAL.md` — upgrade report/plan theo baseline cũ;
- `releases/*` — release note lịch sử.

Không sửa status/checklist lịch sử chỉ để tài liệu trông mới hơn. Khi cần assessment mới, tạo audit/ADR mới có date + baseline rõ ràng.

---

## 8. Machine-facing prompt và literal contract

`CODEX_MASTER_IMPLEMENTATION_PROMPT.md` là prompt/machine-facing artifact lịch sử. Phần instruction literal được giữ tiếng Anh để không vô tình thay semantic của agent command.

Người đọc nên mở [`CODEX_MASTER_IMPLEMENTATION_PROMPT_VI.md`](CODEX_MASTER_IMPLEMENTATION_PROMPT_VI.md) trước để xem bản diễn giải tiếng Việt, phần nào còn giá trị và phần assumption nào đã bị current Studio architecture supersede.

Khi dịch/chỉnh tài liệu machine-facing, luôn giữ nguyên:

- schema name;
- JSON key;
- route;
- event name;
- class/function/file path;
- CSS variable;
- command/code block mà parser/agent phụ thuộc.

Tiếng Việt dùng để giải thích **xung quanh** contract, không đổi literal contract.

---

## 9. Quy ước thuật ngữ

Các từ sau có thể giữ tiếng Anh khi giúp câu chính xác hơn:

`runtime`, `owner`, `state`, `scope`, `Session`, `patch`, `render`, `build`, `fallback`, `adapter`, `sanitizer`, `invariant`, `commit`, `branch`, `merge`, `responsive`, `breakpoint`, `control`, `Inspector`.

Mục tiêu là dễ hiểu và không mơ hồ, không phải dịch mọi từ bằng mọi giá.

---

## 10. Quy tắc cập nhật docs

Khi behavior/architecture thay đổi:

1. sửa code + test;
2. sửa canonical contract/ADR liên quan trong cùng change;
3. cập nhật `PROJECT_RULES.md` nếu quy tắc toàn dự án đổi;
4. cập nhật file subsystem nếu public behavior đổi;
5. không rewrite historical evidence thành trạng thái hiện tại;
6. ghi rõ phần chưa verify;
7. giữ filename/schema/literal identifier ổn định nếu compatibility cần chúng.

Tài liệu tốt phải giúp người mới xác định được **owner nào cần sửa**, không chỉ mô tả giao diện trông như thế nào.