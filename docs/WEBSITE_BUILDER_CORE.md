# Cresco Website Builder Core

`website-core/v1` là integrated website-building layer trên `cresco-session/v1`. Nó **không tạo Page document format thứ hai**. Session cũ vẫn đọc được; Page chỉ được đánh dấu dùng Website Builder renderer sau khi save qua Website Builder flow phù hợp.

## Mục tiêu sản phẩm

Website Builder Core bao phủ workflow cần để xây WordPress site thực tế:

- responsive layout/styling chuyên nghiệp;
- widget library rộng;
- site identity/navigation widget;
- dynamic post/ACF data và bounded post loop;
- reusable component;
- form dựa trên signed Cresco Form runtime;
- WooCommerce product widget khi WooCommerce active;
- Page Settings và Global Design trong cùng editor;
- Theme Builder cho header/footer/single/page/archive/search/404;
- AI context/import, History/revision, keyboard command, multi-selection, Navigator và resize.

## Document compatibility

Stored document vẫn là `cresco-session/v1` version 1. Website Builder node dùng validated:

- `props`
- `style`
- `responsive`
- `states`
- `customCSS`
- `meta`
- `children`

Builder metadata có thể chứa component ID, Navigator label, locked/hidden flag nhưng không cho executable content.

Page meta `_cresco_canvas_builder_version=website-core/v1` chọn Website Builder frontend renderer theo compatibility contract hiện hành.

## Widget groups

Schema authoritative là `includes/Builder/WidgetCatalog.php`.

### Layout

Container và Columns. Container hỗ trợ Block/Flex/Grid, content width, direction, wrap, alignment, justification, grid template, semantic tag và accessible label theo catalog hiện hành.

### Content/media

Heading, Text, Button, Image, List, Divider, Spacer, Icon, Icon Box, Video, Gallery, Testimonial, Social Icons và các widget chuyên nghiệp đã đăng ký.

### Interactive

Accordion, Tabs, Counter, Progress và professional widgets như Carousel/Slides/Modal/Off-canvas… khi catalog/module đăng ký. Interactive control phải giữ ARIA, keyboard và reduced-motion semantics.

### Site

Site Logo, Site Title, Navigation Menu, Breadcrumbs và các site widget được catalog expose. Navigation tham chiếu WordPress menu ID thay vì nhận arbitrary menu markup từ Session.

### Dynamic

Post Title, Post Excerpt, Featured Image, Post Content, Dynamic Field, Loop Grid và các extension liên quan. Dynamic query phải bounded và chỉ dùng public/allow-listed resource theo security contract.

### Forms

Session Form widget dùng Cresco Form runtime canonical cho signed config, server validation, honeypot/rate limit, CAPTCHA, storage, notification, retention, upload và redirect.

### WooCommerce

Woo product widget fail gracefully khi WooCommerce inactive và phải dùng bounded/sanitized Woo/query capability.

## Inspector model

Editor đọc widget catalog từ server, không discover capability chỉ bằng visible label.

Tabs:

- Content
- Layout
- Style
- Advanced

Context:

- Wide/Desktop/Laptop/Tablet/Mobile
- Normal/Hover/Focus/Active khi widget contract hỗ trợ.

UI phải phân biệt Global token, local value, breakpoint override, inherited value và state override.

Shared control như responsive property, dimension/unit, Border/Radius, state tab và Typography popup chỉ là UI adapter/proxy trên canonical style/state model.

Custom CSS là escape hatch cuối cùng và vẫn bị scoped/sanitized.

## Reusable components

Reusable subtree được validate bằng cùng Website Builder sanitizer. Insert cần fresh stable ID/collision-safe remap và ghi source component metadata theo contract.

Nếu linked/synchronized instance được hỗ trợ bởi current workflow thì synchronization vẫn phải qua canonical Session mutation path, không bypass validator.

## Theme Builder integration

Website Builder dùng Theme Builder domain hiện có thay vì router/template system song song.

Theme document có thể dùng Session-native bridge khi capability hiện hành hỗ trợ, nhưng display condition, priority, sanitization và routing vẫn phải có owner rõ.

## Global Design và Page Settings

Global token đến từ Global Design/Design System canonical. User có capability phù hợp mới được edit site settings.

Page Settings dùng `_cresco_canvas_page_settings` và backend model `PageSettings`. Layout, title, header/footer, body style và Page Custom CSS là domain riêng với Session.

## AI workflow

Builder export context chứa current Session, live widget schema, Global Design token và installed capability flag cần thiết.

AI-generated full Session hoặc Patch phải qua Website Builder/AI validator trước Apply. Apply không tự Save.

## Security boundary

Website Builder Core không cho arbitrary executable HTML/shortcode/script/remote CSS hoặc unbounded query đi vào Session contract.

Boundary gồm:

- node/depth budget;
- unique stable IDs;
- bounded collection;
- style allow-list;
- scoped Custom CSS;
- public post type/taxonomy checks;
- private-meta rejection nơi áp dụng;
- capability check cho writes.

Chi tiết: `SECURITY.md`.

## Runtime ownership

Current Studio shell owner là `WebsiteBuilderStudio` + `build/website-builder-studio.js`; các legacy/editor runtime file khác chỉ giữ ownership ở scope được registration code hiện tại cho phép.

Không suy luận canonical runtime chỉ từ tên file cũ. Luôn kiểm tra `WebsiteBuilderStudio.php`, module registry và bootstrap registration trên `main`.

Source/build mirror được khai báo byte-identical phải giữ đồng bộ và được build-integrity check bảo vệ.

## Scope boundary

Website Builder Core mở rộng mạnh khả năng xây site nhưng không tự chứng nhận release. Browser/WP/PHP matrix, accessibility, performance, exact-ZIP, upgrade và P0 commercial hardening vẫn là gate riêng.
