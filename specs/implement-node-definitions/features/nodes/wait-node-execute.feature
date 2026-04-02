@ignore @command
Feature: WaitNode duration + unit 轉換為 Unix timestamp 並排程

  WaitNode 是工作流引擎的「相對延遲」節點。使用者在節點編輯器設定
  「等待 N 分鐘/小時/天」，workflow 執行到此節點時，將 duration + unit
  轉換為 Unix timestamp，透過 Action Scheduler 排程延遲，到期後繼續
  執行下一個節點。

  Background:
    Given 系統已註冊以下 NodeDefinition：
      | id   | name | type   |
      | wait | 等待  | action |
    And 系統中有以下 Workflow（status=running）：
      | id  |
      | 100 |
    And Workflow 100 有以下節點：
      | id | node_definition_id | params                              |
      | n1 | wait               | {"duration":"30","unit":"minutes"}  |
      | n2 | email              | {"recipient":"test@example.com","subject_tpl":"Hi","content_tpl":"After wait"} |

  Rule: 後置（狀態）- 成功時應將 duration + unit 轉為 Unix timestamp 並排程

    Example: 等待 30 分鐘後排程成功
      Given 當前時間為 2026-04-15T10:00:00+08:00
      And 節點 "n1" 的 params 為 {"duration":"30","unit":"minutes"}
      When 系統執行節點 "n1"（WaitNode）
      Then 應呼叫 as_schedule_single_action(timestamp_of("2026-04-15T10:30:00+08:00"), 'power_funnel/workflow/running', ['workflow_id' => '100'])
      And 結果的 code 應為 200
      And 結果的 message 應為 "等待 30 分鐘"
      And 結果的 scheduled 應為 true

    Example: 等待 2 小時後排程成功
      Given 當前時間為 2026-04-15T10:00:00+08:00
      And 節點 "n1" 的 params 為 {"duration":"2","unit":"hours"}
      When 系統執行節點 "n1"（WaitNode）
      Then 應呼叫 as_schedule_single_action(timestamp_of("2026-04-15T12:00:00+08:00"), 'power_funnel/workflow/running', ['workflow_id' => '100'])
      And 結果的 code 應為 200
      And 結果的 message 應為 "等待 2 小時"
      And 結果的 scheduled 應為 true

    Example: 等待 2 天後排程成功
      Given 當前時間為 2026-04-15T10:00:00+08:00
      And 節點 "n1" 的 params 為 {"duration":"2","unit":"days"}
      When 系統執行節點 "n1"（WaitNode）
      Then 應呼叫 as_schedule_single_action(timestamp_of("2026-04-17T10:00:00+08:00"), 'power_funnel/workflow/running', ['workflow_id' => '100'])
      And 結果的 code 應為 200
      And 結果的 message 應為 "等待 2 天"
      And 結果的 scheduled 應為 true

  Rule: 後置（狀態）- 排程失敗時回傳 code 500

    Example: as_schedule_single_action 回傳 0 時排程失敗
      Given 節點 "n1" 的 params 為 {"duration":"30","unit":"minutes"}
      And as_schedule_single_action() 回傳 0（排程失敗）
      When 系統執行節點 "n1"（WaitNode）
      Then 結果的 code 應為 500
      And 結果的 message 應為 "WaitNode 執行失敗：排程失敗"

  Rule: 前置（參數）- duration 必須提供

    Example: 缺少 duration 時操作失敗
      Given 節點 "n1" 的 params 為 {"unit":"minutes"}
      When 系統執行節點 "n1"（WaitNode）
      Then 結果的 code 應為 500
      And 結果的 message 應為 "WaitNode 執行失敗：缺少 duration"

  Rule: 前置（參數）- unit 必須提供

    Example: 缺少 unit 時操作失敗
      Given 節點 "n1" 的 params 為 {"duration":"30"}
      When 系統執行節點 "n1"（WaitNode）
      Then 結果的 code 應為 500
      And 結果的 message 應為 "WaitNode 執行失敗：缺少 unit"

  Rule: 前置（參數）- duration 必須大於 0

    Example: duration 為 0 時操作失敗
      Given 節點 "n1" 的 params 為 {"duration":"0","unit":"minutes"}
      When 系統執行節點 "n1"（WaitNode）
      Then 結果的 code 應為 500
      And 結果的 message 應為 "WaitNode 執行失敗：duration 必須大於 0"

    Example: duration 為負數時操作失敗
      Given 節點 "n1" 的 params 為 {"duration":"-5","unit":"minutes"}
      When 系統執行節點 "n1"（WaitNode）
      Then 結果的 code 應為 500
      And 結果的 message 應為 "WaitNode 執行失敗：duration 必須大於 0"

  Rule: 前置（參數）- unit 必須為支援的時間單位（minutes/hours/days）

    Example: unit 為不支援的值時操作失敗
      Given 節點 "n1" 的 params 為 {"duration":"1","unit":"weeks"}
      When 系統執行節點 "n1"（WaitNode）
      Then 結果的 code 應為 500
      And 結果的 message 應為 "WaitNode 執行失敗：不支援的時間單位 weeks"
