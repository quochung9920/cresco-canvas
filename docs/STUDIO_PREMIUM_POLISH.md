# Studio Premium Polish

Cresco Studio keeps its existing runtime ownership, responsive inheritance, and structural UI-correction contracts. The premium layer is presentation-only and is loaded after the canonical Studio and UI-correction styles.

## Visual direction

- Preserve the light professional Studio shell and Cresco purple action accent.
- Add restrained blue, purple, and cyan ambient depth to the canvas instead of pastel page-wide fills.
- Increase hierarchy with tighter headings, cleaner panel edges, refined elevation, and more deliberate selected states.
- Treat the editable frame as the primary visual asset with stronger depth and focus feedback.
- Give widget cards, command surfaces, notices, AI preview, recovery, and empty states distinct but coherent surfaces.

## Interaction and state quality

- Buttons use subtle lift and elevation only for interaction states; disabled controls never move.
- Inputs, search, buttons, links, tree controls, and command surfaces retain visible keyboard focus.
- Loading receives a dedicated branded progress state.
- Fatal startup errors retain the existing retry behavior and gain a readable recovery surface.
- Empty, AI-preview, recovery, success, warning, and error states remain semantically driven by the runtime; the polish layer changes presentation only.

## Responsive and accessibility rules

- Desktop canvas depth is reduced at 1280px so the editing surface stays visually dominant.
- Compact Studio layouts receive reduced stage padding at 960px without changing structural ownership.
- `prefers-reduced-motion` removes decorative motion and loading rotation while preserving state visibility.
- `prefers-contrast: more` strengthens borders and focus indication.
- Glass effects are progressive enhancement; readable opaque backgrounds remain the baseline.

## Verification

Run:

```sh
npm run check:studio-premium
npm run lint:css
npm run lint:php
```

The premium checker also verifies that the stylesheet is enqueued by the Studio owner, registered in core asset diagnostics, included in the release allowlist, and part of the quality gate.

Hosted GitHub Actions must still be treated as separate release evidence. A local or static pass does not substitute for a hosted workflow run or a WordPress browser smoke test.
