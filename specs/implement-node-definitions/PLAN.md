# 實作計劃：NodeDefinition execute() 方法與 Action Scheduler 統一排程

## 概述

實作 Power Funnel 工作流引擎中 6 個尚未實作的 NodeDefinition `execute()` 方法（LineNode、SmsNode、WebhookNode、WaitUntilNode、TimeWindowNode、TagUserNode），並重構引擎層的節點串接機制，從同步 `do_action()` 改為 Action Scheduler 統一排程。同時更新 TagUserNode 的表單欄位定義、修改既有 WaitNode 以配合新的 `scheduled` 欄位。

## 範圍模式：HOLD SCOPE

本次為既有架構的功能實作（填充 stub），受影響檔案約 12 個，在 HOLD SCOPE 範圍內。

## 需求重述

1. **引擎層改動**：`NodeDTO::try_execute()` 成功路徑（code=200）新增 Action Scheduler 排程邏輯，使非延遲節點成功後能自動排程下一個節點
2. **WorkflowResultDTO**：新增 `scheduled` 布林欄位（預設 false），供延遲節點標記「已自行排程」以防止引擎二次排程
3. **6 個 NodeDefinition execute()** 實作
4. **既有 WaitNode 修改**：回傳的 WorkflowResultDTO 需帶 `scheduled: true`
5. **TagUserNode form_fields 更新**：tags 欄位從 `select` 改為 `tags_input`

## 架構變更

| 檔案 | 變更類型 | 說明 |
|------|----------|------|
| `inc/classes/Contracts/DTOs/WorkflowResultDTO.php` | 擴充 | 新增 `public bool $scheduled = false` 屬性 |
| `inc/classes/Contracts/DTOs/NodeDTO.php` | 修改 | 成功路徑新增 AS 排程邏輯，移除 skip 路徑的同步 `do_next()` |
| `inc/classes/Infrastructure/.../WaitNode.php` | 修改 | 回傳值帶 `scheduled: true` |
| `inc/classes/Infrastructure/.../LineNode.php` | 實作 | `execute()` 方法 |
| `inc/classes/Infrastructure/.../SmsNode.php` | 實作 | `execute()` 方法 |
| `inc/classes/Infrastructure/.../WebhookNode.php` | 實作 | `execute()` 方法 |
| `inc/classes/Infrastructure/.../WaitUntilNode.php` | 實作 | `execute()` 方法 |
| `inc/classes/Infrastructure/.../TimeWindowNode.php` | 實作 | `execute()` 方法 |
| `inc/classes/Infrastructure/.../TagUserNode.php` | 實作+修改 | `execute()` 方法 + form_fields 更新 |

> 以上 `...` 代表 `Repositories/WorkflowRule/NodeDefinitions`

## 資料流分析

### 引擎層節點執行流程

```
NodeDTO::try_execute()
  │
  ├──▶ can_execute() == false ──▶ add_result(301) ──▶ AS 排程下一節點
  │
  ├──▶ definition.execute() ──▶ result
  │       │
  │       ├── result.is_success() == false ──▶ throw RuntimeException ──▶ catch ──▶ add_result(500) ──▶ STOP (workflow failed)
  │       │
  │       └── result.is_success() == true ──▶ add_result_with_ts
  │               │
  │               ├── result.scheduled == true ──▶ STOP (節點已自行排程)
  │               │
  │               └── result.scheduled == false ──▶ as_schedule_single_action(time(), 'power_funnel/workflow/running', ...)
  │
  └──▶ catch Throwable ──▶ add_result(500) ──▶ throw (workflow failed)
```

### LineNode 資料流

```
INPUT(node.params, workflow.context)
  │
  ├──▶ ParamHelper.replace(content_tpl)  ──▶ content
  │       │ nil: content_tpl 未設定 → 空字串（wp_mail-like 行為）
  │
  ├──▶ context['line_user_id']           ──▶ line_user_id
  │       │ nil: 缺少 → code 500, "line_user_id"
  │       │ empty: 空字串 → code 500, "line_user_id"
  │
  ├──▶ MessageService::getInstance()     ──▶ service
  │       │ error: Channel Access Token 未設定 → catch Exception → code 500
  │
  └──▶ service.send_text_message(line_user_id, content)
          │ success: → code 200, "LINE 訊息發送成功"
          │ error: LINE API error → catch Exception → code 500
```

### SmsNode 資料流

```
INPUT(node.params, workflow.context)
  │
  ├──▶ ParamHelper.replace(recipient)    ──▶ phone
  │       │ nil/empty: → code 500, "recipient"
  │
  ├──▶ ParamHelper.replace(content_tpl)  ──▶ content
  │
  └──▶ apply_filters('power_funnel/sms/send', default, phone, content)
          │ 回傳 {success: true, message: ...}  → code 200
          │ 回傳 {success: false, message: ...} → code 500
          │ 無 filter 掛載 → 預設值 {success: false, message: 'SMS 發送失敗'} → code 500
```

### WebhookNode 資料流

```
INPUT(node.params, workflow.context)
  │
  ├──▶ params['url']      ──▶ url
  │       │ nil/empty: → code 500, "url"
  │
  ├──▶ params['method']   ──▶ method (預設 POST)
  │
  ├──▶ params['headers']  ──▶ json_decode → headers
  │       │ empty: → 空陣列
  │       │ error: JSON 解析失敗 → code 500, "headers"
  │
  ├──▶ ParamHelper.replace(body_tpl) ──▶ body
  │       │ empty: → 空字串
  │
  └──▶ wp_remote_request(url, {method, headers, body})
          │ WP_Error → code 500, error message
          │ HTTP 2xx → code 200, "Webhook 發送成功"
          │ HTTP 非 2xx → code 500, "HTTP {status}"
```

### WaitUntilNode 資料流

```
INPUT(node.params)
  │
  ├──▶ params['datetime'] ──▶ strtotime → timestamp
  │       │ nil/empty: → code 500, "datetime"
  │       │ error: 無法解析 → code 500, "datetime"
  │
  ├──▶ timestamp < time() ? time() : timestamp ──▶ schedule_time
  │
  └──▶ as_schedule_single_action(schedule_time, 'power_funnel/workflow/running', ...)
          │ 回傳 0: → code 500, "排程失敗"
          │ 回傳 > 0: → code 200, scheduled=true, "等待至 ..."
```

### TimeWindowNode 資料流

```
INPUT(node.params)
  │
  ├──▶ params['start_time'] ──▶ start (HH:MM)
  │       │ nil/empty: → code 500, "start_time"
  │
  ├──▶ params['end_time']   ──▶ end (HH:MM)
  │       │ nil/empty: → code 500, "end_time"
  │
  ├──▶ params['timezone'] || wp_timezone_string() ──▶ tz
  │
  ├──▶ is_in_window(now, start, end, tz)?
  │       │ start == end: → 24hr 窗口 → 立即排程
  │       │ start < end (正常): now 在 [start, end) → 立即排程
  │       │ start > end (跨日): now >= start 或 now < end → 立即排程
  │       │ 否則: 計算下一個 start_time 的 timestamp
  │
  └──▶ as_schedule_single_action(timestamp, 'power_funnel/workflow/running', ...)
          │ 回傳 0: → code 500, "排程失敗"
          │ 回傳 > 0: → code 200, scheduled=true
```

### TagUserNode 資料流

```
INPUT(node.params, workflow.context)
  │
  ├──▶ params['tags']    ──▶ tags (string[])
  │       │ nil/empty: → code 500, "tags"
  │
  ├──▶ params['action']  ──▶ action ('add' | 'remove')
  │       │ invalid: → code 500, "action"
  │
  ├──▶ context['line_user_id'] ──▶ user_id
  │       │ nil: → code 500, "user_id"
  │
  ├──▶ get_user_meta(user_id, 'pf_user_tags') ──▶ existing_tags (JSON decode)
  │       │ nil/invalid: → []
  │
  ├──▶ action == 'add'?
  │       ├── 計算 new_tags = tags \ existing_tags（差集）
  │       ├── merged = existing_tags ∪ tags（去重合併）
  │       ├── update_user_meta(user_id, 'pf_user_tags', json_encode(merged))
  │       └── 對每個 new_tag: TriggerPointService::fire_user_tagged(user_id, tag)
  │
  └──▶ action == 'remove'?
          ├── filtered = existing_tags \ tags（差集）
          └── update_user_meta(user_id, 'pf_user_tags', json_encode(filtered))
```

## 錯誤處理登記表

| 方法/路徑 | 可能失敗原因 | 錯誤類型 | 處理方式 | 使用者可見? |
| --------- | ------------ | -------- | -------- | ----------- |
| `NodeDTO::try_execute()` AS 排程 | `as_schedule_single_action` 回傳 0 | RuntimeException | 記錄錯誤日誌，workflow 標記 failed | 管理端可見 |
| `LineNode::execute()` | context 缺少 line_user_id | WorkflowResultDTO(500) | 回傳失敗結果 | 管理端可見 |
| `LineNode::execute()` | Channel Access Token 未設定 | Exception (catch) | 回傳失敗結果，訊息含 "Channel Access Token" | 管理端可見 |
| `LineNode::execute()` | LINE API 回傳錯誤 | Exception (catch) | 回傳失敗結果，保留 API 錯誤訊息 | 管理端可見 |
| `SmsNode::execute()` | recipient 為空 | WorkflowResultDTO(500) | 回傳失敗結果 | 管理端可見 |
| `SmsNode::execute()` | SMS filter 回傳 success=false | WorkflowResultDTO(500) | 回傳失敗結果，保留 filter 訊息 | 管理端可見 |
| `WebhookNode::execute()` | url 為空 | WorkflowResultDTO(500) | 回傳失敗結果 | 管理端可見 |
| `WebhookNode::execute()` | headers JSON 無效 | WorkflowResultDTO(500) | 回傳失敗結果 | 管理端可見 |
| `WebhookNode::execute()` | wp_remote_request 回傳 WP_Error | WorkflowResultDTO(500) | 回傳失敗結果，保留 cURL 錯誤訊息 | 管理端可見 |
| `WebhookNode::execute()` | HTTP 非 2xx | WorkflowResultDTO(500) | 回傳失敗結果，含 HTTP status code | 管理端可見 |
| `WaitUntilNode::execute()` | datetime 為空或無法解析 | WorkflowResultDTO(500) | 回傳失敗結果 | 管理端可見 |
| `WaitUntilNode::execute()` | AS 排程失敗 (回傳 0) | WorkflowResultDTO(500) | 回傳失敗結果 | 管理端可見 |
| `TimeWindowNode::execute()` | start_time/end_time 為空 | WorkflowResultDTO(500) | 回傳失敗結果 | 管理端可見 |
| `TimeWindowNode::execute()` | AS 排程失敗 (回傳 0) | WorkflowResultDTO(500) | 回傳失敗結果 | 管理端可見 |
| `TagUserNode::execute()` | tags 為空陣列 | WorkflowResultDTO(500) | 回傳失敗結果 | 管理端可見 |
| `TagUserNode::execute()` | action 非 add/remove | WorkflowResultDTO(500) | 回傳失敗結果 | 管理端可見 |
| `TagUserNode::execute()` | context 無 line_user_id | WorkflowResultDTO(500) | 回傳失敗結果 | 管理端可見 |

## 失敗模式登記表

| 程式碼路徑 | 失敗模式 | 已處理? | 有測試? | 使用者可見? | 恢復路徑 |
| ---------- | -------- | ------- | ------- | ----------- | -------- |
| NodeDTO 成功後 AS 排程 | AS 排程回傳 0 | 待實作 | 待建立 | 管理端 | workflow 標記 failed |
| NodeDTO skip 路徑 AS 排程 | AS 排程回傳 0 | 待實作 | 待建立 | 管理端 | workflow 標記 failed |
| LineNode Channel Token | Token 未設定 | 待實作 | 待建立 | 管理端 | 管理員設定 Token 後重建 workflow |
| LineNode API 呼叫 | LINE 伺服器不可達 | 待實作 | 待建立 | 管理端 | 人工重試 |
| SmsNode filter | 無 filter 掛載 | 待實作 | 待建立 | 管理端 | 安裝 SMS 外掛 |
| WebhookNode HTTP | 目標伺服器 5xx | 待實作 | 待建立 | 管理端 | 目標伺服器恢復後重建 workflow |
| WaitUntilNode 過去時間 | datetime 已過期 | 待實作 | 待建立 | 不可見（自動立即排程） | 自動處理 |
| TimeWindowNode 跨日 | start > end 時間計算 | 待實作 | 待建立 | 不可見 | 自動處理 |
| TagUserNode fire_user_tagged | 觸發的子 workflow 遞迴 | 既有 RecursionGuard | 既有測試 | 管理端 | RecursionGuard 自動阻斷 |

## 實作步驟

### Phase 1：引擎層基礎設施（所有節點的前置依賴）

> 執行 Agent: `@wp-workflows:wordpress-master`

#### Step 1.1 — WorkflowResultDTO 新增 scheduled 欄位

**檔案**：`inc/classes/Contracts/DTOs/WorkflowResultDTO.php`

**行動**：
- 新增 `public bool $scheduled = false;` 公開屬性
- 新增繁體中文 PHPDoc：`/** @var bool 節點是否已自行排程下一步（WaitNode/WaitUntilNode/TimeWindowNode 為 true） */`

**原因**：所有延遲節點需要用此欄位告知引擎「我已經自行排程了，不要二次排程」。DTO 基類的 `to_array()` 使用反射自動收錄公開屬性，因此新增屬性即自動參與序列化/反序列化。

**依賴**：無

**風險**：低。新增欄位預設值為 false，向下相容既有資料（舊的 results 反序列化時 scheduled 自動為預設值 false）。

#### Step 1.2 — NodeDTO::try_execute() 新增 Action Scheduler 排程邏輯

**檔案**：`inc/classes/Contracts/DTOs/NodeDTO.php`

**行動**：

1. 成功路徑（code=200）：在 `add_result()` 之後，新增排程邏輯：
   ```
   if (!$result_with_ts->scheduled) {
       as_schedule_single_action(time(), 'power_funnel/workflow/running', ['workflow_id' => $workflow_dto->id]);
   }
   ```
   注意：`$result_with_ts` 建構時需保留原始 `$result->scheduled` 欄位值。

2. Skip 路徑（code=301）：將 `$workflow_dto->do_next()` 替換為 `as_schedule_single_action(time(), ...)`，使 skip 也走 AS 排程而非同步 `do_action()`。

3. 需要 `use J7\PowerFunnel\Shared\Enums\EWorkflowStatus;`（如尚未引入）。

**原因**：
- 目前成功路徑沒有呼叫 `do_next()`，workflow 成功執行一個非延遲節點後就會停住（只有 WaitNode 自行做了 AS 排程）。
- 統一改為 AS 排程後，每個節點的執行都在獨立的 PHP request 中，避免長鏈 workflow 佔用單一 request 太久或遞迴溢出。
- Skip 路徑原本用同步 `do_action()` 也需改為 AS 排程，保持一致性。

**依賴**：Step 1.1（需要 `scheduled` 欄位）

**風險**：**中**。這是核心引擎改動，影響所有已實作的節點（EmailNode、WaitNode、YesNoBranchNode）。需要驗證：
- EmailNode 成功後是否正確排程下一節點
- WaitNode 回傳 `scheduled=true` 後引擎不重複排程
- YesNoBranchNode 帶 `next_node_id` 時引擎仍排程
- 最後一個節點成功後排程使 workflow 進入 completed

#### Step 1.3 — 既有 WaitNode 修改

**檔案**：`inc/classes/Infrastructure/Repositories/WorkflowRule/NodeDefinitions/WaitNode.php`

**行動**：
- 在 `execute()` 回傳的 `WorkflowResultDTO` 建構參數中加入 `'scheduled' => true`（成功路徑，即 `$action_id` 不為 0 時）
- 失敗路徑（`$action_id` 為 0）不需設 scheduled（預設 false），因為引擎會在 catch 中處理失敗

**原因**：WaitNode 已自行呼叫 `as_schedule_single_action` 排程未來時間。新增 `scheduled=true` 後，引擎不會在成功路徑再排一次 `time()` 立即排程。

**依賴**：Step 1.1

**風險**：低。僅在回傳值中新增一個欄位。

### Phase 2：訊息發送節點（可平行開發）

> 執行 Agent: `@wp-workflows:wordpress-master`

#### Step 2.1 — LineNode.execute() 實作

**檔案**：`inc/classes/Infrastructure/Repositories/WorkflowRule/NodeDefinitions/LineNode.php`

**行動**：

1. 新增 `use` 語句：
   - `use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\ParamHelper;`
   - `use J7\PowerFunnel\Infrastructure\Line\Services\MessageService;`

2. 實作 `execute()` 方法：
   ```
   (a) 建立 ParamHelper
   (b) 從 workflow->context 取得 line_user_id
       - 若為空字串或不存在：回傳 code 500，message 含 "line_user_id"
   (c) 用 ParamHelper.replace(content_tpl) 取得替換後的內容
   (d) try: MessageService::getInstance()->send_text_message(line_user_id, content)
       - 成功：回傳 code 200，message "LINE 訊息發送成功"
       - catch Exception：
         - 訊息含 "Channel Access Token" 則回傳 code 500 訊息含 "Channel Access Token"
         - 其他 Exception：回傳 code 500，保留原始錯誤訊息
   ```

3. 移除 `@throws \BadMethodCallException` PHPDoc 標記

**原因**：LineNode 需要透過 LINE Messaging API 發送文字訊息給指定用戶，是工作流引擎最常用的訊息發送節點之一。

**依賴**：Phase 1（引擎層需先完成，但 execute() 本身不直接依賴 AS 排程）

**風險**：低。MessageService 已有完整實作，只需呼叫 `send_text_message()`。

#### Step 2.2 — SmsNode.execute() 實作

**檔案**：`inc/classes/Infrastructure/Repositories/WorkflowRule/NodeDefinitions/SmsNode.php`

**行動**：

1. 新增 `use` 語句：
   - `use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\ParamHelper;`

2. 實作 `execute()` 方法：
   ```
   (a) 建立 ParamHelper
   (b) 取得 recipient = ParamHelper.replace(recipient 參數值)
       - 若為空字串：回傳 code 500，message 含 "recipient"
   (c) 取得 content = ParamHelper.replace(content_tpl 參數值)
   (d) 呼叫 apply_filters('power_funnel/sms/send',
         ['success' => false, 'message' => 'SMS 發送失敗'],
         $recipient, $content)
   (e) 依據回傳值的 success 欄位：
       - true：回傳 code 200，message 為 filter 回傳的 message
       - false：回傳 code 500，message 為 filter 回傳的 message
   ```

3. 移除 `@throws \BadMethodCallException` PHPDoc 標記

**原因**：SmsNode 透過 WordPress filter 委派 SMS 發送，這個設計讓用戶可以插入任何 SMS 服務（如 Twilio、Mitake）的 filter 來實際發送。

**依賴**：Phase 1

**風險**：低。純 filter 呼叫，邏輯簡單。

#### Step 2.3 — WebhookNode.execute() 實作

**檔案**：`inc/classes/Infrastructure/Repositories/WorkflowRule/NodeDefinitions/WebhookNode.php`

**行動**：

1. 新增 `use` 語句：
   - `use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\ParamHelper;`

2. 實作 `execute()` 方法：
   ```
   (a) 建立 ParamHelper
   (b) 取得 url = node->params['url']
       - 若為空字串：回傳 code 500，message 含 "url"
   (c) 取得 method = node->params['method'] ?? 'POST'
   (d) 取得 headers_raw = node->params['headers'] ?? ''
       - 若為空字串：headers = []
       - 否則 json_decode，失敗則回傳 code 500，message 含 "headers"
   (e) 取得 body = ParamHelper.replace(body_tpl 參數值 ?? '')
   (f) 呼叫 wp_remote_request(url, ['method' => method, 'headers' => headers, 'body' => body])
   (g) 檢查回傳值：
       - is_wp_error：回傳 code 500，message 含 WP_Error message
       - wp_remote_retrieve_response_code >= 200 且 < 300：回傳 code 200，"Webhook 發送成功"
       - 其他 HTTP status：回傳 code 500，message 含 "HTTP {status}"
   ```

3. 移除 `@throws \BadMethodCallException` PHPDoc 標記

**原因**：WebhookNode 使用 WordPress 內建的 HTTP API 發送請求，支援 GET/POST/PUT/DELETE 方法和自訂 headers。

**依賴**：Phase 1

**風險**：低。使用 `wp_remote_request()` 標準 WordPress API。

### Phase 3：時間控制節點（依序開發）

> 執行 Agent: `@wp-workflows:wordpress-master`

#### Step 3.1 — WaitUntilNode.execute() 實作

**檔案**：`inc/classes/Infrastructure/Repositories/WorkflowRule/NodeDefinitions/WaitUntilNode.php`

**行動**：

1. 新增 `use` 語句：
   - `use J7\PowerFunnel\Shared\Enums\EWorkflowStatus;`

2. 實作 `execute()` 方法：
   ```
   (a) 取得 datetime_str = node->params['datetime'] ?? ''
       - 若為空字串：回傳 code 500，message 含 "datetime"
   (b) timestamp = strtotime(datetime_str)
       - 若為 false（無法解析）：回傳 code 500，message 含 "datetime"
   (c) 若 timestamp < time()，則 timestamp = time()（已過期，立即排程）
   (d) 呼叫 as_schedule_single_action(
         timestamp,
         'power_funnel/workflow/' . EWorkflowStatus::RUNNING->value,
         ['workflow_id' => $workflow->id]
       )
   (e) 依據 action_id：
       - 0：回傳 code 500，message 含 "排程失敗"
       - > 0：回傳 code 200，scheduled=true，message 含 "等待至"
   ```

3. 移除 `@throws \BadMethodCallException` PHPDoc 標記

**原因**：WaitUntilNode 與既有 WaitNode 類似，但接受絕對時間而非相對時間。模式已由 WaitNode 建立。

**依賴**：Phase 1（需要 `scheduled` 欄位）

**風險**：低。模式與 WaitNode 一致。

#### Step 3.2 — TimeWindowNode.execute() 實作

**檔案**：`inc/classes/Infrastructure/Repositories/WorkflowRule/NodeDefinitions/TimeWindowNode.php`

**行動**：

1. 新增 `use` 語句：
   - `use J7\PowerFunnel\Shared\Enums\EWorkflowStatus;`

2. 實作 `execute()` 方法：
   ```
   (a) 取得 start_time = node->params['start_time'] ?? ''
       - 若為空字串：回傳 code 500，message 含 "start_time"
   (b) 取得 end_time = node->params['end_time'] ?? ''
       - 若為空字串：回傳 code 500，message 含 "end_time"
   (c) 取得 timezone_str = node->params['timezone'] ?? ''
       - 若為空字串：使用 wp_timezone_string()
   (d) 建立 DateTimeZone 物件
   (e) 判斷當前時間是否在窗口內（呼叫私有方法 is_in_window）：
       - start == end：24 小時窗口，立即排程
       - start < end（正常窗口）：now_time 在 [start, end) 內 → 立即排程
       - start > end（跨日窗口）：now_time >= start 或 now_time < end → 立即排程
       - 否則：計算下一個 start_time 的 timestamp
   (f) 呼叫 as_schedule_single_action(timestamp, ...)
   (g) 依據 action_id：
       - 0：回傳 code 500，message 含 "排程失敗"
       - > 0：回傳 code 200，scheduled=true，message 含 "時間窗口內" 或 "排程至"
   ```

3. 新增私有方法 `calculate_schedule_timestamp(string $start_time, string $end_time, \DateTimeZone $tz): int`：
   ```
   (a) 取得當前時間（使用指定時區）
   (b) 解析 start_time 和 end_time 為今天的 DateTime
   (c) 判斷是否在窗口內：
       - start == end → return time()
       - start < end（正常）→ now 在 [start, end) → return time()
       - start > end（跨日）→ now >= start 或 now < end → return time()
   (d) 不在窗口內，計算下一個 start_time：
       - 正常窗口且 now < start → 今天 start_time 的 timestamp
       - 正常窗口且 now >= end → 明天 start_time 的 timestamp
       - 跨日窗口且 end <= now < start → 今天 start_time 的 timestamp
   ```

4. 移除 `@throws \BadMethodCallException` PHPDoc 標記

**原因**：TimeWindowNode 是最複雜的時間控制節點，需要處理正常窗口、跨日窗口、24 小時窗口三種情境。

**依賴**：Phase 1（需要 `scheduled` 欄位）

**風險**：**中**。跨日窗口的時間計算邏輯較複雜，是本次實作中最容易出 bug 的部分。需要完善的單元/整合測試覆蓋所有邊界情況：
- 正常窗口：窗口前 / 窗口內 / 窗口後
- 跨日窗口：窗口內（當日部分）/ 窗口內（隔日部分）/ 窗口外
- 邊界值：start == end

### Phase 4：用戶操作節點

> 執行 Agent: `@wp-workflows:wordpress-master`

#### Step 4.1 — TagUserNode.execute() 實作

**檔案**：`inc/classes/Infrastructure/Repositories/WorkflowRule/NodeDefinitions/TagUserNode.php`

**行動**：

1. 新增 `use` 語句：
   - `use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;`

2. 實作 `execute()` 方法：
   ```
   (a) 取得 tags = node->params['tags'] ?? []
       - 若非陣列或為空陣列：回傳 code 500，message 含 "tags"
   (b) 取得 action = node->params['action'] ?? ''
       - 若非 'add' 也非 'remove'：回傳 code 500，message 含 "action"
   (c) 取得 user_id = workflow->context['line_user_id'] ?? ''
       - 若為空字串：回傳 code 500，message 含 "user_id"
   (d) 讀取現有標籤：get_user_meta 或自訂儲存機制取得 pf_user_tags
       - JSON decode，若不是陣列則預設 []
   (e) action == 'add'：
       - 計算新標籤（tags 中不在 existing_tags 中的）
       - 合併去重：array_values(array_unique(array_merge(existing_tags, tags)))
       - 寫回 user_meta
       - 對每個新標籤呼叫 TriggerPointService::fire_user_tagged(user_id, tag)
       - 回傳 code 200，message "標籤新增成功"
   (f) action == 'remove'：
       - 過濾：array_values(array_diff(existing_tags, tags))
       - 寫回 user_meta
       - 回傳 code 200，message "標籤移除成功"
   ```

3. 移除 `@throws \BadMethodCallException` PHPDoc 標記

**注意**：TagUserNode 的 user_meta 儲存機制需確認。由於 `line_user_id` 不是 WordPress user ID，無法直接使用 `get_user_meta()`/`update_user_meta()`。需要確認專案中如何將 LINE user ID 映射到 WordPress user 或使用 `wp_options` / 自訂表作為替代。若專案已有 LINE user 到 WP user 的映射機制，則取得 WP user ID 後使用 `get_user_meta()`。否則可能需要使用 `get_option("pf_user_tags_{$line_user_id}")` 或類似機制。

> 此為需要在實作前確認的設計決策。建議在實作時搜尋 `line_user_id` 與 `user_meta` 的現有使用方式來決定。

**依賴**：Phase 1

**風險**：**中**。`fire_user_tagged()` 會觸發 `pf/trigger/user_tagged` hook，若有 WorkflowRule 監聽此觸發點，會建立新的 workflow 實例。RecursionGuard 已處理無限遞迴問題，但需要在測試中驗證。

#### Step 4.2 — TagUserNode form_fields 更新

**檔案**：`inc/classes/Infrastructure/Repositories/WorkflowRule/NodeDefinitions/TagUserNode.php`

**行動**：在 `__construct()` 中修改 `tags` 欄位定義：

- `'type'` 從 `'select'` 改為 `'tags_input'`
- `'description'` 從 `'選擇要操作的標籤'` 改為 `'輸入要操作的標籤'`（或類似）
- 移除 `options` 屬性（若有）

確認 `action` 欄位保持不變（仍為 select，含 add/remove 選項）。

**原因**：tags 改為自由輸入的純字串標籤，不需要預定義選項。前端需要對應 `tags_input` 類型渲染 tag input 元件。

**依賴**：無（可與 Step 4.1 一起完成）

**風險**：低。前端若尚未支援 `tags_input` 類型，需要在前端另行實作該元件（不在本次後端範圍內，但需記錄為後續工作）。

## 測試策略

> 此 section 供 tdd-coordinator 交給 test-creator 執行。

### 整合測試位置與對應 Feature

| Feature 檔案 | 整合測試檔案 | 測試重點 |
|--------------|-------------|----------|
| `action-scheduler-chaining.feature` | `tests/integration/Workflow/ActionSchedulerChainingTest.php` | 非延遲節點成功後 AS 排程、延遲節點 scheduled=true 不排程、失敗不排程、最後節點 completed、skip(301) 排程 |
| `line-node-execute.feature` | `tests/integration/NodeSystem/LineNodeExecuteTest.php` | 發送成功 200、缺 line_user_id 500、Token 未設定 500、API 錯誤 500、模板替換 |
| `sms-node-execute.feature` | `tests/integration/NodeSystem/SmsNodeExecuteTest.php` | filter success=true 200、預設值 500、recipient 空 500、模板替換 |
| `webhook-node-execute.feature` | `tests/integration/NodeSystem/WebhookNodeExecuteTest.php` | HTTP 2xx 200、HTTP 非2xx 500、WP_Error 500、url 空 500、headers 無效 500、body 模板替換 |
| `wait-until-node-execute.feature` | `tests/integration/NodeSystem/WaitUntilNodeExecuteTest.php` | 未來時間排程、過去時間立即排程、排程失敗 500、datetime 空/無效 500 |
| `time-window-node-execute.feature` | `tests/integration/NodeSystem/TimeWindowNodeExecuteTest.php` | 窗口內立即排程、窗口前排程至 start、窗口後排程至明天 start、跨日窗口、start==end 24hr、排程失敗 500 |
| `tag-user-node-execute.feature` | `tests/integration/NodeSystem/TagUserNodeExecuteTest.php` | add 新增標籤、add 去重、remove 移除、fire_user_tagged 觸發、remove 不觸發、tags 空 500、action 無效 500、無 user_id 500 |
| `tag-user-form-fields.feature` | `tests/integration/NodeSystem/TagUserFormFieldsTest.php` | tags 欄位 type=tags_input、無 options、action 欄位不變 |

### 測試執行指令

```bash
composer test                    # 全部整合測試
composer test -- --filter=ActionSchedulerChaining   # 單一測試
composer test -- --filter=LineNodeExecute
```

### 關鍵邊界情況

1. **AS 排程**：
   - `as_schedule_single_action` 回傳 0 的處理
   - 最後一個節點成功後，AS 排程觸發 `try_execute` 應進入 completed
   - 帶 `next_node_id` 的分支節點成功後仍需排程

2. **TimeWindowNode**：
   - 跨日窗口 22:00~06:00，當前 23:00（在窗口內）
   - 跨日窗口 22:00~06:00，當前 03:00（在隔日部分，仍在窗口內）
   - 跨日窗口 22:00~06:00，當前 10:00（不在窗口內，排程至今天 22:00）
   - start_time == end_time（24 小時窗口）
   - timezone 未提供時使用 wp_timezone_string()

3. **TagUserNode**：
   - 新增已存在的標籤不應重複
   - 新增已存在的標籤不觸發 fire_user_tagged
   - 移除不存在的標籤不報錯
   - fire_user_tagged 觸發子 workflow 的 RecursionGuard 防護

4. **WorkflowResultDTO**：
   - 舊的序列化資料反序列化後 scheduled 預設為 false（向下相容）

### 測試 Mock 策略

- **LineNode**：mock `MessageService::getInstance()` 的 `send_text_message()` 方法
- **SmsNode**：使用 `add_filter('power_funnel/sms/send', ...)` 掛載測試 filter
- **WebhookNode**：mock `wp_remote_request()` 回傳值（使用 `pre_http_request` filter）
- **WaitUntilNode / TimeWindowNode**：mock `as_schedule_single_action()` 或使用 Action Scheduler 測試輔助（若可用）
- **TagUserNode**：直接操作 user_meta，驗證 `fire_user_tagged` 是否被呼叫（使用 `did_action()` 計數器或 mock）

## 風險與緩解措施

- **高**：**NodeDTO::try_execute() 修改影響所有已實作節點**
  - 緩解措施：Phase 1 完成後立即執行既有測試套件（`composer test`），確保 EmailNode、WaitNode、YesNoBranchNode 的既有測試全部通過
  - 緩解措施：為 action-scheduler-chaining.feature 建立完整測試，覆蓋非延遲、延遲、失敗、最後節點四種情境

- **中**：**TimeWindowNode 跨日窗口時間計算**
  - 緩解措施：為所有時間邊界情況（正常/跨日/24hr）建立獨立測試案例
  - 緩解措施：使用 `DateTimeImmutable` 避免時間物件狀態汙染

- **中**：**TagUserNode 的 user_meta 儲存機制不確定**
  - 緩解措施：實作前搜尋專案中 `line_user_id` 與 `user_meta`/`pf_user_tags` 的使用方式，確認正確的存取 API

- **低**：**前端 tags_input 元件尚未實作**
  - 緩解措施：後端先完成 form_fields 定義變更；前端 tags_input 元件列為後續任務（不影響後端功能）

- **低**：**SMS filter 無掛載時預設回傳失敗**
  - 緩解措施：這是 by design。若無 SMS 外掛，SmsNode 會安全地回傳 code 500。workflow 會標記為 failed，管理員可在管理端看到錯誤訊息。

## 成功標準

- [ ] `composer test` 全部通過（含既有測試 + 新增測試）
- [ ] `composer analyse` (PHPStan Level 9) 無新增錯誤
- [ ] `composer lint` (PHPCS) 無新增違規
- [ ] 6 個 NodeDefinition 的 `execute()` 不再拋出 `BadMethodCallException`
- [ ] EmailNode 成功後 workflow 能自動透過 AS 排程繼續執行下一個節點
- [ ] WaitNode / WaitUntilNode / TimeWindowNode 成功後引擎不二次排程
- [ ] TagUserNode add 操作能觸發 `fire_user_tagged`，且 RecursionGuard 正常運作
- [ ] TagUserNode 的 tags 欄位 type 為 `tags_input`
- [ ] WorkflowResultDTO 新增 `scheduled` 欄位，向下相容舊資料

## 限制條件

- **不實作** SplitBranchNode（保留 `BadMethodCallException` 存根）
- **不實作** 前端 `tags_input` UI 元件（僅修改後端 form_fields 定義）
- **不新增** REST API 端點
- **不修改** 資料庫 schema（僅擴充 DTO 欄位與 user_meta）
- **不處理** SMS 外掛整合（僅提供 filter hook，實際發送由第三方外掛負責）

## 預估複雜度：中

Phase 1（引擎層）為核心改動，需謹慎處理向下相容；Phase 2-3 的節點實作多為套用既有模式；Phase 4 的 TagUserNode 因涉及觸發點機制而稍複雜。TimeWindowNode 的跨日窗口計算是邏輯最密集的部分。
