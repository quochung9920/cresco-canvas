# Codex Master Implementation Prompt — Cresco Canvas

Copy everything below into Codex while it is connected to this repository.

---

You are the lead architect and implementation agent for **Cresco Canvas**, a native visual website builder for WordPress.

Repository:

```text
quochung9920/cresco-canvas
```

Product name:

```text
Cresco Canvas
```

Tagline:

```text
Build visually. Run natively.
```

Your job is to transform the current proof of concept into a production-grade WordPress visual builder through controlled, testable, backward-compatible milestones.

This is one master instruction for the entire product. Do not attempt to implement every milestone in one unreviewable change. Execute the roadmap in order, one milestone and one pull request at a time. Continue automatically only when the previous milestone meets its acceptance criteria and all available checks pass. If a blocking requirement cannot be completed, document the blocker precisely, preserve a working repository, and stop rather than pretending the work is complete.

## 1. Non-negotiable product principles

1. Keep WordPress as the engine. Do not fork Gutenberg or WordPress Core.
2. Store page content as native block markup in `post_content`.
3. Store templates through native WordPress entities whenever appropriate, including `wp_template`, `wp_template_part`, and `wp_block`.
4. Avoid proprietary page JSON that causes vendor lock-in.
5. Reuse capable Core blocks instead of cloning Heading, Paragraph, Image, Button, List, and similar blocks without a strong technical reason.
6. Build a small set of powerful composable layout blocks rather than hundreds of widgets.
7. React and editor packages must not be loaded on the public frontend.
8. The public frontend must use semantic HTML, scoped CSS, server rendering where appropriate, and the WordPress Interactivity API only for genuinely interactive blocks.
9. No global CSS leakage. Cresco styles must not unexpectedly alter non-Canvas pages, theme admin screens, or third-party blocks.
10. Every destructive or structural operation must be undoable or recoverable.
11. Accessibility, security, performance, migration safety, and backward compatibility are release requirements, not optional cleanup work.
12. Never claim completion based only on successful compilation. Test behavior.
13. Never commit secrets, tokens, credentials, generated local configuration, or user data.
14. Do not automatically merge pull requests into `main` unless explicitly authorized by the repository owner.

## 2. Current repository context to verify before changing code

The current project is an early MVP around version `0.1.1`. Inspect the repository before implementation and verify the actual state rather than assuming these notes are perfectly current.

Known characteristics of the current MVP include:

- A custom full-screen admin editor.
- A monolithic editor JavaScript file using `BlockEditorProvider`.
- Custom REST endpoints for loading and saving pages.
- A basic Global Settings option.
- A custom `cresco/container` block.
- A Page edit-link filter that opens Cresco Canvas directly.
- A secondary route back to the native WordPress editor.
- Basic device preview widths.
- Frontend CSS that is currently too broad and needs isolation.
- No mature build system, CI matrix, autosave, post locking, revision browser, conflict recovery, responsive inheritance engine, template system, dynamic data system, or production release pipeline.

Before modifying code:

1. Read every tracked source file.
2. Inspect repository history and current branches.
3. Identify PHP, JavaScript, CSS, REST, block registration, and editor integration risks.
4. Create or update:
   - `docs/ARCHITECTURE.md`
   - `docs/ROADMAP.md`
   - `docs/DECISIONS.md`
   - `CHANGELOG.md`
5. Record the verified baseline in `docs/BASELINE_AUDIT.md`.
6. Do not delete working behavior until its replacement has tests.

## 3. Target architecture

Build toward this architecture:

```text
WordPress Core
├── Entity data: @wordpress/core-data
├── Block editor: @wordpress/block-editor
├── State and stores: @wordpress/data
├── UI: @wordpress/components
├── Native block markup: post_content
├── Templates: wp_template / wp_template_part
├── Components: Patterns / Synced Patterns
├── Dynamic values: Block Bindings
├── Styles: theme.json concepts, presets, CSS custom properties, public Style Engine APIs
└── Interactions: WordPress Interactivity API

Cresco Canvas
├── Custom visual editor shell
├── Editor state and document workflow
├── Layout blocks
├── Shared inspector controls
├── Responsive inheritance engine
├── Design token system
├── Template and component library
├── Theme Builder and display conditions
├── Dynamic data and query builder
├── Interactive components
├── Compatibility layer
├── Recovery and diagnostics
└── Extension SDK
```

Preferred source organization:

```text
cresco-canvas/
├── cresco-canvas.php
├── composer.json
├── package.json
├── phpcs.xml.dist
├── phpunit.xml.dist
├── playwright.config.*
├── .github/workflows/
├── includes/
│   ├── Admin/
│   ├── API/
│   ├── Blocks/
│   ├── Compatibility/
│   ├── Data/
│   ├── Diagnostics/
│   ├── Migration/
│   ├── Rendering/
│   ├── Security/
│   ├── Styles/
│   ├── Templates/
│   └── Plugin.php
├── src/
│   ├── editor/
│   ├── blocks/
│   ├── components/
│   ├── controls/
│   ├── data/
│   ├── hooks/
│   ├── stores/
│   ├── styles/
│   ├── types/
│   └── utils/
├── build/
├── templates/
├── patterns/
├── tests/
│   ├── php/
│   ├── unit/
│   └── e2e/
└── docs/
```

Adjust this structure when WordPress conventions or repository reality justify a better choice. Document material architecture decisions.

## 4. Required engineering workflow

For every milestone:

1. Create a branch named like:

```text
milestone/0.2-foundation
milestone/0.3-editor-reliability
```

2. Update `docs/ROADMAP.md` with scope and acceptance criteria.
3. Implement in small coherent commits using Conventional Commits where practical:

```text
feat:
fix:
refactor:
test:
docs:
chore:
perf:
security:
```

4. Run all available checks locally.
5. Update `CHANGELOG.md`.
6. Update migration/version metadata.
7. Create a pull request with:
   - problem statement
   - architectural changes
   - user-visible changes
   - migration impact
   - security impact
   - accessibility impact
   - performance impact
   - test evidence
   - known limitations
   - rollback instructions
8. Do not merge automatically.
9. Do not start the next milestone while the repository is red or the current milestone is materially incomplete.

When the GitHub environment permits, create issues or task checklists for each milestone and link them from the pull request. If GitHub issue creation is unavailable, keep the same tracking in `docs/ROADMAP.md`.

## 5. Definition of Done for every feature

A feature is not done unless applicable requirements are satisfied:

- UX behavior is specified.
- Data model is documented.
- Capability checks are present.
- Input is validated and sanitized.
- Output is escaped.
- REST endpoints use correct permissions and schemas.
- Error states are visible and recoverable.
- Keyboard operation works.
- Screen-reader behavior is considered.
- Responsive behavior is defined.
- Unit tests exist.
- PHP tests exist where applicable.
- E2E coverage exists for user-critical behavior.
- Migration and rollback are addressed.
- Performance impact is measured or bounded.
- Documentation and changelog are updated.
- No new console warnings or PHP notices are introduced.

## 6. Milestone 0.2.0 — Architecture and reliability foundation

Do this before adding significant new widgets.

### Build and tooling

- Introduce `@wordpress/scripts` or a clearly justified equivalent.
- Convert editor source to TypeScript.
- Split the monolithic editor into maintainable components and modules.
- Add Composer autoloading.
- Add WordPress Coding Standards through PHPCS.
- Add ESLint, Stylelint, type checking, unit tests, and Playwright.
- Add production and development builds.
- Exclude source maps and development dependencies from release ZIPs unless intentionally needed.
- Add deterministic dependency lock files.

### Continuous integration

Add GitHub Actions that perform, where environment support permits:

- PHP syntax checks.
- PHPCS.
- TypeScript type checks.
- JavaScript lint.
- CSS lint.
- JavaScript unit tests.
- PHP unit tests.
- Production build.
- E2E smoke tests.
- Plugin Check.
- Release ZIP artifact generation.

Use a compatibility matrix covering currently supported WordPress and PHP versions. At execution time, verify current official compatibility rather than hard-coding stale assumptions.

### Plugin lifecycle

Implement:

- Activation checks.
- Minimum version checks.
- Versioned migrations.
- Idempotent migration runner.
- Safe migration failure handling.
- Deactivation behavior that does not delete user content.
- Explicit uninstall policy with opt-in data removal.
- Feature flags for experimental functionality.

### Style isolation

Immediately fix broad frontend styling:

- Do not style the global `body` for every site page.
- Do not globally restyle all `.wp-block-button__link` instances.
- Load Cresco frontend assets only on pages/templates that use Cresco functionality.
- Scope generated variables and rules to a stable Canvas wrapper or body class.
- Preserve theme compatibility.

### Editor entry behavior

Replace unconditional edit-link takeover with a configurable and recoverable system:

- Global setting: Canvas, WordPress Editor, or remember last choice.
- Per-page Canvas-enabled metadata.
- Secondary row actions for both editors.
- Safe query parameter or recovery route to bypass Canvas.
- Never create redirect loops.
- Never block access to native editing if Canvas crashes.

### Acceptance criteria

- A clean clone can install dependencies, build, and create an installable plugin ZIP.
- CI is green.
- Existing MVP pages remain readable.
- Non-Canvas pages are not restyled.
- Native editor fallback always works.
- No data migration loses page content.
- Publish an alpha release or release candidate artifact only after checks pass.

## 7. Milestone 0.3.0 — Reliable document editor

Replace the custom page persistence approach with native WordPress entity workflows wherever possible.

### Data and save workflow

Use `@wordpress/core-data` and WordPress editor data patterns for:

- fetching the current entity
- editing entity records
- dirty-state tracking
- save state
- autosave
- undo and redo
- publish and update
- scheduled publishing
- draft, pending, private, and published states
- slug
- excerpt
- featured image
- parent page
- page template
- discussion settings where relevant

Keep custom REST endpoints only for genuinely custom Cresco domain data.

### Reliability

Implement:

- post locking
- conflict detection
- stale revision warning
- autosave recovery
- save retry
- offline or network-failure messaging
- crash boundary and recovery screen
- unsaved-change navigation warning
- revision browser or safe link to WordPress revisions
- meaningful notices, not silent failures

### Editor shell

Build a professional shell:

Top bar:

```text
Back | Page title | Status | Undo | Redo | Devices | Preview | Save/Publish | More
```

Left panel:

```text
Add | Structure | Templates | Components
```

Right panel:

```text
Content | Layout | Style | Responsive | Advanced
```

Add:

- block search
- inserter categories
- structure/navigator tree
- drag and drop
- breadcrumb
- context menu
- duplicate
- copy/paste
- copy/paste style
- keyboard shortcuts
- command palette
- media selection
- empty-state onboarding
- loading skeletons

### Acceptance criteria

- Refreshing does not lose a valid autosave.
- Undo and redo work for content and supported style operations.
- Save and publish expose clear success/failure states.
- Simultaneous editing produces a conflict warning.
- A full E2E test opens a page, edits content, saves, previews, refreshes, and verifies persistence.
- The native WordPress editor remains accessible.

## 8. Milestone 0.4.0 — Layout and responsive engine

Build a compact, powerful layout foundation.

### Core Cresco layout blocks

Implement or complete:

- Cresco Section
- Cresco Container
- Cresco Grid
- Cresco Stack
- Cresco Spacer only where native spacing controls are insufficient

Prefer extending Core blocks for content blocks.

### Container capabilities

Support:

- semantic HTML tag selection
- block, flex, and grid
- direction
- wrap
- alignment
- justification
- grid columns and rows
- auto-fit and auto-fill
- gap
- width, min-width, max-width
- height, min-height, max-height
- aspect ratio
- overflow
- relative, absolute, sticky positioning with sensible safeguards
- inset controls
- z-index
- background color, gradient, image, overlay
- border and radius
- shadow
- opacity and transform
- visibility and responsive ordering

Avoid unnecessary wrapper elements.

### Shared control system

Create reusable controls and schemas for:

- dimensions
- units
- linked/unlinked sides
- spacing
- colors
- typography
- borders
- shadows
- backgrounds
- layout
- responsive overrides
- visibility
- states such as normal, hover, and focus

Do not duplicate control logic per block.

### Responsive inheritance

Default preview widths:

```text
4K: 1920
Desktop: 1440
Laptop: 1024
Tablet: 768
Mobile: 390
```

Treat preview width separately from CSS breakpoint generation. Store only explicit overrides. Values inherit toward smaller viewports unless overridden.

Example behavior:

```text
Desktop padding: 80px
Laptop: inherited
Tablet: 48px
Mobile: 24px
```

Do not generate a Laptop media query when no Laptop override exists.

Support safe values and units:

```text
px, %, rem, em, vw, vh, min(), max(), clamp(), design tokens
```

Validate arbitrary CSS functions and never permit unsafe injection.

### Styling output

Prefer, in order:

1. Native block supports.
2. Public WordPress Style Engine APIs.
3. CSS custom properties and scoped generated CSS.
4. Minimal inline styles only when technically justified.

Do not depend on private WordPress APIs without an isolated compatibility adapter and documented reason.

### Acceptance criteria

- Editor and frontend rendering are materially consistent.
- Responsive inheritance behaves predictably.
- No unused media queries are generated.
- A representative landing page can be built without excessive wrappers.
- Copy/paste style preserves tokens and responsive overrides.
- Layout E2E tests cover desktop, tablet, and mobile.

## 9. Milestone 0.5.0 — Global design system

Create a token-first design system.

### Token groups

Implement:

- colors
- typography
- spacing
- containers
- breakpoints
- radius
- borders
- shadows
- buttons
- forms
- links
- images
- motion
- z-index

Each token needs:

- stable ID
- label
- group
- value
- optional alias
- validation
- global/local state
- migration behavior

Support token references from block styles. Changing a token must update all linked usages without replacing local overrides.

### Style precedence

Define and document:

```text
WordPress/theme defaults
→ Cresco global tokens
→ block defaults
→ instance overrides
→ responsive/state overrides
```

### Import and export

Add:

- design-system export
- safe import preview
- conflict resolution
- token remapping
- version metadata
- rollback

### Companion theme

Optionally create a separate lightweight block theme named **Cresco Base**, but Cresco Canvas must not require it. The plugin must remain compatible with block themes and classic themes.

### Acceptance criteria

- Global token updates propagate correctly.
- Local overrides remain local.
- Non-Canvas pages are unaffected unless the user intentionally enables global scope.
- Import/export round trips without data loss.
- There is a clear reset and recovery flow.

## 10. Milestone 0.6.0 — Templates and components

### Template library

Add a library for:

- full pages
- sections
- headers
- footers
- heroes
- cards
- CTAs
- pricing
- testimonials
- FAQ
- contact
- blog
- portfolio

Each item needs:

- stable ID
- title
- category
- tags
- thumbnail
- version
- required blocks
- required plugin version
- token dependencies
- preview
- import validation
- migration support

### Components

Use native Patterns, Synced Patterns, pattern overrides, block locking, and content-only editing before inventing a proprietary component format.

Provide:

- Design Mode
- Content Mode
- lock movement
- lock removal
- lock layout controls
- expose only approved content fields
- component updates with instance-safe overrides

### Acceptance criteria

- Updating a synced component updates all intended instances.
- Per-instance content overrides survive component updates.
- Content Mode users cannot accidentally destroy layout.
- Older library items migrate safely.

## 11. Milestone 0.7.0 — Theme Builder

Implement visual editing for:

- Header
- Footer
- Single Post
- Single Page
- Single custom post type
- Archive
- Taxonomy
- Author
- Search Results
- 404
- WooCommerce templates later through a compatibility module

### Display conditions

Support:

- include/exclude
- entire site
- post type
- specific content
- taxonomy
- author
- search
- user role where appropriate
- logged-in state where appropriate
- AND/OR condition groups
- priorities
- conflict resolution
- preview context
- default fallback
- draft/publish lifecycle
- revision and recovery

Store template content in native template entities where appropriate. Store only Cresco-specific condition metadata separately.

### Safety requirements

- A broken condition must not white-screen the website.
- A fallback header/footer must remain available.
- Preview must use a selected context.
- Conflicts must be shown before publishing.
- An emergency disable switch must exist.

### Acceptance criteria

- Conditions resolve deterministically.
- Conflict tests pass.
- Templates can be previewed against real content contexts.
- Disabling Theme Builder restores theme fallback behavior.

## 12. Milestone 0.8.0 — Dynamic data and query builder

### Dynamic sources

Support through Block Bindings or an appropriate native-compatible abstraction:

- post title
- post content
- excerpt
- featured image
- author
- date
- taxonomy terms
- post meta
- ACF fields when ACF is active
- site options
- user fields with capability protection
- URL parameters with strict validation
- WooCommerce fields when WooCommerce is active
- registered custom callbacks

Dynamic values must render consistently in editor preview and frontend.

### Query Builder

Support:

- post type
- status
- taxonomy query
- meta query
- search
- author
- date
- ordering
- pagination
- offset
- AND/OR relationships
- current context
- related posts
- manual selection

Add hard limits, query validation, caching, invalidation, and protection against expensive or malicious queries.

### Loop Builder

Provide a visual loop-item template using native blocks and dynamic bindings.

### Conditional visibility

Support conditions such as:

- field empty/not empty
- field equality and safe comparisons
- logged-in state
- role/capability
- taxonomy membership
- WooCommerce stock state
- URL parameter existence
- current content context

Security-sensitive conditions must be enforced server-side. CSS-only hiding is not sufficient.

### Acceptance criteria

- No unbounded query can be created from the UI.
- Cache invalidation is tested.
- Pagination works.
- Dynamic preview matches frontend output.
- Permission-protected data is not exposed through REST or markup.

## 13. Milestone 0.9.0 — Interactive components

Build accessible native components using the WordPress Interactivity API where practical:

- Accordion
- Tabs
- Modal
- Off-canvas panel
- Dropdown
- Tooltip
- Mobile navigation
- Slider only if accessibility requirements are met
- Load more
- Live filters

Requirements:

- semantic server-rendered HTML
- progressive enhancement
- correct ARIA
- keyboard navigation
- focus management
- Escape behavior
- reduced-motion support
- no hover-only controls
- no large generic JavaScript bundle
- load component assets only when the component is present

Before version 1.0, prioritize integrations with established form plugins over building a full form platform.

### Acceptance criteria

- Each component is keyboard usable.
- Focus behavior is E2E tested.
- Without JavaScript, essential content remains available where possible.
- Asset loading is conditional.

## 14. Milestone 1.0.0 — Production release and hardening

Use staged releases:

```text
1.0.0-alpha
1.0.0-beta.1
1.0.0-beta.2
1.0.0-rc.1
1.0.0
```

### Onboarding

Implement:

1. Welcome screen.
2. Website type selection.
3. Design system starter selection.
4. Editor-default choice.
5. Starter page/template selection.
6. First-page creation.
7. Optional guided tour.

### Recovery and diagnostics

Add:

- Safe Mode
- revision browser or native revision integration
- restore snapshot
- reset broken style
- regenerate generated CSS/assets
- clear internal caches
- compatibility report
- debug report export with sensitive data redaction
- emergency disable for Theme Builder and generated styles

### Documentation

Complete:

- Getting Started
- Editor Guide
- Responsive Guide
- Design System
- Templates and Components
- Theme Builder
- Dynamic Data
- Developer API
- Troubleshooting
- Migration Guide
- Accessibility Guide
- Security model

### Release quality

- Run Plugin Check.
- Perform accessibility review against WCAG 2.2 AA goals.
- Run compatibility matrix.
- Produce signed or checksum-documented release artifacts when practical.
- Provide upgrade and rollback notes.
- Do not release 1.0 while critical or high-severity known defects remain.

## 15. Post-1.0 roadmap

Implement only after the 1.0 foundation is stable.

### 1.1 — WooCommerce Builder

- Product templates
- Product archives
- Cart presentation
- Checkout presentation without replacing WooCommerce transaction logic
- My Account presentation
- Dynamic price, stock, variation, and add-to-cart integrations
- Product filtering through WooCommerce-compatible APIs

### 1.2 — Native Form Builder

Only after a security and deliverability design review:

- accessible fields
- server validation
- conditional logic
- multi-step forms
- file upload security
- email actions
- webhook allowlists and SSRF protection
- entry storage
- spam protection
- privacy and retention controls
- GDPR tools

### 1.3 — AI Assistant

AI must operate on validated structures:

- block trees
- block attributes
- patterns
- templates
- design tokens
- queries
- display conditions

AI must not directly inject arbitrary unsafe HTML, PHP, scripts, or unvalidated CSS.

Required flow:

```text
User command
→ proposed structured changes
→ visual/textual diff
→ validation
→ user acceptance
→ one undoable transaction
```

### 1.4 — Collaboration

- block comments
- review assignments
- approval workflow
- content staging
- design staging
- visual revision comparison
- scheduled changes
- activity log

### 2.0 — Extension ecosystem

Only after APIs stabilize:

- documented SDK
- custom block/control registration
- dynamic source registration
- display-condition registration
- template provider registration
- compatibility contracts
- semantic versioning guarantees
- extension test harness
- CLI utilities
- curated marketplace model

## 16. Performance budgets

Treat these as internal targets, not universal guarantees.

### Editor

- Typical editor becomes usable within 2.5 seconds on the defined reference environment.
- Typing response p95 below 50 ms.
- Block selection response p95 below 100 ms.
- Typical save below 1.5 seconds excluding slow hosting/network conditions.
- A page with 500 representative blocks remains operable.
- No release introduces more than 5% unexplained regression in tracked editor metrics.

### Frontend

- Zero Cresco editor React runtime on frontend.
- Zero Cresco JavaScript on a non-interactive Canvas page where possible.
- Typical Canvas CSS below 40 KB gzip.
- Each interactive component should add less than 12 KB gzip when practical.
- Avoid layout shifts; target CLS below 0.1 on reference pages.
- Load only assets used by the current page.
- Avoid duplicate CSS declarations and unnecessary wrappers.

Create repeatable benchmark fixtures and document the reference environment.

## 17. Accessibility requirements

Target WCAG 2.2 AA.

Test:

- keyboard-only usage
- Tab and Shift+Tab order
- Enter and Space activation
- arrow-key behavior in composite widgets
- Escape handling
- focus visibility
- focus restoration
- screen-reader labels
- error announcements
- color contrast
- reduced motion
- 200% zoom
- RTL
- Chromium, Firefox, and WebKit where supported
- representative NVDA and VoiceOver checks when the environment allows

Do not hide essential functionality behind hover.

## 18. Security requirements

For every endpoint, action, import, dynamic source, and upload:

- authenticate
- check capabilities
- protect against CSRF
- validate schema
- sanitize input
- escape output
- use prepared queries
- prevent stored and reflected XSS
- prevent SSRF
- prevent path traversal
- validate uploads
- prevent privilege escalation
- prevent REST data exposure
- constrain expensive queries
- redact sensitive diagnostics
- add audit logging for high-impact administrative changes where appropriate

Never use nonce checks as a replacement for capability checks.

Perform threat modeling for:

- template import
- remote media
- dynamic URL sources
- webhooks
- custom CSS
- custom HTML
- role-based editing
- Theme Builder conditions
- AI-generated changes

## 19. Compatibility matrix

At execution time, verify current supported versions from official sources and configure CI for a practical matrix including:

- current stable WordPress
- previous supported WordPress line
- upcoming beta/RC or nightly as an allowed experimental job
- supported PHP versions
- block theme
- classic theme
- multisite
- RTL
- WooCommerce active/inactive
- ACF active/inactive
- Gutenberg plugin active/inactive when relevant
- Chromium
- Firefox
- WebKit

A failure in an experimental future-version job may be non-blocking only if clearly documented. Stable-version failures are blocking.

## 20. Testing strategy

### PHP tests

Cover:

- activation and migrations
- capability checks
- REST permissions
- sanitization
- generated CSS scoping
- template condition resolution
- dynamic data permission boundaries
- query limits
- uninstall behavior

### JavaScript unit tests

Cover:

- responsive inheritance
- token resolution
- reducer/store behavior
- serialization helpers
- style copy/paste
- condition builders
- validation

### E2E tests

At minimum:

- activate plugin
- choose editor preference
- open a page through Edit
- add blocks
- modify content
- change layout
- switch devices
- save
- preview
- refresh
- verify persistence
- use undo/redo
- recover autosave
- open native editor fallback
- verify non-Canvas page isolation
- test keyboard navigation

Maintain a small set of visual regression fixtures for editor/frontend parity.

## 21. UX rules

1. Favor clarity over maximum control density.
2. Use progressive disclosure.
3. Keep common actions visible and advanced actions discoverable.
4. Do not expose technical labels where a user-friendly label is accurate.
5. Preserve native WordPress mental models where doing so reduces confusion.
6. A user should be able to create a clean responsive page without understanding CSS.
7. A developer should be able to inspect predictable markup and extend the system.
8. Error messages must explain what happened and what the user can do next.
9. Never silently discard invalid values; explain validation and preserve recoverable input when safe.
10. Content Mode must protect layout from non-design users.

## 22. Internationalization

- All user-facing strings must be translatable.
- Use the `cresco-canvas` text domain.
- Avoid concatenated translatable fragments.
- Support RTL layouts.
- Do not hard-code English UI inside source without translation functions.
- Keep developer documentation in English unless otherwise requested; user documentation may include Vietnamese later.

## 23. Coding constraints

- Follow WordPress PHP coding standards.
- Use strict, maintainable PHP compatible with the declared minimum version.
- Use TypeScript for new editor code.
- Avoid `any` unless isolated and justified.
- Avoid global JavaScript variables except the minimal bootstrap configuration needed by WordPress.
- Prefer dependency injection or explicit service registration over hidden singletons, while preserving practical WordPress boot conventions.
- Avoid direct database queries when native APIs are sufficient.
- Avoid private Gutenberg APIs unless no public alternative exists; isolate and test compatibility when unavoidable.
- Do not suppress errors globally.
- Do not use `!important` as a default styling strategy.
- Do not duplicate WordPress Core functionality without documented value.

## 24. Migration and backward compatibility

- Existing `cresco/container` content must remain valid or migrate automatically.
- Never rewrite all post content merely because the plugin version changes.
- Block deprecations and migrations must preserve content.
- Options must use versioned schemas.
- Generated CSS must be regenerable from source data.
- Migrations must be idempotent.
- Before destructive migration, create a backup or recovery reference when technically possible.
- Document downgrade limitations.

## 25. Reporting format after every milestone

At the end of each milestone, provide a concise implementation report containing:

```text
Milestone:
Branch:
Pull request:
Version:
Summary:
Files changed:
Database/data migrations:
Tests run:
Test results:
Performance before/after:
Accessibility checks:
Security checks:
Known limitations:
Rollback instructions:
Next milestone:
```

Do not use vague statements such as “all done” or “fully optimized.” Provide evidence.

## 26. Immediate execution order

Start now with this order:

1. Perform the baseline audit.
2. Create or update architecture, roadmap, decision, and changelog documents.
3. Implement Milestone `0.2.0` on a dedicated branch.
4. Build CI and release ZIP generation.
5. Fix CSS isolation and editor fallback safety before expanding features.
6. Run tests and create a pull request.
7. Stop for review if any migration, compatibility, security, or data-loss uncertainty remains.
8. After approval or when explicitly instructed to continue, proceed to `0.3.0` and repeat the workflow.

Do not begin with AI, WooCommerce, popups, animations, a marketplace, or a large widget catalog. The priority order is:

```text
Reliability
→ testing and build system
→ editor UX
→ layout/responsive engine
→ global design system
→ templates/components
→ Theme Builder
→ dynamic data/query
→ interactions/integrations
→ AI/ecosystem
```

Your first concrete deliverable is a reviewable pull request for **`0.2.0 — Architecture and Reliability Foundation`**, not an unbounded rewrite of the entire product.

---

End of master prompt.
