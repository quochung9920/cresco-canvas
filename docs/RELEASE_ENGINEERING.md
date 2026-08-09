# Release Engineering — Cresco Canvas 1.0

This document defines the commercial release evidence pipeline for Cresco Canvas. It is operational documentation, not a declaration that the current release candidate has passed.

## Status vocabulary

Every gate must be reported as one of:

- `PASS`: the gate executed successfully against the exact commit or artifact being assessed.
- `FAIL`: the gate executed and produced a product or release-engineering failure.
- `NOT RUN`: no execution evidence exists for the assessed commit.
- `SKIPPED`: the gate was intentionally skipped and the reason is recorded.
- `INFRA FAILURE`: the test did not reach the product assertion because the runner, service, browser, Docker environment, dependency registry, or workflow failed to start.
- `MANUAL REQUIRED`: automation cannot establish the requirement and a human verification record is still required.

`configured`, `source present`, `workflow exists`, and `check was skipped` are never synonyms for `PASS`.

## Authoritative trees

| Layer | Authoritative location | Generated/output location | Verification |
| --- | --- | --- | --- |
| TypeScript/React editor bundles | `src/` | `build/` | `npm run build` + `npm run check:build-integrity` |
| Reviewed standalone runtimes not yet modularized | `runtime-src/build/` | `build/` | byte-for-byte SHA-256 parity in `check-build-integrity` |
| WordPress/PHP runtime | `includes/`, `blocks/`, plugin bootstrap | release ZIP | strict allowlist in `scripts/release-files.mjs` |
| CSS authored as source | `assets/css/`, `blocks/**/*.css` | release ZIP | CSS lint + package allowlist |

The transitional `runtime-src/build/` tree exists because several standalone runtimes historically lived only in `build/`. Those files are now source inputs. `build/` is disposable: the release gate removes it completely before rebuilding. New direct edits to `build/` are not an accepted source workflow.

## Clean source build

From a clean checkout:

```bash
npm ci
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
rm -rf build
npm run build
npm run check:build-integrity
```

A passing source/build gate requires both:

1. every production `build/*` file has an owner in `runtime-src/manifest.json`; and
2. the rebuilt `build/` tree has no difference from the committed production runtime.

## Quality commands

```bash
npm run typecheck
npm run lint:js
npm run lint:css
npm run lint:md
npm run test:unit
phpunit -c phpunit.xml.dist
npm run check:hygiene
npm run check:editor-runtime
npm run check:build-integrity
npm run check:version
npm run package
node scripts/verify-package.mjs
```

PHPCS is run by the release workflow with WordPress Coding Standards and PHPCompatibilityWP. E2E, matrix, ZIP-install, upgrade, accessibility, and performance gates require Docker and browsers and therefore execute in the release workflow or an equivalent documented environment.

## Production package

`npm run package` creates:

- `dist/cresco-canvas-<version>.zip`
- `dist/SHA256SUMS`
- `dist/cresco-canvas-<version>.zip.spdx.json`
- `dist/cresco-canvas-<version>.zip.provenance.json`

The ZIP uses a strict production allowlist. Development tests, source trees, release scripts, local environment files, VCS metadata, temporary output, and source maps are rejected. Archive entries are sorted and normalized to a fixed timestamp for reproducibility.

The SPDX document is a file-level package inventory. The provenance record identifies the commit/ref, builder, Node version, and artifact digest. Signing is explicitly `not-configured`; the pipeline does not pretend that unsigned metadata is cryptographically signed.

## Reproducibility

The release workflow builds the exact version from two independent clean checkouts and compares the SHA-256 of the resulting ZIPs. A same-workspace double build also runs as a fast diagnostic, but only the two-checkout result is commercial evidence.

## Runtime matrices

The optimized WordPress/PHP smoke matrix covers:

| WordPress line | PHP | Purpose |
| --- | --- | --- |
| 6.7.5 | 8.1 | minimum supported boundary |
| 6.9.5 | 8.2 | latest-minus-one line and PHP 8.2 |
| 7.0.2 | 8.3 | current stable line and release-default PHP |
| 7.0.2 | 8.4 | current stable line and newest supported PHP |

PHPUnit separately runs on PHP 8.1, 8.2, 8.3, and 8.4. Each WordPress matrix cell performs clean activation plus the critical Chromium editor smoke rather than only checking installation.

Theme smoke runs a modern block theme (`twentytwentyfive`) and a classic theme (`twentytwentyone`). Browser smoke runs Chromium, Firefox, and WebKit. Edge remains a documented manual smoke requirement because the project does not currently provision a dedicated Edge runner.

## Critical E2E contract

The critical flow exercises:

- login and standalone editor open;
- widget insertion/editing;
- responsive mode;
- Settings Center;
- save/reload;
- preview;
- undo/redo;
- History revisions;
- basic AI panel/import entry points;
- editor responsiveness/no freeze while switching critical surfaces.

Exact-release installation has a separate test: the versioned ZIP is served as a remote plugin source to an isolated `wp-env` installation, activated, edited, saved, reloaded, and previewed. Source-checkout E2E is not a substitute for this P0 gate.

## Upgrade evidence

The upgrade smoke pins the historical `0.9.0-rc.1` source commit `e23103c98a7fce013313fd96263cb469aff211f7`, creates a database/session/settings fixture, replaces that plugin with the exact candidate ZIP, runs migration, and verifies settings/session preservation and schema completion. It is a release fixture, not a promise that every historical development snapshot is supported.

## Accessibility evidence

Automated evidence uses axe against critical editor, Settings Center, and frontend scopes. Serious or critical violations fail the automated gate.

Automation does **not** establish the following. These remain manual verification records before stable 1.0 approval:

- keyboard-only operation and focus order/return;
- reduced motion;
- 200% and 400% zoom/reflow;
- RTL;
- forced colors/high contrast;
- NVDA;
- VoiceOver;
- dedicated Edge smoke.

A release report must never convert these items from `MANUAL REQUIRED` to `PASS` without a human record.

## Performance evidence

The automated benchmark creates 50-, 200-, and 500-node Cresco Session documents and records:

- editor load;
- selection response;
- Inspector tab opening;
- Settings tab switching;
- save serialization/persistence.

Initial automation contains only generous anti-freeze ceilings. The first successful controlled run establishes the baseline. Subsequent release thresholds must be documented from that baseline; a regression budget must not be invented before evidence exists.

Frontend release review also records the production asset inventory and verifies that editor-only code is not part of the public release path where it is not required.

## Integration smoke

Automated release smoke currently covers ACF, WooCommerce, and multisite. Object-cache, page-cache, CDN/proxy, security-plugin, and common optimization/minification behavior remain `MANUAL REQUIRED` until repeatable infrastructure exists.

## Stable-version guard

`npm run check:version` also runs `scripts/check-stable-release.mjs`. A prerelease such as `1.0.0-rc.1` is allowed without a stable approval record. A future stable `1.0.0` requires `release-evidence/1.0.0.json` bound to the exact commit and artifact SHA-256, with every required P0 status recorded as `pass` and manual verification explicitly confirmed.

This guard prevents an accidental version flip; it does not manufacture evidence.

## CI truth and release decision

The commercial workflow is `.github/workflows/release-hardening.yml` and runs on `commercial/release-quality` or manual dispatch. P0 jobs do not use `continue-on-error`. The aggregate job succeeds only when every automated prerequisite succeeds.

The final human release report still has to distinguish automated evidence from manual verification. `COMMERCIAL 1.0 READY` is allowed only after all P0 evidence, including exact ZIP and manual gates, is complete. Otherwise the conclusion is `NOT READY` or `RC READY` as supported by the evidence.
