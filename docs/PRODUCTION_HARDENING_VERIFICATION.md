# Production Hardening Verification Runbook

This runbook converts Cresco Canvas security, privacy, and lifecycle controls into repeatable release evidence. Source code, unit tests, and configured workflows are prerequisites; they are not substitutes for executing these checks against the exact release commit and artifact.

Use the status vocabulary from `docs/RELEASE_ENGINEERING.md`: `PASS`, `FAIL`, `NOT RUN`, `SKIPPED`, `INFRA FAILURE`, or `MANUAL REQUIRED`.

## 1. Source and isolated regression gate

From a clean checkout of the exact candidate commit:

```bash
npm ci
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
npm run lint:php
npm run lint:runtime
npm run check:production-hardening
phpunit -c phpunit.xml.dist
```

Record the commit SHA and command output. A failure in any command blocks the production-hardening gate.

## 2. REST authentication and public-route boundary

Use a clean WordPress site with the candidate plugin activated.

Verify every route documented in `docs/SECURITY.md` with the intended user class:

1. anonymous request;
2. authenticated subscriber or other user without the required capability;
3. authorized editor/administrator as appropriate;
4. cookie-authenticated request with an invalid/missing WordPress REST nonce where Core cookie authentication requires one.

Expected results:

- editor/admin routes reject unauthorized callers before returning private editor/session/submission data;
- only the documented form/query public routes accept anonymous requests;
- public routes still enforce signed payloads, size/shape limits, rate limits, and route-specific validation;
- no response exposes credentials, private submission values, cookies, authorization headers, or stack traces.

Capture route, caller, HTTP status, and a redacted response sample.

## 3. Payload, rate, and idempotency verification

For each public form/query route, test a valid request and requests just above the documented bounds.

Form submission idempotency must satisfy all of these:

1. first request with a new `X-Cresco-Idempotency-Key` can enter processing;
2. a concurrent request with the same form, fingerprint, and key is rejected as duplicate while the first request is pending;
3. if the first request finishes with a non-2xx response, the pending key is released so a corrected retry can proceed;
4. after a successful 2xx response, the same form/fingerprint/key remains duplicate-protected for the completion TTL;
5. no submitted field values or secrets are stored in the idempotency/rate transient key itself.

Record HTTP status and timing; do not store real personal data in the test fixture.

## 4. Private upload storage

Test on the actual web-server family used for release smoke (Apache, Nginx, IIS/XAMPP where applicable).

Cresco must store accepted uploads outside both the WordPress root and the web server document root. If the automatic candidate path is not safe/writable, configure `CRESCO_CANVAS_PRIVATE_UPLOAD_DIR` to an absolute non-web-served path.

Example local Windows/XAMPP configuration in `wp-config.php`:

```php
define( 'CRESCO_CANVAS_PRIVATE_UPLOAD_DIR', 'C:/xampp/cresco-canvas-private' );
```

Do not place this directory under `C:/xampp/htdocs`.

Verify:

- direct HTTP requests cannot address the stored file;
- the directory cannot execute PHP/scripts;
- protected download requires the Cresco nonce and appropriate capability/submission ownership;
- responses include no-store/nosniff protections;
- file mode/ACL is as restrictive as the host supports;
- deleting/erasing a submission removes linked Cresco private uploads as documented;
- expired orphan uploads are removed by bounded retention cleanup.

Hostile fixtures must include at least:

- `file.php.jpg` and another executable double extension;
- extension/MIME mismatch;
- PHP-like payload in an allowed text extension;
- binary control bytes in text/CSV;
- malformed/active PDF actions or JavaScript;
- an image with detectable appended polyglot/trailing content;
- an oversized file and more than the allowed file count.

All hostile fixtures must be rejected before private ownership records are committed.

## 5. Webhook SSRF and delivery

Use controlled endpoints and DNS records; never point these tests at unrelated private systems.

Verify rejection of:

- HTTP/non-TLS URLs;
- URL credentials;
- non-allowlisted ports;
- localhost and `.local` names;
- IPv4 loopback, RFC1918/private, link-local, reserved, and metadata-service addresses;
- IPv6 loopback, unique-local, link-local, and reserved addresses;
- a hostname with a mixture of public and private A/AAAA answers;
- an unresolved hostname.

Verify accepted public destinations are revalidated immediately before every initial delivery/retry. Redirects must remain disabled, TLS verification enabled, timeout bounded, and response body size capped.

Record that failure logs contain only operational metadata. They must not contain submitted values, full secret-bearing URLs, webhook secrets, authorization/cookie headers, CAPTCHA secrets, or passwords.

DNS validation reduces SSRF risk but cannot eliminate resolver/connection TOCTOU behavior. Production hosting should enforce outbound firewall/egress policy when webhooks are enabled.

## 6. CSV export

Create test submissions whose exported cells begin, after whitespace/control characters, with:

```text
=1+1
+SUM(1,1)
-1+1
@SUM(1,1)
```

Export as an authorized administrator and inspect the resulting CSV in at least one spreadsheet application. Each dangerous cell must be neutralized as text rather than executed as a formula.

Also verify:

- unauthorized users cannot export;
- the admin nonce is required;
- row and per-cell byte caps are enforced;
- exported private data is not written to a public plugin cache or debug log.

## 7. Privacy and retention

Using synthetic personal data:

1. create stored form submissions with an email address;
2. run the WordPress personal-data exporter and verify only matching Cresco submission data is returned;
3. run the eraser and verify matching Cresco submissions and linked private uploads are removed;
4. verify ordinary WordPress posts/pages remain unchanged;
5. exercise retention cleanup with expired and non-expired records.

Record before/after IDs and hashes where useful; do not commit real personal data to release evidence.

## 8. Migration, downgrade, and rollback

On a disposable environment backed by a real database:

1. install the pinned historical supported fixture;
2. create representative settings, Session data, revisions, and synthetic form records;
3. back up the database and private upload directory;
4. install the exact candidate ZIP;
5. verify migration reaches the expected schema and data remains usable;
6. induce/reproduce a migration failure fixture and verify retry resumes from the last completed version;
7. run an older plugin against a newer stored schema and verify Cresco enters compatibility pause without writing the older format;
8. restore the pre-upgrade database/private-file backup and matching package, then confirm the site returns to the recorded pre-upgrade state.

Never simulate rollback by manually lowering `cresco_canvas_db_version`.

## 9. Deactivation, uninstall, and multisite

Single site:

- deactivate/reactivate without data loss;
- confirm scheduled Cresco jobs are cleared/recreated as documented;
- uninstall with default preserve-data behavior;
- uninstall with explicit cleanup enabled.

For explicit cleanup, capture a normal WordPress Page with known `post_content` before uninstall and verify the page/body remains byte-for-byte unchanged afterward.

Multisite:

- per-site activate/deactivate;
- network activation across existing sites;
- create a new site while network-active;
- network upgrade/migration;
- network deactivation;
- uninstall with different `removeDataOnUninstall` choices on different sites.

One site's cleanup choice must never authorize deletion of another site's Cresco data.

## 10. Evidence record

For each candidate, archive a redacted record containing:

- candidate commit SHA;
- exact ZIP SHA-256;
- WordPress/PHP/web-server versions;
- single-site or multisite mode;
- each section status;
- command/test output or manual evidence reference;
- unresolved defect IDs and owners;
- reviewer/date.

Do not set any P0 hardening item to `PASS` solely because the source gate or unit test exists. Stable `1.0.0` remains blocked until the exact artifact and required manual/live checks have objective evidence.
