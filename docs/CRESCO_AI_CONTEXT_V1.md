# Cresco AI Context v1 — compatibility profile

> V1 vẫn được hỗ trợ như **compatibility profile**. Workflow One-Shot mới nên dùng [Cresco AI Context v3](CRESCO_AI_CONTEXT_V3.md) theo tài liệu hiện tại. V1/V2 được giữ để tương thích với integration cũ.
>
> Tên schema, JSON key, route, code literal và contract value trong file này được giữ nguyên tiếng Anh.

`cresco-ai-context/v1` là portable, design-only interchange envelope của Cresco Canvas dành cho external AI systems. Nó mô tả những gì AI được phép hiểu và chỉnh sửa mà không cấp cho AI quyền truy cập trực tiếp DOM, WordPress credentials hoặc arbitrary application state.

## Envelope

```json
{
  "schema": "cresco-ai-context/v1",
  "version": 1,
  "scope": "subtree",
  "mode": "optimized",
  "target": {
    "scope": "subtree",
    "nodeId": "hero",
    "type": "container"
  },
  "environment": {
    "crescoVersion": "…",
    "sessionSchema": "cresco-session/v1",
    "postId": 123,
    "postTitle": "Home"
  },
  "designSystem": {},
  "pageSettings": {},
  "contracts": {},
  "content": {},
  "dependencies": {
    "tokens": [],
    "media": [],
    "responsive": []
  },
  "instructions": []
}
```

Không có `baseChecksum` bắt buộc. AI patch được validate với target, scope, contracts, IDs và canonical Session rules của editor state hiện tại. AI output cũ có thể vẫn chứa `baseChecksum`; patch validator hiện tại bỏ qua legacy field này.

## Scopes

### `page`

Export toàn bộ current Session. `Full Context` gồm complete contract catalog và complete Design System. `Optimized Context` vẫn giữ full page content nhưng giảm supporting dependencies khi có thể.

### `subtree`

Export target node cùng toàn bộ descendants, minimal ancestry, contracts cần cho content đó, semantic token dependencies, responsive devices liên quan và media descriptors.

### `widget`

Chỉ export selected node. Children bị cố ý bỏ qua ngay cả khi widget có thể chứa children. Minimal parent/ancestry context được giữ để AI hiểu layout nhưng không có edit authority trên parent.

### `selection`

Protocol hỗ trợ nhiều `nodeIds`. Standalone editor ở thời điểm V1 được mô tả là single-select, vì vậy V1 UI export current selection như selection một node. Wire protocol vẫn sẵn sàng cho multi-select sau này mà không đổi schema.

## Full và Optimized

`mode: "full"` export:

- complete Design System token catalog;
- raw và effective Page Settings;
- toàn bộ current widget contracts;
- selected content scope;
- toàn bộ discovered dependencies.

`mode: "optimized"` export:

- selected content và ancestry cần thiết;
- effective Page Settings;
- chỉ semantic Design System tokens được content tham chiếu;
- breakpoint definitions khi có responsive overrides;
- chỉ widget contracts cần cho scope/ancestry;
- media descriptors được scope sử dụng.

Vì vậy một Button edit không cần mang toàn bộ widget catalog.

## Widget Contracts

Contracts được derive từ authoritative Cresco Session widget catalog. Mỗi contract expose:

- `type`, `label`, `allowsChildren` và child behavior;
- supported `props`, types, enums, bounds và defaults;
- allowed structured style properties;
- responsive device support;
- token-reference syntax;
- scoped Custom CSS capability và stable `data-cresco-part` selectors;
- stable root selector key (`data-cresco-id`).

AI output có unsupported widget, prop, structured style property, responsive device hoặc Custom CSS bucket bị Patch validator từ chối trước khi Session sanitizer chạy.

## Design token dependencies

Semantic references được giữ nguyên dưới dạng reference, ví dụ:

```json
{
  "path": "spacing.xl",
  "fallback": "clamp(2rem, 1.35rem + 2.4vw, 4rem)"
}
```

AI nên giữ `{spacing.xl}` thay vì thay bằng resolved fallback, trừ khi người dùng yêu cầu local value rõ ràng.

## Media dependencies

Image dependencies dùng portable descriptors:

```json
{
  "nodeId": "hero-image",
  "id": 123,
  "url": "https://example.test/uploads/hero.jpg",
  "alt": "…",
  "width": 1600,
  "height": 900,
  "policy": "URL is descriptive only; cross-site import must map media explicitly and must not auto-download remote URLs."
}
```

Attachment IDs là site-local và không được coi là portable identifiers. Cresco AI Interchange v1 không tự động download remote media.

## Security và privacy boundary

Context chỉ được build từ Cresco design data: sanitized Session content, Design System, Page Settings, contracts và derived dependencies.

Defense-in-depth sanitizer loại các secret-bearing keys như nonces, passwords, cookies, authorization headers, API/license keys, webhook/client secrets, database credentials, access/refresh tokens, private form submissions và user-session data.

AI context **không** cấp cho AI quyền mutate trực tiếp WordPress hoặc DOM.

Việc không dùng checksum locking không loại bỏ safety boundary: target existence, exported scope, widget contracts, structural destinations, ID rules, Custom CSS policy, canonical Session validation, Diff review, Undo và normal Update persistence vẫn được enforce với current editor state.

## Rendered appearance

Phần semantic envelope mô tả document *có nghĩa gì*, nhưng không đủ để biết document *trông như thế nào*. Không thể đánh giá overflow, contrast, alignment hoặc spacing consistency chỉ từ semantic tree.

Đặt `includeVisual: true` trong context request để gắn `visual` object:

| Field | Ý nghĩa |
| --- | --- |
| `html` | Rendered markup của exported scope từ cùng renderer với public page |
| `css` | Compiled stylesheet cho scope, gồm state và responsive rules |
| `breakpoints` | Breakpoint starts để không cần tự suy từ media queries |
| `maxWidths` | `max-width` boundaries của downward-inheriting cascade |
| `htmlTruncated`, `cssTruncated` | Cho biết byte caps (500 KB markup, 256 KB CSS) có được áp dụng hay không |

Visual context mặc định tắt ở V1 vì markup/CSS chiếm nhiều payload. Rendering đi theo scope; `widget` export render widget đó thay vì toàn page.

Editing result vẫn phải trả về `cresco-session/v1` hoặc `cresco-patch/v1`. HTML/CSS chỉ để đọc, không phải result format.

### Standalone document

`POST /cresco-canvas/v1/ai-interchange/{postId}/visual`

Endpoint trả `cresco-ai-visual/v1` với `document` string: một complete HTML page có stylesheet inline và minimal reset để browser defaults không bị hiểu nhầm là design fault.

Studio expose workflow này qua **Export rendered HTML** trong command palette. File được site renderer tạo và lưu local; Cresco không tự truyền file ra ngoài.

## Container width rule

Rule này là normative cho AI output:

> `Container props.contentWidth="full"` nghĩa là `width: 100%` của **parent** của Container. Nó không có nghĩa viewport width. AI không được dùng `100vw` để break một Container thật ra khỏi boxed parent.

## API

Legacy readable endpoint được giữ để backward compatibility:

`GET /cresco-canvas/v1/ai-context/{postId}`

Canonical scoped export dùng:

`POST /cresco-canvas/v1/ai-interchange/{postId}/context`

Request:

```json
{
  "session": { "schema": "cresco-session/v1", "version": 1, "documentId": "home", "nodes": [] },
  "scope": "subtree",
  "target": { "nodeId": "hero" },
  "mode": "optimized"
}
```

Editor gửi live in-memory Session để unsaved work tham gia exported context và validation sau đó dùng current in-memory Session.

## Ghi chú compatibility

V1 được giữ để không làm hỏng integration cũ. Khi xây workflow mới, không suy rộng V1 thành kiến trúc AI hiện tại; đọc `CRESCO_AI_CONTEXT_V3.md`, `AI_INTERCHANGE_V2.md` và current server contracts trước.