# Architecture Decisions

| ID | Decision | Rationale | Consequence |
| --- | --- | --- | --- |
| ADR-001 | Keep native block markup in `post_content` | Preserves WordPress interoperability and readable content after deactivation | Cresco must preserve unknown/Core blocks and cannot depend on a proprietary document blob |
| ADR-002 | Implement only milestone 0.2 in this PR | The audited repository was a 0.1.1 MVP and foundations were missing | Native entity reliability work remains explicitly in 0.3 |
| ADR-003 | Use `@wordpress/scripts` and exact npm versions | Aligns compilation/linting with WordPress while retaining deterministic resolution | Upstream development-only advisories must be monitored |
| ADR-004 | Use Composer PSR-4 plus a restricted fallback loader | Release ZIPs get optimized autoloading; source checkouts still fail safely | The release builder requires `vendor/autoload.php` |
| ADR-005 | Retain a transitional custom Page REST route for 0.2 only | Avoids combining the 0.2 foundation and 0.3 native entity rewrite | Route has strict schemas, permissions, and revision-token conflict protection |
| ADR-006 | Scope and condition all Cresco frontend assets | Prevents theme/plugin collisions and unrelated-page cost | Legacy Container detection is retained for backward compatibility |
| ADR-007 | Default editor selection to remember/native fallback | Avoids trapping users in an experimental editor | Explicit Canvas use records the user choice and Page enablement |
| ADR-008 | Make uninstall deletion opt-in and content-preserving | Destructive cleanup must be explicit and recoverable | Orphaned native block markup can remain if users request metadata cleanup |
| ADR-009 | Build deterministic ZIPs from an allowlist | Prevents accidental credentials/source/test inclusion and makes artifacts comparable | Composer and production build outputs must exist before packaging |
| ADR-010 | Treat compatibility/nightly and manual UX claims separately | A configured test is not evidence that it passed | Documentation uses `NOT TESTED`/`NOT VERIFIED` until CI or a human records evidence |
