@ignore @command
Feature: 觸發 USER_TAGGED 觸發點

  # 觸發來源：tag_user 節點（ENode::TAG_USER）的 NodeDefinition.execute() 完成後觸發。
  # 目前只捕捉工作流節點加的標籤，未來若有獨立標籤管理介面可在該處也加觸發。

  Rule: 前置（狀態）- tag_user 節點必須執行成功

    Example: tag_user 節點執行成功後觸發
      Given 工作流 301 正在執行 tag_user 節點
      And 節點參數為：為用戶 "U1234567890" 加上標籤 "VIP"
      When tag_user 節點執行成功
      Then 系統應觸發 "pf/trigger/user_tagged"
      And context_callable_set 執行後應產生以下 context：
        | key          | value         |
        | user_id      | U1234567890   |
        | tag_name     | VIP           |

  Rule: 前置（狀態）- tag_user 節點執行失敗時不應觸發

    Example: tag_user 節點執行失敗時不觸發 USER_TAGGED
      Given 工作流 301 正在執行 tag_user 節點
      And 節點參數為：為用戶 "U1234567890" 加上標籤 "VIP"
      When tag_user 節點執行失敗
      Then 系統不應觸發 "pf/trigger/user_tagged"

  Rule: 前置（參數）- 必要的 context 欄位必須包含 user_id 和 tag_name

    Example: 觸發時 context 包含所有必要欄位
      Given 工作流 301 正在執行 tag_user 節點
      And 節點參數為：為用戶 "U1234567890" 加上標籤 "VIP"
      When tag_user 節點執行成功
      Then context_callable_set 的 callable 應為可呼叫函式
      And context 應包含 user_id 和 tag_name 欄位
