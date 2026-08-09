# Accessibility Audit — 1.0.0-rc.1

Target: WCAG 2.2 AA for critical Cresco editor workflows and frontend output.

This file separates automated evidence from manual verification. A configured test is not a pass.

## Automated gate

| Area | Test/evidence | Current status before release workflow |
| --- | --- | --- |
| Standalone editor | axe serious/critical scan in `tests/e2e/accessibility-release.spec.ts` | NOT RUN |
| Settings Center | axe serious/critical scan in the same suite | NOT RUN |
| Frontend Cresco output | axe serious/critical scan after save/preview | NOT RUN |
| Browser coverage | Chromium, Firefox, WebKit release matrix | NOT RUN |

A serious or critical axe violation fails the automated gate. Automated scans cannot prove announcement quality, focus intent, keyboard efficiency, zoom usability, or screen-reader comprehension.

## Manual verification required

| Check | Status |
| --- | --- |
| Keyboard-only: open editor, insert/edit, Settings, History, save, preview | MANUAL REQUIRED |
| Focus visibility, order, modal/off-canvas containment and return | MANUAL REQUIRED |
| `prefers-reduced-motion` behavior | MANUAL REQUIRED |
| 200% zoom/reflow | MANUAL REQUIRED |
| 400% zoom/reflow | MANUAL REQUIRED |
| RTL workflow | MANUAL REQUIRED |
| Forced colors/high contrast | MANUAL REQUIRED |
| NVDA critical smoke | MANUAL REQUIRED |
| VoiceOver critical smoke | MANUAL REQUIRED |
| Dedicated Edge critical smoke | MANUAL REQUIRED |

No screen-reader or manual accessibility pass may be claimed until a human record exists for the exact release candidate.

Accessibility remains a P0 commercial gate in `docs/COMMERCIAL_HARDENING.md` and `docs/RELEASE_CHECKLIST.md`.
