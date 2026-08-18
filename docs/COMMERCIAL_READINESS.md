# Mức độ sẵn sàng thương mại — assessment lịch sử

> **Tài liệu lịch sử.** Assessment date: **2026-08-04**. Version: `0.3.0-alpha.1`.
>
> Không dùng các tỷ lệ/status trong file này để kết luận release hiện tại nếu commit/version không khớp.

Alpha tại thời điểm assessment này **chưa sẵn sàng thương mại**. Master roadmap khi đó vẫn tiếp tục đến 1.0 và tài liệu không claim đã có legal, staging, beta hoặc release-candidate approval.

## Tiến độ dựa trên evidence

Feature matrix trong `ROADMAP.md` tính:

- `COMPLETE` = 1 điểm;
- `PARTIAL` = 0.5 điểm;
- `MISSING` / `BROKEN` = 0 điểm;
- `NOT APPLICABLE` không tính.

Tỷ lệ được tính lại từ matrix sau verification. Đây là **product-scope progress**, không phải xác suất thành công, chất lượng hay commercial readiness.

Weighted product-scope readiness tại thời điểm đó là **44.7%**:

```text
(63 + 0.5 × 35) ÷ (63 + 35 + 82 + 0)
```

Commercial readiness **chưa được xác lập** vì cả tám release gates đều còn `NOT VERIFIED`.

## Tóm tắt severity

| Severity | Open validated findings | Ghi chú tại thời điểm assessment |
| --- | ---: | --- |
| P0 | 0 | Không reproduce được P0 trong audited scope |
| P1 | 0 sau local fixes | Hosted CI và adversarial runtime validation còn chờ |
| P2 | Nhiều | Hosted native-editor verification, manual accessibility/performance/compatibility, dev advisories, CI action pinning và release operations |
| P3 | Nhiều | Polish và documentation expansion của milestone sau |

## Release gates

| Gate | Status | Lý do tại thời điểm assessment |
| --- | --- | --- |
| 1 — Data safety | `NOT VERIFIED` | Core autosave/revision/locking architecture đã được restore nhưng rollback, concurrent-editor và unknown-block runtime matrix chưa pass |
| 2 — Security | `NOT VERIFIED` | Có threat model và local review sạch, nhưng role integration, hosted CI, dependency provenance và full product surface chưa verify |
| 3 — Accessibility | `NOT VERIFIED` | Automated test đã cấu hình; chưa có mandatory manual assistive-technology evidence |
| 4 — Reliability | `NOT VERIFIED` | Hosted CI và toàn bộ lifecycle/recovery workflow chưa pass hết |
| 5 — Compatibility | `NOT VERIFIED` | Matrix đã cấu hình nhưng chưa có evidence trên toàn supported environment |
| 6 — Performance | `NOT VERIFIED` | Static CSS budget pass; chưa có runtime và 500-block benchmark |
| 7 — Product completeness | `NOT VERIFIED` | Milestone 0.3 runtime verification và milestone 0.4–1.0 còn chưa hoàn tất |
| 8 — Release/commercial operations | `NOT VERIFIED` | Artifact workflow tồn tại; beta/RC/staging/legal/privacy/translation operations còn thiếu |

## Kết luận của assessment này

Không được tuyên bố commercial readiness trong baseline này khi còn bất kỳ gate nào `NOT VERIFIED`.

Để đánh giá Cresco Canvas hiện tại, không tái sử dụng trực tiếp tỷ lệ `44.7%`; hãy dùng current source, current canonical docs và exact-artifact evidence của release đang xét.