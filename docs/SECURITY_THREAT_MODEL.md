# Mô hình đe dọa bảo mật — milestone 0.3 (lịch sử)

> **Tài liệu lịch sử.** Last reviewed: **2026-08-04** cho milestone 0.3.
>
> Với security contract hiện tại, ưu tiên `SECURITY.md`. File này chỉ mô tả threat model của baseline/milestone được ghi ở trên.

## Tài sản cần bảo vệ

- Page content, Cresco Page metadata, publication state và draft privacy.
- Site-wide Cresco design settings và feature flags.
- WordPress authentication cookies và REST nonces.
- Khả dụng của Gutenberg và public frontend.
- Tính toàn vẹn của release artifact và việc không chứa developer/private files.

## Trust boundaries

Ở kiến trúc milestone 0.3, WordPress Core sở hữu Page REST/entity boundary và document workflows. Browser input do Cresco sở hữu đi qua một custom REST boundary cho global settings.

Page author không mặc định có site-design permission. Stored settings và legacy options phải được coi là untrusted cho tới khi normalize. Themes, plugins, blocks và build dependencies có thể lỗi hoặc conflict.

## Threats và controls

| Threat | Control tại milestone 0.3 | Residual status tại thời điểm review |
| --- | --- | --- |
| Unauthorized Page content/meta write | Core Page endpoint + `_cresco_canvas_enabled` `edit_post` authorization | Role và real WordPress integration matrix vẫn `NOT VERIFIED` |
| Unauthorized publication/state transition | Delegate hoàn toàn cho Core Page capabilities/editor workflow | Runtime role matrix vẫn `NOT VERIFIED` |
| Cross-site request forgery | REST nonce do Core cấu hình và `apiFetch` sử dụng; không còn custom editor-choice action | Browser evidence còn chờ |
| Stale overwrite/concurrent editing | Native autosave, revisions, post locking, conflict notices và Core save behavior | Two-user runtime test vẫn `NOT VERIFIED` |
| Stored CSS injection | Hex sanitization, bounded numbers, strict font-stack grammar và trusted internal selectors | Chưa có property/fuzz testing; arbitrary Custom CSS chưa được implement ở milestone này |
| Malicious settings REST values | `edit_theme_options`, response schema, normalization, bounds và explicit property allowlist | Real REST permission integration test vẫn `NOT VERIFIED` |
| Cross-site scripting trong admin output | WordPress escaping + `wp_json_encode` cho bootstrap data; React escape rendered text | Full manual payload review vẫn `NOT VERIFIED` |
| Editor denial of service do thiếu asset | Thiếu Cresco build chỉ tạo non-blocking notice; Gutenberg tiếp tục chạy | Real missing-build staging test vẫn `NOT VERIFIED` |
| Redirect loop/open redirect | Baseline này không có Cresco editor router/takeover/redirect/alternate Page URL | Static regression và E2E absence checks đã cấu hình |
| Lifecycle data destruction | Deactivation giữ dữ liệu; uninstall explicit và không xóa posts/content | Multisite execution vẫn `NOT VERIFIED` |
| Frontend style/script contamination | Không có public editor JS; frontend CSS scoped và conditional | Third-party theme/block matrix vẫn `NOT VERIFIED` |
| Artifact contamination | Deterministic allowlist ZIP, archive-content assertion và SHA-256 output | Signing/provenance còn thiếu |
| Dependency compromise | Exact locks, production audit gate và visible full audit | Development toolchain còn 30 transitive advisories tại thời điểm đó |
| CI action tag mutation | Established major action tags | Commit-SHA pinning còn open P2 |

## REST inventory tại milestone 0.3

| Route | Method | Capability | Mutation |
| --- | --- | --- | --- |
| WordPress Core Page endpoint | Core methods | Core Page/post capabilities | Native Page content, status và registered metadata |
| `/cresco-canvas/v1/settings` | GET/POST | `edit_theme_options` | Chỉ validated Cresco option |

Retired `/cresco-canvas/v1/pages` routes được cố ý loại bỏ ở baseline này và có E2E regression coverage.

## Kết quả security của assessment này

Static review và local JavaScript/PHP-source checks không còn reproduce P0/P1 security issue trong scope được audit.

Điều đó **không đồng nghĩa security gate đã pass**. Native role integration, Plugin Check, hosted CI, penetration testing, provenance và future product surfaces vẫn chưa verify tại thời điểm tài liệu được viết.

## Cách dùng hiện nay

Dùng file này để hiểu threat model của giai đoạn Gutenberg-native/milestone 0.3. Không dùng route inventory hoặc ownership assumption của file này để phủ định current Studio architecture. Với code hiện tại, đọc `SECURITY.md`, `PROJECT_RULES.md` và current runtime/architecture docs.