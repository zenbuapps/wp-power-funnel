# Power Funnel — 專案開發指引

## 專案概述

WordPress 外掛（PHP 8.1+ / React 18 / TypeScript 5.5）。兩大核心功能：
1. **LINE 報名漏斗**：自動抓取 YouTube 直播場次 → 透過推廣連結（PromoLink）發送 LINE Carousel → 用戶 Postback 報名 → 狀態通知與自動審核
2. **工作流引擎**：管理員以 ReactFlow 節點編輯器設計 WorkflowRule → 觸發後建立 Workflow 實例 → 逐節點執行（含 WaitNode 排程）

後端 PHP（WordPress + DDD 分層），前端 React SPA（Refine.dev + Ant Design）。前後端透過 REST API 溝通。

詳細規範見 `.claude/rules/` 目錄。

## 技術棧總覽

| 層 | 技術 |
|----|------|
| 後端語言 | PHP 8.1+（`declare(strict_types=1)` 必須） |
| 後端框架 | WordPress 6.8+、WP REST API |
| 前端語言 | TypeScript 5.5（strict mode） |
| 前端框架 | React 18 + Refine.dev 4.x + Ant Design 5 + React Router v7 |
| 節點編輯器 | @xyflow/react（ReactFlow v12+）— 核心待開發 |
| LIFF | @line/liff 2.x |
| 狀態管理 | Jotai + TanStack Query v4 |
| 富文本編輯 | BlockNote 0.30 |
| 建構工具 | Vite + @kucrut/vite-for-wp（port 5188） |
| CSS | Tailwind CSS 3（`important: '#tw'`，衝突 class 需 `tw-` 前綴） |
| PHP 依賴管理 | Composer + Strauss（namespace prefix `PowerFunnel\`） |
| PHP 代碼品質 | PHPCS（WordPress-Core）+ PHPStan Level 9 |
| 前端代碼品質 | ESLint + Prettier |
| PHP 測試 | PHPUnit 9.6（integration tests） |
| E2E 測試 | Playwright |
| 套件管理 | pnpm 10.x |

## 建構指令

| 指令 | 說明 |
|------|------|
| `pnpm run bootstrap` | 安裝所有依賴（pnpm install + composer install） |
| `pnpm dev` | Vite 開發伺服器（port 5188） |
| `pnpm build` | 生產建構（js/dist/） |
| `pnpm lint` | ESLint + phpcbf |
| `pnpm format` | Prettier 格式化 tsx |
| `composer lint` | phpcs 代碼風格檢查 |
| `composer analyse` | PHPStan Level 9 靜態分析（需 6G 記憶體） |
| `composer strauss` | 重新生成 vendor-prefixed/ |
| `composer test` | PHPUnit 整合測試 |
| `composer test:smoke` | PHPUnit smoke 測試群組 |

## CPT 一覽

| Post Type | 用途 | 自訂狀態 |
|-----------|------|----------|
| `pf_promo_link` | 推廣連結（綁定活動篩選條件） | 標準 WP |
| `pf_registration` | 活動報名紀錄 | pending / success / rejected / failed / cancelled |
| `pf_workflow_rule` | 工作流規則模板（ReactFlow 節點圖） | publish / draft / trash |
| `pf_workflow` | 工作流執行實例 | running / completed / failed |

## 工作流引擎

```
WorkflowRule (設計時) → trigger_point 觸發 → Workflow (執行時)
    ├── NodeDTO[0].try_execute() → NodeDefinition.execute()
    ├── NodeDTO[1].try_execute() → ...
    └── 全部完成 → completed / 任一失敗 → failed
```

### 最高指導原則：Serializable Context Callable

> **任何 context 都必須以「可序列化的 callable + params」形式儲存，絕不儲存完整物件或使用 Closure。**

工作流引擎的 context 傳遞機制遵循**延遲求值（Deferred Evaluation）**模式：

```php
// ✅ 正確：string[] callable — 可被 serialize() / unserialize()
$context_callable_set = [
    'callable' => [TriggerPointService::class, 'resolve_registration_context'],
    'params'   => [ $post_id ],
];

// ❌ 禁止：Closure — 無法被 serialize()，WaitNode 恢復後 context 會丟失
$context_callable_set = [
    'callable' => static function ( int $id ): array { ... },
    'params'   => [ $post_id ],
];
```

**規則：**

1. `callable` 型別必須為 `string`（函數名）或 `string[]`（`[ClassName::class, 'method']`）— 禁止 Closure
2. `params` 必須為純值陣列（int / string / array），禁止物件
3. 呼叫 `call_user_func_array($callable, $params)` 後回傳 `array<string, string>` 作為 workflow context
4. Context 在節點**執行時**才求值，不在觸發時快照 — 確保 WaitNode 延遲後仍能取得最新資料
5. `context_callable_set` 透過 `wp_postmeta` 持久化，必須能安全通過 WordPress 的 `serialize()` / `unserialize()`

**為什麼：** Workflow 可能被 WaitNode 暫停數小時甚至數天，期間 context_callable_set 儲存在 `wp_postmeta`。Action Scheduler 恢復執行時從 DB 反序列化 — Closure 在此處會靜默失敗，導致 context 變成空陣列。

**resolve 方法命名慣例：** `resolve_{domain}_context()`，如 `resolve_registration_context()`、`resolve_line_context()`，置於對應的 Service class 中作為 `public static` 方法。

### 核心元件

- **WorkflowResultDTO 結果碼**：200=成功、301=跳過（match_callback 不符）、500=失敗
- **WaitNode**：使用 Action Scheduler (`as_schedule_single_action`) 排程延遲，到期後重新觸發 `power_funnel/workflow/running`
- **ParamHelper**：處理 `context` 參數替換和模板字串取代
- **RecursionGuard**：靜態深度計數器，`MAX_DEPTH=3`，防止工作流觸發工作流的無限遞迴；超過時呼叫 `Repository::create_failed_from_recursion_exceeded()`
- **TriggerPointService**：集中橋接所有業務域事件到對應 `pf/trigger/*` hook，避免觸發邏輯散落各處
- **ActivitySchedulerService**：整合 Action Scheduler 支援時間型觸發點（ACTIVITY_STARTED、ACTIVITY_BEFORE_START）

### trigger_point meta 格式（v2，向下相容）

```json
{ "hook": "pf/trigger/activity_before_start", "params": { "before_minutes": 30 } }
```

舊版純字串格式（僅含 hook 名稱）仍受支援，由 `WorkflowRuleDTO::parse_trigger_point_meta()` 自動解析。

### ETriggerPoint 觸發點清單

| 類別 | case | hook value | 說明 |
|------|------|-----------|------|
| P0 報名狀態 | `REGISTRATION_CREATED` | `pf/trigger/registration_created` | 已棄用，保留相容 |
| P0 報名狀態 | `REGISTRATION_APPROVED` | `pf/trigger/registration_approved` | 報名審核通過 |
| P0 報名狀態 | `REGISTRATION_REJECTED` | `pf/trigger/registration_rejected` | 報名被拒絕 |
| P0 報名狀態 | `REGISTRATION_CANCELLED` | `pf/trigger/registration_cancelled` | 報名取消 |
| P0 報名狀態 | `REGISTRATION_FAILED` | `pf/trigger/registration_failed` | 報名失敗 |
| P1 LINE 互動 | `LINE_FOLLOWED` | `pf/trigger/line_followed` | 用戶關注 LINE 官方帳號 |
| P1 LINE 互動 | `LINE_UNFOLLOWED` | `pf/trigger/line_unfollowed` | 用戶取消關注 |
| P1 LINE 互動 | `LINE_MESSAGE_RECEIVED` | `pf/trigger/line_message_received` | 收到 LINE 訊息 |
| P1 LINE 互動 | `LINE_POSTBACK_RECEIVED` | `pf/trigger/line_postback_received` | 收到 LINE Postback；支援 trigger_params.postback_action 過濾 |
| P1 LINE 群組 | `LINE_JOIN` | `pf/trigger/line_join` | 枚舉存根：Bot 被加入群組 |
| P1 LINE 群組 | `LINE_LEAVE` | `pf/trigger/line_leave` | 枚舉存根：Bot 被移出群組 |
| P1 LINE 群組 | `LINE_MEMBER_JOINED` | `pf/trigger/line_member_joined` | 枚舉存根：新成員加入群組 |
| P1 LINE 群組 | `LINE_MEMBER_LEFT` | `pf/trigger/line_member_left` | 枚舉存根：成員離開群組 |
| P2 工作流引擎 | `WORKFLOW_COMPLETED` | `pf/trigger/workflow_completed` | 工作流完成 |
| P2 工作流引擎 | `WORKFLOW_FAILED` | `pf/trigger/workflow_failed` | 工作流失敗 |
| P3 活動時間 | `ACTIVITY_STARTED` | `pf/trigger/activity_started` | 活動開始時（Action Scheduler） |
| P3 活動時間 | `ACTIVITY_BEFORE_START` | `pf/trigger/activity_before_start` | 活動開始前 N 分鐘（params.before_minutes） |
| P3 活動時間 | `ACTIVITY_ENDED` | `pf/trigger/activity_ended` | 枚舉存根，目前無實作 |
| P3 用戶行為 | `USER_TAGGED` | `pf/trigger/user_tagged` | 用戶被貼標籤（由 TagUserNode 呼叫） |
| P3 用戶行為 | `PROMO_LINK_CLICKED` | `pf/trigger/promo_link_clicked` | 枚舉存根，目前無實作 |
| P4 WooCommerce 訂單 | `ORDER_COMPLETED` | `pf/trigger/order_completed` | 訂單完成（`woocommerce_order_status_completed`） |
| P4 WooCommerce 訂單 | `ORDER_PENDING` | `pf/trigger/order_pending` | 訂單待付款（`woocommerce_order_status_pending`） |
| P4 WooCommerce 訂單 | `ORDER_PROCESSING` | `pf/trigger/order_processing` | 訂單處理中（`woocommerce_order_status_processing`） |
| P4 WooCommerce 訂單 | `ORDER_ON_HOLD` | `pf/trigger/order_on_hold` | 訂單保留中（`woocommerce_order_status_on-hold`） |
| P4 WooCommerce 訂單 | `ORDER_CANCELLED` | `pf/trigger/order_cancelled` | 訂單已取消（`woocommerce_order_status_cancelled`） |
| P4 WooCommerce 訂單 | `ORDER_REFUNDED` | `pf/trigger/order_refunded` | 訂單已退款（全額，`woocommerce_order_status_refunded`） |
| P4 WooCommerce 訂單 | `ORDER_FAILED` | `pf/trigger/order_failed` | 訂單失敗（`woocommerce_order_status_failed`） |
| P5 顧客行為 | `CUSTOMER_REGISTERED` | `pf/trigger/customer_registered` | 新顧客註冊（`user_register`，含 WC 結帳建立帳號） |
| P5 訂閱 | `SUBSCRIPTION_INITIAL_PAYMENT` | `pf/trigger/subscription_initial_payment` | 訂閱首次付款完成（powerhouse hook） |
| P5 訂閱 | `SUBSCRIPTION_FAILED` | `pf/trigger/subscription_failed` | 訂閱失敗（powerhouse hook） |
| P5 訂閱 | `SUBSCRIPTION_SUCCESS` | `pf/trigger/subscription_success` | 訂閱從失敗到成功（powerhouse hook） |
| P5 訂閱 | `SUBSCRIPTION_RENEWAL_ORDER` | `pf/trigger/subscription_renewal_order` | 續訂訂單建立（powerhouse hook） |
| P5 訂閱 | `SUBSCRIPTION_END` | `pf/trigger/subscription_end` | 訂閱結束（powerhouse hook） |
| P5 訂閱 | `SUBSCRIPTION_TRIAL_END` | `pf/trigger/subscription_trial_end` | 試用期結束（powerhouse hook） |
| P5 訂閱 | `SUBSCRIPTION_PREPAID_END` | `pf/trigger/subscription_prepaid_end` | 預付期結束（powerhouse hook） |

## Hook 命名慣例

```
power_funnel/workflow/{status}               # 工作流狀態變更 action
power_funnel/workflow/transition_status      # 任何狀態轉換 action
power_funnel/workflow_rule/node_definitions  # filter: 注入節點定義
power_funnel/workflow_rule/trigger_points    # filter: 注入觸發點
power_funnel/registration/{status}           # 報名狀態變更 action（pending 時 priority=20 觸發自動審核）
power_funnel/registration/can_register       # filter: 是否允許報名
power_funnel/liff_callback                   # LIFF 回調 action
power_funnel/line/webhook/{type}/{action}    # LINE webhook action（如 postback/register）
power_funnel/line/webhook/{type}             # LINE webhook type-only hook（WebhookService 新增，供 TriggerPointService 監聽）
pf/trigger/{trigger_name}                    # 觸發工作流的 action（ETriggerPoint enum）
power_funnel/activity_trigger/started        # ActivitySchedulerService：活動開始 Action Scheduler hook
power_funnel/activity_trigger/before_start   # ActivitySchedulerService：活動開始前 Action Scheduler hook
```

## 前端環境變數

透過 `window.power_funnel_data.env` 注入，使用 `useEnv<TEnv>()` hook 存取：

`SITE_URL` / `API_URL` / `NONCE` / `KEBAB` (`power-funnel`) / `SNAKE` (`power_funnel`) / `APP_NAME` / `APP1_SELECTOR` / `APP2_SELECTOR` / `LIFF_ID` / `IS_LOCAL` / `CURRENT_USER_ID` / `CURRENT_POST_ID` / `PERMALINK` / `ELEMENTOR_ENABLED` / `PROMO_LINK_POST_TYPE` / `REGISTRATION_POST_TYPE` / `WORKFLOW_POST_TYPE` / `WORKFLOW_RULE_POST_TYPE`

## 必須遵守

1. **PHP**：每個檔案 `declare(strict_types=1)` + WordPress Coding Standards + PHPStan Level 9
2. **PHP Namespace**：`J7\PowerFunnel`，根目錄對應 `inc/classes/`
3. **DDD 分層**：Applications > Domains > Infrastructure > Contracts > Shared，禁止反向依賴；Shared 不引用其他層
4. **PHP Hook 註冊**：統一使用靜態方法 `register_hooks()`，在 `Bootstrap::register_hooks()` 中調用
5. **PHPDoc**：所有 PHP 函數需有繁體中文 PHPDoc
6. **TypeScript**：禁止 `any`（ESLint 設為 warn），Props 使用 `TProps` 命名慣例
7. **Tailwind**：`important: '#tw'`，與 WordPress 衝突的 class 使用 `tw-` 前綴（`tw-hidden`、`tw-block`、`tw-flex`、`tw-fixed`、`tw-inline`、`tw-columns-1`、`tw-columns-2`）
8. **Strauss**：`namespace_prefix: PowerFunnel\`，僅前綴 guzzlehttp 系列套件
9. **溝通與註解風格**：所有程式碼註解使用繁體中文，技術名詞與程式碼維持英文

## 開發流程

1. **新增節點定義** → 繼承 `BaseNodeDefinition`，在 `WorkflowRule\Register::register_default_node_definitions` 注入
2. **新增觸發點** → `ETriggerPoint` enum 加 case，在 `TriggerPointService` 加對應監聽器並 `do_action`；若為時間型觸發點則在 `ActivitySchedulerService` 加排程邏輯
3. **新增 REST API** → `Applications/` 繼承 `ApiBase`，在 `Bootstrap::register_hooks()` 調用
4. **新增前端頁面** → `js/src/pages/` 新增，更新 `resources/index.tsx` 和 `App1.tsx` 路由

## 開發環境

- **wp-env** 設定：WordPress 6.8、PHP 8.2、port 8894
- 依賴外掛：powerhouse（自動下載）
- `WP_ENVIRONMENT_TYPE=local` 時使用 `vendor/autoload.php`，production 使用 `vendor-prefixed/autoload.php`

## AI 開發工具

### 瀏覽器操作（Playwright）

- 使用 `/playwright-cli` skill 可啟動瀏覽器進行頁面操作、表單填寫、截圖、E2E 測試
- 連接模式：`--remote-debugging-port=9222`，使用用戶的 Chrome Profile
- 啟動 Chrome Debug 模式：`~/.claude/scripts/chrome-debug-start.ps1`（port 9222）
- 網站 URL 與登入帳密存放於 `.env`
- 若 `https://local-turbo.powerhouse.tw` 連不上，可能是 Cloudflare Tunnel 未啟動，執行 `C:\Users\User\LocalSites\turbo\app\public\start-tunnel.sh` 開啟

### 視覺檢查（Chrome MCP）

- 使用 `/chrome` 命令可查看網站頁面的視覺呈現
- **僅在需要視覺能力時使用**（如 UI 排版確認、截圖比對）

### 資料庫操作（MySQL MCP）

- `.mcp.json` 已設定 MySQL MCP Server，可直接對資料庫執行 CRUD
- 連線資訊：`127.0.0.1:10085`，DB=`local`，user=`root`
- 適用場景：查詢資料狀態、驗證 API 寫入結果、除錯資料問題
- DB 備份：`C:\Users\User\LocalSites\turbo\app\public\local-20260401-044201.sql`（如需還原）

## 已知限制

1. ESLint 與 `prettier-plugin-multiline-arrays` 可能有相容性問題
2. TypeScript 版本可能超出 `@typescript-eslint` 官方支援範圍
3. 使用 `legacy-peer-deps=true` 處理 peer dependency 衝突
4. PHPStan bootstrapFiles 包含本機硬編碼路徑（`C:\Users\User\DEV\...`）
5. `@typescript-eslint/no-explicit-any` 設為 `warn` 而非 `error`
6. ReactFlow 節點編輯器 UI 尚未開始開發（核心待辦）
7. `ETriggerPoint::ACTIVITY_ENDED` 和 `ETriggerPoint::PROMO_LINK_CLICKED` 目前僅為枚舉存根，無實際觸發機制（無結束時間資料來源 / 無點擊追蹤機制）

## 相關 SKILL

- `.claude/skills/react-flow-master/SKILL.md` — ReactFlow 節點編輯器開發指引
