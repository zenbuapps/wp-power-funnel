# 管理員

## 描述
WordPress 管理員，擁有 `manage_options` 權限。透過 Power Funnel 後台管理介面操作 WorkflowRules 的 CRUD。

## 關鍵屬性
- 必須已登入 WordPress 後台
- 必須具備 `manage_options` capability
- 透過 WP REST API + nonce 驗證身份
- 操作範圍：WorkflowRules 的列表查詢、新增、編輯、配置節點、儲存、刪除
