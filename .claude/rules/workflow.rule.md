---
paths:
  - "**/Workflow/**/*.php"
  - "**/WorkflowRule/**/*.php"
  - "**/Enums/ENode.php"
  - "**/Enums/ETriggerPoint.php"
---

# Power Funnel — 工作流引擎開發規範

## 工作流生命週期

```
[管理員] 建立 WorkflowRule (draft)
    → 發布 (publish)
    → 在 trigger_point hook 上掛載監聽器
    → hook 被觸發
    → 建立 Workflow 實例 (running)
    → 逐節點執行
    → completed 或 failed
```

## WorkflowRule（設計時）

- CPT：`pf_workflow_rule`
- 狀態：draft → publish → trash
- 核心 meta：
  - `trigger_point`：ETriggerPoint enum 的 hook name（如 `pf/trigger/registration_created`）
  - `nodes`：JSON 序列化的 `NodeDTO[]`

### NodeDTO 結構

```php
[
    'id'                    => 'n1',           // 節點唯一識別碼
    'node_definition_id'    => 'email',        // 對應已註冊的 NodeDefinition
    'params'                => [               // 節點參數
        'recipient'    => 'context',
        'subject_tpl'  => '歡迎',
        'content_tpl'  => '感謝報名',
    ],
    'match_callback'        => ['__return_true'],  // 執行前檢查 callback
    'match_callback_params' => [],
]
```

### 發布流程

1. `WorkflowRule` 從 draft 切換到 publish
2. `register_workflow_rules()` 掃描所有已發布規則
3. 在對應 `trigger_point` hook 上 `add_action`
4. 當 hook 被觸發時，呼叫 `Repository::create_from()` 建立 Workflow 實例

## Workflow（執行時）

- CPT：`pf_workflow`
- 狀態：running → completed 或 failed
- 核心 meta：
  - `workflow_rule_id`：來源規則 ID
  - `trigger_point`：從規則複製
  - `nodes`：從規則複製的 `NodeDTO[]`
  - `context_callable_set`：上下文取得方式
  - `results`：`WorkflowResultDTO[]` 執行結果陣列

### 執行邏輯（WorkflowDTO::try_execute）

1. 檢查 `post_status === 'running'`
2. `get_current_index()` = results 數量，決定下一個要執行的節點
3. 若 `current_index === null`（全部完成）→ 標記 `completed`
4. 執行 `NodeDTO::try_execute()`：
   - `can_execute()` 檢查 `match_callback` 條件
   - 不滿足 → 記錄 `code: 301`（跳過），呼叫 `do_next()`
   - 滿足 → 呼叫 `NodeDefinition::execute()`
5. 執行成功（`code: 200`）→ 記錄結果，呼叫 `do_next()`
6. 執行失敗（`code: 500` 或例外）→ 記錄結果，標記 `failed`

### WorkflowResultDTO 結構

```php
[
    'node_id' => 'n1',          // 對應 NodeDTO.id
    'code'    => 200,           // 200=成功, 301=跳過, 500=失敗
    'message' => '發信成功',     // 結果描述
    'data'    => [],            // 額外資料
]
```

## 新增節點定義

1. **建立 NodeDefinition 類別**
   ```
   Infrastructure/Repositories/WorkflowRule/NodeDefinitions/{Name}Node.php
   ```
   繼承 `BaseNodeDefinition`，實作 `execute()` 方法。

2. **在 ENode enum 確認 case 存在**（或新增）

3. **註冊至系統**
   在 `WorkflowRule\Register::register_default_node_definitions` 中：
   ```php
   // 透過 filter 'power_funnel/workflow_rule/node_definitions' 注入
   ```

4. **已有的 NodeDefinition**：
   - `EmailNode`：使用 `wp_mail` 發送郵件，已正式註冊
   - `WaitNode`：使用 `as_schedule_single_action` 排程延遲，已實作未正式註冊

## 新增觸發點

1. 在 `ETriggerPoint` enum 加入新 case：
   ```php
   case NEW_TRIGGER = self::PREFIX . 'new_trigger_name';
   ```
2. 更新 `label()` 方法
3. 在適當的業務邏輯時機呼叫 `do_action($trigger_point->value, $context_callable_set)`
4. 觸發點透過 filter `power_funnel/workflow_rule/trigger_points` 可擴充

## ParamHelper

`ParamHelper` 處理節點參數中的動態替換：
- `context` 關鍵字代表從 `context_callable_set` 取得的上下文資料
- 模板字串支援 `{{variable}}` 格式的替換

## WaitNode 排程機制

1. 執行時呼叫 `as_schedule_single_action($timestamp, 'power_funnel/workflow/running', [$workflow_id])`
2. 記錄 `code: 200, message: '等待中'`
3. 排程到期後 Action Scheduler 觸發 `power_funnel/workflow/running`
4. Workflow 從下一個節點繼續執行
5. `as_schedule_single_action` 回傳 0 表示排程失敗 → `code: 500`
