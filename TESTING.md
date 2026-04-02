# 測試覆蓋說明

## 執行測試

```bash
composer test                          # 全部測試
composer test -- --group=workflow      # 只跑 Workflow 相關
composer test -- --group=smoke         # 冒煙測試
```

## Workflow 整合測試覆蓋範圍

### 有保證 ✅

| 項目 | 測試位置 |
|------|---------|
| 節點依序執行、AS 串接排程 | `ActionSchedulerChainingTest` |
| WaitNode 暫停 / 到期恢復 | `WorkflowEndToEndTest` |
| 狀態機轉換（running → completed / failed） | `WorkflowExecutionTest` |
| YesNoBranchNode yes / no 分支路徑 | `WorkflowEndToEndTest` |
| Context 序列化與跨節點傳遞 | `WorkflowContextPassingTest` |
| ORDER_COMPLETED 觸發 → Workflow 建立 | `WorkflowEndToEndTest` |
| RecursionGuard 防無限遞迴 | `RecursionGuardTest` |

### 未覆蓋 ⚠️

| 項目 | 原因 | 影響 |
|------|------|------|
| EmailNode `{{variable}}` 模板替換 | `powerhouse` 的 `ReplaceHelper` null-object bug，測試改用 `test_email` stub | 正式環境的郵件模板替換未驗證 |
| 真實 WooCommerce 觸發路徑 | 測試直接呼叫 `do_action('pf/trigger/order_completed')`，跳過 `TriggerPointService::resolve_order_context()` | 真實訂單 context 解析未驗證 |
| LINE / Webhook 外部 API | HTTP 請求以 `pre_http_request` filter mock 掉 | 真實 API 呼叫結果未驗證 |

## 修復路徑

`ReplaceHelper` bug 修掉後，將 `WorkflowEndToEndTest` 的 `test_email` stub 換回真實 `EmailNode`，即可補上模板替換的覆蓋缺口。
