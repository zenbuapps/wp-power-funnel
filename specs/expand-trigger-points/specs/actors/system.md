# System

## 描述
Power Funnel 外掛的後端系統。負責監聽 WordPress hook 事件，在特定條件滿足時觸發對應的 `pf/trigger/*` hook，讓已註冊的 WorkflowRule 建立 Workflow 實例並執行。

## 關鍵屬性
- 監聽 `power_funnel/registration/{status}` 生命周期 hook
- 監聽 `power_funnel/line/webhook/{type}/{action}` LINE webhook hook
- 監聯 `power_funnel/workflow/{status}` 工作流狀態 hook
- 透過 `do_action('pf/trigger/{trigger_name}', $context_callable_set)` 觸發工作流
- 觸發時傳入 `context_callable_set`（含 callable + params），供 Workflow 執行時取得 context
