@ignore @command
Feature: Action Scheduler 統一節點排程測試實作

  補完 ActionSchedulerChainingTest 中全部 markTestIncomplete 的測試。
  驗證 NodeDTO::try_execute() 在成功、失敗、延遲節點三種路徑下的 AS 排程行為。

  測試策略：
  - 使用 test_email 節點定義（繞過 ReplaceHelper null-object bug）
  - 透過 as_has_scheduled_action() 驗證 AS 排程是否建立
  - 透過 as_unschedule_all_actions() 在 tear_down() 清理 AS pending actions

  Background:
    Given 系統已註冊以下 NodeDefinition：
      | id         | name          | type         |
      | test_email | 測試 Email 節點 | send_message |
      | wait       | 等待           | action       |
      | yes_no_branch | 是/否分支   | action       |
    And 系統中有以下 Workflow（status=running）：
      | id  |
      | 100 |

  Rule: 後置（狀態）- 非延遲節點成功後引擎應排程立即執行下一節點

    Example: test_email 成功後 AS 中有 power_funnel/workflow/running 排程
      Given Workflow 100 有以下節點：
        | id | node_definition_id | params |
        | n1 | test_email         | {"recipient":"test@example.com","subject_tpl":"Hi","content_tpl":"Hello"} |
        | n2 | test_email         | {"recipient":"test@example.com","subject_tpl":"Follow","content_tpl":"Up"} |
      And Workflow 100 的 results 為空
      When 系統呼叫 WorkflowDTO::try_execute() 執行節點 "n1"
      Then 節點 "n1" 的結果 code 應為 200
      And as_has_scheduled_action('power_funnel/workflow/running') 應回傳 true
      And Workflow 100 的 results 應包含 1 筆紀錄

  Rule: 後置（狀態）- 延遲節點成功後引擎不應二次排程

    Example: WaitNode 回傳 scheduled=true 時 AS 中有 WaitNode 自身的排程但引擎不再額外排程
      Given Workflow 100 有以下節點：
        | id | node_definition_id | params |
        | n1 | wait               | {"duration":"30","unit":"minutes"} |
        | n2 | test_email         | {"recipient":"test@example.com","subject_tpl":"Hi","content_tpl":"After wait"} |
      And Workflow 100 的 results 為空
      When 系統呼叫 WorkflowDTO::try_execute() 執行節點 "n1"
      Then 節點 "n1" 的結果 code 應為 200
      And 節點 "n1" 的結果 scheduled 應為 true
      And AS 中 power_funnel/workflow/running 的排程應僅有 WaitNode 自身建立的那一筆（引擎未二次排程）

  Rule: 後置（狀態）- 節點執行失敗時不應排程下一節點

    Example: 節點定義找不到時 workflow 標記 failed 且 AS 中無排程
      Given Workflow 100 有以下節點：
        | id | node_definition_id | params |
        | n1 | non_existent       | {} |
        | n2 | test_email         | {"recipient":"test@example.com","subject_tpl":"Hi","content_tpl":"Hello"} |
      And Workflow 100 的 results 為空
      And 清除所有 AS pending actions
      When 系統呼叫 WorkflowDTO::try_execute() 執行節點 "n1"
      Then 節點 "n1" 的結果 code 應為 500
      And as_has_scheduled_action('power_funnel/workflow/running') 應回傳 false
      And Workflow 100 的 status 應為 "failed"

  Rule: 後置（狀態）- 最後一個節點成功後引擎排程使 workflow 進入 completed

    Example: 單節點 workflow 成功後排程並最終 completed
      Given Workflow 100 有以下節點：
        | id | node_definition_id | params |
        | n1 | test_email         | {"recipient":"test@example.com","subject_tpl":"Hi","content_tpl":"Hello"} |
      And Workflow 100 的 results 為空
      When 系統呼叫 WorkflowDTO::try_execute() 執行節點 "n1"
      Then 節點 "n1" 的結果 code 應為 200
      And 應呼叫 as_schedule_single_action
      When 再次呼叫 WorkflowDTO::try_execute()（模擬 AS 到期觸發）
      Then Workflow 100 的 get_current_index() 應回傳 null
      And Workflow 100 的 status 應為 "completed"

  Rule: 後置（狀態）- WorkflowResultDTO 的 scheduled 欄位預設值

    Example: WorkflowResultDTO 未指定 scheduled 時預設為 false
      When 建立 WorkflowResultDTO(node_id='n1', code=200, message='OK')
      Then scheduled 欄位應為 false

    Example: WorkflowResultDTO 指定 scheduled=true 時為 true
      When 建立 WorkflowResultDTO(node_id='n1', code=200, message='等待中', scheduled=true)
      Then scheduled 欄位應為 true

  Rule: 後置（狀態）- 帶有 next_node_id 的分支節點成功後引擎應排程

    Example: YesNoBranchNode 回傳 next_node_id 時 AS 中有排程
      Given Workflow 100 的 context 為 {"order_total":"2500"}
      And Workflow 100 有以下節點：
        | id | node_definition_id | params |
        | n1 | yes_no_branch      | {"condition_field":"order_total","operator":"gt","condition_value":"1000","yes_next_node_id":"n2","no_next_node_id":"n3"} |
        | n2 | test_email         | {"recipient":"test@example.com","subject_tpl":"VIP","content_tpl":"Hi VIP"} |
        | n3 | test_email         | {"recipient":"test@example.com","subject_tpl":"Thanks","content_tpl":"Hi"} |
      And Workflow 100 的 results 為空
      When 系統呼叫 WorkflowDTO::try_execute() 執行節點 "n1"
      Then 節點 "n1" 的結果 code 應為 200
      And 節點 "n1" 的結果 next_node_id 應為 "n2"
      And as_has_scheduled_action('power_funnel/workflow/running') 應回傳 true
