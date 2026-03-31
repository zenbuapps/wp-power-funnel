@ignore @command
Feature: 定義 PROMO_LINK_CLICKED 觸發點（僅 enum case，暫不實作觸發邏輯）

  # 注意：此觸發點僅在 ETriggerPoint enum 中定義 case 與 label，
  # 讓前端觸發條件列表可見。暫不實作觸發邏輯，
  # 等推廣連結點擊追蹤機制建立後再補。

  Rule: 後置（回應）- 觸發點列表應包含 PROMO_LINK_CLICKED

    Example: 查詢觸發條件列表時應包含 PROMO_LINK_CLICKED
      Given 管理員 "Admin" 已登入
      When 管理員 "Admin" 查詢觸發條件列表
      Then 操作成功
      And 查詢結果應包含：
        | hook                              | name           |
        | pf/trigger/promo_link_clicked     | 推廣連結被點擊後 |

  Rule: 前置（參數）- ETriggerPoint enum 必須包含 PROMO_LINK_CLICKED case

    Example: ETriggerPoint 包含 PROMO_LINK_CLICKED case
      Given 系統已啟動
      Then ETriggerPoint enum 應包含 case PROMO_LINK_CLICKED
      And PROMO_LINK_CLICKED 的 hook 值為 "pf/trigger/promo_link_clicked"
      And PROMO_LINK_CLICKED 的 label 為 "推廣連結被點擊後"
