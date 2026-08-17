# Cresco AI Context v3 — Thiết kế One-Shot độ trung thực cao

## Mục đích

`cresco-ai-context/v3` là authoring package mặc định cho workflow **Copy for AI** đơn giản hóa. Import format vẫn là `cresco-patch/v1` và không đưa checksum revision gate trở lại.

Mục tiêu: người dùng chọn section, mô tả kết quả mong muốn một lần, có thể attach reference image trong external AI chat, rồi nhận patch mà Cresco validate/review được mà không cần người dùng hiểu nội bộ Cresco.

## V3 thêm gì so với V2

V2 tập trung contract completeness. V3 tập trung package và design context:

- `task`: natural-language request + immutable editable target.
- `scopePackage.scene`: ancestry, sibling summary và containing top-level root dưới dạng **read-only context**.
- `scopePackage.contracts.recommended`: tập full contract được focus theo request.
- `scopePackage.contracts.catalogIndex`: discovery metadata gọn cho mọi registered widget.
- `scopePackage.visualFacts`: semantic fact lấy từ Session và luôn nói rõ geometry có thật sự được đo hay không.
- `scopePackage.visual.contextMode`: narrow target có thể render trong real top-level branch để giữ parent/sibling layout context.
- `returnContract.examples`: operation example dùng resolved target ID.
- quality goal trong `authoringPolicy`.

Optimized mode **không gửi full `creationCatalog`**. Model chỉ được author node mới từ full contract có trong `contracts.current` hoặc `contracts.recommended`. `catalogIndex` chỉ là discovery metadata, không phải permission để đoán prop. Full mode có thể giữ complete creation catalog.

## Default One-Shot request

Studio action gửi shape gần như:

```json
{
  "version": 3,
  "profile": "one-shot-v3",
  "purpose": "redesign",
  "scope": "subtree",
  "target": { "nodeId": "selected-node" },
  "mode": "optimized",
  "includeVisual": true,
  "request": "Match the attached reference image and keep the copy."
}
```

Nếu không có selection, default target có thể là Page theo workflow hiện hành.

Server trả:

```json
{
  "package": { "schema": "cresco-ai-context/v3" },
  "prompt": "CRESCO ONE-SHOT DESIGN TASK ..."
}
```

Clipboard nhận `prompt`, không phải raw schema dump.

## Contract focus

`contracts.recommended` được derive từ real `ContractRegistry`; không có widget schema thứ hai.

Nó bắt đầu từ widget type hiện có trong scope + common layout/content primitive thực sự tồn tại trong `WidgetCatalog`, rồi deterministically thêm contract liên quan từ request.

`catalogIndex` cho model biết product vocabulary mà không trả token cost của mọi full contract.

## Visual context ring

Target section có thể render sai nếu tách khỏi parent. Ví dụ hero trong boxed grid 1200px có thể khác khi đứng root.

Với narrow target, `VisualContext` cố render complete **top-level branch chứa target**. Ancestor/sibling layout được giữ nhưng patch target không đổi.

Visual payload đánh dấu rõ:

- `contextMode`
- `contextRootIds`
- `editableTarget`

Model được nhìn context nhưng không được edit ngoài scope.

## Visual facts

Server-side v3 không fabricate browser geometry.

`visualFacts` có semantic/session-derived info như:

- node count và max depth;
- widget type trong target;
- responsive bucket được dùng;
- số widget có Custom CSS;
- text/image/interactive counts.

Nó khai báo:

```json
{
  "source": "session-semantic-summary",
  "measuredGeometry": false
}
```

Browser capture tương lai có thể thêm real box measurement nhưng phải giữ distinction này.

## Import và review

Validate endpoint có thể nhận raw pasted text. `AIResultNormalizer` loại deterministic formatting noise như một Markdown fence hoặc UTF-8 BOM; sau đó result vẫn phải qua normal Cresco validator.

Successful validation có thể chứa:

- structured `diff`;
- deterministic `quality` preflight;
- `visualReview.beforeDocument`;
- `visualReview.afterDocument`.

Studio có thể mở before/after review ở Desktop, Tablet, Mobile reference widths.

## Quality preflight

`DesignQualityGate` chỉ chạy check chứng minh được từ Session data, ví dụ:

- heading hierarchy jump;
- image alt rỗng;
- button label rỗng;
- missing button destination dưới dạng informational feedback.

Nó phải liệt kê điều **không kiểm tra**, như browser geometry, horizontal overflow, pixel contrast, similarity với reference image. UI không được claim evidence không tồn tại.

## Repair prompt

Khi validation fail, Studio bridge giữ error message + structured details như path, operation index, property, widget type, node ID khi có.

**Copy repair prompt** tạo deterministic prompt thứ hai chứa previous result + return contract. Cresco không silently mutate ambiguous AI output.

## UI model

AI Studio bình thường nên gọn:

1. Current target.
2. Natural-language request.
3. Reference-image hint.
4. **Copy for AI**.
5. Paste result.
6. **Validate & Preview**.
7. Review diff/preflight/visual before-after.
8. **Apply to current target**.

Scope/context-detail nâng cao nằm trong **Advanced export** cho developer.

## Backward compatibility

- `cresco-ai-context/v1` vẫn có thể dùng khi không request version/profile mới hơn.
- explicit `version: 2` vẫn là v2, gồm `profile: one-shot-v2`.
- profile-only `one-shot` resolve về v3 theo contract hiện tại.
- `cresco-patch/v1` vẫn là preferred return schema.
- AI workflow vẫn checksum-free ở Patch level.
- Apply chỉ đổi local editor state; vẫn cần **Update** để Save.
