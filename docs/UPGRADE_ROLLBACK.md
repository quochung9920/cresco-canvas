# Upgrade, Downgrade, Rollback và Multisite Lifecycle

## Lifecycle invariant

Migration có thể sửa Cresco-owned settings/metadata nhưng không được rewrite/xóa user-authored `post_content` như side effect của migration/uninstall.

Lifecycle hỗ trợ:

```text
clean install -> activation -> use -> deactivate -> reactivate -> upgrade -> migration -> rollback/downgrade detection -> uninstall
```

## Clean install và activation

Activation validate requirements và chạy versioned migration cho site hiện tại. Activation thành công schedule retention job cần thiết.

Với network activation trên multisite, Cresco xử lý site theo bounded batch, `switch_to_blog()`/`restore_current_blog()`, migrate/schedule per-site. Site tạo mới khi network-active phải được initialize theo hook hiện hành.

Migration fail trong activation phải fail safely; không sửa Page body content.

## Migration contract

Schema version lưu tại `cresco_canvas_db_version`.

Behavior:

- lock ngăn concurrent migration writer và có expiry cho stale lock;
- `cresco_canvas_migration_backup` lưu snapshot pre-migration theo contract;
- step chạy tăng dần;
- sau step thành công, completed schema version được persist;
- later failure retry từ last completed version;
- failure state chỉ giữ non-secret fingerprint/metadata, không log private value;
- migration nên idempotent khi có thể.

Historical fixture bao phủ old settings/Session và malformed input theo test suite.

## Upgrade

Recommended production procedure:

1. Backup database và Cresco private upload directory.
2. Ghi installed plugin/schema version.
3. Deploy package mới.
4. Activate/update và confirm migration complete.
5. Smoke editor, frontend, forms, private download, webhook, retention.
6. Giữ package/backup trước deploy cho tới khi verify xong.

## Migration failure và retry

Nếu state là `failed`:

1. Không manually tăng stored schema version.
2. Ghi reference/fingerprint an toàn.
3. Kiểm tra server/PHP log theo secret-redaction policy.
4. Sửa environment/data issue gốc.
5. Retry activation/migration path bình thường.
6. Nếu không recover được, restore matching DB/private-file backup + plugin package.

## Downgrade detection

Nếu stored schema version lớn hơn schema mà plugin cũ hiểu, Cresco phải vào compatibility pause thay vì silently ghi format cũ.

Recovery:

- cài lại plugin version hỗ trợ stored schema; hoặc
- restore backup trước newer schema rồi cài matching older package.

Không hạ `cresco_canvas_db_version` thủ công; đổi số version không reverse migrated data.

## Deactivation/reactivation

Deactivation non-destructive: remove migration lock và unschedule Cresco jobs theo contract; settings, Session, revisions, submissions, private uploads được preserve.

Reactivation chạy migration check và schedule idempotently.

## Multisite

Operational data lưu per-site. Network lifecycle dùng bounded batch và luôn pair `switch_to_blog()` với `restore_current_blog()`.

Verify:

- per-site activate chỉ initialize site đó;
- network activate initialize existing sites;
- new site khi network-active được initialize;
- deactivate preserve data nhưng clear intended cron scope;
- network upgrade migrate từng site độc lập;
- uninstall với mixed `removeDataOnUninstall` không để decision site này xóa data site khác.

## Uninstall và rollback invariant

Mặc định uninstall preserve Cresco data. Explicit cleanup là opt-in và chỉ xóa resource thuộc `Lifecycle\\UninstallPolicy`.

**Invariant tuyệt đối: Cresco uninstall không xóa ordinary WordPress `post`/`page` record và không xóa/rewrite user-authored `post_content`.**

Rollback authoritative là restore matching database + private upload backup. Migration settings snapshot chỉ là aid bổ sung, không phải full database rollback.
