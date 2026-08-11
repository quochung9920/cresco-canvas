# Ollama Super Upgrade — Architecture Audit

Branch: `ollama-super-upgrade-20260812`
Starting SHA: `ceeb120e0e3c367ae977b34892543f5797f5d55d`

This audit records ownership discovered during the Super Upgrade sprint. It is intentionally about contracts and competing authority, not feature count.

## Canonical runtime map

### Studio bootstrap and runtime ownership

- `WebsiteBuilderRuntimeOwner` is the primary browser-runtime owner and claims the Studio/consistency/bootstrap handles.
- `WebsiteBuilderStudio` attaches configuration and support assets to the existing Studio handle; it does not create another editor generation.
- `WebsiteBuilderModuleRegistry` defines core, core-extension, transitional and quarantined modules.
- `WebsiteBuilderSessionIsolation` blocks the legacy Session API from the canonical Website Builder page editor.
- Transitional Professional UX / Comprehensive modules remain opt-in and should not regain core ownership.

### Document model and validation

- `WebsiteBuilder::sanitize_session()` is the canonical Session boundary for Website Builder documents.
- `Document` provides portable document/session/checksum behavior.
- `PatchValidator` uses the canonical Website Builder sanitizer before returning a candidate Session.
- `WidgetCatalog` is shared by REST context, editor, AI contracts and rendering.

### Browser document state

Before this sprint, React Studio owned Session/history/dirty state while `website-builder-consistency-guard.js` independently owned revision/checksum/recovery/in-flight-save state.

The sprint adds `website-builder-document-store.js` as the canonical browser-side revision/persistence/recovery boundary. The consistency guard now delegates revision, document, checksum, save lifecycle, conflict and recovery state to that store instead of incrementing its own revision.

Remaining integration work: Studio still owns React Session, selection and its immediate undo/redo snapshots. The store exposes selection/transaction/history APIs and compatibility events, but Studio must be migrated to call those APIs directly before the browser state can be called fully single-owner.

### Mutation path

- `CommandBus` is the canonical validated mutation vocabulary.
- `TransactionManager` groups multiple commands into one validated candidate/diff/history unit.
- AI patch application must use validated patch/command flow; it must not persist directly.
- Pointer drag is the document-movement owner; responsive-properties is explicitly UI-only for drag behavior.

Remaining integration work: the current Studio React runtime still performs several local clone/map mutations before emitting `cresco:studio-session-change`. Canvas, Structure and Inspector should be migrated incrementally to dispatch canonical commands rather than gaining a second mutation implementation.

### Persistence and concurrency

- `WordPressDocumentRepository` is the canonical storage adapter and now owns canonical persisted checksum/verification helpers.
- `WebsiteBuilderConcurrencyGuard` applies the same optimistic precondition + mutex to legacy Session, Website Builder Session and Theme Session writes.
- Missing `baseChecksum` fails closed with 428; stale writes fail with 409; concurrent lock contention fails with 423.
- Successful Session responses are verified against persisted checksum before being treated as successful.

Remaining ownership conflict: `SessionManager`, `WebsiteBuilder` and history restore paths still contain direct post-meta writes. They are protected by the concurrency boundary but should converge on `DocumentRepository::save()` so persistence implementation itself has one owner.

### History and recovery

- Server revision history remains in `HistoryManager`.
- Browser crash recovery is now represented in the document store as `cresco-recovery/v1` with document ID, revision, timestamp, title and Session.
- An older in-flight save cannot clear a newer local recovery snapshot because `markPersisted()` only clears dirty/recovery when its start revision still equals the current revision.

Remaining work: migrate Studio undo/redo to store transactions so undo, redo, AI apply, component operations and typing/drag gestures share one revision model.

### Responsive and style resolution

- Canonical breakpoint order is `wide -> desktop -> laptop -> tablet -> mobile`.
- `StyleCascade` resolves sparse overrides across token/global/component/local layers and reports property provenance (`value`, `source`, `breakpoint`, `state`, inheritance and previous explicit breakpoint).
- `StyleCascade::fluid()` provides a validated first-class clamp foundation without requiring raw clamp syntax in UI.
- Design Tokens now expose semantic colors, typography, space, radius, shadow, containers, transitions and z-index while retaining legacy token aliases.

Remaining work: wire provenance/status into Inspector controls and migrate existing responsive UI to consume one cascade helper instead of locally re-deriving inheritance.

### Renderer and core widgets

- `RenderEngine` routes canonical Session through `WebsiteRenderer` and renderer parity completion.
- Text rich content is sanitized with WordPress allow-listing.
- Button new-tab links normalize safe `noopener noreferrer` behavior.
- Image rendering defaults to lazy loading and async decoding.
- Container layout props compile through the same frontend CSS compiler as the canonical renderer.

Gaps to close together (catalog + sanitizer + renderer + Inspector): semantic `article` container support; richer image decorative/loading/priority/object-position contract; button accessible-label/disabled foundation; deeper Divider/Icon/Spacer responsive controls. Do not add Inspector controls until frontend parity exists.

### Accessibility and design diagnostics

`DocumentDiagnostics` now performs non-mutating document checks with locatable `nodeId`/path output for:

- multiple H1 headings;
- heading level skips;
- image missing alt unless explicitly decorative;
- button missing accessible name;
- deeply nested layout;
- excessive local styles;
- redundant responsive overrides;
- safe new-tab rel normalization information.

Remaining work: expose these results in one Inspector/diagnostics surface and add keyboard/axe browser coverage when the WordPress test environment is available.

### Security

Current hardening preserved:

- canonical Session validation and node bounds;
- allow-listed custom CSS values/scoping;
- safe rich-text rendering and temporary Studio preview sanitization;
- URL sanitization;
- REST capability checks;
- optimistic concurrency + write verification;
- no direct Session POST fallback added to extensions.

## High-priority remaining ownership defects

1. Studio React local mutation/history must migrate to `crescoDocumentStore` + `CommandBus` rather than remain a parallel mutation owner.
2. Direct Session post-meta writes in legacy/canonical/history services must converge on `DocumentRepository`.
3. Selection must be emitted/consumed through the store; do not infer it using MutationObserver or DOM surgery.
4. Responsive Inspector must consume `StyleCascade` provenance instead of duplicating inheritance rules.
5. Core widget catalog changes must ship with sanitizer + frontend renderer parity in the same milestone.

## Performance observations

The current Studio still uses recursive tree helpers for several mutations and selections. With the hard document limit at 1000 nodes this is bounded, but repeated full-tree clone/map operations during high-frequency typing/drag remain a structural performance target. Prefer indexed node lookup and transaction batching before adding virtualization.

## Test environment status

GitHub Actions created a run for this branch but the job did not start. GitHub's check annotation reports: account locked due to a billing issue. Therefore that run is not evidence of code test failure or success.

Local shell in this session cannot resolve `github.com`, so a full checkout-based npm/PHP/Playwright suite cannot be executed here. JavaScript syntax checks were executed on the new document-store and revised consistency-guard files before they were published. Repository quality gates were strengthened so they will run once CI capacity is restored.
