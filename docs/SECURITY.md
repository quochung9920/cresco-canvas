# Security Model của Cresco Canvas

Tài liệu này ghi production security boundary cho Cresco Canvas 1.0. Nó tập trung vào authentication, authorization, request bounds, public abuse controls, outbound network restriction, upload policy và REST inventory phải giữ đúng qua các release.

## WordPress REST authentication model

Authenticated Cresco REST endpoint không thêm một plugin nonce layer thứ hai chỉ để lặp lại WordPress REST cookie authentication. WordPress REST cookie auth validate REST nonce trước `permission_callback`; cơ chế như Application Password có authentication path riêng.

Vì vậy Cresco coi route `permission_callback` + WordPress capability checks là authorization boundary.

Public endpoint chỉ tồn tại khi anonymous frontend operation thật sự cần. Chúng không dùng WordPress nonce như authentication mechanism; thay vào đó dùng signed server-authored payload khi phù hợp, input/resource bounds, rate limit và idempotency.

## Global request bounds

`CrescoCanvas\\Security\\SecurityHardening` chạy trước Cresco REST callback.

Theo security contract hiện tại:

- Mutating `/cresco-canvas/v1/*` request bị cap **1 MiB** trừ route có cap nhỏ hơn.
- `/forms/submit`: **256 KiB**, tối đa **50 fields**, **12 requests/phút** mỗi anonymous identity/form.
- `/forms/submit-multipart`: **8 MiB**, tối đa **5 files**, mỗi file tối đa **5 MiB**, **8 requests/phút**.
- `/forms/verify-captcha`: **16 KiB**, CAPTCHA token tối đa **4096 bytes**, **20 requests/phút**.
- `/dynamic/interactive-query`: **128 KiB**, page tối đa **100**, tối đa **3 taxonomy filters**, **12 terms/filter**, **40 requests/phút**.
- `/dynamic/facet-counts`: **128 KiB**, tối đa **3 taxonomy filters**, **12 terms/filter**, **3 requests/phút**; response cache 60 giây với canonical request key.
- Form submit có thể nhận `X-Cresco-Idempotency-Key`; key kết hợp request fingerprint và retention 10 phút theo current contract.
- Cresco Session giới hạn document theo sanitizer hiện hành; security baseline này ghi **500 nodes**, nesting depth **12**, cùng bounded per-widget Custom CSS.

Public rate key chỉ chứa keyed hash của request identity, không lưu raw source IP làm key công khai.

## REST route inventory

`Authentication / capability` dưới đây mô tả effective gate. “Core REST nonce” nghĩa Cresco không duplicate WordPress cookie-auth nonce validation.

| Route | Methods | Authentication / capability | Nonce / signature | Bound chính | Output/leakage policy |
| --- | --- | --- | --- | --- | --- |
| `/cresco-canvas/v1/settings` | GET, POST/PUT/PATCH | Authenticated; `edit_theme_options` | Core REST nonce | Write <= 1 MiB; allowlist/sanitizer | Site design settings; không credential |
| `/cresco-canvas/v1/settings/import-preview` | POST | Authenticated; `edit_theme_options` | Core REST nonce | <= 1 MiB; importer sanitizer | Sanitized preview |
| `/cresco-canvas/v1/settings/reset` | POST | Authenticated; `edit_theme_options` | Core REST nonce | <= 1 MiB | Sanitized defaults |
| `/cresco-canvas/v1/design-tokens` | GET | Authenticated; `edit_theme_options` | Core REST nonce | Read-only | Design token catalog; không secret |
| `/cresco-canvas/v1/site-identity` | GET, POST/PUT/PATCH | Authenticated; `edit_theme_options` | Core REST nonce | Write <= 1 MiB; image ID validate | Site identity only |
| `/cresco-canvas/v1/page-settings/{postId}` | GET, POST | Authenticated; `edit_post(postId)` trên Page | Core REST nonce | <= 1 MiB; Page Settings sanitizer | Page-owned settings |
| `/cresco-canvas/v1/session/{postId}` | GET, POST | Authenticated; `edit_post(postId)` trên Page | Core REST nonce | <= 1 MiB; Session limits/contracts | Full editor-only Cresco document |
| `/cresco-canvas/v1/session/validate` | POST | Authenticated; `edit_pages` | Core REST nonce | <= 1 MiB; Session validator | Sanitized Session/checksum/count |
| `/cresco-canvas/v1/ai-context/{postId}` | GET | Authenticated; `edit_post(postId)` | Core REST nonce | Read-only | Global/widget/session editor context |
| `/cresco-canvas/v1/ai-interchange/{postId}/context` | POST | Authenticated; `edit_post(postId)` | Core REST nonce | <= 1 MiB; validated Session/scope | Sanitized AI context envelope |
| `/cresco-canvas/v1/ai-interchange/{postId}/validate` | POST | Authenticated; `edit_post(postId)` | Core REST nonce | <= 1 MiB; Session/Patch validator | Candidate Session + bounded Diff; không execute |
| `/cresco-canvas/v1/history/{postId}` | GET | Authenticated; `edit_post(postId)` | Core REST nonce | Max 50 revisions | Revision metadata; sensitive author info capability-gated |
| `/cresco-canvas/v1/history/{postId}/{revisionId}/restore` | POST | Authenticated; `edit_post(postId)` + ownership | Core REST nonce | Re-sanitize stored revision | Restore result/checksum |
| `/cresco-canvas/v1/templates/catalog` | GET | Authenticated; `edit_pages` | Core REST nonce | Bounded bundled catalog | Bundled template data |
| `/cresco-canvas/v1/components` | GET, POST | Authenticated; `edit_pages` + per-item checks | Core REST nonce | POST <= 1 MiB; executable blocks reject | Component metadata/result |
| `/cresco-canvas/v1/site-kit` | GET, POST | Authenticated; `edit_theme_options` | Core REST nonce | <= 1 MiB; sanitized settings/allowlisted IDs | Site design/settings, không secret store |
| `/cresco-canvas/v1/theme-templates` | GET, POST | Authenticated; `edit_pages` | Core REST nonce | <= 1 MiB; safe block/session contract | Theme template data |
| `/cresco-canvas/v1/theme-templates/{id}` | POST/PUT/PATCH, DELETE | Authenticated; `edit_post(id)` | Core REST nonce | <= 1 MiB; validation | Authorized template data |
| `/cresco-canvas/v1/theme-builder/options` | GET | Authenticated; `edit_pages` | Core REST nonce | Bounded catalogs | Public content-type metadata |
| `/cresco-canvas/v1/theme-builder/diagnostics` | GET | Authenticated; `edit_pages` | Core REST nonce | Bounded template scan | Editor-only diagnostics |
| `/cresco-canvas/v1/dynamic/options` | GET | Authenticated; `edit_pages` | Core REST nonce | Bounded catalogs | Type/query metadata |
| `/cresco-canvas/v1/dynamic/query-preview` | POST | Authenticated; `edit_pages` | Core REST nonce | <= 1 MiB; bounded args | Caller-readable IDs/titles/URLs |
| `/cresco-canvas/v1/dynamic/field-inspect` | POST | Authenticated; `edit_pages` + `edit_post(postId)` | Core REST nonce | <= 1 MiB; key/source sanitized | Type/count, không raw field value |
| `/cresco-canvas/v1/dynamic/acf-fields` | GET | Authenticated; `edit_pages` | Core REST nonce | Bounded schema catalog | Field definition, không value |
| `/cresco-canvas/v1/dynamic/advanced-query-options` | GET | Authenticated; `edit_pages` | Core REST nonce | Bounded author/options | Query metadata |
| `/cresco-canvas/v1/dynamic/advanced-query-preview` | POST | Authenticated; `edit_pages` | Core REST nonce | <= 1 MiB; bounded rows/filters/IDs | Readable result metadata |
| `/cresco-canvas/v1/dynamic/interactive-query` | POST | **Public** | HMAC-signed server-authored query | 128 KiB; rate/page/filter bounds | Rendered public post markup/counts |
| `/cresco-canvas/v1/dynamic/facet-counts` | POST | **Public** | HMAC-signed query | 128 KiB; strict filter/rate/cache bounds | Public aggregate counts |
| `/cresco-canvas/v1/dynamic/diagnostics/{id}` | GET | Authenticated; `edit_post(id)` | Core REST nonce | Structural scan one post | Diagnostic code/instance only |
| `/cresco-canvas/v1/forms/submit` | POST | **Public** | Signed form config + honeypot + CAPTCHA khi required | 256 KiB; 50 fields; field bounds; 12/min | Success + validated redirect; không echo private data |
| `/cresco-canvas/v1/forms/submit-multipart` | POST | **Public** | Cùng signed form boundary | 8 MiB; 5 files; 5 MiB/file; 8/min | Success + validated redirect |
| `/cresco-canvas/v1/forms/verify-captcha` | POST | **Public** | Provider token server validation | 16 KiB; token <=4096; 20/min | Boolean/generic failure |
| `/cresco-canvas/v1/forms/diagnostics/{postId}` | GET | Authenticated; `edit_post(postId)` | Core REST nonce | Bounded structural scan | Form issues; không submission values |

### WordPress REST surface ngầm định

Custom post types/resource có `show_in_rest => true` vẫn có thể được WordPress Core expose qua Core REST controller. Những surface này dùng WordPress capability/KSES và không phải anonymous Cresco custom endpoint. Chúng vẫn cần compatibility/security regression testing.

## Form input validation

Browser không bao giờ là validation authority.

- Field name normalize + bound.
- Tối đa 50 fields.
- Scalar/textarea/email/URL/number/date/consent/choice có sanitizer/validator phù hợp.
- Email dùng WordPress email validation; URL được sanitize/validate; number phải finite và tôn trọng signed min/max.
- Multi-value chỉ cho declared checkbox group và bị bound.
- Required validation chạy server-side.
- Hidden/calculated value vẫn là untrusted submitted value và validate theo signed schema.
- Honeypot/CAPTCHA check phải diễn ra trên actual submit endpoint, không chỉ browser/preflight.
- Stored submission là private Cresco-owned record có ownership/retention metadata.

## File upload policy

Accepted extension hiện tại giới hạn JPEG, PNG, GIF, WebP, PDF, TXT, CSV. SVG và executable/server-config format không được nhận.

Validation kết hợp:

- extension allowlist;
- filename inspection;
- WordPress extension/type checks;
- server-side MIME/content inspection;
- format-specific checks;
- dangerous secondary extension / executable marker / polyglot rejection.

Accepted file **không phải Media Library attachment** theo default current private-upload path. File được copy tới random filename trong Cresco private directory ngoài `ABSPATH` và server `DOCUMENT_ROOT` khi có thể.

Nếu không xác lập được non-web-served path, storage phải fail closed và operator cần cấu hình `CRESCO_CANVAS_PRIVATE_UPLOAD_DIR` phù hợp.

Protected download yêu cầu authenticated admin-post flow, nonce và capability/ownership phù hợp; response dùng no-store/nosniff/CSP sandbox headers theo current implementation.

## Webhook SSRF và delivery policy

Webhook destination phải:

- HTTPS;
- không URL credentials;
- port 443 trừ khi administrator allowlist;
- không localhost, `.local`, loopback, RFC1918/private, link-local, reserved, metadata service hoặc private/reserved IPv6;
- DNS resolve thành address công khai; mọi A/AAAA returned phải public.

Mỗi delivery/retry revalidate destination ngay trước request. Redirect bị disable (`redirection = 0`) để tránh jump vào private network.

WordPress HTTP request dùng SSL verification, bounded timeout và bounded response size theo contract.

Retry bị bound. Cron argument chỉ chứa opaque retry token + attempt, không payload/secret. Failure log không chứa request body, auth/cookie, webhook secret hoặc submitted values.

DNS validation giảm SSRF risk nhưng không loại hết resolver/connection TOCTOU. Production host nên có outbound firewall/egress policy khi bật webhook.

## CSV export policy

CSV export yêu cầu admin capability/nonce theo implementation, có record/cell bound. Cell có ký tự formula-leading như `=`, `+`, `-`, `@` sau whitespace/control phải được neutralize trước serialization.

## Dynamic-query resource policy

Advanced query dùng allowlisted public post type, bounded rows/page/offset/ID/filter/term/search/meta strings và bounded nesting/loop policy.

Public query/facet request cần signed server-authored payload + rate limit. Public response dùng canonical cache key và invalidation generation để tránh transient scan không giới hạn.

## AI/import security boundary

- Mọi Cresco write/import request bị byte-bound trước callback.
- Session enforce known schema/version, unique IDs, widget catalog, node/depth budget và sanitized style.
- Custom CSS phải widget-scoped và reject out-of-contract/global/resource/executable constructs.
- Component/theme import reject executable Core block/unknown third-party content theo allowlist.
- AI context/validate edit-post scoped, byte-bounded và đi qua Session/Patch validators.
- Site Kit/Global setting đi qua canonical sanitizer/allowlist.

Không đưa password, token, CAPTCHA secret, webhook secret, Authorization header, cookie hoặc private submission value vào diagnostics/log. Dùng `SecurityHardening::redact_sensitive()` trước khi log arbitrary structured diagnostic data.

## Operational verification

Trước production release, chạy security/lifecycle PHP tests, WordPress compatibility matrix, Plugin Check, E2E và real web-server upload/download test.

Cần verify private upload directory nằm ngoài Nginx/Apache document root và không execute script.

Source contract/automation không thay thế penetration testing, production-like egress test hoặc exact-artifact release evidence.
