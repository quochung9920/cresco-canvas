# Kiến trúc — thế hệ Gutenberg-native (lịch sử)

> **TÀI LIỆU LỊCH SỬ, KHÔNG PHẢI KIẾN TRÚC CANONICAL CỦA STUDIO HIỆN TẠI.** Nội dung dưới đây ghi lại thế hệ cũ khi Gutenberg chuẩn là Page editor duy nhất. File này được giữ để phục vụ migration, lịch sử và legacy/native path. Với Website Builder hiện tại, hãy đọc `docs/CORE_ARCHITECTURE.md`, `docs/WEBSITE_BUILDER_CORE.md`, `docs/STUDIO_EDITOR_EXPERIENCE_2.md`, ADR-013/ADR-014 trong `docs/DECISIONS.md` và đặc biệt `docs/STUDIO_RUNTIME_OWNERSHIP_AND_CONFLICT_PREVENTION.md`. Những phát biểu bên dưới như “không có custom Page editor/workbench” hoặc “không có Page document route” đã bị supersede đối với Studio-owned Website Builder documents.

---

## Ranh giới hệ thống ở thế hệ này

Ở thế hệ được mô tả trong tài liệu này, Cresco Canvas là extension của Gutenberg, không phải fork và không phải editor thứ hai. Hành động **Edit** chuẩn của WordPress Page mở Gutenberg. WordPress Core sở hữu Page entity và toàn bộ document workflow; Cresco đăng ký native blocks, plugin sidebar, Page metadata có revision và scoped style tokens.

```text
WordPress Page post_content và post meta
        ↕ Core editor/data stores
Native Gutenberg editor
        ↕ public SlotFills và block APIs
Cresco sidebar, Container block và design settings
```

Public frontend không chứa Cresco editor React runtime. Khi deactivate plugin, native markup vẫn có thể đọc và chỉnh sửa.

---

## PHP services

| Service | Trách nhiệm ở kiến trúc lịch sử này |
| --- | --- |
| `Requirements` | Bắt buộc WordPress 6.7+ và PHP 8.1+ trước boot/activation |
| `Plugin` | Đăng ký các service tách biệt sau khi compatibility check thành công |
| `Lifecycle\Activator` | Chạy requirement checks và migration runner |
| `Lifecycle\Deactivator` | Giữ content/settings và chỉ dọn stale migration lock |
| `Migration\Migrator` | Serialize idempotent schema upgrades và giữ failure evidence |
| `Support\FeatureFlags` | Normalize experimental flags đã biết; mặc định tắt |
| `Admin\EditorIntegration` | Đăng ký Page metadata có revision và enqueue Gutenberg sidebar chỉ trong Page block editor |
| `API\RestApi` | Chỉ expose Cresco global settings đã permission/validate; không có Page document routes |
| `Styles\GlobalStyles` | Validate design settings và conditionally emit scoped editor/frontend variables |
| `Blocks\Blocks` | Đăng ký native Container metadata và compiled block editor script |

Composer cung cấp optimized PSR-4 loading trong release. Restricted fallback autoloader giúp source checkout vẫn recover được trước `composer install` và chỉ load class `CrescoCanvas\` từ `includes/`.

---

## Editor modules

- `src/editor/index.tsx` đăng ký một WordPress plugin extension.
- `src/editor/components/SettingsSidebar.tsx` tích hợp Page enablement và site-wide settings vào Gutenberg sidebar.
- `src/editor/components/GlobalSettingsPanel.tsx` dùng WordPress controls và một save riêng chỉ cho site-wide Cresco data.
- `src/editor/previewTokens.ts` chiếu validated settings vào Gutenberg canvas hiện tại mà không chạm DOM không liên quan.
- `src/blocks/container/` sở hữu native Container registration, edit/save view, types và style projection.

Trong thế hệ này không có custom Page editor mount point, router, top bar, document state store, Page REST adapter hoặc alternate Page edit URL.

Generated files trong `build/` là production assets cần cho source-checkout recovery. Source maps, dependency metadata, tests và developer source bị loại khỏi release ZIP.

---

## Data model

| Storage | Key/entity | Policy của thế hệ này |
| --- | --- | --- |
| Posts | Page `post_content` | Canonical native block markup; Core save/revise; uninstall không xóa |
| Post meta | `_cresco_canvas_enabled` | Boolean, REST-visible, capability-protected, revision-enabled |
| Option | `cresco_canvas_settings` | Validated global tokens và explicit uninstall choice |
| Option | `cresco_canvas_feature_flags` | Known Boolean experimental flags |
| Option | `cresco_canvas_db_version` | Schema version hoàn tất gần nhất |
| Option | `cresco_canvas_migration_state` | Complete/failure evidence, không render exception detail |
| Option | `cresco_canvas_migration_lock` | Short-lived atomic migration lock |

Schema version 1 normalize legacy settings và tạo feature flags. Schema version 2 loại bỏ retired editor-choice option và metadata. Hai migration này không rewrite posts hoặc block markup; `_cresco_canvas_enabled` được giữ nguyên.

---

## Document reliability

Trong kiến trúc Gutenberg-native, workflow Page của Core cung cấp:

- entity fetching;
- dirty state;
- save/update/publish;
- status và scheduling;
- autosave/revisions;
- undo/redo;
- post locking và conflict handling;
- retry/error notices;
- unsaved-navigation protection;
- preview;
- slug, featured image, parent, template, discussion;
- media, inserter và List View.

Cresco Page metadata được chỉnh qua `core/editor` và lưu bằng cùng Core action với Page content. Metadata này opt-in vào revision. Custom REST chỉ còn cho site-wide Cresco settings vì chúng không phải Page entity data.

---

## Asset isolation

- Sidebar JavaScript/CSS chỉ load trong Gutenberg Page editor chuẩn.
- Thiếu sidebar asset chỉ tạo PHP warning không blocking; Gutenberg vẫn mở được.
- RTL dùng WordPress stylesheet replacement metadata.
- Không enqueue editor JavaScript trên public frontend.
- Frontend CSS chỉ load trên singular Page có Cresco metadata explicit hoặc legacy `cresco/container` block.
- Design variables target `.editor-styles-wrapper` trong Gutenberg và `body.cresco-canvas-page` trên frontend.
- Container selector được scope theo block; không có unqualified root/body/button selector.

---

## Recovery và rollback

- Core autosave, revisions, locks, notices và recovery sở hữu document safety.
- Nếu thiếu Cresco build, chỉ Cresco tools bị disable; Page vẫn chỉnh trong Gutenberg được.
- Deactivation giữ toàn bộ content, metadata và settings.
- Uninstall mặc định bảo toàn dữ liệu. Chỉ khi explicit cleanup mới xóa documented Cresco options/metadata trên multisite và không bao giờ chạm `post_content`.

---

## Cách sử dụng tài liệu này hiện nay

Chỉ dùng file này để:

- hiểu quá trình chuyển đổi từ Gutenberg-native sang Cresco Studio;
- đọc migration/legacy compatibility decision;
- đối chiếu code hoặc ADR có nguồn gốc từ thế hệ cũ.

**Không dùng các phát biểu ownership trong file này để thiết kế feature Studio mới.** Với code hiện tại, authority nằm ở `PROJECT_RULES.md` và bộ tài liệu canonical được liệt kê trong `docs/README.md`.