# Website Builder Comprehensive V3

Comprehensive V3 is an additive professional workflow layer for the existing `website-core/v1` builder. It does not change the `cresco-session/v1` schema.

## Phase 1 — rendering parity

- Frontend document styles are normalized to the authoritative `WebsiteBuilderCssCompiler` range model.
- Editor virtual breakpoints keep the same structured style contract, and V3 adds Pixel 100% for direct frontend comparisons.
- Scoped Custom CSS gets an unsaved live preview for the selected widget while editing.
- Native Form rendering stays authoritative on the frontend, with editor Form controls normalized for closer visual parity.

## Phase 2 — portable interchange and AI

`cresco-interchange/v1` packages represent an entire page, selected subtree/section, one widget, or a multi-widget selection. Export packages include source checksum, dependencies, optimized Design System data and scoped AI context.

Import remains non-destructive until Preview Diff succeeds. Insert/replace actions go through the existing `cresco-patch/v1`, `PatchValidator`, `PatchApplier` and `IdRemapper` pipeline. Destinations are before, after, inside, replace and replace-page. Preview returns the candidate Session, structural diff, ID remaps and dependency warnings. The editor stages the candidate through its existing Validate -> Apply workflow so Undo/History behavior is preserved.

Media references remain descriptors and are never auto-downloaded. Global Design token and media dependencies are carried with the package so destination-site mapping can be reviewed before applying.

## Phase 3 — builder systems

- Professional Canvas/Inspector V2 remains the primary visual editing surface; V3 links portable design, accessibility and production tools into it rather than creating a second editor.
- Linked reusable component instances can be explicitly synchronized from their published component source with collision-safe descendant IDs.
- Existing Loop Grid query controls continue to provide bounded post type, ordering, taxonomy, column and content controls. Reusable Components and section/widget interchange are the portable authoring path for custom repeated layouts in this tranche.
- Theme Builder templates now have a Session-native bridge. Theme template REST items point their Edit action to a dedicated Cresco Theme Template editor using the same Website Builder runtime. Saving the template stores `cresco-session/v1` and replaces its block content with the safe dynamic `cresco/theme-session` bridge block, so existing Theme Builder display conditions and template resolution stay intact.
- Theme Session templates enqueue the Website Builder frontend runtime/CSS and compile the same authoritative structured styles used by Pages.

## Phase 4 — commerce and production hardening

- WooCommerce capability detection accepts the WooCommerce class, `WC_VERSION`, or `WC()` bootstrap signals. Existing Woo Products/Product Title/Product Price/Product Image/Add to Cart widgets can now be used in Session-native Theme Builder templates, including product-specific Single template conditions.
- The V3 production panel reports node count, maximum nesting depth, Custom CSS volume, Forms, Loop Grids and WooCommerce widget usage.
- Canvas accessibility scan checks missing image alt text, empty/hash links, heading-level jumps and multiple visible H1 elements. It is an authoring aid and does not replace release accessibility automation.
- Existing license/update/migration, upgrade smoke, ZIP install, browser E2E, accessibility and performance suites remain the release authority.

## Release gate

`npm run check:comprehensive-v3` verifies runtime syntax, source/build equality, service registration, portable interchange contracts, Theme Session bridge registration, accessibility/performance tool tokens, runtime manifest ownership and release-package allowlisting. `check:quality` includes this gate.

The plugin remains `1.0.0-rc.1`; V3 adds commercial-grade workflows but does not by itself certify a stable release without hosted CI/browser/accessibility/release evidence.
