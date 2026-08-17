# Quyền riêng tư và thời gian lưu dữ liệu của Cresco Canvas

## Ownership dữ liệu

Cresco Canvas phân biệt resource do plugin sở hữu với content WordPress do người dùng tạo.

Cresco-owned resource gồm:

- private `cresco_submission` post cho form submission;
- private `cresco_upload` record và file upload tương ứng;
- `cresco_revision` Session revision;
- Cresco option, migration state/backup, webhook failure metadata và scoped transient;
- Cresco-specific post/user metadata được `Lifecycle\\UninstallPolicy` liệt kê.

WordPress `post` và `page` bình thường **không thuộc Cresco ownership**. User-authored `post_content` không bao giờ là target của uninstall cleanup.

## Form submissions

Form chỉ lưu submission khi signed server-authored config bật `storeSubmissions`.

Private stored record có thể gồm:

- sanitized field values;
- owning form ID;
- deletion timestamp từ retention policy.

Default retention là **30 ngày**. Signed form config có thể chọn 1–365 ngày theo contract hiện hành. Daily retention job xóa expired Cresco submissions theo bounded batch và cascade private upload liên kết.

Email/webhook có thể truyền submitted values tới destination do site administrator cấu hình. Site operator chịu trách nhiệm về lawful recipient, retention và data-processing arrangement bên ngoài Cresco.

## Uploaded files

Form upload mới được lưu trong Cresco private storage ngoài web document root và có private `cresco_upload` record. Chúng không phải public Media Library URL.

Mỗi upload lưu expiration metadata; sau khi submission được lưu, upload có thể liên kết submission ID. Daily cleanup xóa expired orphan/private upload record/file theo bounded batch.

Privacy erasure của matching submission cũng xóa linked private upload.

Legacy Media Library attachment có `_cresco_form_upload=1` có thể vẫn được nhận diện để cleanup/erasure compatibility.

## Webhook retry và failure log

Failed webhook delivery lưu opaque retry token ngắn hạn trong WordPress transient. Transient chỉ chứa minimum delivery state cần để retry và có expiry theo contract (nguồn hiện tại ghi một giờ).

Cron argument chỉ mang retry token + attempt number, không mang form payload hoặc webhook secret.

Failure log chỉ nên chứa operational metadata như form ID, destination host, attempt, response status/reason và timestamp.

Không log submitted values, full destination query string, webhook secret, Authorization header, cookie, CAPTCHA secret hoặc password.

## CSV exports

CSV export là administrator action được bảo vệ bằng `manage_options` và admin nonce theo implementation hiện hành. Export có record/cell bound và neutralize formula-like spreadsheet cell trước output.

CSV có thể chứa private submission data; administrator phải lưu/truyền theo privacy policy của site.

## WordPress personal-data exporter/eraser

Cresco đăng ký WordPress personal-data exporter và eraser cho form submission.

Exporter:

- normalize requested email;
- tìm private Cresco submissions theo bounded page;
- recursively match email trong submitted values;
- trả matching field theo WordPress privacy-export format.

Eraser:

- dùng safe paging/marker strategy để không skip record khi delete;
- remove matching private submission;
- remove linked private/legacy Cresco upload;
- report `items_removed=true` khi thực sự xóa.

Không ordinary WordPress Page/Post nào bị Cresco privacy eraser xóa.

## Deactivation và reactivation

Deactivation preserve Cresco data. Nó remove migration lock và unschedule Cresco background work theo lifecycle contract.

Reactivation chạy idempotent migration check và recreate periodic job khi cần.

## Uninstall

Mặc định uninstall **preserve data**. Scheduled Cresco job được clear nhưng stored Cresco data chỉ bị xóa khi administrator đã explicit opt-in `removeDataOnUninstall` theo policy.

Explicit cleanup chỉ xóa resource trong Cresco ownership allowlist. Không xóa `post`/`page` bình thường và không alter user-authored `post_content`.

Multisite cleanup xử lý site riêng biệt bằng bounded batch + `switch_to_blog()`/`restore_current_blog()`. Cleanup decision của site này không được authorize xóa data site khác.

## Trách nhiệm của site operator

Trước khi thu production form data, cần document:

1. mỗi form thu gì và vì sao;
2. submission storage có bật không;
3. retention period;
4. external email/webhook recipient/processor;
5. private upload directory + backup policy;
6. cách xử lý WordPress privacy export/erase request;
7. uninstall nên preserve hay explicit cleanup Cresco data.
