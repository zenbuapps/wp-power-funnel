@ignore @command
Feature: 觸發 ACTIVITY_BEFORE_START 觸發點

  Background:
    Given 系統中有以下活動：
      | activityId | title            | scheduledStartTime    |
      | 501        | React 直播教學    | 2026-04-01T20:00:00Z  |
    And 系統使用 Action Scheduler 排程活動時間事件

  Rule: 前置（狀態）- WorkflowRule 的 trigger_point meta 必須包含 before_minutes 參數

    Example: WorkflowRule 設定 before_minutes=30 時，活動開始前 30 分鐘觸發
      Given WorkflowRule 401 的 trigger_point meta 為：
        | hook                                | before_minutes |
        | pf/trigger/activity_before_start    | 30             |
      And 活動 501 的排程開始時間為 "2026-04-01T20:00:00Z"
      And 系統已為 WorkflowRule 401 建立 Action Scheduler 排程，觸發時間為 "2026-04-01T19:30:00Z"
      When Action Scheduler 在 "2026-04-01T19:30:00Z" 觸發排程
      Then 系統應觸發 "pf/trigger/activity_before_start"
      And context_callable_set 執行後應產生以下 context：
        | key              | value                  |
        | activity_id      | 501                    |
        | activity_title   | React 直播教學          |
        | scheduled_time   | 2026-04-01T20:00:00Z   |
        | before_minutes   | 30                     |

    Example: WorkflowRule 設定 before_minutes=60 時，活動開始前 60 分鐘觸發
      Given WorkflowRule 402 的 trigger_point meta 為：
        | hook                                | before_minutes |
        | pf/trigger/activity_before_start    | 60             |
      And 活動 501 的排程開始時間為 "2026-04-01T20:00:00Z"
      And 系統已為 WorkflowRule 402 建立 Action Scheduler 排程，觸發時間為 "2026-04-01T19:00:00Z"
      When Action Scheduler 在 "2026-04-01T19:00:00Z" 觸發排程
      Then 系統應觸發 "pf/trigger/activity_before_start"
      And context_callable_set 執行後應產生以下 context：
        | key              | value                  |
        | activity_id      | 501                    |
        | activity_title   | React 直播教學          |
        | scheduled_time   | 2026-04-01T20:00:00Z   |
        | before_minutes   | 60                     |

  Rule: 前置（參數）- before_minutes 必須為正整數

    Example: before_minutes 未設定時使用預設值 30
      Given WorkflowRule 403 的 trigger_point meta 為：
        | hook                                |
        | pf/trigger/activity_before_start    |
      Then 系統應使用預設 before_minutes = 30

    Example: before_minutes 為 0 或負數時操作失敗
      Given WorkflowRule 404 的 trigger_point meta 為：
        | hook                                | before_minutes |
        | pf/trigger/activity_before_start    | 0              |
      Then 系統不應為 WorkflowRule 404 建立 Action Scheduler 排程

  Rule: 後置（狀態）- 活動同步時應根據 before_minutes 建立排程

    Example: 活動同步後根據 before_minutes 計算排程時間
      Given WorkflowRule 401 的 trigger_point meta 為：
        | hook                                | before_minutes |
        | pf/trigger/activity_before_start    | 30             |
      And 活動 501 的排程開始時間為 "2026-04-01T20:00:00Z"
      When 系統同步活動 501 的資料
      Then 系統應建立 Action Scheduler 排程，觸發時間為 "2026-04-01T19:30:00Z"
