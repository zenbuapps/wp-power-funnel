@ignore @command
Feature: Context 跨節點傳遞與 ParamHelper 替換驗證

  驗證 context_callable_set 解析後產生的 context 能在節點 params 中
  被 ParamHelper 正確替換。具體測試 {{variable}} 模板替換與 "context"
  關鍵字取值在 wp_mail 實際呼叫中的效果。

  測試策略：
  - TestCallable::$test_context 設定測試用 context 資料
  - context_callable_set 使用 [TestCallable::class, 'return_test_context']
  - test_email 節點定義直接從 params 取 recipient 呼叫 wp_mail()
  - 透過 wp_mail filter 攔截 wp_mail 呼叫，捕捉實際收件人

  Background:
    Given 系統已註冊以下 NodeDefinition：
      | id         | name          | type         |
      | test_email | 測試 Email 節點 | send_message |
    And TestCallable::$test_context 設定為：
      | key              | value               |
      | order_id         | 999                 |
      | customer_email   | buyer@example.com   |
      | customer_name    | Alice               |
    And context_callable_set 為 [TestCallable::class, 'return_test_context']

  Rule: 後置（狀態）- context key 應能透過 ParamHelper 在節點 params 中被替換

    Example: EmailNode recipient 設為 "context" 時從 context 取得 customer_email 並發信
      Given 系統中有以下 Workflow（status=running）：
        | id  | context_callable_set                                                   |
        | 200 | {"callable":["TestCallable","return_test_context"],"params":[]}        |
      And Workflow 200 有以下節點：
        | id | node_definition_id | params |
        | n1 | test_email         | {"recipient":"context","subject_tpl":"歡迎 {{customer_name}}","content_tpl":"您的訂單 {{order_id}} 已完成"} |
      And Workflow 200 的 results 為空
      And 已掛載 wp_mail filter 攔截收件人
      When 系統呼叫 WorkflowDTO::try_execute() 執行節點 "n1"
      Then 節點 "n1" 的結果 code 應為 200
      And wp_mail 攔截到的收件人應為 "buyer@example.com"

  Rule: 後置（狀態）- 多個 context key 在模板中應同時被替換

    Example: subject_tpl 與 content_tpl 中的多個 {{variable}} 同時替換
      Given 系統中有以下 Workflow（status=running）：
        | id  | context_callable_set                                                   |
        | 201 | {"callable":["TestCallable","return_test_context"],"params":[]}        |
      And Workflow 201 有以下節點：
        | id | node_definition_id | params |
        | n1 | test_email         | {"recipient":"buyer@example.com","subject_tpl":"訂單 {{order_id}} 確認","content_tpl":"親愛的 {{customer_name}}，訂單 {{order_id}} 已完成"} |
      And Workflow 201 的 results 為空
      And 已掛載 wp_mail filter 攔截郵件主旨與內容
      When 系統呼叫 WorkflowDTO::try_execute() 執行節點 "n1"
      Then 節點 "n1" 的結果 code 應為 200
      And wp_mail 攔截到的主旨應為 "訂單 999 確認"
      And wp_mail 攔截到的內容應包含 "親愛的 Alice，訂單 999 已完成"

  Rule: 後置（狀態）- context_callable_set 經 serialize/unserialize 後仍可正確解析

    Example: context_callable_set 儲存到 wp_postmeta 後讀取仍可呼叫
      Given context_callable_set 為 {"callable":["TestCallable","return_test_context"],"params":[]}
      And TestCallable::$test_context 設定為 {"order_id":"999","customer_email":"buyer@example.com"}
      When context_callable_set 經 WordPress update_post_meta 儲存後再 get_post_meta 讀回
      And 呼叫 call_user_func_array(callable, params)
      Then 回傳結果應包含 key "order_id" 值為 "999"
      And 回傳結果應包含 key "customer_email" 值為 "buyer@example.com"

  Rule: 前置（狀態）- context_callable_set 為空時 context 應為空陣列

    Example: context_callable_set 為空陣列時 context 為空
      Given 系統中有以下 Workflow（status=running）：
        | id  | context_callable_set |
        | 202 | []                   |
      And Workflow 202 有以下節點：
        | id | node_definition_id | params |
        | n1 | test_email         | {"recipient":"fallback@example.com","subject_tpl":"Hi","content_tpl":"Hello"} |
      And Workflow 202 的 results 為空
      When 系統呼叫 WorkflowDTO::try_execute() 執行節點 "n1"
      Then 節點 "n1" 的結果 code 應為 200
      And wp_mail 攔截到的收件人應為 "fallback@example.com"
