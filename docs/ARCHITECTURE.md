# Architecture

## Boundaries

Cresco Canvas remains a WordPress plugin, not a fork of WordPress or Gutenberg. The canonical Page document is native block markup in `wp_posts.post_content`. The custom shell composes public `@wordpress/*` packages and selected Core blocks; public output contains no Cresco editor React runtime.

```text
WordPress Page post_content
        ↑ parse / serialize
Typed Canvas editor shell
        ↓ revision-guarded save (0.2 transition)
Versioned Cresco REST API
```

Milestone 0.3 replaces the transitional Page route with native entity workflows through `@wordpress/core-data`, including autosaves, revisions, locks, undo/redo, and recovery.

## PHP services

| Service | Responsibility |
| --- | --- |
| `Requirements` | Enforce WordPress 6.7+ and PHP 8.1+ before boot/activation |
| `Plugin` | Register isolated services once after compatibility succeeds |
| `Lifecycle\Activator` | Run requirement checks and the migration runner |
| `Lifecycle\Deactivator` | Preserve content/settings and clear only a stale migration lock |
| `Migration\Migrator` | Serialize idempotent schema upgrades and retain failure evidence |
| `Support\FeatureFlags` | Normalize known experimental flags; all default off |
| `Admin\EditorPreferences` | Resolve Page/global/user editor choice and build signed recovery URLs |
| `Admin\Admin` | Render/enqueue the custom editor only on its own admin screen |
| `API\RestApi` | Transitional Page/settings routes with schemas, capabilities, normalization, and conflict detection |
| `Styles\GlobalStyles` | Validate design settings and conditionally emit scoped CSS variables |
| `Blocks\Blocks` | Register the native Container metadata and compiled editor script |

Composer supplies optimized PSR-4 loading in release artifacts. A small fallback autoloader keeps a source checkout recoverable before `composer install`; it loads only the `CrescoCanvas\` namespace from `includes/`.

## Editor modules

- `src/editor/App.tsx` owns document state and composes the shell.
- `src/editor/api.ts` centralizes authenticated REST requests and error normalization.
- `src/editor/components/` isolates top bar, inserter, inspector, settings, and crash recovery.
- `src/blocks/container/` owns Container registration, edit/save views, types, and style projection.
- `src/types/wordpress-block-editor.d.ts` declares only the public block-editor runtime surface used by this plugin because that package does not currently publish TypeScript declarations.

Generated files under `build/` are production assets required for a source checkout to activate safely. Source maps, dependencies, tests, and developer source are excluded from release ZIPs.

## Data model

| Storage | Key/entity | Policy |
| --- | --- | --- |
| Posts | Page `post_content` | Canonical native block markup; never deleted by uninstall |
| Post meta | `_cresco_canvas_enabled` | Boolean conditional-asset marker |
| Post meta | `_cresco_canvas_editor_preference` | Optional `canvas` or `wordpress` Page override |
| User meta | `cresco_canvas_last_editor` | Last explicit editor choice in remember mode |
| Option | `cresco_canvas_settings` | Validated global tokens, editor default, uninstall choice |
| Option | `cresco_canvas_feature_flags` | Known Boolean experimental flags |
| Option | `cresco_canvas_db_version` | Last completed schema version |
| Option | `cresco_canvas_migration_state` | Complete/failure evidence without sensitive stack traces |
| Option | `cresco_canvas_migration_lock` | Short-lived atomic migration lock |

Schema version one normalizes legacy settings and creates default feature flags. It never rewrites posts or block markup.

## Save safety

The 0.2 route returns a SHA-256 token derived from the exact persisted ID, modified time, title, status, and content. A save must present that token. A mismatch returns HTTP 409 before mutation, including when both edits share WordPress's one-second timestamp. The route separately checks Page edit and publish capabilities.

This is transitional optimistic concurrency, not a substitute for native autosaves, revisions, or post locks; those remain 0.3 acceptance criteria.

## Asset isolation

- Admin JavaScript/CSS loads only on the registered Cresco submenu hook and never in Safe Mode.
- The custom screen enqueues the supported Core block library and the Cresco Container registration.
- Frontend CSS loads only for singular Pages with explicit Canvas metadata or a legacy `cresco/container` block.
- Design variables target `.cresco-canvas-scope` in admin and `body.cresco-canvas-page` on the frontend.
- Container selectors are block-specific; no unqualified `body`, button, or root selector remains.

## Recovery and rollback

- A React error boundary links to signed Safe Mode and WordPress Editor URLs.
- Safe Mode renders without Cresco editor scripts or generated admin CSS.
- Missing build files produce an actionable PHP recovery view.
- Unsaved changes trigger a browser navigation warning.
- Deactivation preserves all user data and native markup.
- Uninstall defaults to preservation. Explicit cleanup removes only documented Cresco options/meta, including on multisite, and never touches `post_content`.
