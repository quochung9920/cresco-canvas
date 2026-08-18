# Roadmap và ma trận mức độ hoàn thiện tính năng

> **TÀI LIỆU LỊCH SỬ — stale từ 2026-08-14. Không dùng status hoặc readiness percentage bên dưới để quyết định release hiện tại.** Verification với code thực sự boot đã cho thấy nhiều dòng `MISSING` hoặc `NOT APPLICABLE` mô tả feature đã được implement, register trong `Plugin::boot()` và render — gồm các milestone 0.5 đến 0.9, Accordion, Tabs, Loop Grid, dynamic post widgets, native Forms và WooCommerce widgets. File này có vẻ đã dừng cập nhật quanh milestone 0.3/0.4 trong khi development tiếp tục. Xem [`AUDIT_2026-08.md`](AUDIT_2026-08.md) cho verified position của thời điểm audit; khi hai file mâu thuẫn trong scope đó, audit có authority cao hơn. Matrix dưới đây được giữ làm lịch sử.

Status vocabulary được giữ nguyên: `COMPLETE`, `PARTIAL`, `MISSING`, `BROKEN`, `NOT APPLICABLE`. Một row có thể nhóm nhiều capability liên quan chặt; status áp dụng cho toàn bộ capability được ghi trong row. Evidence mô tả implemented repository behavior tại thời điểm roadmap, không phải planned intent.

## Thứ tự milestone

| Milestone | Status | Evidence lịch sử |
| --- | --- | --- |
| Pre-roadmap 0.1.1 MVP | COMPLETE | Audited `main` commit `7e56722e76138b9b08af5ee5d8bc2b02789e77d9` |
| 0.2 Architecture và reliability foundation | COMPLETE | Implemented trên `milestone/0.2-foundation`; hosted CI evidence được track riêng |
| 0.3 Reliable editor workflow | PARTIAL | Native Gutenberg integration đã implement; hosted WordPress/browser verification chưa có |
| 0.4 Layout và responsive engine | MISSING | Giữ legacy-compatible Container; complete engine chưa có |
| 0.5 Global Design System | MISSING | Chỉ có một subset setting nhỏ đã validate |
| 0.6 Templates và components | MISSING | Chưa implement theo snapshot này |
| 0.7 Theme Builder | MISSING | Chưa implement theo snapshot này |
| 0.8 Dynamic Data và Query Builder | MISSING | Chưa implement theo snapshot này |
| 0.9 Interactive components và integrations | MISSING | Chưa implement theo snapshot này |
| 1.0 Commercial production release | MISSING | Product/release operations chưa complete |

## WordPress integration và architecture

| Capability | Status | Evidence lịch sử |
| --- | --- | --- |
| WordPress/Gutenberg vẫn là engine; không fork Core | COMPLETE | Compose public WordPress packages/APIs |
| Native block markup trong `post_content` | COMPLETE | Parse/serialize dùng standard block markup |
| Native entities: `wp_template`, `wp_template_part`, `wp_block` | MISSING | 0.6/0.7 scope |
| Patterns, Synced Patterns, Pattern Overrides, Block Bindings | MISSING | 0.6/0.8 scope |
| Public Style Engine APIs | MISSING | Safe subset khi đó dùng scoped CSS properties |
| WordPress Interactivity API | MISSING | 0.9 scope |
| Bảo toàn unknown/third-party block | PARTIAL | Standard Gutenberg sở hữu document; thiếu third-party E2E fixture |
| Content vẫn đọc được sau deactivation | COMPLETE | Native markup + không destructive deactivation |
| Reuse Core blocks thay vì clone | COMPLETE | Full native Gutenberg inserter/Core block library vẫn có |
| Không có editor React runtime trên public frontend | COMPLETE | Không enqueue frontend JavaScript |
| Semantic/minimal public output | PARTIAL | Container lưu một wrapper; full library chưa implement |
| Một standard Gutenberg editor entry | COMPLETE | Normal Page title/Edit không bị đụng; dual-editor routing đã remove |
| Per-Page Canvas enablement | COMPLETE | Native sidebar chỉnh revision-enabled REST post metadata qua `core/editor` |
| Không có dual-editor row actions | COMPLETE | Source/E2E regression cấm separate Canvas/WordPress links |
| Native recovery khi thiếu build | COMPLETE | Gutenberg vẫn dùng được; non-blocking admin warning báo missing asset |
| Không redirect loop | COMPLETE | Không có Cresco editor router/edit-link filter/redirect |
| Pages-first support | COMPLETE | Page type/capability checks xuyên suốt |
| Configured public post types | MISSING | Later expansion |
| Correct capabilities/permissions | COMPLETE | Page/settings/publish boundaries được tách |

## Editor shell và document reliability

| Capability | Status | Evidence lịch sử |
| --- | --- | --- |
| Top bar: Back, Page title, Status, Devices, Preview, Save | COMPLETE | Standard Gutenberg top bar sở hữu workflow |
| Top bar: Undo, Redo, Publish, More | COMPLETE | Standard Gutenberg top bar sở hữu workflow |
| Left panel: Add | COMPLETE | Native categorized Gutenberg inserter |
| Left panel: Structure, Templates, Components | PARTIAL | Core List View/patterns có; Cresco template/component library còn thuộc 0.6 |
| Right panel: Content, Layout, Style | COMPLETE | Native block inspector + Container controls + Cresco sidebar |
| Right panel: Responsive, Effects, Advanced | MISSING | 0.4+ scope |
| Element search và categorized inserter | COMPLETE | Native Gutenberg inserter còn hoạt động |
| Drag and drop | COMPLETE | Native Gutenberg canvas/List View behavior |
| Structure/Navigator tree và block breadcrumbs | COMPLETE | Core document overview/List View + selection breadcrumbs |
| Inline text editing | COMPLETE | Native Core block editing; browser matrix vẫn cần cho release evidence |
| Context menu, duplicate, delete | COMPLETE | Native Gutenberg block actions |
| Copy/paste và copy/paste styles | PARTIAL | Core copy/paste có; representative style-copy validation còn thiếu |
| Multi-selection | COMPLETE | Native Gutenberg selection không bị thay |
| Block locking | COMPLETE | Native Gutenberg locking UI/data không bị thay |
| Keyboard shortcuts và command palette | COMPLETE | Native Gutenberg shortcuts/command surfaces |
| Media library integration | COMPLETE | Native media blocks/Media Library |
| Loading skeletons | PARTIAL | Có spinner/empty state; chưa có skeleton system |
| Empty-state onboarding | PARTIAL | Có actionable empty state; guided onboarding thuộc 1.0 |
| Clear errors/notices | COMPLETE | WordPress notices, settings retry, non-blocking missing-build warning |
| Resizable/collapsible panels | PARTIAL | Native panels collapse; chưa thêm custom resizing |
| Beginner defaults và progressive disclosure | PARTIAL | Có safe defaults/panel grouping; chưa có usability study |
| `@wordpress/core-data` native entity workflow | COMPLETE | Cresco chạy trong Core Page editor và không sở hữu Page requests |
| Fetch/edit current Page entity | COMPLETE | Core editor/data stores là source of truth |
| Dirty state | COMPLETE | Core dirty-state selectors/UI sở hữu document |
| Save/update | COMPLETE | Gutenberg lưu Page content và Cresco Page metadata cùng nhau |
| Publish | COMPLETE | Giữ native publish flow |
| Draft/pending/private/scheduled/published states | COMPLETE | Giữ native document status UI; runtime role matrix chưa verify |
| Autosave và revisions | COMPLETE | Giữ Core workflow; Cresco metadata opt-in revisions |
| Undo/redo | COMPLETE | Giữ native Gutenberg history |
| Post locking | COMPLETE | Giữ native WordPress locking |
| Concurrent edit detection/stale revision warning | COMPLETE | Native locking/conflict UI thay custom optimistic route |
| Save retry | PARTIAL | Giữ Core notices/retry; destructive-network E2E còn thiếu |
| Offline/timeout states | PARTIAL | Giữ Core recovery/notices; offline E2E còn thiếu |
| Crash boundary/recovery screen | PARTIAL | Core recovery sở hữu Page; missing Cresco asset fail open; crash E2E còn thiếu |
| Unsaved-change navigation warning | COMPLETE | Giữ native Gutenberg navigation protection |
| Slug, excerpt, featured image, parent Page, template, discussion | COMPLETE | Native Page inspector vẫn có nơi được hỗ trợ |
| Recovery không mất dữ liệu | PARTIAL | Giữ Core autosave/revisions/locking; staged recovery matrix chưa verify |
| Custom REST schemas, permissions, validation, sanitization, tests | COMPLETE | Chỉ còn custom settings; Page routes đã remove/regression-test |

## Thư viện capability layout và content

| Capability | Status | Evidence lịch sử |
| --- | --- | --- |
| Section | MISSING | 0.4 scope |
| Container | PARTIAL | Native custom block có basic layout/spacing/style; full 0.4 controls chưa có |
| Grid và Stack | MISSING | 0.4 scope |
| Columns, Spacer, Divider | PARTIAL | Expose selected Core blocks; custom-shell E2E còn thiếu |
| Full-width/boxed và nested layouts | PARTIAL | Có Container max width/InnerBlocks; chưa có shared complete controls |
| Semantic HTML tags | MISSING | Container luôn save `div` |
| Block/Flexbox/CSS Grid | PARTIAL | Container có mode nhưng chưa có complete responsive engine |
| Direction, alignment, justification, gap | PARTIAL | Basic validated Container controls; chưa có wrap |
| Grid columns/rows, auto-fit/auto-fill | MISSING | 0.4 scope |
| Width/min/max width và height/min/max height | PARTIAL | Chỉ max width |
| Aspect ratio, overflow | MISSING | 0.4 scope |
| Static/relative/absolute/sticky safeguards | MISSING | 0.4 scope |
| Insets, z-index, responsive ordering, visibility | MISSING | 0.4 scope |
| Heading và Paragraph/Text | PARTIAL | Expose Core blocks; full workflow validation đang chờ |
| Button và button group | PARTIAL | Expose Core Buttons; full states/controls chưa có |
| Icon | MISSING | Chưa expose |
| Image và Video | PARTIAL | Expose Core blocks; media workflow validation đang chờ |
| Gallery, Audio | MISSING | Chưa expose |
| List | PARTIAL | Expose Core block; validation đang chờ |
| Quote, Pullquote | MISSING | Chưa expose |
| Code/preformatted safe handling | MISSING | Chưa expose/validate |
| Table, File/download, Embed | MISSING | Chưa expose |
| Safe Shortcode compatibility | MISSING | Chưa implement/validate |
| Restricted/sanitized HTML block | MISSING | Chưa implement trong Canvas library |
| Icon Box, Image Box, Call to Action, Testimonial | MISSING | 0.6 pattern/composition scope |
| Pricing table, Counter, Progress bar, Star rating | MISSING | Later capability scope |
| Social icons, Team member card, Feature list, FAQ | MISSING | Later pattern/component scope |
| Timeline, Logo cloud, accessible before/after | MISSING | Later capability scope |
| Table of contents và Breadcrumbs | MISSING | Later capability scope |

## Interactive, navigation, dynamic, forms và commerce

| Capability | Status | Evidence lịch sử |
| --- | --- | --- |
| Accordion, Tabs, Modal, Off-canvas panel | MISSING | 0.9 scope |
| Dropdown, Tooltip, Mobile menu | MISSING | 0.9 scope |
| Slider/Carousel, Load More, Live filters | MISSING | 0.9 scope |
| Dismissible notice và accessible disclosures | MISSING | 0.9 scope |
| Interactive keyboard/focus/reduced-motion/ARIA/server fallback | MISSING | Áp dụng khi interactive components được build |
| Site Logo, Site Title, Navigation/Menu, Search | MISSING | Site/Theme Builder scope |
| Login/logout links, Post navigation, Pagination | MISSING | Later capability scope |
| Archive title và Query results | MISSING | 0.7/0.8 scope |
| Dynamic Post title/content/excerpt/featured image | MISSING | 0.8 Block Bindings scope |
| Dynamic Author, Date, Terms, Post meta | MISSING | 0.8 scope |
| ACF fields, Site options, User fields | MISSING | 0.8 scope |
| Validated URL parameters, current context, related content | MISSING | 0.8 scope |
| Extensible custom source registry | MISSING | 0.8 scope |
| Established form-plugin integrations | MISSING | 0.9 scope |
| Native accessible fields/validation/conditional logic/multi-step forms | NOT APPLICABLE | Post-1.0 scope theo roadmap snapshot này |
| Native file-upload security/email/webhooks/storage/spam/GDPR | NOT APPLICABLE | Post-1.0 scope theo roadmap snapshot này |
| WooCommerce title/images/price/rating/stock/variations/Add to Cart | NOT APPLICABLE | Post-1.0 scope theo roadmap snapshot này |
| WooCommerce meta/related/upsells/loop/archive filters | NOT APPLICABLE | Post-1.0 scope |
| Cart/Checkout/My Account templates qua stable APIs | NOT APPLICABLE | Post-1.0 scope |

## Responsive và preview systems

| Capability | Status | Evidence lịch sử |
| --- | --- | --- |
| Preview 4K 1920, Desktop 1440, Laptop 1024, Tablet 768, Mobile 390 | PARTIAL | Giữ Core preview modes; exact five-width custom set chưa implement |
| Documented CSS breakpoint system tách preview widths | MISSING | 0.4 scope |
| Desktop-base inheritance; chỉ explicit smaller overrides | MISSING | 0.4 scope |
| Reset/inheritance indicators và không unused media queries | MISSING | 0.4 scope |
| Validated px/%/rem/em/vw/vh/auto/min/max/clamp/token values | MISSING | Controls lúc đó chỉ bounded pixels |
| CSS injection prevention | COMPLETE | Strict server setting grammar; không arbitrary custom values |
| Responsive layout/spacing/typography/alignment/order/sizing/visibility/background/border controls | MISSING | 0.4 scope |
| Fast Gutenberg editing canvas/device simulation | PARTIAL | Giữ Core canvas/modes; timing/exact viewport accuracy chưa đo |
| Selection outlines/editing controls | PARTIAL | Container/Core editor controls có; representative validation đang chờ |
| Không editor chrome trong saved frontend markup | COMPLETE | Save output chỉ có block markup |
| Live frontend theme iframe/synchronization | MISSING | 0.3+ preview scope |
| Theme/header/footer/global/dynamic/query/interactive/template context trong live preview | MISSING | Later milestones |
| External real WordPress preview | COMPLETE | Core preview URL mở new tab |
| Draft preview nonce/privacy preservation | PARTIAL | Dùng Core preview API; role/E2E validation đang chờ |
| Material consistency giữa editor/live/public views | PARTIAL | Native editor/public markup share blocks; thiếu live-theme/browser comparison |

## Shared controls, design system, templates và Theme Builder

| Capability | Status | Evidence lịch sử |
| --- | --- | --- |
| Shared units và linked/unlinked dimensions | MISSING | 0.4 scope |
| Shared margin/padding/width/height/layout controls | PARTIAL | Chỉ Container-specific; chưa shared |
| Shared typography/colors/backgrounds | PARTIAL | Small global subset + Core controls |
| Shared gradients/images/overlays/borders/radius/shadows | MISSING | 0.4/0.5 scope |
| Shared responsive overrides/visibility | MISSING | 0.4 scope |
| Normal/hover/focus/active/disabled states | MISSING | Later controls scope |
| Transitions/motion/transform | MISSING | Later controls scope |
| Permissioned, strictly scoped Custom CSS | MISSING | Chưa implement |
| Native supports → Style Engine → variables → inline precedence | PARTIAL | Dùng variables/inline block style; full adapter chưa implement |
| Colors token group | PARTIAL | Primary/text/muted/background settings chưa có stable token entities |
| Typography token group | PARTIAL | Chỉ một validated font stack |
| Spacing/containers/radius token groups | PARTIAL | Numeric global subset |
| Breakpoint/border/shadow/button/form/link/image/motion/z-index token groups | MISSING | 0.5 scope |
| Token ID/label/group/value/alias/global-local/migration/usage tracking | MISSING | 0.5 scope |
| Documented style precedence | COMPLETE | Architecture/global design documentation |
| Token import/export/preview/conflicts/remapping/reset/versioning/migrations | MISSING | 0.5 scope |
| Scoped global assets/variables | COMPLETE | Canvas-only selectors + conditional enqueue |
| Optional Cresco Base theme | NOT APPLICABLE | Optional future project; plugin không yêu cầu |
| Template categories: Pages/Sections/Headers/Footers/Heroes/Cards/CTAs | MISSING | 0.6 scope |
| Template categories: Pricing/Testimonials/FAQ/Contact/Blog/Portfolio | MISSING | 0.6 scope |
| Template metadata/thumbnail/version/requirements/tokens/preview | MISSING | 0.6 scope |
| Template import validation/conflict/migration | MISSING | 0.6 scope |
| Patterns/Synced Patterns/Overrides/locking/content-only components | MISSING | 0.6 scope |
| Design Mode và Content Mode | MISSING | 0.6 scope |
| Safe synchronized component instance updates | MISSING | 0.6 scope |
| Header/Footer/Single/Archive/Taxonomy/Author/Search/404 Theme Builder | MISSING | 0.7 scope |
| Native template entity storage | MISSING | 0.7 scope |
| Display Conditions: site/post/content/taxonomy/author/search/role/login | MISSING | 0.7 scope |
| Display Conditions: include/exclude, AND/OR, priority/conflict/fallback/preview/emergency | MISSING | 0.7 scope |
| Blank-site-safe condition fallback | MISSING | 0.7 scope |

## Dynamic query và visibility

| Capability | Status | Evidence lịch sử |
| --- | --- | --- |
| Query post type/status/taxonomy/meta/search/author/date | MISSING | 0.8 scope |
| Query ordering/pagination/offset/AND-OR | MISSING | 0.8 scope |
| Query current context/related/manual selection | MISSING | 0.8 scope |
| Query caching/invalidation/limits/expense protection | MISSING | 0.8 scope |
| Native-concept Loop Builder | MISSING | 0.8 scope |
| Visibility theo dynamic values/auth/role/taxonomy/stock | MISSING | 0.8 scope |
| Visibility theo URL/device/date/custom conditions | MISSING | 0.8 scope |
| Server-enforced security-sensitive visibility | MISSING | Áp dụng cùng implementation 0.8 |

## Engineering và repository workflow

| Capability | Status | Evidence lịch sử |
| --- | --- | --- |
| TypeScript source và modular React architecture | COMPLETE | Strict TS modules dưới `src/` |
| `@wordpress/scripts` build system | COMPLETE | Production/development scripts + webpack entries |
| Composer autoloading | COMPLETE | PSR-4 manifest, lock, optimized release install, fallback |
| PHPCS với WordPress/PHP compatibility standards | COMPLETE | Config + required CI job |
| ESLint, Stylelint, type checking | COMPLETE | Scripts chạy local/CI |
| PHP unit tests | COMPLETE | Isolated migration, native metadata, settings, style suites |
| PHP integration tests | MISSING | Real WordPress behavior chỉ smoke/E2E |
| JavaScript unit tests | COMPLETE | Error normalization, token projection, Container style tests |
| Playwright E2E | COMPLETE | Three-browser Gutenberg entry/save/meta/isolation/removed-route/axe suite được cấu hình |
| GitHub Actions | COMPLETE | JS/PHP/WPCS/compatibility/E2E jobs |
| Deterministic lock files | COMPLETE | npm và Composer locks |
| Production/development builds | COMPLETE | `build` và `start` scripts |
| Reproducible release ZIP/checksum | COMPLETE | Fixed timestamps, allowlist, double-build hash check |
| Plugin Check | COMPLETE | Required CI execution được cấu hình |
| Versioned/idempotent migrations | COMPLETE | Schema 1/2, atomic lock, failure state, retired-preference cleanup |
| Feature flags | COMPLETE | Known flags normalize, default off |
| Activation/minimum-version checks | COMPLETE | WordPress/PHP requirements block activation |
| Safe deactivation | COMPLETE | Preserve content/settings |
| Explicit uninstall policy | COMPLETE | Opt-in plugin-data cleanup; không chạm `post_content` |
| Conventional milestone branch/review-only PR | COMPLETE | Dedicated branch; draft PR/no auto-merge policy |

## 1.0 operations và completion

| Capability | Status | Evidence lịch sử |
| --- | --- | --- |
| Onboarding, interactive tour, starter designs | MISSING | 1.0 scope |
| Diagnostics, compatibility report, privacy-safe debug export | MISSING | 1.0 scope |
| Revision browser, restore snapshot, reset/regenerate assets | MISSING | 1.0 scope |
| Complete user/developer documentation | PARTIAL | Engineering docs có; end-user/product docs chưa complete |
| Translations | MISSING | Text domain wired; catalogs chưa có |
| Beta, RC, real staging validation | MISSING | Release-candidate process chưa bắt đầu ở snapshot này |
| Upgrade/rollback guides | PARTIAL | Alpha notes/checklist có; thiếu real fixture evidence |
| Không placeholder production UI | MISSING | Product cố ý vẫn alpha ở snapshot này |

## Matrix summary và readiness calculation

Summary được tạo từ matrix rows bên trên, không tính milestone-history rows:

- `COMPLETE`: 63
- `PARTIAL`: 35
- `MISSING`: 82
- `BROKEN`: 0
- `NOT APPLICABLE`: 6
- Weighted product-scope readiness: **44.7%**

Formula:

```text
(COMPLETE + 0.5 × PARTIAL) ÷ (COMPLETE + PARTIAL + MISSING + BROKEN)
```

Percentage này chỉ mô tả roadmap coverage của snapshot lịch sử. Nó không override tám commercial gates, vốn đều `NOT VERIFIED` ở thời điểm đó.

## Cách dùng roadmap này hiện nay

Roadmap này được giữ để xem product plan và baseline cũ. Không dùng các row `MISSING`/`NOT APPLICABLE` để kết luận feature hiện tại chưa tồn tại. Với current implementation, kiểm tra `main`, current feature contracts và audit/release evidence mới hơn.