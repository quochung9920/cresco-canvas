# Changelog

<!-- markdownlint-disable MD024 -->

All notable changes are documented here. Versions follow Semantic Versioning where practical during the pre-1.0 cycle.

## [Unreleased]

No changes have been classified for the next version yet.

## [0.6.0-alpha.1] - 2026-08-05

### Added

- Native Cresco pattern categories and a bundled catalog for complete Pages, heroes, feature grids, calls to action, testimonials, pricing, and contact sections.
- A compiled **Cresco Templates** Gutenberg sidebar with search, category filters, and click-to-insert native block templates.
- Synced component creation from the selected Gutenberg block using WordPress `wp_block` entities.
- Listing and insertion of saved synced components through native `core/block` references.
- Safe Site Kit export and import for sanitized Cresco design settings and allow-listed bundled template identifiers.
- REST endpoints for the template catalog, synced components, and Site Kits.
- Regression tests for stable template identifiers, categories, native block markup, and executable-content exclusions.
- Dedicated Templates runtime, manifest, and stylesheet included in release packaging.

### Changed

- Registered Templates, Components, and Site Kit services during plugin boot.
- Bumped the plugin, package, and Container block to `0.6.0-alpha.1`.

### Security

- Template and component editing requires `edit_pages`; Site Kit management requires `edit_theme_options`.
- Synced components reject Custom HTML, Shortcode, Classic, non-Core, and non-Cresco blocks.
- Site Kit imports accept only schema version one, sanitized design settings, and template IDs present in the bundled catalog.
- Site Kits never import PHP or JavaScript.

### Known limitations

- Hosted CI and WordPress runtime evidence have not yet been produced for the current `main` head.
- Component creation currently saves one selected root block at a time; multi-selection and content-only overrides are not yet implemented.
- Template thumbnails, remote catalog delivery, favorites, version conflict handling, and dependency installation are not yet implemented.
- The repository dependency lock remains unusable, so a full `npm ci` build and byte comparison have not been completed.

## [0.5.0-alpha.1] - 2026-08-04

### Added

- A structured Global Design token registry for colors, typography, spacing, layout widths, radius, shadows, and motion.
- Custom color tokens and aliases with bounded counts, sanitized slugs, validated values, and scoped CSS variables.
- Schema version three migration for custom tokens without modifying Page content.
- REST endpoints for reading settings, saving settings, resetting defaults, and retrieving the normalized token catalog.
- Import, export, reset, custom-color, and alias management controls.
- Safe deletion protection when an alias still references a custom color.
- A compiled **Cresco Design System** Gutenberg sidebar runtime that is loaded independently from the legacy editor bundle.
- Regression tests for token catalogs, custom colors, aliases, and CSS variable output.

### Changed

- Global settings now expose muted color, container width, content width, custom colors, and aliases.
- Frontend and Gutenberg editor token output now use a complete scoped CSS-variable catalog.
- Release packaging now requires the Design System runtime, manifest, and stylesheet.
- Bumped the plugin, package, and Container block to `0.5.0-alpha.1`.

### Security

- Site-wide design changes remain restricted to users with `edit_theme_options`.
- Custom token slugs, colors, aliases, widths, radius, and font stacks are sanitized again on the server before storage or CSS output.
- Aliases can reference only allow-listed core colors or existing custom color tokens.

### Known limitations

- Hosted CI and WordPress runtime evidence have not yet been produced for the current `main` head.
- The repository `package-lock.json` is still unavailable as a usable dependency lock, so the full source build has not been reproduced through `npm ci`.
- The independent Design System runtime is checked in and enqueued, but keyboard, RTL, browser, multisite, lifecycle, visual-regression, and performance verification remain pending.
- Templates, Components, Theme Builder, Dynamic Data, Query/Loop Builder, full interactive blocks, form integrations, WooCommerce scope, and commercial hardening remain later milestones.

## [0.4.0-alpha.1] - 2026-08-04

### Added

- Responsive Container controls for Flex, Grid, Block, direction, wrapping, columns, justification, alignment, gap, maximum width, and per-side padding.
- Explicit responsive inheritance from Desktop to Laptop, Tablet, and Mobile, with an independent 4K override derived from Desktop.
- Five logical editor preview modes: 4K at 1920px, Desktop at 1440px, Laptop at 1024px, Tablet at 768px, and Mobile at 390px.
- A dedicated **Cresco Preview** Gutenberg sidebar with persisted device selection.
- Permission-aware Live Frontend Preview in a size-constrained iframe, manual refresh, new-tab access, and refresh after WordPress save or autosave completes.
- Unit coverage for responsive normalization, inheritance, override reset, safe style generation, exact preview widths, and hardened browser storage behavior.

### Changed

- Preserved legacy Container inline output until responsive-only attributes are used, reducing block markup churn for existing Pages.
- Added bounded numeric normalization and allow-listed layout values before responsive CSS variables are generated.
- Restricted Container background output to recognized color values and CSS custom properties.
- Added wide and full alignment support to the Container block.
- Replaced one-size Container controls with device-aware controls synchronized with the preview selector.
- Included preview source, runtime, styles, manifest, and required assets in release packaging.
- Bumped the plugin, package, Container block, runtime manifests, and CI artifact name to `0.4.0-alpha.1`.

### Security

- Frontend preview URLs are generated only for users who can edit the current Page.
- Invalid, infinite, and out-of-range responsive values are normalized before output.
- Unsafe background expressions are discarded instead of being serialized into Container styles.

### Known limitations

- The checked-in runtime and source pass local syntax and isolated TypeScript validation only; a repository production build has not yet regenerated and compared all assets.
- Hosted CI, WordPress runtime behavior, save/reload parity, frontend parity, RTL, accessibility, browser, role, multisite, lifecycle, and performance evidence remain unverified.
- The current responsive property model applies to the Cresco Container. Shared controls for every supported Core block remain future work in this milestone.
- Templates, Theme Builder, Dynamic Data, Query/Loop Builder, custom interactive blocks, forms, WooCommerce scope, diagnostics, beta, RC, and commercial release gates remain later milestones.

## [0.3.1-alpha.1] - 2026-08-04

### Added

- A visible **Cresco Elements** library inside the Gutenberg sidebar.
- Search, categories, favorites, recent elements, click-to-insert, and supported drag-to-canvas insertion.
- Native layout elements for Section, Container, Row, Grid, Stack, Columns, Spacer, and Divider.
- Native content and media elements for Heading, Text, Buttons, List, Quote, Table, Image, Gallery, Video, Audio, File, and Embed.
- Composed marketing elements for Hero, Feature Grid, Call to Action, Testimonial, and Pricing Card.
- Native interactive, navigation, blog, and utility elements including FAQ disclosure, Navigation, Search, Site Logo, Social Links, dynamic post blocks, Latest Posts, Shortcode, and Custom HTML.
- A dedicated **Cresco Canvas** category in Gutenberg's native inserter.
- Responsive helper output for inserted Grid and Stack compositions.
- Unit coverage for element registration, block factories, persisted library state, nested block availability, search matching, recent ordering, and insertion-point resolution.
- Playwright coverage for finding and inserting Heading as a native Gutenberg block while automatically enabling Cresco Page styles.

### Changed

- Widened the Cresco sidebar when active and moved the element library ahead of Page and Global Design settings.
- Automatically enables Page-level Cresco styling after inserting an element.
- Added searchable Container keywords and moved the block into the Cresco inserter category.
- Sanitizes corrupt, duplicated, stale, and unknown favorite or recent element IDs before using browser storage.
- Inserts after the selected sibling, inside supported container blocks, or at the document end instead of always appending blindly.
- Replaced continuous animation-frame canvas polling with mutation and iframe-load observers that are removed when the sidebar unmounts.
- Synchronized the checked-in editor runtime and asset cache version with the stabilized source.
- Bumped the plugin, package, Container block, and CI artifact name to `0.3.1-alpha.1`.

### Fixed

- Prevents element factories from inserting unavailable WordPress block types.
- Prevents insertion into a location where WordPress reports that a root block type is restricted.
- Shows dismissible success, warning, or error feedback instead of failing silently.
- Handles removed drag payloads and storage failures without breaking the Elements Library.

### Known limitations

- This release candidate has not yet produced a successful hosted CI result on its current `main` head.
- WordPress runtime, save/reload, drag insertion, frontend parity, multisite, lifecycle, role, manual accessibility, RTL, browser, and performance evidence remain unverified.
- Dedicated responsive property controls, exact five-device preview modes, custom Tabs/Modal/Slider blocks, Templates, Theme Builder, Dynamic Data bindings, Query Builder, WooCommerce Builder, and Live Frontend Preview remain subsequent milestones.

## [0.3.0-alpha.1] - 2026-08-04

### Added

- A native Gutenberg `PluginSidebar` for Page styling and permissioned global design settings.
- Revision-enabled `_cresco_canvas_enabled` metadata saved through WordPress Core's Page workflow.
- Live scoped design-token preview for both iframe and non-iframe Gutenberg canvases.
- Schema version two migration and regression coverage for the retired dual-editor preferences.
- Native Gutenberg E2E coverage for standard Edit entry, Core saving, metadata persistence, asset isolation, removed Page routes, and sidebar accessibility.
- RTL replacement data for the generated editor stylesheet.

### Changed

- Bumped the plugin, package, and Container block to `0.3.0-alpha.1`.
- Made Gutenberg the only Page editor and delegated document loading, saving, publishing, autosaves, revisions, undo/redo, locking, navigation protection, previews, media, and document settings to WordPress Core.
- Reduced the compiled Cresco editor runtime to a sidebar extension instead of a duplicate full-screen editor.
- Restricted the Cresco REST API to genuinely custom site-wide settings.
- Updated the authoritative product specification to prohibit a separate editor screen or dual-editor routing.
- Limited the current sidebar to design controls with visible editor/frontend output; deferred width and muted utility controls stay out of the UI until their layout/token consumers exist.

### Removed

- The **Edit in Canvas / WordPress Editor** split, editor-choice settings, row actions, redirects, and remembered user preference.
- The custom Page REST read/write routes and their duplicate revision-token workflow.
- The proprietary top bar, inserter, inspector, error boundary, Safe Mode screen, and custom React editor shell.

### Security

- Continued capability enforcement for Page metadata and site-wide design settings.
- Preserved strict setting sanitization, scoped output, and non-destructive lifecycle behavior while reducing the custom Page-write attack surface.

## [0.2.0-alpha.1] - 2026-08-03

### Added

- TypeScript and modular React sources built with `@wordpress/scripts`.
- Composer PSR-4 autoloading with a recoverable source-checkout fallback.
- GitHub Actions for JavaScript, CSS, PHP 8.1–8.5, WordPress 6.7/6.9/7.0.1/trunk, Playwright, axe, Plugin Check, packaging, and reproducibility.
- Versioned idempotent migration runner, feature flags, activation requirements, safe deactivation, and opt-in uninstall cleanup.
- Configurable global, per-Page, and remembered editor entry behavior.
- Nonce-protected native-editor bypass and Safe Mode recovery.
- Exact revision-token conflict detection and crash/unsaved-change recovery paths.
- PHP, JavaScript, E2E, compatibility, accessibility, and style-isolation test foundations.
- Reproducible release ZIP generation and SHA-256 checksum.
- Baseline, architecture, roadmap, security, accessibility, performance, compatibility, commercial-readiness, release, and limitations documentation.

### Changed

- Bumped the plugin and Container block from 0.1.1 to `0.2.0-alpha.1`.
- Replaced the monolithic editor source with typed modules and generated production assets.
- Scoped frontend variables and selectors to Canvas Pages and conditionally load frontend CSS only when Canvas is used.
- Preserved legacy `cresco/container` block markup while expanding the block editor implementation.

### Security

- Added explicit REST schemas, capability checks, input normalization, output escaping, nonce-protected editor-choice actions, and same-second concurrent-edit protection.
- Confirmed zero installable production npm vulnerabilities at high severity or above; development-toolchain advisories remain documented.

### Removed

- Unconditional Canvas takeover of every Page edit link.
- Global frontend selectors that changed all site buttons and the unscoped `body` element.
