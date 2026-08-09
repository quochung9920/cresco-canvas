# Global Design editing and import/export

Cresco Canvas keeps Global Design simple inside the **Global** tab. The same panel is used to edit values directly, import a configuration, and export the current configuration.

## Editor workflow

1. Open a Page with Cresco Canvas.
2. Open **Global**.
3. Edit Colors, Font family, Layout, Radius, or Breakpoints directly in the fields.
4. Click **Save Global**.
5. Use **Import** to paste CSS variables or JSON.
6. Use **Export** to copy the complete current Global settings as JSON.

After Global Design is saved, **Reload editor** refreshes Canvas token resolution and AI Context. Save any unsaved Page changes before reloading.

Import validation and saving use protected Cresco REST endpoints and require the WordPress `edit_theme_options` capability.

## CSS import example

```css
--bg: oklch(98% 0.005 250);
--surface: oklch(99% 0.002 250);
--surface-alt: oklch(95% 0.012 250);
--ink: oklch(22% 0.02 250);
--ink-muted: oklch(46% 0.015 250);
--blue-dark: oklch(38% 0.13 255);
--blue: oklch(55% 0.15 235);
--blue-light: oklch(90% 0.035 235);
--green: oklch(55% 0.13 145);
--border: oklch(88% 0.012 250);
font-family: Poppins, sans-serif;
color: var(--ink);
```

Built-in mappings include:

- `--bg` / `--background` -> `colors.background`
- `--ink` / `--text` / `--foreground` -> `colors.text`
- `--ink-muted` / `--muted` -> `colors.muted`
- `--blue` / `--primary` / `--brand` / `--accent` -> `colors.primary`
- Other safe color variables -> `colors.custom-*`

Custom colors are shown as editable rows in the Global panel after import. New custom colors can also be added manually.

## Supported color values

Global colors accept sanitized HEX, `rgb()`, `rgba()`, `hsl()`, `hsla()`, `oklab()`, and `oklch()` values. External resources and arbitrary CSS are rejected.

## JSON import/export

**Export** copies the complete editable Cresco Global settings as JSON. The importer accepts raw Cresco settings JSON, exported Global JSON, and the token catalog produced by older **Copy Global Config** builds.

Font-family import stores the font stack only. Cresco does not automatically download or load external font files.
