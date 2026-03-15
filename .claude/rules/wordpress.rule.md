---
name: "WordPress開發指引"
description: "Power Funnel WordPress 後端開發規範：PHP 8.1+、DDD 架構、REST API、工作流引擎、LINE/YouTube 整合"
globs: "inc/**/*.php"
---

# WordPress / PHP 後端開發指引

## 開發規範

### 強制要求
- `declare(strict_types=1)` — 所有 PHP 檔案開頭
- 命名空間 `J7\PowerFunnel` — PSR-4 自動載入根目錄 `inc/classes/`
- WordPress Coding Standards (phpcs) + PHPStan Level 9
- 繁體中文 PHPDoc 註解

### 命名風格
- 變數、函數、方法：`snake_case`
- 類別：`PascalCase`
- 常數：`UPPER_SNAKE_CASE`
- Hook 名稱：`power_funnel/{domain}/{action}`

### 靜態方法優先 + register_hooks 模式
```php
final class MyService {
    /** Register hooks */
    public static function register_hooks(): void {
        \add_action('init', [__CLASS__, 'init_callback']);
        \add_filter('the_content', [__CLASS__, 'filter_content']);
    }

    /** 初始化回調 */
    public static function init_callback(): void { /* ... */ }
}
```

需要單例模式時使用 `SingletonTrait`：
```php
final class MyService {
    use \J7\WpUtils\Traits\SingletonTrait;
    public function __construct() { /* ... */ }
}
// 使用：MyService::instance();
```

## DDD 架構分層

| 層次 | 路徑 | 職責 | 可依賴 |
|------|------|------|--------|
| Applications | `inc/classes/Applications/` | REST API 端點、應用服務 | Domains, Infrastructure, Contracts, Shared |
| Domains | `inc/classes/Domains/` | 業務邏輯、領域服務 | Contracts, Shared |
| Infrastructure | `inc/classes/Infrastructure/` | 外部 API 整合、Repository CRUD | Contracts, Shared |
| Contracts | `inc/classes/Contracts/DTOs/` | DTO 資料傳輸物件 | Shared |
| Shared | `inc/classes/Shared/` | 常數、Enum | 無依賴 |

**禁止**：Infrastructure 直接呼叫 Applications；Domains 直接 import Infrastructure；Shared 引用其他層。

## REST API 開發

繼承 `ApiBase` 類別：
```php
final class MyApi extends ApiBase {
    use SingletonTrait;

    protected $namespace = 'power-funnel';

    protected $apis = [
        [
            'endpoint' => 'my-resource',
            'method'   => 'get',
        ],
    ];

    /** Register hooks */
    public static function register_hooks(): void {
        self::instance();
    }

    /**
     * 取得資源
     *
     * @param \WP_REST_Request $request 請求物件
     * @return \WP_REST_Response
     * @phpstan-ignore-next-line
     */
    public function get_my_resource_callback(\WP_REST_Request $request): \WP_REST_Response {
        // 回調方法名稱格式：{method}_{endpoint_snake_case}_callback
        return new \WP_REST_Response(['data' => []]);
    }
}
```

在 `Bootstrap::register_hooks()` 中調用 `MyApi::register_hooks()`。

## 新增節點定義 (NodeDefinition)

繼承 `BaseNodeDefinition`，放在 `Infrastructure\Repositories\WorkflowRule\NodeDefinitions\`：

```php
final class MyNode extends BaseNodeDefinition {
    public string $id = 'my_node';
    public string $name = '我的節點';
    public string $description = '節點說明';
    public ENodeType $type = ENodeType::ACTION;

    /**
     * 執行回調
     *
     * @param NodeDTO     $node     節點
     * @param WorkflowDTO $workflow 當前 workflow
     * @return WorkflowResultDTO 結果
     */
    public function execute(NodeDTO $node, WorkflowDTO $workflow): WorkflowResultDTO {
        $param_helper = new ParamHelper($node, $workflow);
        // 執行邏輯...
        $workflow->do_next(); // 繼續下個節點
        return new WorkflowResultDTO([
            'node_id' => $node->id,
            'code'    => 200,
            'message' => '成功',
        ]);
    }
}
```

在 `WorkflowRule\Register::register_default_node_definitions()` 中注入。

## 新增觸發點 (TriggerPoint)

在 `ETriggerPoint` enum 新增 case：
```php
case MY_TRIGGER = self::PREFIX . 'my_trigger';
```

在適當時機觸發：
```php
\do_action(ETriggerPoint::MY_TRIGGER->value, $context_callable_set);
```

## CPT Post Types

| 常數 | Post Type | 自訂狀態 |
|------|-----------|----------|
| `WORKFLOW_RULE_POST_TYPE` | `pf_workflow_rule` | publish / draft / trash |
| `WORKFLOW_POST_TYPE` | `pf_workflow` | running / completed / failed |
| `PROMO_LINK_POST_TYPE` | `pf_promo_link` | 標準 WP 狀態 |
| `REGISTRATION_POST_TYPE` | `pf_registration` | success / rejected / failed / pending / cancelled |

## Strauss Vendor Prefixing

- **Namespace prefix**: `PowerFunnel\`
- **Classmap prefix**: `PowerFunnel_`
- **Target**: `vendor-prefixed/`
- **適用套件**: guzzlehttp/guzzle, guzzlehttp/psr7, guzzlehttp/promises
- Local 環境載入 `vendor/autoload.php`，Production 載入 `vendor-prefixed/autoload.php`

## 安全性

- `\wp_create_nonce('wp_rest')` 產生 REST API nonce
- `sanitize_text_field` / `esc_html` / `esc_attr` 清理輸入輸出
- `current_user_can()` 權限檢查
- `$wpdb->prepare()` 防止 SQL 注入

## 代碼品質指令

```bash
composer lint                               # phpcs 檢查
composer analyse                            # PHPStan Level 9（需 6G 記憶體）
vendor/bin/phpstan analyse inc --memory-limit=6G  # 手動指定記憶體
composer strauss                            # 重新生成 vendor-prefixed/
```
