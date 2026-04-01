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
  - `trigger_point`：觸發點設定，支援兩種格式（向下相容）：
    - 舊版：純字串 hook 名稱，如 `pf/trigger/registration_approved`
    - 新版：JSON 字串 `{"hook": "pf/trigger/activity_before_start", "params": {"before_minutes": 30}}`
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
2. `register_workflow_rules()` 掃描所有已發布規則（在 `init` priority=99 時執行）
3. 在對應 `trigger_point` hook 上 `add_action`（由 `WorkflowRuleDTO::register()` 處理）
4. 當 hook 被觸發時，先透過 `RecursionGuard::enter()` 檢查遞迴深度
5. 深度超過 `MAX_DEPTH=3` 時呼叫 `Repository::create_failed_from_recursion_exceeded()` 並終止
6. 否則呼叫 `Repository::create_from()` 建立 Workflow 實例

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
   在 `WorkflowRule\Register::register_default_node_definitions` 中加入：
   ```php
   // 透過 filter 'power_funnel/workflow_rule/node_definitions' 注入
   ```

4. **已有的 NodeDefinition**（均已正式在 `register_default_node_definitions` 中註冊）：
   - `EmailNode`：使用 `wp_mail` 發送郵件
   - `SmsNode`：簡訊發送
   - `LineNode`：LINE 訊息發送
   - `WebhookNode`：呼叫外部 Webhook
   - `WaitNode`：使用 `as_schedule_single_action` 排程延遲
   - `WaitUntilNode`：等待至指定時間
   - `TimeWindowNode`：時間窗口條件判斷
   - `YesNoBranchNode`：是否分支
   - `SplitBranchNode`：多路分支
   - `TagUserNode`：貼標籤，執行後呼叫 `TriggerPointService::fire_user_tagged()`

## 新增觸發點

1. 在 `ETriggerPoint` enum 加入新 case：
   ```php
   case NEW_TRIGGER = self::PREFIX . 'new_trigger_name';
   ```
2. 更新 `label()` 方法
3. 在 `TriggerPointService` 中新增監聽器並呼叫 `do_action($trigger_point->value, $context_callable_set)`：
   - 事件型觸發點：在 `register_hooks()` 中 `add_action` 監聽業務事件
   - 時間型觸發點：在 `ActivitySchedulerService` 中加入排程邏輯
4. 觸發點透過 filter `power_funnel/workflow_rule/trigger_points` 可擴充

### context_callable_set 格式（Serializable Context Callable 原則）

callable 必須為 `string`（函數名）或 `string[]`（`[ClassName::class, 'method']`），**禁止 Closure**。
params 必須為純值陣列（int / string），禁止物件。
這確保 `context_callable_set` 能安全通過 WordPress `serialize()` / `unserialize()`，WaitNode 延遲恢復後仍可取得 context。

```php
// ✅ 正確：靜態方法引用（可序列化）
[
    'callable' => [ TriggerPointService::class, 'resolve_registration_context' ],
    'params'   => [ $post_id ],
]

// ❌ 禁止：Closure（無法序列化，WaitNode 恢復後 context 會丟失）
[
    'callable' => static function ( int $id ): array { ... },
    'params'   => [ $post_id ],
]
```

各觸發點的 context key：

| 觸發點類別 | context 包含的 key |
|-----------|-------------------|
| P0 報名狀態 | `registration_id`、`identity_id`、`identity_provider`、`activity_id`、`promo_link_id` |
| P1 LINE 互動 | `line_user_id`、`event_type`（訊息型額外含 `message_text`） |
| P2 工作流引擎 | `workflow_id`、`workflow_rule_id`、`trigger_point` |
| P3 活動時間 | `activity_id`、`event_type` |
| P3 活動時間（before_start） | `activity_id`、`workflow_rule_id`、`event_type` |
| P3 用戶行為 | `user_id`、`tag_name` |

### ETriggerPoint 完整清單

| 類別 | case | hook value | 狀態 |
|------|------|-----------|------|
| P0 | `REGISTRATION_CREATED` | `pf/trigger/registration_created` | 已棄用（保留相容） |
| P0 | `REGISTRATION_APPROVED` | `pf/trigger/registration_approved` | 正常 |
| P0 | `REGISTRATION_REJECTED` | `pf/trigger/registration_rejected` | 正常 |
| P0 | `REGISTRATION_CANCELLED` | `pf/trigger/registration_cancelled` | 正常 |
| P0 | `REGISTRATION_FAILED` | `pf/trigger/registration_failed` | 正常 |
| P1 | `LINE_FOLLOWED` | `pf/trigger/line_followed` | 正常 |
| P1 | `LINE_UNFOLLOWED` | `pf/trigger/line_unfollowed` | 正常 |
| P1 | `LINE_MESSAGE_RECEIVED` | `pf/trigger/line_message_received` | 正常 |
| P2 | `WORKFLOW_COMPLETED` | `pf/trigger/workflow_completed` | 正常 |
| P2 | `WORKFLOW_FAILED` | `pf/trigger/workflow_failed` | 正常 |
| P3 | `ACTIVITY_STARTED` | `pf/trigger/activity_started` | 正常（Action Scheduler） |
| P3 | `ACTIVITY_BEFORE_START` | `pf/trigger/activity_before_start` | 正常（需 `params.before_minutes`） |
| P3 | `ACTIVITY_ENDED` | `pf/trigger/activity_ended` | 枚舉存根，無實作 |
| P3 | `USER_TAGGED` | `pf/trigger/user_tagged` | 正常（TagUserNode 呼叫） |
| P3 | `PROMO_LINK_CLICKED` | `pf/trigger/promo_link_clicked` | 枚舉存根，無實作 |

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

## RecursionGuard（遞迴防護）

防止工作流觸發工作流（例如 WORKFLOW_COMPLETED 觸發點）造成無限遞迴：

- 靜態深度計數器，同一 PHP 請求內共用
- `RecursionGuard::enter()` → 深度 +1
- `RecursionGuard::leave()` → 深度 -1（在 `finally` 中呼叫）
- `RecursionGuard::is_exceeded()` → 深度 > `MAX_DEPTH=3` 時回傳 true
- 超過上限時呼叫 `Repository::create_failed_from_recursion_exceeded()` 記錄失敗 Workflow 後直接終止

## ActivitySchedulerService（活動時間觸發）

負責將活動時間轉換為 Action Scheduler 排程：

- 當活動資料同步時呼叫 `ActivitySchedulerService::schedule_activity(ActivityDTO $activity)`
- 自動排程 `ACTIVITY_STARTED`（活動開始時刻）
- 掃描所有 `ACTIVITY_BEFORE_START` 規則，依各規則的 `params.before_minutes` 排程（預設 30 分鐘）
- 重新排程時先呼叫 `as_unschedule_all_actions` 取消舊排程

### Action Scheduler hooks

| hook | 說明 |
|------|------|
| `power_funnel/activity_trigger/started` | 活動開始，參數：`$activity_id` |
| `power_funnel/activity_trigger/before_start` | 活動開始前，參數：`$activity_id, $workflow_rule_id` |
