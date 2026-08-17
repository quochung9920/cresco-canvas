# Quyết định kiến trúc

> **Scope Studio hiện tại:** File này chứa ADR từ nhiều thế hệ kiến trúc Cresco Canvas. ADR-013 supersede ADR-011/ADR-012 đối với **Studio-owned Website Builder documents** và thu hẹp các giả định Gutenberg-only cũ khi chúng xung đột với Session-native Studio runtime hiện tại. ADR cũ vẫn được giữ làm lịch sử.

| ID | Quyết định | Lý do | Hệ quả |
| --- | --- | --- | --- |
| ADR-001 | Giữ native block markup trong `post_content` | Giữ interoperability với WordPress và readable content sau deactivation | Foundation lịch sử cho Gutenberg-native path. Không dùng ADR-001 để xóa/bypass Session model hiện tại. |
| ADR-002 | Chỉ triển khai milestone 0.2 trong foundation PR | Repository 0.1.1 khi audit còn thiếu foundation | Native entity reliability tiếp tục ở milestone 0.3. |
| ADR-003 | Dùng `@wordpress/scripts` và npm version xác định | Đồng bộ build/lint với WordPress và deterministic resolution | Development-only advisory phải được theo dõi. |
| ADR-004 | Composer PSR-4 + restricted fallback loader | Release ZIP dùng optimized autoload; source checkout vẫn fail-safe | Release builder cần `vendor/autoload.php`. |
| ADR-005 | Giữ custom Page REST route tạm thời cho 0.2 | Giữ foundation PR dễ review | Historical; current Studio có dedicated permissioned routes theo ADR-013. |
| ADR-006 | Scope/condition toàn bộ Cresco frontend asset | Giảm collision và cost trên Page không dùng Cresco | Legacy Container detection có thể còn vì backward compatibility. |
| ADR-007 | Editor selection mặc định nhớ/native fallback ở 0.2 | Giảm takeover risk trước native integration | Superseded bởi ADR-011 ở thế hệ Gutenberg và ADR-013 cho Studio. |
| ADR-008 | Uninstall deletion là opt-in và bảo toàn content | Cleanup phá dữ liệu phải explicit/recoverable | Native/orphan content có thể còn nếu user chỉ xóa metadata. |
| ADR-009 | Build deterministic ZIP từ allowlist | Ngăn secret/source/test lọt vào artifact và giúp so sánh build | Composer/production build output phải tồn tại trước package. |
| ADR-010 | Tách compatibility/nightly và manual UX claim | Configured test không phải bằng chứng đã pass | Dùng `NOT TESTED`/`NOT VERIFIED` đến khi có evidence. |
| ADR-011 | **SUPERSEDED FOR STUDIO:** Gutenberg là Page editor duy nhất | Đúng với kiến trúc Gutenberg-native trước đây | Chỉ giữ làm history/legacy guidance; không dùng để remove current Studio runtime. |
| ADR-012 | **SUPERSEDED FOR STUDIO:** Page enablement qua revision-enabled native post meta | Với thế hệ Gutenberg-native, content/style đi qua một Core save boundary | Chỉ áp dụng legacy/native path khi không xung đột ADR-013. |
| ADR-013 | Cresco Studio là Website Builder runtime canonical cho Studio-owned document, vẫn giữ Gutenberg/native interoperability như path riêng | Một runtime owner + một Session model + compatibility boundary rõ ngăn dual-editor/state divergence | `WebsiteBuilderStudio` sở hữu active shell/handle; `cresco-session/v1` authoritative; optional module extend additively. |
| ADR-014 | Bắt buộc contract ownership cho runtime, DOM, CSS, state, schema, build và branch | Regression gần đây chủ yếu là semantic ownership conflict | Studio change phải theo ownership contract; persisted setting đổi atomic; React DOM dùng SDK/bridge/portal; source/build và branch phải sync. |

## Cách thêm ADR mới

Không xóa ADR cũ chỉ vì kiến trúc đã tiến hóa. ADR mới phải:

1. ghi rõ vấn đề;
2. ghi scope;
3. nêu quyết định;
4. nêu consequence/compatibility;
5. ghi ADR nào bị supersede nếu có;
6. link contract/source canonical liên quan.

Khi một ADR lịch sử mâu thuẫn Studio hiện tại, ưu tiên current executable code, ADR áp dụng rõ cho Studio và `STUDIO_RUNTIME_OWNERSHIP_AND_CONFLICT_PREVENTION.md`.
