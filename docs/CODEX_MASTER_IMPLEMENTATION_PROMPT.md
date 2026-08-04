# Cresco Canvas — Single Codex Completion Prompt

Use this file as the **only authoritative prompt** for Codex.

Copy everything below into Codex while it is connected to this repository. Reuse the exact same prompt after every merged pull request. It is intentionally idempotent: on every run, Codex must inspect the real repository state, verify what is complete, repair incomplete work, and continue from the next missing requirement.

---

You are the lead product architect, principal WordPress and Gutenberg engineer, React/TypeScript engineer, PHP engineer, UI/UX designer, QA lead, security reviewer, accessibility specialist, performance engineer, release manager, and commercial-readiness gatekeeper for **Cresco Canvas**.

Repository:

```text
quochung9920/cresco-canvas
```

Product:

```text
Cresco Canvas
Build visually. Run natively.
```

## Mission

Transform the current repository into an excellent, production-grade, commercially distributable native WordPress visual website builder.

This is the single authoritative instruction for the entire product lifecycle. Do not request another specification unless a genuinely business-critical decision cannot be inferred safely from this prompt or from established WordPress conventions.

For every capability in this prompt:

- If it does not exist, implement it.
- If it exists only as a prototype, complete it.
- If it is broken, fix it.
- If it is insecure, redesign and secure it.
- If it is duplicated, consolidate it without losing data.
- If it is obsolete, remove it only through a tested migration and rollback path.
- If WordPress Core already provides the capability well, integrate or extend the Core capability instead of creating an unnecessary proprietary replacement.

Do not merely write plans or placeholder interfaces. Implement working behavior, tests, migrations, documentation, and release evidence.

Do not interpret words such as “100%”, “perfect”, “best”, or “commercial-ready” as permission to make unsupported claims. Commercial readiness is allowed only when every mandatory release gate defined below is verified as `PASS` with reproducible evidence.

If something cannot be verified, mark it `NOT VERIFIED`. If it fails, mark it `FAIL`. Never convert `NOT TESTED` into `PASS`.

## How to execute this one prompt

This prompt covers the complete product, but the work must remain reviewable.

1. Audit the current repository.
2. Determine the latest genuinely completed milestone from code and evidence, not from version labels.
3. Repair the current milestone if it is incomplete.
4. Select the next incomplete milestone.
5. Implement one coherent milestone or release-hardening scope on a dedicated branch.
6. Test it comprehensively.
7. Open one pull request.
8. Do not merge automatically.
9. Stop and report evidence.
10. After human review and merge, run this exact same prompt again. It must re-audit the merged result and continue automatically from the real state.

No second prompt is required at any stage.

# 1. Non-negotiable product principles

1. Keep WordPress and Gutenberg as the engine.
2. Do not fork WordPress Core or Gutenberg.
3. Store Page content as native WordPress block markup in `post_content`.
4. Use native entities where appropriate, including:
   - `wp_template`
   - `wp_template_part`
   - `wp_block`
   - Patterns
   - Synced Patterns
   - Pattern Overrides
   - Block Bindings
5. Avoid proprietary page formats that create unnecessary vendor lock-in.
6. When the plugin is deactivated, user content must remain readable and must not disappear.
7. Reuse capable Core blocks instead of cloning basic content blocks without a strong documented reason.
8. Provide complete user-facing capabilities even when they are implemented through Core blocks, patterns, variations, extensions, or compositions rather than custom widgets.
9. Build a small set of powerful composable layout blocks rather than hundreds of redundant blocks.
10. React and editor packages must not load on the public frontend.
11. Public output must prioritize semantic HTML, scoped CSS, minimal wrappers, server rendering where appropriate, and the WordPress Interactivity API for interactive behavior.
12. Cresco Canvas CSS and JavaScript must not unexpectedly affect non-Canvas Pages, unrelated admin screens, themes, plugins, or third-party blocks.
13. Every destructive or structural operation must be undoable, recoverable, or protected by a clear confirmation.
14. Security, accessibility, performance, data safety, migration safety, recovery, and backward compatibility are release requirements.
15. Do not commit credentials, API keys, tokens, secrets, private user data, local environment files, or unnecessary build artifacts.
16. Never merge automatically into `main`.
17. Never claim completion because code compiles. Test real behavior.
18. Do not weaken, delete, skip, broadly mock, or suppress tests merely to obtain green CI.
19. Do not silently change existing content or site output during an upgrade.
20. Preserve unknown blocks and third-party blocks.

# 2. Mandatory first actions on every run

Before changing code:

1. Inspect all tracked source files relevant to the product.
2. Inspect Git history, recent commits, current branches, open pull requests, tags, and CI status.
3. Read:
   - `README.md`
   - `CHANGELOG.md`
   - all files under `docs/`
   - package and Composer manifests
   - build configuration
   - CI workflows
   - release scripts
   - migration code
   - tests
4. Verify the actual plugin version and current functionality.
5. Install dependencies from a clean checkout.
6. Run all existing checks before editing.
7. Reproduce known defects before fixing them.
8. Create or update:
   - `docs/BASELINE_AUDIT.md`
   - `docs/ARCHITECTURE.md`
   - `docs/ROADMAP.md`
   - `docs/DECISIONS.md`
   - `docs/COMMERCIAL_READINESS.md`
   - `docs/SECURITY_THREAT_MODEL.md`
   - `docs/ACCESSIBILITY_AUDIT.md`
   - `docs/PERFORMANCE_BASELINE.md`
   - `docs/COMPATIBILITY_MATRIX.md`
   - `docs/RELEASE_CHECKLIST.md`
   - `docs/KNOWN_LIMITATIONS.md`
   - `CHANGELOG.md`
9. Build a feature-completeness matrix with every capability in this prompt marked:
   - `COMPLETE`
   - `PARTIAL`
   - `MISSING`
   - `BROKEN`
   - `NOT APPLICABLE`
10. Do not remove working behavior until the replacement exists and has tests.

# 3. Target architecture

Build toward:

```text
WordPress Core
├── @wordpress/block-editor
├── @wordpress/core-data
├── @wordpress/data
├── @wordpress/components
├── @wordpress/notices
├── native block markup in post_content
├── native autosaves and revisions
├── wp_template and wp_template_part
├── Patterns, Synced Patterns, Pattern Overrides
├── Block Bindings
├── public Style Engine APIs
└── Interactivity API

Cresco Canvas
├── custom visual editor shell
├── document workflow and recovery
├── layout engine
├── shared controls
├── responsive inheritance engine
├── global design system
├── templates and components
├── live and external preview systems
├── Theme Builder and display conditions
├── dynamic data and Query Builder
├── interactive components
├── integrations
├── diagnostics and Safe Mode
├── compatibility layer
└── extension SDK
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

Adjust this only when WordPress conventions or repository reality justify a better structure. Document material decisions.

# 4. Complete user experience

## 4.1 WordPress integration

The normal WordPress Page title and `Edit` action must open the correct Page directly in Cresco Canvas when Canvas is selected as the editor.

Provide:

- Global default editor setting:
  - Cresco Canvas
  - WordPress Editor
  - Remember last choice
- Per-Page Canvas enablement.
- Row actions for both Cresco Canvas and WordPress Editor.
- Safe bypass URL to open the native editor when Canvas fails.
- No redirect loops.
- Support for Pages first, then configured public post types.
- Correct capabilities and permissions.

## 4.2 Editor shell

Build a professional full-screen builder.

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
Content | Layout | Style | Responsive | Effects | Advanced
```

Required editor behavior:

- Element search.
- Categorized inserter.
- Drag and drop.
- Structure/Navigator tree.
- Block breadcrumbs.
- Inline text editing.
- Context menu.
- Duplicate.
- Delete.
- Copy and paste.
- Copy and paste styles.
- Multi-selection.
- Block locking.
- Keyboard shortcuts.
- Command palette.
- Media library integration.
- Loading skeletons.
- Empty-state onboarding.
- Clear errors and notices.
- Resizable/collapsible panels.
- Beginner-friendly defaults.
- Progressive disclosure for advanced controls.

## 4.3 Document reliability

Use `@wordpress/core-data` and native WordPress entity workflows wherever appropriate.

Implement and verify:

- Fetching and editing the current entity.
- Dirty state.
- Save and update.
- Publish.
- Draft, pending, private, scheduled, and published states.
- Autosave.
- Revisions.
- Undo and redo.
- Post locking.
- Concurrent edit conflict detection.
- Stale revision warning.
- Save retry.
- Offline and timeout states.
- Crash boundary.
- Recovery screen.
- Unsaved-change navigation warning.
- Slug.
- Excerpt.
- Featured image.
- Parent Page.
- Page template.
- Discussion settings where applicable.
- Recovery without data loss.

Custom REST endpoints may remain only for genuinely custom Cresco domain data and must have schemas, permission callbacks, validation, sanitization, and tests.

# 5. Widgets, blocks, and capability library

Provide all of the following user-facing capabilities. Reuse and extend Core blocks when practical; create custom Cresco blocks only where Core cannot meet the required UX, responsive behavior, dynamic data, or frontend output.

## 5.1 Layout capabilities

- Section.
- Container.
- Grid.
- Stack.
- Columns.
- Spacer.
- Divider.
- Full width and boxed layout.
- Nested layouts.
- Semantic HTML tags.

Layout controls:

- Block, Flexbox, and CSS Grid.
- Direction and wrap.
- Grid columns and rows.
- Auto-fit and auto-fill.
- Alignment and justification.
- Gap.
- Width, min-width, max-width.
- Height, min-height, max-height.
- Aspect ratio.
- Overflow.
- Position: static, relative, absolute, sticky with safeguards.
- Insets.
- Z-index.
- Responsive ordering.
- Visibility.

## 5.2 Basic content capabilities

- Heading.
- Paragraph/Text.
- Button and button group.
- Icon.
- Image.
- Gallery.
- Video.
- Audio.
- List.
- Quote.
- Pullquote.
- Code and preformatted content with safe handling.
- Table.
- File/download.
- Embed.
- Shortcode compatibility where safe.
- HTML block with appropriate capability restrictions and sanitization.

## 5.3 Common design and marketing capabilities

Provide equivalent capabilities through blocks, patterns, variations, or compositions:

- Icon Box.
- Image Box.
- Call to Action.
- Testimonial.
- Pricing table.
- Counter.
- Progress bar.
- Star rating.
- Social icons.
- Team member card.
- Feature list.
- FAQ.
- Timeline.
- Logo cloud.
- Before/after comparison when implemented accessibly.
- Table of contents.
- Breadcrumbs.

Avoid creating redundant custom blocks when a pattern or variation provides a cleaner native solution.

## 5.4 Interactive capabilities

Implement with the WordPress Interactivity API where appropriate:

- Accordion.
- Tabs.
- Modal.
- Off-canvas panel.
- Dropdown.
- Tooltip.
- Mobile menu.
- Slider/Carousel.
- Load More.
- Live filters.
- Dismissible notice.
- Accessible disclosure components.

Every interactive capability must support keyboard operation, focus management, reduced motion, correct ARIA, and server-rendered fallback content.

## 5.5 Navigation and site capabilities

- Site Logo.
- Site Title.
- Navigation/Menu.
- Search.
- Breadcrumbs.
- Login/logout links.
- Post navigation.
- Pagination.
- Archive title.
- Query results.

## 5.6 Dynamic capabilities

- Post title.
- Post content.
- Post excerpt.
- Featured image.
- Author.
- Date.
- Terms.
- Post meta.
- ACF fields.
- Site options.
- User fields.
- URL parameters with validation.
- Current context.
- Related content.
- WooCommerce data when WooCommerce support is in scope.
- Extensible custom source registry.

Prefer Block Bindings.

## 5.7 Forms

Before creating a large native form system, provide excellent integrations with established form plugins such as Gravity Forms, Fluent Forms, Contact Form 7, and other integrations supported by the project.

A later native Form Builder may include:

- Accessible fields.
- Validation.
- Conditional logic.
- Multi-step forms.
- File upload security.
- Email actions.
- Webhooks.
- Entry storage.
- Spam protection.
- GDPR controls.

Do not mark native Forms complete until delivery, validation, security, privacy, and failure handling are tested.

## 5.8 WooCommerce

When WooCommerce is included in the supported commercial scope, provide:

- Product title.
- Product images/gallery.
- Price.
- Rating.
- Stock.
- Variations.
- Add to Cart.
- Product meta.
- Related products.
- Upsells.
- Product Loop.
- Product archive filters.
- Cart, Checkout, and My Account template support only through stable WooCommerce APIs.

Do not duplicate or bypass WooCommerce business logic.

# 6. Responsive system

Required preview modes:

```text
4K
Desktop
Laptop
Tablet
Mobile
```

Default preview widths:

```text
4K: 1920
Desktop: 1440
Laptop: 1024
Tablet: 768
Mobile: 390
```

Preview widths and CSS breakpoints are related but not identical. Implement a documented breakpoint system.

Responsive inheritance:

- Desktop is the base value.
- Smaller devices inherit until an explicit override exists.
- Store only explicit overrides.
- Do not generate unused media queries.
- Allow reset to inherited value.
- Make inheritance visually clear in controls.

Support validated values and units:

```text
px, %, rem, em, vw, vh, auto, min(), max(), clamp(), design tokens
```

Prevent CSS injection through arbitrary values.

Responsive controls must work for layout, spacing, typography, alignment, order, sizing, visibility, backgrounds, borders, and other applicable properties.

# 7. Preview system

Provide three distinct preview experiences.

## 7.1 Edit Canvas

- Fast interactive editor canvas.
- Device-width simulation.
- Accurate block styling.
- Clear selection outlines and editing controls.
- No editor chrome in saved frontend markup.

## 7.2 Live Frontend Preview

Implement a secure iframe or equivalent frontend-rendered preview that can display:

- Active theme CSS.
- Header and footer.
- Global Styles.
- Dynamic Data.
- Queries.
- Interactive components.
- Theme Builder templates.
- Correct preview context.
- Desktop, laptop, tablet, mobile, and 4K widths.

Synchronize safe editor changes when practical. Clearly indicate when saving or refreshing is required.

## 7.3 External Preview

- Open the real WordPress preview in a new tab.
- Preserve draft preview nonces and permissions.
- Never expose private drafts to unauthorized users.

Acceptance requirement: representative Pages must render materially consistently between Edit Canvas, Live Preview, and public frontend.

# 8. Shared control system and styling

Create shared, reusable controls and schemas for:

- Units.
- Linked and unlinked dimensions.
- Margin and padding.
- Width and height.
- Typography.
- Colors.
- Backgrounds.
- Gradients.
- Images and overlays.
- Borders.
- Radius.
- Shadows.
- Layout.
- Responsive overrides.
- Visibility.
- Normal, hover, focus, active, and disabled states where applicable.
- Transitions and motion.
- Transform.
- Custom CSS with strict scoping and permissions.

Do not duplicate control logic in every block.

Prefer styling in this order:

1. Native block supports.
2. Public WordPress Style Engine APIs.
3. CSS custom properties and scoped generated CSS.
4. Minimal inline styles only when justified.

Avoid private WordPress APIs unless isolated behind a compatibility adapter and documented.

# 9. Global Design System

Implement token groups:

- Colors.
- Typography.
- Spacing.
- Containers.
- Breakpoints.
- Radius.
- Borders.
- Shadows.
- Buttons.
- Forms.
- Links.
- Images.
- Motion.
- Z-index.

Every token needs:

- Stable ID.
- Label.
- Group.
- Validated value.
- Optional alias.
- Global/local state.
- Migration support.
- Usage tracking.

Style precedence must be documented and predictable:

```text
WordPress/theme defaults
→ Cresco global tokens
→ block defaults
→ instance overrides
→ responsive and state overrides
```

Provide:

- Import/export.
- Safe import preview.
- Conflict resolution.
- Token remapping.
- Reset and recovery.
- Version metadata.
- Backward-compatible migrations.

Do not style global `body`, all buttons, or unrelated Pages unintentionally. Scope assets and variables to Canvas usage.

A separate lightweight companion block theme named **Cresco Base** may be created, but the plugin must not require it.

# 10. Templates and components

Template library categories:

- Full Pages.
- Sections.
- Headers.
- Footers.
- Heroes.
- Cards.
- CTAs.
- Pricing.
- Testimonials.
- FAQ.
- Contact.
- Blog.
- Portfolio.
- WooCommerce templates when supported.

Every template needs:

- Stable ID.
- Title.
- Category.
- Tags.
- Thumbnail.
- Version.
- Required blocks.
- Required plugin version.
- Token dependencies.
- Preview.
- Import validation.
- Conflict handling.
- Migration support.

Components must prioritize:

- Patterns.
- Synced Patterns.
- Pattern Overrides.
- Block Locking.
- Content-only editing.

Required editing modes:

### Design Mode

- Full layout, responsive, structure, and style control.

### Content Mode

- Text, images, video, and links.
- No destructive structure or layout control.

Changing a synced component must update instances without losing valid per-instance content overrides.

# 11. Theme Builder

Support:

- Header.
- Footer.
- Single Post.
- Single Page.
- Custom Post Type.
- Archive.
- Taxonomy.
- Author.
- Search Results.
- 404.
- WooCommerce templates when supported.

Store templates through native entities where appropriate.

Display Conditions must support:

- Entire Site.
- Post Type.
- Specific content.
- Taxonomy.
- Author.
- Search.
- User role.
- Logged-in state.
- WooCommerce conditions.
- Include and exclude.
- AND and OR groups.
- Priority.
- Conflict detection.
- Fallback templates.
- Preview context.
- Emergency disable.

Never allow a malformed condition to produce an unrecoverable blank site. Always provide a safe fallback.

# 12. Dynamic Data and Query Builder

Implement a visual Query Builder supporting:

- Post type.
- Status.
- Taxonomy query.
- Meta query.
- Search.
- Author.
- Date.
- Ordering.
- Pagination.
- Offset.
- AND and OR relations.
- Current context.
- Related content.
- Manual selection.
- Caching and invalidation.
- Query limits.
- Protection against expensive queries.

Provide a Loop Builder using native query concepts where practical.

Conditional Visibility may include:

- Dynamic field values.
- Authentication state.
- User role.
- Taxonomy.
- Stock state.
- URL parameter.
- Device.
- Date/time.
- Custom registered conditions.

Security-sensitive visibility must be enforced server-side, not only hidden with CSS or JavaScript.

# 13. Build system, engineering quality, and repository workflow

Implement or complete:

- TypeScript source.
- Modular React architecture.
- `@wordpress/scripts` or a justified equivalent.
- Composer autoloading.
- PHPCS with WordPress Coding Standards.
- ESLint.
- Stylelint.
- Type checking.
- PHP unit/integration tests.
- JavaScript unit tests.
- Playwright E2E.
- GitHub Actions.
- Deterministic lock files.
- Production and development builds.
- Reproducible release ZIP.
- Plugin Check.
- Versioned migrations.
- Idempotent migration runner.
- Feature flags.
- Activation checks.
- Minimum version checks.
- Safe deactivation.
- Explicit uninstall policy.

Branch examples:

```text
milestone/0.2-foundation
milestone/0.3-editor-reliability
feature/live-preview
fix/save-conflict
release/1.0.0-rc1
```

Use small coherent commits and Conventional Commits where practical:

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

# 14. Required milestone order

Do not skip foundations. Determine the current actual state and continue in this order:

## 0.2 — Architecture and reliability foundation

- Build system.
- TypeScript and modular editor.
- Composer.
- CI.
- Tests.
- Migration framework.
- Feature flags.
- Activation/deactivation/uninstall safety.
- Style isolation.
- Configurable editor entry and Safe Mode.

## 0.3 — Reliable editor workflow

- Native entity data.
- Save/publish.
- Autosave.
- Revisions.
- Undo/redo.
- Locking and conflicts.
- Crash recovery.
- Full editor shell and Navigator.

## 0.4 — Layout and responsive engine

- Section, Container, Grid, Stack.
- Shared controls.
- Complete responsive inheritance.
- Efficient CSS output.

## 0.5 — Global Design System

- Complete token system.
- Import/export.
- Theme compatibility.

## 0.6 — Templates and components

- Template library.
- Synced components.
- Design Mode and Content Mode.

## 0.7 — Theme Builder

- Templates and Display Conditions.

## 0.8 — Dynamic Data and Query Builder

- Block Bindings.
- ACF.
- Queries.
- Loops.
- Conditional Visibility.

## 0.9 — Interactive components and integrations

- Interactivity API components.
- Form integrations.
- Accessibility hardening.

## 1.0 — Commercial production release

- Onboarding.
- Interactive tour.
- Starter designs.
- Diagnostics.
- Safe Mode.
- Revision browser.
- Restore snapshot.
- Reset/regenerate styles and assets.
- Compatibility report.
- Privacy-safe debug export.
- Complete documentation.
- Translations.
- Release ZIP.
- Beta and RC process.
- Real staging validation.
- Upgrade and rollback guides.
- No placeholder production UI.

Post-1.0, after 1.0 is stable:

- WooCommerce Builder.
- Native Form Builder.
- AI Assistant.
- Collaboration.
- Extension SDK.
- Marketplace.

AI must operate on blocks, attributes, tokens, patterns, templates, queries, and conditions. AI changes require a visual preview, explicit user acceptance, one-step undo, no automatic publishing, and no unsafe arbitrary scripts.

# 15. Independent audit requirements

Audit the plugin as an external commercial product before deploying it to customer websites.

## Product and UX

Test:

- First activation and onboarding.
- Opening a Page from normal WordPress Edit.
- Adding, moving, nesting, duplicating, deleting, and restoring blocks.
- Inline content editing.
- Media selection.
- Save, publish, autosave, revisions, undo, redo.
- Locking and concurrent editing.
- Crash recovery.
- Device previews.
- Live Preview.
- External Preview.
- Global Settings.
- Templates.
- Components.
- Design Mode.
- Content Mode.
- Empty, loading, offline, permission-denied, timeout, invalid-content, and server-error states.
- Deactivation and uninstall.

## Data integrity

Verify:

- Native markup remains in `post_content`.
- Unknown and third-party blocks survive.
- Failed saves do not overwrite newer content silently.
- Concurrent edits cannot silently corrupt content.
- Migrations are versioned and idempotent.
- Realistic legacy fixtures exist.
- Rollback is documented and tested.
- Deactivation preserves content.
- Uninstall removes only data the user explicitly approved for removal.

## Security

Review every trust boundary:

- REST endpoints.
- Admin actions.
- AJAX actions.
- Uploads.
- Template imports.
- Dynamic sources.
- Query inputs.
- URL parameters.
- Custom CSS and HTML.
- External requests.
- Webhooks.
- AI changes.
- Extension hooks.
- Licensing and update mechanisms if added.

Check for:

- Missing authentication.
- Missing capabilities.
- Missing nonce or CSRF protection.
- Stored, reflected, and DOM XSS.
- SQL injection.
- SSRF.
- Path traversal.
- Unsafe deserialization.
- Arbitrary upload or execution.
- Privilege escalation.
- IDOR.
- Information disclosure.
- Open redirects.
- Secret leakage.
- Vulnerable dependencies.
- Supply-chain risk.

Safely reproduce realistic findings, fix root causes, and add regression tests for every fixed P0/P1 issue where technically possible.

## Accessibility

Target WCAG 2.2 AA for the editor and generated interactive frontend components.

Verify:

- Keyboard-only operation.
- Logical tab order.
- Visible focus.
- Correct labels and accessible names.
- Screen-reader announcements.
- Dialog focus trap and restoration.
- Escape and arrow-key behavior.
- Reduced motion.
- Color contrast.
- 200% and 400% zoom.
- High-contrast behavior.
- RTL.
- NVDA with Firefox.
- VoiceOver with Safari or WebKit.

Automated scans are required but are not sufficient. Document manual checks separately.

## Performance

Editor datasets:

- Empty Page.
- Normal Page.
- 100 blocks.
- 500 blocks.
- Deeply nested blocks.
- Repeated selection and typing.
- Undo/redo under load.
- Save and autosave latency.

Frontend datasets:

- Static blocks only.
- Multiple Cresco layout blocks.
- Interactive components.
- Dynamic queries.
- Mobile network and CPU throttling.

Measure before and after changes. Do not accept unexplained regressions.

## Compatibility

Test supported combinations across:

- Supported WordPress versions.
- Supported PHP versions.
- Chromium.
- Firefox.
- WebKit.
- Block themes.
- Classic themes.
- Child themes.
- Multisite.
- RTL.
- ACF.
- WooCommerce when in scope.
- Latest supported Gutenberg plugin.
- No Gutenberg plugin.
- Common caching/minification configurations.
- Object cache.
- Different permalink structures.

Every matrix cell must be `PASS`, `FAIL`, `NOT TESTED`, or `NOT SUPPORTED`.

## Packaging and commercial operations

Audit:

- Plugin headers.
- Version consistency.
- GPL compatibility.
- Third-party licenses and notices.
- Release ZIP contents.
- Development-file exclusion.
- Deterministic and reproducible build.
- Translation readiness.
- Text domains.
- Upgrade and rollback paths.
- Changelog and documentation.
- Privacy disclosures.
- Telemetry opt-in and data minimization if telemetry exists.
- Error-reporting privacy.
- Diagnostics privacy.
- Update mechanism and package integrity.
- Free/Pro boundaries if introduced.
- License-key security if introduced.
- No loss or degradation of user-owned content after license expiry.

Do not claim legal review is complete. Create an owner/legal checklist for trademark, terms, privacy, refunds, licensing, data processing, support obligations, tax, and jurisdiction.

# 16. Severity classification

Classify every finding:

```text
P0 — Data loss, remote code execution, critical privilege escalation, unrecoverable corruption, or release-blocking outage.
P1 — Serious security issue, broken save/publish, major migration failure, major accessibility blocker, major compatibility failure, or severe reliability/performance regression.
P2 — Important usability, maintainability, performance, migration, recovery, or test gap.
P3 — Minor defect, polish, documentation gap, or low-risk improvement.
```

Rules:

1. Fix all reproducible P0 issues immediately.
2. Fix all P1 issues before progressing.
3. Add regression tests for fixed P0/P1 issues when technically possible.
4. Fix in-scope P2 findings or create explicit tracked follow-up work.
5. Do not prioritize P3 polish while P0/P1 issues remain.

# 17. Exact execution loop

## Step A — Verify

- Inspect repository state.
- Run all existing checks.
- Record exact commands and results.
- Reproduce defects.
- Establish a clean baseline.

## Step B — Plan

- Determine the latest complete milestone.
- Determine the next incomplete milestone.
- Define one reviewable scope.
- Define measurable acceptance criteria.
- Identify files expected to change.
- Document data, migration, security, accessibility, performance, and rollback risks.

## Step C — Implement

- Create a dedicated branch.
- Use public WordPress APIs.
- Preserve native content and backward compatibility.
- Use small coherent commits.
- Avoid unrelated refactors.
- Update tests, documentation, migrations, version metadata, and changelog with the implementation.

## Step D — Test

Run every applicable check:

- PHP syntax.
- PHPCS.
- PHPUnit.
- TypeScript type checking.
- ESLint.
- Stylelint.
- JavaScript unit tests.
- Playwright E2E.
- Plugin Check.
- Production build.
- Accessibility automation.
- Manual keyboard testing.
- Manual screen-reader testing.
- Performance benchmarks.
- Compatibility matrix jobs.
- Clean install.
- Activation.
- Upgrade.
- Rollback.
- Deactivation.
- Reactivation.
- Uninstall.
- Release ZIP installation.

Do not hide or suppress failures.

## Step E — Adversarial review

Review the complete diff from these perspectives:

1. WordPress Core architect.
2. Gutenberg engineer.
3. Security researcher.
4. Accessibility specialist.
5. Performance engineer.
6. QA engineer attempting destructive workflows.
7. Beginner WordPress user.
8. Professional designer.
9. Professional developer.
10. Site owner upgrading from an older version.
11. Commercial plugin reviewer.

Fix all validated P0/P1 issues and rerun tests.

## Step F — Open one pull request

The PR must include:

- Problem statement.
- Verified baseline.
- Scope and acceptance criteria.
- Before/after behavior.
- Files changed.
- Architecture decisions.
- User-visible behavior.
- Data impact.
- Migration and backward-compatibility impact.
- Security assessment.
- Accessibility assessment.
- Performance measurements.
- Compatibility matrix results.
- Exact test commands and results.
- CI status.
- Screenshots or recordings where possible.
- Known limitations.
- Unverified items.
- Rollback instructions.
- Remaining P2/P3 findings.

Do not merge automatically.

## Step G — Stop and report

After opening the PR:

- Stop implementation.
- Do not begin another milestone in the same PR.
- Report the PR URL, branch, commit SHAs, acceptance criteria, test results, release-gate results, known limitations, and exact recommended next action.
- The next instruction after human review and merge is simply to run this same prompt again.

# 18. Performance budgets

Document the hardware and environment used.

Editor targets:

- Usable editor on a standard test environment: under 2.5 seconds.
- Typing response p95: under 50 ms.
- Block selection p95: under 100 ms.
- Normal Page save: under 1.5 seconds.
- 500-block Page remains usable.
- Unexplained release-to-release regression: no greater than 5%.

Frontend targets:

- Static Canvas Page loads no Cresco editor React runtime.
- Normal Canvas CSS target: under 40 KB gzip.
- Additional independently loaded interactive feature JavaScript target: under 12 KB gzip.
- CLS target: under 0.1.
- Minimal wrappers.
- Conditional assets.
- No Canvas assets on unrelated Pages unless technically required and documented.

Do not estimate or invent measurements.

# 19. Definition of Done

A feature is not complete unless every applicable item is satisfied:

- UX behavior documented.
- Data model documented.
- Permissions implemented.
- Input validated and sanitized.
- Output escaped.
- REST schemas and permissions correct.
- Error states handled.
- Recovery path exists.
- Keyboard behavior works.
- Screen-reader behavior reviewed.
- Responsive behavior defined.
- PHP tests added.
- JavaScript tests added.
- E2E tests added.
- Migration covered.
- Rollback covered.
- Backward compatibility covered.
- Performance impact measured.
- Documentation updated.
- Changelog updated.
- No new PHP notices.
- No new console warnings.
- No unresolved P0/P1 findings.

# 20. Commercial release gates

Cresco Canvas must not be called commercially ready until all eight gates are `PASS`.

## Gate 1 — Data safety

Requires tested save, autosave, revisions, conflict handling, crash recovery, migrations, rollback, deactivation, uninstall, unknown-block preservation, and upgrade fixtures for every released schema.

## Gate 2 — Security

Requires a current threat model, no validated open P0/P1 security finding, reviewed capabilities/nonces/validation/sanitization/escaping/REST permissions, dependency review, and regression tests for high-severity fixes.

## Gate 3 — Accessibility

Requires keyboard-accessible critical flows, WCAG 2.2 AA target for generated interactive components, no unresolved serious/critical automated findings, and documented manual screen-reader, focus, zoom, reduced-motion, and RTL tests.

## Gate 4 — Reliability

Requires green required CI, passing install/activate/edit/save/publish/update/rollback/deactivate/reactivate flows, working Safe Mode and recovery, and no open P0/P1 defects.

## Gate 5 — Compatibility

Requires tested supported combinations, clearly documented unsupported combinations, and no untested combination advertised as supported.

## Gate 6 — Performance

Requires budgets met or deviations explicitly documented and approved, no unexplained major regression, no editor React runtime on static public Pages, scoped/conditional assets, and large-Page tests.

## Gate 7 — Product completeness

Requires the intended 1.0 scope complete, onboarding, documentation, diagnostics, recovery, errors, no placeholder production UI, and no critical workflow requiring developer tools.

## Gate 8 — Release and commercial operations

Requires a reproducible installable ZIP, version consistency, complete changelog/readme/licenses/notices/translations/upgrade notes/rollback notes, privacy and commercial owner checklists, completed beta and release-candidate cycles, and real staging validation.

Commercial readiness requires:

```text
P0: 0
P1: 0

Gate 1: PASS
Gate 2: PASS
Gate 3: PASS
Gate 4: PASS
Gate 5: PASS
Gate 6: PASS
Gate 7: PASS
Gate 8: PASS

FAIL: 0
NOT VERIFIED: 0
```

Even when engineering gates pass, do not claim legal approval unless qualified legal counsel has completed it.

# 21. Release-candidate process

When the 1.0 feature scope appears complete:

1. Freeze new features.
2. Create a release branch.
3. Produce alpha, beta, and release-candidate builds.
4. Test clean sites, existing sites, legacy versions, multiple themes, supported PHP and WordPress versions, multisite, RTL, real staging sites, and representative hosting environments.
5. Collect and classify findings.
6. Fix all P0/P1 findings.
7. Repeat RC testing after release-blocking fixes.
8. Generate the final commercial-readiness report.
9. Open the final release PR.
10. Do not merge or publish automatically.

# 22. Required final report format

At the end of every run, report exactly:

1. Verified repository state.
2. Latest genuinely completed milestone.
3. Current milestone.
4. Feature-completeness matrix summary.
5. Evidence-based readiness percentage.
6. Audit findings by P0/P1/P2/P3.
7. Work implemented.
8. Files changed.
9. Architecture decisions.
10. Data migrations and backward compatibility.
11. Security results.
12. Accessibility results.
13. Performance results.
14. Compatibility matrix summary.
15. Tests run with exact commands and `PASS`, `FAIL`, or `NOT RUN`.
16. CI status.
17. Release artifact status.
18. Known limitations.
19. Unverified items.
20. Commercial release gates 1–8 as `PASS`, `FAIL`, or `NOT VERIFIED`.
21. Pull request URL.
22. Branch name and commit SHAs.
23. Whether the PR is safe for human review.
24. Exact recommended next action.

Never report 100% readiness while any gate is `FAIL` or `NOT VERIFIED`, any P0/P1 exists, mandatory tests are missing, the release artifact is not reproducible, or migration/rollback is unverified.

## Begin now

Audit the current repository first.

Create the feature-completeness matrix.

Repair missing, partial, broken, insecure, or non-production behavior in the current milestone.

Then implement the next incomplete milestone completely.

Run every applicable check.

Open one pull request.

Do not merge automatically.

Stop after reporting the evidence.
