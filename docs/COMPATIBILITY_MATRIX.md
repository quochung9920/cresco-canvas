# Compatibility Matrix — 1.0.0-rc.1

Minimum đã khai báo: WordPress 6.7 và PHP 8.1. Matrix này mô tả **release gate configuration**, không claim kết quả trước khi job chạy.

Mỗi cell dùng status `PASS`, `FAIL`, `NOT RUN`, `SKIPPED`, `INFRA FAILURE`, `NOT SUPPORTED`; manual gate có thể dùng `MANUAL REQUIRED`.

## WordPress/PHP release matrix

| WordPress | PHP | Mục đích | Clean activation | Critical editor smoke | Trạng thái hiện tại |
| --- | --- | --- | --- | --- | --- |
| 6.7.5 | 8.1 | minimum supported boundary | NOT RUN | NOT RUN | NOT RUN |
| 6.9.5 | 8.2 | latest-minus-one + PHP 8.2 | NOT RUN | NOT RUN | NOT RUN |
| 7.0.2 | 8.3 | current stable + default release PHP | NOT RUN | NOT RUN | NOT RUN |
| 7.0.2 | 8.4 | current stable + newest supported PHP | NOT RUN | NOT RUN | NOT RUN |

PHPUnit chạy riêng trên PHP 8.1, 8.2, 8.3, 8.4 theo release workflow.

## Browser và theme

| Dimension | Gate | Trạng thái hiện tại |
| --- | --- | --- |
| Chromium | critical Playwright suite | NOT RUN |
| Firefox | critical Playwright suite | NOT RUN |
| WebKit/Safari engine | critical Playwright suite | NOT RUN |
| Microsoft Edge | manual Chromium-compatible smoke | MANUAL REQUIRED |
| Twenty Twenty-Five | block-theme Page Settings/frontend smoke | NOT RUN |
| Twenty Twenty-One | classic-theme Page Settings/frontend smoke | NOT RUN |

## Integration

| Dimension | Gate | Trạng thái hiện tại |
| --- | --- | --- |
| ACF | install/activate + critical editor smoke | NOT RUN |
| WooCommerce | install/activate + critical editor smoke | NOT RUN |
| Multisite | network activation/site smoke | NOT RUN |
| Object cache | compatibility smoke | MANUAL REQUIRED |
| Page cache | compatibility smoke | MANUAL REQUIRED |
| Optimization/minification | compatibility smoke | MANUAL REQUIRED |
| Security plugin | compatibility smoke | MANUAL REQUIRED |
| CDN/proxy | compatibility smoke | MANUAL REQUIRED |

Chỉ update matrix từ **actual release-run evidence**. Workflow tồn tại không phải compatibility evidence.
