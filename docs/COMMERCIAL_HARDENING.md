# Commercial Hardening cho 1.0

Tài liệu này là kế hoạch chuyển Cresco Canvas từ `1.0.0-rc.1` thành stable `1.0.0` được hỗ trợ thương mại.

## Quy tắc release

Không tag/market `1.0.0` cho tới khi mọi P0 có objective evidence. Source có mặt hoặc checked-in runtime không đồng nghĩa gate đã pass.

## P0 blockers

### Source và build integrity

- [ ] Mọi checked-in runtime có authoritative source owner.
- [ ] Clean checkout chạy được `npm ci`, `composer install --no-dev --optimize-autoloader`, `npm run build`.
- [ ] Xóa `build/` rồi rebuild tái tạo required runtime.
- [x] Release ZIP dùng allowlist và chứa runtime/control asset cần thiết theo manifest hiện hành.
- [ ] Hai clean package build có cùng SHA-256.
- [ ] Không cần manually edited generated file để feature hoạt động.

### Inspector và rendering integrity

- [ ] Mọi visible Inspector control có verified editor/frontend output trên supported WordPress matrix.
- [ ] Dimension/unit, Border/Radius, responsive/state và Typography popup phải có save/reload evidence, không chỉ source presence.
- [x] Generic structured style path có sanitized renderer/compiler coverage theo current architecture.
- [ ] Save/reload/copy/duplicate/undo/redo/revision/component flow preserve setting trong runtime test phù hợp.
- [ ] Invalid/legacy style data được test bằng historical fixture thực.

### Security

- [ ] REST route có documented auth/capability/rate/payload boundary.
- [ ] Upload pass extension/MIME/size/executable/polyglot/ownership/download review.
- [ ] Webhook chặn private/metadata/DNS-rebinding target theo production-like test.
- [ ] CSV neutralize spreadsheet formula.
- [ ] Dynamic query/facet có bounded cost/cache/invalidation.
- [ ] Import/export không inject executable content.
- [ ] Form/diagnostics/webhook log không expose sensitive value.

### Lifecycle và data

- [x] Settings schema 4 migration + pre-migration backup tồn tại.
- [x] Uninstall inventory bao phủ known Cresco ownership.
- [ ] Clean install/activation pass.
- [ ] Historical upgrade fixture pass trên real database.
- [ ] Downgrade detection/rollback guidance được verify.
- [ ] Single-site/multisite lifecycle pass.
- [ ] User-authored `post_content` không bị xóa.

### Compatibility

- [ ] Minimum/latest/latest-minus-one WordPress pass.
- [ ] PHP 8.1/8.2/8.3/8.4 pass.
- [ ] Block/classic theme smoke pass.
- [ ] Supported editor/document routes pass.
- [ ] Chrome/Firefox/WebKit/Edge pass.
- [ ] ACF/WooCommerce/multisite/cache/optimization smoke pass.

### Accessibility

- [ ] Keyboard-only pass.
- [ ] NVDA/VoiceOver smoke pass.
- [ ] 200%/400% zoom pass.
- [ ] RTL/forced-colors pass.
- [ ] Reduced motion được tôn trọng.
- [ ] Interactive/modal/form focus & announcement pass.
- [ ] axe không có serious/critical violation.

### Performance

- [ ] Editor được đo với 50/200/500 node.
- [ ] Selection/Inspector interaction nằm trong evidence-based budget.
- [ ] Frontend asset chỉ load khi cần.
- [ ] Observer/subscription scoped/debounced/cleanup đúng.
- [ ] Dynamic loop/facet benchmark representative dataset.
- [ ] Form/upload/email/webhook workload được tách khỏi response path khi phù hợp.

### Documentation và release operation

- [x] README mô tả scope `1.0.0-rc.1`.
- [ ] Architecture docs khớp current service/editor shell.
- [x] Known limitations evidence-based.
- [ ] Changelog đầy đủ.
- [ ] Upgrade/rollback/privacy/security/support policy publish.
- [ ] Release ZIP/checksum/SBOM/provenance được tạo.
- [ ] Clean WordPress install exact ZIP thành công.
- [ ] Beta/RC feedback không còn unresolved P0/P1.

## P1 commercial quality

- [ ] Unified Studio shell/ownership rõ và không duplicate surface.
- [ ] Responsive inheritance/reset-to-global indicator hoàn chỉnh.
- [ ] Global token preview/validation/preset/contrast/usage/reset hoàn chỉnh theo scope ship.
- [ ] Safe mode/conflict diagnostics khả dụng.
- [ ] Privacy-safe system status có thể copy.
- [ ] Form delivery history/retry/download permission hoàn chỉnh.
- [ ] Translation/localization review hoàn chỉnh.
- [ ] Automatic update/release channel/rollback behavior được định nghĩa.

## Deferred sau 1.0

Cloud library, marketplace, collaboration, white-label, advanced animation timeline và broad CRM catalog không chặn stable 1.0 trừ khi release scope được thay đổi bằng ADR/plan mới.
