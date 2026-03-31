@ignore @query
Feature: 註冊擴展觸發點

  Background:
    Given 系統已註冊以下預設觸發點（ETriggerPoint）：
      | hook                              | name           | priority |
      | pf/trigger/registration_created   | 用戶報名後       | P0       |
      | pf/trigger/registration_approved  | 報名審核通過後   | P0       |
      | pf/trigger/registration_rejected  | 報名被拒絕後     | P0       |
      | pf/trigger/registration_cancelled | 報名取消後       | P0       |
      | pf/trigger/registration_failed    | 報名失敗後       | P0       |
      | pf/trigger/line_followed          | LINE 加入好友後  | P1       |
      | pf/trigger/line_unfollowed        | LINE 封鎖後      | P1       |
      | pf/trigger/line_message_received  | LINE 收到訊息後  | P1       |
      | pf/trigger/workflow_completed     | 工作流完成後     | P2       |
      | pf/trigger/workflow_failed        | 工作流失敗後     | P2       |
      | pf/trigger/activity_started       | 活動開始時       | P3       |
      | pf/trigger/activity_ended         | 活動結束時       | P3       |
      | pf/trigger/activity_before_start  | 活動開始前提醒   | P3       |
      | pf/trigger/user_tagged            | 用戶被加標籤後   | P3       |
      | pf/trigger/promo_link_clicked     | 推廣連結被點擊後 | P3       |

  Rule: 後置（回應）- 觸發點列表應包含所有已註冊的 ETriggerPoint enum cases

    Example: 查詢觸發條件列表時應包含所有擴展觸發點
      Given 管理員 "Admin" 已登入
      When 管理員 "Admin" 查詢觸發條件列表
      Then 操作成功
      And 查詢結果應包含 16 個觸發點（含原有的 REGISTRATION_CREATED）

  Rule: 後置（回應）- 每個觸發點應包含 hook 與 name 欄位

    Example: 新增的觸發點回應格式正確
      Given 管理員 "Admin" 已登入
      When 管理員 "Admin" 查詢觸發條件列表
      Then 操作成功
      And 每個觸發點項目應包含：
        | 欄位 | 型別   |
        | hook | string |
        | name | string |
