# Cresco AI Context v1

`cresco-ai-context/v1` is Cresco Canvas's portable, design-only interchange envelope for external AI systems. It describes what an AI may understand and edit without giving the AI direct access to the DOM, WordPress credentials, or arbitrary application state.

## Envelope

```json
{
  "schema": "cresco-ai-context/v1",
  "version": 1,
  "scope": "subtree",
  "mode": "optimized",
  "baseChecksum": "sha256…",
  "target": {
    "scope": "subtree",
    "nodeId": "hero",
    "type": "container"
  },
  "environment": {
    "crescoVersion": "…",
    "sessionSchema": "cresco-session/v1",
    "postId": 123,
    "postTitle": "Home"
  },
  "designSystem": {},
  "pageSettings": {},
  "contracts": {},
  "content": {},
  "dependencies": {
    "tokens": [],
    "media": [],
    "responsive": []
  },
  "instructions": []
}
```

`baseChecksum` is the SHA-256 checksum of the complete sanitized `cresco-session/v1` document at export time. A `cresco-patch/v1` result must return this exact value. Cresco rejects a stale patch instead of silently overwriting later edits.

## Scopes

### `page`

Exports the complete current Session. `Full Context` includes the complete contract catalog and complete Design System. `Optimized Context` keeps the full page content but reduces supporting dependencies where possible.

### `subtree`

Exports the target node plus all descendants, its minimal ancestry, contracts required by that content, semantic token dependencies, relevant responsive devices, and media descriptors.

### `widget`

Exports only the selected node. Children are intentionally omitted even when the selected widget can contain children. Minimal parent/ancestry context is included so AI can make layout-aware changes without gaining edit authority over the parent.

### `selection`

The protocol supports multiple `nodeIds`. The current standalone editor is single-select, so the v1 UI exports the current selection as a one-node selection. This keeps the wire protocol ready for future multi-select without changing the schema.

## Full vs Optimized

`mode: "full"` exports:

- complete Design System token catalog;
- raw and effective Page Settings;
- all current widget contracts;
- the selected content scope;
- all discovered dependencies.

`mode: "optimized"` exports:

- only the selected content and required ancestry;
- effective Page Settings;
- only semantic Design System tokens referenced by the exported content;
- breakpoint definitions when responsive overrides are present;
- only widget contracts required by the scope/ancestry;
- media descriptors used by the scope.

A Button edit therefore does not require the entire widget catalog.

## Widget Contracts

Contracts are derived from the authoritative Cresco Session widget catalog. Each contract exposes:

- `type`, `label`, `allowsChildren`, and child behavior;
- supported `props`, types, enums, bounds, and defaults;
- allowed structured style properties;
- responsive device support;
- token-reference syntax;
- scoped Custom CSS capability and stable `data-cresco-part` selectors;
- stable root selector key (`data-cresco-id`).

AI output containing an unsupported widget, prop, structured style property, responsive device, or Custom CSS bucket is rejected by the Patch validator before the Session sanitizer runs.

## Design token dependencies

Semantic references are preserved as references, for example:

```json
{
  "path": "spacing.xl",
  "fallback": "clamp(2rem, 1.35rem + 2.4vw, 4rem)"
}
```

AI should keep `{spacing.xl}` rather than replacing it with the resolved fallback unless the user explicitly asks for a local value.

## Media dependencies

Image dependencies use portable descriptors:

```json
{
  "nodeId": "hero-image",
  "id": 123,
  "url": "https://example.test/uploads/hero.jpg",
  "alt": "…",
  "width": 1600,
  "height": 900,
  "policy": "URL is descriptive only; cross-site import must map media explicitly and must not auto-download remote URLs."
}
```

Attachment IDs are site-local and are never treated as portable identifiers. Cresco AI Interchange v1 does not automatically download remote media.

## Security and privacy boundary

Context is built only from Cresco design data: sanitized Session content, Design System, Page Settings, contracts, and derived dependencies. A defense-in-depth sanitizer removes secret-bearing keys such as nonces, passwords, cookies, authorization headers, API/license keys, webhook/client secrets, database credentials, access/refresh tokens, private form submissions, and user-session data.

The AI context does **not** grant AI direct WordPress or DOM mutation capability.

## Rendered appearance

The rest of the envelope is semantic: it describes what a document *means*, which
is the right shape for editing it. It says nothing about what the document *looks
like*, so a reader cannot judge overflow, contrast, alignment, or spacing
consistency from it.

Set `includeVisual: true` on the context request to attach a `visual` object:

| Field | Meaning |
| --- | --- |
| `html` | Rendered markup for the exported scope, from the same renderer as the public page |
| `css` | Compiled stylesheet for that scope, including state and responsive rules |
| `breakpoints` | Breakpoint starts, so a reader need not re-derive them from media queries |
| `maxWidths` | `max-width` boundaries used by the downward-inheriting cascade |
| `htmlTruncated`, `cssTruncated` | Whether the byte caps (500 KB markup, 256 KB CSS) applied |

It is off by default because markup and CSS dominate the payload. Rendering
follows the scope, so a `widget` export renders that widget rather than the page.

Editing must still be returned as `cresco-session/v1` or `cresco-patch/v1`. HTML
and CSS are provided to be read, never to be returned as a result.

### Standalone document

`POST /cresco-canvas/v1/ai-interchange/{postId}/visual`

Returns `cresco-ai-visual/v1` carrying a `document` string: a complete HTML page
with the stylesheet inlined and a minimal reset, so browser defaults do not read
as design faults. It opens in any browser with no WordPress, no plugin, and no
network access, which is what makes it usable for visual review or for a reader
that looks at pages rather than parsing JSON.

Studio exposes this through the **Export rendered HTML** command in the command
palette. The file is generated by the site's own renderer and saved locally;
nothing is transmitted anywhere, and carrying the file further is the operator's
choice.

## Container width rule

This rule is normative for AI output:

> `Container props.contentWidth="full"` means `width: 100%` of the Container's **parent**. It does not mean viewport width. AI must not use `100vw` to break an actual Container out of a boxed parent.

## API

The legacy readable endpoint remains for backward compatibility:

`GET /cresco-canvas/v1/ai-context/{postId}`

Canonical scoped exports use:

`POST /cresco-canvas/v1/ai-interchange/{postId}/context`

Request:

```json
{
  "session": { "schema": "cresco-session/v1", "version": 1, "documentId": "home", "nodes": [] },
  "scope": "subtree",
  "target": { "nodeId": "hero" },
  "mode": "optimized"
}
```

The editor sends its live in-memory Session so unsaved work participates in the checksum and exported context.
