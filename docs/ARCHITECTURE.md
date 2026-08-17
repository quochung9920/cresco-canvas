# Architecture

> **Historical Gutenberg-native architecture.** The body of this document records the earlier generation in which standard Gutenberg was the only Page editor. It is intentionally preserved for migration/history and the legacy/native path, but it is **not the canonical runtime ownership document for the current Cresco Studio Website Builder**. For the current architecture use `docs/CORE_ARCHITECTURE.md`, `docs/WEBSITE_BUILDER_CORE.md`, `docs/STUDIO_EDITOR_EXPERIENCE_2.md`, ADR-013/ADR-014 in `docs/DECISIONS.md`, and especially `docs/STUDIO_RUNTIME_OWNERSHIP_AND_CONFLICT_PREVENTION.md`. Where the statements below say there is no custom Page editor/workbench or no Page document route, those statements are superseded for Studio-owned Website Builder documents.

## Boundaries

Cresco Canvas is a Gutenberg extension, not a fork and not a second editor. The normal WordPress Page **Edit** action opens Gutenberg. WordPress Core owns the Page entity and every document workflow; Cresco registers native blocks, a plugin sidebar, revision-enabled Page metadata, and scoped style tokens.

```text
WordPress Page post_content and post meta
        ↕ Core editor/data stores
Native Gutenberg editor
        ↕ public SlotFills and block APIs
Cresco sidebar, Container block, and design settings
```

The public frontend contains no Cresco editor React runtime. Deactivation leaves native markup readable and editable.

## PHP services

| Service | Responsibility |
| --- | --- |
| `Requirements` | Enforce WordPress 6.7+ and PHP 8.1+ before boot/activation |
| `Plugin` | Register isolated services once after compatibility succeeds |
| `Lifecycle\Activator` | Run requirement checks and the migration runner |
| `Lifecycle\Deactivator` | Preserve content/settings and clear only a stale migration lock |
| `Migration\Migrator` | Serialize idempotent schema upgrades and retain failure evidence |
| `Support\FeatureFlags` | Normalize known experimental flags; all default off |
| `Admin\EditorIntegration` | Register revision-enabled Page metadata and enqueue the Gutenberg sidebar only in the Page block editor |
| `API\RestApi` | Expose only permissioned, validated Cresco global settings; no Page document routes |
| `Styles\GlobalStyles` | Validate design settings and conditionally emit scoped editor/frontend variables |
| `Blocks\Blocks` | Register the native Container metadata and compiled block editor script |

Composer supplies optimized PSR-4 loading in releases. A restricted fallback autoloader keeps source checkouts recoverable before `composer install` and loads only `CrescoCanvas\` classes from `includes/`.

## Editor modules

- `src/editor/index.tsx` registers one WordPress plugin extension.
- `src/editor/components/SettingsSidebar.tsx` integrates Page enablement and site-wide settings with Gutenberg's sidebar.
- `src/editor/components/GlobalSettingsPanel.tsx` uses WordPress controls and a separate save only for site-wide Cresco data.
- `src/editor/previewTokens.ts` projects validated settings into the current Gutenberg canvas without touching unrelated DOM.
- `src/blocks/container/` owns native Container registration, edit/save views, types, and style projection.

There is no custom Page editor mount point, router, top bar, document state store, Page REST adapter, or alternate Page edit URL.

Generated files under `build/` are production assets required for source-checkout recovery. Source maps, dependencies, tests, and developer source are excluded from release ZIPs.

## Data model

| Storage | Key/entity | Policy |
| --- | --- | --- |
| Posts | Page `post_content` | Canonical native block markup; Core saves/revises it; uninstall never deletes it |
| Post meta | `_cresco_canvas_enabled` | Boolean, REST-visible, capability-protected, and revision-enabled |
| Option | `cresco_canvas_settings` | Validated global tokens and explicit uninstall choice |
| Option | `cresco_canvas_feature_flags` | Known Boolean experimental flags |
| Option | `cresco_canvas_db_version` | Last completed schema version |
| Option | `cresco_canvas_migration_state` | Complete/failure evidence without rendered exception details |
| Option | `cresco_canvas_migration_lock` | Short-lived atomic migration lock |

Schema version one normalizes legacy settings and creates feature flags. Schema version two removes the retired editor-choice option and metadata. Neither migration rewrites posts or block markup; `_cresco_canvas_enabled` is preserved.

## Document reliability

Gutenberg's native Page workflow provides entity fetching, dirty state, save/update/publish, statuses and scheduling, autosaves, revisions, undo/redo, post locking and conflict handling, retry/error notices, unsaved-navigation protection, preview, slug, featured image, parent, template, discussion, media, inserter, and List View.

Cresco Page metadata is edited through `core/editor` and saved by the same Core action as Page content. It opts into metadata revisions. Custom REST remains only for site-wide Cresco settings because those are not Page entity data.

## Asset isolation

- Sidebar JavaScript/CSS loads only in the standard Gutenberg Page editor.
- Missing sidebar assets produce a non-blocking PHP warning and never prevent Gutenberg from opening.
- RTL uses WordPress stylesheet replacement metadata.
- No editor JavaScript is enqueued on the public frontend.
- Frontend CSS loads only on a singular Page with explicit Cresco metadata or a legacy `cresco/container` block.
- Design variables target `.editor-styles-wrapper` in Gutenberg and `body.cresco-canvas-page` on the frontend.
- Container selectors are block-specific; no unqualified root, body, or button selector exists.

## Recovery and rollback

- Core autosaves, revisions, locks, notices, and recovery own document safety.
- A missing Cresco build disables only Cresco tools; the Page remains editable in Gutenberg.
- Deactivation preserves all content, metadata, and settings.
- Uninstall defaults to preservation. Explicit cleanup removes documented Cresco options/metadata across multisite and never touches `post_content`.
