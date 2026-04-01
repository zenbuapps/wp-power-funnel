# System

## 描述
Power Funnel 後端系統，負責接收 LINE webhook 事件、觸發工作流、管理觸發點註冊與 context 解析。

## 關鍵屬性
- 監聽 WebhookService dispatch 的 `power_funnel/line/webhook/{type}` hooks
- 遵循 Serializable Context Callable 模式建立 context_callable_set
- 透過 TriggerPointService 集中管理所有 do_action('pf/trigger/...') 呼叫
- RecursionGuard MAX_DEPTH=3 防護 Postback 觸發迴圈
