@ignore @query
Feature: 解析訂閱 Context（延遲求值）

  Background:
    Given WooCommerce Subscriptions 外掛已啟用
    And 系統中有以下 WC_Subscription：
      | subscriptionId | status | customerId | billingEmail       | billingFirstName | billingLastName | total | paymentMethod |
      | 5001           | active | 42         | alice@example.com  | Alice            | Wang            | 499   | credit_card   |

  Rule: 後置（回應）- resolve_subscription_context 應回傳 8 個訂閱關鍵欄位

    Example: 訂閱存在時回傳完整 context（8 個 keys）
      When 系統呼叫 resolve_subscription_context(5001)
      Then 回傳結果應包含：
        | key                  | value              |
        | subscription_id      | 5001               |
        | subscription_status  | active             |
        | customer_id          | 42                 |
        | billing_email        | alice@example.com  |
        | billing_first_name   | Alice              |
        | billing_last_name    | Wang               |
        | order_total          | 499                |
        | payment_method       | credit_card        |

  Rule: 後置（回應）- 使用 wcs_get_subscription() 取得 WC_Subscription 物件

    Example: 以 subscription_id 為參數呼叫 wcs_get_subscription
      When 系統呼叫 resolve_subscription_context(5001)
      Then 系統內部應呼叫 wcs_get_subscription(5001)

  Rule: 後置（回應）- subscription_status 為 WC_Subscription::get_status() 原始值

    Example: subscription_status 不含 wc- 前綴
      Given 訂閱 5001 的 WooCommerce 內部狀態為 "wc-active"
      When 系統呼叫 resolve_subscription_context(5001)
      Then 回傳結果的 subscription_status 應為 "active"

  Rule: 後置（回應）- 訂閱不存在時應回傳空陣列

    Example: 訂閱不存在時回傳空陣列
      Given subscription_id 為 9999 的訂閱不存在
      When 系統呼叫 resolve_subscription_context(9999)
      Then 回傳結果應為空陣列

  Rule: 後置（回應）- WaitNode 延遲後應取得最新訂閱資料

    Example: 訂閱狀態在 WaitNode 等待期間變更後取得最新值
      Given 訂閱 5001 的 status 為 "active"
      And WaitNode 等待 1 小時後，訂閱 5001 的 status 被修改為 "on-hold"
      When 系統呼叫 resolve_subscription_context(5001)
      Then 回傳結果的 subscription_status 應為 "on-hold"

  Rule: 前置（狀態）- WooCommerce Subscriptions 未啟用時回傳空陣列

    Example: wcs_get_subscription 函式不存在時回傳空陣列
      Given WooCommerce Subscriptions 外掛未啟用
      When 系統呼叫 resolve_subscription_context(5001)
      Then 回傳結果應為空陣列

  Rule: 前置（參數）- 必要參數必須提供

    Scenario Outline: 缺少必要參數時操作失敗
      When 系統呼叫 resolve_subscription_context(<subscriptionId>)
      Then 回傳結果應為空陣列

      Examples:
        | subscriptionId |
        | 0              |
