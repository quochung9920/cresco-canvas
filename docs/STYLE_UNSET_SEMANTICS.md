# Semantics Reset/Unset style trong Studio

Cresco Studio coi Inspector Layout, Style và Advanced là một lớp **override**.

## Contract

- Field Inspector rỗng nghĩa là **không lưu CSS override** cho property tại active scope.
- Wide + Normal không có override thì fallback về widget/theme/browser cascade.
- Desktop/Laptop/Tablet/Mobile không có override thì inherit value từ breakpoint rộng hơn nếu có; nếu không thì fallback cascade bình thường.
- Hover/Focus/Active không có override thì fallback về Normal effective value.
- Reset có thể ghi CSS-wide keyword `initial` tại active scope theo unset bridge hiện hành. Điều này cố ý chặn value từ wider breakpoint/state và đưa property về initial value theo CSS spec.
- Clearing input hoặc chọn empty option phải remove active override và khôi phục inheritance/cascade.
- Inherited/default value có thể hiển thị dưới dạng placeholder/context nhưng không serialize vào Session.
- Multi-selection chỉ hiển thị explicit value khi các widget được chọn có cùng override; mixed/unset phải để trống cho đến khi user nhập override mới.

## Persistence và rendering

Canonical Studio style mutation xóa empty key khỏi base/responsive/state bucket. Frontend compiler bỏ qua empty declaration như safety boundary thứ hai.

Reset value `initial` khác với unset: nếu bridge dùng `initial`, keyword này được persist/emitted có chủ ý để override inherited Cresco value mà không hard-code literal như `auto`, `flex`, `44px` hoặc `176px`.

Purpose-built widget behavior tách khỏi style override. Ví dụ Container/Columns/Spacer có semantic prop cần cho chức năng; clear Style override không xóa semantic đó.

## Runtime presentation

`website-builder-unset-styles.js` chạy sau responsive Inspector/UI correction module. Nhiệm vụ:

- đọc current Session;
- hiển thị explicit value thuộc active scope;
- dùng inherited/default chỉ làm placeholder/context;
- điều phối Reset để model semantics đúng;
- tránh tình trạng input nhìn như reset nhưng persisted override còn tồn tại.

## Quy tắc khi thêm control mới

Mỗi responsive/state control phải định nghĩa:

1. explicit value nằm ở bucket nào;
2. inherited value resolve từ đâu;
3. empty input nghĩa unset hay literal empty;
4. Reset có nghĩa delete key hay persist `initial`;
5. UI hiển thị placeholder thế nào;
6. Save -> reload có tái tạo đúng effective value không;
7. frontend compiler phát CSS nào.

Không được tự tạo reset semantics riêng trong một popup/enhancer.
