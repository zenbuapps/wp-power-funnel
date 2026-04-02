# Workflow 執行詳情頁

## 描述
管理員用於查看單一 Workflow 執行實例的完整資訊，包含：ReactFlow 唯讀流程圖（節點執行狀態視覺化）、節點結果 Drawer、resolved context、簡化版觸發資訊面板。

## 前端路由
`/workflows/:id`（Refine.dev resource show page）

## 頁面區塊

### A. 頂部摘要列
| 欄位 | 說明 |
|------|------|
| Workflow ID | pf_workflow Post ID |
| 來源規則 | WorkflowRule 標題，可點擊跳轉到 WorkflowRule 編輯頁 |
| 觸發點 | trigger_point 的顯示名稱 |
| 狀態 | running / completed / failed，以顏色標籤呈現 |
| 觸發時間 | Workflow 建立時間 |
| 耗時 | startedAt 到 completedAt 的時間差（API 計算） |

### B. ReactFlow 唯讀流程圖
- 複用現有 FlowCanvas 元件體系（ActionNode、flowSerializer、useFlowLayout）
- 新增 ReadonlyFlowCanvas wrapper：`nodesDraggable=false`、`nodesConnectable=false`、`panOnDrag=true`、`zoomOnScroll=true`
- 節點執行狀態以 CSS overlay 呈現：
  - 200 成功：綠色邊框 + 勾號徽章
  - 301 跳過：灰色邊框 + 跳過徽章
  - 500 失敗：紅色邊框 + 叉號徽章
  - 進行中（有 node 但無 result）：藍色邊框 + 載入動畫徽章
  - 未執行（尚未輪到的 node）：預設邊框，無徽章
- 點擊節點觸發 Drawer

### C. 節點結果 Drawer（Ant Design Drawer）
| 欄位 | 說明 |
|------|------|
| 節點 ID | NodeDTO.id |
| 節點類型 | node_definition_id 對應的顯示名稱 |
| 結果碼 | 200 / 301 / 500 |
| 訊息 | WorkflowResultDTO.message |
| 額外資料 | WorkflowResultDTO.data（JSON 格式化展示） |
| 執行時間 | WorkflowResultDTO.executed_at |
| 節點參數 | NodeDTO.params（key-value 展示） |

### D. 簡化版觸發資訊面板
展示在流程圖下方的摺疊面板（Ant Design Collapse），包含：
| 欄位 | 說明 |
|------|------|
| 觸發點 | trigger_point hook 名稱 + 顯示名稱 |
| 來源規則 | WorkflowRule 標題 + ID，可點擊跳轉 |
| Context 來源 | context_callable_set 的人類可讀格式（如 `TriggerPointService::resolve_registration_context(123)`） |
| Resolved Context | key-value 表格顯示（如 user_email: alice@example.com） |

## 使用的 API
- `GET /power-funnel/workflows/{id}`（詳情）

## 元件依賴
- @xyflow/react: ReactFlow（唯讀模式）
- 複用: FlowCanvas 的 ActionNode、EntranceNode、ExitNode、CustomEdge、flowSerializer、useFlowLayout
- 新增: ReadonlyFlowCanvas（wrapper）、NodeStatusBadge（狀態徽章）
- Ant Design: Drawer, Descriptions, Tag, Collapse, Table
- Refine.dev: useShow
