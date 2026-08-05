# Release Checklist — 1.0.0

This checklist applies to `1.0.0-rc.1` and the future stable `1.0.0`. A checked source file is not evidence that a runtime gate passed.

## Version and documentation

- [x] Plugin header and package version are `1.0.0-rc.1`.
- [x] README reflects the current product scope.
- [x] Commercial hardening plan exists.
- [ ] Changelog is complete through the release commit.
- [ ] Architecture and known-limitations documents match the current code.
- [ ] No documentation claims stable or commercial readiness before approval.

## Data and migration

- [x] Settings schema version is 4.
- [x] Schema 4 migration sanitizes fluid settings and stores a pre-migration backup.
- [x] Uninstall preserves data by default.
- [x] Opt-in uninstall cleanup includes known Cresco schedules, settings, submissions, uploads, and metadata.
- [ ] Clean install and activation pass on a real WordPress site.
- [ ] Historical upgrade fixtures pass.
- [ ] Migration failure and retry pass.
- [ ] Downgrade and rollback behavior are documented and tested.
- [ ] Single-site and multisite lifecycle tests pass.

## Build and packaging

- [ ] `package-lock.json` is valid and `npm ci` passes from a clean checkout.
- [ ] Composer install produces an optimized release autoloader.
- [ ] All checked-in runtime files have authoritative source files.
- [ ] Removing `build/` and rebuilding reproduces the required runtime.
- [ ] TypeScript, lint, JavaScript unit tests, PHP syntax, PHPCS, and PHPUnit pass.
- [ ] Two clean package builds produce the same checksum.
- [ ] Release ZIP contents and SHA-256 are reviewed.
- [ ] Exact release ZIP installs and activates on a clean site.

## Editor and persistence

- [ ] Unified left workspace works in Page Editor, Post Editor, and Site Editor.
- [ ] List View remains usable on the right where supported.
- [ ] Workspace fallback preserves the native editor when DOM adapters do not match.
- [ ] Every visible widget control renders in editor and frontend.
- [ ] Unsupported controls are hidden rather than silently ignored.
- [ ] Save/reload, undo/redo, autosave, revisions, copy/paste, duplicate, patterns, and locking pass.
- [ ] Unknown and third-party blocks remain intact.

## Feature smoke tests

- [ ] Global Design save/reset/import/export and fluid tokens pass.
- [ ] Templates/components/site-kit flows pass.
- [ ] Theme Builder display conditions pass.
- [ ] Dynamic fields, loops, filters, facets, load-more, and history sync pass.
- [ ] Interactions pass keyboard and reduced-motion review.
- [ ] Forms pass validation, conditional logic, calculations, steps, upload, CAPTCHA adapter, storage, export, email, and webhook flows.

## Security and privacy

- [ ] REST route inventory and permission review are complete.
- [ ] Upload security review is complete.
- [ ] Webhook SSRF, DNS rebinding, retry, logging, and secret review are complete.
- [ ] CSV formula injection is prevented.
- [ ] Query/facet resource limits and cache behavior are verified.
- [ ] Import/export and CSS token sanitization are verified.
- [ ] Privacy exporter, eraser, retention, and uninstall behavior pass.
- [ ] Logs and diagnostics exclude private submission values and secrets.

## Compatibility

- [ ] WordPress minimum, latest-minus-one, and latest pass.
- [ ] PHP 8.1, 8.2, 8.3, and 8.4 pass.
- [ ] Block and classic themes pass.
- [ ] Chrome, Firefox, WebKit/Safari, and Edge pass critical flows.
- [ ] ACF and WooCommerce compatibility pass.
- [ ] Multisite, object cache, page cache, security, and optimization-plugin smoke tests pass.

## Accessibility and performance

- [ ] Keyboard-only, NVDA, VoiceOver, RTL, forced-colors, 200%/400% zoom, and reduced-motion review pass.
- [ ] axe reports no serious or critical violations in critical flows.
- [ ] Modal/off-canvas focus management passes.
- [ ] Slider and AJAX result announcements pass.
- [ ] Form error summary and field focus pass.
- [ ] 50-, 200-, and 500-block editor performance is measured.
- [ ] Frontend assets are conditionally loaded and performance budgets pass.

## Commercial release approval

- [ ] No unresolved P0 defects.
- [ ] P1 defects are fixed or explicitly accepted with owners and dates.
- [ ] Beta and RC validation are complete.
- [ ] Support, update, rollback, privacy, and security policies are published.
- [ ] Human reviewer approves the exact release commit and artifact.
- [ ] Version is changed from `1.0.0-rc.1` to `1.0.0` only after all required gates pass.
