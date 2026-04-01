# Admin

## 描述
Power Funnel 管理員，透過前端介面查詢觸發點列表、設定工作流規則（WorkflowRule）的觸發條件與參數。

## 關鍵屬性
- 可查詢所有已註冊的觸發點及其 context keys
- 可為 LINE_POSTBACK_RECEIVED 觸發點設定 postback_action 過濾參數
- 透過 WorkflowRule 編輯器設定 trigger_point meta（含 hook 和 params）
