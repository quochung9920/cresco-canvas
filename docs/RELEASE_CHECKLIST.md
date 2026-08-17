# Checklist Release — 1.0.0

Checklist này áp dụng cho `1.0.0-rc.1` và stable `1.0.0` tương lai. Source tồn tại hoặc checkbox source-level được tick **không phải bằng chứng runtime gate đã pass**.

## Version và documentation

- [x] Plugin/package version là `1.0.0-rc.1`.
- [x] README phản ánh product scope hiện tại.
- [x] Có commercial hardening plan.
- [x] Security/privacy/upgrade-rollback policy nằm trong production ZIP allowlist.
- [ ] Changelog hoàn chỉnh tới release commit.
- [ ] Architecture và known limitations khớp current code.
- [ ] Không docs nào claim stable/commercial readiness trước approval.

## Data và migration

- [x] Settings schema version là 4.
- [x] Schema 4 migration sanitize fluid settings và tạo pre-migration backup.
- [x] Uninstall mặc định preserve data.
- [x] Opt-in uninstall cleanup bao phủ Cresco schedules/settings/submissions/uploads/metadata đã biết.
- [ ] Clean install/activation pass trên WordPress thực.
- [ ] Historical upgrade fixtures pass.
- [ ] Migration failure/retry pass trong release environment.
- [ ] Downgrade/rollback được exercise trên real DB/backup.
- [ ] Single-site/multisite lifecycle pass.

## Build và packaging

- [ ] `package-lock.json` hợp lệ và `npm ci` pass từ clean checkout.
- [ ] Composer install tạo optimized release autoloader.
- [ ] Checked-in runtime có authoritative source owner.
- [ ] Xóa `build/` rồi rebuild tái tạo required runtime.
- [ ] TypeScript, lint, JS unit, PHP syntax/PHPCS/PHPUnit pass.
- [x] `check:production-hardening` thuộc `check:quality` và bảo vệ critical source contract.
- [ ] Hai clean package build cho cùng checksum.
- [ ] Review release ZIP + SHA-256.
- [ ] Exact release ZIP install/activate trên clean site.

## Studio editor và persistence

- [ ] Canonical Studio runtime mở đúng trên supported document flow.
- [ ] Không có competing legacy runtime/root.
- [ ] Structure, Canvas, Inspector, Page/Global surface dùng đúng canonical owner.
- [ ] Visible control có matching model/render path; unsupported control bị hide/reject thay vì silently ignored.
- [ ] Dimension/unit, Border/Radius, Typography popup và state/responsive control sống qua save/reload + frontend render.
- [ ] Save/reload, undo/redo, autosave, revision, clipboard, duplicate, locking/component flow pass theo scope hỗ trợ.
- [ ] Legacy-compatible content không bị phá ngoài migration contract.

## Feature smoke

- [ ] Global Design save/reset/import/export và token flow pass.
- [ ] Template/component/site-kit flow pass.
- [ ] Theme Builder display condition pass.
- [ ] Dynamic field/loop/filter/facet/load-more/history sync pass khi feature được ship.
- [ ] Interaction pass keyboard/reduced-motion review.
- [ ] Forms pass validation, conditional logic, upload, CAPTCHA adapter, storage, export, email, webhook theo scope ship.

## Security và privacy

- [x] REST route inventory/public-route intent được document và có source-level regression gate.
- [x] Upload/webhook/CSV/privacy/migration/downgrade/uninstall invariant có source coverage.
- [ ] REST permission review pass trên exact artifact/environment.
- [ ] Upload security pass real web-server hostile-file/storage/download test.
- [ ] Webhook SSRF/DNS/TOCTOU/retry/log/secret review pass production-like egress test.
- [ ] CSV formula injection và bounded large-export pass integration test.
- [ ] Query/facet cost/cache behavior verified dưới realistic load.
- [ ] Import/export và CSS/token sanitizer verified với hostile payload.
- [ ] Privacy exporter/eraser/retention/uninstall pass trên real WordPress data.
- [ ] Log/diagnostics không leak private data/secret.

## Compatibility

- [ ] WordPress minimum/latest-minus-one/latest pass.
- [ ] PHP 8.1/8.2/8.3/8.4 pass.
- [ ] Block + classic theme pass.
- [ ] Chrome/Firefox/WebKit-Safari/Edge critical flow pass.
- [ ] ACF/WooCommerce pass.
- [ ] Multisite/cache/security/optimization smoke pass theo matrix.

## Accessibility và performance

- [ ] Keyboard-only, NVDA, VoiceOver, RTL, forced-colors, 200%/400% zoom, reduced-motion review pass.
- [ ] axe không có serious/critical violation ở critical flow.
- [ ] Modal/off-canvas focus management pass.
- [ ] Slider/AJAX announcement pass nếu feature được ship.
- [ ] Form error summary/focus pass.
- [ ] 50/200/500-node editor performance được đo.
- [ ] Frontend asset conditional loading và performance budget pass.

## Commercial release approval

- [ ] Không còn unresolved P0.
- [ ] P1 được fix hoặc accepted có owner/date.
- [ ] Beta/RC validation hoàn tất.
- [ ] Support/update/rollback/privacy/security policy được publish.
- [ ] Human reviewer approve đúng release commit + artifact.
- [ ] Chỉ đổi `1.0.0-rc.1` sang `1.0.0` sau khi required gate pass.
