# Power Funnel — AI Agent 開發指南

## 專案概述

Power Funnel 是一個 WordPress 外掛，核心功能包含：
1. 自動抓取 YouTube 直播場次，讓用戶可以透過 LINE 報名
2. **工作流引擎 (Workflow Engine)**：讓管理員透過 ReactFlow 節點編輯器設計工作流規則 (`WorkflowRule`)，當觸發條件滿足時，自動建立 `Workflow` 實例並逐節點執行

採用現代化混合架構：後端 PHP (WordPress + DDD)，前端 React SPA 渲染於 WordPress 後台。

**專案狀態**: 開發中
- 後端 DDD 架構、4 個 CPT、工作流執行引擎、LINE/YouTube 整合、PromoLink/Activity 功能
- 前端 PromoLinks CRUD、Settings 頁面、workflow-rules 資源定義
- 工作流節點：EmailNode 已完成並註冊，WaitNode 已實作但未註冊，其他 8 種為 stub
- **ReactFlow 節點編輯器 UI — 尚未開始開發（核心待辦）**

## 技術棧

### 後端 (PHP)
- PHP 8.1+ / WordPress 5.7+
- WordPress Coding Standards (WPCS) + PHPStan Level 9
- Composer 依賴管理 / PSR-4 自動載入 (namespace: `J7\PowerFunnel`)
- DDD 架構分層：`Applications` / `Domains` / `Infrastructure` / `Contracts` / `Shared`
- Strauss vendor namespace prefixing (prefix: `PowerFunnel\`)
- Action Scheduler (`as_schedule_single_action` / `as_enqueue_async_action`)

### 前端 (JavaScript/TypeScript)
- React 18 + TypeScript 5.5
- @xyflow/react (ReactFlow) — 節點編輯器（待開發）
- Vite + @kucrut/vite-for-wp（建構工具）
- Ant Design 5 / antd-toolkit (UI 框架)
- Tailwind CSS + Sass + daisyUI
- Refine.dev 4.x (資料管理框架)
- React Query v4 (TanStack)
- React Router v7
- @line/liff (LINE LIFF SDK)
- BlockNote (區塊編輯器)
- Jotai (原子化狀態管理)
- Zod (Schema 驗證)
- pnpm 套件管理器

## 專案結構

```
power-funnel/
├── plugin.php                          # 外掛入口（PluginTrait + SingletonTrait）
├── inc/classes/                        # PHP 源碼（PSR-4 根目錄）
│   ├── Bootstrap.php                   # Hook 註冊 & 腳本載入
│   ├── Applications/                   # 應用服務層（REST API 端點）
│   │   ├── ActivityApi.php             # GET /power-funnel/activities
│   │   ├── OptionApi.php               # GET/POST /power-funnel/options
│   │   ├── RegisterActivityViaLine.php # LINE 報名 + 自動審核 + 通知
│   │   └── SendLine.php                # LINE Carousel 訊息發送
│   ├── Compatibility/                  # 版本升級相容性（Action Scheduler）
│   ├── Contracts/DTOs/                 # 資料傳輸物件
│   │   ├── ActivityDTO.php             # 活動（可來自外部，非 WP_Post）
│   │   ├── NodeDTO.php                 # 節點實例（含 match_callback 條件執行）
│   │   ├── PromoLinkDTO.php            # 推廣連結
│   │   ├── RegistrationDTO.php         # 報名紀錄
│   │   ├── TriggerPointDTO.php         # 觸發時機
│   │   ├── UserDTO.php                 # 用戶
│   │   ├── WorkflowDTO.php             # 工作流實例（含執行引擎 try_execute）
│   │   ├── WorkflowResultDTO.php       # 節點執行結果
│   │   └── WorkflowRuleDTO.php         # 工作流規則（含 register 掛載觸發）
│   ├── Domains/
│   │   ├── Admin/Entry.php             # 後台全螢幕 SPA 入口
│   │   └── Activity/Services/          # YouTube 活動聚合服務
│   ├── Infrastructure/
│   │   ├── Line/                       # LINE API 整合（LIFF / Messaging / Webhook）
│   │   ├── Youtube/                    # YouTube API 整合（OAuth / Live / Service）
│   │   └── Repositories/
│   │       ├── PromoLink/Register.php  # CPT: pf_promo_link
│   │       ├── Registration/           # CPT: pf_registration + Repository CRUD
│   │       ├── Workflow/               # CPT: pf_workflow + 生命週期 + 執行啟動
│   │       └── WorkflowRule/           # CPT: pf_workflow_rule + 節點定義注入
│   │           └── NodeDefinitions/    # EmailNode（已完成）、WaitNode（已實作）
│   └── Shared/
│       ├── Constants/App.php           # APP1_SELECTOR / APP2_SELECTOR
│       └── Enums/                      # 10 個列舉
├── js/src/
│   ├── main.tsx                        # React 入口（雙 App 掛載）
│   ├── App1.tsx                        # 管理後台 SPA（Refine + HashRouter）
│   ├── App2.tsx                        # LIFF 報名畫面
│   ├── pages/
│   │   ├── PromoLinks/                 # 推廣連結 List / Edit
│   │   └── Settings/                   # LINE / YouTube 設定
│   ├── resources/index.tsx             # Refine 資源定義（含 workflow-rules）
│   ├── api/                            # API 層（CRUD 函式）
│   ├── components/                     # 共用元件
│   ├── types/                          # TypeScript 型別
│   └── utils/                          # 工具函式
├── spec/                               # 規格文件
├── tests/e2e/                          # Playwright E2E 測試
├── composer.json / package.json
├── vite.config.ts / tailwind.config.js
└── .claude/                            # AI Agent 開發指引
```

## 工作流引擎架構

### 核心概念
- **WorkflowRule** (CPT: `pf_workflow_rule`)：管理員設計的工作流模板，包含觸發點與節點序列
- **Workflow** (CPT: `pf_workflow`)：WorkflowRule 被觸發後創建的執行實例
- **NodeDefinition**：節點邏輯定義，透過 `power_funnel/workflow_rule/node_definitions` filter 註冊
- **TriggerPoint**：`pf/trigger/` 前綴的 hook，透過 `power_funnel/workflow_rule/trigger_points` filter 擴充

### 節點類型 (ENode — 10 種)
| 節點 | 狀態 | 類型 | 說明 |
|------|------|------|------|
| `email` | 已完成 + 已註冊 | SEND_MESSAGE | 發送 Email（支援訊息模板） |
| `wait` | 已實作未註冊 | ACTION | 排程等待（Action Scheduler） |
| `wait_until` | Stub | ACTION | 等待至指定時間 |
| `time_window` | Stub | ACTION | 等待至時間窗口 |
| `line` | Stub | SEND_MESSAGE | 發送 LINE 訊息 |
| `sms` | Stub | SEND_MESSAGE | 發送 SMS |
| `webhook` | Stub | SEND_MESSAGE | 發送 Webhook |
| `yes_no_branch` | Stub | ACTION | 是/否分支 |
| `split_branch` | Stub | ACTION | 多路分支 |
| `tag_user` | Stub | ACTION | 標籤用戶 |

### 工作流執行流程
1. WorkflowRule 被發佈 → `WorkflowRuleDTO::register()` 在其 `trigger_point` hook 上掛載監聽
2. Trigger 觸發 → `Workflow\Repository::create_from()` 建立 pf_workflow CPT（狀態 `running`）
3. `transition_post_status` → `Workflow\Register::register_lifecycle()` → 觸發 `power_funnel/workflow/running`
4. `Workflow\Register::start_workflow()` → `WorkflowDTO::try_execute()` → 按順序執行 `NodeDTO`
5. 每個 `NodeDTO::try_execute()` 呼叫 `NodeDefinition::execute()` → 成功後 `do_next()`
6. 全部完成 → 狀態 `completed`；任一失敗 → 狀態 `failed`

## CPT 一覽

| 常數 | Post Type | 用途 | 狀態集合 |
|------|-----------|------|----------|
| `WORKFLOW_RULE_POST_TYPE` | `pf_workflow_rule` | 工作流規則模板 | publish / draft / trash |
| `WORKFLOW_POST_TYPE` | `pf_workflow` | 工作流執行實例 | running / completed / failed |
| `PROMO_LINK_POST_TYPE` | `pf_promo_link` | 推廣連結 | 標準 WP 狀態 |
| `REGISTRATION_POST_TYPE` | `pf_registration` | 活動報名 | success / rejected / failed / pending / cancelled |

## REST API 端點

| 方法 | 端點 | 類別 | 說明 |
|------|------|------|------|
| GET | `/power-funnel/activities` | ActivityApi | 查詢活動列表 |
| GET | `/power-funnel/options` | OptionApi | 取得 LINE/YouTube/GoogleOAuth 設定 |
| POST | `/power-funnel/options` | OptionApi | 儲存設定 |
| POST | `/power-funnel/revoke-google-oauth` | OptionApi | 撤銷 Google OAuth |

通用 CRUD 透過 Powerhouse 的 `/v2/powerhouse/posts` 端點處理各 CPT。

## 架構說明

1. **渲染方式**: PHP 輸出 `#power_funnel_app` (App1) / `#power_funnel_liff_app` (App2) 容器，React 掛載 SPA
2. **資料流**: REST API → Refine.dev dataProvider → React Query 快取
3. **全螢幕模式**: `Entry::maybe_output_admin_page()` 偵測 `current_screen` 後攔截輸出
4. **環境變數**: `Bootstrap::enqueue_script()` 透過 `wp_localize_script` 注入 `window.power_funnel_data.env`
5. **Vite 整合**: `@kucrut/vite-for-wp` 處理開發/生產模式的資源載入
6. **LIFF 流程**: App2 初始化 LINE LIFF → 取得用戶 Profile → 送回後端 → 發 Carousel → 關閉視窗

## 建構指令

| 指令 | 說明 |
|------|------|
| `pnpm run bootstrap` | 安裝所有依賴 (pnpm + composer) |
| `pnpm dev` | Vite 開發伺服器 (port 5188) |
| `pnpm build` | 生產建構 (js/dist/) |
| `pnpm lint` | ESLint + phpcbf |
| `pnpm format` | Prettier 格式化 tsx |
| `composer lint` | phpcs 代碼風格檢查 |
| `composer analyse` | PHPStan Level 9 靜態分析 (需 6G 記憶體) |
| `composer strauss` | 重新生成 vendor-prefixed/ |

## 重要注意事項

### 必須遵守
1. **PHP 代碼**: `declare(strict_types=1)` + WordPress Coding Standards + PHPStan Level 9
2. **命名空間**: PHP 類別使用 `J7\PowerFunnel` namespace
3. **DDD 架構**: Applications → Domains → Infrastructure，禁止反向依賴。Shared 不引用其他層
4. **Tailwind 衝突**: 使用 `tw-` 前綴（`tw-hidden`, `tw-block`, `tw-flex`, `tw-fixed` 等）
5. **TypeScript**: 禁止 `any`，使用 `TProps` 命名慣例
6. **靜態方法優先**: Hook 註冊使用 `register_hooks()` 靜態方法
7. **繁體中文註解**: 所有 PHP 函數需有繁體中文 PHPDoc
8. **Strauss**: vendor 依賴在 local 用原始 vendor/，production 用 vendor-prefixed/（prefix: `PowerFunnel\`）

### 環境變數（前端可用）
透過 `useEnv<TEnv>()` 存取：
- `API_URL` / `NONCE` / `KEBAB` (`power-funnel`) / `SNAKE` (`power_funnel`)
- `APP1_SELECTOR` / `APP2_SELECTOR` / `LIFF_ID` / `IS_LOCAL`
- `PROMO_LINK_POST_TYPE` / `REGISTRATION_POST_TYPE` / `WORKFLOW_POST_TYPE` / `WORKFLOW_RULE_POST_TYPE`

### Data Provider 配置
| Provider | 基礎路徑 | 用途 |
|----------|---------|------|
| `default` | `/v2/powerhouse` | Powerhouse 通用 API |
| `power-funnel` | `/power-funnel` | 本外掛專屬 API |
| `wp-rest` | `/wp/v2` | WordPress Core |
| `wc-rest` | `/wc/v3` | WooCommerce |
| `wc-store` | `/wc/store/v1` | WC Store API |

### Hook 命名慣例
```
power_funnel/workflow/{status}                      # 工作流狀態變更 action
power_funnel/workflow/transition_status             # 任何狀態轉換 action
power_funnel/workflow_rule/node_definitions         # filter: 注入節點定義
power_funnel/workflow_rule/trigger_points           # filter: 注入觸發點
power_funnel/registration/{status}                  # 報名狀態變更 action
power_funnel/registration/can_register              # filter: 是否允許報名
power_funnel/liff_callback                          # LIFF 回調 action
power_funnel/line/webhook/{action_type}/{action}    # LINE webhook action
pf/trigger/{trigger_name}                           # 觸發工作流的 action
```

### 開發工作流程
1. 新增節點定義 → 繼承 `BaseNodeDefinition`，在 `WorkflowRule\Register::register_default_node_definitions` 注入
2. 新增觸發點 → `ETriggerPoint` enum 加 case，適當時機 `do_action`
3. 新增 REST API → `Applications/` 繼承 `ApiBase`，在 `Bootstrap::register_hooks()` 調用
4. 新增前端頁面 → `js/src/pages/` 新增，更新 `resources/index.tsx` 和 `App1.tsx` 路由

### 已知限制
1. ESLint 與 prettier-plugin-multiline-arrays 可能有相容性問題
2. TypeScript 版本可能超出 @typescript-eslint 官方支援範圍
3. 專案使用 `legacy-peer-deps=true` 處理 peer dependency 衝突
4. ENode enum 中 `SPILT_BRANCH` 為 typo（拼寫為 SPILT 而非 SPLIT），但 value 正確為 `split_branch`

## 信任此文件

請信任此指南中的指令。只有在資訊不完整或發現錯誤時才需要額外搜尋。
