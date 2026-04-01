@ignore @query
Feature: 解析訂單 Context（延遲求值）

  Background:
    Given WooCommerce 外掛已啟用
    And 系統中有以下 WooCommerce 訂單：
      | orderId | orderTotal | billingEmail       | customerId | status    | lineItemsSummary | shippingAddress                 | paymentMethod | orderDate  | billingPhone |
      | 1001    | 2500       | alice@example.com  | 42         | completed | MacBook Pro x1   | 台北市信義區信義路五段7號         | credit_card   | 2026-04-01 | 0912345678   |

  Rule: 後置（回應）- resolve_order_context 應回傳 9 個訂單關鍵欄位

    Example: 訂單存在時回傳完整 context（9 個 keys）
      When 系統呼叫 resolve_order_context(1001)
      Then 回傳結果應包含：
        | key                | value                          |
        | order_id           | 1001                           |
        | order_total        | 2500                           |
        | billing_email      | alice@example.com              |
        | customer_id        | 42                             |
        | line_items_summary | MacBook Pro x1                 |
        | shipping_address   | 台北市信義區信義路五段7號        |
        | payment_method     | credit_card                    |
        | order_date         | 2026-04-01                     |
        | billing_phone      | 0912345678                     |

  Rule: 後置（回應）- 訂單已刪除時應回傳安全預設值

    Example: 訂單不存在時回傳空陣列
      Given 訂單 9999 已被刪除
      When 系統呼叫 resolve_order_context(9999)
      Then 回傳結果應為空陣列

  Rule: 後置（回應）- WaitNode 延遲後應取得最新訂單資料

    Example: 訂單金額在 WaitNode 等待期間被修改後取得最新值
      Given 訂單 1001 的 order_total 為 "2500"
      And WaitNode 等待 1 小時後，訂單 1001 的 order_total 被修改為 "3000"
      When 系統呼叫 resolve_order_context(1001)
      Then 回傳結果的 order_total 應為 "3000"

  Rule: 前置（參數）- 必要參數必須提供

    Scenario Outline: 缺少必要參數時操作失敗
      When 系統呼叫 resolve_order_context(<orderId>)
      Then 回傳結果應為空陣列

      Examples:
        | orderId |
        | 0       |
