# Realtime canonical Studio preview

Cresco Studio dùng một persistent canonical iframe làm visual renderer khi canonical preview path được bật theo runtime hiện hành.

## Runtime contract

- Persisted document được render server-side cho bootstrap preview theo canonical render path.
- Legacy React canvas nếu còn tồn tại chỉ được dùng như interaction bridge, không phải competing visual authority.
- Session edit phải patch current visual preview sớm; root node CSS có thể compile từ current Session để style change không phải chờ REST round trip.
- `RenderEngine` reconciliation chạy background và cập nhật canonical HTML/CSS mà không liên tục replace `iframe.srcdoc`, blank surface hoặc hiện blocking spinner sau initial render.
- Local edit mới phải loại stale reconciled root CSS trước khi cài live root stylesheet đầy đủ để removed/reset declaration không leak từ render cũ.
- Renderer failure sau hydration phải non-blocking: preview hiện có vẫn visible/editable, status báo synchronization delayed.
- Chỉ khi bootstrap render không thể tạo mới dùng blocking renderer/retry state.

## Regression rule

Canonical runtime phải giữ intent tương đương:

- không tự chuyển sang legacy visual fallback như visual authority;
- realtime update bật;
- không reload iframe toàn bộ cho mỗi edit;
- Session-change handler áp local live patch trước rồi mới schedule background reconciliation.

Source/runtime flag cụ thể phải được verify theo code hiện hành trước khi sửa.
