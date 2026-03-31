@query
Feature: 查詢觸發條件列表

  Background:
    Given 系統已註冊以下預設觸發點（ETriggerPoint）：
      | hook                              | name     |
      | pf/trigger/registration_created   | 用戶報名後 |

  Rule: 前置（狀態）- 呼叫者必須具有 manage_options 權限

    Example: 未登入用戶查詢觸發條件列表時操作失敗
      Given 用戶未登入
      When 用戶查詢觸發條件列表
      Then 操作失敗，錯誤為「未授權的操作」

  Rule: 前置（參數）- 此端點無必要參數

    Example: 無需提供任何參數即可查詢
      Given 管理員 "Admin" 已登入
      When 管理員 "Admin" 查詢觸發條件列表
      Then 操作成功

  Rule: 後置（回應）- 回應應包含所有已註冊的觸發點

    Example: 僅有預設觸發點時回傳預設列表
      Given 管理員 "Admin" 已登入
      And 無第三方開發者透過 filter 擴充觸發點
      When 管理員 "Admin" 查詢觸發條件列表
      Then 操作成功
      And 查詢結果應包含：
        | hook                              | name     |
        | pf/trigger/registration_created   | 用戶報名後 |

    Example: 有第三方開發者擴充觸發點時回傳合併後的列表
      Given 管理員 "Admin" 已登入
      And 第三方開發者透過 filter 新增觸發點：
        | hook                           | name         |
        | pf/trigger/order_completed     | 訂單完成後     |
      When 管理員 "Admin" 查詢觸發條件列表
      Then 操作成功
      And 查詢結果應包含：
        | hook                              | name       |
        | pf/trigger/registration_created   | 用戶報名後   |
        | pf/trigger/order_completed        | 訂單完成後   |

  Rule: 後置（回應）- 每個觸發點應包含 hook 與 name 欄位

    Example: 回應格式正確
      Given 管理員 "Admin" 已登入
      When 管理員 "Admin" 查詢觸發條件列表
      Then 操作成功
      And 每個觸發點項目應包含：
        | 欄位 | 型別   |
        | hook | string |
        | name | string |
