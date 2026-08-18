# Contract Widget → Control của Cresco Studio

## Mục tiêu

Inspector phải được sinh từ `WidgetCatalog`, không được hiển thị một danh sách control tĩnh cho mọi widget. Một control chỉ được xuất hiện khi widget thực sự khai báo capability/schema tương ứng và pipeline persistence/compiler hỗ trợ nó.

## Source of truth

- Widget/property schema: `includes/Builder/WidgetCatalog.php` và các catalog Professional.
- React owner của Inspector: `runtime-src/build/website-builder-studio.js`.
- Dimension UI: `runtime-src/build/studio-dimension-controls.js`.
- Session vẫn lưu `props`, `style`, `responsive`, `states`, `customCSS` theo contract hiện tại.

## Quy tắc bắt buộc

1. **Không có tab/group rỗng.** Content/Layout/Style chỉ xuất hiện nếu widget có control hợp lệ trong tab đó. Advanced chỉ xuất hiện khi có advanced style/prop hoặc Scoped Custom CSS thực sự dùng được.
2. **Style phải theo allow-list của widget.** Inspector chỉ render key có trong `definition.style`. Không được render toàn bộ `STYLE_GROUPS` cho mọi widget.
3. **Không duplicate owner.** Nếu một prop khai báo `styleKey`, prop đó là control semantic của capability; Inspector không đồng thời hiển thị style control thô cho cùng key.
4. **State phải theo widget.** Chỉ hiển thị Normal + các state có trong `definition.states`. Widget không hỗ trợ hover/focus/active không được cho phép ghi state đó.
5. **Condition phải có hiệu lực.** `schema.condition` quyết định control có được render hay không. Ví dụ Flex controls chỉ hiện khi layout là flex; Grid controls chỉ hiện khi layout là grid.
6. **Panel/group phải có hiệu lực.** `schema.panel` định tuyến control vào Content/Layout/Style/Advanced; `schema.group` tạo nhóm có nội dung và không tạo section rỗng.
7. **Control type phải đúng semantic.**
   - `media`: URL + WordPress media picker.
   - `link`: URL input, không có media picker.
   - `email`: email input.
   - `option-select`: lấy dữ liệu từ `/website-builder/options` theo `optionsSource`.
   - `icon`: Dashicon value + preview.
   - `repeater`: editor danh sách/card; không bắt người dùng sửa toàn bộ JSON thô.
   - `valueLabels`: label thân thiện như H1/H2/H3 phải được dùng.
8. **Numeric control không được biến ô rỗng thành giá trị ngoài schema.** Khi blur, dùng `default` hoặc `min` hợp lệ.
9. **Bulk edit chỉ hiện style chung.** Khi chọn nhiều widget, một style key chỉ được hiện nếu mọi widget được chọn đều hỗ trợ key đó; state cũng dùng giao của capability.
10. **Dimension bridge phải bám key ổn định.** Dùng `data-cresco-prop-key`, `data-cresco-style-key`, `data-cresco-spacing-kind`; không dựa vào vị trí field trong DOM.

## Repeater

Các shape phổ biến (`gallery`, `accordion`, `tabs`, `social`, `form_fields`) phải có editor React theo item với Add/Remove/Move. `string_list` dùng editor một dòng mỗi item. JSON không có shape/control chuyên biệt có thể dùng JSON fallback có validation, nhưng invalid JSON không được ghi vào Session.

## React ownership

Control mới phải được render bởi React/Studio SDK. Cấm re-parent/remove/replace children do React sở hữu bằng `appendChild`, `insertBefore`, `removeChild`, `replaceChildren`, `innerHTML`, hoặc MutationObserver dùng để tái dựng Inspector.

## Regression gates

- `tests/php/WidgetCatalogControlContractTest.php` kiểm tra toàn bộ catalog: type/control/state/style/condition/styleKey hợp lệ.
- `scripts/check-widget-control-contract.mjs` kiểm tra core Inspector thực sự áp dụng catalog allow-list, state filtering, condition/panel/group và source/build parity.
- Các gate này phải nằm trong `npm run check:quality`.

## Definition of Done cho widget mới

Một widget mới chỉ hoàn thành khi: schema hợp lệ → đúng control → save/reload giữ nguyên → frontend/compiler hiểu dữ liệu → responsive/state đúng capability → không có tab/group rỗng → quality gates pass.
