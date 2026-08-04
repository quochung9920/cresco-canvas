# Roadmap and Feature-Completeness Matrix

Status vocabulary is fixed to `COMPLETE`, `PARTIAL`, `MISSING`, `BROKEN`, and `NOT APPLICABLE`. A row may group tightly coupled capabilities; its status applies to every named capability in that row. Evidence describes implemented repository behavior, not planned intent.

## Milestone order

| Milestone | Status | Evidence |
| --- | --- | --- |
| Pre-roadmap 0.1.1 MVP | COMPLETE | Audited `main` commit `7e56722e76138b9b08af5ee5d8bc2b02789e77d9` |
| 0.2 Architecture and reliability foundation | COMPLETE | Implemented on `milestone/0.2-foundation`; hosted CI evidence is tracked separately |
| 0.3 Reliable editor workflow | PARTIAL | Native Gutenberg integration is implemented; hosted WordPress/browser verification remains unavailable |
| 0.4 Layout and responsive engine | MISSING | Legacy-compatible Container is retained; complete engine absent |
| 0.5 Global Design System | MISSING | Small validated setting subset only |
| 0.6 Templates and components | MISSING | Not implemented |
| 0.7 Theme Builder | MISSING | Not implemented |
| 0.8 Dynamic Data and Query Builder | MISSING | Not implemented |
| 0.9 Interactive components and integrations | MISSING | Not implemented |
| 1.0 Commercial production release | MISSING | Product/release operations incomplete |

## WordPress integration and architecture

| Capability | Status | Evidence |
| --- | --- | --- |
| WordPress/Gutenberg remains the engine; no Core fork | COMPLETE | Public WordPress packages and APIs are composed |
| Native block markup in `post_content` | COMPLETE | Parse/serialize uses standard block markup |
| Native entities: `wp_template`, `wp_template_part`, `wp_block` | MISSING | 0.6/0.7 scope |
| Patterns, Synced Patterns, Pattern Overrides, Block Bindings | MISSING | 0.6/0.8 scope |
| Public Style Engine APIs | MISSING | Current safe subset uses scoped CSS properties |
| WordPress Interactivity API | MISSING | 0.9 scope |
| Unknown/third-party block preservation | PARTIAL | Standard Gutenberg owns the document; a third-party E2E fixture remains absent |
| Readable content after deactivation | COMPLETE | Native markup plus no destructive deactivation |
| Core blocks reused instead of cloned | COMPLETE | Full native Gutenberg inserter and Core block library remain available |
| No editor React runtime on public frontend | COMPLETE | No frontend JavaScript enqueue |
| Semantic/minimal public output | PARTIAL | Container saves one wrapper; full library not implemented |
| Single standard Gutenberg editor entry | COMPLETE | Normal Page title/Edit is untouched; dual-editor routing is removed |
| Per-Page Canvas enablement | COMPLETE | Native sidebar edits revision-enabled REST post metadata through `core/editor` |
| No dual-editor row actions | COMPLETE | Source and E2E regression prohibit separate Canvas/WordPress links |
| Missing-build native recovery | COMPLETE | Gutenberg remains usable and a non-blocking admin warning identifies missing assets |
| No redirect loops | COMPLETE | No Cresco editor router, edit-link filter, or redirect exists |
| Pages-first support | COMPLETE | Page type/capability checks throughout |
| Configured public post types | MISSING | Later expansion only |
| Correct capabilities and permissions | COMPLETE | Page/settings/publish boundaries separated |

## Editor shell and document reliability

| Capability | Status | Evidence |
| --- | --- | --- |
| Top bar: Back, Page title, Status, Devices, Preview, Save | COMPLETE | Standard Gutenberg top bar owns these workflows |
| Top bar: Undo, Redo, Publish, More | COMPLETE | Standard Gutenberg top bar owns these workflows |
| Left panel: Add | COMPLETE | Native categorized Gutenberg inserter |
| Left panel: Structure, Templates, Components | PARTIAL | Core List View and patterns exist; Cresco template/component library remains 0.6 |
| Right panel: Content, Layout, Style | COMPLETE | Native block inspector plus Container controls and Cresco sidebar |
| Right panel: Responsive, Effects, Advanced | MISSING | 0.4+ scope |
| Element search and categorized inserter | COMPLETE | Native Gutenberg inserter remains available |
| Drag and drop | COMPLETE | Native Gutenberg canvas/List View behavior remains available |
| Structure/Navigator tree and block breadcrumbs | COMPLETE | Core document overview/List View and selection breadcrumbs remain available |
| Inline text editing | COMPLETE | Native Core block editing; browser matrix still required for release evidence |
| Context menu, duplicate, delete | COMPLETE | Native Gutenberg block actions remain available |
| Copy/paste and copy/paste styles | PARTIAL | Core copy/paste is available; representative style-copy validation remains absent |
| Multi-selection | COMPLETE | Native Gutenberg selection is not replaced |
| Block locking | COMPLETE | Native Gutenberg locking UI/data is not replaced |
| Keyboard shortcuts and command palette | COMPLETE | Native Gutenberg shortcuts and command surfaces remain available |
| Media library integration | COMPLETE | Native media blocks and Media Library remain available |
| Loading skeletons | PARTIAL | Spinner/empty state exists; skeleton system absent |
| Empty-state onboarding | PARTIAL | Actionable empty state exists; guided onboarding is 1.0 scope |
| Clear errors and notices | COMPLETE | WordPress notices, settings retry, and non-blocking missing-build warning |
| Resizable/collapsible panels | PARTIAL | Native panels collapse; custom resizing is not added |
| Beginner defaults and progressive disclosure | PARTIAL | Safe defaults and panel grouping exist; usability study absent |
| `@wordpress/core-data` native entity workflow | COMPLETE | Cresco runs inside Core's Page editor and does not own Page requests |
| Fetch/edit current Page entity | COMPLETE | Core editor/data stores are the single source of truth |
| Dirty state | COMPLETE | Core dirty-state selectors and UI own the document |
| Save/update | COMPLETE | Gutenberg saves Page content and Cresco Page metadata together |
| Publish | COMPLETE | Native publish flow is retained |
| Draft, pending, private, scheduled, published states | COMPLETE | Native document status UI is retained; runtime role matrix remains unverified |
| Autosave and revisions | COMPLETE | Core workflows retained; Cresco metadata opts into revisions |
| Undo and redo | COMPLETE | Native Gutenberg history is retained |
| Post locking | COMPLETE | Native WordPress locking is retained |
| Concurrent edit detection and stale revision warning | COMPLETE | Native locking/conflict UI replaces the custom optimistic route |
| Save retry | PARTIAL | Core notices/retry behavior retained; destructive network E2E remains absent |
| Offline and timeout states | PARTIAL | Core recovery/notices retained; offline E2E remains absent |
| Crash boundary and recovery screen | PARTIAL | Core recovery owns the Page; missing Cresco assets fail open, but crash E2E remains absent |
| Unsaved-change navigation warning | COMPLETE | Native Gutenberg navigation protection is retained |
| Slug, excerpt, featured image, parent Page, template, discussion | COMPLETE | Native Page document inspector remains available where supported |
| Recovery without data loss | PARTIAL | Core autosave/revisions/locking retained; staged recovery matrix remains unverified |
| Custom REST schemas, permissions, validation, sanitization, tests | COMPLETE | Only custom settings remain; Page routes are removed and regression-tested |

## Layout and content capability library

| Capability | Status | Evidence |
| --- | --- | --- |
| Section | MISSING | 0.4 scope |
| Container | PARTIAL | Native custom block with basic layout/spacing/style; full 0.4 controls absent |
| Grid and Stack | MISSING | 0.4 scope |
| Columns, Spacer, Divider | PARTIAL | Selected Core blocks exposed; custom-shell E2E absent |
| Full-width/boxed and nested layouts | PARTIAL | Container max width/InnerBlocks exist; shared complete controls absent |
| Semantic HTML tags | MISSING | Container always saves `div` |
| Block/Flexbox/CSS Grid | PARTIAL | Container supports modes but not complete responsive engine |
| Direction, alignment, justification, gap | PARTIAL | Basic validated Container controls; wrap absent |
| Grid columns/rows, auto-fit/auto-fill | MISSING | 0.4 scope |
| Width/min/max width and height/min/max height | PARTIAL | Max width only |
| Aspect ratio, overflow | MISSING | 0.4 scope |
| Static/relative/absolute/sticky position safeguards | MISSING | 0.4 scope |
| Insets, z-index, responsive ordering, visibility | MISSING | 0.4 scope |
| Heading and Paragraph/Text | PARTIAL | Core blocks exposed; full workflow validation pending |
| Button and button group | PARTIAL | Core Buttons exposed; full states/controls absent |
| Icon | MISSING | Not exposed |
| Image and Video | PARTIAL | Core blocks exposed; media workflow validation pending |
| Gallery, Audio | MISSING | Not exposed |
| List | PARTIAL | Core block exposed; validation pending |
| Quote, Pullquote | MISSING | Not exposed |
| Code/preformatted safe handling | MISSING | Not exposed/validated |
| Table, File/download, Embed | MISSING | Not exposed |
| Safe Shortcode compatibility | MISSING | Not implemented/validated |
| Restricted/sanitized HTML block | MISSING | Not implemented in Canvas library |
| Icon Box, Image Box, Call to Action, Testimonial | MISSING | 0.6 pattern/composition scope |
| Pricing table, Counter, Progress bar, Star rating | MISSING | Later capability scope |
| Social icons, Team member card, Feature list, FAQ | MISSING | Later pattern/component scope |
| Timeline, Logo cloud, accessible before/after | MISSING | Later capability scope |
| Table of contents and Breadcrumbs | MISSING | Later capability scope |

## Interactive, navigation, dynamic, forms, and commerce

| Capability | Status | Evidence |
| --- | --- | --- |
| Accordion, Tabs, Modal, Off-canvas panel | MISSING | 0.9 scope |
| Dropdown, Tooltip, Mobile menu | MISSING | 0.9 scope |
| Slider/Carousel, Load More, Live filters | MISSING | 0.9 scope |
| Dismissible notice and accessible disclosures | MISSING | 0.9 scope |
| Interactive keyboard/focus/reduced-motion/ARIA/server fallback | MISSING | Applies when interactive components are built |
| Site Logo, Site Title, Navigation/Menu, Search | MISSING | Site/Theme Builder scope |
| Login/logout links, Post navigation, Pagination | MISSING | Later capability scope |
| Archive title and Query results | MISSING | 0.7/0.8 scope |
| Dynamic Post title/content/excerpt/featured image | MISSING | 0.8 Block Bindings scope |
| Dynamic Author, Date, Terms, Post meta | MISSING | 0.8 scope |
| ACF fields, Site options, User fields | MISSING | 0.8 scope |
| Validated URL parameters, current context, related content | MISSING | 0.8 scope |
| Extensible custom source registry | MISSING | 0.8 scope |
| Established form-plugin integrations | MISSING | 0.9 scope |
| Native accessible fields/validation/conditional logic/multi-step forms | NOT APPLICABLE | Post-1.0 scope, not current supported product |
| Native file-upload security/email/webhooks/storage/spam/GDPR | NOT APPLICABLE | Post-1.0 scope, not current supported product |
| WooCommerce title/images/price/rating/stock/variations/Add to Cart | NOT APPLICABLE | Post-1.0 scope |
| WooCommerce meta/related/upsells/loop/archive filters | NOT APPLICABLE | Post-1.0 scope |
| Cart/Checkout/My Account templates through stable APIs | NOT APPLICABLE | Post-1.0 scope |

## Responsive and preview systems

| Capability | Status | Evidence |
| --- | --- | --- |
| 4K 1920, Desktop 1440, Laptop 1024, Tablet 768, Mobile 390 previews | PARTIAL | Core preview modes are retained; the exact five-width custom set is not implemented |
| Documented CSS breakpoint system distinct from preview widths | MISSING | 0.4 scope |
| Desktop-base inheritance; explicit smaller overrides only | MISSING | 0.4 scope |
| Reset/inheritance indicators and no unused media queries | MISSING | 0.4 scope |
| Validated px/%/rem/em/vw/vh/auto/min/max/clamp/token values | MISSING | Current controls are bounded pixels only |
| CSS injection prevention | COMPLETE | Strict server setting grammar; arbitrary custom values absent |
| Responsive layout/spacing/typography/alignment/order/sizing/visibility/background/border controls | MISSING | 0.4 scope |
| Fast Gutenberg editing canvas and device simulation | PARTIAL | Core canvas/modes retained; timing and exact viewport accuracy are not measured |
| Selection outlines/editing controls | PARTIAL | Container/Core editor controls exist; representative validation pending |
| No editor chrome in saved frontend markup | COMPLETE | Save output contains block markup only |
| Live frontend theme iframe and synchronization | MISSING | 0.3+ preview scope |
| Theme/header/footer/global/dynamic/query/interactive/template context in live preview | MISSING | Later milestones |
| External real WordPress preview | COMPLETE | Core preview URL opens in a new tab |
| Draft preview nonce/privacy preservation | PARTIAL | Core preview API used; role/E2E validation pending |
| Material consistency across editor/live/public views | PARTIAL | Native editor and public markup share blocks; live-theme/browser comparison absent |

## Shared controls, design system, templates, and Theme Builder

| Capability | Status | Evidence |
| --- | --- | --- |
| Shared units and linked/unlinked dimensions | MISSING | 0.4 scope |
| Shared margin/padding/width/height/layout controls | PARTIAL | Container-specific controls only; not shared |
| Shared typography/colors/backgrounds | PARTIAL | Small global subset and Core controls |
| Shared gradients/images/overlays/borders/radius/shadows | MISSING | 0.4/0.5 scope |
| Shared responsive overrides and visibility | MISSING | 0.4 scope |
| Normal/hover/focus/active/disabled states | MISSING | Later controls scope |
| Transitions/motion/transform | MISSING | Later controls scope |
| Permissioned, strictly scoped Custom CSS | MISSING | Not implemented |
| Native supports → Style Engine → variables → inline precedence | PARTIAL | Variables/inline block style used; full adapter not implemented |
| Colors token group | PARTIAL | Primary/text/muted/background settings lack stable token entities |
| Typography token group | PARTIAL | One validated font stack only |
| Spacing/containers/radius token groups | PARTIAL | Numeric global subset only |
| Breakpoint/border/shadow/button/form/link/image/motion/z-index token groups | MISSING | 0.5 scope |
| Token ID/label/group/value/alias/global-local/migration/usage tracking | MISSING | 0.5 scope |
| Documented style precedence | COMPLETE | Architecture/global design documentation |
| Token import/export/preview/conflicts/remapping/reset/versioning/migrations | MISSING | 0.5 scope |
| Scoped global assets and variables | COMPLETE | Canvas-only selectors and conditional enqueue |
| Optional Cresco Base theme | NOT APPLICABLE | Optional future project; plugin does not require it |
| Template categories: Pages/Sections/Headers/Footers/Heroes/Cards/CTAs | MISSING | 0.6 scope |
| Template categories: Pricing/Testimonials/FAQ/Contact/Blog/Portfolio | MISSING | 0.6 scope |
| Template metadata/thumbnail/version/requirements/tokens/preview | MISSING | 0.6 scope |
| Template import validation/conflict/migration | MISSING | 0.6 scope |
| Patterns/Synced Patterns/Overrides/locking/content-only components | MISSING | 0.6 scope |
| Design Mode and Content Mode | MISSING | 0.6 scope |
| Safe synchronized component instance updates | MISSING | 0.6 scope |
| Header/Footer/Single/Archive/Taxonomy/Author/Search/404 Theme Builder | MISSING | 0.7 scope |
| Native template entity storage | MISSING | 0.7 scope |
| Display Conditions: site/post/content/taxonomy/author/search/role/login | MISSING | 0.7 scope |
| Display Conditions: include/exclude, AND/OR, priority/conflict/fallback/preview/emergency | MISSING | 0.7 scope |
| Blank-site-safe condition fallback | MISSING | 0.7 scope |

## Dynamic query and visibility

| Capability | Status | Evidence |
| --- | --- | --- |
| Query post type/status/taxonomy/meta/search/author/date | MISSING | 0.8 scope |
| Query ordering/pagination/offset/AND-OR | MISSING | 0.8 scope |
| Query current context/related/manual selection | MISSING | 0.8 scope |
| Query caching/invalidation/limits/expense protection | MISSING | 0.8 scope |
| Native-concept Loop Builder | MISSING | 0.8 scope |
| Visibility by dynamic values/auth/role/taxonomy/stock | MISSING | 0.8 scope |
| Visibility by URL/device/date/custom conditions | MISSING | 0.8 scope |
| Server-enforced security-sensitive visibility | MISSING | Applies with 0.8 implementation |

## Engineering and repository workflow

| Capability | Status | Evidence |
| --- | --- | --- |
| TypeScript source and modular React architecture | COMPLETE | Strict TS modules under `src/` |
| `@wordpress/scripts` build system | COMPLETE | Production/development scripts and webpack entries |
| Composer autoloading | COMPLETE | PSR-4 manifest, lock, optimized release install, fallback |
| PHPCS with WordPress/PHP compatibility standards | COMPLETE | Config and required CI job |
| ESLint, Stylelint, type checking | COMPLETE | Scripts run locally and in CI |
| PHP unit tests | COMPLETE | Isolated migration, native metadata, settings, and style suites |
| PHP integration tests | MISSING | Real WordPress behavior is smoke/E2E only |
| JavaScript unit tests | COMPLETE | Error normalization, token projection, and Container style tests |
| Playwright E2E | COMPLETE | Three-browser Gutenberg entry/save/meta/isolation/removed-route/axe suite configured |
| GitHub Actions | COMPLETE | JS/PHP/WPCS/compatibility/E2E jobs |
| Deterministic lock files | COMPLETE | npm and Composer locks |
| Production and development builds | COMPLETE | `build` and `start` scripts |
| Reproducible release ZIP and checksum | COMPLETE | Fixed timestamps, allowlist, double-build hash check |
| Plugin Check | COMPLETE | Required CI execution configured |
| Versioned/idempotent migrations | COMPLETE | Schemas one and two, atomic lock, failure state, and retired-preference cleanup |
| Feature flags | COMPLETE | Known flags normalized and default off |
| Activation and minimum-version checks | COMPLETE | WordPress/PHP requirements block activation |
| Safe deactivation | COMPLETE | Content/settings preserved |
| Explicit uninstall policy | COMPLETE | Opt-in plugin-data cleanup; `post_content` untouched |
| Conventional milestone branch and review-only PR | COMPLETE | Dedicated branch; draft PR/no auto-merge policy |

## 1.0 operations and completion

| Capability | Status | Evidence |
| --- | --- | --- |
| Onboarding, interactive tour, starter designs | MISSING | 1.0 scope |
| Diagnostics, compatibility report, privacy-safe debug export | MISSING | 1.0 scope |
| Revision browser, restore snapshot, reset/regenerate assets | MISSING | 1.0 scope |
| Complete user/developer documentation | PARTIAL | Engineering docs exist; end-user/product docs incomplete |
| Translations | MISSING | Text domain wired; catalogs absent |
| Beta, RC, real staging validation | MISSING | Release-candidate process not started |
| Upgrade and rollback guides | PARTIAL | Alpha notes/checklist exist; real fixture evidence absent |
| No placeholder production UI | MISSING | Product intentionally remains alpha |

## Matrix summary and readiness calculation

The summary is generated from the matrix rows above (excluding milestone-history rows):

- `COMPLETE`: 63
- `PARTIAL`: 35
- `MISSING`: 82
- `BROKEN`: 0
- `NOT APPLICABLE`: 6
- Weighted product-scope readiness: **44.7%**

Formula: `(COMPLETE + 0.5 × PARTIAL) ÷ (COMPLETE + PARTIAL + MISSING + BROKEN)`. This percentage is descriptive roadmap coverage only. It does not override the eight commercial gates, all of which remain `NOT VERIFIED`.
