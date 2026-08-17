# Release Engineering — Cresco Canvas 1.0

Tài liệu này định nghĩa commercial release evidence pipeline. Đây là operational documentation, **không phải tuyên bố RC hiện tại đã pass**.

## Status vocabulary

Mọi gate phải dùng một trong:

- `PASS`: gate đã chạy thành công trên đúng commit/artifact đang đánh giá.
- `FAIL`: gate đã chạy và phát hiện product/release failure.
- `NOT RUN`: chưa có execution evidence.
- `SKIPPED`: cố ý bỏ qua và có lý do.
- `INFRA FAILURE`: runner/service/browser/Docker/registry/workflow lỗi trước product assertion.
- `MANUAL REQUIRED`: automation không đủ, cần human verification record.

`configured`, `source present`, `workflow exists`, `check was skipped` **không bao giờ đồng nghĩa `PASS`**.

## Authoritative trees

| Layer | Authoritative location | Output | Verification |
| --- | --- | --- | --- |
| TypeScript/React bundle | `src/` | `build/` | `npm run build` + `check:build-integrity` |
| Reviewed standalone runtime | `runtime-src/build/` | `build/` | byte-for-byte SHA-256 parity |
| WordPress/PHP runtime | `includes/`, `blocks/`, bootstrap | release ZIP | strict allowlist |
| Authored CSS | `assets/css/`, `blocks/**/*.css` | release ZIP | CSS lint + allowlist |

`runtime-src/build/` là transitional source tree vì nhiều standalone runtime lịch sử từng chỉ sống trong `build/`. `build/` phải có thể xóa/rebuild; direct edit mới vào `build/` không phải source workflow được chấp nhận nếu có canonical mirror.

## Clean source build

```bash
npm ci
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
rm -rf build
npm run build
npm run check:build-integrity
```

Gate pass cần:

1. production `build/*` có owner trong manifest/ownership contract;
2. rebuilt `build/` không drift khỏi committed production runtime theo rule hiện hành.

## Quality commands

```bash
npm run typecheck
npm run lint:php
npm run lint:runtime
npm run lint:js
npm run lint:css
npm run lint:md
npm run test:unit
npm run test:php
npm run check:hygiene
npm run check:editor-runtime
npm run check:website-builder
npm run check:runtime-modules
npm run check:studio
npm run check:studio-motion
npm run check:build-integrity
npm run check:version
npm run package
node scripts/verify-package.mjs
```

PHPCS/E2E/matrix/ZIP-install/upgrade/accessibility/performance chạy trong release workflow hoặc môi trường tài liệu hóa tương đương.

## Production package

`npm run package` tạo các artifact theo release script, điển hình:

- `dist/cresco-canvas-<version>.zip`
- `dist/SHA256SUMS`
- SPDX inventory
- provenance JSON

ZIP dùng strict allowlist. Dev test/source/release tooling/local env/VCS/temp/source map ngoài policy phải bị reject.

Archive cần deterministic normalization để reproducibility có thể kiểm chứng.

Provenance ghi commit/ref, builder, Node version và artifact digest. Nếu signing chưa configured thì phải ghi rõ, không fake signing claim.

## Reproducibility

Commercial evidence cần hai independent clean checkout tạo cùng ZIP SHA-256. Same-workspace double build chỉ là fast diagnostic.

## Runtime matrix

Matrix được duy trì trong `COMPATIBILITY_MATRIX.md`. Mỗi cell phải clean activate và chạy critical smoke theo release contract; chỉ install thành công là chưa đủ.

Browser smoke gồm Chromium, Firefox, WebKit; Edge có thể vẫn `MANUAL REQUIRED` nếu không có dedicated runner.

## Critical E2E

Critical flow nên exercise:

- login + Studio open;
- widget insertion/editing;
- responsive/state control liên quan;
- Inspector/Structure/Page/Global critical surfaces;
- save/reload;
- preview;
- undo/redo/history;
- AI import entry point;
- no-freeze khi đổi critical surface.

Exact-release installation có test riêng với versioned ZIP trên clean isolated WordPress. Source-checkout E2E không thay P0 exact-ZIP gate.

## Upgrade evidence

Upgrade smoke dùng pinned historical fixture + exact candidate ZIP, chạy migration và verify settings/session preservation. Đây là release fixture, không phải hứa hỗ trợ mọi development snapshot lịch sử.

## Accessibility evidence

Axe automation có thể fail serious/critical violation, nhưng không tự chứng minh:

- keyboard-only/focus order;
- reduced motion;
- 200%/400% zoom;
- RTL;
- forced colors;
- NVDA;
- VoiceOver;
- dedicated Edge smoke.

Manual item không được đổi sang `PASS` nếu thiếu human record.

## Performance evidence

Benchmark 50/200/500 node record editor load, selection, Inspector/Settings switching và save. Initial ceiling chỉ anti-freeze; first controlled run mới thiết lập baseline.

## Integration smoke

Automated release smoke có thể cover ACF/WooCommerce/multisite. Object/page cache, CDN/proxy, security plugin, optimization/minification vẫn `MANUAL REQUIRED` cho tới khi có repeatable infrastructure.

## Stable-version guard

`npm run check:version` chạy stable-release guard. Prerelease được phép khi chưa có stable approval record; stable `1.0.0` phải có release evidence bound đúng commit + artifact SHA-256 và required P0 status.

Guard chỉ ngăn accidental version flip; nó không tự tạo evidence.

## CI truth và release decision

Commercial workflow/job chỉ là authority khi thực sự chạy trên đúng commit/artifact. P0 aggregate phải không dùng `continue-on-error` để che failure.

`COMMERCIAL 1.0 READY` chỉ hợp lệ khi toàn P0 automated + manual + exact ZIP evidence hoàn tất. Nếu không, conclusion phải là `NOT READY` hoặc `RC READY` đúng với evidence.
