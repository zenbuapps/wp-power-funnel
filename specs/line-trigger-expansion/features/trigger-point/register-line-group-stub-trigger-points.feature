@ignore @command
Feature: 註冊 LINE 群組事件枚舉存根觸發點

  # 這些觸發點目前只新增 ETriggerPoint enum cases + labels，
  # 不在 TriggerPointService 實作任何 add_action 監聽或 context resolve 方法。
  # 前端觸發點列表中可見，等有實際業務場景後再補實作。

  Rule: 後置（狀態）- ETriggerPoint enum 應包含 LINE_JOIN case

    Example: ETriggerPoint enum 新增 LINE_JOIN 後可正確取得 hook 值和標籤
      When 系統讀取 ETriggerPoint::LINE_JOIN
      Then 該 enum case 的值應為 "pf/trigger/line_join"
      And 該 enum case 的 label 應為 "Bot 被加入群組後"

  Rule: 後置（狀態）- ETriggerPoint enum 應包含 LINE_LEAVE case

    Example: ETriggerPoint enum 新增 LINE_LEAVE 後可正確取得 hook 值和標籤
      When 系統讀取 ETriggerPoint::LINE_LEAVE
      Then 該 enum case 的值應為 "pf/trigger/line_leave"
      And 該 enum case 的 label 應為 "Bot 被移出群組後"

  Rule: 後置（狀態）- ETriggerPoint enum 應包含 LINE_MEMBER_JOINED case

    Example: ETriggerPoint enum 新增 LINE_MEMBER_JOINED 後可正確取得 hook 值和標籤
      When 系統讀取 ETriggerPoint::LINE_MEMBER_JOINED
      Then 該 enum case 的值應為 "pf/trigger/line_member_joined"
      And 該 enum case 的 label 應為 "新成員加入群組後"

  Rule: 後置（狀態）- ETriggerPoint enum 應包含 LINE_MEMBER_LEFT case

    Example: ETriggerPoint enum 新增 LINE_MEMBER_LEFT 後可正確取得 hook 值和標籤
      When 系統讀取 ETriggerPoint::LINE_MEMBER_LEFT
      Then 該 enum case 的值應為 "pf/trigger/line_member_left"
      And 該 enum case 的 label 應為 "成員離開群組後"

  Rule: 前置（狀態）- 群組事件存根不應在 TriggerPointService 註冊監聽

    Example: TriggerPointService 不監聽 join/leave/memberJoined/memberLeft webhook
      Given TriggerPointService 已呼叫 register_hooks
      When WebhookService dispatch "power_funnel/line/webhook/join" 事件
      Then TriggerPointService 不應觸發任何 pf/trigger/ hook

  Rule: 前置（參數）- 群組事件存根必須提供 enum cases

    Example: 所有 4 個群組事件存根的 enum values 格式正確
      When 系統列舉所有 ETriggerPoint cases
      Then 結果應包含以下觸發點：
        | case                | value                              |
        | LINE_JOIN           | pf/trigger/line_join               |
        | LINE_LEAVE          | pf/trigger/line_leave              |
        | LINE_MEMBER_JOINED  | pf/trigger/line_member_joined      |
        | LINE_MEMBER_LEFT    | pf/trigger/line_member_left        |
