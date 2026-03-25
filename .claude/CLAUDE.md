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

- **WorkflowResultDTO 結果碼**：200=成功、301=跳過（match_callback 不符）、500=失敗
- **WaitNode**：使用 Action Scheduler (`as_schedule_single_action`) 排程延遲，到期後重新觸發 `power_funnel/workflow/running`
- **ParamHelper**：處理 `context` 參數替換和模板字串取代

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
pf/trigger/{trigger_name}                    # 觸發工作流的 action（ETriggerPoint enum）
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
2. **新增觸發點** → `ETriggerPoint` enum 加 case，適當時機 `do_action`
3. **新增 REST API** → `Applications/` 繼承 `ApiBase`，在 `Bootstrap::register_hooks()` 調用
4. **新增前端頁面** → `js/src/pages/` 新增，更新 `resources/index.tsx` 和 `App1.tsx` 路由

## 開發環境

- **wp-env** 設定：WordPress 6.8、PHP 8.2、port 8894
- 依賴外掛：powerhouse（自動下載）
- `WP_ENVIRONMENT_TYPE=local` 時使用 `vendor/autoload.php`，production 使用 `vendor-prefixed/autoload.php`

## 已知限制

1. ESLint 與 `prettier-plugin-multiline-arrays` 可能有相容性問題
2. TypeScript 版本可能超出 `@typescript-eslint` 官方支援範圍
3. 使用 `legacy-peer-deps=true` 處理 peer dependency 衝突
4. `ENode` enum 中 `SPILT_BRANCH` 為 typo（value 正確為 `split_branch`）
5. PHPStan bootstrapFiles 包含本機硬編碼路徑（`C:\Users\User\DEV\...`）
6. `@typescript-eslint/no-explicit-any` 設為 `warn` 而非 `error`
7. ReactFlow 節點編輯器 UI 尚未開始開發（核心待辦）

## 相關 SKILL

- `.claude/skills/react-flow-master/SKILL.md` — ReactFlow 節點編輯器開發指引
