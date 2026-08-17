# Cresco Session v1

Cresco Session là visual document format authoritative của standalone Cresco Editor. Đây cũng là format trao đổi copy/paste khi Page được phân tích hoặc tạo bên ngoài WordPress bởi ChatGPT hoặc AI tool khác.

Luồng AI:

```text
Global Design + Widget Contract + Current Session
                    |
                    v
             Copy AI Context
                    |
                    v
                 ChatGPT
                    |
                    v
          cresco-session/v1 JSON
                    |
                    v
       Validate -> Apply -> Update
                    |
                    v
              Cresco Editor
```

Gutenberg block markup không phải AI interchange format. WordPress vẫn sở hữu authentication, permissions, media, routing, REST và page delivery; Cresco Session là source of truth cho Page có saved Cresco document hợp lệ.

## Nguyên tắc thiết kế

1. Giữ widget catalog nhỏ, ổn định và có contract.
2. Ưu tiên Global Design token hơn hard-coded visual value.
3. Ưu tiên native widget prop và structured `style` hơn Custom CSS.
4. Chỉ dùng widget-scoped Custom CSS khi contract chưa expose capability cần thiết.
5. Mỗi node có `id` ổn định/duy nhất để patch có thể address deterministically.
6. Import không Save ngay. Session được validate, Apply vào editor state và chỉ persist khi người dùng bấm **Update**.

## Cấu trúc document

```json
{
  "schema": "cresco-session/v1",
  "version": 1,
  "documentId": "home",
  "nodes": [
    {
      "id": "hero",
      "type": "container",
      "props": {
        "layout": "flex",
        "direction": "column",
        "align": "center",
        "justify": "center"
      },
      "style": {
        "maxWidth": "{layout.contentMax}",
        "paddingTop": "{spacing.2xl}",
        "paddingBottom": "{spacing.2xl}"
      },
      "responsive": {
        "mobile": {
          "paddingTop": "{spacing.xl}",
          "paddingBottom": "{spacing.xl}"
        }
      },
      "customCSS": {},
      "children": [
        {
          "id": "hero-title",
          "type": "heading",
          "props": {
            "text": "Build visually. Run natively.",
            "level": 1
          },
          "style": {
            "fontSize": "{typography.sizes.h1}",
            "textAlign": "center"
          },
          "responsive": {},
          "customCSS": {
            "base": "& { text-wrap: balance; }"
          },
          "children": []
        }
      ]
    }
  ]
}
```

## Core widgets

Version 1 ban đầu expose một core nhỏ:

- `container`
- `columns`
- `heading`
- `text`
- `button`
- `image`
- `list`
- `divider`
- `spacer`

Repository hiện có catalog rộng hơn, nhưng nguyên tắc không đổi: AI output chỉ được dùng widget/property có trong **live catalog** được export bởi current context. Không invent type/property ngoài contract.

## Global Design reference

Structured style có thể dùng token path:

```json
{
  "color": "{colors.text}",
  "background": "{colors.background}",
  "fontSize": "{typography.sizes.h2}",
  "paddingTop": "{spacing.xl}",
  "maxWidth": "{layout.contentMax}",
  "borderRadius": "{radius.md}",
  "boxShadow": "{shadows.md}"
}
```

Renderer compile known token thành stable `--cc-*` CSS variable. Custom color/alias hợp lệ cũng được đưa vào AI context khi contract hỗ trợ.

## Responsive model

`style` là base/widescreen style. Device override nằm trong `responsive`:

```json
{
  "style": {
    "fontSize": "{typography.sizes.h1}"
  },
  "responsive": {
    "desktop": {},
    "laptop": {},
    "tablet": {
      "fontSize": "48px"
    },
    "mobile": {
      "fontSize": "36px"
    }
  }
}
```

Breakpoint/media query được compiler sinh từ responsive contract. Session/AI output không cần tự viết raw `@media` để sở hữu breakpoint system.

## State style

Current Website Builder node có thể có `states` cho các state được widget contract cho phép, ví dụ `hover`, `focus`, `active`.

State override chỉ lưu value explicit. Không được tạo state schema riêng trong optional UI module.

## Custom CSS

Custom CSS là fallback first-class cho capability chưa thuộc Inspector contract.

`&` nghĩa là current widget:

```json
{
  "customCSS": {
    "base": "& { transition: transform var(--cc-motion) var(--cc-easing); } &:hover { transform: translateY(-3px); }",
    "mobile": "& { transform: none; }"
  }
}
```

Stable inner part được widget contract publish, ví dụ:

```css
& [data-cresco-part="text"] {
  letter-spacing: 0.02em;
}
```

Custom CSS phải đi qua canonical scoped CSS validator. Global selector, executable/script-like construct, resource-loading ngoài policy và escape khỏi widget scope phải bị reject.

Responsive Custom CSS dùng bucket Cresco thay vì tự tạo breakpoint ownership song song.

## AI context

AI context có thể chứa:

```json
{
  "format": "cresco-ai-context/v1",
  "global": {},
  "cssVariables": {},
  "widgets": {},
  "session": {}
}
```

Context version mới hơn có thể thêm field/shape, nhưng nguyên tắc vẫn là: AI phải dựa trên live widget contract, Global Design và current Session.

Prompt cơ bản:

> Phân tích Cresco AI Context này và thiết kế lại Page. Chỉ dùng widget type/property được khai báo trong `widgets`. Ưu tiên Global Design token và structured style. Chỉ dùng scoped Custom CSS khi capability chưa có native control. Trả về đúng format Cresco mà workflow yêu cầu, không tự phát minh schema khác.

## Validation và giới hạn

Server validate imported/saved Session, gồm tối thiểu:

- schema/version compatibility;
- known widget type;
- stable unique ID;
- parent/child capability;
- node/depth budget theo runtime hiện hành;
- allow-listed structured style;
- bounded/sanitized prop;
- scoped Custom CSS budget.

Invalid AI output không được trở thành current document qua normal Import flow.

## Storage và frontend

Saved Session được lưu dưới meta `_cresco_canvas_document`. Khi document hợp lệ có node, Cresco render Session và structured/responsive/scoped CSS.

Existing `post_content` được giữ nguyên làm fallback và được dùng khi không có valid Cresco document.

Sự tách biệt này cho phép Cresco phát triển editor/AI mà không yêu cầu AI hiểu WordPress block serialization.
