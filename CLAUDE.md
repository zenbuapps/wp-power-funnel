# Copilot 編碼代理指南 - Power Funnel

## 專案概述

Power Funnel 是一個 WordPress 外掛，核心功能包含：
1. 自動抓取 YouTube 直播場次，讓用戶可以透過 LINE 報名
2. **工作流引擎 (Workflow Engine)**：讓管理員透過 **ReactFlow 節點編輯器**設計工作流規則 (`WorkflowRule`)，當觸發條件 (Trigger) 滿足時，自動建立 `Workflow` 實例並逐節點執行

此專案採用現代化混合架構：後端使用 PHP (WordPress + DDD)，前端使用 React SPA 渲染於 WordPress 後台。

**專案狀態**: 開發中
- ✅ 後端 DDD 架構、WorkflowRule/Workflow CPT、工作流執行引擎、LINE/YouTube 整合、PromoLink/Activity 功能
- ✅ 前端 PromoLinks CRUD、Settings 頁面
- 🚧 工作流引擎 - 部分節點類型待實作 (EmailNode 已完成，其他為 stub)
- ❌ **ReactFlow 節點編輯器 UI — 尚未開始開發（核心待辦）**

## 技術棧

### 後端 (PHP)
- PHP 8.1+
- WordPress Coding Standards (WPCS) + PHPStan (level 9)
- PHPCS/PHPCBF 代碼格式化
- Composer 依賴管理
- PSR-4 自動載入 (namespace: `J7\PowerFunnel`)
- DDD 架構分層：`Applications` / `Domains` / `Infrastructure` / `Shared` / `Contracts`

### 前端 (JavaScript/TypeScript)
- React 18 + TypeScript
- **@xyflow/react (ReactFlow)** — 節點編輯器（待開發）
- Vite 建構工具
- Ant Design 5 (UI 框架)
- Tailwind CSS + Sass
- Refine.dev (資料管理框架)
- React Query v4 (資料獲取)
- React Router v6
- pnpm 套件管理器

## 專案結構

```
power-funnel/
├── plugin.php                          # 外掛入口點
├── inc/classes/
│   ├── Bootstrap.php                   # Hook 註冊 & 腳本載入
│   ├── Applications/                   # 應用服務層
│   │   ├── ActivityApi.php             # 活動 REST API
│   │   ├── OptionApi.php               # 設定 REST API
│   │   ├── RegisterActivityViaLine.php # LINE 報名處理
│   │   └── SendLine.php               # LINE Carousel 訊息發送
│   ├── Domains/
│   │   ├── Admin/Entry.php             # 後台全螢幕入口
│   │   ├── Activity/Services/          # YouTube 活動服務
│   │   └── Workflow/Services/          # 工作流領域服務 (開發中)
│   ├── Infrastructure/
│   │   ├── Line/                       # LINE API 整合 (LIFF, Messaging)
│   │   ├── Youtube/                    # YouTube API 整合
│   │   └── Repositories/
│   │       ├── PromoLink/              # 推廣連結 CPT (pf_promo_link)
│   │       ├── Registration/           # 報名 CPT (pf_registration)
│   │       ├── Workflow/               # 工作流實例 CPT (pf_workflow)
│   │       └── WorkflowRule/           # 工作流規則 CPT (pf_workflow_rule)
│   │           └── NodeDefinitions/    # 節點定義 (EmailNode, WaitNode...)
│   ├── Contracts/DTOs/                 # 資料傳輸物件
│   │   ├── WorkflowRuleDTO.php         # 工作流規則 DTO
│   │   ├── WorkflowDTO.php             # 工作流實例 DTO (含執行引擎)
│   │   ├── NodeDTO.php                 # 節點實例 DTO
│   │   └── WorkflowResultDTO.php       # 節點執行結果 DTO
│   └── Shared/
│       ├── Constants/App.php           # 常數 (APP1_SELECTOR 等)
│       └── Enums/                      # 列舉
│           ├── ENode.php               # 節點類型 (10 種)
│           ├── ENodeType.php           # 節點分類
│           ├── ETriggerPoint.php       # 觸發時機點
│           ├── EWorkflowStatus.php     # 工作流狀態
│           └── EWorkflowRuleStatus.php # 工作流規則狀態
├── js/src/
│   ├── main.tsx                        # React 入口點
│   ├── App1.tsx                        # 主應用 (Refine.dev + Router)
│   ├── App2.tsx                        # Metabox/LIFF 應用
│   ├── pages/
│   │   ├── PromoLinks/                 # ✅ 推廣連結管理 (List/Edit)
│   │   └── Settings/                   # ✅ 設定頁面
│   ├── resources/                      # Refine 資源定義
│   ├── types/                          # TypeScript 型別定義
│   └── components/                     # 共用元件
├── composer.json / package.json
├── vite.config.ts / tailwind.config.js
└── .claude/rules/               # 開發指引
```

## 工作流引擎架構

### 核心概念
- **WorkflowRule** (CPT: `pf_workflow_rule`)：管理員設計的工作流模板，包含觸發點與節點序列
- **Workflow** (CPT: `pf_workflow`)：WorkflowRule 被觸發後創建的執行實例，記錄每個節點的執行狀態
- **NodeDefinition**：節點邏輯定義（後端可擴充，透過 `power_funnel/workflow_rule/node_definitions` filter 註冊）
- **TriggerPoint**：WordPress hook 名稱，透過 `power_funnel/workflow_rule/trigger_points` filter 擴充

### 節點類型 (ENode)
| 節點 | 狀態 | 說明 |
|------|------|------|
| `email` | ✅ 已實作 | 發送 Email |
| `wait` | 🚧 Stub | 等待指定時間 |
| `wait_until` | 🚧 Stub | 等待至指定時間 |
| `time_window` | 🚧 Stub | 等待至時間窗口 |
| `line` | 🚧 Stub | 發送 LINE 訊息 |
| `sms` | 🚧 Stub | 發送 SMS |
| `webhook` | 🚧 Stub | 發送 Webhook |
| `yes_no_branch` | 🚧 Stub | 是/否分支 |
| `split_branch` | 🚧 Stub | 多路分支 |
| `tag_user` | 🚧 Stub | 標籤用戶 |

### 工作流執行流程
1. WorkflowRule 被發佈 → 在其 `trigger_point` hook 上掛載監聽
2. Trigger 觸發 → `Workflow` CPT 被創建，初始狀態為 `running`
3. WP `transition_post_status` → 觸發 `power_funnel/workflow/running` action
4. `WorkflowDTO::try_execute()` → 按順序執行每個 `NodeDTO`
5. 每個節點執行完畢呼叫 `do_next()` → 觸發下一個節點
6. 全部完成 → 狀態改為 `completed`；任一失敗 → 狀態改為 `failed`

## 架構說明

1. **渲染方式**: PHP 輸出 `id="power_funnel_app"` (App::APP1_SELECTOR) 的 DOM 容器，React 掛載渲染 SPA
2. **資料流**: 透過 WordPress REST API (`/wp-json/`) 提供資料，前端使用 Refine.dev + React Query 管理
3. **全螢幕模式**: `Domains\Admin\Entry` 類別實現 WordPress 後台全螢幕介面
4. **環境變數**: 後端透過 `wp_localize_script` 傳遞至前端 `window.power_funnel_data.env`
5. **可擴充節點**: 透過 `power_funnel/workflow_rule/node_definitions` filter 在後端注入自訂節點定義

## 建構指令

| 指令 | 說明 |
|------|------|
| `pnpm run bootstrap` | 安裝所有依賴 (pnpm install + composer install) |
| `pnpm dev` | 啟動前端開發伺服器 |
| `pnpm build` | 生產建構 |
| `pnpm preview` | 預覽建構結果 |
| `pnpm lint` | ESLint + phpcbf 檢查 |
| `pnpm lint:fix` | 自動修復 ESLint 問題 |
| `pnpm format` | Prettier 格式化 tsx 檔案 |
| `composer lint` | phpcs 代碼風格檢查 |
| `composer analyse` | PHPStan 靜態分析 |

## 重要注意事項

### 必須遵守
1. **PHP 代碼**: 永遠使用 `declare(strict_types=1)` 並遵循 WordPress Coding Standards
2. **命名空間**: PHP 類別必須使用 `J7\PowerFunnel` 命名空間
3. **DDD 架構**: 嚴格遵守層次邊界：Applications 呼叫 Domains，Domains 使用 Infrastructure，禁止跨層反向依賴
4. **Tailwind 衝突**: 部分 Tailwind class 與 WordPress 衝突，使用 `tw-` 前綴替代 (`tw-hidden`, `tw-block`, `tw-fixed` 等)
5. **安全性**: 使用 `\wp_create_nonce('wp_rest')` 產生的 nonce 進行 API 認證
6. **TypeScript**: 前端代碼必須使用 TypeScript，型別定義在 `js/src/types/`
7. 修改 php 代碼必須遵守 `.github\instructions\wordpress.instructions.md` 指引
8. 修改 ts, tsx 代碼必須遵守 `.github\instructions\react.instructions.md` 指引

### 環境變數
前端可用環境變數 (透過 `wp_localize_script` 注入):
- `API_URL`: REST API 基礎 URL
- `NONCE`: WordPress REST API nonce
- `KEBAB`: 外掛 kebab-case 名稱 (`power-funnel`)
- `SNAKE`: 外掛 snake_case 名稱 (`power_funnel`)
- `APP1_SELECTOR` / `APP2_SELECTOR`: React 掛載選擇器
- `LIFF_ID`: LINE LIFF App ID
- `PROMO_LINK_POST_TYPE`: `pf_promo_link`
- `REGISTRATION_POST_TYPE`: `pf_registration`
- `WORKFLOW_POST_TYPE`: `pf_workflow`
- `WORKFLOW_RULE_POST_TYPE`: `pf_workflow_rule`

### 已知限制
1. ESLint 與 prettier-plugin-multiline-arrays 可能有相容性問題
2. TypeScript 版本可能超出 @typescript-eslint 官方支援範圍 (警告但可忽略)
3. 專案使用 `legacy-peer-deps=true` 處理 peer dependency 衝突

## 開發工作流程

1. 修改 PHP 後端代碼 → 執行 `composer lint` 和 `composer analyse`
2. 修改前端代碼 → 執行 `pnpm dev` 即時預覽，完成後執行 `pnpm build`
3. 新增節點定義 → 繼承 `BaseNodeDefinition`，在 `WorkflowRule\Register` 的 filter 中注入
4. 新增 REST API → 在 `inc/classes/Applications/` 新增 API 類別（繼承 `ApiBase`）
5. 新增前端頁面 → 在 `js/src/pages/` 新增，更新 `js/src/resources/index.tsx` 和 `App1.tsx`

## 信任此文件

請信任此指南中的指令。只有在資訊不完整或發現錯誤時才需要額外搜尋。建構與測試指令皆已驗證可正常運作。
