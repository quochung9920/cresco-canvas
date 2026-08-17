# Accessibility Audit — 1.0.0-rc.1

Target: WCAG 2.2 AA cho critical Cresco editor workflow và frontend output.

File này tách automated evidence khỏi manual verification. **Configured test không phải PASS.**

## Automated gate

| Khu vực | Test/evidence | Trạng thái trước release workflow |
| --- | --- | --- |
| Standalone Studio/editor | axe serious/critical scan trong `tests/e2e/accessibility-release.spec.ts` | NOT RUN |
| Settings/critical configuration surface | axe scan trong cùng suite theo test selector hiện hành | NOT RUN |
| Frontend Cresco output | axe scan sau save/preview | NOT RUN |
| Browser coverage | Chromium, Firefox, WebKit release matrix | NOT RUN |

Serious/critical axe violation làm automated gate fail. Automated scan không chứng minh announcement quality, focus intent, keyboard efficiency, zoom usability hoặc screen-reader comprehension.

## Manual verification bắt buộc

| Check | Status |
| --- | --- |
| Keyboard-only: open editor, insert/edit, Settings/History, save, preview | MANUAL REQUIRED |
| Focus visibility/order, modal/off-canvas containment + return | MANUAL REQUIRED |
| `prefers-reduced-motion` | MANUAL REQUIRED |
| 200% zoom/reflow | MANUAL REQUIRED |
| 400% zoom/reflow | MANUAL REQUIRED |
| RTL workflow | MANUAL REQUIRED |
| Forced colors/high contrast | MANUAL REQUIRED |
| NVDA critical smoke | MANUAL REQUIRED |
| VoiceOver critical smoke | MANUAL REQUIRED |
| Dedicated Edge critical smoke | MANUAL REQUIRED |

Không claim screen-reader/manual accessibility pass nếu chưa có human record cho exact release candidate.

Accessibility vẫn là P0 commercial gate trong `COMMERCIAL_HARDENING.md` và `RELEASE_CHECKLIST.md`.
