@ignore @command
Feature: 觸發 ORDER_COMPLETED 觸發點

  Background:
    Given WooCommerce 外掛已啟用
    And 系統中有以下 WooCommerce 訂單：
      | orderId | orderTotal | billingEmail       | customerId | status     |
      | 1001    | 2500       | alice@example.com  | 42         | processing |

  Rule: 前置（狀態）- 訂單狀態必須變更為 completed

    Example: 訂單狀態從 processing 變為 completed 時觸發
      Given 訂單 1001 的狀態為 "processing"
      When WooCommerce 將訂單 1001 的狀態更新為 "completed"
      Then 系統應觸發 "pf/trigger/order_completed"
      And context_callable_set 的 callable 應為 [TriggerPointService::class, "resolve_order_context"]
      And context_callable_set 的 params 應為 [1001]

  Rule: 前置（狀態）- 訂單狀態未變更為 completed 時不應觸發

    Example: 訂單狀態從 processing 變為 cancelled 時不觸發 ORDER_COMPLETED
      Given 訂單 1001 的狀態為 "processing"
      When WooCommerce 將訂單 1001 的狀態更新為 "cancelled"
      Then 系統不應觸發 "pf/trigger/order_completed"

  Rule: 前置（狀態）- 訂單不存在時不應觸發

    Example: wc_get_order() 回傳 false 時不觸發
      Given 訂單 9999 不存在
      When 系統接收到 woocommerce_order_status_completed hook，order_id 為 9999
      Then 系統不應觸發 "pf/trigger/order_completed"
      And 系統應記錄 warning 日誌

  Rule: 後置（狀態）- context_callable_set 必須符合 Serializable Context Callable 模式

    Example: context_callable_set 可被安全序列化
      Given 訂單 1001 的狀態為 "processing"
      When WooCommerce 將訂單 1001 的狀態更新為 "completed"
      Then context_callable_set 的 callable 應為 string[] 格式（非 Closure）
      And context_callable_set 的 params 應僅包含純值（int）

  Rule: 前置（參數）- 必要參數必須提供

    Example: woocommerce_order_status_completed hook 必須傳入 order_id
      When 系統接收到 woocommerce_order_status_completed hook
      Then 系統應使用 wc_get_order() 取得訂單物件（HPOS 相容）
