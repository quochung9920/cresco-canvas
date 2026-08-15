# Cresco Canvas Professional Widgets & Border Controls

This document describes the professional widget suite and the linked border editor introduced on top of the canonical Cresco Session/WidgetCatalog architecture.

## Goals

1. Keep saved documents inside the existing `cresco-session/v1` model.
2. Keep the server allow-list and AI creation catalog driven by `WidgetCatalog`.
3. Reuse one browser interaction engine for carousels and related motion widgets instead of shipping a separate library for every widget.
4. Make common visual patterns native so users and AI do not need Custom CSS for sliders, infinite marquee loops, ratings, before/after comparisons, or per-side borders.
5. Preserve backwards compatibility with the existing CSS shorthand properties.

## Border controls

Cresco continues to save the standard structured CSS keys:

- `borderWidth`
- `borderStyle`
- `borderColor`
- `borderRadius`

The Inspector now presents each shorthand visually as four values. Width, Style and Color use CSS order `top right bottom left`. Radius uses `top-left top-right bottom-right bottom-left`.

The controls start in **Linked** mode. Unlinking exposes all sides/corners. This is intentionally implemented as an editor control over CSS shorthands rather than a new document schema, so existing sessions and the renderer remain compatible. Responsive and state buckets automatically receive the same shorthand because the visual control drives the canonical React field for the active bucket.

The same semantics are exposed in every widget blueprint as `blueprint.styleShorthands`, and `ContractRegistry` passes those blueprints through to AI contracts so external AI receives the exact side/corner order instead of guessing.

## Professional widget suite

### Carousel family

- `carousel` — arbitrary direct children become slides.
- `slides` — hero-oriented single-slide viewport with slide/fade behavior.
- `loop-carousel` — reuses the existing bounded Loop Grid query model and renders the result with the carousel engine.
- `image-carousel` — Media Library/gallery data rendered as a carousel.
- `testimonial-carousel` — nested testimonial/content slides.
- `logo-carousel` — nested logo/image slides with optional grayscale presentation.
- `media-carousel` — mixed image/video/content slides.

Shared controls include slides per view, tablet/mobile counts, gap, loop, autoplay, autoplay delay, transition speed, pause on hover, arrows, dots/fraction pagination, centered layout, adaptive height, and keyboard navigation.

### Infinite Marquee

`marquee` is a native nested-content infinite loop. Direct children are duplicated by the frontend engine and can run left, right, up, or down. The widget supports duration, gap, pause on hover/focus, edge fade, and `prefers-reduced-motion` fallback.

### Interactive and content widgets

- `before-after`
- `timeline`
- `pricing-table`
- `countdown`
- `modal`
- `off-canvas`
- `comparison-table`
- `hotspot-image`
- `flip-card`
- `animated-headline`
- `progress-circle`
- `rating`
- `site-search`
- `advanced-breadcrumbs`
- `map`

These widgets use existing validated prop primitives (string, enum, bool, number, CSS value, URL, bounded list/JSON) and therefore remain visible to the same editor, REST context, AI catalog, and validation pipeline.

## Rendering architecture

`ProfessionalWidgets` is an adapter around `WebsiteRenderer` rather than a second renderer.

1. The canonical Session remains unchanged in storage.
2. When a document contains a professional widget, the adapter creates an in-memory renderer representation using safe existing primitives such as `container`, `gallery`, `loop-grid`, `image`, or `breadcrumbs`.
3. `WebsiteRenderer` renders and compiles that translated document.
4. The resulting root element is annotated with `data-cresco-pro-widget` and a compact encoded config.
5. `professional-widgets.js` initializes the appropriate behavior.

This keeps style compilation, responsive/state handling, scoped CSS, global tokens, and existing security boundaries in the canonical renderer.

## Shared frontend engine

The shared runtime provides:

- carousel navigation, pagination, autoplay, keyboard control, centered slides, adaptive height and responsive slide counts;
- seamless duplicated marquee groups with horizontal/vertical motion;
- before/after range comparison;
- countdown updates;
- animated headline rotation;
- progress circle and rating rendering;
- accessible modal/off-canvas behavior;
- comparison table construction;
- WordPress search form rendering;
- constrained map embedding;
- flip-card, timeline, pricing and hotspot behavior.

No third-party carousel dependency is required.

## AI authoring guidance

AI should prefer the native widgets before emitting Custom CSS. Examples:

- use `marquee` instead of hand-written duplicated list `@keyframes`;
- use `loop-carousel` instead of converting a post query into custom HTML;
- use `carousel` for nested cards and `image-carousel` for Media Library images;
- use CSS shorthand values for per-side border styling, e.g. `borderWidth: "1px 0 2px 0"`;
- use radius shorthand in corner order, e.g. `borderRadius: "16px 16px 4px 4px"`.

The authoritative list of available controls is always the exported `WidgetCatalog`/AI creation catalog.
