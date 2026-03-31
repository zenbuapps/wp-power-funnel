# System

## 描述
Power Funnel 後端 PHP 系統。負責註冊 NodeDefinition、處理 REST API 請求、透過 filter hook 允許第三方擴充。

## 關鍵屬性
- WordPress init hook 階段執行 NodeDefinition 註冊
- 透過 apply_filters 允許第三方外掛擴充節點定義
- 提供 REST API 端點供前端查詢
