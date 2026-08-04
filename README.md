# Cresco Canvas

Cresco Canvas is an experimental visual-building extension for the native WordPress Gutenberg editor. Version `0.3.0-alpha.1` integrates Cresco controls directly into the standard Page editor and now includes the first usable **Cresco Elements** toolkit. It is not yet a commercial release.

Page content remains normal Gutenberg block markup in `post_content`. WordPress Core owns loading, saving, publishing, autosaves, revisions, undo/redo, post locking, previews, media, the inserter, and List View. Cresco adds a native Container block, a searchable element library, composed website sections, a Gutenberg sidebar, and scoped design tokens.

## Requirements

- WordPress 6.7 or newer.
- PHP 8.1 or newer.
- A browser supported by the installed WordPress version.
- Node.js 22 and npm 10 or newer for development.
- Composer 2 and Docker for packaging and WordPress/E2E suites.

## Current editor experience

1. Open **Pages** and select the normal **Edit** action.
2. Open **Cresco Canvas** from Gutenberg's More menu.
3. Expand **Elements**.
4. Search, filter, favorite, click, or drag an element into the editor canvas.
5. Continue editing with Gutenberg's native block toolbar, Inspector, List View, Save, Update, Publish, Preview, undo/redo, revisions, and locking.

The initial Elements Library includes:

- Layout: Section, Container, Row, Grid, Stack, Columns, Spacer, Divider.
- Content: Heading, Text, Buttons, List, Quote, Table.
- Media: Image, Gallery, Video, Audio, File, Embed.
- Marketing compositions: Hero, Feature Grid, Call to Action, Testimonial, Pricing Card.
- Navigation and dynamic content: Social Links, Navigation, Search, Site Logo, Post Title, Featured Image, Excerpt, Date, Latest Posts.
- Utility and interaction: FAQ disclosure, Shortcode, Custom HTML.

Elements are implemented with native Core blocks and Cresco Container compositions wherever possible. This keeps content portable and editable after plugin deactivation.

## Page and global styling

- The Page styling switch is revision-enabled metadata and is saved by Gutenberg's normal **Save**, **Update**, or **Publish** workflow.
- Site-wide Cresco design settings are permissioned custom data with an explicit save action.
- Current design settings include primary, text, muted, and background colors, global radius, font family, container width, and content width.
- Editor preview tokens and frontend CSS are scoped to Pages using Cresco.
- Inserting an element automatically enables Cresco Page styling for that Page.

## Install a release ZIP

1. Download the CI-produced `cresco-canvas.zip` artifact from a reviewed milestone build.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Upload the ZIP and activate Cresco Canvas.
4. Open **Pages**, select the normal **Edit** action, then open the **Cresco Canvas** sidebar in Gutenberg.

Do not package the repository directory manually: source checkouts omit Composer's optimized release autoloader.

## Develop locally

```bash
npm ci
composer install
npm run build
npx wp-env start
```

Useful checks:

```bash
npm run typecheck
npm run lint:js
npm run lint:css
npm run lint:md
npm run test:unit
phpunit --configuration phpunit.xml.dist
npm run test:e2e
npm run check:version
npm run package:verify
```

`npm run package:verify` requires `vendor/autoload.php`; run Composer first. The archive and checksum are written to `dist/`.

## Data and rollback

- Canonical content: native block markup in `wp_posts.post_content`.
- Plugin option: `cresco_canvas_settings` for validated global tokens and opt-in uninstall cleanup.
- Page metadata: `_cresco_canvas_enabled`, registered for native REST saving and revisions.
- Favorites and recent Elements are local editor preferences stored in browser local storage; they are not site content.
- Migration schema 2 removes retired dual-editor preferences without changing Page content.
- Deactivation preserves all content and settings.
- Uninstall preserves data by default; explicit cleanup removes only documented Cresco options and metadata, never `post_content`.
- Rollback from this alpha: deactivate the plugin and continue editing native blocks in Gutenberg. Review schema compatibility before installing older plugin code.

## Current limitations

The Elements Library is the first visual-builder stage. Dedicated responsive property controls, custom Tabs/Modal/Slider blocks, Templates, Theme Builder, Dynamic Data bindings, Query Builder, WooCommerce Builder, and Live Frontend Preview remain in the authoritative roadmap and are not yet claimed complete.

See [Architecture](docs/ARCHITECTURE.md), [Roadmap](docs/ROADMAP.md), [Known limitations](docs/KNOWN_LIMITATIONS.md), and [Release checklist](docs/RELEASE_CHECKLIST.md) for evidence-backed status.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
