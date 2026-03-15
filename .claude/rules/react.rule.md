---
name: "React開發指引"
description: "專精於 React 18、TypeScript、Refine.dev、Ant Design 5、ReactFlow 節點編輯器的前端工程專家"
applyTo: 'js/**/*.{ts,tsx}'
---

# React / TypeScript 前端開發指引

您是一位世界級的 React 前端工程師，精通 React 18、TypeScript、Refine.dev、Ant Design 5，以及 @xyflow/react 節點編輯器開發。

## 技術棧

- **React 18** + **TypeScript**（嚴格模式）
- **@xyflow/react** — ReactFlow 節點編輯器
- **Refine.dev** — 資料管理框架（CRUD、資源定義）
- **Ant Design 5** — UI 元件庫
- **React Query v4** — 資料獲取與快取
- **React Router v6** — 前端路由
- **Tailwind CSS**（使用 `tw-` 前綴避免 WordPress 衝突）
- **Vite** — 建構工具

## 專案結構規範

```
js/src/
├── App1.tsx              # 主應用 (管理後台)
├── App2.tsx              # 副應用 (LIFF/Metabox)
├── main.tsx              # React 掛載入口
├── pages/                # 頁面元件（每個功能一個目錄）
│   ├── PromoLinks/       # ✅ 已完成
│   │   ├── List/
│   │   ├── Edit/
│   │   ├── types/
│   │   └── index.tsx
│   ├── Settings/         # ✅ 已完成
│   └── WorkflowRules/    # ❌ 待開發（ReactFlow 編輯器）
├── components/           # 跨頁面共用元件
├── resources/            # Refine 資源定義
│   └── index.tsx
├── types/                # TypeScript 型別定義
│   ├── index.ts          # 主要 export
│   ├── env.ts            # 環境變數型別
│   └── common.ts         # 通用型別
└── assets/
    └── scss/             # 全局樣式
```

## 編碼規範

### TypeScript 強制要求
- 所有元件、函式必須有明確型別定義
- **禁止使用** `any`；必要時使用 `unknown` 加型別守衛
- Props 介面使用 `TProps` 命名慣例（如 `TPromoLinkListProps`）
- 型別定義統一放在 `js/src/types/` 或頁面目錄下的 `types/`

```typescript
// ✅ 正確
interface TMyComponentProps {
  id: string
  onSave: (data: TWorkflowRule) => void
}

// ❌ 禁止
const MyComponent = (props: any) => {}
```

### 元件規範
- 使用**函式元件** + Hooks，禁止 Class Component
- 元件檔案以 **PascalCase** 命名（`WorkflowEditor.tsx`）
- 每個頁面目錄提供 `index.tsx` 作為 barrel export

```typescript
// ✅ 標準元件結構
import { FC } from 'react'

interface TWorkflowEditorProps {
  workflowRuleId: string
}

export const WorkflowEditor: FC<TWorkflowEditorProps> = ({ workflowRuleId }) => {
  // ...
}
```

### Tailwind CSS 規範
部分 Tailwind class 與 WordPress admin CSS 衝突，必須加 `tw-` 前綴：

```tsx
// ✅ 使用 tw- 前綴
<div className="tw-hidden tw-block tw-fixed tw-flex">

// ❌ 不加前綴（WordPress 會覆蓋）
<div className="hidden block fixed flex">
```

常見需要加前綴的 class：`hidden`, `block`, `flex`, `grid`, `fixed`, `absolute`, `relative`, `overflow-hidden`

### 環境變數存取
透過 `useEnv` hook 存取後端注入的環境變數：

```typescript
import { useEnv } from 'antd-toolkit'
import { TEnv } from '@/types'

const { API_URL, NONCE, KEBAB, WORKFLOW_RULE_POST_TYPE } = useEnv<TEnv>()
```

## Refine.dev 規範

### 資源定義
在 `js/src/resources/index.tsx` 註冊所有 CRUD 資源：

```typescript
export const resources: ResourceProps[] = [
  {
    name: 'workflow-rules',
    list: '/workflow-rules',
    create: '/workflow-rules/create',
    edit: '/workflow-rules/edit/:id',
    show: '/workflow-rules/show/:id',
    meta: { dataProviderName: 'power-funnel' },
  },
]
```

### Data Provider
專案有多個 data provider，選擇對應的：

| Provider 名稱 | 用途 |
|--------------|------|
| `default` | `/v2/powerhouse` |
| `power-funnel` | `/power-funnel` (本外掛 API) |
| `wp-rest` | `/wp/v2` |
| `wc-rest` | `/wc/v3` |

### CRUD Hooks
```typescript
import { useList, useOne, useCreate, useUpdate, useDelete } from '@refinedev/core'

const { data, isLoading } = useList({
  resource: 'workflow-rules',
  meta: { dataProviderName: 'power-funnel' },
})
```

## ReactFlow 節點編輯器規範

> **狀態**: 尚未開始開發，以下為開發時應遵循的規範

### 核心概念
- 使用 `@xyflow/react`（v12+）
- 節點類型對應後端 `ENode` enum（10 種類型）
- 編輯完成後將節點陣列序列化存入 WorkflowRule CPT 的 `nodes` meta

### 節點資料結構（對應後端 NodeDTO）
```typescript
interface TNode {
  id: string                    // 唯一 ID
  node_definition_id: string    // 對應後端 ENode value
  params: Record<string, unknown>  // 節點設定參數
  match_callback?: string[]     // 執行條件 callback
  match_callback_params?: Record<string, unknown>
}
```

### 自訂節點元件規範
```typescript
import { NodeProps, Handle, Position } from '@xyflow/react'

interface TEmailNodeData extends Record<string, unknown> {
  recipient?: string
  subject_tpl?: string
}

export const EmailNode: FC<NodeProps<TEmailNodeData>> = ({ data, selected }) => {
  return (
    <div className={`tw-rounded tw-border ${selected ? 'tw-border-blue-500' : 'tw-border-gray-300'}`}>
      <Handle type="target" position={Position.Top} />
      {/* 節點內容 */}
      <Handle type="source" position={Position.Bottom} />
    </div>
  )
}
```

### 工作流規則儲存格式
儲存到 WordPress post meta `nodes`（陣列格式）：
```json
[
  {
    "id": "node-1",
    "node_definition_id": "email",
    "params": {
      "recipient": "{{context.user.email}}",
      "subject_tpl": "歡迎報名 {{context.activity.title}}"
    }
  }
]
```

## React Query 規範

```typescript
// 使用 staleTime 避免頻繁重新請求
const { data } = useQuery({
  queryKey: ['workflow-rules', id],
  queryFn: () => fetchWorkflowRule(id),
  staleTime: 1000 * 60 * 10,  // 10 分鐘
})
```

## 頁面路由規範

在 `App1.tsx` 中新增路由：

```tsx
<Route path="workflow-rules">
  <Route index element={<WorkflowRuleList />} />
  <Route path="create" element={<WorkflowRuleCreate />} />
  <Route path="edit/:id" element={<WorkflowRuleEdit />} />  {/* ReactFlow 編輯器 */}
</Route>
```

## 代碼品質指令

```bash
pnpm lint         # ESLint 檢查
pnpm lint:fix     # 自動修復
pnpm format       # Prettier 格式化 tsx
pnpm build        # 建構（含型別檢查）
```

## 最佳實踐

1. **型別安全**：充分利用 TypeScript，避免 `any`
2. **React Query 快取**：善用 `staleTime` 和 `cacheTime` 減少 API 請求
3. **拆分元件**：頁面元件不超過 300 行，複雜邏輯抽成自訂 Hook
4. **錯誤邊界**：使用 Refine 的 `ErrorComponent` 處理路由錯誤
5. **ReactFlow 效能**：大型節點圖使用 `useMemo` 和 `useCallback`，避免不必要的重渲染
6. **Ant Design 主題**：透過 `ConfigProvider` 統一調整主題，不直接覆蓋 CSS
