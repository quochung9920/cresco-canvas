# Trạng thái thực thi Roadmap của Cresco Canvas

> **Tài liệu lịch sử.** Assessment date: **2026-08-05**.
>
> File này ghi implementation state trên `main` tại thời điểm assessment. Một milestone không được coi production-verified cho tới khi có hosted CI và WordPress runtime evidence tương ứng.

## Trình tự release

1. `0.3.1` — Gutenberg và Cresco Elements stabilization.
2. `0.4.0` — Layout, responsive inheritance và preview.
3. `0.5.0` — Global Design System.
4. `0.6.0` — Templates, components và site kits.
5. `0.7.0` — Theme Builder và display conditions.
6. `0.8.0` — Dynamic Data, ACF, Query Builder và Loop Builder.
7. `0.9.0` — Interactive components và form integrations.
8. `1.0.0` — Production/commercial hardening.

## Trạng thái implementation tại thời điểm assessment

### `0.3.1-alpha.1` — SOURCE CANDIDATE, NOT VERIFIED

Native Gutenberg integration, Cresco Elements, insertion safeguards, browser-state sanitation và regression tests đã tồn tại. Hosted CI và complete WordPress runtime evidence vẫn thiếu.

### `0.4.0-alpha.1` — SOURCE CANDIDATE, NOT VERIFIED

Responsive Container controls, inheritance, năm logical preview widths và Live Frontend Preview runtime đã tồn tại. Shared responsive controls cho supported Core blocks và full runtime verification vẫn chưa complete.

### `0.5.0-alpha.1` — FUNCTIONAL SOURCE AND CHECKED-IN RUNTIME, NOT VERIFIED

Đã triển khai:

- structured global tokens cho colors, typography, spacing, layout, radius, shadows và motion;
- custom colors/aliases với server sanitization và dependency-aware deletion;
- REST read/save/reset/catalog endpoints;
- import/export controls và schema version 3 migration;
- independent checked-in Gutenberg runtime và release-package requirements.

Hosted CI, manual accessibility, browser, RTL, multisite, lifecycle và performance evidence vẫn thiếu.

### `0.6.0-alpha.1` — FUNCTIONAL SOURCE AND CHECKED-IN RUNTIME, NOT VERIFIED

Đã triển khai:

- native WordPress pattern categories và bundled templates;
- Gutenberg Template Library search/filter/insertion;
- synced components dùng native `wp_block` entities;
- sanitized Site Kit import/export;
- checked-in runtime, stylesheet, manifest, packaging requirements và regression test source.

Giới hạn quan trọng khi đó gồm thiếu hosted CI/runtime evidence, component capture còn hạn chế, chưa có remote catalog và chưa chứng minh reproducible `npm ci` build.

### `0.7.0-alpha.1` — FUNCTIONAL SOURCE AND CHECKED-IN RUNTIME, NOT VERIFIED

Đã triển khai:

- native `cresco_template` Theme Builder entities với Gutenberg editing/revisions;
- Header, Footer, Single, Page, Archive, Search và 404 template types;
- allow-listed include/exclude display conditions và priority-based resolution;
- frontend slot/document rendering, REST CRUD, Gutenberg controls, admin columns và conflict diagnostics;
- checked-in runtime, renderer, packaging requirements và regression test source.

Evidence còn thiếu gồm hosted CI, theme/plugin compatibility, accessibility, RTL, browser, multisite, role, revision và frontend runtime verification.

### `0.8.0-alpha.5` — FUNCTIONAL SOURCE AND CHECKED-IN RUNTIME, NOT VERIFIED

Đã triển khai trong alpha.1 → alpha.5:

- dynamic scalar fields cho post, site, post meta và ACF values;
- dynamic images cho featured/meta/ACF image return formats;
- bounded standard Loop queries, presets, pagination và nested-loop protection;
- Dynamic Gallery/Relationship Loop cho ACF/meta structured values;
- ACF Repeater với một native block template mỗi row;
- ACF Flexible Content với layout-specific child templates và fallback mapping;
- ACF Sub Field dot-path binding cho scalar row values;
- permission-protected ACF field schema discovery không expose raw values;
- Advanced Loop cho post type, author, parent, search, dates, include/exclude IDs, một meta clause và tối đa ba taxonomy clauses;
- Filterable Loop với signed public AJAX rendering, search, tối đa ba taxonomy facets, AJAX pagination, Load More, Infinite Scroll, URL/history synchronization và no-JavaScript fallback;
- WooCommerce presets cho newest, featured, on-sale, in-stock, best-selling và top-rated products;
- checked-in Gutenberg/editor/frontend runtimes, styles, manifests, release-package requirements, REST discovery/preview/render endpoints và regression test source.

Giới hạn đã biết tại thời điểm đó:

- taxonomy facets chưa tính live per-option counts;
- runtime meta facets, range filters, active-filter chips và dependent facet counts chưa implement;
- Repeater/Flexible Content chủ yếu bind scalar sub-fields; dedicated nested image/gallery/relationship/repeater row bindings còn future work;
- Advanced Loop cố ý không cho arbitrary nested meta/tax query groups hoặc arbitrary SQL;
- nhiều Filterable Loop giống nhau nên dùng unique Instance IDs để tránh URL-key collision;
- dependency lock/build environment chưa khả dụng nên checked-in runtimes chưa được reproduce từ TypeScript source pipeline;
- hosted CI và full WordPress/ACF/WooCommerce runtime, accessibility, RTL, multisite, browser, security, performance evidence vẫn thiếu.

### `0.9.0` — NOT STARTED AS A COMPLETE MILESTONE

Tại assessment này, Interactive Tabs, Accordion, Modal, Slider/Carousel, Form Builder, validation, submission handling, spam protection, external form integrations và interaction controls vẫn được ghi là future work của complete milestone 0.9.

> Lưu ý lịch sử: release notes sau đó cho thấy scope 0.9 tiếp tục được triển khai. Không dùng dòng này để kết luận trạng thái hiện tại.

### `1.0.0` — NOT COMPLETE

Production hardening, complete CI/runtime matrices, reproducible packaging, migration/rollback validation, accessibility/security certification, beta/RC gates, stable documentation, support policy và commercial infrastructure vẫn là future work tại thời điểm assessment.

## Trạng thái validation

- Checked-in PHP, JavaScript, CSS và asset manifests tồn tại đến `0.8.0-alpha.5`.
- Regression test source tồn tại cho main sanitization, catalog, resolver, query, structured-data, signature, public-filter và template-safety boundaries.
- Chưa quan sát successful hosted workflow hoặc complete WordPress runtime result cho latest `main` commit tại thời điểm đó.
- Không được claim production/commercial readiness.
- `0.9.0` và `1.0.0` release gates còn unverified.

## Direct-main policy tại thời điểm này

Direct `main` updates đã được yêu cầu rõ. Changes được commit theo các coherent unit nhỏ. Mỗi milestone phải bảo toàn native Gutenberg content, dùng capability checks, cân nhắc migration/packaging và disclose evidence còn thiếu.

## Cách dùng hiện nay

Đây là snapshot ngày 2026-08-05, không phải current roadmap. Để quyết định implementation hiện tại, dùng current source, `PROJECT_RULES.md`, current ADR/canonical docs và release evidence mới nhất.