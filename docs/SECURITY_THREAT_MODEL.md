# Security Threat Model

Last reviewed: 2026-08-04 for milestone 0.3.

## Protected assets

- Page content, Cresco Page metadata, publication state, and draft privacy.
- Site-wide Cresco design settings and feature flags.
- WordPress authentication cookies and REST nonces.
- Availability of Gutenberg and the public frontend.
- Release artifact integrity and absence of developer/private files.

## Trust boundaries

WordPress Core owns the Page REST/entity boundary and document workflows. Cresco-owned browser input crosses one custom REST boundary for global settings. Page authors are not assumed to have site-design permission. Stored settings and legacy options are untrusted until normalized. Themes, plugins, blocks, and build dependencies may fail or conflict.

## Threats and controls

| Threat | Control | Residual status |
| --- | --- | --- |
| Unauthorized Page content/meta write | Core Page endpoint plus `_cresco_canvas_enabled` `edit_post` authorization | Role and real WordPress integration matrix remains NOT VERIFIED |
| Unauthorized publication/state transition | Delegated entirely to Core Page capabilities and editor workflow | Runtime role matrix remains NOT VERIFIED |
| Cross-site request forgery | Core-configured REST nonce used by `apiFetch`; no custom editor-choice actions remain | Browser evidence pending |
| Stale overwrite/concurrent editing | Native autosave, revisions, post locking, conflict notices, and Core save behavior | Two-user runtime test remains NOT VERIFIED |
| Stored CSS injection | Hex sanitization, bounded numbers, strict font-stack grammar, and trusted internal selectors | Property/fuzz testing absent; arbitrary Custom CSS is not implemented |
| Malicious settings REST values | `edit_theme_options`, response schema, normalization, bounds, and explicit property allowlist | Real REST permission integration test remains NOT VERIFIED |
| Cross-site scripting in admin output | WordPress escaping plus `wp_json_encode` for bootstrap data; React escapes rendered text | Full manual payload review remains NOT VERIFIED |
| Editor denial of service from missing assets | Missing Cresco build emits a non-blocking notice; Gutenberg continues without the extension | Real missing-build staging test remains NOT VERIFIED |
| Redirect loop/open redirect | No Cresco editor router, takeover, redirect, or alternate Page URL exists | Static source regression and E2E absence checks configured |
| Lifecycle data destruction | Deactivation preserves data; uninstall is explicit and never deletes posts/content | Multisite execution remains NOT VERIFIED |
| Frontend style/script contamination | No public editor JS; frontend CSS is scoped and conditional | Third-party theme/block matrix remains NOT VERIFIED |
| Artifact contamination | Deterministic allowlist ZIP, archive-content assertion, and SHA-256 output | Signing/provenance absent |
| Dependency compromise | Exact locks, production audit gate, and visible full audit | Development toolchain retains 30 transitive advisories |
| CI action tag mutation | Established major action tags | Commit-SHA pinning remains open P2 |

## REST inventory

| Route | Method | Capability | Mutation |
| --- | --- | --- | --- |
| WordPress Core Page endpoint | Core methods | Core Page/post capabilities | Native Page content, status, and registered metadata |
| `/cresco-canvas/v1/settings` | GET/POST | `edit_theme_options` | Validated Cresco option only |

The retired `/cresco-canvas/v1/pages` routes are intentionally absent and have E2E regression coverage.

## Security result

No reproducible P0 or P1 security issue remains after static review and local JavaScript/PHP-source checks. This is not a passed security gate: native role integration, Plugin Check, hosted CI, penetration testing, provenance, and future product surfaces remain unverified.
