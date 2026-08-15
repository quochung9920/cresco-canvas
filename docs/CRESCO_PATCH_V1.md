# Cresco Patch v1

`cresco-patch/v1` is the targeted mutation protocol used by Cresco AI Interchange. A patch is data, not executable code. It is never applied directly to the DOM, PHP, or WordPress storage.

## Envelope

```json
{
  "schema": "cresco-patch/v1",
  "target": {
    "scope": "subtree",
    "nodeId": "hero"
  },
  "operations": []
}
```

Supported target scopes are `page`, `subtree`, `widget`, `selection`, and `selection-subtrees` where supported by the scope resolver.

Cresco Patch v1 no longer binds a patch to an exported Session revision. There is no required checksum field and no stale-checksum rejection. A legacy `baseChecksum` field may still be present in older AI output, but the validator ignores it.

## Validation pipeline

Every patch follows this pipeline:

1. parse JSON;
2. verify `cresco-patch/v1` schema;
3. validate target existence and scope against the **current** Session;
4. validate node IDs and structural destinations;
5. validate widget contracts and operation permissions;
6. apply operations to an in-memory Session clone;
7. run the canonical Website Builder Session sanitizer, including scoped Custom CSS validation;
8. generate a structured Diff;
9. return the validated candidate for user review;
10. only after explicit **Apply** does the editor replace its local Session;
11. the user can **Undo** to the pre-AI checkpoint; persistence still requires **Update**.

Removing revision checks does not remove scope or contract safety. If the target no longer exists, the patch is rejected. If an operation escapes the target scope, uses an unsupported widget/property, or produces an invalid Session, it is rejected.

## Operations

### `setProps`

```json
{
  "op": "setProps",
  "nodeId": "cta",
  "props": { "text": "Book a survey" }
}
```

Only props declared by that widget contract are accepted.

### `setStyle`

```json
{
  "op": "setStyle",
  "nodeId": "hero",
  "style": { "paddingTop": "{spacing.3xl}" }
}
```

Only structured style properties declared by the contract are accepted.

### `setResponsive`

```json
{
  "op": "setResponsive",
  "nodeId": "hero",
  "responsive": {
    "tablet": { "paddingTop": "{spacing.xl}" },
    "mobile": { "paddingTop": "48px" }
  }
}
```

Devices are limited to `desktop`, `laptop`, `tablet`, and `mobile`. Widescreen/base values belong in `style`.

### `setCustomCSS`

```json
{
  "op": "setCustomCSS",
  "nodeId": "cta",
  "customCSS": {
    "base": "&:hover { opacity: .9; }"
  }
}
```

Buckets are limited to `base`, `desktop`, `laptop`, `tablet`, and `mobile`. Custom CSS is parsed by the canonical scoped CSS engine. Ordinary selectors must contain `&`. Local `@keyframes` / `@-webkit-keyframes` and scoped nested `@media`, `@supports`, `@container`, and `@layer` blocks are supported. Document-global/resource-loading constructs such as `@import`, `@charset`, `@namespace`, external `url()`, JavaScript/expression constructs, and global selectors remain forbidden.

### `insertNode`

```json
{
  "op": "insertNode",
  "parentId": "hero-actions",
  "index": 1,
  "node": {
    "id": "secondary-cta",
    "type": "button",
    "props": { "text": "Call us", "url": "#", "target": "_self" },
    "style": {},
    "responsive": {},
    "customCSS": {},
    "children": []
  }
}
```

`parentId: null` is allowed only for a page-scoped patch. The destination must allow children.

### `removeNode`

```json
{ "op": "removeNode", "nodeId": "old-badge" }
```

### `moveNode`

```json
{
  "op": "moveNode",
  "nodeId": "cta",
  "parentId": "hero-actions",
  "index": 0
}
```

A node cannot be moved into itself or one of its descendants. Moving to the Session root is page-scope only.

### `replaceSubtree`

```json
{
  "op": "replaceSubtree",
  "nodeId": "hero",
  "node": {
    "id": "ai-hero",
    "type": "container",
    "props": { "contentWidth": "full", "layout": "block" },
    "style": {},
    "responsive": {},
    "customCSS": {},
    "children": []
  }
}
```

The existing target root ID is preserved. Descendant IDs are kept when safe and remapped when they collide with IDs outside the replaced subtree.

## Scope boundaries

- `page`: may modify any node and may insert/move at the Session root.
- `subtree`: may modify the target and its descendants only; structural destinations must remain inside that subtree.
- `widget`: may only use `setProps`, `setStyle`, `setResponsive`, and `setCustomCSS` on the exact target widget. It cannot edit children or structure.
- `selection`: may modify selected node IDs only. V1 UI is single-select, while the protocol already accepts multiple `nodeIds`.

An escape attempt returns `cresco_ai_patch_scope_escape`.

## ID remapping

Cresco stable IDs are preserved when they do not collide. Inserted IDs that collide are deterministically suffixed (`-ai`, `-ai-2`, and so on). The validator returns `idMap`, and subsequent patch operations referring to a remapped AI ID are rewritten to the mapped ID.

The current core widget contracts do not contain cross-node ID reference props. When reference-bearing contracts are added later, those reference paths must be registered and remapped by the same ID layer.

## Diff

Validation returns a structured diff with:

- `changed` fields such as `props.text`, `style.fontSize`, or `responsive.mobile.paddingTop`;
- `inserted` nodes;
- `removed` nodes;
- `moved` nodes with old/new parent and index.

The review UI displays these changes before Apply.

## Full Session compatibility

The validation endpoint also accepts a complete `cresco-session/v1`. A full Session goes through the canonical Session validator and structured Diff before Apply. Existing Session import remains supported.

## API

`POST /cresco-canvas/v1/ai-interchange/{postId}/validate`

```json
{
  "currentSession": { "schema": "cresco-session/v1", "version": 1, "documentId": "home", "nodes": [] },
  "result": { "schema": "cresco-patch/v1", "target": { "scope": "page" }, "operations": [] }
}
```

The route only validates and returns a candidate Session + Diff. It does not persist the page. The standalone AI bridge applies the validated candidate to editor state, where normal Undo and Update semantics remain authoritative.

## Prohibited output

`cresco-patch/v1` has no JavaScript, DOM-command, arbitrary PHP, SQL, raw WordPress-meta, request-header, or credential operation. There is no bypass flag for the Cresco Session validator.
