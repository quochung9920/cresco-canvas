# Tài liệu Cresco Canvas

Thư mục này chứa tài liệu kiến trúc, contract, Studio UX, AI, security, privacy, release, audit và lịch sử phát triển của Cresco Canvas.

Mục tiêu của bộ docs mới là: **phần hướng dẫn/canonical đọc bằng tiếng Việt**, còn identifier kỹ thuật giữ nguyên để developer và AI Coding Agent không làm sai contract.

## Đọc nhanh theo thứ tự

1. [`../PROJECT_RULES.md`](../PROJECT_RULES.md) — quy tắc bắt buộc toàn repository.
2. [`CORE_ARCHITECTURE.md`](CORE_ARCHITECTURE.md) — kiến trúc Core hiện tại.
3. [`STUDIO_RUNTIME_OWNERSHIP_AND_CONFLICT_PREVENTION.md`](STUDIO_RUNTIME_OWNERSHIP_AND_CONFLICT_PREVENTION.md) — ownership runtime/DOM/state/CSS/schema/build.
4. [`STUDIO_EDITOR_EXPERIENCE_2.md`](STUDIO_EDITOR_EXPERIENCE_2.md) — trải nghiệm Studio 2.0.
5. [`CRESCO_SESSION_V1.md`](CRESCO_SESSION_V1.md) — document model.
6. [`CRESCO_PATCH_V1.md`](CRESCO_PATCH_V1.md) — mutation protocol cho AI/interchange.
7. [`CRESCO_AI_CONTEXT_V3.md`](CRESCO_AI_CONTEXT_V3.md) — one-shot AI authoring context hiện hành.
8. [`DECISIONS.md`](DECISIONS.md) — ADR/quyết định kiến trúc.

## Tài liệu canonical/đang dùng đã Việt hóa

| Tài liệu | Nội dung | Authority |
| --- | --- | --- |
| `CORE_ARCHITECTURE.md` | Core/Application/Rendering/Module, Session, responsive, AI | Cao |
| `STUDIO_RUNTIME_OWNERSHIP_AND_CONFLICT_PREVENTION.md` | Runtime, React DOM, state, CSS, Page Settings, branch/build | Cao |
| `STUDIO_EDITOR_EXPERIENCE_2.md` | Feature/UX contract của Studio 2.0 | Cao |
| `CRESCO_SESSION_V1.md` | Session JSON và validation rule | Cao |
| `CRESCO_PATCH_V1.md` | Patch operation, scope, validation, Diff | Cao |
| `CRESCO_AI_CONTEXT_V3.md` | Default one-shot AI context | Cao cho AI workflow hiện hành |
| `STYLE_UNSET_SEMANTICS.md` | Reset/unset/inherit style | Cao |
| `WEBSITE_BUILDER_CORE.md` | Website Builder Core capability/ownership | Cao |
| `PROFESSIONAL_WIDGETS.md` | Professional widgets + Border shorthand | Feature contract |
| `WEBSITE_BUILDER_V3.md` | Comprehensive V3 compatibility/workflow layer | Feature contract |
| `REALTIME_CANONICAL_PREVIEW.md` | Realtime canonical preview intent | Feature contract |
| `STUDIO_PREMIUM_POLISH.md` | Presentation-only polish contract | Feature contract |
| `GLOBAL_CONFIG_IMPORT.md` | Global Design import/export | Feature contract |
| `SECURITY.md` | Security boundary + REST inventory | Release/security |
| `PRIVACY.md` | Data ownership, retention, exporter/eraser, uninstall | Release/privacy |
| `PRODUCTION_HARDENING_VERIFICATION.md` | Runbook tạo production hardening evidence | Release |
| `ACCESSIBILITY_AUDIT.md` | Automated/manual accessibility gate | Release |
| `PERFORMANCE_BASELINE.md` | Performance benchmark/baseline rules | Release |
| `COMPATIBILITY_MATRIX.md` | WordPress/PHP/browser/integration matrix | Release |
| `RELEASE_ENGINEERING.md` | Build/package/evidence pipeline | Release |
| `RELEASE_CHECKLIST.md` | Gate trước stable release | Release |
| `COMMERCIAL_HARDENING.md` | P0/P1 plan cho stable 1.0 | Release |
| `KNOWN_LIMITATIONS.md` | Giới hạn đã biết của RC | Release |
| `UPGRADE_ROLLBACK.md` | Upgrade/downgrade/rollback/multisite | Release/lifecycle |

## Tài liệu compatibility hoặc lịch sử

Một số file được giữ nguyên theo thời điểm audit/release để không làm sai bằng chứng lịch sử. Khi chúng mâu thuẫn với code hoặc tài liệu canonical hiện tại, **không dùng chúng để override `main`**.

Nhóm này gồm:

- `ARCHITECTURE.md` — kiến trúc Gutenberg-native cũ.
- `ROADMAP.md` — tự ghi rõ stale từ 2026-08-14; chỉ dùng làm lịch sử.
- `COMMERCIAL_READINESS.md` — assessment cũ theo version/date ghi trong file.
- `SECURITY_THREAT_MODEL.md` — threat model milestone 0.3; `SECURITY.md` mới hơn là release security contract hiện tại.
- `CRESCO_AI_CONTEXT_V1.md`, `CRESCO_AI_CONTEXT_V2.md` — compatibility contract của AI context cũ; V3 là default one-shot hiện tại.
- `AUDIT_2026-08.md`, `BASELINE_AUDIT.md`, `OLLAMA_SUPER_UPGRADE_AUDIT.md` — audit evidence theo thời điểm.
- `CRESCO_CANVAS_SYSTEM_REPORT.md`, `DEVELOPMENT_CONTINUATION_REPORT.md`, `EXECUTION_STATUS.md`, `FINALIZATION_STATUS.md` — report trạng thái theo thời điểm.
- `UPGRADE_2026-08-COMMERCIAL.md` — upgrade report/plan theo baseline ghi trong file.
- `docs/releases/*` — release note lịch sử.

Không sửa status/checklist lịch sử chỉ để tài liệu “trông mới hơn”. Nếu cần assessment mới, tạo audit/ADR mới có ngày và baseline rõ ràng.

## Tài liệu machine-facing/prompt

`CODEX_MASTER_IMPLEMENTATION_PROMPT.md` và các prompt/contract machine-facing có thể giữ phần literal instruction/identifier bằng tiếng Anh nếu việc dịch làm tăng nguy cơ thay semantic của agent command.

Khi chỉnh các file AI/machine contract, phải giữ nguyên:

- schema name;
- JSON key;
- route;
- event name;
- class/function/file path;
- CSS variable;
- code block/literal command mà parser/agent phụ thuộc.

## Authority khi tài liệu xung đột

Dùng thứ tự sau cho Studio hiện tại:

1. executable code + tests trên `main`;
2. current ADR áp dụng rõ cho Studio-owned document;
3. `PROJECT_RULES.md`;
4. `STUDIO_RUNTIME_OWNERSHIP_AND_CONFLICT_PREVENTION.md`;
5. `CORE_ARCHITECTURE.md` và current feature contract;
6. compatibility/history docs trong đúng scope ban đầu.

Nếu code và canonical docs lệch nhau ngoài ý muốn, đó là defect. Sửa code hoặc docs trong cùng change để khôi phục một source of truth rõ ràng.

## Quy ước ngôn ngữ

Prose hướng dẫn/canonical ưu tiên tiếng Việt.

Identifier kỹ thuật như class, function, schema, route, file path, event name, CSS variable và JSON key giữ nguyên tiếng Anh.

Các thuật ngữ kỹ thuật phổ biến như `runtime`, `owner`, `state`, `scope`, `Session`, `patch`, `render`, `build`, `fallback`, `adapter`, `sanitizer`, `invariant` có thể giữ nguyên khi giúp câu rõ và ít mơ hồ hơn.
