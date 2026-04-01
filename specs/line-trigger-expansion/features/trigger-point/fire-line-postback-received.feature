@ignore @command
Feature: 觸發 LINE_POSTBACK_RECEIVED 觸發點

  Background:
    Given LINE webhook 設定已完成（channel_secret 有效）

  Rule: 前置（狀態）- 必須收到 LINE postback 類型的 webhook 事件

    Example: 收到 LINE postback 事件時觸發
      Given LINE webhook 設定已完成
      When 系統收到 LINE webhook 事件，類型為 "postback"，來源用戶為 "U1234567890"，postback data 為 '{"action":"register","activity_id":"99"}'
      Then 系統應觸發 "pf/trigger/line_postback_received"
      And context_callable_set 執行後應產生以下 context：
        | key              | value                                          |
        | line_user_id     | U1234567890                                    |
        | event_type       | postback                                       |
        | postback_data    | {"action":"register","activity_id":"99"}        |
        | postback_action  | register                                       |

  Rule: 前置（狀態）- 非 postback 類型的事件不應觸發

    Example: 收到 LINE message 事件時不觸發 LINE_POSTBACK_RECEIVED
      Given LINE webhook 設定已完成
      When 系統收到 LINE webhook 事件，類型為 "message"，來源用戶為 "U1234567890"
      Then 系統不應觸發 "pf/trigger/line_postback_received"

  Rule: 前置（參數）- LINE webhook 事件必須包含來源用戶 ID

    Example: LINE postback 事件缺少來源用戶時不觸發
      Given LINE webhook 設定已完成
      When 系統收到 LINE webhook 事件，類型為 "postback"，但來源用戶為空，postback data 為 '{"action":"register"}'
      Then 系統不應觸發 "pf/trigger/line_postback_received"

  Rule: 後置（狀態）- context 應包含 postback_data 原始字串

    Example: postback data 為非 JSON 格式時 postback_data 仍保留原始字串
      Given LINE webhook 設定已完成
      When 系統收到 LINE webhook 事件，類型為 "postback"，來源用戶為 "U1234567890"，postback data 為 "plain_text_data"
      Then 系統應觸發 "pf/trigger/line_postback_received"
      And context_callable_set 執行後應產生以下 context：
        | key              | value           |
        | line_user_id     | U1234567890     |
        | event_type       | postback        |
        | postback_data    | plain_text_data |
        | postback_action  |                 |

  Rule: 後置（狀態）- 與既有報名流程雙重觸發為正確行為

    Example: 收到 action 為 register 的 postback 時同時觸發報名流程和 LINE_POSTBACK_RECEIVED
      Given LINE webhook 設定已完成
      When 系統收到 LINE webhook 事件，類型為 "postback"，來源用戶為 "U1234567890"，postback data 為 '{"action":"register","activity_id":"99","promo_link_id":"55"}'
      Then 系統應觸發 "pf/trigger/line_postback_received"
      And 既有的 PostbackService 報名流程也應被觸發

  Rule: 後置（狀態）- postback_action 過濾匹配時應觸發對應 WorkflowRule

    Example: WorkflowRule 設定 postback_action 過濾為 register 時僅匹配 register 的 Postback 觸發
      Given LINE webhook 設定已完成
      And 系統中有以下工作流規則：
        | ruleId | trigger_point                       | trigger_point_params              |
        | 1      | pf/trigger/line_postback_received   | {"postback_action": "register"}   |
      When 系統收到 LINE webhook 事件，類型為 "postback"，來源用戶為 "U1234567890"，postback data 為 '{"action":"register","activity_id":"99"}'
      Then 工作流規則 1 應被匹配並建立 Workflow 實例

    Example: WorkflowRule 設定 postback_action 過濾為 confirm 時不匹配 register 的 Postback
      Given LINE webhook 設定已完成
      And 系統中有以下工作流規則：
        | ruleId | trigger_point                       | trigger_point_params              |
        | 2      | pf/trigger/line_postback_received   | {"postback_action": "confirm"}    |
      When 系統收到 LINE webhook 事件，類型為 "postback"，來源用戶為 "U1234567890"，postback data 為 '{"action":"register","activity_id":"99"}'
      Then 工作流規則 2 不應被匹配

  Rule: 後置（狀態）- postback_action 過濾為空時應觸發所有 Postback 事件

    Example: WorkflowRule 未設定 postback_action 過濾時所有 Postback 都觸發
      Given LINE webhook 設定已完成
      And 系統中有以下工作流規則：
        | ruleId | trigger_point                       | trigger_point_params |
        | 3      | pf/trigger/line_postback_received   | {}                   |
      When 系統收到 LINE webhook 事件，類型為 "postback"，來源用戶為 "U1234567890"，postback data 為 '{"action":"any_action"}'
      Then 工作流規則 3 應被匹配並建立 Workflow 實例
