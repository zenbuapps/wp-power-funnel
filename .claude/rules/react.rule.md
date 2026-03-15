---
name: "React開發指引"
description: "Power Funnel React 前端開發規範：React 18、TypeScript、Refine.dev、Ant Design 5、ReactFlow 節點編輯器"
globs: "js/**/*.{ts,tsx}"
---

# React / TypeScript 前端開發指引

## 技術棧

- **React 18** + **TypeScript 5.5**（嚴格模式）
- **@xyflow/react** — ReactFlow 節點編輯器（待開發）
- **Refine.dev 4.x** — 資料管理框架（CRUD、資源定義）
- **Ant Design 5** / **antd-toolkit** — UI 元件庫
- **React Query v4** (TanStack) — 資料獲取與快取
- **React Router v7** — 前端路由（HashRouter）
- **Tailwind CSS**（`tw-` 前綴避免 WordPress 衝突）+ **daisyUI**
- **Vite** + **@kucrut/vite-for-wp** — 建構工具
- **Jotai** — 原子化狀態管理
- **Zod** — Schema 驗證
- **@line/liff** — LINE LIFF SDK
- **BlockNote** — 區塊編輯器

## 專案結構

```
js/src/
├── main.tsx              # React 掛載入口（雙 App）
├── App1.tsx              # 管理後台 SPA（Refine + HashRouter）
├── App2.tsx              # LIFF 報名畫面
├── pages/
│   ├── PromoLinks/       # 推廣連結管理（List / Edit）
│   ├── Settings/         # 設定頁面（LINE / YouTube）
│   └── WorkflowRules/    # ReactFlow 節點編輯器（待開發）
├── components/           # 跨頁面共用元件
├── resources/index.tsx   # Refine 資源定義
├── api/                  # API 層（CRUD 函式）
├── types/                # TypeScript 型別定義
│   ├── index.ts          # 主要 export
│   ├── env.ts            # TEnv 型別
│   ├── common.ts         # 通用型別
│   ├── activity.ts       # 活動型別
│   └── option.ts         # 設定型別
├── utils/                # 工具函式
└── assets/scss/          # 全局樣式
```

## 編碼規範

### TypeScript 強制要求
- 所有元件、函式必須有明確型別定義
- **禁止使用** `any`；必要時使用 `unknown` 加型別守衛
- Props 介面使用 `TProps` 命名慣例（如 `TPromoLinkListProps`）

```typescript
// 正確
interface TMyComponentProps {
  id: string
  onSave: (data: TWorkflowRule) => void
}

// 禁止
const MyComponent = (props: any) => {}
```

### 元件規範
- 使用函式元件 + Hooks，禁止 Class Component
- 元件檔案以 PascalCase 命名
- 每個頁面目錄提供 `index.tsx` 作為 barrel export
- 頁面元件不超過 300 行，複雜邏輯抽成自訂 Hook

### Tailwind CSS 規範
部分 class 與 WordPress admin CSS 衝突，必須加 `tw-` 前綴：
`hidden`, `block`, `flex`, `grid`, `fixed`, `absolute`, `relative`, `overflow-hidden`

```tsx
// 正確
<div className="tw-hidden tw-block tw-fixed tw-flex">

// 錯誤（WordPress 會覆蓋）
<div className="hidden block fixed flex">
```

### 環境變數存取
```typescript
import { useEnv } from 'antd-toolkit'
import { TEnv } from '@/types'

const { API_URL, NONCE, KEBAB, WORKFLOW_RULE_POST_TYPE } = useEnv<TEnv>()
```

## Refine.dev 規範

### 資源定義
在 `js/src/resources/index.tsx` 註冊 CRUD 資源。目前已定義：
- `promo-links` — LINE 連結管理
- `workflow-rules` — 自動化（待開發 UI）
- `settings` — 設定

### Data Provider
| Provider | 基礎路徑 | 用途 |
|----------|---------|------|
| `default` | `/v2/powerhouse` | Powerhouse 通用 API |
| `power-funnel` | `/power-funnel` | 本外掛專屬 API |
| `wp-rest` | `/wp/v2` | WordPress Core |
| `wc-rest` | `/wc/v3` | WooCommerce |
| `wc-store` | `/wc/store/v1` | WC Store API |

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

### 核心要求
- 使用 `@xyflow/react`（v12+）
- 節點類型對應後端 `ENode` enum（10 種）
- 節點類型分類：`SEND_MESSAGE`（email/sms/line/webhook）和 `ACTION`（其餘）
- 編輯完成後將節點陣列序列化存入 WorkflowRule CPT 的 `nodes` meta

### 節點資料結構（對應後端 NodeDTO）
```typescript
interface TNode {
  id: string
  node_definition_id: string           // 對應 ENode value
  params: Record<string, unknown>      // 節點設定參數
  match_callback?: string[]            // 執行條件 callback
  match_callback_params?: Record<string, unknown>
}
```

### 自訂節點元件
```typescript
import { memo } from 'react'
import { Handle, Position, NodeProps } from '@xyflow/react'

// nodeTypes 必須在模組頂層宣告，不在元件 render 內
export const nodeTypes = {
  email: memo(EmailNode),
  wait: memo(WaitNode),
} as const
```

### 頁面路由
在 `App1.tsx` 中新增路由：
```tsx
<Route path="workflow-rules">
  <Route index element={<WorkflowRuleList />} />
  <Route path="edit/:id" element={<WorkflowRuleEdit />} />
</Route>
```

## 代碼品質指令

```bash
pnpm lint         # ESLint 檢查
pnpm lint:fix     # 自動修復
pnpm format       # Prettier 格式化 tsx
pnpm build        # 建構（含型別檢查）
```
