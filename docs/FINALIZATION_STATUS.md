# Trạng thái finalization của Cresco Canvas

> **Tài liệu trạng thái lịch sử.** Nội dung mô tả finalization candidate của branch tại thời điểm file được viết, không tự động phản ánh `main` hiện tại.

Branch này tồn tại để chạy automated **Release Hardening** workflow với current finalization candidate của thời điểm đó.

Stable `1.0.0` không được tuyên bố certified cho tới khi toàn bộ automated gates pass và repository có objective manual evidence mà `scripts/check-stable-release.mjs` yêu cầu, bao gồm manual accessibility verification và exact artifact SHA-256.

Commercial-readiness capability được candidate này triển khai gồm:

- provider-backed license activation/deactivation;
- authenticated update manifests với stable/beta channels;
- copy-safe Site Health diagnostics;
- onboarding completion state;
- previous-version metadata để hỗ trợ rollback.

Khi đánh giá trạng thái hiện tại, hãy kiểm tra current release docs, current source và evidence của đúng commit/artifact thay vì dùng file snapshot này như certification.