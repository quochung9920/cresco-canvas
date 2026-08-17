# Studio Premium Polish

Cresco Studio giữ nguyên runtime ownership, responsive inheritance và structural UI-correction contract. Premium layer chỉ làm **presentation**, load sau canonical Studio/UI-correction style.

## Hướng hình ảnh

- Giữ light professional Studio shell và Cresco purple action accent.
- Thêm blue/purple/cyan ambient depth có tiết chế quanh Canvas, tránh pastel fill toàn Page.
- Tăng hierarchy bằng heading gọn, panel edge sạch, elevation tinh và selected state rõ.
- Xem editable frame là visual asset chính, có depth/focus feedback mạnh hơn.
- Widget card, command surface, notice, AI preview, recovery và empty state có surface riêng nhưng cùng hệ.

## Interaction/state

- Button chỉ dùng subtle lift/elevation ở interaction state; disabled control không di chuyển.
- Input/search/button/link/tree/command surface giữ visible keyboard focus.
- Loading có branded progress state.
- Fatal startup error giữ retry behavior và có recovery surface dễ đọc.
- Empty/AI-preview/recovery/success/warning/error vẫn do runtime semantics quyết định; polish chỉ đổi presentation.

## Responsive và accessibility

- Desktop canvas depth giảm ở khoảng 1280px để editing surface vẫn dominant.
- Compact Studio có stage padding nhỏ hơn quanh 960px mà không đổi structural ownership.
- `prefers-reduced-motion` loại decorative motion/loading rotation nhưng giữ state visibility.
- `prefers-contrast: more` tăng border/focus indication.
- Glass effect là progressive enhancement; opaque readable background là baseline.

## Verification

```sh
npm run check:studio-premium
npm run lint:css
npm run lint:php
```

Premium checker phải verify stylesheet được Studio owner enqueue, xuất hiện trong asset diagnostics/release allowlist và quality gate theo contract hiện hành.

Hosted Actions/browser smoke vẫn là release evidence riêng; static/local pass không thay thế chúng.
