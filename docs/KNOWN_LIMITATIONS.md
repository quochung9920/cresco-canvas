# Known Limitations — 1.0.0-rc.1

Cresco Canvas `1.0.0-rc.1` is a release candidate. It is not a commercially certified stable release.

## Build and release evidence

- The release-quality branch introduces an authoritative transitional source tree for historically hand-maintained standalone runtimes (`runtime-src/build/`) plus clean-delete/rebuild verification. This architecture is not considered proven until the hosted source/build gate passes for the exact commit.
- `npm ci`, optimized Composer install, lint, unit/PHP tests, PHPCS, two-clean-checkout reproducibility, package verification, and dependency audits require passing hosted evidence for the exact release commit.
- The release pipeline now defines a strict production ZIP allowlist, `SHA256SUMS`, a file-level SPDX inventory, and unsigned provenance metadata. Artifact generation does not imply installability.
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

- Provider secrets and production CAPTCHA verification depend on site-specific adapters and have not been tested against live providers.
- Webhook delivery still requires dedicated SSRF/private-network/DNS-rebinding/idempotency/retry/log-privacy review.
- Uploads require polyglot, double-extension, MIME-spoofing, ownership, retention, and protected-download review.
- CSV formula-injection and large-export behavior require security verification.

## Dynamic data and integrations

- ACF and WooCommerce smoke jobs are configured for the release branch but are not evidence until they execute.
- Object cache, page cache, CDN/proxy, security-plugin, and common optimization/minification behavior remain manual compatibility work.
- Large-catalog query/facet cost, cache invalidation, and no-JavaScript behavior require additional production-like evidence.

## Lifecycle and upgrade

- The release branch contains a pinned historical `0.9.0-rc.1` upgrade fixture that verifies settings/session preservation and schema migration against the exact candidate ZIP. Its result is `NOT RUN` until hosted execution.
- Downgrade detection, rollback guidance, migration-failure recovery, and full uninstall lifecycle evidence remain incomplete.
- User-authored page `post_content` must never be deleted by uninstall or migration.

## Accessibility

- Automated axe coverage is configured for critical editor, Settings Center, and frontend scopes, but no pass is claimed before execution.
- Keyboard-only, focus intent, reduced motion, 200%/400% zoom, RTL, forced colors, NVDA, and VoiceOver remain `MANUAL REQUIRED`.
- No screen-reader certification is claimed.

## Performance

- A 50/200/500-node benchmark framework now records editor load, selection, Inspector tab, Settings tab, and save timings.
- The first successful controlled run has not yet established the regression baseline; current ceilings are only anti-freeze safeguards.
- Frontend asset-count and conditional-enqueue evidence still needs to be recorded from release runs.

## Commercial operations

- Automatic update channels, rollback packages, support policy, security response policy, and complete privacy documentation remain incomplete.
- Release signing keys are not configured. Provenance explicitly records `signed: false`; there is no fake signing claim.
- A general copyable privacy-safe system-status report is not part of the current release-engineering change. Existing feature diagnostics must not be described as a complete system-status facility.
- Stable `1.0.0` remains blocked by every unresolved P0 item in `docs/COMMERCIAL_HARDENING.md` and `docs/RELEASE_CHECKLIST.md`.
