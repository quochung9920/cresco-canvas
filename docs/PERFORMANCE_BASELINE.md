# Performance Gate — 1.0.0-rc.1

Static measurement từ thời Gutenberg không phải baseline hợp lệ cho standalone Cresco Session Studio hiện tại. Tài liệu này reset release baseline thay vì mang số liệu không liên quan sang kiến trúc mới.

## Automated benchmark

`tests/performance/editor-performance.spec.ts` chạy Chromium qua `playwright.performance.config.ts` và tạo ba kích thước document:

- 50 node;
- 200 node;
- 500 node.

Mỗi kích thước ghi:

| Metric | Ý nghĩa |
| --- | --- |
| `editorLoadMs` | navigation tới khi editor và expected node count usable |
| `selectionMs` | click widget tới khi Inspector visible |
| `inspectorTabMs` | chuyển sang Inspector Style |
| `settingsTabMs` | mở Settings surface theo test hiện hành |
| `saveMs` | Update tới khi saved state được acknowledge |

Test ghi `CRESCO_PERF_METRIC` JSON vào log và attach evidence per-size.

## Initial safety ceilings

Trước khi có evidence-based baseline, test chỉ reject freeze rõ ràng:

- editor load: 30 giây;
- selection: 5 giây;
- Inspector tab: 5 giây;
- Settings tab: 5 giây;
- save: 10 giây.

Đây là **anti-freeze ceiling**, không phải commercial performance target.

## Regression baseline rule

Trước first successful controlled release run: **NOT RUN**.

Sau run đầu tiên, record commit, runner class, browser, WordPress/PHP và metric artifacts. Chỉ sau đó mới thiết lập regression threshold dựa trên repeated measurement/CI variance.

Không invent threshold ngược để làm release pass.

## Frontend review

Package verifier cung cấp production file inventory. Release review còn phải confirm:

- editor/admin-only module không enqueue trên unrelated frontend request;
- Cresco frontend asset vẫn conditional theo relevant content;
- không thêm unconditional module/stylesheet nếu không có justification.

Asset-count/per-request measurement vẫn `NOT RUN` cho tới khi có workflow evidence.
