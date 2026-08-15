# Realtime canonical Studio preview

Cresco Studio uses one persistent canonical iframe as its visual renderer.

## Runtime contract

- The persisted document is rendered once on the server during Studio bootstrap and embedded as the initial iframe document.
- The legacy React canvas remains hidden and may only be used temporarily as an interaction bridge.
- Session edits patch the current iframe immediately. Root node CSS is compiled in the browser from the current Session, so style changes do not wait for a REST round trip.
- RenderEngine reconciliation runs in the background. It updates canonical HTML plus server-compiled root/stable CSS in place without replacing `iframe.srcdoc`, blanking the surface, or showing a blocking spinner after the first render.
- A new local edit clears the previously reconciled root CSS before installing the full live root stylesheet. This prevents removed/reset declarations from leaking through from an older server render.
- Renderer failures after hydration are non-blocking: the existing preview remains visible and editable while the status reports delayed synchronization.
- If no bootstrap render can be produced, only then may Studio show the blocking renderer/retry state.

## Regression rules

The canonical runtime must keep `legacyVisualFallback` false, `realtime` true, and `iframeReloadOnEdit` false. Session-change handlers must call the local live patch path before scheduling background reconciliation.
