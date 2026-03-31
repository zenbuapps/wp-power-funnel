# Node Menu (CustomEdge 節點選單)

## 描述
CustomEdge 上的 + 按鈕點擊後彈出的節點類型選單。目前為前端硬編碼的 NODE_MODULE 常數，改為從 API 動態取得。

## 行為
- 前端啟動時呼叫 GET /node-definitions 取得節點清單
- 按鈕點擊後顯示所有已註冊節點，含 icon + name
- 節點依 type 分組顯示（send_message / action）
- 選擇節點後插入對應 node_module 至 Flow

## 關鍵屬性
- 節點清單來源：API 回傳的 NodeDefinition[]
- 分組依據：NodeDefinition.type
- 顯示：icon + name
