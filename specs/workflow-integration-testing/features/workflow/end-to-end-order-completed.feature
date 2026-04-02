@ignore @command
Feature: ORDER_COMPLETED 全鏈路 E2E 整合測試

  以 pf/trigger/order_completed 觸發，建立包含 TagUserNode + EmailNode +
  WaitNode + WebhookNode 的 workflow，驗證整條鏈路走通。WaitNode 暫停後
  透過手動觸發 power_funnel/workflow/running 模擬 Action Scheduler 到期。

  測試策略：
  - 建立 publish 狀態的 WorkflowRule，trigger_point = pf/trigger/order_completed
  - 使用 test_email 節點定義（繞過 ReplaceHelper null-object bug）
  - WebhookNode 使用 pre_http_request filter mock，不做真實 HTTP 請求
  - TagUserNode 使用 user_meta 'pf_user_tags' 驗證標籤是否寫入
  - WaitNode 手動 do_action 模擬 AS 到期
  - tear_down() 呼叫 as_unschedule_all_actions() 清理 AS pending actions

  Background:
    Given 系統已註冊以下 NodeDefinition：
      | id           | name          | type         |
      | tag_user     | 貼標籤         | action       |
      | test_email   | 測試 Email 節點 | send_message |
      | wait         | 等待           | action       |
      | webhook      | Webhook       | action       |
    And TestCallable::$test_context 設定為：
      | key              | value               |
      | order_id         | 1001                |
      | customer_email   | alice@example.com   |
      | customer_name    | Alice               |
      | line_user_id     | U_alice_line_001    |
    And context_callable_set 為 [TestCallable::class, 'return_test_context']
    And 已設定 pre_http_request filter mock（回傳 HTTP 200）

  Rule: 後置（狀態）- ORDER_COMPLETED 觸發後應建立 running 狀態的 Workflow

    Example: do_action 觸發後 Workflow 建立為 running
      Given 系統中有以下已發布的 WorkflowRule：
        | id | trigger_point                | nodes |
        | 10 | pf/trigger/order_completed   | [TagUserNode(tags=["vip"]), EmailNode, WaitNode(30min), WebhookNode] |
      When 系統觸發 do_action('pf/trigger/order_completed', $context_callable_set)
      Then 應建立 1 筆 pf_workflow 紀錄
      And 該 Workflow 的 status 應為 "running"
      And 該 Workflow 的 workflow_rule_id 應為 "10"
      And 該 Workflow 的 trigger_point 應為 "pf/trigger/order_completed"
      And 該 Workflow 的 nodes 應有 4 個

  Rule: 後置（狀態）- 四個節點應依序執行至 WaitNode 暫停

    Example: TagUserNode 與 EmailNode 成功後 WaitNode 暫停 workflow
      Given 系統中有以下已發布的 WorkflowRule：
        | id | trigger_point                | nodes |
        | 10 | pf/trigger/order_completed   | [TagUserNode(tags=["vip"],action="add"), test_email(recipient="context",subject_tpl="Hi",content_tpl="Hello"), WaitNode(duration="30",unit="minutes"), WebhookNode(url="https://hook.example.com/test",method="POST",body_tpl="{}")] |
      And 暫時移除 start_workflow hook 避免自動執行
      When 系統觸發 do_action('pf/trigger/order_completed', $context_callable_set)
      Then 應建立 1 筆 running 狀態的 Workflow
      When 系統呼叫 WorkflowDTO::try_execute()（第一次，執行 TagUserNode）
      Then 節點 "n1" 的結果 code 應為 200
      And 用戶的 pf_user_tags meta 應包含 "vip"
      When 系統呼叫 WorkflowDTO::try_execute()（第二次，執行 EmailNode）
      Then 節點 "n2" 的結果 code 應為 200
      When 系統呼叫 WorkflowDTO::try_execute()（第三次，執行 WaitNode）
      Then 節點 "n3" 的結果 code 應為 200
      And 節點 "n3" 的結果 scheduled 應為 true
      And Workflow 的 status 應仍為 "running"
      And Workflow 的 results 應有 3 筆

  Rule: 後置（狀態）- WaitNode 到期後剩餘節點繼續執行至 completed

    Example: 手動觸發 power_funnel/workflow/running 後 WebhookNode 執行完成
      Given 延續上述情境，Workflow 已有 3 筆 results（TagUserNode + EmailNode + WaitNode）
      When 系統觸發 do_action('power_funnel/workflow/running', ['workflow_id' => $wf_id])
      Then 節點 "n4"（WebhookNode）的結果 code 應為 200
      And Workflow 的 results 應有 4 筆
      And Workflow 的 status 應為 "completed"

  Rule: 後置（狀態）- 所有節點結果的 code 均為 200 或 301

    Example: 完整鏈路的 results 驗證
      Given 延續全鏈路完成後的 Workflow
      Then results 應為：
        | node_id | code | scheduled |
        | n1      | 200  | false     |
        | n2      | 200  | false     |
        | n3      | 200  | true      |
        | n4      | 200  | false     |
      And Workflow 最終 status 應為 "completed"
