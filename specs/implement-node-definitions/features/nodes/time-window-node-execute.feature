@ignore @command
Feature: TimeWindowNode 等待至時間窗口

  TimeWindowNode 確保後續節點在指定的時間窗口內執行。
  若當前時間已在窗口內，立即排程。若不在窗口內，排程至下一個窗口開始時刻。
  支援跨日窗口（如 22:00~06:00）。timezone 預設使用 wp_timezone_string()。

  Background:
    Given 系統已註冊以下 NodeDefinition：
      | id          | name       | type   |
      | time_window | 時間窗口    | action |
    And 系統中有以下 Workflow（status=running）：
      | id  |
      | 100 |
    And Workflow 100 有以下節點：
      | id | node_definition_id | params                                                        |
      | n1 | time_window        | {"start_time":"09:00","end_time":"18:00","timezone":"Asia/Taipei"} |
      | n2 | email              | {"recipient":"test@example.com","subject_tpl":"Hi","content_tpl":"In window"} |

  Rule: 後置（狀態）- 當前時間在窗口內應立即排程

    Example: 當前 10:00 在 09:00~18:00 窗口內
      Given 當前時間為 2026-04-01T10:00:00 Asia/Taipei
      When 系統執行節點 "n1"（TimeWindowNode）
      Then 應呼叫 as_schedule_single_action(time(), 'power_funnel/workflow/running', ['workflow_id' => '100'])
      And 結果的 code 應為 200
      And 結果的 message 應包含 "時間窗口內"
      And 結果的 scheduled 應為 true

  Rule: 後置（狀態）- 當前時間在窗口前應排程至 start_time

    Example: 當前 07:00 在 09:00~18:00 窗口前
      Given 當前時間為 2026-04-01T07:00:00 Asia/Taipei
      When 系統執行節點 "n1"（TimeWindowNode）
      Then 應排程至 2026-04-01T09:00:00 Asia/Taipei 的 Unix timestamp
      And 結果的 code 應為 200
      And 結果的 message 應包含 "排程至"
      And 結果的 scheduled 應為 true

  Rule: 後置（狀態）- 當前時間在窗口後應排程至隔天 start_time

    Example: 當前 20:00 在 09:00~18:00 窗口後
      Given 當前時間為 2026-04-01T20:00:00 Asia/Taipei
      When 系統執行節點 "n1"（TimeWindowNode）
      Then 應排程至 2026-04-02T09:00:00 Asia/Taipei 的 Unix timestamp
      And 結果的 code 應為 200
      And 結果的 scheduled 應為 true

  Rule: 後置（狀態）- 跨日窗口（start_time > end_time）

    Example: 22:00~06:00 窗口，當前 23:00（在窗口內）
      Given 節點 "n1" 的 start_time 為 "22:00"，end_time 為 "06:00"
      And 當前時間為 2026-04-01T23:00:00 Asia/Taipei
      When 系統執行節點 "n1"（TimeWindowNode）
      Then 應呼叫 as_schedule_single_action(time(), ...)
      And 結果的 code 應為 200

    Example: 22:00~06:00 窗口，當前 03:00（在窗口內，隔天部分）
      Given 節點 "n1" 的 start_time 為 "22:00"，end_time 為 "06:00"
      And 當前時間為 2026-04-02T03:00:00 Asia/Taipei
      When 系統執行節點 "n1"（TimeWindowNode）
      Then 應呼叫 as_schedule_single_action(time(), ...)
      And 結果的 code 應為 200

    Example: 22:00~06:00 窗口，當前 10:00（不在窗口內）
      Given 節點 "n1" 的 start_time 為 "22:00"，end_time 為 "06:00"
      And 當前時間為 2026-04-01T10:00:00 Asia/Taipei
      When 系統執行節點 "n1"（TimeWindowNode）
      Then 應排程至 2026-04-01T22:00:00 Asia/Taipei 的 Unix timestamp
      And 結果的 code 應為 200

  Rule: 前置（參數）- timezone 未提供時使用 wp_timezone_string()

    Example: timezone 為空時使用 WordPress 站台時區
      Given 節點 "n1" 的 params 中 timezone 為 ""
      And wp_timezone_string() 回傳 "Asia/Taipei"
      When 系統執行節點 "n1"（TimeWindowNode）
      Then 應使用 "Asia/Taipei" 時區計算時間窗口

  Rule: 前置（參數）- start_time 與 end_time 必須提供

    Example: start_time 為空時失敗
      Given 節點 "n1" 的 params 中 start_time 為 ""
      When 系統執行節點 "n1"（TimeWindowNode）
      Then 結果的 code 應為 500
      And 結果的 message 應包含 "start_time"

    Example: end_time 為空時失敗
      Given 節點 "n1" 的 params 中 end_time 為 ""
      When 系統執行節點 "n1"（TimeWindowNode）
      Then 結果的 code 應為 500
      And 結果的 message 應包含 "end_time"

  Rule: 後置（狀態）- 排程失敗時回傳 code 500

    Example: as_schedule_single_action 回傳 0
      Given as_schedule_single_action() 回傳 0（排程失敗）
      When 系統執行節點 "n1"（TimeWindowNode）
      Then 結果的 code 應為 500
      And 結果的 message 應包含 "排程失敗"

  Rule: 後置（狀態）- 邊界值：start_time 等於 end_time

    Example: start_time 等於 end_time 視為 24 小時窗口，立即排程
      Given 節點 "n1" 的 start_time 為 "09:00"，end_time 為 "09:00"
      When 系統執行節點 "n1"（TimeWindowNode）
      Then 應呼叫 as_schedule_single_action(time(), ...)
      And 結果的 code 應為 200
