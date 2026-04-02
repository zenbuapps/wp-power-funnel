# Workflow 執行清單頁

## 描述
管理員用於瀏覽所有 Workflow 執行實例的表格頁面。提供篩選、搜尋、排序功能，讓管理員快速定位特定 Workflow 的執行紀錄。

## 前端路由
`/workflows`（Refine.dev resource list page）

## 頁面元素

### 篩選列
- **狀態篩選**：下拉選單，選項為 running / completed / failed，可多選或全選
- **觸發點篩選**：下拉選單，選項來自 `/power-funnel/trigger-points` API
- **來源規則篩選**：下拉選單，選項來自已有的 WorkflowRule 清單
- **搜尋框**：文字輸入，模糊搜尋 results 中的 message 欄位

### 資料表格欄位
| 欄位 | 說明 | 排序 |
|------|------|------|
| ID | Workflow ID（workflowId），最左欄，固定寬度 | - |
| 觸發時間 | Workflow 建立時間（post_date），預設由新到舊排序 | 可排序 |
| 來源規則 | 關聯的 WorkflowRule 標題，可點擊跳轉到 WorkflowRule 編輯頁 | - |
| 用戶 | 觸發用戶顯示名稱（userDisplayName）。已登入用戶顯示 display_name，訪客/系統觸發/用戶已刪除顯示「訪客」 | - |
| 觸發點 | trigger_point 的顯示名稱 | - |
| 狀態 | running / completed / failed，以顏色標籤呈現 | 可排序 |
| 節點進度 | 已完成節點數 / 總節點數（如 3/5） | - |
| 耗時 | 從第一個 result 的 executed_at 到最後一個的時間差 | 可排序 |
| 操作 | 「查看詳情」按鈕，跳轉到詳情頁 | - |

### 分頁
Ant Design Pagination 元件，支援 per_page 和 page 切換

## 使用的 API
- `GET /power-funnel/workflows`（清單 + 篩選 + 搜尋 + 分頁）

## 元件依賴
- Ant Design: Table, Select, Input.Search, Tag, Pagination
- Refine.dev: useTable, useList
