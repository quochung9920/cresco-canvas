# Giới hạn đã biết — 1.0.0-rc.1

Cresco Canvas `1.0.0-rc.1` là **release candidate**, chưa phải stable release được chứng nhận thương mại.

## Build và release evidence

- Repository có transitional authoritative runtime source tree `runtime-src/build/` cho các runtime lịch sử từng được hand-maintain, cùng clean-delete/rebuild verification. Kiến trúc này chỉ được coi là chứng minh khi hosted source/build gate pass trên đúng commit.
- `npm ci`, optimized Composer install, lint, unit/PHP test, PHPCS, reproducible clean checkout, package verification và dependency audit cần hosted evidence trên đúng release commit.
- Release pipeline dùng production ZIP allowlist, `SHA256SUMS`, SPDX inventory và unsigned provenance metadata. Tạo artifact không đồng nghĩa artifact install được.
- Exact candidate ZIP vẫn phải pass isolated clean WordPress install/edit/save/preview P0 gate trước stable release.

## Widget Inspector và persistence

- Core/Cresco style path có coverage rộng, nhưng toàn visible-control matrix vẫn cần WordPress/browser evidence.
- Save/reload, revisions, History, copy/paste, pattern và legacy compatibility chỉ được gọi commercially verified sau khi gate tương ứng pass.
- Shared Dimension/Border, responsive state và Typography popup là UI/runtime feature; sự tồn tại của chúng trong source không thay thế browser save/reload/frontend verification.

## Editor workspace

- Product dùng standalone Cresco Session Studio. Khác biệt browser/WordPress DOM/runtime vẫn có thể lộ freeze, focus hoặc rendering defect.
- Critical Chromium/Firefox/WebKit flow có automation trong release process nhưng chỉ được tính là pass khi workflow thực sự chạy.
- Dedicated Edge validation vẫn có phần manual.

## Global Design và Page Settings

- Global Design/Page Settings đã implement nhưng compatibility trên WordPress/PHP/theme matrix cần release-run evidence.
- Site-wide lightbox và page-transition/preloader vẫn bị giới hạn nếu frontend runtime hiện hành chưa implement control tương ứng.

## Forms và public endpoints

- Source-level hardening có request/rate bounds, signed form/query payload, webhook HTTPS/private-network/DNS checks, disabled redirects, upload extension/MIME/content/polyglot validation, private upload ownership/download checks, CSV formula neutralization, retention và privacy export/erase.
- `check:production-hardening` bảo vệ source contract nhưng không phải penetration test.
- Provider secret/CAPTCHA verification phụ thuộc adapter/site config và cần live-provider evidence.
- Webhook vẫn cần production-like egress testing vì DNS validation không loại bỏ mọi TOCTOU condition.
- Upload cần real web-server test để chứng minh private directory ngoài document root, không execute script và protected download hoạt động.
- Large CSV export và public rate/idempotency cần integration evidence với traffic thực tế.

## Dynamic data và integrations

- ACF/WooCommerce smoke job chỉ là cấu hình cho đến khi run.
- Object cache, page cache, CDN/proxy, security plugin và optimization/minification cần compatibility evidence.
- Large-catalog query/facet cost, invalidation và no-JS behavior cần production-like testing.

## Lifecycle và upgrade

- Migration, pre-migration backup, retry, downgrade detection, multisite batching, non-destructive deactivation, explicit uninstall ownership và `post_content` preservation đã có source/test coverage theo release process.
- Historical upgrade fixture chỉ trở thành evidence khi hosted run trên exact candidate ZIP pass.
- Clean install, real-database historical upgrade, network lifecycle và restore-from-backup rollback vẫn cần release-environment evidence.
- User-authored `post_content` không được xóa/rewrite bởi uninstall/migration.

## Accessibility

- Axe automation có thể được cấu hình cho critical scope nhưng chưa chạy thì không được claim pass.
- Keyboard-only, focus intent, reduced motion, 200%/400% zoom, RTL, forced colors, NVDA và VoiceOver vẫn cần evidence/manual review tương ứng.
- Không claim screen-reader certification nếu chưa có bằng chứng.

## Performance

- Benchmark framework có thể đo document 50/200/500 node cho editor load, selection, Inspector, Settings và save.
- Anti-freeze ceiling không phải regression baseline chính thức nếu controlled run chưa thiết lập baseline.
- Frontend asset-count/conditional-enqueue cần release evidence.

## Commercial operations

- Automatic update, rollback package, support policy, security response và release signing infrastructure còn là gate riêng.
- Security/privacy/upgrade policy có thể đã được package nhưng vẫn cần final release review/operational owner.
- Nếu provenance ghi `signed: false` thì không được claim signing.
- Stable `1.0.0` bị chặn bởi mọi P0 chưa giải quyết trong `COMMERCIAL_HARDENING.md` và `RELEASE_CHECKLIST.md`.
