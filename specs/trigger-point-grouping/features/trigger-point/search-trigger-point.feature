@ignore @query
Feature: 搜尋觸發點

  Background:
    Given 系統中有以下分組觸發點清單：
      | group            | group_label | hook                              | name                    | disabled |
      | registration     | 報名狀態    | pf/trigger/registration_approved  | 用戶報名審核通過後       | false    |
      | registration     | 報名狀態    | pf/trigger/registration_rejected  | 用戶報名被拒絕後         | false    |
      | line_interaction | LINE 互動   | pf/trigger/line_followed          | 用戶關注 LINE 官方帳號後 | false    |
      | line_interaction | LINE 互動   | pf/trigger/line_unfollowed        | 用戶取消關注 LINE 官方帳號後 | false |
      | line_interaction | LINE 互動   | pf/trigger/line_message_received  | 收到 LINE 訊息後         | false    |
      | line_interaction | LINE 互動   | pf/trigger/line_postback_received | 收到 LINE Postback 後    | false    |
      | line_group       | LINE 群組   | pf/trigger/line_join              | Bot 被加入群組後（即將推出） | true |
      | workflow         | 工作流引擎  | pf/trigger/workflow_completed     | 工作流完成後              | false    |
      | woocommerce      | WooCommerce | pf/trigger/order_completed        | 訂單完成後                | false    |

  Rule: 後置（回應）- 搜尋應同時比對選項名稱和分組名稱

    Example: 輸入選項名稱關鍵字後僅顯示匹配的選項
      When 管理員 "Alice" 在觸發條件 Select 中輸入搜尋關鍵字 "報名"
      Then 搜尋結果應包含：
        | group        | hook                              | name               |
        | registration | pf/trigger/registration_approved  | 用戶報名審核通過後  |
        | registration | pf/trigger/registration_rejected  | 用戶報名被拒絕後    |

    Example: 輸入分組名稱關鍵字後顯示該分組所有選項
      When 管理員 "Alice" 在觸發條件 Select 中輸入搜尋關鍵字 "LINE"
      Then 搜尋結果應包含：
        | group            | hook                              | name                           |
        | line_interaction | pf/trigger/line_followed          | 用戶關注 LINE 官方帳號後        |
        | line_interaction | pf/trigger/line_unfollowed        | 用戶取消關注 LINE 官方帳號後    |
        | line_interaction | pf/trigger/line_message_received  | 收到 LINE 訊息後                |
        | line_interaction | pf/trigger/line_postback_received | 收到 LINE Postback 後           |
        | line_group       | pf/trigger/line_join              | Bot 被加入群組後（即將推出）     |

  Rule: 後置（回應）- 搜尋應支援模糊匹配

    Example: 輸入部分關鍵字仍能匹配
      When 管理員 "Alice" 在觸發條件 Select 中輸入搜尋關鍵字 "完成"
      Then 搜尋結果應包含：
        | group       | hook                           | name        |
        | workflow    | pf/trigger/workflow_completed  | 工作流完成後 |
        | woocommerce | pf/trigger/order_completed     | 訂單完成後   |

  Rule: 後置（回應）- 搜尋無結果時應顯示空狀態

    Example: 輸入不存在的關鍵字時顯示空狀態
      When 管理員 "Alice" 在觸發條件 Select 中輸入搜尋關鍵字 "email"
      Then 搜尋結果為空

  Rule: 後置（回應）- 搜尋不區分大小寫

    Example: 輸入小寫 line 仍能匹配 LINE 相關選項
      When 管理員 "Alice" 在觸發條件 Select 中輸入搜尋關鍵字 "line"
      Then 搜尋結果應包含：
        | group            | hook                              | name                           |
        | line_interaction | pf/trigger/line_followed          | 用戶關注 LINE 官方帳號後        |
        | line_interaction | pf/trigger/line_unfollowed        | 用戶取消關注 LINE 官方帳號後    |
        | line_interaction | pf/trigger/line_message_received  | 收到 LINE 訊息後                |
        | line_interaction | pf/trigger/line_postback_received | 收到 LINE Postback 後           |
        | line_group       | pf/trigger/line_join              | Bot 被加入群組後（即將推出）     |

  Rule: 前置（參數）- 必要參數必須提供

    Example: 搜尋關鍵字為空時顯示所有觸發點
      When 管理員 "Alice" 在觸發條件 Select 中輸入搜尋關鍵字 ""
      Then 搜尋結果應包含所有觸發點
