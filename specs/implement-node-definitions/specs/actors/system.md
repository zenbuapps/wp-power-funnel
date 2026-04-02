# System

## 描述
Power Funnel 工作流引擎系統。負責執行各 NodeDefinition 的 execute() 方法、管理 Action Scheduler 排程、處理節點間的串接邏輯。

## 關鍵屬性
- 透過 Action Scheduler 統一管理節點執行排程
- 執行 LineNode、SmsNode、WebhookNode 等訊息發送節點
- 管理 WaitUntilNode、TimeWindowNode 等時間控制節點的延遲排程
- 執行 TagUserNode 的用戶標籤操作並觸發 USER_TAGGED 事件
