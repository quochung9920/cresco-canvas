# Commercial hardening pass — 2026-08-14

> **Tài liệu lịch sử theo thời điểm.** Năm nâng cấp dưới đây được áp dụng sau audit trong [`AUDIT_2026-08.md`](AUDIT_2026-08.md), tập trung vào khoảng cách giữa một plugin “chạy được” và một sản phẩm thương mại có thể ship.
>
> Các con số/check result bên dưới là evidence của đúng pass này, không phải guarantee cho current `main` nếu code đã thay đổi.

## 1. Update channel: transport, origin và integrity

`includes/Commercial/UpdateManager.php` từng lấy `packageUrl` từ remote manifest, đi qua `esc_url_raw`, rồi giao cho WordPress download/execute. Khi đó chưa có transport requirement, origin constraint hoặc integrity check — dù release pipeline đã tạo `SHA256SUMS`.

Rủi ro: compromised manifest host hoặc plaintext URL trên hostile network có thể dẫn đến arbitrary code install trên customer sites.

Ba gate được thêm giữa manifest và install:

| Gate | Rule |
| --- | --- |
| Transport | Manifest/package URLs phải dùng `https`. `secure_url()` trả `''` cho scheme khác |
| Origin | Package host phải bằng manifest host, không cho manifest redirect install sang host khác |
| Integrity | SHA-256 của archive phải match digest trong manifest, kiểm bằng `hash_equals` tại `upgrader_pre_download` |

Update có manifest không khai digest sẽ **bị từ chối**, không được cài unverified.

Signature verification vẫn là future work. Hook/seam `cresco_canvas_update_verify_package` cho phép distributor có signing infrastructure thêm verification mà không patch class này.

Cùng change này sửa performance defect: `manifest()` từng thực hiện HTTP request timeout 10 giây mỗi lần `pre_set_site_transient_update_plugins` chạy, không cache. Endpoint unreachable có thể làm admin page chậm.

Manifest được cache 6 giờ; failure cache 15 phút.

**Evidence được ghi trong pass:** 23 functional checks cho transport, origin pinning, digest normalization và manifest validation; mirrored trong `tests/php/UpdateSecurityTest.php`.

---

## 2. Translation catalogue

Plugin header đã khai `Domain Path: /languages` và gọi `load_plugin_textdomain()`. Khoảng 1,800 strings đã được bọc translation functions, nhưng `languages/` chưa tồn tại và không có extractor — nghĩa là support được quảng bá nhưng thực tế chưa hoạt động.

`scripts/make-pot.mjs` được thêm để extract từ PHP/JavaScript source.

WP-CLI không khả dụng trong environment của pass, nên script đọc cùng call signatures mà WP-CLI dùng:

- `__`
- `_e`
- `esc_html__`
- `esc_attr__`
- `_x`
- `_n`
- `_nx`
- các escaping variants tương ứng

Chỉ text domain `cresco-canvas` được lấy. Extractor cố ý lexical và bỏ call có argument là variable/concatenation vì các dạng đó vốn không translatable đúng cách.

`scripts/check-i18n.mjs` được thêm vào `check:quality` và fail khi catalogue thiếu, malformed hoặc stale so với source. `languages/` trở thành một phần release package.

**Evidence được ghi:** 1,257 unique strings từ 203 files; output parse sạch bằng `gettext-parser`. Gate cũng bắt được drift ngay trong pass khi các string mới làm catalogue stale.

---

## 3. Screen reader announcements

Studio có notice với `role="status"`, nhưng node được render conditionally — nó được tạo cùng lúc text xuất hiện. Live region vừa được insert cùng content thường không được assistive technology announce ổn định vì screen reader theo dõi established region cho change, thay vì rescanning new subtree.

Kết quả thực tế có thể làm save, save failure và recovery trở nên im lặng với screen-reader user.

`src/studio/announcer.ts` được thêm để install polite/assertive regions từ lúc load và mirror Studio notice vào đó:

- normal message → polite;
- error/warning → assertive, vì unsaved work cần được thông báo ngay.

`.cc-sr-only` trong `cresco-foundation.css` dùng `clip-path`, không dùng `display: none` vì `display: none` loại text khỏi accessibility tree.

Implementation quan sát DOM thay vì phụ thuộc editor internals, nên không cần chạm minified runtime.

**Evidence:** 9 jsdom tests trong `tests/unit/studio-announcer.test.ts`, gồm test bảo đảm region tồn tại trước khi text đến.

---

## 4. Diagnostic logging

Trước pass, report ghi rằng không có logging đáng kể: không `error_log`, không `WP_DEBUG` branch và chỉ ba `do_action` trong 118 classes. Khi user báo “editor froze”, gần như không có evidence để đọc.

`includes/Support/Logger.php` được thêm với hai constraint để có thể ship an toàn:

- chỉ ghi log khi `WP_DEBUG` bật, tránh production site chịu cost/log growth không kiểm soát;
- context được scrub trước khi ghi, vì support log thường được paste vào ticket và secret leak qua log là real disclosure.

Mỗi entry vẫn fire `cresco_canvas_log` bất kể `WP_DEBUG`, tạo integration point cho monitoring/APM thay vì buộc parser đọc file log.

**Evidence:** redaction được cover trong update-security suite; `token`, `apiKey` và `client_secret` bị thay trong khi ordinary context vẫn giữ.

---

## 5. Object cache và extension surface

Report ghi `wp_cache_*` chưa xuất hiện trong 118 classes. Trên site không có persistent object cache, mỗi transient write trở thành một `wp_options` row; dynamic query endpoint dưới traffic có thể làm bảng này thành bottleneck.

`includes/Support/ObjectCache.php` được thêm:

- dùng object cache khi site có persistent cache;
- fallback về transients khi không có.

`QueryCache` chuyển sang dùng helper này và memoize cache generation. Option generation cố ý không autoload nên trước đó mỗi read là một query riêng, kể cả read/write path của cacheable request.

Hai extension point được thêm để integration không phải dùng output buffering:

- `cresco_canvas_rendered_document`
- `cresco_canvas_compiled_css`

CSS filter append bên trong scoped selector namespace do compiler tạo, an toàn hơn việc enqueue stylesheet ngoài scope.

---

## Verification summary của pass

- 11 pre-existing `check:*` gates được ghi là green trước/sau; thêm `check:i18n` và green.
- `check:startup-hardening`, `lint:php`, `lint:runtime`, `lint:md` green.
- JS unit tests: 10 suites, 55 tests, 0 failures.
- PHP syntax: 226 files pass.
- `npm run build` chạy hai lần; `build/` không có unintended modification.

### Một gate còn đỏ và không phát sinh từ pass này

`npm run typecheck` fail với **14 errors, giống hệt trước và sau change**, được đo bằng stash comparison.

Không lỗi nào nằm trong files của pass. Chúng nằm ở:

- `src/editor/*`;
- axe result typing trong `tests/e2e/accessibility-release.spec.ts`;
- ba `tests/unit` fixtures drift khỏi type.

Vì các gate khác đã green, `check:quality` vẫn fail chỉ vì typecheck. Report kết luận sửa 14 lỗi này là bước rẻ nhất còn lại để `check:quality` trở thành signal thật sự.

---

## Những phần chưa làm trong pass

- Signature verification cho updates; seam tồn tại nhưng signing infrastructure chưa có.
- `.json` translation bundles cho JavaScript; cần actual `.po` translations trước.
- Frontend skip-link và `sr-only` utility cho public pages; editor đã có nhưng rendered page chưa.
- Object cache adoption ngoài `QueryCache`.

## Cách dùng report hiện nay

Đây là hardening evidence của 2026-08-14. Khi kiểm tra current implementation, xác nhận lại source/tests hiện tại trước khi kết luận một finding hoặc “not done” vẫn còn đúng.