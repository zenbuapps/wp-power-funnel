@ignore @command
Feature: 觸發 6 個 WooCommerce 訂單狀態觸發點

  Background:
    Given WooCommerce 外掛已啟用
    And 系統中有以下 WooCommerce 訂單：
      | orderId | orderTotal | billingEmail       | customerId | status     |
      | 1001    | 2500       | alice@example.com  | 42         | processing |

  Rule: 後置（狀態）- 訂單狀態變更時應觸發對應觸發點

    Scenario Outline: 訂單狀態變更為 <status> 時觸發 <hookValue>
      Given 訂單 1001 的狀態為 "processing"
      When WooCommerce 將訂單 1001 的狀態更新為 "<status>"
      Then 系統應觸發 "<hookValue>"
      And context_callable_set 的 callable 應為 [TriggerPointService::class, "resolve_order_context"]
      And context_callable_set 的 params 應為 [1001]

      Examples:
        | status     | hookValue                          |
        | pending    | pf/trigger/order_pending           |
        | on-hold    | pf/trigger/order_on_hold           |
        | cancelled  | pf/trigger/order_cancelled         |
        | refunded   | pf/trigger/order_refunded          |
        | failed     | pf/trigger/order_failed            |

    Example: 訂單狀態從 pending 變為 processing 時觸發 ORDER_PROCESSING
      Given 訂單 1001 的狀態為 "pending"
      When WooCommerce 將訂單 1001 的狀態更新為 "processing"
      Then 系統應觸發 "pf/trigger/order_processing"
      And context_callable_set 的 callable 應為 [TriggerPointService::class, "resolve_order_context"]
      And context_callable_set 的 params 應為 [1001]

  Rule: 前置（狀態）- 訂單不存在時不應觸發

    Example: wc_get_order() 回傳 false 時不觸發
      Given 訂單 9999 不存在
      When 系統接收到 woocommerce_order_status_pending hook，order_id 為 9999
      Then 系統不應觸發任何 "pf/trigger/order_*" hook
      And 系統應記錄 warning 日誌

  Rule: 後置（狀態）- context_callable_set 必須符合 Serializable Context Callable 模式

    Example: context_callable_set 可被安全序列化
      Given 訂單 1001 的狀態為 "processing"
      When WooCommerce 將訂單 1001 的狀態更新為 "pending"
      Then context_callable_set 的 callable 應為 string[] 格式（非 Closure）
      And context_callable_set 的 params 應僅包含純值（int）

  Rule: 後置（狀態）- 所有 6 個觸發點複用 build_order_context_callable_set()

    Scenario Outline: <status> 狀態觸發時使用相同的 build_order_context_callable_set 方法
      When WooCommerce 將訂單 1001 的狀態更新為 "<status>"
      Then 系統內部應呼叫 build_order_context_callable_set(1001)

      Examples:
        | status     |
        | pending    |
        | processing |
        | on-hold    |
        | cancelled  |
        | refunded   |
        | failed     |
