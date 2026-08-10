# Website Builder Comprehensive V3

Comprehensive V3 is an additive professional workflow layer for the existing `website-core/v1` builder. It does not change the `cresco-session/v1` schema.

## Rendering parity

- The frontend document stylesheet is normalized to the authoritative `WebsiteBuilderCssCompiler` range model.
- The editor continues to use virtual breakpoint widths, and Comprehensive V3 adds a Pixel 100% control for direct frontend comparisons.
- Scoped Custom CSS receives an unsaved live preview for the selected widget while the Inspector textarea is being edited.
- Existing native Form rendering and the Form compatibility repair remain in place.

## Portable interchange

`cresco-interchange/v1` packages can represent an entire page, selected subtree/section, one widget, or a multi-widget selection. Export packages include source checksum, dependencies, Design System information and scoped AI context.

Import is non-destructive until preview succeeds. The server converts insert/replace actions through the existing `cresco-patch/v1`, `PatchValidator`, `PatchApplier` and `IdRemapper` pipeline. Supported destinations are before, after, inside, replace, and replace-page. Preview returns the candidate Session, structural diff, ID remaps and dependency warnings. The editor then stages the candidate through its existing Validate -> Apply workflow so Undo/History behavior is preserved.

## Builder workflow

- Linked reusable component instances can be explicitly synchronized from their published component source.
- Existing Canvas, Professional Inspector, Loop Grid query controls, Theme Builder, Dynamic Data, Forms and reusable Components remain the authoritative systems; V3 links to them instead of creating parallel editors.
- Theme templates remain supported by the existing Theme Builder conditions and block renderer. Session-native template editing is still a separate architectural migration and is not claimed by this V3 layer.

## Production tools

The V3 production panel reports node count, maximum nesting depth, Custom CSS volume, Forms, Loop Grids and WooCommerce widget usage. It also reports WooCommerce, ACF and Theme Builder capability state. WooCommerce detection accepts the WooCommerce class, `WC_VERSION`, or `WC()` bootstrap signals.

The Canvas accessibility scan checks common authoring issues such as missing image alt text, empty/hash links, heading-level jumps and multiple visible H1 elements. This is an authoring aid and does not replace the release accessibility test suite.

## Release gate

`npm run check:comprehensive-v3` verifies runtime syntax, source/build equality, service registration, portable interchange contracts, accessibility/performance tool tokens, runtime manifest ownership and release-package allowlisting. `check:quality` includes this gate.
