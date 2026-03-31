@ignore @command
Feature: 觸發 LINE_UNFOLLOWED 觸發點

  Background:
    Given LINE webhook 設定已完成（channel_secret 有效）

  Rule: 前置（狀態）- 必須收到 LINE unfollow 類型的 webhook 事件

    Example: 收到 LINE unfollow 事件時觸發
      Given LINE webhook 設定已完成
      When 系統收到 LINE webhook 事件，類型為 "unfollow"，來源用戶為 "U9876543210"
      Then 系統應觸發 "pf/trigger/line_unfollowed"
      And context_callable_set 執行後應產生以下 context：
        | key          | value         |
        | line_user_id | U9876543210   |
        | event_type   | unfollow      |

  Rule: 前置（狀態）- 非 unfollow 類型的事件不應觸發

    Example: 收到 LINE follow 事件時不觸發 LINE_UNFOLLOWED
      Given LINE webhook 設定已完成
      When 系統收到 LINE webhook 事件，類型為 "follow"，來源用戶為 "U9876543210"
      Then 系統不應觸發 "pf/trigger/line_unfollowed"
