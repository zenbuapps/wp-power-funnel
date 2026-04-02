@ignore @query
Feature: 解析顧客 Context（延遲求值）

  Background:
    Given WordPress 系統運作中
    And 系統中有以下 WordPress 用戶：
      | userId | billingEmail       | billingFirstName | billingLastName | billingPhone |
      | 100    | alice@example.com  | Alice            | Wang            | 0912345678   |

  Rule: 後置（回應）- resolve_customer_context 應回傳 5 個顧客關鍵欄位

    Example: 用戶存在時回傳完整 context（5 個 keys）
      When 系統呼叫 resolve_customer_context(100)
      Then 回傳結果應包含：
        | key                  | value              |
        | customer_id          | 100                |
        | billing_email        | alice@example.com  |
        | billing_first_name   | Alice              |
        | billing_last_name    | Wang               |
        | billing_phone        | 0912345678         |

  Rule: 後置（回應）- 用戶不存在時應回傳空陣列

    Example: 用戶不存在時回傳空陣列
      Given user_id 為 9999 的用戶不存在
      When 系統呼叫 resolve_customer_context(9999)
      Then 回傳結果應為空陣列

  Rule: 後置（回應）- WaitNode 延遲後應取得最新用戶資料

    Example: 用戶 email 在 WaitNode 等待期間被修改後取得最新值
      Given 用戶 100 的 billing_email 為 "alice@example.com"
      And WaitNode 等待 1 小時後，用戶 100 的 billing_email 被修改為 "newalice@example.com"
      When 系統呼叫 resolve_customer_context(100)
      Then 回傳結果的 billing_email 應為 "newalice@example.com"

  Rule: 後置（回應）- 使用 WordPress get_user_meta 取得帳單資訊

    Example: billing 欄位從 user_meta 讀取
      When 系統呼叫 resolve_customer_context(100)
      Then 系統內部應呼叫 get_user_meta(100, "billing_email", true)
      And 系統內部應呼叫 get_user_meta(100, "billing_first_name", true)
      And 系統內部應呼叫 get_user_meta(100, "billing_last_name", true)
      And 系統內部應呼叫 get_user_meta(100, "billing_phone", true)

  Rule: 前置（參數）- 必要參數必須提供

    Scenario Outline: 缺少必要參數時操作失敗
      When 系統呼叫 resolve_customer_context(<userId>)
      Then 回傳結果應為空陣列

      Examples:
        | userId |
        | 0      |
