@ignore @command
Feature: WaitUntilNode 等待至指定時間點

  WaitUntilNode 將 workflow 暫停至指定的日期時間。
  使用 Action Scheduler 排程，到達指定時間後恢復執行。
  若指定時間已過，視為立即執行（使用 time()）。

  Background:
    Given 系統已註冊以下 NodeDefinition：
      | id         | name          | type   |
      | wait_until | 等待至指定時間 | action |
    And 系統中有以下 Workflow（status=running）：
      | id  |
      | 100 |
    And Workflow 100 有以下節點：
      | id | node_definition_id | params                              |
      | n1 | wait_until         | {"datetime":"2026-04-15T10:00:00"}  |
      | n2 | email              | {"recipient":"test@example.com","subject_tpl":"Hi","content_tpl":"After wait"} |

  Rule: 後置（狀態）- 未來時間應排程至該時間點

    Example: datetime 為未來時間
      Given 當前時間為 2026-04-01T09:00:00
      And 節點 "n1" 的 datetime 為 "2026-04-15T10:00:00"
      When 系統執行節點 "n1"（WaitUntilNode）
      Then 應呼叫 as_schedule_single_action(timestamp_of("2026-04-15T10:00:00"), 'power_funnel/workflow/running', ['workflow_id' => '100'])
      And 結果的 code 應為 200
      And 結果的 message 應包含 "等待至"
      And 結果的 scheduled 應為 true

  Rule: 後置（狀態）- 過去時間應立即排程

    Example: datetime 已過期
      Given 當前時間為 2026-04-15T12:00:00
      And 節點 "n1" 的 datetime 為 "2026-04-10T10:00:00"
      When 系統執行節點 "n1"（WaitUntilNode）
      Then 應呼叫 as_schedule_single_action(time(), 'power_funnel/workflow/running', ['workflow_id' => '100'])
      And 結果的 code 應為 200
      And 結果的 scheduled 應為 true

  Rule: 後置（狀態）- 排程失敗時回傳 code 500

    Example: as_schedule_single_action 回傳 0
      Given as_schedule_single_action() 回傳 0（排程失敗）
      When 系統執行節點 "n1"（WaitUntilNode）
      Then 結果的 code 應為 500
      And 結果的 message 應包含 "排程失敗"

  Rule: 前置（參數）- datetime 必須提供且格式正確

    Example: datetime 為空時失敗
      Given 節點 "n1" 的 params 中 datetime 為 ""
      When 系統執行節點 "n1"（WaitUntilNode）
      Then 結果的 code 應為 500
      And 結果的 message 應包含 "datetime"

    Example: datetime 格式無法解析時失敗
      Given 節點 "n1" 的 params 中 datetime 為 "not-a-date"
      When 系統執行節點 "n1"（WaitUntilNode）
      Then 結果的 code 應為 500
      And 結果的 message 應包含 "datetime"
