# Release Checklist

Statuses describe `0.3.0-alpha.1`, not the future 1.0 release.

## Source and build

- [x] Version synchronized in plugin header, constant, package, block metadata, and changelog.
- [x] Exact npm dependency lock committed.
- [x] Composer manifest and lock committed.
- [x] Production Gutenberg sidebar and block assets generated.
- [x] Release builder uses an explicit allowlist and fixed timestamps.
- [ ] Composer optimized autoloader generated in the CI workspace.
- [ ] Reproducibility check passed twice in hosted CI.
- [ ] Archive contents and SHA-256 reviewed.
- [ ] Release ZIP installed on a clean WordPress site.

## Quality

- [x] Local TypeScript type check passed.
- [x] Local ESLint and Stylelint passed.
- [x] Local JavaScript unit tests passed.
- [x] Supplemental PHP 8.3 WebAssembly syntax lint passed for all 20 PHP files.
- [ ] Authoritative PHP syntax, PHPCS, and PHPUnit passed.
- [ ] Playwright Chromium, Firefox, and WebKit passed.
- [ ] axe serious/critical assertion passed.
- [ ] Plugin Check passed.
- [ ] Required WordPress/PHP matrix passed.
- [ ] Manual keyboard/screen-reader/zoom/RTL review completed.
- [ ] Runtime and large-document performance budgets measured.

## Lifecycle and data

- [x] Activation checks minimum versions.
- [x] Migration versions one and two are idempotent in isolated unit coverage and preserve Page content/Cresco enablement.
- [x] Deactivation preserves content/settings.
- [x] Uninstall is opt-in and never deletes Page content.
- [ ] Clean install/activation tested in WordPress.
- [ ] 0.1.1 → 0.2 → 0.3 upgrade fixture tested on a real WordPress database.
- [ ] Standard Page Edit opens Gutenberg with no dual-editor row actions.
- [ ] Native save, publish, autosave, revisions, undo/redo, metadata revisions, and two-user post locking pass end to end.
- [ ] Rollback to prior code tested and documented against a real site copy.
- [ ] Deactivate/reactivate/uninstall tested on single-site and multisite.
- [ ] Unknown and third-party block preservation tested end to end.

## Human review

- [ ] Draft pull request CI is green.
- [ ] Complete diff reviewed from architecture, Gutenberg, security, accessibility, performance, destructive QA, beginner, designer, developer, upgrader, and commercial-review perspectives.
- [ ] Screenshots/recordings reviewed where available.
- [ ] Known P2/P3 items accepted or tracked.
- [ ] Human reviewer explicitly approves merge.

Do not merge, tag, publish, or call the product commercially ready from this checklist automatically.
