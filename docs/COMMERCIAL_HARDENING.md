# 1.0 Commercial Hardening

This document is the authoritative plan for moving Cresco Canvas from `1.0.0-rc.1` to a commercially supported `1.0.0` release.

## Release rule

Do not tag or market `1.0.0` until every P0 item has objective evidence. Source presence or checked-in runtime is not equivalent to a passing release gate.

## P0 blockers

### Source and build integrity

- [ ] Every checked-in runtime has an authoritative source file.
- [ ] A clean checkout can run `npm ci`, `composer install --no-dev --optimize-autoloader`, and `npm run build`.
- [ ] Deleting `build/` and rebuilding reproduces every required runtime.
- [ ] Release ZIP contents are allow-listed and reviewed.
- [ ] Two clean package builds produce the same SHA-256 checksum.
- [ ] No manually edited generated file is required for functionality.

### Inspector and rendering integrity

- [ ] Every visible inspector control produces verified editor and frontend output.
- [ ] Unsupported Core block capabilities hide or disable the corresponding control.
- [ ] Cresco-only positioning/effects use a versioned Cresco attribute and rendering pipeline rather than unsupported Core style keys.
- [ ] Save, reload, copy, duplicate, undo, redo, revisions, and reusable patterns preserve settings.
- [ ] Invalid or legacy style data migrates without invalidating blocks.

### Security

- [ ] All REST routes have documented authentication, capability, nonce, signature, rate, and payload limits.
- [ ] File uploads pass extension, MIME, size, executable, polyglot, ownership, and download-permission review.
- [ ] Webhooks block loopback, link-local, private-network, metadata-service, and DNS-rebinding targets.
- [ ] CSV exports neutralize spreadsheet formulas.
- [ ] Dynamic queries and facets have bounded cost, cache keys, and invalidation.
- [ ] Import/export cannot inject scripts, arbitrary CSS, objects, or executable markup.
- [ ] Form, diagnostics, and webhook logs do not expose sensitive values.

### Lifecycle and data

- [x] Settings schema 4 migration exists and creates a pre-migration backup.
- [x] Uninstall inventory covers known settings, schedules, submissions, uploads, and metadata.
- [ ] Clean install and activation pass.
- [ ] Upgrade fixtures from supported historical versions pass against a real database.
- [ ] Downgrade detection and rollback guidance are implemented.
- [ ] Single-site and multisite deactivate/reactivate/uninstall pass.
- [ ] User-authored `post_content` is never deleted by cleanup.

### Compatibility

- [ ] Minimum supported WordPress passes.
- [ ] Latest stable WordPress passes.
- [ ] Latest-minus-one WordPress passes.
- [ ] PHP 8.1, 8.2, 8.3, and 8.4 pass.
- [ ] Block theme and classic theme smoke tests pass.
- [ ] Post Editor, Page Editor, and Site Editor smoke tests pass.
- [ ] Chrome, Firefox, WebKit/Safari, and Edge pass critical flows.
- [ ] ACF, WooCommerce, multisite, object cache, page cache, and common optimization-plugin smoke tests pass.

### Accessibility

- [ ] Keyboard-only operation passes.
- [ ] NVDA and VoiceOver smoke tests pass.
- [ ] 200% and 400% zoom pass.
- [ ] RTL and forced-colors pass.
- [ ] Reduced motion is respected.
- [ ] Modal, off-canvas, slider, AJAX results, and form errors pass focus and announcement review.
- [ ] axe reports no serious or critical violations in critical flows.

### Performance

- [ ] Editor overhead is measured on 50-, 200-, and 500-block documents.
- [ ] Selecting a block and opening the Cresco inspector stays within the interaction budget.
- [ ] Frontend assets load only when their blocks are present.
- [ ] Mutation observers are scoped, debounced, and disconnected correctly.
- [ ] Dynamic loops and facets are benchmarked with representative datasets.
- [ ] Form submission, upload, email, and webhook work are separated from the response path where appropriate.

### Documentation and release operations

- [x] README describes the actual `1.0.0-rc.1` scope.
- [ ] Architecture documentation matches the current services and editor shell.
- [ ] Known limitations are current and evidence-based.
- [ ] Changelog is complete and user-facing.
- [ ] Upgrade, rollback, privacy, security, and support policies are published.
- [ ] Release ZIP, checksum, SBOM, and provenance are produced.
- [ ] A clean WordPress install successfully installs the exact release ZIP.
- [ ] Beta and RC feedback contain no unresolved P0/P1 defects.

## P1 commercial quality

- [ ] One persistent Add/Edit/Global shell replaces module switching.
- [ ] Responsive inheritance and reset-to-global indicators are complete.
- [ ] Global token preview, validation, presets, contrast checks, usage counts, and per-group reset are complete.
- [ ] Safe mode and conflict diagnostics are available.
- [ ] System status can be copied without private data.
- [ ] Form delivery history, retry status, and download permissions are complete.
- [ ] Translation catalogs and localization review are complete.
- [ ] Automatic updates, release channels, and rollback behavior are defined.

## Deferred after 1.0

Cloud libraries, marketplace features, AI generation, collaboration, white-label tools, advanced animation timelines, and broad CRM catalogs do not block the first stable commercial release.
