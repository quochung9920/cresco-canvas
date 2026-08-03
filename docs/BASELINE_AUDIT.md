# Baseline Audit

Audit date: 2026-08-03 (Asia/Ho_Chi_Minh)

## Verified repository state

The audit used the private GitHub repository `quochung9920/cresco-canvas` as the source of truth.

| Item | Verified baseline |
| --- | --- |
| Default branch | `main` |
| Audited commit | `7e56722e76138b9b08af5ee5d8bc2b02789e77d9` |
| Plugin version | `0.1.1` |
| Production branches | `main`; documentation branch `docs/codex-master-roadmap` |
| Open pull requests | PR #1, documentation-only, targeting `main` |
| Tags/releases | No product release tag or release evidence was present |
| Tracked production files | 14 files: one bootstrap, five PHP service files, three editor assets, and one Container block directory |

Every tracked file on `main`, recent commit history, branches, and the open PR were inspected before implementation. The master implementation prompt was read from `docs/codex-master-roadmap` and treated as the only product specification.

## Baseline behavior

- Pages had a custom Canvas submenu and a single monolithic JavaScript editor.
- All Page edit links were replaced by Canvas links without a configurable preference or signed recovery path.
- A custom REST route loaded and saved Page content but had no concurrency token.
- `cresco/container` stored native block markup and remained readable when the plugin was disabled.
- Global settings produced CSS variables, but frontend CSS targeted the unscoped `body` and every `.wp-block-button__link`.
- Frontend assets loaded on unrelated pages.
- There was no package manifest, Composer manifest, lock file, build pipeline, automated test suite, CI workflow, migration runner, feature flag system, lifecycle policy, release script, changelog, or engineering documentation.

No baseline command suite could be run because the repository did not define one. This absence is a finding, not a passing result.

## Findings and disposition

| Severity | Finding | Baseline evidence | Milestone 0.2 disposition |
| --- | --- | --- | --- |
| P0 | None reproduced | Full tracked-source and history audit | No known P0 remains; broader validation is still required |
| P1 | Stale Canvas sessions could silently overwrite newer Page content | Save route accepted content without a persisted-state precondition | Fixed with exact revision tokens and a same-second conflict regression test |
| P1 | Cresco styles affected unrelated site output | Unconditional frontend enqueue plus unscoped `body` and global button selectors | Fixed with Canvas-page detection, body scoping, conditional enqueue, and E2E assertions |
| P2 | Every Page edit link was taken over with no user preference or reliable bypass | Unconditional `get_edit_post_link` filter | Fixed with global/per-Page/remember choices, explicit row actions, signed bypass, and Safe Mode |
| P2 | Missing build, type, dependency, and release controls | No npm/Composer manifests, lock files, or packaging scripts | Fixed in 0.2; CI verification required |
| P2 | No automated PHP, JavaScript, browser, accessibility, compatibility, or Plugin Check coverage | No tests or workflows | Test foundations and matrix workflow added; manual and hosted evidence remain distinct |
| P2 | No migration lock, status, retry evidence, or schema version | Direct option use only | Version-one idempotent migration and failure state added; rollback matrix remains unverified |
| P2 | No explicit activation, deactivation, or uninstall safety policy | No lifecycle hooks or uninstall file | Runtime checks, data-preserving deactivation, and opt-in cleanup added |
| P2 | Monolithic untyped editor made failures and changes difficult to isolate | One global `assets/js/editor.js` file | Replaced by typed modules and an error boundary |
| P2 | Upstream development toolchain reports unresolved advisories | Current `@wordpress/scripts` transitive graph | Production audit is clean; development-only advisories are visible and tracked in Known Limitations |
| P3 | Product status, architecture, compatibility, and recovery were undocumented | README-only MVP | Required documentation and changelog added |

## Scope decision

The latest genuinely completed state was the pre-roadmap 0.1.1 MVP. Milestone 0.2 was therefore the next incomplete milestone. This branch repairs its baseline P1/P2 issues and implements only the 0.2 architecture and reliability foundation. It deliberately does not begin milestone 0.3.
