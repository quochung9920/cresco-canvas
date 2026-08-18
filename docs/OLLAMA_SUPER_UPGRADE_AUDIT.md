# Ollama Super Upgrade — Audit kiến trúc

> **Tài liệu lịch sử.**  
> Branch: `ollama-super-upgrade-20260812`  
> Starting SHA: `ceeb120e0e3c367ae977b34892543f5797f5d55d`

Audit này ghi lại ownership được phát hiện trong Super Upgrade sprint. Trọng tâm là contract và competing authority, không phải số lượng feature.

## Canonical runtime map tại thời điểm audit

### Studio bootstrap và runtime ownership

- `WebsiteBuilderRuntimeOwner` là primary browser-runtime owner và claim các Studio/consistency/bootstrap handles.
- `WebsiteBuilderStudio` gắn configuration/support assets vào Studio handle hiện có; không tạo editor generation khác.
- `WebsiteBuilderModuleRegistry` định nghĩa core, core-extension, transitional và quarantined modules.
- `WebsiteBuilderSessionIsolation` chặn legacy Session API khỏi canonical Website Builder Page editor.
- Transitional Professional UX / Comprehensive modules vẫn opt-in và không được lấy lại core ownership.

### Document model và validation

- `WebsiteBuilder::sanitize_session()` là canonical Session boundary cho Website Builder documents.
- `Document` cung cấp portable document/session/checksum behavior.
- `PatchValidator` dùng canonical Website Builder sanitizer trước khi trả candidate Session.
- `WidgetCatalog` được share cho REST context, editor, AI contracts và rendering.

### Browser document state

Trước sprint này, React Studio sở hữu Session/history/dirty state trong khi `website-builder-consistency-guard.js` độc lập sở hữu revision/checksum/recovery/in-flight-save state.

Sprint thêm `website-builder-document-store.js` làm canonical browser-side revision/persistence/recovery boundary. Consistency guard chuyển sang delegate revision, document, checksum, save lifecycle, conflict và recovery state cho store thay vì tự tăng revision.

**Remaining integration work tại thời điểm đó:** Studio vẫn sở hữu React Session, selection và immediate undo/redo snapshots. Store đã expose selection/transaction/history APIs và compatibility events, nhưng Studio cần migrate sang gọi trực tiếp các API đó trước khi browser state thật sự single-owner.

### Mutation path

- `CommandBus` là canonical validated mutation vocabulary.
- `TransactionManager` nhóm nhiều command thành một validated candidate/diff/history unit.
- AI patch application phải đi qua validated patch/command flow; không persist trực tiếp.
- Pointer drag là document-movement owner; responsive-properties chỉ UI-only cho drag behavior.

**Remaining integration work:** Studio React runtime khi đó vẫn thực hiện một số local clone/map mutations trước khi emit `cresco:studio-session-change`. Canvas, Structure và Inspector nên migrate dần sang canonical commands thay vì tạo mutation implementation thứ hai.

### Persistence và concurrency

- `WordPressDocumentRepository` là canonical storage adapter và sở hữu persisted checksum/verification helpers.
- `WebsiteBuilderConcurrencyGuard` áp cùng optimistic precondition + mutex cho legacy Session, Website Builder Session và Theme Session writes.
- Thiếu `baseChecksum` fail closed với 428; stale write fail 409; concurrent lock contention fail 423.
- Successful Session response được verify với persisted checksum trước khi coi là thành công.

**Ownership conflict còn lại tại thời điểm audit:** `SessionManager`, `WebsiteBuilder` và history restore paths vẫn có direct post-meta writes. Dù đã nằm sau concurrency boundary, chúng nên hội tụ vào `DocumentRepository::save()` để persistence implementation có một owner.

### History và recovery

- Server revision history vẫn ở `HistoryManager`.
- Browser crash recovery được đại diện trong document store bằng `cresco-recovery/v1` gồm document ID, revision, timestamp, title và Session.
- In-flight save cũ không thể clear recovery snapshot mới hơn vì `markPersisted()` chỉ clear dirty/recovery khi start revision vẫn bằng current revision.

Remaining work là migrate Studio undo/redo sang store transactions để undo, redo, AI apply, component operations và typing/drag gestures dùng cùng revision model.

### Responsive và style resolution

- Canonical breakpoint order: `wide -> desktop -> laptop -> tablet -> mobile`.
- `StyleCascade` resolve sparse overrides qua token/global/component/local layers và report property provenance (`value`, `source`, `breakpoint`, `state`, inheritance và previous explicit breakpoint).
- `StyleCascade::fluid()` cung cấp validated first-class clamp foundation mà UI không cần raw clamp syntax.
- Design Tokens expose semantic colors, typography, space, radius, shadow, containers, transitions và z-index, đồng thời giữ legacy token aliases.

Remaining work là wire provenance/status vào Inspector controls và migrate responsive UI sang một cascade helper thay vì local re-derive inheritance.

### Renderer và core widgets

- `RenderEngine` route canonical Session qua `WebsiteRenderer` và renderer parity completion.
- Text rich content được sanitize bằng WordPress allow-list.
- Button new-tab links normalize `noopener noreferrer` an toàn.
- Image rendering mặc định lazy loading và async decoding.
- Container layout props compile qua cùng frontend CSS compiler với canonical renderer.

Các gap cần đóng **đồng thời catalog + sanitizer + renderer + Inspector**:

- semantic `article` container support;
- richer image decorative/loading/priority/object-position contract;
- button accessible-label/disabled foundation;
- deeper Divider/Icon/Spacer responsive controls.

Không thêm Inspector control trước khi frontend parity tồn tại.

### Accessibility và design diagnostics

`DocumentDiagnostics` thực hiện non-mutating document checks với locatable `nodeId`/path output cho:

- nhiều H1 headings;
- heading-level skip;
- image thiếu alt trừ khi explicit decorative;
- button thiếu accessible name;
- nested layout quá sâu;
- quá nhiều local styles;
- redundant responsive overrides;
- thông tin safe new-tab rel normalization.

Remaining work: expose kết quả trong một Inspector/diagnostics surface và thêm keyboard/axe browser coverage khi WordPress test environment khả dụng.

### Security

Hardening được giữ gồm:

- canonical Session validation và node bounds;
- allow-listed custom CSS values/scoping;
- safe rich-text rendering và temporary Studio preview sanitization;
- URL sanitization;
- REST capability checks;
- optimistic concurrency + write verification;
- không thêm direct Session POST fallback cho extensions.

## Các ownership defect ưu tiên cao tại thời điểm audit

1. Studio React local mutation/history cần migrate sang `crescoDocumentStore` + `CommandBus`, không tiếp tục là parallel mutation owner.
2. Direct Session post-meta writes trong legacy/canonical/history services cần hội tụ vào `DocumentRepository`.
3. Selection phải emit/consume qua store; không infer bằng MutationObserver hoặc DOM surgery.
4. Responsive Inspector phải consume `StyleCascade` provenance thay vì duplicate inheritance rules.
5. Core widget catalog changes phải ship cùng sanitizer + frontend renderer parity trong cùng milestone.

## Performance observations

Studio khi đó vẫn dùng recursive tree helpers cho một số mutation/selection. Với hard document limit 1000 nodes, chi phí được bounded, nhưng repeated full-tree clone/map trong high-frequency typing/drag vẫn là structural performance target.

Ưu tiên indexed node lookup và transaction batching trước khi thêm virtualization.

## Trạng thái test environment

GitHub Actions đã tạo run cho branch nhưng job không start. Check annotation của GitHub ghi account bị lock do billing issue. Vì vậy run đó **không phải evidence pass hoặc fail của code**.

Local shell trong session audit không resolve được `github.com`, nên không thể chạy full checkout-based npm/PHP/Playwright suite.

JavaScript syntax checks đã chạy cho document-store mới và consistency-guard được sửa trước khi publish. Repository quality gates được tăng cường để chạy khi CI capacity trở lại.

## Cách dùng audit này hiện nay

File này rất hữu ích để hiểu quá trình hợp nhất runtime/state/persistence ownership. Tuy nhiên mọi “remaining work” ở trên là finding của đúng branch/SHA được ghi đầu file.

Trước khi thực hiện một finding cũ, kiểm tra current `main`, current `PROJECT_RULES.md` và current ownership docs để biết nó đã được xử lý hay chưa.