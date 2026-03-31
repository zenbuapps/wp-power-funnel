@ignore @command
Feature: 觸發 ACTIVITY_STARTED 觸發點

  Background:
    Given 系統中有以下活動：
      | activityId | title            | scheduledStartTime    |
      | 501        | React 直播教學    | 2026-04-01T20:00:00Z  |
    And 系統使用 Action Scheduler 排程活動時間事件

  Rule: 前置（狀態）- 活動的排程開始時間必須到達

    Example: 活動開始時間到達時觸發
      Given 活動 501 的排程開始時間為 "2026-04-01T20:00:00Z"
      And 系統已為活動 501 建立 Action Scheduler 排程
      When Action Scheduler 在 "2026-04-01T20:00:00Z" 觸發排程
      Then 系統應觸發 "pf/trigger/activity_started"
      And context_callable_set 執行後應產生以下 context：
        | key              | value                  |
        | activity_id      | 501                    |
        | activity_title   | React 直播教學          |
        | scheduled_time   | 2026-04-01T20:00:00Z   |

  Rule: 前置（狀態）- 活動開始時間未到達時不應觸發

    Example: 活動開始時間尚未到達時不觸發
      Given 活動 501 的排程開始時間為 "2026-04-01T20:00:00Z"
      And 系統時間為 "2026-04-01T19:00:00Z"
      Then 系統不應觸發 "pf/trigger/activity_started"

  Rule: 後置（狀態）- 活動同步時應建立排程

    Example: 活動從外部 provider 同步後應建立 Action Scheduler 排程
      Given 活動 501 的排程開始時間為 "2026-04-01T20:00:00Z"
      When 系統同步活動 501 的資料
      Then 系統應建立 Action Scheduler 排程，觸發時間為 "2026-04-01T20:00:00Z"

    Example: 活動時間更新後應取消舊排程並建立新排程
      Given 活動 501 已有排程，觸發時間為 "2026-04-01T20:00:00Z"
      When 活動 501 的排程開始時間更新為 "2026-04-02T20:00:00Z"
      Then 系統應取消舊的 Action Scheduler 排程
      And 系統應建立新的 Action Scheduler 排程，觸發時間為 "2026-04-02T20:00:00Z"

  Rule: 前置（參數）- 活動必須有有效的 scheduledStartTime

    Example: 活動沒有排程開始時間時不建立排程
      Given 活動 502 沒有 scheduledStartTime
      When 系統同步活動 502 的資料
      Then 系統不應為活動 502 建立 Action Scheduler 排程
