---
name: power-funnel
description: "Power Funnel — WordPress 漏斗自動化與工作流外掛開發指引。DDD 架構、LINE LIFF 整合、YouTube 直播報名、ReactFlow 節點編輯器、工作流引擎。使用 /power-funnel 觸發。"
origin: project-analyze
---

# power-funnel — 開發指引

> WordPress Plugin，整合 YouTube 直播抓取、LINE LIFF 報名流程，並提供基於節點的工作流引擎（WorkflowRule 設計 → Workflow 執行），採用 DDD 架構。

## When to Activate

當使用者在此專案中：
- 修改 `inc/src/**/*.php`（DDD 後端邏輯）
- 修改 `js/src/**/*.tsx`（React/ReactFlow 前端）
- 新增工作流節點類型、LINE/YouTube 整合功能
- 詢問工作流引擎、Strauss 命名空間前綴、PHP 8 Attributes 相關問題

## 架構概覽

**技術棧：**
- **語言**: PHP 8.1+（`declare(strict_types=1)`）
- **框架**: WordPress 5.7+、WooCommerce（選用）
- **關鍵依賴**: `linecorp/line-bot-sdk ^12.3`、`kucrut/vite-for-wp ^0.12`、Strauss（vendor namespace prefixing）
- **前端**: React 18 + TypeScript + Refine.dev + Ant Design 5 + @xyflow/react (ReactFlow) + @line/liff + TanStack Query
- **建置**: Vite（開發 port 自動）
- **代碼風格**: PHPCS（WordPress-Core）、PHPStan Level 9、ESLint + Prettier

## 目錄結構

```
power-funnel/
├── plugin.php                                      # 主入口（PluginTrait + SingletonTrait）
├── inc/src/
│   ├── App.php                                     # 應用層初始化入口
│   ├── Applications/
│   │   └── Services/                               # 應用服務層
│   ├── Domains/
│   │   ├── PromoLink/
│   │   │   ├── CPT.php                             # CPT 'promo-links' 註冊
│   │   │   └── Api.php                             # PromoLink REST API
│   │   ├── Registration/
│   │   │   ├── CPT.php                             # CPT 'registration' 註冊
│   │   │   └── Api.php                             # 報名 REST API
│   │   ├── Workflow/
│   │   │   ├── CPT.php                             # CPT 'workflow' 實例
│   │   │   ├── Engine.php                          # 工作流執行引擎
│   │   │   └── Nodes/
│   │   │       ├── EmailNode.php                   # Email 節點（已完成）
│   │   │       └── WaitNode.php                    # 等待節點（stub）
│   │   └── WorkflowRule/
│   │       ├── CPT.php                             # CPT 'workflow-rule' 設計時
│   │       └── Api.php                             # WorkflowRule REST API
│   ├── Infrastructure/
│   │   ├── ExternalServices/
│   │   │   ├── Line/
│   │   │   │   ├── ApiClient.php                   # LINE Messaging API 客戶端
│   │   │   │   └── LiffHandler.php                 # LINE LIFF 處理
│   │   │   └── YouTube/
│   │   │       └── ApiClient.php                   # YouTube Data API v3
│   │   └── Settings/
│   │       └── SettingsModel.php                   # WordPress Options 設定
│   ├── Contracts/                                  # 介面定義
│   └── Shared/
│       ├── Cache/                                  # Transient 快取
│       └── Http/                                   # HTTP 抽象客戶端
├── js/src/
│   ├── main.tsx                                    # React 掛載入口
│   ├── App1.tsx                                    # Refine 應用 Shell
│   ├── pages/
│   │   ├── PromoLinks/                             # PromoLink CRUD 頁面
│   │   ├── Settings/                               # 設定頁面
│   │   └── WorkflowEditor/                         # ReactFlow 節點編輯器（待開發）
│   ├── hooks/
│   │   └── useEnv.tsx                              # 環境變數訪問
│   └── types/                                      # TypeScript 型別
└── vendor_prefixed/                               # Strauss 前綴後的 vendor 依賴
```

## CPT 清單

| CPT | 說明 |
|---|---|
| `promo-links` | 促銷連結（YouTube 直播場次對應） |
| `registration` | 報名記錄（LINE 用戶 + 直播場次） |
| `workflow` | 工作流執行實例 |
| `workflow-rule` | 工作流設計規則（ReactFlow 節點圖） |

## 工作流引擎

```php
// WorkflowRule 設計時：管理員用 ReactFlow 設計節點圖，儲存為 JSON meta
// Workflow 執行時：當 Trigger 觸發，Engine 逐節點執行

// 節點類型
abstract class BaseNode {
    abstract public function execute(Workflow $workflow): NodeResult;
}

class EmailNode extends BaseNode { ... }  // 已完成
class WaitNode extends BaseNode { ... }   // stub
```

## LINE LIFF 整合

```typescript
// 前端 LIFF SDK 初始化
import liff from '@line/liff';

await liff.init({ liffId: env.LIFF_ID });
const profile = await liff.getProfile();
// 取得 LINE userId 後送往後端報名
```

```php
// 後端 LINE Bot Webhook（Strauss 前綴後的 SDK）
use J7\PowerFunnel\Vendor\LINE\LINEBot;
use J7\PowerFunnel\Vendor\LINE\LINEBot\MessageBuilder\TextMessageBuilder;
```

## YouTube 直播抓取

```php
// YouTube Data API v3 搜尋進行中直播
// 定期透過 Action Scheduler 或手動觸發
// 結果儲存為 promo-links CPT
```

## Strauss 命名空間前綴

```json
// composer.json extra.strauss 設定
{
  "extra": {
    "strauss": {
      "target_directory": "vendor_prefixed",
      "namespace_prefix": "J7\\PowerFunnel\\Vendor\\"
    }
  }
}
```

所有 vendor 依賴（如 LINE Bot SDK）在 `vendor_prefixed/` 下以 `J7\PowerFunnel\Vendor\` 前綴存取，避免與其他外掛衝突。

## Refine.dev DataProvider 配置

```typescript
// App1.tsx
const dataProviders = {
    default:      dataProvider('/v2/powerhouse'),    // Powerhouse REST API
    'wp-rest':    dataProvider('/wp/v2'),            // WordPress Core REST API
    'power-funnel': dataProvider(`/${KEBAB}`),       // Power Funnel 專屬 API
}
```

## 命名慣例

| 類型 | 慣例 | 範例 |
|------|------|------|
| PHP Namespace | PascalCase | `J7\PowerFunnel\Domains\Workflow` |
| PHP 類別 | PascalCase（final） | `final class Engine` |
| CPT | kebab-case | `workflow-rule`、`promo-links` |
| Interface | I 前綴 | `INode`、`ITrigger` |
| Text Domain | snake_case | `power_funnel` |

## 開發規範

1. DDD 分層嚴格遵守：Domain 層不得 import Infrastructure 層
2. 所有外部 API（LINE、YouTube）透過 `Infrastructure/ExternalServices/` 隔離
3. Vendor 依賴必須透過 Strauss 前綴，使用 `vendor_prefixed/` 下的命名空間
4. 工作流節點新增時，繼承 `BaseNode` 並實作 `execute()` 方法
5. PHPStan Level 9 必須通過

## 開發狀態

| 狀態 | 功能 |
|---|---|
| ✅ 已完成 | DDD 後端架構、WorkflowRule/Workflow CPT |
| ✅ 已完成 | 工作流執行引擎、LINE/YouTube 整合 |
| ✅ 已完成 | PromoLink/Registration CRUD、前端 Settings 頁面 |
| ✅ 已完成 | EmailNode 節點類型 |
| 🚧 待開發 | WaitNode 等其他節點類型 |
| ❌ 未開始 | **ReactFlow 節點編輯器 UI（核心待辦）** |

## 常用指令

```bash
composer install           # 安裝 PHP 依賴
pnpm install               # 安裝 Node 依賴
pnpm dev                   # Vite 開發伺服器
pnpm build                 # 建置到 js/dist/
composer strauss           # 重新生成 vendor_prefixed/
vendor/bin/phpcs           # PHP 代碼風格檢查
vendor/bin/phpstan analyse # PHPStan 靜態分析（Level 9）
pnpm release               # 發佈 patch 版本
```

## 相關 SKILL

- `wordpress-master` — WordPress Plugin 開發通用指引
- `react-master` — React 前端開發指引
- `refine` — Refine.dev 框架使用指引
- `wp-rest-api` — REST API 設計規範
