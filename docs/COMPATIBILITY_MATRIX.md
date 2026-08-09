# Compatibility Matrix — 1.0.0-rc.1

Declared minimums remain WordPress 6.7 and PHP 8.1. This matrix describes the release gate configuration; it does not claim results before jobs execute.

Every cell is one of `PASS`, `FAIL`, `NOT RUN`, `SKIPPED`, `INFRA FAILURE`, or `NOT SUPPORTED`.

## Optimized WordPress/PHP release matrix

| WordPress | PHP | Purpose | Clean activation | Critical editor smoke | Current status |
| --- | --- | --- | --- | --- | --- |
| 6.7.5 | 8.1 | minimum supported boundary | NOT RUN | NOT RUN | NOT RUN |
| 6.9.5 | 8.2 | latest-minus-one line + PHP 8.2 | NOT RUN | NOT RUN | NOT RUN |
| 7.0.2 | 8.3 | current stable + release-default PHP | NOT RUN | NOT RUN | NOT RUN |
| 7.0.2 | 8.4 | current stable + newest supported PHP | NOT RUN | NOT RUN | NOT RUN |

PHPUnit separately executes on PHP 8.1, 8.2, 8.3, and 8.4.

## Browser and theme dimensions

| Dimension | Gate | Current status |
| --- | --- | --- |
| Chromium | critical Playwright suite | NOT RUN |
| Firefox | critical Playwright suite | NOT RUN |
| WebKit/Safari engine | critical Playwright suite | NOT RUN |
| Microsoft Edge | manual Chromium-compatible smoke | MANUAL REQUIRED |
| Twenty Twenty-Five | modern block-theme Page Settings/frontend smoke | NOT RUN |
| Twenty Twenty-One | classic-theme Page Settings/frontend smoke | NOT RUN |

## Integration dimensions

| Dimension | Gate | Current status |
| --- | --- | --- |
| ACF | install/activate + critical editor smoke | NOT RUN |
| WooCommerce | install/activate + critical editor smoke | NOT RUN |
| Multisite | network activation/site smoke | NOT RUN |
| Object cache | compatibility smoke | MANUAL REQUIRED |
| Page cache | compatibility smoke | MANUAL REQUIRED |
| Common optimization/minification behavior | compatibility smoke | MANUAL REQUIRED |
| Security plugin behavior | compatibility smoke | MANUAL REQUIRED |
| CDN/proxy behavior | compatibility smoke | MANUAL REQUIRED |

The matrix is updated only from actual release-run evidence. Workflow configuration alone is not compatibility evidence.
