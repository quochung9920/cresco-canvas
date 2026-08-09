# Known Limitations — 1.0.0-rc.1

Cresco Canvas `1.0.0-rc.1` is a release candidate. It is not a commercially certified stable release.

## Build and release evidence

- The release-quality branch introduces an authoritative transitional source tree for historically hand-maintained standalone runtimes (`runtime-src/build/`) plus clean-delete/rebuild verification. This architecture is not considered proven until the hosted source/build gate passes for the exact commit.
- `npm ci`, optimized Composer install, lint, unit/PHP tests, PHPCS, two-clean-checkout reproducibility, package verification, and dependency audits require passing hosted evidence for the exact release commit.
- The release pipeline defines a strict production ZIP allowlist, `SHA256SUMS`, a file-level SPDX inventory, and unsigned provenance metadata. Production security, privacy, and upgrade/rollback policy documents are included in the release allowlist. Artifact generation does not imply installability.
- The exact candidate ZIP must still pass the isolated clean WordPress install/edit/save/preview P0 gate before stable release.

## Widget Inspector and persistence

- Core-compatible and Cresco-specific style paths have broad implementation coverage, but the complete visible-control matrix still requires supported WordPress/browser runtime evidence.
- Save/reload, revisions, History, copy/paste, patterns, and legacy data compatibility are not considered commercially verified until their applicable gates pass.

## Editor workspace

- The current product uses the standalone Cresco Session editor. Browser and WordPress DOM/runtime differences can still expose freeze, focus, or rendering defects.
- Critical Chromium, Firefox, and WebKit flows are automated on the release branch; results remain `NOT RUN` until the workflow executes.
- Dedicated Edge validation remains manual.

## Global Design and Page Settings

- Global Design and Page Settings are implemented, but their compatibility across the supported WordPress/PHP/theme matrix requires objective release-run evidence.
- Site-wide lightbox and page-transition/preloader behavior remains limited where the current frontend runtime does not implement those controls.

## Forms and public endpoints

- Source-level production controls exist for REST payload/rate bounds, signed public form/query payloads, webhook HTTPS/private-network/DNS checks, disabled webhook redirects, upload extension/MIME/content/polyglot validation, private upload ownership/download checks, CSV formula neutralization, retention, and privacy export/erasure.
- `check:production-hardening` protects the expected public-route ownership and critical security/lifecycle source contracts, but a source contract is not a penetration test or hosted release result.
- Provider secrets and production CAPTCHA verification depend on site-specific adapters and have not been tested against live providers.
- Webhook controls still require production-like egress testing against real DNS/resolver/network behavior; DNS validation cannot eliminate every resolver/connection time-of-check/time-of-use condition.
- Upload controls still require a real web-server test confirming the configured private directory is outside Apache/Nginx/XAMPP document roots, cannot execute scripts, and protected downloads behave correctly.
- CSV large-export behavior and public endpoint rate/idempotency behavior still require integration evidence under realistic traffic.

## Dynamic data and integrations

- ACF and WooCommerce smoke jobs are configured for the release branch but are not evidence until they execute.
- Object cache, page cache, CDN/proxy, security-plugin, and common optimization/minification behavior remain manual compatibility work.
- Large-catalog query/facet cost, cache invalidation, and no-JavaScript behavior require additional production-like evidence.

## Lifecycle and upgrade

- Versioned migrations, a pre-migration Cresco settings snapshot, retry-from-last-completed-version behavior, downgrade detection/compatibility pause, multisite batching, non-destructive deactivation, explicit uninstall ownership, and the `post_content` preservation invariant are implemented and covered by isolated regression tests.
- The release branch contains a pinned historical `0.9.0-rc.1` upgrade fixture that verifies settings/session preservation and schema migration against the exact candidate ZIP. Its result is `NOT RUN` until hosted execution.
- Clean install/activation, historical upgrades against a real WordPress database, network-wide deactivate/reactivate/uninstall, and restore-from-backup rollback procedures still require release-environment evidence.
- User-authored page `post_content` must never be deleted or rewritten by uninstall or migration.

## Accessibility

- Automated axe coverage is configured for critical editor, Settings Center, and frontend scopes, but no pass is claimed before execution.
- Keyboard-only, focus intent, reduced motion, 200%/400% zoom, RTL, forced colors, NVDA, and VoiceOver remain `MANUAL REQUIRED`.
- No screen-reader certification is claimed.

## Performance

- A 50/200/500-node benchmark framework records editor load, selection, Inspector tab, Settings tab, and save timings.
- The first successful controlled run has not yet established the regression baseline; current ceilings are only anti-freeze safeguards.
- Frontend asset-count and conditional-enqueue evidence still needs to be recorded from release runs.

## Commercial operations

- Automatic update channels, rollback packages, support policy, security response process, and release-signing infrastructure remain incomplete.
- Security, privacy, and upgrade/rollback product policies exist and are packaged, but they still require final release review and operational ownership before stable commercial support is claimed.
- Release signing keys are not configured. Provenance explicitly records `signed: false`; there is no fake signing claim.
- A general copyable privacy-safe system-status report is not part of the current release-engineering change. Existing feature diagnostics must not be described as a complete system-status facility.
- Stable `1.0.0` remains blocked by every unresolved P0 item in `docs/COMMERCIAL_HARDENING.md` and `docs/RELEASE_CHECKLIST.md`.
