# Cresco AI Context v2

One-Shot authoring package. Additive to v1: v1 remains the compatibility interchange profile for existing integrations, while v2 adds enough authoring context for a model to build inside the selected scope in one exchange.

## Why v2 exists

v1 answers *what is here*. That is enough to edit something, and not enough to build something.

Ask v1 to redesign an empty Container and it exports one widget contract — the container itself — plus whatever design tokens that container already references, which is usually none. A model reading that package has nothing to build with. It invents widget names and property names, the patch fails validation, and the user pays for a second exchange to be told what was available all along.

v2 answers *what may be built here*. Three additions carry that:

| Addition | Problem it removes |
| --- | --- |
| `contracts.creationCatalog` | Every widget type the model may create, not just the ones present |
| `designSystem.available` | The whole token palette, present even when the scope references none |
| `returnContract.template` | The exact reply object, pre-filled with the resolved target |

The goal is not "the model never makes mistakes". It is to eliminate failures caused by missing Cresco knowledge and format ambiguity — the two classes Cresco can fix without judgement.

## Difference from v1

| | v1 | v2 |
| --- | --- | --- |
| Schema | `cresco-ai-context/v1` | `cresco-ai-context/v2` |
| Shape | Flat envelope | `scopePackage` + `authoringPolicy` + `returnContract` |
| Contracts | Scope types only (optimized) | `current` **and** `creationCatalog` |
| Design system | Dependency-optimized only | `available` **and** `used` |
| Visual | Opt-in, default off | Default on |
| Purpose | — | `edit`, `redesign`, `create`, `content`, `style`, `import` |
| Return shape | Prose instructions | Machine-readable contract with a filled target template |
| Session revision lock | None | None |

Both profiles are checksum-free for AI interchange. A patch is validated against the current Session's target, scope, contracts, IDs, and canonical Session rules rather than being bound to the exact Session revision that was exported.

## One-Shot flow

1. Select a Container, or select nothing to work on the whole page.
2. Type a request in ordinary language.
3. Press **Copy for AI**.
4. Paste into any external assistant, attaching reference images if you have them.
5. The reply is one `cresco-patch/v1` object.
6. Paste it back.
7. Cresco normalizes → validates against the current Session → previews the diff → applies.

No second prompt is needed to explain Cresco's schema, property names, responsive model, or Custom CSS rules. All of it travels in the package.

## Example: subtree package

```json
{
  "schema": "cresco-ai-context/v2",
  "version": 2,
  "purpose": "redesign",
  "mode": "optimized",
  "scopePackage": {
    "target": { "scope": "subtree", "nodeId": "one-shot-root", "type": "container" },
    "environment": { "crescoVersion": "…", "sessionSchema": "cresco-session/v1", "postId": 12, "postTitle": "Home" },
    "content": { "node": { "id": "one-shot-root", "type": "container", "children": [] }, "ancestry": [] },
    "designSystem": { "available": { "colors": {}, "spacing": {} }, "used": {} },
    "contracts": { "current": { "container": {} }, "creationCatalog": { "container": {}, "heading": {} } },
    "capabilities": { "patchOperations": [], "responsiveDevices": [], "states": [] },
    "visual": { "html": "…", "css": "…" }
  },
  "authoringPolicy": { "decisionOrder": [] },
  "returnContract": {
    "preferred": "cresco-patch/v1",
    "template": { "schema": "cresco-patch/v1", "target": {}, "operations": [] }
  }
}
```

## creationCatalog semantics

`contracts.creationCatalog` is `ContractRegistry::all()`, which is generated from `WidgetCatalog`. There is no second hand-maintained list of widgets for AI, and there must never be one: a list that drifts from the validator produces packages that promise what the validator refuses.

`contracts.current` stays scoped to the types actually present, so a model editing an existing section is not forced to read the whole catalog to find them.

## designSystem.available vs designSystem.used

- `used` — dependency-optimized, the tokens the scope actually references.
- `available` — the complete catalogue, for authoring.

Both are present. `used` tells the model what the design already commits to; `available` tells it what it is allowed to reach for.

## Visual context

`scopePackage.visual` comes from `VisualContext::build()`, which uses the same `WebsiteRenderer` that produces the saved public page. It carries rendered HTML, compiled CSS, breakpoint starts, max-width boundaries and truncation flags.

There is one renderer. A second one would drift, and the whole value of the visual block is that it shows what the page really looks like.

It defaults on for v2 because a One-Shot request is usually about appearance, which the semantic tree cannot express. It can still be switched off for payload reasons.

## Authoring decision order

`authoringPolicy.decisionOrder` states the ladder:

```
widgetProps → structuredStyle → responsiveStyle → states → customCSS
```

Custom CSS is the last resort, not the first tool. The policy also carries the reference-image priority: explicit text request, then image intent, then existing design semantics, then contracts as a hard technical boundary. An image may influence design; it cannot authorize a widget type the contracts do not declare.

### The responsive model, stated twice on purpose

`capabilities.responsiveDevices` lists **four** buckets: `desktop`, `laptop`, `tablet`, `mobile`. `wide` is deliberately absent — it is the base, written to `node.style`, and `responsive.wide` fails validation. This is the single most common source of invalid output, so it appears in `capabilities.responsiveModel` and again in the prompt prose.

## returnContract

The model should never infer what Cresco expects back.

`template` arrives pre-filled with the resolved `target`. The model fills `operations` and changes nothing else. No checksum is required or emitted.

`preferredOperationForRedesign` is `replaceSubtree` when redesigning a whole selected section, and a minimal operation otherwise. It is guidance, not a requirement: forcing `replaceSubtree` for a colour change would be worse output, not better.

## Current-Session validation instead of checksum locking

AI interchange deliberately does not use a Session checksum or stale-revision gate. This keeps the copy/paste workflow usable even if another part of the page changes between export and import.

Safety remains structural and scope-based:

- the target node must still exist in the current Session;
- every operation must remain inside the exported target scope;
- widget types, props, styles, responsive buckets, states, and Custom CSS must match the current contracts;
- structural destinations must still be legal;
- the resulting candidate must pass the canonical Website Builder Session sanitizer;
- the user still reviews the Diff and explicitly applies the candidate;
- persistence still requires the normal **Update** action.

Older AI output may contain a `baseChecksum` field. The current validator ignores that legacy field instead of rejecting the patch as stale.

## Security boundary

The package carries design data only. `ContextSanitizer` runs over the finished envelope and strips secret-bearing keys recursively — nonces, passwords, cookies, authorization headers, API and licence keys, webhook and client secrets, access and refresh tokens, private form submissions.

v2 grants no new capability. It does not add API connectivity, does not store keys, does not let AI mutate the DOM, and does not persist anything: applying a validated patch stages a candidate that the normal editor save still has to commit.

## Import normalization

`AIResultNormalizer` removes formatting noise that has nothing to do with safety: surrounding whitespace, a UTF-8 BOM, and a Markdown fence when the entire response is exactly one fenced block.

It refuses to guess. It will not scan prose for the first `{`, will not choose among several JSON objects, and will not repair malformed JSON — each of those turns "the model returned something unclear" into "Cresco applied something nobody reviewed". Ambiguity returns an error.

Normalization is server-side and authoritative. The editor may guess a schema to label the paste box; that guess never decides validity. Everything that survives normalization goes through `PatchValidator` unchanged.

## Backward compatibility

One endpoint serves both profiles:

`POST /cresco-canvas/v1/ai-interchange/{postId}/context`

- No `version` and no `profile` → v1 compatibility profile.
- `version: 2` → v2 package.
- `profile: "one-shot"` → v2 package plus a ready-to-paste `prompt`, and `includeVisual` defaults true.

`cresco-patch/v1` remains the targeted patch schema, but revision checksum enforcement has been removed from AI interchange. Scope enforcement, target existence checks, contract validation, ID remapping, canonical Session validation, Diff review, Undo, and normal Update persistence remain authoritative.

## Reference images

Cresco does not encode external screenshots into the package. The prompt tells the model to treat attached images as visual direction while contracts remain authoritative for what can actually be built.
