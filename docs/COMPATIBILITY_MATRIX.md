# Compatibility Matrix

The declared minimum remains WordPress 6.7 and PHP 8.1 for backward compatibility. CI also covers maintained/current environments and WordPress trunk as experimental evidence.

Every cell is one of `PASS`, `FAIL`, `NOT TESTED`, or `NOT SUPPORTED`.

| WordPress | PHP | Clean activation | Editor smoke | Status |
| --- | --- | --- | --- | --- |
| 6.7 | 8.1 | NOT TESTED | NOT TESTED | Required CI job configured |
| 6.9 | 8.3 | NOT TESTED | NOT TESTED | Required CI job configured |
| 7.0.1 | 8.5 | NOT TESTED | NOT TESTED | Required CI job configured |
| trunk | 8.5 | NOT TESTED | NOT TESTED | Experimental, non-blocking CI job configured |

## Additional dimensions

| Dimension | Status | Notes |
| --- | --- | --- |
| PHP syntax/unit: 8.1, 8.2, 8.3, 8.4, 8.5 | NOT TESTED | Required matrix configured |
| Chromium | NOT TESTED | Playwright project configured |
| Firefox | NOT TESTED | Playwright project configured |
| WebKit | NOT TESTED | Playwright project configured |
| Multisite activation/uninstall | NOT TESTED | Uninstall iterates sites; runtime evidence absent |
| RTL | NOT TESTED | RTL build generated; manual behavior absent |
| Twenty Twenty-Five/theme baseline | NOT TESTED | `wp-env` default theme only |
| Third-party themes/plugins | NOT TESTED | No supported list is advertised |
| WooCommerce | NOT SUPPORTED | Post-1.0 scope |
| Configured public post types | NOT SUPPORTED | Pages-first implementation |

This document must be updated from actual CI run URLs and manual evidence before any compatibility claim is promoted. Gate 5 remains `NOT VERIFIED`.
