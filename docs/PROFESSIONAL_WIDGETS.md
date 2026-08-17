# Professional Widgets và Border Controls của Cresco Canvas

Tài liệu này mô tả bộ professional widget và linked Border editor xây trên kiến trúc canonical Cresco Session/WidgetCatalog.

## Mục tiêu

1. Saved document tiếp tục dùng `cresco-session/v1`.
2. Server allow-list và AI creation catalog tiếp tục do `WidgetCatalog` điều khiển.
3. Reuse một browser interaction engine cho carousel/motion widget thay vì thư viện riêng cho từng widget.
4. Biến visual pattern phổ biến thành native capability để user/AI không phải dùng Custom CSS cho slider, marquee, rating, before/after hoặc per-side border.
5. Giữ backward compatibility với CSS shorthand hiện có.

## Border controls

Cresco tiếp tục lưu các structured CSS key chuẩn:

- `borderWidth`
- `borderStyle`
- `borderColor`
- `borderRadius`

Width/Style/Color dùng CSS order `top right bottom left`. Radius dùng `top-left top-right bottom-right bottom-left`.

Control bắt đầu ở **Linked** mode. Khi unlink, user chỉnh từng side/corner. Đây là editor control trên CSS shorthand, **không phải document schema mới**. Session cũ và renderer vẫn tương thích. Responsive/state bucket nhận shorthand qua canonical control của active scope.

Khi blueprint/AI contract publish `styleShorthands`, external AI phải dùng đúng side/corner order.

## Professional widget suite

### Carousel family

- `carousel` — direct children trở thành slide.
- `slides` — hero-oriented viewport với slide/fade behavior.
- `loop-carousel` — dùng bounded Loop Grid query model và carousel engine.
- `image-carousel` — gallery/Media Library data.
- `testimonial-carousel` — testimonial/content slide lồng nhau.
- `logo-carousel` — logo/image slide, có thể grayscale.
- `media-carousel` — mixed image/video/content.

Shared control có thể gồm slides per view, tablet/mobile count, gap, loop, autoplay, delay, speed, pause-on-hover, arrow, dots/fraction pagination, centered layout, adaptive height và keyboard navigation.

### Infinite Marquee

`marquee` là nested-content infinite loop native. Direct child được frontend engine duplicate và có thể chạy left/right/up/down. Widget hỗ trợ duration, gap, pause on hover/focus, edge fade và `prefers-reduced-motion` fallback.

### Interactive/content widgets

Theo catalog/module hiện hành có thể gồm:

- `before-after`
- `timeline`
- `pricing-table`
- `countdown`
- `modal`
- `off-canvas`
- `comparison-table`
- `hotspot-image`
- `flip-card`
- `animated-headline`
- `progress-circle`
- `rating`
- `site-search`
- `advanced-breadcrumbs`
- `map`

Các widget này dùng validated prop primitive như string, enum, bool, number, CSS value, URL, bounded list/JSON để tiếp tục đi qua cùng editor, REST context, AI catalog và validation pipeline.

## Rendering architecture

Professional widget layer là adapter quanh canonical renderer, không phải renderer thứ hai.

1. Stored Session không đổi schema.
2. Professional widget được translate in-memory sang safe/canonical render primitives khi adapter yêu cầu.
3. Canonical renderer/compiler tạo HTML/CSS.
4. Root có thể nhận `data-cresco-pro-widget` và bounded config để frontend behavior khởi tạo.
5. Shared professional runtime initialize interaction phù hợp.

Nhờ đó style compilation, responsive/state, scoped CSS, Global token và security boundary vẫn nằm ở canonical render path.

## Shared frontend engine

Runtime dùng chung có thể cung cấp carousel navigation/pagination/autoplay/keyboard, marquee, before/after, countdown, animated headline, progress/rating, accessible modal/off-canvas, comparison table, search, constrained map, flip-card, timeline, pricing và hotspot behavior.

Không cần third-party carousel dependency nếu shared engine đã đáp ứng contract.

## Hướng dẫn AI authoring

AI nên ưu tiên native widget trước Custom CSS. Ví dụ:

- dùng `marquee` thay hand-written duplicated-list `@keyframes`;
- dùng `loop-carousel` thay custom HTML từ post query;
- dùng `carousel` cho nested card và `image-carousel` cho Media Library image;
- dùng `borderWidth: "1px 0 2px 0"` cho per-side border;
- dùng `borderRadius: "16px 16px 4px 4px"` đúng corner order.

Danh sách capability authoritative luôn là `WidgetCatalog`/AI creation catalog được export từ current context.
