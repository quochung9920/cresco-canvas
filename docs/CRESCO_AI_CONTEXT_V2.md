# Cresco AI Context v2 — One-Shot compatibility profile

> V2 là One-Shot authoring package bổ sung trên V1. V1 vẫn là compatibility interchange profile cho integration cũ; V2 thêm đủ authoring context để model có thể build trong selected scope trong một lần trao đổi.
>
> Với workflow mặc định hiện tại, kiểm tra `CRESCO_AI_CONTEXT_V3.md`. Tên schema, JSON key, route và literal contract trong file này được giữ nguyên.

## Vì sao V2 tồn tại

V1 trả lời câu hỏi: **“ở đây đang có gì?”**. Điều đó đủ để chỉnh một thứ đã tồn tại nhưng chưa đủ để tự xây một thứ mới.

Ví dụ: yêu cầu V1 redesign một Container rỗng thường chỉ export contract của chính Container và các design token mà Container đó đang reference — thường là không có. Model không biết những widget/property nào được phép tạo, dễ invent tên widget/property và làm patch fail validation.

V2 trả lời thêm câu hỏi: **“được phép xây gì ở đây?”**.

Ba bổ sung chính:

| Bổ sung | Vấn đề được giải quyết |
| --- | --- |
| `contracts.creationCatalog` | Cung cấp mọi widget type model được phép tạo, không chỉ type đã có trong scope |
| `designSystem.available` | Cung cấp toàn bộ token palette ngay cả khi scope chưa reference token nào |
| `returnContract.template` | Cung cấp exact reply object đã điền sẵn resolved target |

Mục tiêu không phải “model sẽ không bao giờ sai”, mà là loại bỏ lỗi do thiếu kiến thức về Cresco và do mơ hồ format — hai nhóm lỗi Cresco có thể chủ động tránh.

## Khác biệt với V1

| | V1 | V2 |
| --- | --- | --- |
| Schema | `cresco-ai-context/v1` | `cresco-ai-context/v2` |
| Shape | Flat envelope | `scopePackage` + `authoringPolicy` + `returnContract` |
| Contracts | Scope types only trong optimized mode | `current` **và** `creationCatalog` |
| Design system | Dependency-optimized | `available` **và** `used` |
| Visual | Opt-in, mặc định tắt | Mặc định bật |
| Purpose | — | `edit`, `redesign`, `create`, `content`, `style`, `import` |
| Return shape | Prose instructions | Machine-readable contract với target template đã điền |
| Session revision lock | Không | Không |

Cả hai profile đều checksum-free cho AI interchange. Patch được validate với target, scope, contracts, IDs và canonical Session rules của **current Session**, thay vì bị khóa vào đúng revision đã export.

## One-Shot flow

1. Chọn một Container, hoặc không chọn gì để làm trên toàn page.
2. Nhập yêu cầu bằng ngôn ngữ tự nhiên.
3. Nhấn **Copy for AI**.
4. Paste sang external assistant và đính kèm reference image nếu cần.
5. AI trả về một `cresco-patch/v1` object.
6. Paste kết quả lại Cresco.
7. Cresco normalize → validate với current Session → preview diff → apply.

Không cần prompt thứ hai để giải thích schema, property names, responsive model hay Custom CSS rules vì package đã mang các contract đó.

## Ví dụ subtree package

```json
{
  "schema": "cresco-ai-context/v2",
  "version": 2,
  "purpose": "redesign",
  "mode": "optimized",
  "scopePackage": {
    "target": { "scope": "subtree", "nodeId": "one-shot-root", "type": "container" },
    "environment": { "crescoVersion": "…", "sessionSchema": "cresco-session/v1", "postId": 12, "postTitle": "Home" },
    "content": { "node": { "id": "one-shot-root", "type": "container", "children": [] }, "ancestry": [] },
    "designSystem": { "available": { "colors": {}, "spacing": {} }, "used": {} },
    "contracts": { "current": { "container": {} }, "creationCatalog": { "container": {}, "heading": {} } },
    "capabilities": { "patchOperations": [], "responsiveDevices": [], "states": [] },
    "visual": { "html": "…", "css": "…" }
  },
  "authoringPolicy": { "decisionOrder": [] },
  "returnContract": {
    "preferred": "cresco-patch/v1",
    "template": { "schema": "cresco-patch/v1", "target": {}, "operations": [] }
  }
}
```

## `creationCatalog` semantics

`contracts.creationCatalog` là `ContractRegistry::all()`, được generate từ `WidgetCatalog`.

Không được có một danh sách widget AI thứ hai do con người duy trì riêng. Danh sách riêng sẽ drift khỏi validator và tạo package hứa những gì validator từ chối.

`contracts.current` vẫn scope theo các type thật sự xuất hiện để model chỉnh section hiện có không phải đọc toàn catalog chỉ để tìm widget đang dùng.

## `designSystem.available` và `designSystem.used`

- `used` — dependency-optimized: các token scope đang thực sự reference.
- `available` — complete catalogue: các token có thể dùng khi authoring.

Cả hai cùng tồn tại. `used` cho model biết thiết kế hiện tại đang cam kết với gì; `available` cho biết được phép dùng gì.

## Visual context

`scopePackage.visual` đến từ `VisualContext::build()`, dùng cùng `WebsiteRenderer` tạo saved public page ở thế hệ V2 này. Nó mang rendered HTML, compiled CSS, breakpoint starts, max-width boundaries và truncation flags.

Ý tưởng cốt lõi là chỉ có một render authority. Visual block chỉ có giá trị khi nó phản ánh output thật của page.

V2 bật visual mặc định vì One-Shot request thường liên quan appearance — điều semantic tree không mô tả đủ. Có thể tắt khi cần giảm payload.

## Authoring decision order

`authoringPolicy.decisionOrder` dùng ladder:

```text
widgetProps → structuredStyle → responsiveStyle → states → customCSS
```

Custom CSS là phương án cuối, không phải công cụ đầu tiên.

Policy cũng mô tả priority khi có reference image: explicit text request → image intent → existing design semantics → contracts như hard technical boundary. Ảnh có thể ảnh hưởng design nhưng không cấp quyền tạo widget type mà contract không cho phép.

### Responsive model được lặp lại có chủ ý

`capabilities.responsiveDevices` liệt kê **bốn** bucket: `desktop`, `laptop`, `tablet`, `mobile`.

`wide` cố ý không có trong danh sách vì nó là base, ghi vào `node.style`; `responsive.wide` fail validation.

Đây là một nguồn lỗi output phổ biến nên rule xuất hiện cả trong `capabilities.responsiveModel` và prompt prose.

## `returnContract`

Model không nên tự suy đoán Cresco cần trả về gì.

`template` được gửi kèm với resolved `target` đã điền. Model chỉ điền `operations` và không đổi phần còn lại. Không cần/emitted checksum.

`preferredOperationForRedesign` là `replaceSubtree` khi redesign toàn selected section và dùng operation tối thiểu hơn cho thay đổi nhỏ. Đây là guidance, không phải bắt buộc tuyệt đối.

## Validate current Session thay vì checksum locking

AI interchange cố ý không dùng Session checksum/stale-revision gate để copy/paste workflow vẫn hữu dụng nếu một phần khác của page thay đổi giữa export và import.

Safety vẫn dựa trên structure và scope:

- target node phải còn tồn tại trong current Session;
- operation phải nằm trong exported target scope;
- widget types, props, styles, responsive buckets, states và Custom CSS phải khớp current contracts;
- structural destinations phải còn hợp lệ;
- candidate result phải pass canonical Website Builder Session sanitizer;
- người dùng review Diff và explicitly Apply;
- persistence vẫn yêu cầu normal **Update** action.

AI output cũ có thể chứa `baseChecksum`; current validator bỏ qua legacy field này thay vì reject patch chỉ vì stale checksum.

## Security boundary

Package chỉ mang design data.

`ContextSanitizer` chạy trên envelope hoàn chỉnh và recursively loại secret-bearing keys như nonces, passwords, cookies, authorization headers, API/licence keys, webhook/client secrets, access/refresh tokens và private form submissions.

V2 không thêm API connectivity, không lưu key, không cho AI mutate DOM và không tự persist. Apply một validated patch chỉ stage candidate; editor save thông thường vẫn phải commit.

## Import normalization

`AIResultNormalizer` loại formatting noise không liên quan safety:

- surrounding whitespace;
- UTF-8 BOM;
- Markdown fence khi toàn response chính xác là một fenced block.

Normalizer **không đoán**. Nó không scan prose để tìm `{` đầu tiên, không tự chọn giữa nhiều JSON object và không repair malformed JSON. Ambiguity phải trả lỗi.

Normalization phía server là authoritative. UI có thể đoán schema để label paste box, nhưng guess đó không quyết định validity. Mọi thứ sống sót qua normalization đi qua `PatchValidator` nguyên vẹn.

## Backward compatibility

Một endpoint phục vụ cả hai profile:

`POST /cresco-canvas/v1/ai-interchange/{postId}/context`

- Không có `version` và không có `profile` → V1 compatibility profile.
- `version: 2` → V2 package.
- `profile: "one-shot"` → V2 package + ready-to-paste `prompt`, `includeVisual` mặc định true.

`cresco-patch/v1` vẫn là targeted patch schema. Revision checksum enforcement đã bị loại khỏi AI interchange, nhưng scope enforcement, target existence, contract validation, ID remapping, canonical Session validation, Diff review, Undo và normal Update persistence vẫn authoritative.

## Reference images

Cresco không encode external screenshot vào package. Prompt yêu cầu model coi attached images như visual direction trong khi contracts vẫn quyết định hard boundary của những gì có thể build.

## Ghi chú compatibility

V2 rất quan trọng khi duy trì integration One-Shot cũ, nhưng không nên được dùng để phủ định contract V3/current implementation. Khi sửa workflow mới, đọc current code và V3 trước.