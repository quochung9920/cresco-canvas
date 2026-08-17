# Cresco Patch v1

`cresco-patch/v1` là targeted mutation protocol dùng bởi Cresco AI Interchange. Patch là **data**, không phải executable code. Nó không được apply trực tiếp lên DOM, PHP hoặc WordPress storage.

## Envelope

```json
{
  "schema": "cresco-patch/v1",
  "target": {
    "scope": "subtree",
    "nodeId": "hero"
  },
  "operations": []
}
```

Target scope có thể gồm `page`, `subtree`, `widget`, `selection` và `selection-subtrees` khi scope resolver hỗ trợ.

Patch v1 không yêu cầu bind vào exported Session revision. Không có checksum field bắt buộc. Legacy `baseChecksum` có thể còn xuất hiện trong output cũ nhưng validator hiện tại không dùng nó như revision gate của patch.

## Validation pipeline

Mọi patch đi qua:

1. parse JSON;
2. verify `cresco-patch/v1` schema;
3. validate target/scope trên **current Session**;
4. validate node ID và structural destination;
5. validate widget contract và operation permission;
6. apply vào in-memory Session clone;
7. chạy canonical Session sanitizer, gồm scoped Custom CSS validation;
8. tạo structured Diff;
9. trả candidate đã validate để review;
10. chỉ sau **Apply** editor mới thay local Session;
11. **Undo** quay lại pre-AI checkpoint; persistence vẫn cần **Update**.

Bỏ revision check của patch không có nghĩa bỏ scope/contract safety. Target mất, scope escape, unsupported widget/property hoặc invalid Session đều phải reject.

## Operations

### `setProps`

```json
{
  "op": "setProps",
  "nodeId": "cta",
  "props": { "text": "Book a survey" }
}
```

Chỉ prop được widget contract khai báo mới hợp lệ.

### `setStyle`

```json
{
  "op": "setStyle",
  "nodeId": "hero",
  "style": { "paddingTop": "{spacing.3xl}" }
}
```

Chỉ structured style property được contract cho phép.

### `setResponsive`

```json
{
  "op": "setResponsive",
  "nodeId": "hero",
  "responsive": {
    "tablet": { "paddingTop": "{spacing.xl}" },
    "mobile": { "paddingTop": "48px" }
  }
}
```

Override device dùng `desktop`, `laptop`, `tablet`, `mobile`; widescreen/base nằm trong `style`.

### `setCustomCSS`

```json
{
  "op": "setCustomCSS",
  "nodeId": "cta",
  "customCSS": {
    "base": "&:hover { opacity: .9; }"
  }
}
```

Bucket gồm `base`, `desktop`, `laptop`, `tablet`, `mobile` theo contract hiện hành. Custom CSS phải qua canonical scoped CSS engine.

Local animation/at-rule chỉ được chấp nhận khi validator hiện hành cho phép và vẫn nằm trong scope. Resource-loading/global/executable construct bị cấm.

### `insertNode`

```json
{
  "op": "insertNode",
  "parentId": "hero-actions",
  "index": 1,
  "node": {
    "id": "secondary-cta",
    "type": "button",
    "props": { "text": "Call us", "url": "#", "target": "_self" },
    "style": {},
    "responsive": {},
    "customCSS": {},
    "children": []
  }
}
```

`parentId: null` chỉ hợp lệ với page-scoped patch. Destination phải cho phép children.

### `removeNode`

```json
{ "op": "removeNode", "nodeId": "old-badge" }
```

### `moveNode`

```json
{
  "op": "moveNode",
  "nodeId": "cta",
  "parentId": "hero-actions",
  "index": 0
}
```

Không move node vào chính nó hoặc descendant. Move về Session root chỉ dành cho page scope.

### `replaceSubtree`

```json
{
  "op": "replaceSubtree",
  "nodeId": "hero",
  "node": {
    "id": "ai-hero",
    "type": "container",
    "props": { "contentWidth": "full", "layout": "block" },
    "style": {},
    "responsive": {},
    "customCSS": {},
    "children": []
  }
}
```

Target root ID hiện có được preserve. Descendant ID được giữ khi an toàn và remap khi collision với node ngoài replaced subtree.

## Scope boundary

- `page`: có thể modify mọi node và insert/move ở Session root.
- `subtree`: chỉ target + descendants; destination phải nằm trong subtree.
- `widget`: chỉ `setProps`, `setStyle`, `setResponsive`, `setCustomCSS` trên đúng target; không edit structure/child.
- `selection`: chỉ selected node ID.

Scope escape phải trả lỗi validator tương ứng, ví dụ `cresco_ai_patch_scope_escape` theo implementation hiện hành.

## ID remapping

Stable ID được giữ nếu không collision. Inserted ID collision được suffix deterministically như `-ai`, `-ai-2`.

Validator trả `idMap`; operation sau tham chiếu AI ID đã remap phải được rewrite sang mapped ID.

Khi widget contract tương lai có cross-node reference prop, reference path phải được đăng ký và remap bởi cùng ID layer.

## Diff

Validation trả structured diff gồm:

- `changed` field như `props.text`, `style.fontSize`, `responsive.mobile.paddingTop`;
- `inserted` node;
- `removed` node;
- `moved` node với old/new parent/index.

Review UI hiển thị thay đổi trước Apply.

## Full Session compatibility

Validation endpoint cũng có thể nhận full `cresco-session/v1`. Full Session đi qua canonical Session validator và structured Diff trước Apply.

## API

`POST /cresco-canvas/v1/ai-interchange/{postId}/validate`

```json
{
  "currentSession": { "schema": "cresco-session/v1", "version": 1, "documentId": "home", "nodes": [] },
  "result": { "schema": "cresco-patch/v1", "target": { "scope": "page" }, "operations": [] }
}
```

Route này validate và trả candidate Session + Diff. Nó **không persist Page**.

Standalone AI bridge chỉ Apply candidate vào editor state; Undo/Update bình thường vẫn authoritative.

## Output bị cấm

`cresco-patch/v1` không có JavaScript, DOM command, arbitrary PHP, SQL, raw WordPress meta, request header hoặc credential operation. Không có bypass flag cho Session validator.
