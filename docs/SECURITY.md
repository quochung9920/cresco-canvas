# Cresco Canvas Security Model

This document records the production security boundary for Cresco Canvas 1.0. It is intentionally narrower than the feature documentation: it identifies authentication, authorization, request bounds, public abuse controls, outbound-network restrictions, upload policy, and the REST inventory that must remain true across releases.

## WordPress REST authentication model

Authenticated Cresco REST endpoints do not add a second plugin nonce check. WordPress REST cookie authentication validates the REST nonce before the endpoint permission callback; non-cookie mechanisms such as Application Passwords authenticate without that browser nonce. Cresco therefore treats the route `permission_callback` and WordPress capability checks as the authorization boundary.

Public endpoints exist only where anonymous frontend operation is required. They do not rely on a WordPress nonce as an authentication mechanism. Instead they use signed server-authored payloads where applicable, strict input/resource bounds, rate limits, and idempotency controls.

## Global request bounds

`CrescoCanvas\\Security\\SecurityHardening` runs before Cresco REST callbacks.

- Every mutating `/cresco-canvas/v1/*` request is capped at **1 MiB** unless a tighter route-specific cap applies.
- `/forms/submit`: **256 KiB** JSON, at most **50 fields**, **12 requests/minute** per anonymous identity/form.
- `/forms/submit-multipart`: **8 MiB** multipart body, at most **5 files**, each file at most **5 MiB**, **8 requests/minute**.
- `/forms/verify-captcha`: **16 KiB**, CAPTCHA token at most **4096 bytes**, **20 requests/minute**.
- `/dynamic/interactive-query`: **128 KiB**, page at most **100**, at most **3 taxonomy filters** and **12 terms/filter**, **40 requests/minute**.
- `/dynamic/facet-counts`: **128 KiB**, at most **3 taxonomy filters** and **12 terms/filter**, **3 requests/minute**. Responses are cached for 60 seconds with a stable canonical request key.
- Form submit endpoints accept an optional `X-Cresco-Idempotency-Key`. The key is combined with a request fingerprint and retained for 10 minutes.
- Cresco Session additionally limits documents to **500 nodes**, nesting depth **12**, and bounded per-widget Custom CSS.

Public rate keys contain only a keyed hash of the request identity, not the source IP itself.

## REST route inventory

`Authentication / capability` below describes the effective endpoint gate after WordPress REST authentication. `Core REST nonce` means Cresco does not duplicate WordPress cookie-auth nonce validation.

| Route | Methods | Authentication / capability | Nonce / signature | Payload / resource bound | Sensitive output / leakage policy |
| --- | --- | --- | --- | --- | --- |
| `/cresco-canvas/v1/settings` | GET, POST/PUT/PATCH | Authenticated; `edit_theme_options` | Core REST nonce for cookie auth | Writes <= 1 MiB; settings allowlist/sanitizers | Site design settings; no credentials |
| `/cresco-canvas/v1/settings/import-preview` | POST | Authenticated; `edit_theme_options` | Core REST nonce | <= 1 MiB; importer schema and value sanitizers | Sanitized preview only |
| `/cresco-canvas/v1/settings/reset` | POST | Authenticated; `edit_theme_options` | Core REST nonce | <= 1 MiB | Sanitized defaults |
| `/cresco-canvas/v1/design-tokens` | GET | Authenticated; `edit_theme_options` | Core REST nonce | Read-only | Public-design token catalog; no secrets |
| `/cresco-canvas/v1/site-identity` | GET, POST/PUT/PATCH | Authenticated; `edit_theme_options` | Core REST nonce | Writes <= 1 MiB; image IDs validated | Site name/description/logo/icon only |
| `/cresco-canvas/v1/page-settings/{postId}` | GET, POST | Authenticated; must be able to `edit_post(postId)` and target a Page | Core REST nonce | Write <= 1 MiB; page-settings schema sanitizer | Page-owned settings |
| `/cresco-canvas/v1/session/{postId}` | GET, POST | Authenticated; `edit_post(postId)` on a Page | Core REST nonce | Write <= 1 MiB; max 500 nodes/depth 12; strict widget/style contract | Full Cresco page document; editor-only |
| `/cresco-canvas/v1/session/validate` | POST | Authenticated; `edit_pages` | Core REST nonce | <= 1 MiB; same Session validator | Sanitized session/checksum/count |
| `/cresco-canvas/v1/ai-context/{postId}` | GET | Authenticated; `edit_post(postId)` | Core REST nonce | Read-only | Global design, widget contract, current session; editor-only |
| `/cresco-canvas/v1/ai-interchange/{postId}/context` | POST | Authenticated; `edit_post(postId)` on a Page | Core REST nonce | <= 1 MiB; current/supplied Session passes strict Session validation; scope/target/mode contracts | Sanitized AI context envelope derived from editable page data |
| `/cresco-canvas/v1/ai-interchange/{postId}/validate` | POST | Authenticated; `edit_post(postId)` on a Page | Core REST nonce | <= 1 MiB; current Session sanitized; result must validate as Cresco Session or Cresco Patch | Validated candidate Session, bounded structured diff/checksums; no execution |
| `/cresco-canvas/v1/history/{postId}` | GET | Authenticated; `edit_post(postId)` | Core REST nonce | Max 50 revisions returned | Revision metadata; author email only when caller can `list_users` |
| `/cresco-canvas/v1/history/{postId}/{revisionId}/restore` | POST | Authenticated; `edit_post(postId)`; revision ownership checked | Core REST nonce | <= 1 MiB; stored revision re-sanitized before restore | Restore result/checksum only |
| `/cresco-canvas/v1/templates/catalog` | GET | Authenticated; `edit_pages` | Core REST nonce | Bounded bundled catalog | Bundled template markup; no secrets |
| `/cresco-canvas/v1/components` | GET, POST | Authenticated; `edit_pages`; per-item editability also checked on list | Core REST nonce | POST <= 1 MiB; executable/third-party block types rejected | Component metadata; create result only |
| `/cresco-canvas/v1/site-kit` | GET, POST | Authenticated; `edit_theme_options` | Core REST nonce | POST <= 1 MiB; settings sanitized; template IDs allowlisted | Cresco site design/settings, no secret store |
| `/cresco-canvas/v1/theme-templates` | GET, POST | Authenticated; `edit_pages` | Core REST nonce | POST <= 1 MiB; safe Core/Cresco block allowlist | Theme-template data for editors |
| `/cresco-canvas/v1/theme-templates/{id}` | POST/PUT/PATCH, DELETE | Authenticated; `edit_post(id)` | Core REST nonce | Write <= 1 MiB; safe-block validation | Theme-template data for authorized editor |
| `/cresco-canvas/v1/theme-builder/options` | GET | Authenticated; `edit_pages` | Core REST nonce | Bounded option catalogs | Public content-type metadata |
| `/cresco-canvas/v1/theme-builder/diagnostics` | GET | Authenticated; `edit_pages` | Core REST nonce | Up to 200 templates inspected | Diagnostics may derive from template content; editor-only |
| `/cresco-canvas/v1/dynamic/options` | GET | Authenticated; `edit_pages` | Core REST nonce | Bounded public content catalogs | Type/query metadata only |
| `/cresco-canvas/v1/dynamic/query-preview` | POST | Authenticated; `edit_pages` | Core REST nonce | <= 1 MiB; query normalized to bounded args | IDs/titles/URLs readable by caller |
| `/cresco-canvas/v1/dynamic/field-inspect` | POST | Authenticated; `edit_pages` plus `edit_post(postId)` inside callback | Core REST nonce | <= 1 MiB; key/source sanitized | Type/count only, never raw field value |
| `/cresco-canvas/v1/dynamic/acf-fields` | GET | Authenticated; `edit_pages` | Core REST nonce | Bounded field schema catalog | Field definitions only, not values |
| `/cresco-canvas/v1/dynamic/advanced-query-options` | GET | Authenticated; `edit_pages` | Core REST nonce | Authors limited to 100; fixed option catalogs | Query-dimension metadata |
| `/cresco-canvas/v1/dynamic/advanced-query-preview` | POST | Authenticated; `edit_pages` | Core REST nonce | <= 1 MiB; <=24 rows/page, <=3 tax filters, <=24 IDs/terms/filter, search/value lengths bounded | Readable result metadata only |
| `/cresco-canvas/v1/dynamic/interactive-query` | POST | **Public**; anonymous frontend query | HMAC-signed server-authored query payload | 128 KiB; 40/min; page <=100; 3 filters; 12 terms/filter; query rows <=24; 60s cache | Rendered public post markup + public counts only |
| `/cresco-canvas/v1/dynamic/facet-counts` | POST | **Public**; anonymous frontend facets | HMAC-signed server-authored query payload | 128 KiB; 3/min; 3 filters; 12 terms/filter; max 3 facets / 50 catalog terms per facet; 60s cache | Public aggregate counts only |
| `/cresco-canvas/v1/dynamic/diagnostics/{id}` | GET | Authenticated; `edit_post(id)` | Core REST nonce | Structural scan of one post | Diagnostic codes/instance IDs only |
| `/cresco-canvas/v1/forms/submit` | POST | **Public**; anonymous visitor submission | HMAC-signed form config; honeypot; CAPTCHA enforced when signed config requires it | 256 KiB; 50 fields; per-field size/type bounds; 12/min; optional 10-minute idempotency | Success message + validated redirect only; submitted private data is not echoed |
| `/cresco-canvas/v1/forms/submit-multipart` | POST | **Public**; anonymous visitor submission | Same signed form config/honeypot/CAPTCHA boundary | 8 MiB request; max 5 files; max 5 MiB/file; 8/min; private upload policy | Success message + validated redirect only |
| `/cresco-canvas/v1/forms/verify-captcha` | POST | **Public**; preflight CAPTCHA adapter | Provider token validated by server adapter | 16 KiB; token <=4096 bytes; 20/min | Boolean success/generic failure only |
| `/cresco-canvas/v1/forms/diagnostics/{postId}` | GET | Authenticated; `edit_post(postId)` | Core REST nonce | Visits max 500 blocks / reports max 100 issues | Structural form issues; no submission values |

### Implicit WordPress REST surfaces

`cresco_template` remains registered with `show_in_rest => true`, so WordPress Core exposes its normal post-type REST controller in addition to Cresco's Theme Builder routes. That surface uses WordPress post capabilities/KSES rather than Cresco's custom route callback. `wp_block` is also a WordPress Core REST resource used by synced components. These are not anonymous Cresco custom endpoints and must remain covered by WordPress Core capability/KSES regression testing.

## Form input validation

The browser is never trusted as the validation authority.

- Field names are normalized and bounded.
- At most 50 fields are accepted.
- Scalar text is bounded; textarea, email, URL, number, date, consent and choice fields have dedicated sanitization/validation paths.
- Email uses WordPress email validation; URL values are sanitized and validated; numeric values must be finite and respect signed min/max rules.
- Multi-value input is accepted only for declared checkbox groups and is bounded to 24 values.
- Required validation happens on the server.
- Hidden/calculated values are still treated as untrusted submitted values and validated against their signed schema.
- Honeypot and optional CAPTCHA checks occur on the actual submit endpoint, not only in browser JavaScript or a separate preflight call.
- Stored submissions are private Cresco-owned records with form ownership and deletion timestamps.

## File upload policy

Accepted extensions are restricted to JPEG, PNG, GIF, WebP, PDF, TXT and CSV. SVG and executable/server-config formats are not accepted.

Validation combines extension allowlisting, filename inspection, WordPress extension/type checking, server-side content/MIME inspection and format-specific checks. Dangerous secondary extensions, MIME spoofing, PHP-like/executable payload markers, binary text payloads, malformed PDFs and detectable appended image polyglots are rejected.

Accepted files are **not Media Library attachments**. They are copied to a random filename in a Cresco private directory outside both `ABSPATH` and the server `DOCUMENT_ROOT` when available. If Cresco cannot establish a path outside the web document root, storage fails closed and the operator must define `CRESCO_CANVAS_PRIVATE_UPLOAD_DIR` to an appropriate non-web-served path. Files are mode `0640` where supported and are referenced by an internal `cresco_upload` ownership record.

Download is through an authenticated admin-post handler requiring a nonce plus either `manage_options` or permission to edit the linked private submission. Responses use no-store, nosniff and CSP sandbox headers.

## Webhook SSRF and delivery policy

Webhook destinations must:

- use HTTPS;
- have no URL credentials;
- use port 443 unless explicitly allowlisted by the site administrator;
- not target localhost, `.local`, loopback, RFC1918/private, link-local, reserved, metadata-service or private/reserved IPv6 addresses;
- resolve to at least one address, with **every returned A/AAAA address** required to be public.

Every delivery/retry revalidates the destination immediately before the request. Redirects are disabled (`redirection = 0`), so a redirect cannot jump into a private network. Safe WordPress HTTP requests are used with SSL verification, an 8-second maximum timeout and a 64 KiB response cap.

Retries are bounded to three retries. Cron arguments carry only an opaque retry token and attempt number; the temporary delivery state expires after one hour. A stable delivery ID is sent so receivers can deduplicate. Failure logs keep only form ID, destination host, attempt, status/reason and timestamp. Request bodies, authorization/cookie headers, webhook secrets and submitted values are not logged.

DNS validation reduces SSRF risk but cannot eliminate all resolver/connection time-of-check/time-of-use behavior. Production hosts should also enforce outbound firewall/egress policy when webhook delivery is enabled.

## CSV export policy

CSV export requires `manage_options` and an admin nonce, is limited to 2,000 submissions, and caps each cell at 32 KiB. Cells whose first non-whitespace/control character is `=`, `+`, `-`, or `@` are prefixed with a single quote before CSV serialization to neutralize spreadsheet formula execution.

## Dynamic-query resource policy

Advanced queries use allowlisted public post types, bounded rows, bounded pages/offsets, bounded ID lists, bounded taxonomy clauses and terms, bounded search/meta strings, and a one-level loop policy where applicable. Public AJAX/facet requests require a signed server-authored query payload and are additionally rate-limited. Public dynamic responses use a stable canonical cache key with a per-site generation number; public post/term changes increment the generation to invalidate prior results without unbounded transient scans.

## AI/import security boundary

Cresco Session/import security remains owned by its existing subsystem, but the production boundary is:

- all Cresco write/import requests are byte-bounded before callback execution;
- Session documents enforce a known schema/version, stable unique IDs, a fixed widget catalog, max node/depth bounds and sanitized style values;
- widget Custom CSS must remain widget-scoped and rejects `@import`, `@media`, external `url()`, JavaScript/expression constructs, global selectors and malformed braces;
- component/theme imports reject executable Core blocks such as HTML/shortcode/freeform and reject unknown third-party blocks;
- AI Interchange context/validate endpoints are edit-post scoped, byte-bounded centrally, and route candidate output through the existing Cresco Session/Patch validators before it can be applied;
- Site Kit settings pass through the Global Design sanitizer and bundled template IDs are allowlisted.

No passwords, tokens, CAPTCHA secrets, webhook secrets, authorization headers, cookies or private submission values should be added to diagnostics/logs. Use `SecurityHardening::redact_sensitive()` before logging arbitrary structured diagnostic data.

## Operational verification

Before a production release, run the PHP security/lifecycle tests, WordPress compatibility matrix, Plugin Check, E2E suite and a real web-server upload/download test. Also verify that the configured private upload directory is outside Nginx/Apache document roots and cannot execute scripts.
