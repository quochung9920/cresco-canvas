# Cresco Website Builder Core

`website-core/v1` is the integrated website-building layer on top of the existing `cresco-session/v1` document. It does not introduce a second Page document format. Existing Cresco Session pages remain readable; a Page is marked as using the Website Builder renderer only after it is saved through the Website Builder endpoint.

## Product goal

Website Builder Core covers the practical workflow needed to build real WordPress sites rather than only static landing pages:

- professional responsive layout and styling;
- a broader widget library;
- site identity and navigation widgets;
- dynamic post/ACF data and bounded post loops;
- reusable components;
- forms backed by the existing signed Cresco Form runtime;
- WooCommerce product widgets when WooCommerce is available;
- Page Settings and Global Design in the same editor;
- Theme Builder management for headers, footers, single, page, archive, search, and 404 templates;
- AI context/import, History/revisions, keyboard commands, multi-selection, Navigator, and direct resize.

## Document compatibility

The stored document remains `cresco-session/v1` version 1. Website Builder nodes use validated `props`, `style`, `responsive`, `states`, `customCSS`, `meta`, and `children` fields. Builder metadata may contain a component ID, Navigator label, locked flag, and hidden flag; it never permits executable content.

The Page meta `_cresco_canvas_builder_version=website-core/v1` selects the Website Builder frontend renderer. Until a legacy Session is saved through the new builder, its previous renderer remains the fallback.

## Widget groups

The authoritative schema is `includes/Builder/WidgetCatalog.php`.

Layout: Container and Columns. Container supports Block/Flex/Grid, content width, direction, wrapping, alignment, justification, grid template, semantic tag, and accessible label.

Content and media: Heading, Text, Button, Image, List, Divider, Spacer, Icon, Icon Box, Video, Gallery, Testimonial, and Social Icons.

Interactive: Accordion, Tabs, Counter, and Progress. Accordion and Tabs use semantic ARIA relationships and keyboard behavior. Counter respects reduced-motion preferences.

Site: Site Logo, Site Title, Navigation Menu, and Breadcrumbs. Navigation references an existing WordPress menu ID; arbitrary menu markup is not accepted from Session JSON.

Dynamic: Post Title, Post Excerpt, Featured Image, Post Content, Dynamic Field, and Loop Grid. Dynamic Field supports allow-listed post meta, ACF when installed, site identity values, and a small allow-list of author values. Private meta keys beginning with `_` are not rendered. Loop Grid is bounded to 24 items, public post types/taxonomies, and allow-listed order/orderby values.

Forms: the Session Form widget is converted to native `cresco/form` and `cresco/form-field` blocks at render time. Existing FormBuilder remains responsible for signed configuration, server validation, honeypot/rate limiting, CAPTCHA, storage, notifications, retention, uploads, and redirects.

WooCommerce: Woo Products, Product Title, Product Price, Product Image, and Add to Cart. Woo widgets fail gracefully when WooCommerce is inactive. Product grids use WooCommerce's bounded Products shortcode with sanitized attributes. Product-context widgets require an active WooCommerce product context; the general Page editor primarily uses Woo Products.

## Inspector model

The unified editor reads the server-supplied widget catalog instead of discovering controls from visible labels. Inspector tabs are Content, Layout, Style, and Advanced. Editing contexts are Wide/Desktop/Laptop/Tablet/Mobile and Normal/Hover/Focus/Active.

The UI distinguishes Global token references, local values, breakpoint overrides, inherited values, and state overrides. Multi-selection applies structured style changes to selected nodes while content editing remains single-selection.

Custom CSS is a last-resort escape hatch. Every selector must contain `&`. The server rejects media/import/layer/support rules, `url()`, script-like constructs, global selectors, style tags, and CSS that escapes widget scope.

## Reusable components

Reusable subtrees are stored as private `cresco_component` posts. Saving a component validates the selected subtree with the same Website Builder sanitizer. Insertion creates a copy with fresh stable node IDs and records the source component ID in node metadata. The first Website Builder version intentionally uses copy-on-insert semantics; live synchronized instances remain a compatible extension.

## Theme Builder integration

The Website Builder panel manages the existing `cresco_template` system rather than implementing another router. It can create/list Header, Footer, Single, Page, Archive, Search, and 404 templates and open them in the existing WordPress/Cresco template editor. Existing Theme Builder conditions, priority, sanitization, and routing remain authoritative. Template content is not yet stored as a Website Builder Session document.

## Global Design and Page Settings

Global tokens continue to come from `GlobalStyles` and `DesignTokens`. The builder can search/copy tokens and edit core global settings for users with `edit_theme_options`.

Page Settings continues to use `_cresco_canvas_page_settings` and its existing REST service. Layout, page title, header/footer, content root, body style, and scoped Page Custom CSS remain separate from the Session document.

## AI workflow

The builder exports `cresco-website-builder-context/v1` with the current Session, professional widget schema, Global Design tokens, and installed capability flags for Theme Builder, Forms, WooCommerce, and ACF. A complete AI-generated `cresco-session/v1` document must pass Website Builder validation before it can replace the in-memory document. Applying an AI result does not save automatically.

## Security boundaries

Website Builder Core does not add arbitrary HTML, arbitrary shortcodes, scripts, remote CSS imports, or unbounded query definitions to the Session contract. Limits include maximum 1,000 nodes, maximum nesting depth 16, unique stable IDs, bounded structured collections, style-property allow-lists, scoped Custom CSS, public post type/taxonomy checks for loops, private-meta rejection for Dynamic Field, and capability checks for Page/component writes.

## Runtime ownership

`runtime-src/build/website-builder-editor.js` is the authoritative editor source. The checked-in `build/website-builder-editor.js` must be the same self-contained runtime byte-for-byte so a normal plugin checkout or ZIP can start the editor without serving `runtime-src/` in the browser. `scripts/build-runtime.mjs` refreshes the checked-in build from the authoritative source during a clean build, and `scripts/check-website-builder.mjs` rejects source/build drift or browser runtime references back into `runtime-src/`.

`runtime-src/build/website-builder-frontend.js` is a reviewed runtime copied byte-for-byte to `build/website-builder-frontend.js`.

## Scope boundaries

Website Builder Core substantially expands practical site-building capability, but it is not itself release certification. Browser/WP/PHP matrices, manual accessibility review, performance evidence, exact-ZIP install/upgrade evidence, and remaining P0 commercial hardening gates are separate release requirements.

Post-core expansions include live-synced component instances, Session-native Theme Builder template editing, a visual loop-template designer, pagination/facets for Session loops, deeper WooCommerce template controls, animation timelines, remote libraries, and collaboration.
