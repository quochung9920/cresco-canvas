# Security Threat Model

Last reviewed: 2026-08-03 for milestone 0.2.

## Protected assets

- Page content, titles, publication state, and draft privacy.
- Site-wide Cresco design settings.
- Editor preferences and feature flags.
- WordPress authentication cookies and REST nonces.
- Availability of the admin editor and public frontend.
- Release artifact integrity and absence of developer/private files.

## Trust boundaries

Authenticated browser input crosses WordPress admin and REST boundaries. Page authors are not assumed to have theme-setting or publish permissions. Stored WordPress content and legacy plugin options are untrusted until normalized. Themes, plugins, blocks, and build dependencies are external components and may fail or conflict.

## Threats and controls

| Threat | Control | Residual status |
| --- | --- | --- |
| Unauthorized Page read/write | Route-specific `edit_pages`/`edit_post` checks and Page-type validation | Automated permission integration test remains NOT VERIFIED |
| Unauthorized publish/private/scheduled transition | Separate `publish_pages` capability check | Role matrix remains NOT VERIFIED |
| Cross-site request forgery | WordPress REST nonce for API; action-specific nonces for editor-choice URLs | Signed read-only bypass query is intentionally not an authorization grant |
| Stale or same-second overwrite | Exact SHA-256 persisted-state revision token; HTTP 409 before update | Native locks/autosaves/revision UI remain 0.3 |
| Stored CSS injection | Hex sanitization, bounded numbers, editor enum, strict font-stack grammar, trusted selectors | Custom CSS is not implemented |
| Malicious REST values | Route schemas, enums, types, sanitizers, bounds, and WordPress post APIs | Property-based/fuzz testing is not implemented |
| Cross-site scripting in admin output | WordPress escaping for text/attributes/URLs and JSON encoding for bootstrap data | Full manual payload review remains NOT VERIFIED |
| Open redirect or redirect loop | Locally constructed admin URLs; native bypass does not redirect | Browser matrix evidence pending CI |
| Data destruction on lifecycle events | Deactivation preserves data; uninstall is explicit and never deletes posts/content | Multisite uninstall execution pending CI/manual evidence |
| Information leakage in migration errors | User notice is generic; exception detail is stored locally, not rendered | Privacy-safe diagnostics are 1.0 scope |
| Frontend code/style supply | No editor React runtime on public pages; CSS is scoped/conditional | Third-party block behavior remains compatibility work |
| Artifact contamination | Deterministic allowlist ZIP; archive-content CI assertion; SHA-256 output | Signing/provenance is not implemented |
| Dependency compromise | Exact npm lock, Composer lock, production audit gate, visible full audit | Upstream development toolchain has 30 advisories, including 5 high; no production npm dependencies ship |
| CI action tag mutation | Workflow uses established major action tags | Pinning every action to reviewed commit SHAs remains P2 |

## REST inventory

| Route | Method | Capability | Mutation |
| --- | --- | --- | --- |
| `/cresco-canvas/v1/pages` | GET | `edit_pages`, then per-record `edit_post` | None |
| `/cresco-canvas/v1/pages/{id}` | GET | Page type plus `edit_post` | None |
| `/cresco-canvas/v1/pages/{id}` | POST | Page type plus `edit_post`; `publish_pages` for publish-like states | Title/content/status, guarded by exact revision |
| `/cresco-canvas/v1/settings` | GET/POST | `edit_theme_options` | Validated Cresco option only |

## Security result

No reproducible P0 or P1 security issue remains after the code review. This is not a complete security gate: WordPress role integration tests, Plugin Check, hosted CI, penetration testing, dependency provenance, and full 1.0 functionality are not yet verified.
