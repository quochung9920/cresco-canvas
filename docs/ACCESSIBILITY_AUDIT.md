# Accessibility Audit

Target: WCAG 2.2 AA for critical editor workflows and generated interactive components.

## Milestone 0.2 review

| Area | Evidence | Status |
| --- | --- | --- |
| Semantic shell regions | `main`, `section`, `aside`, headings, and labelled control group are present | PASS (code review) |
| Form labels | WordPress controls use labels; color inputs have explicit `for`/`id` pairs | PASS (lint/code review) |
| Keyboard focus | Native buttons/links plus visible `:focus-visible` treatment | PASS (code review) |
| Error recovery | Crash view uses `role="alert"` and exposes both recovery paths | PASS (code review) |
| Reduced motion | Canvas width transition is disabled for `prefers-reduced-motion` | PASS (code review) |
| Automated serious/critical issues | Playwright runs axe on the loaded Canvas shell in Chromium, Firefox, and WebKit | NOT TESTED locally; CI configured |
| Full keyboard workflow | Add/select/edit/save/bypass/Safe Mode, including focus order and traps | NOT TESTED manually |
| Screen reader | NVDA/JAWS/VoiceOver announcements and control names | NOT TESTED manually |
| Browser zoom/reflow | 200% and 400% zoom | NOT TESTED manually |
| RTL | Generated RTL editor stylesheet exists | NOT TESTED manually |
| High contrast/forced colors | No dedicated validation | NOT TESTED |
| Mobile/touch editor | Device simulation exists; interaction validation not performed | NOT TESTED |

The E2E accessibility assertion blocks serious or critical axe findings. Automated checks cannot verify reading order, announcement quality, focus intent, keyboard efficiency, or cognitive usability.

## Known gaps

- The editor shell is a foundation, not the full 0.3 Navigator/document workflow.
- Resizable/collapsible panels and keyboard shortcuts are not implemented.
- Drag-and-drop and interactive components are not in 0.2 scope.
- Manual assistive-technology evidence is required before Gate 3 can pass.

Gate 3 remains `NOT VERIFIED`.
