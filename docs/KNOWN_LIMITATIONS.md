# Known Limitations

## Current milestone boundary

Version `0.3.0-alpha.1` implements the direct Gutenberg architecture and removes the duplicate editor workflow. It is still an alpha and is not a production-ready visual builder.

- WordPress Core now supplies Page persistence, publishing, statuses, autosaves, revisions, undo/redo, locking, document settings, media, inserter, List View, shortcuts, and recovery. The configured WordPress/browser suite has not yet executed successfully, so these integration claims remain runtime `NOT VERIFIED`.
- Cresco adds one Container block and a settings sidebar. A complete Section/Grid/Stack responsive engine and shared controls remain milestone 0.4; stored width/muted values are preserved for compatibility but intentionally hidden until real consumers exist.
- Global settings remain a small validated subset rather than the token entities, import/export, usage tracking, and theme adapters planned for milestone 0.5.
- Templates, components, Theme Builder, dynamic data/query tooling, interactive components, form integrations, onboarding, diagnostics, translations, and commercial operations remain later milestones.
- The site-wide design option has its own explicit save button because it is not Page entity data. Page enablement uses Gutenberg's normal Save/Update action.

## Verification gaps

- Native PHP, Composer, Docker, WordPress, and browser services were unavailable in the implementation environment. Native PHP 8.1–8.5, PHPCS, PHPUnit, Plugin Check, activation, Gutenberg interaction, and Playwright results must come from working CI or a staging environment.
- GitHub Actions previously ended with `startup_failure` before allocating jobs. A configured workflow is not passing evidence.
- Manual keyboard, screen-reader, zoom, forced-colors, RTL, touch, and real-host testing has not been performed.
- Editor timing, save latency, CLS, and 500-block performance have not been measured.
- The schema 1 → 2 migration has isolated unit coverage but no real-site upgrade/rollback fixture.
- Release signing, provenance attestations, translation catalogs, privacy-safe diagnostics, beta, RC, and staging validation are absent.

## Dependency and tooling limitations

- `npm audit --omit=dev --audit-level=high` reports zero production vulnerabilities.
- The development graph reports 30 transitive advisories (25 moderate, 5 high), primarily through `@wordpress/scripts`. Development packages are excluded from the release ZIP; the advisories remain open and visible.
- The WordPress package graph reports a legacy `react-autosize-textarea` React peer-range mismatch even though WordPress packages resolve React 18.3.1.
- WordPress package declarations do not cover every runtime API with matching TypeScript types; Cresco uses narrow local declarations only where required and must review them on upgrades.

No limitation in this file is a commercial-readiness waiver.
