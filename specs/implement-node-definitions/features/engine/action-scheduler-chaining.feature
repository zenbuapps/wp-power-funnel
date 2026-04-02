@ignore @command
Feature: Action Scheduler 統一節點排程

  所有節點執行成功後，引擎統一透過 Action Scheduler 排程下一個節點。
  WaitNode / WaitUntilNode / TimeWindowNode 自行排程（scheduled=true），引擎不二次排程。
  其餘節點（EmailNode、LineNode、SmsNode、WebhookNode、TagUserNode、YesNoBranchNode）
  由引擎排程立即執行下一節點。

  Background:
    Given 系統已註冊以下 NodeDefinition：
      | id    | name         | type         |
      | email | 傳送 Email   | send_message |
      | wait  | 等待         | action       |
      | line  | 傳送 LINE 訊息 | send_message |
    And 系統中有以下 Workflow（status=running）：
      | id  |
      | 100 |

  Rule: 後置（狀態）- 非延遲節點成功後引擎應排程立即執行下一節點

    Example: EmailNode 成功後引擎排程 as_schedule_single_action(time(), ...)
      Given Workflow 100 有以下節點：
        | id | node_definition_id | params |
        | n1 | email              | {"recipient":"test@example.com","subject_tpl":"Hi","content_tpl":"Hello"} |
        | n2 | line               | {"content_tpl":"Follow up"} |
      And 節點 "n1" 執行成功，回傳 WorkflowResultDTO(code=200, scheduled=false)
      When NodeDTO::try_execute() 處理成功結果
      Then 應呼叫 as_schedule_single_action(time(), 'power_funnel/workflow/running', ['workflow_id' => '100'])
      And Workflow 100 的 results 應包含節點 "n1" 的結果

  Rule: 後置（狀態）- 延遲節點成功後引擎不應二次排程

    Example: WaitNode 回傳 scheduled=true 時引擎不排程
      Given Workflow 100 有以下節點：
        | id | node_definition_id | params |
        | n1 | wait               | {"duration":"30","unit":"minutes"} |
        | n2 | email              | {"recipient":"test@example.com","subject_tpl":"Hi","content_tpl":"After wait"} |
      And 節點 "n1" 執行成功，回傳 WorkflowResultDTO(code=200, scheduled=true)
      When NodeDTO::try_execute() 處理成功結果
      Then 不應呼叫 as_schedule_single_action
      And Workflow 100 的 results 應包含節點 "n1" 的結果

  Rule: 後置（狀態）- 節點執行失敗時不應排程下一節點

    Example: 節點回傳 code=500 時不排程
      Given Workflow 100 有以下節點：
        | id | node_definition_id | params |
        | n1 | email              | {"recipient":"","subject_tpl":"Hi","content_tpl":"Hello"} |
        | n2 | line               | {"content_tpl":"Follow up"} |
      And 節點 "n1" 執行失敗（拋出 RuntimeException）
      When NodeDTO::try_execute() 捕獲例外
      Then 不應呼叫 as_schedule_single_action
      And Workflow 100 的 status 應為 "failed"

  Rule: 後置（狀態）- 最後一個節點成功後引擎排程使 workflow 進入 completed

    Example: 最後一個節點成功後排程觸發 try_execute 進入 completed
      Given Workflow 100 有以下節點：
        | id | node_definition_id | params |
        | n1 | email              | {"recipient":"test@example.com","subject_tpl":"Hi","content_tpl":"Hello"} |
      And Workflow 100 的 results 為空（即將執行第一個也是最後一個節點）
      And 節點 "n1" 執行成功，回傳 WorkflowResultDTO(code=200, scheduled=false)
      When NodeDTO::try_execute() 處理成功結果
      Then 應呼叫 as_schedule_single_action(time(), 'power_funnel/workflow/running', ['workflow_id' => '100'])
      And 下次 try_execute 被觸發時 get_current_index() 回傳 null
      And Workflow 100 的 status 應設為 "completed"

  Rule: 後置（狀態）- WorkflowResultDTO 新增 scheduled 欄位

    Example: WorkflowResultDTO 預設 scheduled 為 false
      When 建立 WorkflowResultDTO(node_id='n1', code=200, message='OK')
      Then scheduled 欄位應為 false

    Example: WorkflowResultDTO 可設定 scheduled 為 true
      When 建立 WorkflowResultDTO(node_id='n1', code=200, message='等待中', scheduled=true)
      Then scheduled 欄位應為 true

  Rule: 後置（狀態）- 帶有 next_node_id 的分支節點也應排程

    Example: YesNoBranchNode 回傳 next_node_id 時引擎仍排程下一步
      Given Workflow 100 有以下節點：
        | id | node_definition_id | params |
        | n1 | yes_no_branch      | {"condition_field":"order_total","operator":"gt","condition_value":"1000","yes_next_node_id":"n2","no_next_node_id":"n3"} |
        | n2 | email              | {"recipient":"test@example.com","subject_tpl":"VIP","content_tpl":"Hi VIP"} |
        | n3 | email              | {"recipient":"test@example.com","subject_tpl":"Thanks","content_tpl":"Hi"} |
      And 節點 "n1" 執行成功，回傳 WorkflowResultDTO(code=200, scheduled=false, next_node_id='n2')
      When NodeDTO::try_execute() 處理成功結果
      Then 應呼叫 as_schedule_single_action(time(), 'power_funnel/workflow/running', ['workflow_id' => '100'])
