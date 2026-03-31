@ignore @query
Feature: 查詢擴展後的觸發條件列表

  Background:
    Given 系統已註冊以下觸發點（ETriggerPoint）：
      | hook                              | name           |
      | pf/trigger/registration_created   | 用戶報名後       |
      | pf/trigger/registration_approved  | 報名審核通過後   |
      | pf/trigger/registration_rejected  | 報名被拒絕後     |
      | pf/trigger/registration_cancelled | 報名取消後       |
      | pf/trigger/registration_failed    | 報名失敗後       |
      | pf/trigger/line_followed          | LINE 加入好友後  |
      | pf/trigger/line_unfollowed        | LINE 封鎖後      |
      | pf/trigger/line_message_received  | LINE 收到訊息後  |
      | pf/trigger/workflow_completed     | 工作流完成後     |
      | pf/trigger/workflow_failed        | 工作流失敗後     |
      | pf/trigger/activity_started       | 活動開始時       |
      | pf/trigger/activity_ended         | 活動結束時       |
      | pf/trigger/activity_before_start  | 活動開始前提醒   |
      | pf/trigger/user_tagged            | 用戶被加標籤後   |
      | pf/trigger/promo_link_clicked     | 推廣連結被點擊後 |

  Rule: 前置（狀態）- 呼叫者必須具有 manage_options 權限

    Example: 未登入用戶查詢時操作失敗
      Given 用戶未登入
      When 用戶查詢觸發條件列表
      Then 操作失敗，錯誤為「未授權的操作」

  Rule: 後置（回應）- 回應應包含所有 16 個已註冊的觸發點

    Example: 查詢結果包含所有擴展後的觸發點
      Given 管理員 "Admin" 已登入
      When 管理員 "Admin" 查詢觸發條件列表
      Then 操作成功
      And 查詢結果應包含 16 個觸發點
      And 查詢結果應包含：
        | hook                              | name           |
        | pf/trigger/registration_created   | 用戶報名後       |
        | pf/trigger/registration_approved  | 報名審核通過後   |
        | pf/trigger/registration_rejected  | 報名被拒絕後     |
        | pf/trigger/registration_cancelled | 報名取消後       |
        | pf/trigger/registration_failed    | 報名失敗後       |
        | pf/trigger/line_followed          | LINE 加入好友後  |
        | pf/trigger/line_unfollowed        | LINE 封鎖後      |
        | pf/trigger/line_message_received  | LINE 收到訊息後  |
        | pf/trigger/workflow_completed     | 工作流完成後     |
        | pf/trigger/workflow_failed        | 工作流失敗後     |
        | pf/trigger/activity_started       | 活動開始時       |
        | pf/trigger/activity_ended         | 活動結束時       |
        | pf/trigger/activity_before_start  | 活動開始前提醒   |
        | pf/trigger/user_tagged            | 用戶被加標籤後   |
        | pf/trigger/promo_link_clicked     | 推廣連結被點擊後 |
