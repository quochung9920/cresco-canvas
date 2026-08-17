# Chỉnh sửa và Import/Export Global Design

Cresco Canvas giữ Global Design đơn giản trong tab/panel **Global**. Cùng một domain được dùng để edit trực tiếp, import configuration và export current configuration.

## Workflow trong editor

1. Mở Page bằng Cresco Canvas.
2. Mở **Global**.
3. Chỉnh Colors, Font family, Layout, Radius hoặc Breakpoints trong các field được UI hiện hành expose.
4. Bấm **Save Global**.
5. Dùng **Import** để đưa CSS variable hoặc JSON hợp lệ vào preview/flow import.
6. Dùng **Export** để copy Global settings hiện tại dưới dạng JSON.

Sau khi Save Global Design, reload editor khi cần để refresh token resolution và AI Context. Save Page đang dirty trước khi reload.

Import validation/save dùng protected Cresco REST endpoint và yêu cầu WordPress capability phù hợp, điển hình `edit_theme_options`.

## Ví dụ CSS import

```css
--bg: oklch(98% 0.005 250);
--surface: oklch(99% 0.002 250);
--surface-alt: oklch(95% 0.012 250);
--ink: oklch(22% 0.02 250);
--ink-muted: oklch(46% 0.015 250);
--blue-dark: oklch(38% 0.13 255);
--blue: oklch(55% 0.15 235);
--blue-light: oklch(90% 0.035 235);
--green: oklch(55% 0.13 145);
--border: oklch(88% 0.012 250);
font-family: Poppins, sans-serif;
color: var(--ink);
```

Mapping built-in gồm:

- `--bg` / `--background` -> `colors.background`
- `--ink` / `--text` / `--foreground` -> `colors.text`
- `--ink-muted` / `--muted` -> `colors.muted`
- `--blue` / `--primary` / `--brand` / `--accent` -> `colors.primary`
- safe color variable khác -> `colors.custom-*`

Custom color có thể xuất hiện thành editable row sau import và có thể thêm thủ công nếu UI hiện hành hỗ trợ.

## Color value được hỗ trợ

Global color chấp nhận sanitized HEX, `rgb()`, `rgba()`, `hsl()`, `hsla()`, `oklab()`, `oklch()` theo sanitizer hiện hành.

External resource và arbitrary CSS không thuộc grammar phải bị reject.

## JSON import/export

**Export** copy editable Cresco Global settings dưới dạng JSON.

Importer có thể nhận raw Cresco settings JSON, exported Global JSON và compatibility token catalog của build cũ khi adapter hiện hành hỗ trợ.

Font-family import lưu font stack; Cresco không tự động download/load external font file.

## Ownership rule

Global Design UI không được tạo một token store thứ hai. Import/export/preview phải đi qua canonical Global settings sanitizer và REST/service owner.
