@ignore @query
Feature: 查詢分組觸發點清單

  Background:
    Given 系統中有以下觸發點：
      | hook                              | name                    | group              | group_label | disabled |
      | pf/trigger/registration_approved   | 用戶報名審核通過後       | registration       | 報名狀態    | false    |
      | pf/trigger/registration_rejected   | 用戶報名被拒絕後         | registration       | 報名狀態    | false    |
      | pf/trigger/registration_cancelled  | 用戶取消報名後           | registration       | 報名狀態    | false    |
      | pf/trigger/registration_failed     | 用戶報名失敗後           | registration       | 報名狀態    | false    |
      | pf/trigger/line_followed           | 用戶關注 LINE 官方帳號後 | line_interaction   | LINE 互動   | false    |
      | pf/trigger/line_unfollowed         | 用戶取消關注 LINE 官方帳號後 | line_interaction | LINE 互動 | false    |
      | pf/trigger/line_message_received   | 收到 LINE 訊息後        | line_interaction   | LINE 互動   | false    |
      | pf/trigger/line_postback_received  | 收到 LINE Postback 後   | line_interaction   | LINE 互動   | false    |
      | pf/trigger/line_join               | Bot 被加入群組後（即將推出） | line_group     | LINE 群組   | true     |
      | pf/trigger/line_leave              | Bot 被移出群組後（即將推出） | line_group     | LINE 群組   | true     |
      | pf/trigger/line_member_joined      | 新成員加入群組後（即將推出） | line_group     | LINE 群組   | true     |
      | pf/trigger/line_member_left        | 成員離開群組後（即將推出）   | line_group     | LINE 群組   | true     |
      | pf/trigger/workflow_completed      | 工作流完成後             | workflow           | 工作流引擎  | false    |
      | pf/trigger/workflow_failed         | 工作流失敗後             | workflow           | 工作流引擎  | false    |
      | pf/trigger/activity_started        | 活動開始時               | activity           | 活動時間    | false    |
      | pf/trigger/activity_before_start   | 活動開始前               | activity           | 活動時間    | false    |
      | pf/trigger/activity_ended          | 活動結束後（即將推出）   | activity           | 活動時間    | true     |
      | pf/trigger/user_tagged             | 用戶被貼標籤後           | user_behavior      | 用戶行為    | false    |
      | pf/trigger/promo_link_clicked      | 推廣連結被點擊後（即將推出） | user_behavior  | 用戶行為    | true     |
      | pf/trigger/order_completed         | 訂單完成後               | woocommerce        | WooCommerce | false    |

  Rule: 前置（狀態）- 已棄用的 REGISTRATION_CREATED 必須被排除

    Example: 查詢結果不包含已棄用的觸發點
      Given 系統中另有已棄用的觸發點：
        | hook                             | name             |
        | pf/trigger/registration_created  | 用戶報名後（舊） |
      When 管理員 "Alice" 查詢觸發點清單
      Then 操作成功
      And 查詢結果不包含 hook 為 "pf/trigger/registration_created" 的項目

  Rule: 後置（回應）- 回應應以分組結構組織

    Example: 查詢觸發點清單後回傳分組結構
      When 管理員 "Alice" 查詢觸發點清單
      Then 操作成功
      And 查詢結果應包含以下分組：
        | group            | group_label | items_count |
        | registration     | 報名狀態    | 4           |
        | line_interaction | LINE 互動   | 4           |
        | line_group       | LINE 群組   | 4           |
        | workflow         | 工作流引擎  | 2           |
        | activity         | 活動時間    | 3           |
        | user_behavior    | 用戶行為    | 2           |
        | woocommerce      | WooCommerce | 1           |

  Rule: 後置（回應）- 分組順序應固定

    Example: 分組順序為報名狀態、LINE 互動、LINE 群組、工作流引擎、活動時間、用戶行為、WooCommerce
      When 管理員 "Alice" 查詢觸發點清單
      Then 操作成功
      And 分組順序應為：
        | position | group            |
        | 1        | registration     |
        | 2        | line_interaction |
        | 3        | line_group       |
        | 4        | workflow         |
        | 5        | activity         |
        | 6        | user_behavior    |
        | 7        | woocommerce      |

  Rule: 後置（回應）- 枚舉存根應標記為 disabled

    Example: 枚舉存根項目的 disabled 為 true 且名稱帶有即將推出後綴
      When 管理員 "Alice" 查詢觸發點清單
      Then 操作成功
      And 分組 "line_group" 中的項目應為：
        | hook                            | name                           | disabled |
        | pf/trigger/line_join            | Bot 被加入群組後（即將推出）    | true     |
        | pf/trigger/line_leave           | Bot 被移出群組後（即將推出）    | true     |
        | pf/trigger/line_member_joined   | 新成員加入群組後（即將推出）    | true     |
        | pf/trigger/line_member_left     | 成員離開群組後（即將推出）      | true     |

  Rule: 後置（回應）- 每個 item 應包含 hook、name、disabled 欄位

    Example: 分組 registration 的項目結構正確
      When 管理員 "Alice" 查詢觸發點清單
      Then 操作成功
      And 分組 "registration" 中的項目應為：
        | hook                              | name               | disabled |
        | pf/trigger/registration_approved  | 用戶報名審核通過後  | false    |
        | pf/trigger/registration_rejected  | 用戶報名被拒絕後    | false    |
        | pf/trigger/registration_cancelled | 用戶取消報名後      | false    |
        | pf/trigger/registration_failed    | 用戶報名失敗後      | false    |

  Rule: 前置（參數）- 必要參數必須提供

    Example: 此 API 為無參數 GET 請求，無需參數驗證
      When 管理員 "Alice" 查詢觸發點清單
      Then 操作成功
