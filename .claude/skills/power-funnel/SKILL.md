---
name: power-funnel
description: "Power Funnel — WordPress 漏斗自動化與工作流外掛開發指引。DDD 架構、LINE LIFF 整合、YouTube 直播報名、ReactFlow 節點編輯器、工作流引擎。使用 /power-funnel 觸發。"
origin: project-analyze
---

# power-funnel — 開發指引

> WordPress Plugin，整合 YouTube 直播抓取、LINE LIFF 報名流程，並提供基於節點的工作流引擎（WorkflowRule 設計 → Workflow 執行），採用 DDD 架構。

## When to Activate

當使用者在此專案中：
- 修改 `inc/classes/**/*.php`（DDD 後端邏輯）
- 修改 `js/src/**/*.tsx`（React/ReactFlow 前端）
- 新增工作流節點類型、LINE/YouTube 整合功能
- 詢問工作流引擎、Strauss 命名空間前綴、CPT 結構相關問題

## 架構概覽

**技術棧：**
- **語言**: PHP 8.1+（`declare(strict_types=1)`）
- **框架**: WordPress 5.7+、WooCommerce（選用）
- **PHP 依賴**: `linecorp/line-bot-sdk ^12.3`、`kucrut/vite-for-wp ^0.12`、`j7-dev/wp-plugin-trait`
- **Strauss**: vendor namespace prefix `PowerFunnel\`（target: `vendor-prefixed/`）
- **前端**: React 18 + TypeScript 5.5 + Refine.dev 4.x + Ant Design 5 + @xyflow/react + @line/liff + TanStack Query v4 + React Router v7 + Jotai + Zod + BlockNote
- **建構**: Vite + @kucrut/vite-for-wp（port 5188）
- **代碼品質**: PHPCS（WordPress-Core）、PHPStan Level 9、ESLint + Prettier

## 目錄結構

```
power-funnel/
├── plugin.php                                      # 主入口（PluginTrait + SingletonTrait）
├── inc/classes/                                     # PHP 源碼（PSR-4: J7\PowerFunnel）
│   ├── Bootstrap.php                                # Hook 註冊 & Vite 腳本載入
│   ├── Applications/
│   │   ├── ActivityApi.php                          # GET /power-funnel/activities
│   │   ├── OptionApi.php                            # GET/POST /power-funnel/options
│   │   ├── RegisterActivityViaLine.php              # LINE 報名 + 通知 + 自動審核
│   │   └── SendLine.php                             # LINE Carousel 訊息
│   ├── Compatibility/Compatibility.php              # 版本升級相容性
│   ├── Contracts/DTOs/                              # 9 個 DTO
│   │   ├── ActivityDTO.php                          # 活動（可來自外部）
│   │   ├── NodeDTO.php                              # 節點實例（含條件執行）
│   │   ├── PromoLinkDTO.php                         # 推廣連結
│   │   ├── RegistrationDTO.php                      # 報名紀錄
│   │   ├── WorkflowDTO.php                          # 工作流實例（含執行引擎）
│   │   ├── WorkflowRuleDTO.php                      # 工作流規則（含 register）
│   │   └── WorkflowResultDTO.php                    # 節點執行結果
│   ├── Domains/
│   │   ├── Admin/Entry.php                          # 後台全螢幕 SPA 入口
│   │   └── Activity/Services/ActivityService.php    # YouTube 活動聚合
│   ├── Infrastructure/
│   │   ├── Line/                                    # LINE API（LIFF / Messaging / Webhook）
│   │   ├── Youtube/                                 # YouTube API（OAuth / Live）
│   │   └── Repositories/
│   │       ├── PromoLink/Register.php               # CPT: pf_promo_link
│   │       ├── Registration/                        # CPT: pf_registration + Repository
│   │       ├── Workflow/                            # CPT: pf_workflow + 執行啟動
│   │       └── WorkflowRule/                        # CPT: pf_workflow_rule
│   │           ├── NodeDefinitions/                 # EmailNode, WaitNode
│   │           ├── ParamHelper.php                  # 參數取代 & 上下文處理
│   │           └── Repository.php                   # 查詢 + 節點定義注入
│   └── Shared/
│       ├── Constants/App.php                        # APP1_SELECTOR, APP2_SELECTOR
│       └── Enums/                                   # 10 個列舉
├── js/src/
│   ├── main.tsx                                     # React 掛載入口（雙 App）
│   ├── App1.tsx                                     # Refine 管理後台
│   ├── App2.tsx                                     # LIFF 報名畫面
│   ├── pages/
│   │   ├── PromoLinks/                              # 推廣連結 CRUD
│   │   └── Settings/                                # LINE / YouTube 設定
│   ├── resources/index.tsx                          # Refine 資源定義
│   ├── api/                                         # API 層
│   ├── types/                                       # TypeScript 型別
│   └── utils/                                       # 工具函式
├── spec/                                            # 規格文件
├── tests/e2e/                                       # Playwright E2E 測試
└── vendor-prefixed/                                 # Strauss 前綴後的依賴
```

## CPT 清單

| CPT | Post Type Key | 說明 | 自訂狀態 |
|-----|--------------|------|----------|
| 推廣連結 | `pf_promo_link` | YouTube 直播場次推廣 | 標準 WP |
| 報名紀錄 | `pf_registration` | LINE 用戶 + 活動場次 | success/rejected/failed/pending/cancelled |
| 工作流實例 | `pf_workflow` | 執行中的工作流 | running/completed/failed |
| 工作流規則 | `pf_workflow_rule` | ReactFlow 節點圖模板 | publish/draft/trash |

## 工作流引擎

```
WorkflowRule (設計時) → Trigger 觸發 → Workflow (執行時)
    ├── NodeDTO[0].try_execute() → NodeDefinition.execute()
    ├── NodeDTO[1].try_execute() → ...
    └── 全部完成 → completed / 任一失敗 → failed
```

- `NodeDTO::can_execute()` 檢查 `match_callback` 條件
- `ParamHelper` 處理 `context` 參數替換和模板字串取代
- `WaitNode` 使用 Action Scheduler 排程延遲執行

## LINE LIFF 整合

```typescript
// App2.tsx — LIFF 初始化並取得用戶資訊
import liff from '@line/liff/core'
await liff.init({ liffId: env.LIFF_ID })
// saveLiffUserInfo() → 後端處理 → 發 Carousel → liff.closeWindow()
```

```php
// 後端 LINE Webhook 處理 — Postback 報名流程
// RegisterActivityViaLine::line_postback() → Repository::create() → 狀態通知
```

## Strauss 命名空間前綴

```json
{
  "extra": {
    "strauss": {
      "target_directory": "vendor-prefixed",
      "namespace_prefix": "PowerFunnel\\",
      "packages": ["guzzlehttp/guzzle", "guzzlehttp/psr7", "guzzlehttp/promises"]
    }
  }
}
```

Local 環境載入 `vendor/autoload.php`，Production 載入 `vendor-prefixed/autoload.php`。

## Refine.dev DataProvider 配置

```typescript
const dataProviders = {
    default:        dataProvider(`${API_URL}/v2/powerhouse`, AXIOS_INSTANCE),
    'wp-rest':      dataProvider(`${API_URL}/wp/v2`, AXIOS_INSTANCE),
    'wc-rest':      dataProvider(`${API_URL}/wc/v3`, AXIOS_INSTANCE),
    'wc-store':     dataProvider(`${API_URL}/wc/store/v1`, AXIOS_INSTANCE),
    'power-funnel': dataProvider(`${API_URL}/${KEBAB}`, AXIOS_INSTANCE),
}
```

## 命名慣例

| 類型 | 慣例 | 範例 |
|------|------|------|
| PHP Namespace | PascalCase | `J7\PowerFunnel\Domains\Admin` |
| PHP 類別 | PascalCase + final | `final class Entry` |
| CPT Key | snake_case + pf_ 前綴 | `pf_workflow_rule` |
| Interface | I 前綴 | `IActivityProvider`, `IWebhookHelper` |
| Text Domain | snake_case | `power_funnel` |
| Enum | E 前綴 + PascalCase | `ENode`, `ETriggerPoint` |

## 開發狀態

| 狀態 | 功能 |
|------|------|
| 已完成 | DDD 後端架構、4 個 CPT、工作流執行引擎 |
| 已完成 | LINE LIFF/Messaging/Webhook 整合、YouTube 整合 |
| 已完成 | PromoLink/Registration CRUD、前端 Settings 頁面 |
| 已完成 | EmailNode 節點（已註冊）、WaitNode 節點（已實作未註冊） |
| 已完成 | E2E 測試框架（Playwright）、spec 規格文件 |
| 待開發 | 其他 8 種節點類型 |
| 未開始 | **ReactFlow 節點編輯器 UI（核心待辦）** |

## 常用指令

```bash
pnpm run bootstrap        # 安裝所有依賴
pnpm dev                   # Vite 開發伺服器 (port 5188)
pnpm build                 # 建構到 js/dist/
composer lint              # PHP 代碼風格檢查
composer analyse           # PHPStan Level 9
composer strauss           # 重新生成 vendor-prefixed/
pnpm release               # 發佈 patch 版本
```

## 相關 SKILL

- `react-flow-master` — ReactFlow 節點編輯器開發專家
- `wordpress-coding-standards` — WordPress 代碼規範
- `refine` — Refine.dev 框架使用指引
- `wp-rest-api` — REST API 設計規範
