# Power Funnel

WordPress 外掛，核心功能：

1. **YouTube 活動管理**：自動抓取 YouTube 直播場次，透過 LINE 讓用戶報名活動
2. **工作流引擎 (Workflow Engine)**：以 ReactFlow 節點編輯器設計工作流規則，在觸發條件滿足時自動執行節點動作（發 Email、等待、發 LINE 訊息等）

## 開發狀態

| 功能模組 | 狀態 | 說明 |
|---------|------|------|
| 後端 DDD 架構 | ✅ 完成 | Applications / Domains / Infrastructure / Contracts / Shared |
| WorkflowRule CPT | ✅ 完成 | `pf_workflow_rule`，含節點定義擴充機制 |
| Workflow 執行引擎 | ✅ 完成 | 逐節點執行、結果記錄、狀態管理 |
| EmailNode | ✅ 完成 | 支援模板替換的 Email 發送 |
| 其他節點類型 | 🚧 開發中 | Wait, LINE, SMS, Webhook 等（介面已定義，待實作）|
| LINE 整合 | ✅ 完成 | LIFF、Messaging API、Carousel 訊息 |
| YouTube 整合 | ✅ 完成 | 活動資料抓取 |
| PromoLink 管理 | ✅ 完成 | 後台 CRUD |
| Settings 頁面 | ✅ 完成 | LINE/YouTube API 設定 |
| **ReactFlow 節點編輯器** | ❌ 待開發 | WorkflowRule 視覺化編輯器（核心待辦） |
| WorkflowRule 前端頁面 | ❌ 待開發 | 列表、建立、編輯 |
| Workflow 歷史追蹤頁面 | ❌ 待開發 | 執行紀錄查詢 |

## 技術棧

| 層次 | 技術 |
|------|------|
| 後端 | PHP 8.1+、WordPress、DDD 架構 |
| 前端 | React 18、TypeScript、@xyflow/react、Refine.dev、Ant Design 5 |
| 建構工具 | Vite、Composer |
| 代碼品質 | PHPStan (level 9)、PHPCS/PHPCBF、ESLint、Prettier |

## 安裝與設定

### 環境需求

- PHP 8.1+
- WordPress 5.7+
- Node.js 18+
- pnpm 8+
- Composer 2+

### 初始化

```bash
# 安裝所有依賴（前端 + 後端）
pnpm run bootstrap

# 或分開安裝
pnpm install
composer install --no-interaction
```

## 指令參考

### 前端開發

| 指令 | 說明 |
|------|------|
| `pnpm dev` | 啟動 Vite 開發伺服器（Hot Reload） |
| `pnpm build` | 生產建構 |
| `pnpm preview` | 預覽建構結果 |
| `pnpm lint` | ESLint + phpcbf 檢查 |
| `pnpm lint:fix` | 自動修復 ESLint 問題 |
| `pnpm format` | Prettier 格式化 tsx 檔案 |

### PHP 代碼品質

| 指令 | 說明 |
|------|------|
| `composer lint` | phpcs 代碼風格檢查 |
| `composer analyse` | PHPStan level 9 靜態分析 |

### 版本發佈

| 指令 | 說明 |
|------|------|
| `pnpm release:patch` | 發布 patch 版本 |
| `pnpm release:minor` | 發布 minor 版本 |
| `pnpm release:major` | 發布 major 版本 |
| `pnpm zip` | 打包 zip 檔案 |

## 架構說明

### 工作流引擎 (Workflow Engine)

```
管理員設計 WorkflowRule（透過 ReactFlow UI）
        ↓
WorkflowRule 發佈 → 在 trigger_point hook 掛載監聽
        ↓
Trigger 觸發（如：用戶報名活動）
        ↓
建立 Workflow 實例（pf_workflow CPT，status: running）
        ↓
WorkflowDTO::try_execute() 逐節點執行
        ↓
每節點執行完 → do_next() → 下一節點
        ↓
全部完成 → status: completed
失敗時   → status: failed，記錄錯誤訊息
```

### 可用節點類型

| 節點 | ID | 類型 | 狀態 |
|------|----|------|------|
| 傳送 Email | `email` | SEND_MESSAGE | ✅ 已實作 |
| 傳送 LINE 訊息 | `line` | SEND_MESSAGE | 🚧 Stub |
| 傳送 SMS | `sms` | SEND_MESSAGE | 🚧 Stub |
| 發送 Webhook | `webhook` | SEND_MESSAGE | 🚧 Stub |
| 等待 | `wait` | ACTION | 🚧 Stub |
| 等待至指定時間 | `wait_until` | ACTION | 🚧 Stub |
| 等待至時間窗口 | `time_window` | ACTION | 🚧 Stub |
| 是/否分支 | `yes_no_branch` | ACTION | 🚧 Stub |
| 多路分支 | `split_branch` | ACTION | 🚧 Stub |
| 標籤用戶 | `tag_user` | ACTION | 🚧 Stub |

### 擴充節點（後端 PHP）

透過 WordPress filter 注入自訂節點定義：

```php
add_filter('power_funnel/workflow_rule/node_definitions', function(array $definitions): array {
    $definitions['my_node'] = new MyNodeDefinition();
    return $definitions;
});
```

### 擴充觸發點

工作流引擎的「觸發條件」下拉選單透過 REST API 動態載入，資料來源為 `Repository::get_trigger_points()`。
第三方開發者可透過 `power_funnel/workflow_rule/trigger_points` filter 新增自訂觸發點，新增後會自動出現在管理介面的下拉選單中。

**REST API**

```
GET /wp-json/power-funnel/trigger-points
```

回應格式：

```json
{
  "code": "operation_success",
  "message": "操作成功",
  "data": [
    { "hook": "pf/trigger/registration_created", "name": "用戶報名後" }
  ]
}
```

**透過 Filter 新增觸發點**

```php
add_filter('power_funnel/workflow_rule/trigger_points', function(array $trigger_points): array {
    $trigger_points['pf/trigger/my_event'] = new TriggerPointDTO([
        'hook' => 'pf/trigger/my_event',
        'name' => '自訂觸發事件',
    ]);
    return $trigger_points;
});
```

**觸發工作流**

新增觸發點後，在適當的時機呼叫 `do_action` 觸發工作流：

```php
do_action('pf/trigger/my_event', $context);
```

`$context` 為傳遞給工作流節點的參數陣列，可包含 `user_id`、`post_id` 等業務資料。

## Custom Post Types

| CPT | 說明 |
|-----|------|
| `pf_workflow_rule` | 工作流規則模板（含節點定義） |
| `pf_workflow` | 工作流執行實例（含執行結果） |
| `pf_promo_link` | 推廣連結 |
| `pf_registration` | 活動報名紀錄 |

## 開發指引

- PHP 開發：參閱 `.github/instructions/wordpress.instructions.md`
- React 開發：參閱 `.github/instructions/react.instructions.md`
- 整體架構：參閱 `.github/copilot-instructions.md`

## License

GPL v2 or later
