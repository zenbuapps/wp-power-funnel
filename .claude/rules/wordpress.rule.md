---
paths:
  - "**/*.php"
---

# Power Funnel — WordPress PHP 開發規範

## DDD 分層架構

所有 PHP 類別位於 `inc/classes/`，namespace 根為 `J7\PowerFunnel`。

**依賴方向（僅允許上層呼叫下層）：**
```
Applications → Domains → Infrastructure → Contracts → Shared
```

| 層 | 路徑 | 職責 | 可引用 |
|----|------|------|--------|
| Applications | `Applications/` | REST API 端點、跨 Domain 協調 | Domains, Infrastructure, Contracts, Shared |
| Domains | `Domains/` | 業務邏輯、領域服務 | Infrastructure, Contracts, Shared |
| Infrastructure | `Infrastructure/` | CPT 註冊、外部 API 封裝、Repository | Contracts, Shared |
| Contracts | `Contracts/` | DTO、Interface 定義 | Shared |
| Shared | `Shared/` | Constants、Enums | 不引用其他層 |

## PHP 編碼標準

### 檔案頭

```php
<?php

declare(strict_types=1);

namespace J7\PowerFunnel\{Layer}\{SubPath};
```

### 類別宣告

- 使用 `final class`（PHPCS 規則 `Universal.Classes.RequireFinalClass`）
- 每個類別提供 `register_hooks()` 靜態方法註冊 WordPress Hook
- 所有 PHP 函數附繁體中文 PHPDoc

### 命名慣例

| 類型 | 慣例 | 範例 |
|------|------|------|
| Namespace | PascalCase | `J7\PowerFunnel\Domains\Admin` |
| 類別 | PascalCase + final | `final class Entry` |
| CPT Key | snake_case + `pf_` 前綴 | `pf_workflow_rule` |
| Interface | `I` 前綴 | `IActivityProvider`, `IWebhookHelper` |
| Enum | `E` 前綴 + PascalCase | `ENode`, `ETriggerPoint` |
| Text Domain | snake_case | `power_funnel` |
| Hook 名稱 | `power_funnel/` 或 `pf/` 前綴 + 斜線分隔 | `power_funnel/registration/{status}` |
| Option Name | `_power_funnel_` 前綴 | `_power_funnel_line_setting` |

### PHPCS 排除項

專案已排除以下規則（不要試圖修正）：
- `WordPress.Files.FileName`（允許 PascalCase 檔名）
- `WordPress.PHP.YodaConditions.NotYoda`（不強制 Yoda 條件式）
- `WordPress.NamingConventions.ValidVariableName`（允許 camelCase 屬性）
- `WordPress.NamingConventions.ValidHookName.UseUnderscores`（允許斜線分隔 hook name）

### PHPStan Level 9

- 分析範圍：`plugin.php` + `inc/`
- 執行指令：`composer analyse`（需 6G 記憶體）
- 已忽略的錯誤模式見 `phpstan.neon`

## CPT 操作模式

### 建立 CPT

每個 CPT 在 `Infrastructure/Repositories/{Name}/Register.php` 中透過 `register_hooks()` 註冊：
- 使用 `register_post_type()` 註冊
- 自訂狀態使用 `register_post_status()` 註冊

### Repository 模式

- `Repository.php` 負責查詢與建立操作
- 使用 `WP_Query` 查詢文章
- 使用 `wp_insert_post` / `wp_update_post` 操作資料
- Meta 欄位透過 `get_post_meta` / `update_post_meta` 存取

### 狀態生命週期

報名狀態變更時：
1. `wp_update_post` 修改 `post_status`
2. WordPress 觸發 `transition_post_status` hook
3. 系統觸發 `power_funnel/registration/{new_status}` action
4. 對應的通知邏輯被執行

## Hook 註冊模式

```php
final class SomeService {
    /** 註冊 hooks */
    public static function register_hooks(): void {
        \add_action('init', [__CLASS__, 'some_action']);
        \add_filter('some_filter', [__CLASS__, 'some_filter'], 10, 2);
    }
}
```

所有 `register_hooks()` 最終在 `Bootstrap::register_hooks()` 中集中調用。

## REST API 模式

```php
// 繼承 ApiBase，在 register_hooks() 中呼叫 register_api_*
// Permission callback：
// - 管理端 API 使用 nonce 驗證（current_user_can('manage_options')）
// - 公開端 API（liff, line-callback）使用 __return_true
```

回應格式統一為：
```php
['code' => 'operation_success', 'message' => '操作成功', 'data' => $data]
```

## Strauss 命名空間前綴

```json
{
  "namespace_prefix": "PowerFunnel\\",
  "classmap_prefix": "PowerFunnel_",
  "constant_prefix": "PowerFunnel_",
  "target_directory": "vendor-prefixed",
  "packages": ["guzzlehttp/guzzle", "guzzlehttp/psr7", "guzzlehttp/promises"]
}
```

- Local 環境：`vendor/autoload.php`
- Production：`vendor-prefixed/autoload.php`
- 修改 Strauss 套件後需執行 `composer strauss`

## DTO 模式

所有 DTO 位於 `Contracts/DTOs/`。DTO 同時負責：
- 資料傳輸（建構子接收陣列）
- 業務邏輯封裝（如 `WorkflowDTO::try_execute()`）
- 序列化（`to_array()` 方法）

## 資安規範

- 所有使用者輸入使用 WordPress sanitize 函式清理
- 所有輸出使用 `esc_html()`、`esc_attr()`、`esc_url()` 跳脫
- REST API 使用 `wp_create_nonce('wp_rest')` + `X-WP-Nonce` header 驗證
- LINE Webhook 使用 `X-Line-Signature` 簽章驗證
- 禁止直接拼接 SQL，使用 `$wpdb->prepare()`

## 日誌記錄

使用 `Plugin::logger($message, $level, $args)` 記錄日誌，底層為 WooCommerce Logger（`WC_Logger`）。
