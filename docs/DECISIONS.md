# Architecture Decisions

| ID | Decision | Rationale | Consequence |
| --- | --- | --- | --- |
| ADR-001 | Keep native block markup in `post_content` | Preserves WordPress interoperability and readable content after deactivation | Cresco must preserve unknown/Core blocks and cannot depend on a proprietary document blob |
| ADR-002 | Implement only milestone 0.2 in the foundation PR | The audited repository was a 0.1.1 MVP and foundations were missing | Native entity reliability followed in milestone 0.3 |
| ADR-003 | Use `@wordpress/scripts` and exact npm versions | Aligns compilation/linting with WordPress while retaining deterministic resolution | Upstream development-only advisories must be monitored |
| ADR-004 | Use Composer PSR-4 plus a restricted fallback loader | Release ZIPs get optimized autoloading; source checkouts still fail safely | The release builder requires `vendor/autoload.php` |
| ADR-005 | Retain a transitional custom Page REST route for 0.2 only | Kept the foundation PR reviewable | Retired in schema 2 after the native Gutenberg workflow replaced it |
| ADR-006 | Scope and condition all Cresco frontend assets | Prevents theme/plugin collisions and unrelated-page cost | Legacy Container detection is retained for backward compatibility |
| ADR-007 | Default editor selection to remember/native fallback in 0.2 | Reduced takeover risk before native integration existed | Superseded by ADR-011; related preferences are migrated away |
| ADR-008 | Make uninstall deletion opt-in and content-preserving | Destructive cleanup must be explicit and recoverable | Orphaned native block markup can remain if users request metadata cleanup |
| ADR-009 | Build deterministic ZIPs from an allowlist | Prevents accidental credentials/source/test inclusion and makes artifacts comparable | Composer and production build outputs must exist before packaging |
| ADR-010 | Treat compatibility/nightly and manual UX claims separately | A configured test is not evidence that it passed | Documentation uses `NOT TESTED`/`NOT VERIFIED` until CI or a human records evidence |
| ADR-011 | Make standard Gutenberg the only Page editor | Core already provides the reliable document, accessibility, navigation, media, and editing workflows users expect | No dual editor links, custom shell, Page REST route, or duplicate document state is permitted |
| ADR-012 | Save Page enablement through revision-enabled native post meta | Page content and its styling state must travel through one Core save/revision boundary | The sidebar dispatches `core/editor.editPost`; site-wide settings remain separate custom data |
