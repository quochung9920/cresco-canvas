# Commercial hardening pass — 2026-08-14

Five upgrades applied after the audit in [AUDIT_2026-08.md](AUDIT_2026-08.md), targeting the
areas that separate a working plugin from a shippable commercial product. Each is verified
rather than asserted; the evidence is named alongside the change.

## 1. Update channel: transport, origin, and integrity

`includes/Commercial/UpdateManager.php` took `packageUrl` from a remote manifest, passed it
through `esc_url_raw`, and handed it to WordPress to download and execute. There was no
transport requirement, no origin constraint, and no integrity check — the release pipeline
produced `SHA256SUMS` that nothing ever verified. A compromised manifest host, or a plaintext
URL on a hostile network, meant arbitrary code installed on every customer site.

Three gates now stand between a manifest and an install:

| Gate | Rule |
| --- | --- |
| Transport | Manifest and package URLs must be `https`. `secure_url()` returns `''` for anything else. |
| Origin | The package host must equal the manifest host, so a manifest cannot redirect installation elsewhere. |
| Integrity | The archive's SHA-256 must match the manifest's declared digest, checked with `hash_equals` in `upgrader_pre_download`. |

An update whose manifest declares no digest is **refused**, not installed unverified. Signature
verification stays future work, and `cresco_canvas_update_verify_package` is the seam for it: a
distributor with signing infrastructure adds that check without patching this class.

The same change fixes a performance defect found while reading: `manifest()` performed a
10-second-timeout HTTP request on every `pre_set_site_transient_update_plugins` fire, with no
caching. An unreachable endpoint stalled admin pages. The manifest is now cached for 6 hours,
and failures for 15 minutes.

*Verified:* 23 functional checks covering transport, origin pinning, digest normalisation, and
manifest validation. Mirrored as `tests/php/UpdateSecurityTest.php`.

## 2. Translation catalogue

The plugin header declared `Domain Path: /languages` and called `load_plugin_textdomain()`, and
roughly 1,800 strings were already wrapped in translation functions — but `languages/` did not
exist and no extractor was configured. The advertised support could not work.

`scripts/make-pot.mjs` extracts from PHP and JavaScript sources. WP-CLI is not available in this
environment, so it reads the same call signatures WP-CLI does: `__`, `_e`, `esc_html__`,
`esc_attr__`, `_x`, `_n`, `_nx` and their escaping variants, filtered to the `cresco-canvas`
domain. It is lexical by design and skips calls whose arguments are variables or concatenations,
because those are not translatable anyway.

`scripts/check-i18n.mjs` joins `check:quality` and fails when the catalogue is missing,
malformed, or behind the sources — so user-facing strings cannot ship untranslatable.
`languages/` is now part of the release package.

*Verified:* 1,257 unique strings from 203 files; the output parses cleanly under
`gettext-parser`. The gate caught its first real drift during this very pass, when strings added
for the changes below left the catalogue stale.

## 3. Screen reader announcements

Studio does mark its notice element `role="status"`, but renders it conditionally — the node is
created at the moment its text appears. A live region inserted together with its content is
generally not announced, because assistive technology watches established regions for changes
rather than rescanning new subtrees. In practice, saving, save failures, and recovery were
silent.

`src/studio/announcer.ts` installs polite and assertive regions at load and mirrors Studio's own
notice into them, so a screen reader user hears exactly what a sighted user reads. Errors and
warnings announce assertively, because unsaved work is not something to mention politely. The
`.cc-sr-only` utility lives in `cresco-foundation.css` and uses `clip-path` rather than
`display: none`, which would remove the text from the accessibility tree entirely.

It watches the DOM rather than editor internals, so no minified runtime was touched.

*Verified:* 9 jsdom tests in `tests/unit/studio-announcer.test.ts`, including that the region
exists before text arrives — the property the original markup lacked.

## 4. Diagnostic logging

There was no logging anywhere: no `error_log`, no `WP_DEBUG` branch, three `do_action` calls in
118 classes. A report of "the editor froze" left nothing to read.

`includes/Support/Logger.php` records diagnostics under two constraints that make it safe to
ship enabled. Nothing is written unless `WP_DEBUG` is on, so production sites pay nothing and no
log grows unattended. Context values are scrubbed first — log lines end up pasted into support
tickets, so a token reaching one is a real disclosure.

Every entry also fires `cresco_canvas_log` regardless of `WP_DEBUG`, which is where monitoring
and APM integrations belong rather than parsing a file.

*Verified:* redaction covered in the update-security suite; `token`, `apiKey`, and
`client_secret` are replaced while ordinary context survives.

## 5. Object cache and extension surface

`wp_cache_*` appeared nowhere in 118 classes. On a site without a persistent object cache, every
transient write is a `wp_options` row, and a dynamic query endpoint under traffic makes that
table the bottleneck.

`includes/Support/ObjectCache.php` routes through the object cache when the site has a
persistent one and falls back to transients otherwise, so both deployments get what suits them.
`QueryCache` now uses it, and memoises the cache generation — the option is deliberately not
autoloaded, so each read was its own query, on both the read and write path of every cacheable
request.

Two extension points were added where integrations previously had to resort to output
buffering: `cresco_canvas_rendered_document` and `cresco_canvas_compiled_css`. Appending through
the CSS filter keeps additions inside the scoped selector namespace the compiler established,
which an enqueued stylesheet cannot rely on.

## Verification summary

- All 11 pre-existing `check:*` gates green before and after; `check:i18n` added and green.
- `check:startup-hardening`, `lint:php`, `lint:runtime`, `lint:md` green.
- JS unit tests: 10 suites, 55 tests, 0 failures.
- PHP syntax: 226 files pass.
- `npm run build` run twice; `build/` shows no unintended modification.

### One gate remains red, and it is not from this pass

`npm run typecheck` fails with **14 errors, identical before and after these changes**, measured
by stashing. None are in files touched here. They sit in `src/editor/*`, the axe result typing in
`tests/e2e/accessibility-release.spec.ts`, and three `tests/unit` fixtures that drifted from their
types.

This now matters more than it did. Every other gate is green, so `check:quality` fails on
typecheck alone — meaning the composite gate still cannot distinguish a regression from the
baseline, for exactly one reason. Fixing those 14 errors is the cheapest remaining step to make
`check:quality` a real signal.

## Not done

- Signature verification for updates. The seam exists; signing infrastructure does not.
- `.json` translation bundles for JavaScript. These are generated from `.po` files, which
  require actual translations to exist first.
- Frontend skip-link and `sr-only` utility for public pages. The editor is covered; the rendered
  page is not.
- Object cache adoption beyond `QueryCache`.
