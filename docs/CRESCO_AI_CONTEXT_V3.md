# Cresco AI Context v3 — High-Fidelity One-Shot Design

## Purpose

`cresco-ai-context/v3` is the default authoring package for the simplified **Copy for AI** workflow. It keeps `cresco-patch/v1` as the import format and does not reintroduce checksums.

The design goal is simple: a person selects a section, describes the desired result once, optionally attaches a reference image in the external AI chat, and receives a patch that Cresco can validate and review without requiring the person to understand Cresco internals.

## What v3 adds over v2

V2 solved contract completeness. V3 focuses the package and adds design context:

- `task`: natural-language request and immutable editable target.
- `scopePackage.scene`: ancestry, sibling summaries and the containing top-level root as **read-only context**.
- `scopePackage.contracts.recommended`: a focused set of complete contracts suitable for the request.
- `scopePackage.contracts.catalogIndex`: compact discovery metadata for every registered widget.
- `scopePackage.visualFacts`: explicit semantic facts derived from Session data. It always declares whether geometry was actually measured.
- `scopePackage.visual.contextMode`: narrow targets are rendered inside their real top-level branch when possible, preserving parent/sibling layout context.
- `returnContract.examples`: concrete operation examples using the resolved target ID.
- quality goals in `authoringPolicy`.

In optimized mode, v3 deliberately does **not** send the full `creationCatalog`. A model may author new nodes only from full contracts present in `contracts.current` or `contracts.recommended`. `catalogIndex` is discovery metadata, not permission to guess props. Full mode retains the complete creation catalog.

## Default One-Shot request

The normal Studio action sends roughly:

```json
{
  "version": 3,
  "profile": "one-shot-v3",
  "purpose": "redesign",
  "scope": "subtree",
  "target": { "nodeId": "selected-node" },
  "mode": "optimized",
  "includeVisual": true,
  "request": "Match the attached reference image and keep the copy."
}
```

When nothing is selected, the default target is the page.

The server returns:

```json
{
  "package": { "schema": "cresco-ai-context/v3" },
  "prompt": "CRESCO ONE-SHOT DESIGN TASK ..."
}
```

The clipboard receives `prompt`, not a raw schema dump.

## Contract focus

`contracts.recommended` is derived from the real `ContractRegistry`; there is no second widget schema. It begins with widget types already in the scope plus common layout/content primitives that actually exist in `WidgetCatalog`, then deterministically adds contract types whose type/label/category matches meaningful words in the user's request.

The package still provides `catalogIndex` for every widget so the model understands the product vocabulary without paying the token cost of every full contract.

## Visual context ring

A target section often renders differently when removed from its parent. For example, a hero inside a boxed 1200px grid can look incorrect when rendered as a standalone root.

For a narrow target, `VisualContext` now tries to render the complete **top-level branch containing the target**. This preserves the real ancestor and sibling layout while the patch target remains unchanged.

The visual payload clearly marks:

- `contextMode`
- `contextRootIds`
- `editableTarget`

The model may look at the context but may not edit it.

## Visual facts

Server-side v3 never fabricates browser geometry. `visualFacts` currently contains semantic/session-derived information such as:

- node count and maximum depth;
- widget types in the target;
- responsive buckets used;
- number of Custom CSS widgets;
- text/image/interactive widget counts.

It declares:

```json
{
  "source": "session-semantic-summary",
  "measuredGeometry": false
}
```

A future browser capture may add real box measurements while preserving this distinction.

## Import and review

The validate endpoint accepts the raw pasted text. `AIResultNormalizer` removes deterministic formatting noise such as a single Markdown fence or UTF-8 BOM, then the result still passes the normal Cresco validator.

A successful validation now may contain:

- structured `diff`;
- deterministic `quality` preflight;
- `visualReview.beforeDocument`;
- `visualReview.afterDocument`.

The Studio can open a modal before/after review at Desktop, Tablet and Mobile reference widths.

## Quality preflight

`DesignQualityGate` runs only checks that can be proven from Session data. Current examples include:

- heading hierarchy jumps;
- empty image alt text;
- empty button labels;
- missing button destination as informational feedback.

It explicitly lists what it did **not** check, including browser geometry, horizontal overflow, pixel contrast and similarity to a reference image. This prevents the UI from claiming evidence it does not have.

## Repair prompt

When validation fails, the new Studio bridge keeps the error message and structured details (path, operation index, property, widget type, node ID where available). **Copy repair prompt** creates a deterministic second prompt containing the previous result and return contract. Cresco does not silently mutate an ambiguous AI response.

## UI model

The normal AI Studio is intentionally small:

1. Current target.
2. Natural-language request.
3. Reference-image hint.
4. **Copy for AI**.
5. Paste result.
6. **Validate & Preview**.
7. Review diff/preflight/visual before-after.
8. **Apply to current target**.

Scope and context-detail controls remain under **Advanced export** for developers.

## Backward compatibility

- `cresco-ai-context/v1` remains available when no newer version/profile is requested.
- explicit `version: 2` remains v2, including `profile: one-shot-v2`.
- profile-only `one-shot` now resolves to v3.
- `cresco-patch/v1` remains the preferred return schema.
- AI workflow remains checksum-free.
- Apply changes only local editor state; normal **Update** is still required to save.
