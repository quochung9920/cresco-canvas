# Lịch sử, audit và báo cáo của Cresco Canvas

> **Mục đích:** giúp đọc các tài liệu lịch sử bằng tiếng Việt mà không nhầm chúng với kiến trúc/trạng thái hiện tại.
>
> **Nguyên tắc:** số liệu, commit SHA, version, status và kết luận trong tài liệu lịch sử thuộc về đúng thời điểm của tài liệu đó. Không được “cập nhật cho mới” bằng cách sửa ngược bằng chứng cũ.

---

## 1. Quy tắc quan trọng nhất

Khi tài liệu lịch sử mâu thuẫn với code hoặc canonical docs hiện tại, dùng thứ tự authority trong `PROJECT_RULES.md` và `docs/README.md`.

Nói ngắn gọn:

```text
main code/tests hiện tại
-> current ADR
-> PROJECT_RULES.md
-> current canonical docs
-> historical/compatibility docs trong đúng scope cũ
```

Một file lịch sử có thể hoàn toàn đúng ở ngày nó được viết nhưng không còn đúng cho `main` hôm nay.

---

## 2. Kiến trúc cũ

### `ARCHITECTURE.md`

Đây là kiến trúc Gutenberg-native cũ, khi Gutenberg chuẩn được xem là Page editor duy nhất và Cresco hoạt động như extension/sidebar/native block layer.

File đã được Việt hóa để dễ đọc, nhưng **không dùng làm runtime ownership contract của Cresco Studio hiện tại**.

Đọc file này khi cần hiểu:

- lịch sử trước Studio;
- migration từ Gutenberg-native;
- lý do tồn tại một số compatibility path;
- source/ADR cũ có assumption “không có custom Page editor”.

Với Studio hiện tại, đọc `CORE_ARCHITECTURE.md` và `STUDIO_RUNTIME_OWNERSHIP_AND_CONFLICT_PREVENTION.md`.

---

## 3. Roadmap cũ

### `ROADMAP.md`

File này tự ghi rõ **stale từ 2026-08-14**.

Roadmap từng ghi nhiều capability là `MISSING` hoặc `NOT APPLICABLE`, nhưng audit sau đó xác nhận nhiều capability đã thực sự tồn tại, được register và render. Vì vậy:

- không dùng readiness percentage trong file cho release decision hiện tại;
- không dùng status cũ để kết luận một feature chưa tồn tại;
- có thể dùng để hiểu thứ tự milestone và kế hoạch ban đầu.

Khi `ROADMAP.md` và `AUDIT_2026-08.md` mâu thuẫn trong scope lịch sử đó, audit mới hơn là evidence tốt hơn cho mốc thời gian được audit.

---

## 4. Baseline audit

### `BASELINE_AUDIT.md`

Audit ban đầu ngày **2026-08-03**.

Baseline được ghi nhận:

- audited commit `7e56722e76138b9b08af5ee5d8bc2b02789e77d9`;
- plugin version `0.1.1`;
- repository lúc đó còn rất nhỏ và thiếu build/test/release/migration infrastructure hiện đại;
- audit ghi lại các P1/P2 của editor takeover, stale overwrite, CSS scope, lifecycle và test foundation;
- phần re-audit 2026-08-04 ghi lại quá trình chuyển sang Gutenberg extension ở milestone 0.3.

Đây là evidence về **điểm xuất phát**, không phải mô tả kiến trúc hiện tại.

---

## 5. Audit 2026-08-14

### `AUDIT_2026-08.md`

Audit này so sánh `ROADMAP.md` với code thực sự boot ở thời điểm đó.

Các kết luận chính của audit tại thời điểm ghi nhận:

- roadmap đánh giá thiếu nhiều feature đã tồn tại;
- rủi ro lớn không chỉ là feature thiếu mà là runtime/build/quality ownership bị chồng lớp;
- audit chỉ ra nhiều `website-builder-*`, renderer, PHP service và CSS override layer cạnh tranh;
- audit ghi lại một lỗi build từng làm mất behavior của committed artifacts và phần remediation sau đó;
- quality gate tại baseline lịch sử chưa thể được gọi là green;
- release label phải đi theo evidence chứ không theo feature count.

Phần sau của file còn ghi lại hardening work và blocker của chính sprint đó. Không biến các blocker cũ thành blocker hiện tại nếu chưa kiểm tra lại `main`.

---

## 6. Ollama Super Upgrade audit

### `OLLAMA_SUPER_UPGRADE_AUDIT.md`

Audit này bắt đầu từ branch `ollama-super-upgrade-20260812`, starting SHA `ceeb120e0e3c367ae977b34892543f5797f5d55d`.

Mục tiêu của file là ghi nhận **ownership và competing authority**, không phải feature count.

Nó ghi lại các hướng hợp nhất quan trọng như:

- runtime owner và module registry;
- canonical Session boundary;
- browser `crescoDocumentStore`;
- `CommandBus`/transaction mutation direction;
- `WordPressDocumentRepository` và concurrency guard;
- responsive/style provenance;
- renderer/core widget parity;
- security/diagnostics boundaries.

Một số “remaining work” trong file có thể đã được xử lý sau đó. Luôn kiểm tra current source trước khi hành động.

---

## 7. Commercial readiness cũ

### `COMMERCIAL_READINESS.md`

Assessment ngày **2026-08-04**, version `0.3.0-alpha.1`.

Tài liệu kết luận alpha đó **chưa commercially ready** và ghi weighted product-scope readiness `44.7%` theo matrix cũ. Cả tám release gates lúc đó đều `NOT VERIFIED`.

Không dùng con số `44.7%` để đánh giá release candidate hiện tại; nó chỉ có ý nghĩa với baseline/date được ghi trong file.

### `UPGRADE_2026-08-COMMERCIAL.md`

Là upgrade report/plan dựa trên baseline của thời điểm được ghi trong file. Dùng để hiểu hardening direction và lịch sử quyết định; không dùng làm current release evidence nếu commit/artifact không khớp.

---

## 8. Security threat model cũ

### `SECURITY_THREAT_MODEL.md`

Last reviewed **2026-08-04 cho milestone 0.3**.

Threat model tập trung vào kiến trúc Gutenberg-native lúc đó: Page content/meta, global settings, REST nonce, lifecycle, artifact integrity và dependency risk.

`SECURITY.md` hiện tại có authority cao hơn cho release/security contract hiện hành.

Không được lấy route inventory hoặc ownership assumption trong threat model cũ để phủ định current Studio REST/runtime.

---

## 9. AI context compatibility

### `CRESCO_AI_CONTEXT_V1.md`

`cresco-ai-context/v1` là compatibility profile. Nó mô tả portable design-only envelope, scope `page/subtree/widget/selection`, contract dependencies, visual context tùy chọn và security boundary.

### `CRESCO_AI_CONTEXT_V2.md`

V2 bổ sung One-Shot authoring package, `creationCatalog`, `designSystem.available`, `returnContract.template` và visual context mặc định phù hợp hơn cho create/redesign.

### `CRESCO_AI_CONTEXT_V3.md`

Đây là default one-shot AI context hiện hành theo docs hiện tại.

Khi sửa compatibility parser/endpoint, V1/V2 vẫn quan trọng. Khi thiết kế workflow mới mặc định, ưu tiên V3 và current interchange contracts.

---

## 10. System/status reports

### `CRESCO_CANVAS_SYSTEM_REPORT.md`

Báo cáo hệ thống tại `main` mốc `4cc0b954d3065ed8e500c54e3054bb6426f90fc2`, ngày 2026-08-15, plugin `1.0.0-rc.1`.

File này đã có nội dung tiếng Việt và rất hữu ích để onboarding, nhưng vẫn là snapshot theo commit/date. Dùng canonical docs cho ownership cuối cùng nếu code đã thay đổi sau snapshot.

### `DEVELOPMENT_CONTINUATION_REPORT.md`

Report của branch `refactor/runtime-consolidation`, base `d44d289f27b99ba42586f9ac801d10d8f468f3f1`, ngày 2026-08-11.

File ghi lại hướng runtime consolidation: shared runtime context, asset contract, editor config, module registry, startup ownership, diagnostics và các phase tiếp theo. Dùng để hiểu vì sao nhiều owner hiện tại được hợp nhất như vậy.

### `EXECUTION_STATUS.md`

Status report của một execution phase. Dùng để kiểm tra những gì đã được claim ở phase đó, không dùng làm current source of truth nếu source đã tiếp tục đổi.

### `FINALIZATION_STATUS.md`

Snapshot finalization ngắn của một mốc phát triển. Giữ nguyên ý nghĩa theo thời điểm.

---

## 11. Release notes lịch sử

Các file trong `docs/releases/` là release note của đúng phiên bản:

- `0.7.0-alpha.1.md`
- `0.8.0-alpha.1.md`
- `0.8.0-alpha.2.md`
- `0.8.0-alpha.3.md`
- `0.8.0-alpha.4.md`
- `0.8.0-alpha.5.md`
- `0.8.0-rc.1.md`
- `0.9.0-alpha.2.md`
- `0.9.0-alpha.3.md`
- `0.9.0-rc.1.md`
- `1.0.0-rc.1.md`

Release note chỉ mô tả release tương ứng. Không sửa một release note cũ để phản ánh feature mới hơn.

Nếu cần biết behavior hiện tại, kiểm tra `CHANGELOG.md`, current source và current canonical docs.

---

## 12. Machine-facing historical prompt

### `CODEX_MASTER_IMPLEMENTATION_PROMPT.md`

Đây là prompt lịch sử/machine-facing rất dài. Không nên dịch mù quáng toàn bộ literal instruction nếu việc đó có thể thay đổi semantic của command, schema hoặc agent behavior.

Khi người đọc cần hiểu file này:

- xem nó như historical product specification/implementation prompt;
- không coi mọi instruction trong đó là canonical nếu đã có ADR/code mới hơn;
- giữ nguyên schema, route, command, path và code literal;
- dùng current `PROJECT_RULES.md` để quyết định cách agent phải làm việc hôm nay.

---

## 13. Cách dùng tài liệu lịch sử an toàn

Trước khi lấy một kết luận từ historical doc để sửa code:

```text
[ ] Xác định date/version/commit của tài liệu.
[ ] Kiểm tra file có tự ghi stale/superseded không.
[ ] Đối chiếu current registration/source trên main.
[ ] Đối chiếu current ADR và canonical docs.
[ ] Chỉ giữ phần historical claim trong đúng scope của nó.
[ ] Nếu cần current assessment, tạo evidence mới thay vì sửa ngược lịch sử.
```

Mục tiêu của việc giữ lịch sử là giúp hiểu **vì sao kiến trúc trở thành như hiện tại**, không phải tạo thêm một source of truth cạnh tranh.