@ignore @command
Feature: 定義 ACTIVITY_ENDED 觸發點（僅 enum case，暫不實作觸發邏輯）

  # 注意：此觸發點僅在 ETriggerPoint enum 中定義 case 與 label，
  # 讓前端觸發條件列表可見。暫不實作排程觸發邏輯，
  # 等 ActivityDTO 有結束時間資料來源後再補。

  Rule: 後置（回應）- 觸發點列表應包含 ACTIVITY_ENDED

    Example: 查詢觸發條件列表時應包含 ACTIVITY_ENDED
      Given 管理員 "Admin" 已登入
      When 管理員 "Admin" 查詢觸發條件列表
      Then 操作成功
      And 查詢結果應包含：
        | hook                           | name       |
        | pf/trigger/activity_ended      | 活動結束時   |

  Rule: 前置（參數）- ETriggerPoint enum 必須包含 ACTIVITY_ENDED case

    Example: ETriggerPoint 包含 ACTIVITY_ENDED case
      Given 系統已啟動
      Then ETriggerPoint enum 應包含 case ACTIVITY_ENDED
      And ACTIVITY_ENDED 的 hook 值為 "pf/trigger/activity_ended"
      And ACTIVITY_ENDED 的 label 為 "活動結束時"
