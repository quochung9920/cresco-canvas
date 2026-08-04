# Accessibility Audit

Target: WCAG 2.2 AA for critical editor workflows and generated interactive components.

## Milestone 0.3 review

| Area | Evidence | Status |
| --- | --- | --- |
| Standard editor semantics | Cresco extends Gutenberg with public `PluginSidebar`, `PanelBody`, `Notice`, `ToggleControl`, `TextControl`, `Button`, and `Spinner` components | PASS (code review) |
| Form labels | WordPress controls provide labels; native color inputs use explicit `for`/`id` pairs | PASS (code review) |
| Keyboard focus | Native controls plus visible `:focus-visible` treatment for color inputs | PASS (code review) |
| Loading and errors | Spinner has an accessible label; REST failures render dismissible WordPress notices and retry action | PASS (code review) |
| Document recovery | Core Gutenberg autosave/revision/lock/recovery UI remains the only workflow; missing Cresco assets never replace the editor | PASS (architecture review) |
| RTL build | Generated RTL CSS exists and the WordPress handle is marked `rtl: replace` | PASS (code/build review) |
| Automated serious/critical issues | Playwright runs axe against the Cresco sidebar in Chromium, Firefox, and WebKit | NOT TESTED locally; CI configured |
| Full keyboard workflow | Open sidebar, configure Page/global settings, add/edit/save/revise blocks | NOT TESTED manually |
| Screen reader | NVDA/JAWS/VoiceOver names, status announcements, and reading order | NOT TESTED manually |
| Browser zoom/reflow | 200% and 400% zoom | NOT TESTED manually |
| High contrast/forced colors | No dedicated validation | NOT TESTED |
| Mobile/touch editor | Core responsive editor exists; interaction validation not performed | NOT TESTED |

The E2E assertion blocks serious or critical axe findings within the Cresco sidebar. Automated checks cannot establish announcement quality, focus intent, keyboard efficiency, touch usability, or cognitive accessibility.

## Known gaps

- Native Gutenberg substantially reduces custom accessibility surface, but it does not waive testing of Cresco controls.
- Manual assistive-technology evidence is required before Gate 3 can pass.
- Interactive Cresco frontend components are not implemented yet; their keyboard, focus, ARIA, reduced-motion, and server-fallback requirements remain future scope.

Gate 3 remains `NOT VERIFIED`.
