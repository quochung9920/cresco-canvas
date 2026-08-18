# Baseline Audit — bản dịch tiếng Việt

> **Tài liệu lịch sử.** Ngày audit: **2026-08-03** (`Asia/Ho_Chi_Minh`).
>
> Các commit SHA, version, severity và trạng thái bên dưới thuộc đúng baseline được audit; không dùng chúng như trạng thái hiện tại nếu `main` đã thay đổi.

## Trạng thái repository đã verify

Audit dùng private GitHub repository `quochung9920/cresco-canvas` làm source of truth.

| Hạng mục | Baseline đã verify |
| --- | --- |
| Default branch | `main` |
| Audited commit | `7e56722e76138b9b08af5ee5d8bc2b02789e77d9` |
| Plugin version | `0.1.1` |
| Production branches | `main`; documentation branch `docs/codex-master-roadmap` |
| Open pull requests | PR #1, documentation-only, target `main` |
| Tags/releases | Chưa có product release tag hoặc release evidence |
| Tracked production files | 14 files: một bootstrap, năm PHP service files, ba editor assets và một Container block directory |

Trước khi triển khai, audit đã kiểm tra toàn bộ tracked files trên `main`, recent commit history, branches và open PR. Master implementation prompt được đọc từ `docs/codex-master-roadmap` và được dùng như product specification duy nhất tại thời điểm đó.

## Behavior của baseline

- Pages có custom Canvas submenu và một monolithic JavaScript editor.
- Toàn bộ Page edit links bị thay bằng Canvas links, không có configurable preference hoặc signed recovery path.
- Custom REST route load/save Page content nhưng không có concurrency token.
- `cresco/container` lưu native block markup và vẫn readable khi plugin bị disable.
- Global settings tạo CSS variables nhưng frontend CSS target unscoped `body` và mọi `.wp-block-button__link`.
- Frontend assets load trên các page không liên quan.
- Chưa có package manifest, Composer manifest, lock file, build pipeline, automated test suite, CI workflow, migration runner, feature flag system, lifecycle policy, release script, changelog hoặc engineering documentation.

Không thể chạy baseline command suite vì repository khi đó chưa định nghĩa suite nào. **Sự vắng mặt của test suite là finding, không phải pass.**

## Findings và cách xử lý

| Severity | Finding | Baseline evidence | Cách xử lý milestone 0.2 |
| --- | --- | --- | --- |
| P0 | Không reproduce được | Full tracked-source và history audit | Không còn known P0 trong scope; vẫn cần broader validation |
| P1 | Stale Canvas session có thể âm thầm ghi đè Page content mới hơn | Save route nhận content mà không có persisted-state precondition | Sửa bằng exact revision tokens và same-second conflict regression test |
| P1 | Cresco styles ảnh hưởng unrelated site output | Unconditional frontend enqueue + unscoped `body`/global button selectors | Sửa bằng Canvas-page detection, body scoping, conditional enqueue và E2E assertions |
| P2 | Mọi Page edit link bị takeover, không có user preference/reliable bypass | Unconditional `get_edit_post_link` filter | Sửa bằng global/per-Page/remember choices, explicit row actions, signed bypass và Safe Mode |
| P2 | Thiếu build, type, dependency và release controls | Không có npm/Composer manifests, lock files hoặc packaging scripts | Được thêm trong 0.2; CI verification vẫn cần |
| P2 | Không có automated PHP/JS/browser/accessibility/compatibility/Plugin Check coverage | Không có tests/workflows | Test foundations và matrix workflow được thêm; manual/hosted evidence vẫn tách riêng |
| P2 | Không có migration lock/status/retry evidence/schema version | Chỉ direct option use | Thêm version-one idempotent migration và failure state; rollback matrix chưa verify |
| P2 | Không có activation/deactivation/uninstall safety policy rõ ràng | Không có lifecycle hooks/uninstall file | Thêm runtime checks, data-preserving deactivation và opt-in cleanup |
| P2 | Monolithic untyped editor làm khó cô lập lỗi/thay đổi | Một global `assets/js/editor.js` | Thay bằng typed modules và error boundary |
| P2 | Upstream dev toolchain còn unresolved advisories | Current `@wordpress/scripts` transitive graph | Production audit sạch; dev-only advisories được ghi trong Known Limitations |
| P3 | Product status, architecture, compatibility và recovery chưa được document | README-only MVP | Thêm documentation/changelog bắt buộc |

## Quyết định scope

Trạng thái hoàn tất thật sự mới nhất được audit là pre-roadmap `0.1.1` MVP. Vì vậy milestone 0.2 là milestone chưa hoàn tất tiếp theo.

Branch 0.2 sửa các P1/P2 baseline và chỉ xây **architecture/reliability foundation của 0.2**; cố ý không bắt đầu milestone 0.3 trong cùng scope.

---

## Re-audit milestone 0.3

Ngày re-audit: **2026-08-04** (`Asia/Ho_Chi_Minh`).

Re-audit dùng merged `main` commit `724ad425ae5e578a782942e378852925b29f555f`, version `0.2.0-alpha.1`, làm immutable baseline. PR #1 và #2 đã merge.

Latest Actions run vẫn có `startup_failure` và allocated zero jobs, nên hosted WordPress/runtime claims vẫn chưa có evidence.

### Clean baseline commands trước khi sửa milestone 0.3

| Check | Kết quả |
| --- | --- |
| `npm ci` | `PASS`, kèm documented upstream peer/deprecation warnings |
| TypeScript, JavaScript lint, CSS lint, unit tests, build, version check | `PASS` |
| Markdown lint | `FAIL`: authoritative prompt vừa merge không tuân configured Markdown rules |
| Production npm audit | `PASS`: zero production vulnerabilities |
| Full npm audit | `FAIL`: 30 development-only transitive advisories |
| Native PHP/Composer/WordPress/browser suite | `NOT TESTED`: local environment thiếu runtime cần thiết |

### Finding kiến trúc P1 trung tâm

Normal Page editing bị chia giữa **Edit in Canvas** và **WordPress Editor**, trong khi Cresco tự duplicate Core Page loading, saving, conflict, navigation và recovery trong proprietary shell.

Điều này vừa khó dùng vừa khiến Core autosaves, revisions, locking, document settings, List View, media và standard editor behavior không còn là single source of truth.

Milestone 0.3 vì vậy chuyển sang direct Gutenberg extension:

- normal Edit action không bị thay;
- Page content và revision-enabled Cresco metadata dùng Core save boundary;
- custom Page REST routes và editor-choice data bị retire qua schema version 2;
- site-wide design settings vẫn là custom permissioned data.

Markdown lint được sửa bằng narrow inline exemptions cho intentionally long instruction/numbered top-level section của authoritative prompt; các Markdown rules/docs khác vẫn được check.

Không có Page content/public markup migration. Hosted WordPress, browser, compatibility, accessibility và release-artifact evidence vẫn bắt buộc trước khi milestone được claim complete/production-ready.

---

## Cách sử dụng audit này hiện nay

File này ghi lại **điểm xuất phát và quyết định của 0.1/0.2/0.3**, không mô tả Cresco Studio hiện tại.

Khi một assumption trong audit mâu thuẫn current Studio source/ADR, giữ audit làm historical evidence và dùng current `PROJECT_RULES.md` + canonical docs để quyết định implementation.