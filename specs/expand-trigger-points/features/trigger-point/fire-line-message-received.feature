@ignore @command
Feature: 觸發 LINE_MESSAGE_RECEIVED 觸發點

  # 注意：目前每條 LINE 訊息都會觸發，不做 rate limiting。
  # 未來若有高流量需求，可擴展 throttle 機制（同一 line_user_id 在 N 秒內只觸發一次）。

  Background:
    Given LINE webhook 設定已完成（channel_secret 有效）

  Rule: 前置（狀態）- 必須收到 LINE message 類型的 webhook 事件

    Example: 收到 LINE text message 事件時觸發
      Given LINE webhook 設定已完成
      When 系統收到 LINE webhook 事件，類型為 "message"，來源用戶為 "U9876543210"，訊息內容為 "你好"
      Then 系統應觸發 "pf/trigger/line_message_received"
      And context_callable_set 執行後應產生以下 context：
        | key          | value         |
        | line_user_id | U9876543210   |
        | event_type   | message       |
        | message_text | 你好          |

  Rule: 前置（狀態）- 非 message 類型的事件不應觸發

    Example: 收到 LINE follow 事件時不觸發 LINE_MESSAGE_RECEIVED
      Given LINE webhook 設定已完成
      When 系統收到 LINE webhook 事件，類型為 "follow"，來源用戶為 "U9876543210"
      Then 系統不應觸發 "pf/trigger/line_message_received"

  Rule: 前置（參數）- LINE webhook 事件必須包含來源用戶 ID

    Example: LINE webhook 事件缺少來源用戶時不觸發
      Given LINE webhook 設定已完成
      When 系統收到 LINE webhook 事件，類型為 "message"，但來源用戶為空
      Then 系統不應觸發 "pf/trigger/line_message_received"
