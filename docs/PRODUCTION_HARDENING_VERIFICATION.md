# Runbook Verification Production Hardening

Runbook này biến security/privacy/lifecycle control thành repeatable release evidence. Source, unit test và configured workflow là prerequisite; chúng **không thay thế execution trên exact release commit/artifact**.

Dùng status từ `RELEASE_ENGINEERING.md`: `PASS`, `FAIL`, `NOT RUN`, `SKIPPED`, `INFRA FAILURE`, `MANUAL REQUIRED`.

## 1. Source + isolated regression gate

Từ clean checkout của exact candidate:

```bash
npm ci
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
npm run lint:php
npm run lint:runtime
npm run check:production-hardening
phpunit -c phpunit.xml.dist
```

Record commit SHA + output. Command fail thì production-hardening gate fail.

## 2. REST authentication và public-route boundary

Trên clean WordPress có candidate plugin, verify mỗi route trong `SECURITY.md` với:

1. anonymous;
2. authenticated user thiếu capability;
3. authorized editor/admin;
4. cookie-auth request có invalid/missing REST nonce khi Core yêu cầu.

Expected:

- editor/admin route reject unauthorized caller trước khi leak private data;
- chỉ documented public form/query route nhận anonymous request;
- public route vẫn enforce signature, size/shape bound, rate limit, route validation;
- response không expose credential, submission private values, cookies, Authorization header hoặc stack trace.

Capture route, caller, HTTP status, redacted response sample.

## 3. Payload, rate và idempotency

Với mỗi public form/query route, test valid request + request vượt bound.

Form idempotency phải chứng minh:

1. request đầu với new `X-Cresco-Idempotency-Key` được process;
2. concurrent duplicate cùng form/fingerprint/key bị reject khi first pending;
3. nếu first kết thúc non-2xx, pending key được release để corrected retry;
4. successful 2xx giữ duplicate protection tới completion TTL;
5. transient/rate key không chứa submitted field value/secret.

Không dùng real personal data trong fixture.

## 4. Private upload storage

Test trên web-server family thực dùng cho release smoke.

Accepted upload phải nằm ngoài WordPress root và web document root. Nếu auto path không safe/writable, cấu hình `CRESCO_CANVAS_PRIVATE_UPLOAD_DIR` tới absolute non-web-served path.

Ví dụ Windows/XAMPP:

```php
define( 'CRESCO_CANVAS_PRIVATE_UPLOAD_DIR', 'C:/xampp/cresco-canvas-private' );
```

Không đặt dưới `C:/xampp/htdocs`.

Verify:

- direct HTTP không truy cập stored file;
- directory không execute PHP/script;
- protected download cần nonce + capability/ownership;
- response có no-store/nosniff;
- file permission/ACL hạn chế phù hợp host;
- delete/erase submission remove linked private upload;
- retention cleanup remove expired orphan upload theo bounded batch.

Hostile fixture tối thiểu:

- `file.php.jpg` và executable double extension;
- extension/MIME mismatch;
- PHP-like payload trong allowed text extension;
- binary control byte trong text/CSV;
- malformed/active PDF action/JavaScript;
- image có detectable appended polyglot/trailing content;
- oversized file và quá file-count limit.

Tất cả phải bị reject trước khi commit private ownership record.

## 5. Webhook SSRF và delivery

Dùng controlled endpoint/DNS, không test vào private system không liên quan.

Verify reject:

- HTTP/non-TLS;
- URL credentials;
- non-allowlisted port;
- localhost/`.local`;
- IPv4 loopback/RFC1918/private/link-local/reserved/metadata;
- IPv6 loopback/unique-local/link-local/reserved;
- hostname có cả public và private A/AAAA;
- unresolved hostname.

Accepted public destination phải revalidate ngay trước initial delivery/retry. Redirect disabled, TLS verification enabled, timeout + response body bounded.

Failure log chỉ operational metadata; không submitted values, secret-bearing URL, webhook secret, auth/cookie header, CAPTCHA secret, password.

DNS validation không loại hết TOCTOU; production nên có egress policy.

## 6. CSV export

Tạo synthetic cell bắt đầu bằng:

```text
=1+1
+SUM(1,1)
-1+1
@SUM(1,1)
```

Export bằng authorized admin và kiểm tra trong spreadsheet app. Cell nguy hiểm phải trở thành text, không execute formula.

Cũng verify unauthorized user bị chặn, nonce required, row/cell cap enforced và export private data không bị ghi vào public cache/debug log.

## 7. Privacy và retention

Với synthetic personal data:

1. tạo stored submission có email;
2. chạy WordPress personal-data exporter và verify chỉ matching Cresco submission;
3. chạy eraser và verify submission + linked private upload bị remove;
4. ordinary WordPress post/page không đổi;
5. exercise retention với expired/non-expired record.

Không commit real personal data vào release evidence.

## 8. Migration, downgrade và rollback

Trong disposable real-DB environment:

1. install pinned historical supported fixture;
2. tạo representative settings/Session/revision/synthetic form records;
3. backup DB + private upload;
4. install exact candidate ZIP;
5. verify migration đạt expected schema và data usable;
6. induce migration failure và verify retry từ last completed version;
7. chạy older plugin với newer stored schema và verify compatibility pause, không ghi old format;
8. restore pre-upgrade backup + matching package và confirm state quay lại.

Không rollback bằng cách manually hạ `cresco_canvas_db_version`.

## 9. Deactivation, uninstall và multisite

Single site:

- deactivate/reactivate không data loss;
- scheduled job clear/recreate đúng;
- uninstall default preserve-data;
- uninstall explicit cleanup.

Với explicit cleanup, capture normal Page `post_content` trước/sau và verify không bị thay đổi.

Multisite:

- per-site activate/deactivate;
- network activation;
- tạo site mới khi network-active;
- network upgrade/migration;
- network deactivation;
- uninstall với `removeDataOnUninstall` khác nhau giữa site.

Cleanup choice của site này không được authorize xóa Cresco data site khác.

## 10. Evidence record

Mỗi candidate cần archive redacted record gồm:

- candidate commit SHA;
- exact ZIP SHA-256;
- WordPress/PHP/web-server version;
- single-site/multisite;
- status từng section;
- command/test output hoặc manual evidence reference;
- unresolved defect ID + owner;
- reviewer/date.

Không set P0 hardening `PASS` chỉ vì source gate/unit test tồn tại. Stable `1.0.0` vẫn bị block cho tới khi exact artifact và required live/manual checks có objective evidence.
