@ignore @command
Feature: 觸發 7 個訂閱生命週期觸發點

  Background:
    Given Powerhouse 外掛已啟用
    And WooCommerce Subscriptions 外掛已啟用
    And 系統中有以下 WC_Subscription：
      | subscriptionId | status | customerId | billingEmail       | total | paymentMethod |
      | 5001           | active | 42         | alice@example.com  | 499   | credit_card   |

  Rule: 後置（狀態）- Powerhouse 訂閱事件發生時應觸發對應觸發點

    Scenario Outline: Powerhouse hook <powerhouseHook> 觸發時，應 do_action <pfHook>
      When Powerhouse 觸發 "<powerhouseHook>" hook，傳入 subscription_id 為 5001
      Then 系統應觸發 "<pfHook>"
      And context_callable_set 的 callable 應為 [TriggerPointService::class, "resolve_subscription_context"]
      And context_callable_set 的 params 應為 [5001]

      Examples:
        | powerhouseHook                                         | pfHook                                         |
        | powerhouse_subscription_at_initial_payment_complete    | pf/trigger/subscription_initial_payment        |
        | powerhouse_subscription_at_subscription_failed         | pf/trigger/subscription_failed                 |
        | powerhouse_subscription_at_subscription_success        | pf/trigger/subscription_success                |
        | powerhouse_subscription_at_renewal_order_created       | pf/trigger/subscription_renewal_order          |
        | powerhouse_subscription_at_end                         | pf/trigger/subscription_end                    |
        | powerhouse_subscription_at_trial_end                   | pf/trigger/subscription_trial_end              |
        | powerhouse_subscription_at_end_of_prepaid_term         | pf/trigger/subscription_prepaid_end            |

  Rule: 前置（參數）- Powerhouse hook 傳入 WC_Subscription 物件

    Example: handler 從 WC_Subscription 物件取得 subscription_id
      When Powerhouse 觸發 "powerhouse_subscription_at_initial_payment_complete" hook
      Then handler 的第一個參數應為 WC_Subscription 物件
      And handler 的第二個參數應為 array（$params）
      And 系統應從 WC_Subscription 物件呼叫 get_id() 取得 subscription_id

  Rule: 前置（狀態）- WC_Subscription 物件無效時不應觸發

    Example: 傳入的物件不是 WC_Subscription 時靜默忽略
      When Powerhouse 觸發 "powerhouse_subscription_at_initial_payment_complete" hook，但傳入非 WC_Subscription 物件
      Then 系統不應觸發任何 "pf/trigger/subscription_*" hook
      And 系統應記錄 warning 日誌

  Rule: 後置（狀態）- context_callable_set 必須符合 Serializable Context Callable 模式

    Example: context_callable_set 可被安全序列化
      When Powerhouse 觸發 "powerhouse_subscription_at_subscription_success" hook，傳入 subscription_id 為 5001
      Then context_callable_set 的 callable 應為 string[] 格式（非 Closure）
      And context_callable_set 的 params 應僅包含純值（int）
      And context_callable_set 不應包含 WC_Subscription 物件引用
