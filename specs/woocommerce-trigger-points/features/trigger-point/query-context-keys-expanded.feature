@ignore @query
Feature: 查詢觸發點可用 Context Keys（擴充新增觸發點）

  Background:
    Given 系統中有以下觸發點及其 context keys：
      | triggerPoint                                     | contextKeys                                                                                                                              |
      | pf/trigger/order_pending                         | order_id, order_total, billing_email, customer_id, line_items_summary, shipping_address, payment_method, order_date, billing_phone, order_status |
      | pf/trigger/order_processing                      | order_id, order_total, billing_email, customer_id, line_items_summary, shipping_address, payment_method, order_date, billing_phone, order_status |
      | pf/trigger/order_on_hold                         | order_id, order_total, billing_email, customer_id, line_items_summary, shipping_address, payment_method, order_date, billing_phone, order_status |
      | pf/trigger/order_cancelled                       | order_id, order_total, billing_email, customer_id, line_items_summary, shipping_address, payment_method, order_date, billing_phone, order_status |
      | pf/trigger/order_refunded                        | order_id, order_total, billing_email, customer_id, line_items_summary, shipping_address, payment_method, order_date, billing_phone, order_status |
      | pf/trigger/order_failed                          | order_id, order_total, billing_email, customer_id, line_items_summary, shipping_address, payment_method, order_date, billing_phone, order_status |
      | pf/trigger/customer_registered                   | customer_id, billing_email, billing_first_name, billing_last_name, billing_phone                                                          |
      | pf/trigger/subscription_initial_payment          | subscription_id, subscription_status, customer_id, billing_email, billing_first_name, billing_last_name, order_total, payment_method       |
      | pf/trigger/subscription_failed                   | subscription_id, subscription_status, customer_id, billing_email, billing_first_name, billing_last_name, order_total, payment_method       |
      | pf/trigger/subscription_success                  | subscription_id, subscription_status, customer_id, billing_email, billing_first_name, billing_last_name, order_total, payment_method       |
      | pf/trigger/subscription_renewal_order            | subscription_id, subscription_status, customer_id, billing_email, billing_first_name, billing_last_name, order_total, payment_method       |
      | pf/trigger/subscription_end                      | subscription_id, subscription_status, customer_id, billing_email, billing_first_name, billing_last_name, order_total, payment_method       |
      | pf/trigger/subscription_trial_end                | subscription_id, subscription_status, customer_id, billing_email, billing_first_name, billing_last_name, order_total, payment_method       |
      | pf/trigger/subscription_prepaid_end              | subscription_id, subscription_status, customer_id, billing_email, billing_first_name, billing_last_name, order_total, payment_method       |

  Rule: 後置（回應）- 新增訂單觸發點的 context keys 應包含 order_status

    Example: 查詢 ORDER_PENDING 觸發點的 context keys（10 個，含 order_status）
      When 管理員查詢觸發點 "pf/trigger/order_pending" 的 context keys
      Then 回傳結果應包含：
        | key                | label          |
        | order_id           | 訂單 ID        |
        | order_total        | 訂單金額        |
        | billing_email      | 帳單 Email     |
        | customer_id        | 客戶 ID        |
        | line_items_summary | 商品清單摘要    |
        | shipping_address   | 配送地址        |
        | payment_method     | 付款方式        |
        | order_date         | 訂單日期        |
        | billing_phone      | 帳單電話        |
        | order_status       | 訂單狀態        |

  Rule: 後置（回應）- 6 個訂單觸發點共用相同的 order_keys

    Scenario Outline: <triggerPoint> 的 context keys 與 ORDER_PENDING 相同
      When 管理員查詢觸發點 "<triggerPoint>" 的 context keys
      Then 回傳結果應包含 10 個 key
      And 回傳結果應包含 key "order_status"

      Examples:
        | triggerPoint                    |
        | pf/trigger/order_pending        |
        | pf/trigger/order_processing     |
        | pf/trigger/order_on_hold        |
        | pf/trigger/order_cancelled      |
        | pf/trigger/order_refunded       |
        | pf/trigger/order_failed         |

  Rule: 後置（回應）- 既有 ORDER_COMPLETED 也應擴充 order_status key

    Example: ORDER_COMPLETED 的 context keys 也包含 order_status（10 個）
      When 管理員查詢觸發點 "pf/trigger/order_completed" 的 context keys
      Then 回傳結果應包含 10 個 key
      And 回傳結果應包含 key "order_status"

  Rule: 後置（回應）- CUSTOMER_REGISTERED 的 context keys

    Example: 查詢 CUSTOMER_REGISTERED 觸發點的 context keys（5 個）
      When 管理員查詢觸發點 "pf/trigger/customer_registered" 的 context keys
      Then 回傳結果應包含：
        | key                  | label        |
        | customer_id          | 客戶 ID      |
        | billing_email        | 帳單 Email   |
        | billing_first_name   | 帳單名字      |
        | billing_last_name    | 帳單姓氏      |
        | billing_phone        | 帳單電話      |

  Rule: 後置（回應）- 7 個訂閱觸發點共用 subscription_keys

    Example: 查詢 SUBSCRIPTION_INITIAL_PAYMENT 觸發點的 context keys（8 個）
      When 管理員查詢觸發點 "pf/trigger/subscription_initial_payment" 的 context keys
      Then 回傳結果應包含：
        | key                  | label        |
        | subscription_id      | 訂閱 ID      |
        | subscription_status  | 訂閱狀態      |
        | customer_id          | 客戶 ID      |
        | billing_email        | 帳單 Email   |
        | billing_first_name   | 帳單名字      |
        | billing_last_name    | 帳單姓氏      |
        | order_total          | 訂單金額      |
        | payment_method       | 付款方式      |

    Scenario Outline: <triggerPoint> 的 context keys 與 SUBSCRIPTION_INITIAL_PAYMENT 相同
      When 管理員查詢觸發點 "<triggerPoint>" 的 context keys
      Then 回傳結果應包含 8 個 key
      And 回傳結果應包含 key "subscription_id"

      Examples:
        | triggerPoint                                     |
        | pf/trigger/subscription_failed                   |
        | pf/trigger/subscription_success                  |
        | pf/trigger/subscription_renewal_order            |
        | pf/trigger/subscription_end                      |
        | pf/trigger/subscription_trial_end                |
        | pf/trigger/subscription_prepaid_end              |
