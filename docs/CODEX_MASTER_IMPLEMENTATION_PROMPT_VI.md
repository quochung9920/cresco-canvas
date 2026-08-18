# Hướng dẫn tiếng Việt cho `CODEX_MASTER_IMPLEMENTATION_PROMPT.md`

> **Mục đích:** giúp người đọc hiểu prompt machine-facing cũ mà không phải đọc toàn bộ literal instruction tiếng Anh.
>
> **Quan trọng:** file gốc `CODEX_MASTER_IMPLEMENTATION_PROMPT.md` được giữ nguyên tiếng Anh vì nó là machine-facing artifact; dịch literal instruction có thể làm thay semantic của agent command. Với code hiện tại, `PROJECT_RULES.md`, current source/tests và current ADR/canonical docs có authority cao hơn khi prompt lịch sử mâu thuẫn với kiến trúc đã tiến hóa.

---

## 1. Prompt gốc là gì?

`CODEX_MASTER_IMPLEMENTATION_PROMPT.md` được viết như một “single complete implementation prompt” để giao toàn bộ roadmap Cresco Canvas cho coding agent.

Ý tưởng của prompt:

- agent tự inspect repository thực tế;
- không tin version/roadmap nếu code/evidence không khớp;
- tiếp tục từ milestone chưa hoàn tất;
- implement behavior thật, không dừng ở plan/mock/placeholder;
- dùng WordPress Core khi Core đã có capability tốt;
- làm từng scope reviewable;
- chạy tests/checks;
- self-review security/accessibility/performance/data safety;
- không auto-merge;
- sau human review/merge có thể chạy lại cùng prompt để tiếp tục.

Đó là ý tưởng tốt về **execution discipline**, nhưng một số assumption kiến trúc trong prompt thuộc thế hệ cũ và đã bị supersede bởi Studio/Core hiện tại.

---

## 2. Những phần vẫn có giá trị như nguyên tắc

Các nguyên tắc sau vẫn phù hợp với `PROJECT_RULES.md` hiện tại:

1. Đọc source/repository state trước khi giả định.
2. Verify feature từ code + render/registration + evidence, không chỉ từ docs.
3. Nếu feature partial/broken thì hoàn thiện hoặc sửa thật, không chỉ thêm placeholder.
4. Reuse WordPress/Core capability khi phù hợp thay vì clone vô ích.
5. Implementation phải reviewable, testable và có regression coverage.
6. Security, accessibility, performance, destructive workflow và upgrade path là acceptance criteria thật.
7. Không claim production/stable/commercial readiness nếu chưa có exact evidence.
8. Một task lớn phải chia thành coherent scope thay vì một PR khổng lồ khó review.

---

## 3. Những assumption lịch sử không được dùng để override kiến trúc hiện tại

Prompt gốc chứa các phát biểu được viết khi product architecture còn dựa mạnh vào Gutenberg-native direction, ví dụ các principle quanh:

- Gutenberg/Page editor là editor authority duy nhất;
- `post_content` native block markup là visual document authority chính;
- không có custom Page editor/runtime riêng;
- roadmap milestone/status tại thời điểm prompt được viết.

Cresco Canvas hiện đã có Studio-owned Website Builder runtime và `cresco-session/v1` editable document model cho Cresco documents.

Vì vậy khi prompt gốc và current architecture mâu thuẫn, dùng:

```text
current code/tests
-> current ADR
-> PROJECT_RULES.md
-> STUDIO_RUNTIME_OWNERSHIP_AND_CONFLICT_PREVENTION.md
-> CORE_ARCHITECTURE.md/current feature contracts
-> historical prompt trong scope cũ
```

Không dùng prompt gốc để quay kiến trúc hiện tại về thế hệ Gutenberg-native.

---

## 4. Cách dùng prompt gốc an toàn với AI Coding Agent hôm nay

Nếu vẫn muốn dùng `CODEX_MASTER_IMPLEMENTATION_PROMPT.md` cho agent, nên prepend instruction hiện hành như sau:

```text
Trước khi thực hiện prompt lịch sử này:
1. Đọc PROJECT_RULES.md và docs/README.md.
2. Đọc current source/tests/registration trên main.
3. Current ADR và canonical Studio/Core docs override mọi historical architecture assumption trong prompt này.
4. Không tạo runtime, Session, render pipeline, responsive engine, Inspector architecture hoặc Page Settings backend thứ hai.
5. Giữ literal schema/route/event/file path hiện tại; không suy đoán từ roadmap cũ.
6. Chỉ dùng phần roadmap của prompt như historical product intent nếu current source/contracts chưa supersede nó.
```

Như vậy agent có thể tận dụng product intent rộng của prompt cũ mà không phá ownership hiện tại.

---

## 5. Execution model của prompt gốc — diễn giải tiếng Việt

Mỗi lần chạy, prompt yêu cầu agent về cơ bản phải:

1. inspect repo/branch/history/PR/workflow/test/docs/dependency/build/version;
2. đọc code liên quan;
3. chạy checks từ clean dependency install nếu environment cho phép;
4. phân loại capability bằng `COMPLETE`, `PARTIAL`, `MISSING`, `BROKEN`, `NOT APPLICABLE`;
5. xác định milestone hoàn tất thật từ reproducible evidence;
6. sửa incomplete/broken work trước;
7. chọn next coherent scope;
8. làm trên dedicated branch;
9. implement production behavior + tests + migration + docs + build outputs;
10. chạy applicable checks;
11. adversarial self-review;
12. fix validated P0/P1 và thêm regression tests;
13. tạo reviewable PR;
14. không auto-merge;
15. report exact evidence;
16. sau human review/merge có thể chạy lại prompt.

Trong repository hiện tại, bước 13/14 có thể được thay bằng direct-main workflow **chỉ khi người dùng yêu cầu rõ**, nhưng vẫn nên triển khai trên branch nhỏ và fast-forward `main` sau verification như `PROJECT_RULES.md` quy định.

---

## 6. Các contract kỹ thuật phải giữ nguyên khi đọc/dịch prompt

Không dịch hoặc đổi literal value của:

- `cresco-session/v1`;
- `cresco-patch/v1`;
- schema name khác;
- JSON key;
- WordPress REST route;
- Cresco REST route;
- class/function name;
- file path;
- event name;
- script/style handle;
- CSS variable;
- command trong code block.

Nếu prompt cũ nêu một literal đã retired, phải verify current code trước khi tái sử dụng.

---

## 7. Tóm tắt cho người không muốn đọc file tiếng Anh

Tinh thần của prompt gốc là:

> “Đừng chỉ lập kế hoạch. Hãy đọc repository thật, tìm phần còn thiếu, triển khai đến mức production-quality, verify bằng tests/evidence và tiếp tục từng milestone.”

Tinh thần đó vẫn hữu ích.

Nhưng **kiến trúc cụ thể** phải theo current `PROJECT_RULES.md` và current canonical docs, không theo assumption cũ trong prompt lịch sử.

---

## 8. File nên đọc thay cho prompt gốc khi phát triển hiện tại

1. `../PROJECT_RULES.md`
2. `README.md`
3. `CORE_ARCHITECTURE.md`
4. `STUDIO_RUNTIME_OWNERSHIP_AND_CONFLICT_PREVENTION.md`
5. `STUDIO_EDITOR_EXPERIENCE_2.md`
6. `DECISIONS.md`
7. `CRESCO_SESSION_V1.md`
8. `CRESCO_PATCH_V1.md`
9. `CRESCO_AI_CONTEXT_V3.md`
10. tài liệu subsystem đang sửa

Dùng prompt gốc khi cần hiểu **historical product scope/intent**, không dùng nó như ownership override cho `main` hiện tại.