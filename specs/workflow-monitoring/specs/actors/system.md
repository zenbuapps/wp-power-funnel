# System

## 描述
Power Funnel 工作流引擎的後端系統。在 Workflow 監控情境中，System 負責在節點執行完成時自動記錄 executed_at 時間戳，以及提供 REST API 供前端查詢 Workflow 執行資料。

## 關鍵屬性
- PHP 後端（WordPress + DDD 分層架構）
- 透過 WP REST API 機制提供自訂 endpoint
- 存取 pf_workflow CPT + wp_postmeta 中的節點與結果資料
