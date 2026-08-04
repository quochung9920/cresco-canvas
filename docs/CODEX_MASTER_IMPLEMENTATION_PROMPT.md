# Cresco Canvas — Single Complete Widget Builder Prompt

<!-- markdownlint-disable MD013 MD025 -->

This file is the **only authoritative implementation prompt** for Cresco Canvas.

Give this entire file to the coding agent while it is connected to:

```text
quochung9920/cresco-canvas
```

Run the same prompt again after every reviewed and merged pull request. The prompt is intentionally idempotent: each run must inspect the real repository, verify what already works, repair incomplete work, and continue from the next missing requirement without requesting a second prompt.

---

You are the lead product architect, principal WordPress/Gutenberg engineer, React/TypeScript engineer, PHP engineer, visual-builder UX designer, accessibility specialist, security reviewer, QA lead, performance engineer, release manager, and commercial-readiness gatekeeper for **Cresco Canvas**.

Product:

```text
Cresco Canvas
Build visually. Run natively.
```

## Primary mission

Transform the current Cresco Canvas repository into a complete, polished, native WordPress visual website builder with a clearly visible drag-and-drop element library, professional responsive controls, accurate previews, reusable templates/components, Theme Builder, dynamic data, interactive widgets, integrations, tests, migrations, recovery, documentation, and release evidence.

The current product may contain only a Gutenberg sidebar, global settings, and a basic Container block. That is not sufficient. Do not confuse existing Gutenberg functionality with a completed Cresco visual-builder experience.

For every requirement in this prompt:

- If it does not exist, implement it.
- If it is only planned or documented, implement the real behavior.
- If it is partial, finish it.
- If it is broken, repair it and add regression coverage.
- If it is unsafe, redesign it safely.
- If WordPress Core already provides the capability well, integrate, extend, compose, or expose the Core capability instead of creating a redundant proprietary replacement.
- If a custom Cresco block, variation, pattern, extension, or server renderer is needed to provide a coherent user experience, implement it.
- Do not stop after producing an audit, plan, mock interface, placeholder, or empty block registration.
- Do not claim a widget exists merely because a similarly named Core block exists. It must be discoverable through the Cresco element experience, have the required controls, render correctly, and pass its acceptance tests.

The final product must let a normal user build complete responsive Pages and site templates visually without needing code or developer tools.

## Execution model

This is one prompt for the complete product, but implementation must remain reviewable.

On every run:

1. Inspect the actual repository, branch state, history, open pull requests, workflows, tests, documentation, dependencies, build artifacts, and current plugin version.
2. Read `README.md`, `CHANGELOG.md`, all files under `docs/`, all source files relevant to the next scope, test configuration, migrations, and release scripts.
3. Run all existing checks from a clean dependency installation before changing code.
4. Build or update a feature matrix using exactly:
   - `COMPLETE`
   - `PARTIAL`
   - `MISSING`
   - `BROKEN`
   - `NOT APPLICABLE`
5. Verify the latest genuinely complete milestone from code and reproducible evidence, not from version numbers or roadmap claims.
6. Repair incomplete or broken work in the current milestone first.
7. Select the next coherent implementation scope from the ordered roadmap in this prompt.
8. Create a dedicated branch.
9. Implement production behavior, tests, migrations, documentation, and build outputs.
10. Run all applicable checks.
11. Perform an adversarial self-review from architecture, security, accessibility, performance, destructive-workflow QA, beginner-user, professional-designer, and upgrade-user perspectives.
12. Fix every validated P0/P1 issue and add regression tests where possible.
13. Open one reviewable pull request.
14. Do not merge automatically.
15. Stop and report exact evidence.
16. After human review and merge, the same prompt is run again and continues from the repository’s new real state.

Do not combine several large milestones into one unreviewable pull request. Do not ask for another functional specification unless a genuinely business-critical decision cannot be inferred from this prompt, existing repository conventions, or established WordPress practices.

# 1. Non-negotiable architecture and product principles

1. WordPress and Gutenberg remain the engine.
2. Do not fork WordPress Core or Gutenberg.
3. Page content must remain native block markup in `post_content`.
4. Use native entities where appropriate:
   - `wp_template`
   - `wp_template_part`
   - `wp_block`
   - Patterns
   - Synced Patterns
   - Pattern Overrides
   - Block Bindings
5. Do not create an unnecessary proprietary Page JSON format or vendor lock-in.
6. The normal WordPress Page title and **Edit** action must open the standard Gutenberg editor.
7. Cresco Canvas must extend Gutenberg directly. Do not reintroduce a duplicate editor, alternate Page router, or dual-editor workflow.
8. If Cresco assets fail, Gutenberg must remain usable and user content must remain editable.
9. Plugin deactivation must not hide or destroy content.
10. Uninstall must preserve content by default and delete only explicitly approved Cresco metadata/options.
11. Unknown blocks and third-party blocks must survive edits, migrations, activation, deactivation, and upgrades.
12. Prefer Core blocks for basic content, but provide a coherent Cresco discovery, control, responsive, preview, and template experience around them.
13. Create custom blocks only where Core cannot provide the required output, data model, interaction, responsiveness, accessibility, or builder UX.
14. Build a small set of strong composable layout primitives and reusable shared controls.
15. Do not load editor React packages on the public frontend.
16. Public output must use semantic HTML, minimal wrappers, scoped CSS, conditional assets, server rendering where appropriate, and the Interactivity API only for real interaction.
17. Cresco styles and scripts must not leak into unrelated Pages, admin screens, themes, plugins, or third-party blocks.
18. All destructive operations must be undoable, recoverable, confirmed, or protected by revisions.
19. Security, accessibility, performance, data safety, migrations, rollback, compatibility, documentation, and recovery are release requirements.
20. Never commit credentials, tokens, API keys, secrets, user data, or local environment configuration.
21. Do not weaken, skip, delete, broadly mock, or suppress failing tests merely to obtain green CI.
22. Never claim completion because code compiles. Test user-visible behavior.
23. Never merge into `main` automatically.

# 2. Required visual-builder experience

The current screenshot-like state where the user only sees List View on the left and a small Cresco settings sidebar on the right is not the final target.

Implement a clear, discoverable **Cresco Elements Library** directly inside the native Gutenberg workspace.

## 2.1 Cresco Elements Library

Provide a visible editor entry such as a Cresco tab/button/panel integrated through supported Gutenberg APIs. It must not require the user to know block names or use the generic inserter only.

The Elements Library must provide:

- A clearly labeled **Cresco Elements** entry.
- Search by title, keyword, synonym, category, and use case.
- Categories:
  - Layout
  - Basic
  - Media
  - Marketing
  - Navigation
  - Blog and Content
  - Interactive
  - Dynamic
  - Forms
  - WooCommerce
  - Utility
- Grid and compact-list display modes.
- Recognizable icons.
- Short descriptions/tooltips.
- Recent elements.
- Favorites.
- Recommended starter elements.
- Keyboard navigation.
- Accessible names and focus behavior.
- Click-to-insert.
- Drag-to-canvas when Gutenberg APIs permit reliable accessible drag behavior.
- Correct insertion into the currently selected compatible parent.
- Clear explanation when an element cannot be inserted in the current context.
- Empty search state.
- Loading and error states.
- No duplicate registration or duplicate inserter items.
- A developer API for third-party Cresco element registration after the stable core is complete.

A user opening a normal Page editor must be able to discover Cresco Elements without reading documentation.

## 2.2 Builder workspace

Reuse Gutenberg’s native top bar, canvas, List View, document overview, inserter, Block Inspector, keyboard shortcuts, command palette, media library, save/publish flow, autosaves, revisions, locking, and preview actions.

Add Cresco-specific surfaces only where needed:

```text
Left/Inserter surfaces: Elements | Structure | Templates | Components
Right inspector: Content | Layout | Style | Responsive | Effects | Advanced
Top/preview surfaces: 4K | Desktop | Laptop | Tablet | Mobile | Live Preview | External Preview
```

Required behavior:

- Drag-and-drop ordering and nesting.
- Clear drop zones.
- Selection outline and element label.
- Breadcrumbs.
- Structure/Navigator integration.
- Duplicate, delete, copy, paste.
- Copy and paste styles.
- Multi-selection for compatible operations.
- Block locking.
- Context menu.
- Keyboard shortcuts.
- Undo/redo for supported content and style changes.
- Collapsible control sections.
- Searchable controls where appropriate.
- Beginner-friendly defaults.
- Progressive disclosure of advanced controls.
- Reset individual property, section, device override, or complete style.
- Clear inherited/global/local indicators.
- No raw JSON editing required for normal workflows.

## 2.3 Per-element inspector contract

Every Cresco element or Cresco-enhanced Core element must expose only relevant controls, grouped consistently:

### Content

- Text/content/data source.
- Media selection.
- Links and actions.
- Repeater/list items where applicable.
- Accessibility labels where content alone is insufficient.

### Layout

- Display mode.
- Direction.
- Wrap.
- Alignment.
- Justification.
- Gap.
- Width/height/min/max.
- Positioning where safe.
- Order and alignment within parent.

### Style

- Typography.
- Color.
- Background.
- Border.
- Radius.
- Shadow.
- Spacing.
- States where applicable.

### Responsive

- Device-specific explicit overrides.
- Inheritance indicator.
- Reset to inherited value.
- Responsive visibility and order.

### Effects

- Opacity.
- Transform.
- Transition.
- Motion only when performant, accessible, and reduced-motion aware.

### Advanced

- Semantic tag.
- HTML anchor.
- Additional CSS class.
- ARIA controls when safe and understandable.
- Conditional visibility.
- Dynamic binding.
- Custom CSS only with explicit capability checks, strict scoping, validation, and safe output.

# 3. Shared control and style engine

Do not build separate inconsistent controls for each widget.

Create reusable typed schemas, React controls, PHP validation, serialization, migration, and style-generation utilities for:

- Responsive values.
- Unit values.
- Linked/unlinked dimensions.
- Margin and padding.
- Width and height.
- Min/max dimensions.
- Gap.
- Flex controls.
- Grid controls.
- Position controls.
- Typography.
- Colors.
- Gradients.
- Background images and overlays.
- Borders.
- Radius.
- Shadows.
- Opacity.
- Transform.
- Transition.
- Visibility.
- Normal, hover, focus, active, and disabled states where applicable.
- Dynamic bindings.
- Conditional visibility.
- Design-token selection.

Preferred style-output order:

1. Native block supports.
2. Public WordPress Style Engine APIs.
3. Scoped CSS custom properties and generated rules.
4. Minimal inline styles only where justified.

Do not depend directly on private WordPress APIs without an isolated compatibility adapter, version checks, tests, and documented fallback.

Avoid generating a unique stylesheet request per element. Use efficient scoped styles, stable identifiers, deduplication, and cache invalidation.

# 4. Complete widget and capability library

All capabilities below must be represented in the feature matrix. A capability is not `COMPLETE` until it is discoverable, insertable, editable, responsive where applicable, previewed correctly, rendered correctly, accessible, documented, and tested.

Use Core blocks, block variations, block extensions, patterns, server-rendered blocks, or custom blocks according to the best architecture. The user-facing experience must remain coherent under the Cresco Elements Library.

## 4.1 Layout elements

### Section

Purpose: major horizontal Page region.

Required:

- Full width, boxed, contained, and custom width.
- Semantic tags including `section`, `main`, `article`, `aside`, `header`, `footer`, and `div` with validation.
- Inner content container option.
- Background color, gradient, image, video only when performant and accessible.
- Overlay.
- Min height.
- Vertical alignment.
- Overflow.
- Anchor.
- Responsive spacing, visibility, and order.
- InnerBlocks and allowed-block configuration.

### Container

Complete the existing `cresco/container` without breaking legacy content.

Required:

- Block, flex, and grid.
- Row/column direction.
- Wrap.
- Align/justify.
- Gap.
- Width/min/max.
- Height/min/max.
- Padding/margin.
- Background and overlay.
- Border/radius/shadow.
- Semantic tag.
- Position controls with safeguards.
- Nested containers.
- Responsive overrides.
- Migration from current attributes.

### Grid

Required:

- Explicit columns/rows.
- Repeat.
- `auto-fit` and `auto-fill`.
- Minmax controls.
- Column/row gap.
- Item span/start/end.
- Responsive templates.
- Accessible DOM order preserved.
- Prevent visual order controls from creating misleading keyboard/screen-reader order without warning.

### Stack

Required:

- Vertical/horizontal stack.
- Responsive direction.
- Gap.
- Alignment.
- Distribution.
- Wrap.
- Dividers between items when accessible.

### Columns

Provide a Cresco-enhanced native Columns experience or custom implementation only if needed.

Required:

- Presets.
- Custom ratios.
- Responsive stacking.
- Reverse-on-mobile option with accessibility warning.
- Equal height.
- Gap.
- Per-column width and alignment.

### Spacer and Divider

Required:

- Responsive size.
- Horizontal/vertical divider where semantically appropriate.
- Line style, thickness, width, color, alignment.
- Optional accessible label only when content meaning requires it; decorative output must be hidden from assistive technology.

## 4.2 Basic content elements

Expose and enhance these through the Cresco library:

- Heading.
- Paragraph/Text.
- Rich Text.
- Button.
- Button Group.
- Icon.
- Icon List.
- Image.
- Gallery.
- Video.
- Audio.
- List.
- Quote.
- Pullquote.
- Code.
- Preformatted.
- Table.
- File/Download.
- Embed.
- Shortcode.
- Restricted Custom HTML.

Requirements across applicable elements:

- Content editing directly in canvas.
- Semantic markup.
- Link controls including new-tab and rel behavior.
- Typography and responsive typography.
- Alignment and spacing.
- State styling for interactive elements.
- Media alt text guidance.
- Lazy loading according to WordPress behavior.
- Safe embed and HTML handling.
- No arbitrary script execution through Custom HTML.

## 4.3 Media and visual elements

- Image Box.
- Icon Box.
- Media Card.
- Image Hotspots.
- Before/After comparison.
- Lightbox Gallery.
- Video Lightbox.
- Logo Cloud.
- Lottie only if dependency, licensing, accessibility, reduced motion, asset size, and security are acceptable; otherwise mark intentionally unsupported.

All media elements must preserve alt text, captions, keyboard operation, reduced motion, responsive images, and frontend performance.

## 4.4 Marketing and business elements

- Call to Action.
- Feature List.
- Feature Grid.
- Testimonial.
- Testimonial Carousel.
- Pricing Card.
- Pricing Table.
- Team Member.
- Counter.
- Progress Bar.
- Star Rating.
- Stats Grid.
- Timeline.
- FAQ.
- Announcement Bar.
- Badge.
- Social Icons.
- Share Buttons.
- Contact Information.
- Business Hours.
- Map/embed with privacy-conscious loading.
- Countdown with server/client time consistency and accessible fallback.

Use patterns/compositions rather than redundant custom blocks when that produces better portability and maintainability.

## 4.5 Interactive elements

Use the WordPress Interactivity API and server-rendered fallback content where appropriate.

- Accordion.
- Tabs.
- Modal/Dialog.
- Off-canvas panel.
- Dropdown.
- Tooltip.
- Popover.
- Mobile Menu.
- Slider/Carousel.
- Content Toggle.
- Dismissible Notice.
- Load More.
- Live Filters.
- Search suggestions only with bounded queries and privacy/security safeguards.

Every interactive element must include:

- Keyboard operation.
- Correct focus placement and restoration.
- Escape behavior.
- Arrow-key behavior for composite widgets where appropriate.
- Correct ARIA names, roles, states, and relationships.
- Reduced-motion support.
- Touch support.
- Server-rendered meaningful content without JavaScript.
- No frontend React editor runtime.
- E2E accessibility tests and documented manual checks.

## 4.6 Navigation and site elements

- Site Logo.
- Site Title.
- Site Tagline.
- Navigation/Menu.
- Mobile Navigation.
- Search Form.
- Breadcrumbs.
- Login/Logout link.
- Post Navigation.
- Pagination.
- Archive Title.
- Author Box.
- Language switcher integration points.
- Back to Top.

Do not bypass native WordPress Navigation or authentication behavior.

## 4.7 Blog and content elements

- Post Title.
- Post Content.
- Post Excerpt.
- Featured Image.
- Post Date.
- Author.
- Terms/Categories/Tags.
- Comments.
- Related Posts.
- Post Grid.
- Post List.
- Post Carousel.
- Query Results.
- Table of Contents.
- Reading Time.
- Share Buttons.
- Previous/Next navigation.

Prefer native Query and block context where it meets requirements. Create a Cresco Loop/Query experience only where it adds clear visual and dynamic value.

## 4.8 Dynamic data elements and bindings

Implement a secure extensible dynamic-data registry and visual binding UI.

Sources:

- Post title.
- Post content.
- Post excerpt.
- Featured image.
- Post ID and URL.
- Author fields.
- Dates.
- Terms.
- Post meta.
- ACF fields, including supported field types.
- Site options.
- User fields.
- Current queried object.
- URL parameters with strict allowlisting/validation.
- Request context where safe.
- Related content.
- WooCommerce data when enabled.
- Extensible registered callbacks with schema, capability, sanitization, and caching contracts.

Prefer Block Bindings and native block context.

Binding UI must:

- Show compatible sources for the selected property.
- Preview a representative value.
- Allow fallback values.
- Handle missing data clearly.
- Escape output by context.
- Prevent sensitive-data exposure.
- Avoid arbitrary PHP callbacks from untrusted users.

## 4.9 Query Builder and Loop Builder

Provide a visual Query Builder for supported post types.

Required filters:

- Post type.
- Status with capability restrictions.
- Taxonomy.
- Meta Query.
- Search.
- Author.
- Date.
- Include/exclude IDs.
- Parent/children.
- Current context.
- Related content.
- Manual selection.

Required behavior:

- Ordering.
- Pagination.
- Offset with clear pagination limitations.
- AND/OR groups.
- Preview count.
- Empty result state.
- Query limits.
- Cache strategy and invalidation.
- Protection against expensive unbounded queries.
- Server-side permission and data checks.

Loop Builder must support a reusable visual loop-item template based on native blocks/patterns and block context, not raw duplicated HTML strings.

## 4.10 Conditional visibility

Support conditions based on:

- Dynamic field values.
- Logged-in state.
- User role/capability.
- Post type.
- Taxonomy.
- Author.
- Date/time.
- URL parameter with validation.
- Device preference only as presentation, not security.
- WooCommerce product/stock/cart context when available.
- Registered custom conditions.

Provide AND/OR groups, include/exclude logic, clear summaries, and preview context.

Security-sensitive restrictions must be enforced server-side. CSS hiding is not authorization.

## 4.11 Forms

First provide polished integrations for established form plugins detected on the site, including at minimum architecture for:

- Gravity Forms.
- Fluent Forms.
- Contact Form 7.
- WPForms if compatibility is technically and legally appropriate.

Provide:

- Form selector.
- Safe preview.
- Style integration without breaking plugin functionality.
- Success/error/loading states.
- Accessibility-preserving output.
- Conditional asset loading.

A native Cresco Form Builder is post-1.0 unless earlier implementation is explicitly justified. It must not be considered complete without secure validation, email delivery, entry storage, file-upload security, spam protection, privacy controls, webhooks, failure handling, and accessibility.

## 4.12 WooCommerce elements

When WooCommerce is installed and supported, provide through stable WooCommerce APIs:

- Product Title.
- Product Images/Gallery.
- Price.
- Rating.
- Stock.
- Short Description.
- Product Content.
- Variations.
- Add to Cart.
- Product Meta.
- Product Data tabs or accessible equivalent.
- Related Products.
- Upsells.
- Cross-sells where context permits.
- Product Loop.
- Product Filters.
- Cart notices.
- Mini Cart integration.
- Cart, Checkout, and My Account template support only through stable supported APIs.

Never duplicate pricing, stock, tax, variation, cart, checkout, authentication, or order business logic.

## 4.13 Utility elements

- Anchor/Menu Anchor.
- HTML ID helper.
- Responsive visibility helper.
- Sticky helper.
- Back to Top.
- Page Break where meaningful.
- Print visibility.
- Accessible visually-hidden text.
- Template Slot/Hook integration for developers.

# 5. Responsive engine

Implement five first-class preview modes:

```text
4K      1920px preview width
Desktop 1440px preview width
Laptop  1024px preview width
Tablet   768px preview width
Mobile   390px preview width
```

Preview widths and generated CSS breakpoints are related but must be modeled separately and documented.

Responsive inheritance rules:

- Desktop is the base authored value unless the architecture documents a better compatible model.
- Smaller devices inherit from the next larger authored value until explicitly overridden.
- Store only explicit overrides.
- Clearly show inherited, global, local, and overridden states.
- Allow reset to inherited value.
- Do not generate media queries for missing overrides.
- Support responsive layout, spacing, typography, alignment, sizing, order, visibility, backgrounds, borders, and applicable effects.

Supported validated units:

```text
px
%
rem
em
vw
vh
svw
svh
auto
min-content
max-content
fit-content
min()
max()
clamp()
design tokens
```

Use a safe parser/allowlist. Do not concatenate arbitrary unsanitized CSS.

Breakpoint configuration must have validation, collision prevention, migration, reset, and clear warnings. Existing content must not change appearance silently after breakpoint updates.

# 6. Preview system

Provide three clearly distinct preview workflows.

## 6.1 Edit Canvas

The normal Gutenberg editing canvas must provide:

- Fast direct editing.
- Selection outlines.
- Drop zones.
- Element labels.
- Responsive width simulation.
- Cresco global tokens.
- Correct widget styles.
- No editor controls in saved frontend markup.

## 6.2 Live Frontend Preview

Provide a user-visible **Live Preview** action that displays the real frontend rendering in a secure same-site iframe or supported preview surface.

It must include:

- Active theme styles.
- Header and footer.
- Current template.
- Global Styles.
- Cresco generated styles.
- Dynamic data.
- Query results.
- Interactive widget behavior.
- Draft preview security/nonces.
- Preview context selection for templates.
- Device widths: 4K, Desktop, Laptop, Tablet, Mobile.
- Refresh/reload control.
- Clear stale-preview indicator.
- Error state that does not damage the editor.

Use debounced or explicit refresh where full real-time synchronization is unsafe or expensive. Do not claim live synchronization when the preview is only a static width change in the editor.

## 6.3 External WordPress Preview

Keep the native WordPress preview in a new tab for final verification.

Preserve draft privacy, nonces, permissions, status behavior, and theme/plugin execution.

## 6.4 Preview consistency

Create automated comparison fixtures for representative widgets and layouts. Material differences among editor canvas, live frontend preview, and public frontend must be documented and minimized.

# 7. Global Design System

Build a token-first design system.

Token groups:

- Colors.
- Typography families.
- Typography sizes/scales.
- Line heights.
- Font weights.
- Letter spacing.
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

Each token needs:

- Stable ID.
- Label.
- Group.
- Value.
- Optional alias/reference.
- Validation.
- Global/local state.
- Usage tracking.
- Migration behavior.
- Safe deletion/remapping behavior.

Style precedence must be documented and tested:

```text
WordPress/theme defaults
→ Cresco global tokens
→ element defaults
→ instance overrides
→ responsive/state overrides
```

Provide:

- Global editor UI.
- Token picker in element controls.
- Create/edit/duplicate/delete with dependency checks.
- Import/export.
- Import preview.
- Conflict resolution.
- Token remapping.
- Reset.
- Version metadata.
- Rollback/recovery.

Global styles must be scoped. Non-Cresco Pages must not change unless the user intentionally enables an explicit broader scope and receives a clear warning.

# 8. Templates, patterns, components, and kits

## 8.1 Template Library

Provide a visual library with preview thumbnails and categories:

- Full Pages.
- Sections.
- Headers.
- Footers.
- Heroes.
- Feature sections.
- Cards.
- CTAs.
- Pricing.
- Testimonials.
- FAQ.
- Contact.
- Blog.
- Portfolio.
- Team.
- Navigation.
- WooCommerce when supported.

Each template item needs:

- Stable ID.
- Title.
- Description.
- Category.
- Tags.
- Thumbnail.
- Version.
- Required blocks/plugins.
- Minimum plugin version.
- Token dependencies.
- Preview.
- Import validation.
- Conflict handling.
- Migration support.
- Attribution/license metadata for bundled third-party assets.

Provide click preview, insert, replace-confirmation where applicable, favorite, recent, and search.

## 8.2 Components

Use Patterns, Synced Patterns, Pattern Overrides, block locking, and content-only editing before creating proprietary component storage.

Support:

- Create component from selection.
- Insert component instances.
- Synchronized structural/style updates.
- Allowed content overrides.
- Detach instance.
- Rename and organize.
- Usage count.
- Safe deletion.
- Version/migration strategy.

Modes:

### Design Mode

Full structure, layout, style, responsive, dynamic, and advanced controls.

### Content Mode

Only approved text, image, video, icon, link, and repeated-content fields. No destructive layout controls.

## 8.3 Site Kits

Provide export/import of a complete Cresco site kit containing validated selections of:

- Tokens.
- Templates.
- Template parts.
- Patterns/components.
- Widget defaults.
- Breakpoints.
- Required plugin declarations.

Never silently import executable PHP, JavaScript, unsafe HTML, or remote tracking code.

# 9. Theme Builder

Implement a native Theme Builder using WordPress template entities and supported APIs.

Templates:

- Header.
- Footer.
- Front Page.
- Home/Posts Page.
- Single Post.
- Single Page.
- Custom Post Type single.
- Archive.
- Post Type Archive.
- Category.
- Tag.
- Custom Taxonomy.
- Author.
- Date Archive.
- Search Results.
- 404.
- WooCommerce templates when supported.

Display Conditions:

- Entire Site.
- Singular.
- Post Type.
- Specific content.
- Taxonomy.
- Author.
- Archive.
- Search.
- User role/capability.
- Logged-in state.
- WooCommerce context.
- Include and exclude.
- AND/OR groups.
- Priority.
- Conflict detection.
- Fallback template.
- Preview context.
- Emergency disable/recovery.

Requirements:

- Store templates in native entities where appropriate.
- Respect active theme behavior and WordPress template hierarchy.
- Provide a safe blank-site fallback.
- Never create a condition that makes the admin/editor inaccessible.
- Provide conflict summaries and deterministic resolution.
- Preserve content after plugin deactivation as far as native block/template architecture permits; document any plugin-dependent rendering honestly.

# 10. Reliability and data workflows

Use native WordPress entity data and editor stores for Page workflows.

Verify:

- Load/edit current entity.
- Dirty state.
- Save/update.
- Publish.
- Draft/pending/private/scheduled/published states.
- Autosave.
- Revisions.
- Undo/redo.
- Post locking.
- Concurrent edit handling.
- Stale content warning.
- Save retry.
- Offline/timeout behavior.
- Unsaved navigation warning.
- Crash recovery.
- Slug.
- Excerpt.
- Featured image.
- Parent.
- Template.
- Discussion settings when supported.

Custom Cresco data must use explicit schemas, capabilities, validation, sanitization, escaping, migrations, and tests.

Migrations must be:

- Versioned.
- Idempotent.
- Lock-safe.
- Failure-aware.
- Backed by realistic fixtures.
- Non-destructive by default.
- Documented with rollback limitations.

# 11. Ordered implementation roadmap

Determine actual status first, then implement in this order. Repair earlier incomplete work before moving forward.

## Stage A — Elements Library and shared builder foundation

- Cresco Elements entry visible in Gutenberg.
- Search/categories/favorites/recent.
- Click/drag insertion.
- Consistent inspector groups.
- Shared typed controls.
- Element registry and metadata.
- Error/loading/empty states.
- Initial E2E proving a normal user can find and insert an element.

## Stage B — Layout, responsive engine, and preview

- Complete Section, Container, Grid, Stack, Columns, Spacer, Divider.
- Five preview modes.
- Responsive inheritance and generated styles.
- Edit Canvas consistency.
- Live Frontend Preview.
- External Preview retained.

## Stage C — Basic, media, and marketing elements

- Complete all Sections 4.2, 4.3, and 4.4 capabilities.
- Use Core enhancements/patterns where preferable.
- Ensure every listed element appears coherently in Cresco Elements.

## Stage D — Global Design System, templates, and components

- Token system.
- Template Library.
- Synced components.
- Design Mode and Content Mode.
- Site kits.

## Stage E — Navigation, blog, Theme Builder, and dynamic data

- Site/navigation elements.
- Blog/content elements.
- Native Theme Builder.
- Display Conditions.
- Dynamic binding registry.
- ACF support.
- Query Builder/Loop Builder.
- Conditional Visibility.

## Stage F — Interactive elements and integrations

- All Interactivity API widgets.
- Form-plugin integrations.
- Accessibility and no-JS fallbacks.

## Stage G — WooCommerce

- WooCommerce element set and templates through stable APIs.
- Cart/checkout/account compatibility without duplicating business logic.

## Stage H — Production and commercial hardening

- Onboarding.
- Guided tour.
- Starter kits.
- Diagnostics.
- Recovery tools.
- Regenerate styles/assets.
- Compatibility report.
- Privacy-safe debug export.
- Complete end-user and developer documentation.
- Translation readiness.
- Release ZIP.
- Alpha, Beta, RC, staging validation.
- Upgrade and rollback guides.

# 12. Definition of Done for every element and feature

An element/feature is not complete unless all applicable conditions pass:

- Discoverable in the intended Cresco UI.
- Correct icon, title, description, category, and keywords.
- Insertable by click and supported drag behavior.
- Correct default output.
- Content editing works.
- Inspector controls work.
- Responsive behavior works.
- Reset/inheritance works.
- Editor canvas renders correctly.
- Live preview renders correctly.
- Public frontend renders correctly.
- Saved markup remains valid.
- No editor-only chrome is saved.
- Semantic HTML is used.
- Keyboard operation works.
- Screen-reader behavior is reviewed.
- Focus behavior is correct.
- Reduced motion is honored.
- Inputs are validated/sanitized.
- Output is escaped by context.
- Capabilities and REST permissions are correct.
- Error states are recoverable.
- PHP/unit/E2E coverage exists where applicable.
- Migration and backward compatibility are covered.
- Performance impact is measured or bounded.
- Documentation and changelog are updated.
- No new PHP notices, console errors, accessibility-critical violations, or global style leaks.

# 13. Security requirements

Threat-model every new surface:

- REST endpoints.
- Admin actions.
- Metadata.
- Template/site-kit imports.
- Media and uploads.
- Dynamic data.
- URL parameters.
- Query Builder.
- Conditional visibility.
- Custom CSS/HTML.
- External embeds/requests.
- Forms.
- Webhooks.
- WooCommerce context.
- Extension APIs.
- Future AI actions.

Check for and test realistic paths involving:

- Missing authentication/capabilities.
- CSRF/nonces.
- Stored/reflected/DOM XSS.
- SQL injection.
- SSRF.
- IDOR.
- Information disclosure.
- Privilege escalation.
- Path traversal.
- Unsafe file upload.
- Unsafe deserialization.
- Open redirects.
- Secret leakage.
- Dependency and supply-chain risk.
- Query denial of service.

Fix root causes. Add regression tests for every validated P0/P1 issue where technically possible.

# 14. Accessibility requirements

Target WCAG 2.2 AA for both editor additions and generated frontend components.

Verify:

- Full keyboard-only operation.
- Logical tab order.
- Visible focus.
- Correct names/labels.
- Screen-reader announcements.
- Dialog focus trap and restoration.
- Escape behavior.
- Arrow-key behavior.
- Drag-and-drop alternatives.
- Reduced motion.
- Color contrast.
- 200% and 400% zoom.
- Reflow.
- High-contrast/forced-colors behavior.
- RTL.
- Touch target size.
- NVDA + Firefox.
- VoiceOver + Safari/WebKit.

Automated scans are required but are not sufficient. Record manual evidence and mark unperformed tests `NOT VERIFIED`.

# 15. Performance requirements

Measure before and after meaningful changes.

Editor scenarios:

- Empty Page.
- Typical landing Page.
- 100 blocks.
- 500 blocks.
- Deep nesting.
- Repeated selection.
- Continuous typing.
- Dragging/reordering.
- Undo/redo.
- Save/autosave.
- Opening Elements Library.
- Searching Elements.
- Switching preview devices.

Frontend scenarios:

- Static Core/Cresco Page.
- Multiple layout blocks.
- Interactive widgets.
- Dynamic queries.
- WooCommerce template when supported.
- Mobile CPU/network throttling.

Targets on the documented standard environment:

- Editor usable target under 2.5 seconds.
- Typing response p95 under 50 ms.
- Block selection p95 under 100 ms.
- Normal Page save target under 1.5 seconds excluding network variability.
- 500-block Page remains usable.
- No unexplained release-to-release regression greater than 5%.
- No Cresco editor React runtime on static frontend Pages.
- Normal Canvas CSS target under 40 KB gzip.
- Independently loaded interactive feature JavaScript target under 12 KB gzip when practical.
- CLS under 0.1 on representative Pages.
- Assets loaded only when needed.

Do not fabricate measurements.

# 16. Compatibility matrix

Verify supported combinations and mark each cell `PASS`, `FAIL`, `NOT TESTED`, or `NOT SUPPORTED`:

- Supported WordPress versions.
- Supported PHP versions.
- Chromium.
- Firefox.
- WebKit/Safari.
- Block themes.
- Classic themes.
- Child themes.
- Multisite.
- RTL.
- Gutenberg plugin supported version and no Gutenberg plugin.
- ACF.
- Form integrations.
- WooCommerce when supported.
- Object cache.
- Page cache/minification configurations.
- Different permalink structures.
- Common role/capability combinations.
- Clean install.
- Upgrade from every released Cresco schema.
- Deactivate/reactivate.
- Rollback.
- Uninstall.

Never advertise an untested combination as supported.

# 17. Engineering, CI, packaging, and release

Maintain or implement:

- Strict TypeScript source.
- Modular React architecture.
- `@wordpress/scripts` or justified equivalent.
- Composer autoloading.
- WordPress Coding Standards.
- PHP compatibility rules.
- ESLint.
- Stylelint.
- Markdown linting.
- Unit tests.
- PHP tests.
- Integration tests where feasible.
- Playwright E2E.
- Axe automation.
- Plugin Check.
- GitHub Actions matrices.
- Deterministic dependency locks.
- Reproducible production build.
- Allowlisted release ZIP.
- Checksum/artifact generation.
- Version consistency check.
- Release notes and changelog.

Development dependencies and source maps must not be placed in commercial release ZIPs unless intentionally documented.

Audit GPL compatibility, bundled assets, fonts, icons, images, dependencies, and third-party notices.

Do not claim legal review. Create an owner/legal checklist for trademark, privacy, terms, refunds, licensing, data processing, telemetry, support obligations, tax, and jurisdiction.

# 18. Severity and release gates

Classify findings:

```text
P0 — data loss, remote code execution, critical privilege escalation, unrecoverable corruption, or release-blocking outage
P1 — serious security, broken save/publish, major migration failure, major accessibility blocker, major compatibility failure, or severe reliability regression
P2 — important usability, performance, maintainability, migration, recovery, or test weakness
P3 — minor defect, polish, or documentation gap
```

Rules:

- Fix all reproducible P0 immediately.
- Fix all P1 before progressing.
- Add regression tests for P0/P1 fixes where possible.
- Fix in-scope P2 or create explicit tracked follow-up work.
- Do not prioritize P3 polish while P0/P1 remain.

Cresco Canvas may be called commercially ready only when:

```text
P0: 0
P1: 0

Gate 1 — Data safety: PASS
Gate 2 — Security: PASS
Gate 3 — Accessibility: PASS
Gate 4 — Reliability: PASS
Gate 5 — Compatibility: PASS
Gate 6 — Performance: PASS
Gate 7 — Product completeness: PASS
Gate 8 — Release and commercial operations: PASS

FAIL: 0
NOT VERIFIED: 0
```

The engineering gates do not replace qualified legal review or real customer support operations.

# 19. Pull request workflow

For each run:

1. Create a branch such as:

```text
milestone/elements-library
milestone/layout-responsive-preview
milestone/basic-marketing-elements
milestone/design-templates-components
milestone/theme-dynamic-query
milestone/interactive-integrations
milestone/woocommerce
release/1.0.0-rc1
```

2. Use small coherent Conventional Commits where practical:

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

3. Open one pull request including:

- Problem statement.
- Verified baseline.
- Scope and acceptance criteria.
- Before/after user flow.
- Screenshots or recordings for UI work when possible.
- Elements/capabilities added.
- Files changed.
- Architecture decisions.
- Data model and migration impact.
- Backward compatibility.
- Security assessment.
- Accessibility assessment.
- Performance measurements.
- Compatibility results.
- Exact test commands and results.
- CI status.
- Release artifact status.
- Known limitations.
- Unverified items.
- Rollback instructions.
- Remaining P2/P3 findings.

4. Do not merge automatically.
5. Stop after opening the pull request.

# 20. Required final report

At the end of every run, report exactly:

1. Verified repository state.
2. Latest genuinely completed stage.
3. Current implemented scope.
4. Overall feature matrix counts: COMPLETE/PARTIAL/MISSING/BROKEN/NOT APPLICABLE.
5. Evidence-based roadmap coverage percentage.
6. Audit findings by P0/P1/P2/P3.
7. Elements/widgets implemented in this run.
8. User-visible builder changes.
9. Files changed.
10. Architecture decisions.
11. Data migrations and backward compatibility.
12. Security results.
13. Accessibility results.
14. Performance results.
15. Compatibility summary.
16. Tests run with exact command and PASS/FAIL/NOT RUN.
17. CI status.
18. Release artifact status.
19. Known limitations.
20. Unverified items.
21. Commercial release gates, each PASS/FAIL/NOT VERIFIED.
22. Pull request URL.
23. Branch and commit SHAs.
24. Whether the pull request is safe for human review.
25. Exact recommended next action after review and merge.

Never report 100% readiness while any mandatory capability is missing from the agreed 1.0 scope, any P0/P1 remains, any release gate is `FAIL` or `NOT VERIFIED`, mandatory testing is missing, or real staging/beta/RC validation has not occurred.

## Begin now

Audit the repository first.

Do not merely update the roadmap.

Repair any incomplete current work, then select the first incomplete stage from the ordered roadmap.

Implement it as working production-quality behavior with tests and documentation.

Open one pull request.

Do not merge automatically.

Stop after reporting evidence.