@ignore @query
Feature: 查詢擴展後的 LINE 觸發點列表

  Rule: 後置（回應）- 管理員應能看到所有新增的 LINE 觸發點

    Example: 查詢觸發點列表後應包含 LINE_POSTBACK_RECEIVED 和所有群組事件存根
      Given 系統已完成觸發點註冊
      When 管理員查詢可用的觸發點列表
      Then 查詢結果應包含以下 LINE 觸發點：
        | hook                                 | label                 |
        | pf/trigger/line_followed             | 用戶關注 LINE 官方帳號後 |
        | pf/trigger/line_unfollowed           | 用戶取消關注 LINE 官方帳號後 |
        | pf/trigger/line_message_received     | 收到 LINE 訊息後       |
        | pf/trigger/line_postback_received    | 收到 LINE Postback 後  |
        | pf/trigger/line_join                 | Bot 被加入群組後       |
        | pf/trigger/line_leave                | Bot 被移出群組後       |
        | pf/trigger/line_member_joined        | 新成員加入群組後       |
        | pf/trigger/line_member_left          | 成員離開群組後         |

  Rule: 後置（回應）- LINE_POSTBACK_RECEIVED 應回傳 context keys

    Example: 查詢 LINE_POSTBACK_RECEIVED 的 context keys 後回傳 4 個欄位
      Given 系統已完成觸發點註冊
      When 管理員查詢 "pf/trigger/line_postback_received" 的 context keys
      Then 查詢結果應包含：
        | key             | label              |
        | line_user_id    | LINE 用戶 ID       |
        | event_type      | 事件類型           |
        | postback_data   | Postback 原始資料  |
        | postback_action | Postback Action    |

  Rule: 後置（回應）- 群組事件存根觸發點應回傳空的 context keys

    Example: 查詢 LINE_JOIN 的 context keys 後回傳空陣列
      Given 系統已完成觸發點註冊
      When 管理員查詢 "pf/trigger/line_join" 的 context keys
      Then 查詢結果應為空陣列
