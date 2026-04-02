@ignore @query
Feature: 解析訂單 Context V2（新增 order_status 欄位）

  Background:
    Given WooCommerce 外掛已啟用
    And 系統中有以下 WooCommerce 訂單：
      | orderId | orderTotal | billingEmail       | customerId | status    | lineItemsSummary | shippingAddress                 | paymentMethod | orderDate  | billingPhone |
      | 1001    | 2500       | alice@example.com  | 42         | completed | MacBook Pro x1   | 台北市信義區信義路五段7號         | credit_card   | 2026-04-01 | 0912345678   |
      | 1002    | 800        | bob@example.com    | 43         | pending   | iPhone 16 x1     | 新北市板橋區文化路一段100號       | bank_transfer | 2026-04-02 | 0987654321   |

  Rule: 後置（回應）- resolve_order_context 應回傳 10 個訂單關鍵欄位（含新增 order_status）

    Example: 訂單存在時回傳完整 context（10 個 keys）
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
        | order_status       | completed                      |

    Example: pending 狀態訂單的 order_status 回傳 pending
      When 系統呼叫 resolve_order_context(1002)
      Then 回傳結果的 order_status 應為 "pending"

  Rule: 後置（回應）- order_status 欄位為 WC_Order::get_status() 原始值

    Example: order_status 不含 wc- 前綴
      Given 訂單 1001 的 WooCommerce 內部狀態為 "wc-completed"
      When 系統呼叫 resolve_order_context(1001)
      Then 回傳結果的 order_status 應為 "completed"
      And 回傳結果的 order_status 不應包含 "wc-" 前綴

  Rule: 後置（回應）- 向下相容既有 ORDER_COMPLETED 觸發點

    Example: ORDER_COMPLETED 觸發點也使用新版 resolve_order_context（10 個 keys）
      Given 訂單 1001 的狀態為 "processing"
      When WooCommerce 將訂單 1001 的狀態更新為 "completed"
      And 系統呼叫 resolve_order_context(1001)
      Then 回傳結果應包含 10 個 key
      And 回傳結果應包含 key "order_status"

  Rule: 後置（回應）- 訂單已刪除時應回傳安全預設值

    Example: 訂單不存在時回傳空陣列
      Given 訂單 9999 已被刪除
      When 系統呼叫 resolve_order_context(9999)
      Then 回傳結果應為空陣列

  Rule: 前置（參數）- 必要參數必須提供

    Scenario Outline: 缺少必要參數時操作失敗
      When 系統呼叫 resolve_order_context(<orderId>)
      Then 回傳結果應為空陣列

      Examples:
        | orderId |
        | 0       |
