# Global Config Import

Cresco Canvas can import a site-wide Global Design configuration from either CSS variables or JSON.

## Editor workflow

1. Open a Page with Cresco Canvas.
2. Open **Global**.
3. In **Import Global Config**, paste CSS variables or JSON.
4. Click **Preview import**.
5. Review the detected mappings and ignored values.
6. Click **Apply Global Config**.
7. Save any unsaved Page changes, then use **Reload editor** so Canvas and AI Context read the new Global Design.

Previewing never saves settings. Applying uses the existing protected Cresco settings API and requires the WordPress `edit_theme_options` capability.

## CSS example

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

The original variable slugs are also preserved as Cresco aliases when possible.

## Supported color values

Global colors accept sanitized HEX, `rgb()`, `rgba()`, `hsl()`, `hsla()`, `oklab()`, and `oklch()` values. External resources and arbitrary CSS are not accepted by the importer.

## JSON

The importer accepts both raw Cresco settings JSON and the token catalog produced by **Copy Global Config**, enabling a Global Design to be copied from one Cresco installation and imported into another.

Font-family import stores the font stack only. Cresco does not automatically download or load external font files.
