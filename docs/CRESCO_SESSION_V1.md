# Cresco Session v1

Cresco Session is the authoritative visual document format used by the standalone Cresco Editor. It is also the copy/paste interchange format used when a page is analyzed or generated outside WordPress by ChatGPT or another AI tool.

The AI workflow is intentionally simple:

```text
Global Design + Widget Contract + Current Session
                    |
                    v
             Copy AI Context
                    |
                    v
                 ChatGPT
                    |
                    v
          cresco-session/v1 JSON
                    |
                    v
       Validate -> Apply -> Update
                    |
                    v
              Cresco Editor
```

Gutenberg block markup is not the AI interchange format. WordPress remains the host for authentication, permissions, media, routing, REST, and page delivery; the Cresco Session is the source of truth for a page that has a saved Cresco document.

## Design principles

1. Keep the widget catalog small and stable.
2. Prefer Global Design tokens over hard-coded visual values.
3. Prefer native widget props and structured `style` properties over Custom CSS.
4. Use widget-scoped Custom CSS only when the widget contract does not expose the required visual capability.
5. Every node has a stable, unique `id` so future patch workflows can address widgets deterministically.
6. Import never saves immediately. A session is validated, applied to the current editor state, and only persisted when the user presses **Update**.

## Document shape

```json
{
  "schema": "cresco-session/v1",
  "version": 1,
  "documentId": "home",
  "nodes": [
    {
      "id": "hero",
      "type": "container",
      "props": {
        "layout": "flex",
        "direction": "column",
        "align": "center",
        "justify": "center"
      },
      "style": {
        "maxWidth": "{layout.contentMax}",
        "paddingTop": "{spacing.2xl}",
        "paddingBottom": "{spacing.2xl}"
      },
      "responsive": {
        "mobile": {
          "paddingTop": "{spacing.xl}",
          "paddingBottom": "{spacing.xl}"
        }
      },
      "customCSS": {},
      "children": [
        {
          "id": "hero-title",
          "type": "heading",
          "props": {
            "text": "Build visually. Run natively.",
            "level": 1
          },
          "style": {
            "fontSize": "{typography.sizes.h1}",
            "textAlign": "center"
          },
          "responsive": {},
          "customCSS": {
            "base": "& { text-wrap: balance; }"
          },
          "children": []
        }
      ]
    }
  ]
}
```

## Core widgets

Version 1 intentionally exposes only a compact core:

- `container`
- `columns`
- `heading`
- `text`
- `button`
- `image`
- `list`
- `divider`
- `spacer`

The live catalog is exported by **Copy Widgets** and is included in **Copy AI Context**. AI output must not invent widget types or properties that are absent from that catalog.

## Global Design references

Structured styles may reference Global Design values using token paths:

```json
{
  "color": "{colors.text}",
  "background": "{colors.background}",
  "fontSize": "{typography.sizes.h2}",
  "paddingTop": "{spacing.xl}",
  "maxWidth": "{layout.contentMax}",
  "borderRadius": "{radius.md}",
  "boxShadow": "{shadows.md}"
}
```

The renderer compiles known token references to stable `--cc-*` CSS variables. Custom colors and aliases are included in the exported AI context as well.

## Responsive model

`style` is the base/widescreen style. Device overrides are stored in `responsive`:

```json
{
  "style": {
    "fontSize": "{typography.sizes.h1}"
  },
  "responsive": {
    "desktop": {},
    "laptop": {},
    "tablet": {
      "fontSize": "48px"
    },
    "mobile": {
      "fontSize": "36px"
    }
  }
}
```

Media-query boundaries are generated from Global Design breakpoints. Sessions and AI output do not need to write `@media` rules.

## Custom CSS

Custom CSS is a first-class fallback for widget capabilities that do not belong in the normal Inspector.

`&` always means the current widget:

```json
{
  "customCSS": {
    "base": "& { transition: transform var(--cc-motion) var(--cc-easing); } &:hover { transform: translateY(-3px); }",
    "mobile": "& { transform: none; }"
  }
}
```

Stable inner parts are published by the widget contract. For example, Button exposes its text part:

```css
& [data-cresco-part="text"] {
  letter-spacing: 0.02em;
}
```

Custom CSS rules are intentionally constrained:

- every selector must contain `&`;
- global selectors such as `html`, `body`, and `:root` are rejected;
- `@import`, `@media`, `@supports`, external `url()`, JavaScript expressions, and markup escapes are rejected;
- responsive Custom CSS uses the `desktop`, `laptop`, `tablet`, and `mobile` buckets instead of raw media queries.

When Global Design values are needed inside Custom CSS, use the CSS variables published in the AI context, for example `var(--cc-primary)`, `var(--cc-space-xl)`, or `var(--cc-radius-md)`.

## AI context

**Copy AI Context** returns four important sections:

```json
{
  "format": "cresco-ai-context/v1",
  "global": {},
  "cssVariables": {},
  "widgets": {},
  "session": {}
}
```

A recommended prompt after copying the context is:

> Analyze this Cresco AI Context and redesign the page. Use only widget types and properties declared in `widgets`. Prefer Global Design tokens and structured style values. Use scoped Custom CSS only for capabilities that are not available natively. Return one complete `cresco-session/v1` JSON object and no other format.

## Validation and limits

The server validates every imported or saved session. Version 1 enforces:

- schema/version compatibility;
- known widget types;
- stable unique widget IDs;
- parent/child capability rules;
- maximum 500 nodes;
- maximum nesting depth 12;
- allow-listed structured style properties;
- bounded/sanitized props;
- scoped Custom CSS with a per-widget size limit.

Invalid AI output never becomes the current document through the normal Import flow.

## Storage and frontend

A saved session is stored in page meta under `_cresco_canvas_document`. When the saved document contains nodes, Cresco renders that session for the page and emits its structured/responsive/scoped CSS. The existing page content remains untouched as a fallback and is used when no valid Cresco document is present.

This separation lets Cresco evolve its editor and AI workflow without requiring an AI tool to understand WordPress block serialization.
