---
paths:
  - "**/*.ts"
  - "**/*.tsx"
---

# Power Funnel — React 前端開發規範

## 架構概覽

雙 App 架構，共享同一個 Vite 入口（`js/src/main.tsx`）：

| App | 掛載點 | 用途 | 路由 |
|-----|--------|------|------|
| App1 | `#power_funnel_app` | WP Admin 管理後台 SPA | React Router v7 + Refine.dev |
| App2 | `#power_funnel_liff_app` | LINE LIFF 報名畫面 | 無路由（單頁） |

## Refine.dev 使用規範

### DataProvider 配置

```typescript
const dataProviders = {
    default:        dataProvider(`${API_URL}/v2/powerhouse`, AXIOS_INSTANCE),
    'wp-rest':      dataProvider(`${API_URL}/wp/v2`, AXIOS_INSTANCE),
    'wc-rest':      dataProvider(`${API_URL}/wc/v3`, AXIOS_INSTANCE),
    'wc-store':     dataProvider(`${API_URL}/wc/store/v1`, AXIOS_INSTANCE),
    'power-funnel': dataProvider(`${API_URL}/${KEBAB}`, AXIOS_INSTANCE),
}
```

### Resource 定義

所有 Refine resource 集中定義於 `js/src/resources/index.tsx`，包含 `name`、`list`、`edit`、`create` 等路由配置。

### CRUD 頁面模式

- **List 頁面**：使用 `useTable` hook + Ant Design `Table` 元件
- **Edit 頁面**：使用 `useForm` hook + Ant Design `Form` 元件
- 自訂 API 操作使用 `js/src/api/resources/` 中的 helper（create、get、update、delete）

## 環境變數存取

透過 `window.power_funnel_data.env` 注入，使用 `useEnv<TEnv>()` hook 存取：

```typescript
const env = useEnv<TEnv>()
// env.API_URL, env.NONCE, env.KEBAB, env.LIFF_ID, ...
```

可用變數：`SITE_URL` / `API_URL` / `NONCE` / `KEBAB` / `SNAKE` / `APP_NAME` / `APP1_SELECTOR` / `APP2_SELECTOR` / `LIFF_ID` / `IS_LOCAL` / `CURRENT_USER_ID` / `CURRENT_POST_ID` / `PERMALINK` / `ELEMENTOR_ENABLED` / 各 `*_POST_TYPE`

## TypeScript 規範

### 型別定義

- 所有型別定義集中於 `js/src/types/`
- Props 型別使用 `TProps` 命名慣例（如 `type TEditProps = { ... }`）
- 禁止使用 `any`（ESLint warn）
- 使用 Zod 進行執行期型別驗證

### 型別目錄結構

```
types/
├── activity.ts          # 活動相關型別
├── common.ts            # 通用型別
├── dataProvider.ts       # DataProvider 型別
├── env.ts / env.d.ts     # 環境變數型別
├── option.ts            # 設定型別
├── wcRestApi/            # WooCommerce REST API 型別
├── wcStoreApi/           # WooCommerce Store API 型別
└── wpRestApi/            # WordPress REST API 型別
```

### 路徑別名

`@/` 對應 `js/src/`（tsconfig paths + Vite alias）

## 元件規範

- 強制使用 Functional Components，禁止 Class Components
- 使用 `memo` 優化不常變動的元件
- UI 套件使用 Ant Design 5（`antd`）+ `antd-toolkit`
- 圖示使用 `@ant-design/icons` 和 `react-icons`

## Tailwind CSS

- **重要**：`important: '#tw'`，Tailwind 樣式需在 `#tw` 容器內才生效
- 與 WordPress 衝突的 class 使用 `tw-` 前綴替代：
  - `tw-hidden`（取代 `hidden`）
  - `tw-block`（取代 `block`）
  - `tw-inline`（取代 `inline`）
  - `tw-fixed`（取代 `fixed`）
  - `tw-columns-1`、`tw-columns-2`
- 以上原始 class 已被 `blocklist` 禁用

## LIFF 整合（App2）

```typescript
import liff from '@line/liff/core'
await liff.init({ liffId: env.LIFF_ID })
const profile = await liff.getProfile()
// saveLiffUserInfo() → POST /power-funnel/liff → 後端觸發 liff_callback → Carousel
```

LIFF App 初始化後取得用戶 Profile，將資料與 URL 參數（含 promoLinkId）送至後端。

## 狀態管理

- **全域原子狀態**：Jotai（`jotai`）
- **伺服器狀態**：TanStack Query v4（`@tanstack/react-query` 4.39.x）
- **富文本**：BlockNote 0.30（`@blocknote/react`）

## ESLint 規則重點

- 語法風格：`semi: never`、`quotes: single`
- 禁止未使用的 import（`no-duplicate-imports: error`）
- `_` 前綴的變數/參數允許未使用
- `no-shadow: error`（禁止變數遮蔽）
- 使用 Prettier 自動格式化（tab 縮排，tabWidth: 2）

## 新增前端頁面流程

1. 在 `js/src/pages/` 下建立頁面目錄
2. 建立 `index.tsx` 作為頁面入口元件
3. 更新 `js/src/resources/index.tsx` 新增 Refine resource
4. 更新 `js/src/App1.tsx` 新增路由

## ReactFlow 開發

需要建立或修改 ReactFlow 節點編輯器時，請執行 `/react-flow-master` 載入完整開發指引。
