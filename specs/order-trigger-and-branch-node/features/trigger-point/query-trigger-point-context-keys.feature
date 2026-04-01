@ignore @query
Feature: 查詢觸發點可用 Context Keys

  Background:
    Given 系統中有以下觸發點及其 context keys：
      | triggerPoint                       | contextKeys                                                                                                      |
      | pf/trigger/order_completed         | order_id, order_total, billing_email, customer_id, line_items_summary, shipping_address, payment_method, order_date, billing_phone |
      | pf/trigger/registration_approved   | registration_id, identity_id, identity_provider, activity_id, promo_link_id                                      |
      | pf/trigger/line_followed           | line_user_id, event_type                                                                                          |

  Rule: 後置（回應）- 指定觸發點時應回傳該觸發點的可用 context keys

    Example: 查詢 ORDER_COMPLETED 觸發點的 context keys
      When 管理員查詢觸發點 "pf/trigger/order_completed" 的 context keys
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

    Example: 查詢 REGISTRATION_APPROVED 觸發點的 context keys
      When 管理員查詢觸發點 "pf/trigger/registration_approved" 的 context keys
      Then 回傳結果應包含：
        | key               | label         |
        | registration_id   | 報名 ID       |
        | identity_id       | 身分 ID       |
        | identity_provider | 身分提供者     |
        | activity_id       | 活動 ID       |
        | promo_link_id     | 推廣連結 ID   |

  Rule: 前置（狀態）- 觸發點不存在時應回傳空陣列

    Example: 查詢不存在的觸發點時回傳空陣列
      When 管理員查詢觸發點 "pf/trigger/nonexistent" 的 context keys
      Then 回傳結果應為空陣列

  Rule: 前置（參數）- 必要參數必須提供

    Example: 未提供觸發點名稱時操作失敗
      When 管理員查詢觸發點的 context keys，但未提供觸發點名稱
      Then 操作失敗，錯誤為「必要參數未提供」
