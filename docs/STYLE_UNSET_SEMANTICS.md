# Studio style unset semantics

Cresco Studio treats the Layout, Style, and Advanced inspector as an override layer.

## Contract

- An empty inspector field means **no CSS override is stored** for that property at the active scope.
- Wide + Normal with no override falls back to the widget, theme, or browser cascade.
- Desktop, Laptop, Tablet, and Mobile with no override inherit the last value from a wider breakpoint when one exists; otherwise they fall back to the normal cascade.
- Hover, Focus, and Active with no override fall back to the Normal effective value.
- Reset writes the CSS-wide keyword `initial` at the active scope. This intentionally blocks a wider-breakpoint or state value and returns the property to its CSS specification initial value.
- Clearing an input or choosing the empty option removes the active override and restores inheritance/cascade behavior.
- Inherited/default values may be shown as placeholders, but placeholders are never serialized into the Cresco Session.
- Multi-selection shows an explicit value only when the selected widgets share the same override. Mixed or unset selections stay visually empty until the user enters a new override.

## Persistence and rendering

The canonical Studio `style()` mutation deletes empty keys from base, responsive, and state buckets. The frontend CSS compilers skip empty declarations as a second safety boundary. A Reset value is different: `initial` is deliberately persisted and emitted as CSS so it can override inherited Cresco breakpoint/state values without hard-coding a literal value such as `auto`, `flex`, `44px`, or `176px`.

Purpose-built widget behavior remains separate from style overrides. For example, Container, Columns, and Spacer have semantic properties needed to perform their documented function; clearing a Style Inspector override does not remove those widget semantics.

## Runtime presentation

`website-builder-unset-styles.js` runs after the responsive inspector and UI correction modules. It reads the current Cresco Session, displays only the explicit value owned by the active scope, uses inherited/default values only as placeholders and context, intercepts the per-property Reset action to persist `initial`, and suppresses the legacy WordPress spinner so Studio startup presents a single loading indicator.
