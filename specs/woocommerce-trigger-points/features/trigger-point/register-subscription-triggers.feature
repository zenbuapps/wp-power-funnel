@ignore @command
Feature: 註冊 7 個訂閱生命週期觸發點

  Background:
    Given 系統中有以下觸發點 enum：
      | case                           | hookValue                                     | label            | group        | groupLabel |
      | SUBSCRIPTION_INITIAL_PAYMENT   | pf/trigger/subscription_initial_payment        | 訂閱首次付款完成  | subscription | 訂閱        |
      | SUBSCRIPTION_FAILED            | pf/trigger/subscription_failed                 | 訂閱失敗          | subscription | 訂閱        |
      | SUBSCRIPTION_SUCCESS           | pf/trigger/subscription_success                | 訂閱成功          | subscription | 訂閱        |
      | SUBSCRIPTION_RENEWAL_ORDER     | pf/trigger/subscription_renewal_order          | 訂閱續訂訂單建立  | subscription | 訂閱        |
      | SUBSCRIPTION_END               | pf/trigger/subscription_end                    | 訂閱結束          | subscription | 訂閱        |
      | SUBSCRIPTION_TRIAL_END         | pf/trigger/subscription_trial_end              | 訂閱試用期結束    | subscription | 訂閱        |
      | SUBSCRIPTION_PREPAID_END       | pf/trigger/subscription_prepaid_end            | 訂閱預付期結束    | subscription | 訂閱        |

  Rule: 後置（狀態）- ETriggerPoint 應包含 7 個訂閱 case

    Scenario Outline: <case> 的 hook value 與 label 正確
      When 系統讀取 ETriggerPoint::<case>
      Then hook value 應為 "<hookValue>"
      And label 應為 "<label>"

      Examples:
        | case                           | hookValue                                     | label            |
        | SUBSCRIPTION_INITIAL_PAYMENT   | pf/trigger/subscription_initial_payment        | 訂閱首次付款完成  |
        | SUBSCRIPTION_FAILED            | pf/trigger/subscription_failed                 | 訂閱失敗          |
        | SUBSCRIPTION_SUCCESS           | pf/trigger/subscription_success                | 訂閱成功          |
        | SUBSCRIPTION_RENEWAL_ORDER     | pf/trigger/subscription_renewal_order          | 訂閱續訂訂單建立  |
        | SUBSCRIPTION_END               | pf/trigger/subscription_end                    | 訂閱結束          |
        | SUBSCRIPTION_TRIAL_END         | pf/trigger/subscription_trial_end              | 訂閱試用期結束    |
        | SUBSCRIPTION_PREPAID_END       | pf/trigger/subscription_prepaid_end            | 訂閱預付期結束    |

  Rule: 後置（狀態）- 所有訂閱觸發點歸屬 subscription 群組

    Scenario Outline: <case> 的 group 與 group_label 正確
      When 系統讀取 ETriggerPoint::<case>
      Then group 應為 "subscription"
      And group_label 應為 "訂閱"

      Examples:
        | case                           |
        | SUBSCRIPTION_INITIAL_PAYMENT   |
        | SUBSCRIPTION_FAILED            |
        | SUBSCRIPTION_SUCCESS           |
        | SUBSCRIPTION_RENEWAL_ORDER     |
        | SUBSCRIPTION_END               |
        | SUBSCRIPTION_TRIAL_END         |
        | SUBSCRIPTION_PREPAID_END       |

  Rule: 後置（狀態）- 所有訂閱觸發點均為正式實作（非存根）

    Scenario Outline: <case> 的 is_stub 回傳 false
      When 系統讀取 ETriggerPoint::<case>
      Then is_stub 應為 false

      Examples:
        | case                           |
        | SUBSCRIPTION_INITIAL_PAYMENT   |
        | SUBSCRIPTION_FAILED            |
        | SUBSCRIPTION_SUCCESS           |
        | SUBSCRIPTION_RENEWAL_ORDER     |
        | SUBSCRIPTION_END               |
        | SUBSCRIPTION_TRIAL_END         |
        | SUBSCRIPTION_PREPAID_END       |

  Rule: 後置（狀態）- Powerhouse 外掛啟用時 TriggerPointService 應監聽 7 個 hook

    Scenario Outline: Powerhouse 啟用時註冊 <powerhouseHook> 監聽
      Given Powerhouse 外掛已啟用
      When 系統執行 TriggerPointService::register_hooks()
      Then 系統應在 "<powerhouseHook>" hook 上註冊監聽器

      Examples:
        | powerhouseHook                                         |
        | powerhouse_subscription_at_initial_payment_complete    |
        | powerhouse_subscription_at_subscription_failed         |
        | powerhouse_subscription_at_subscription_success        |
        | powerhouse_subscription_at_renewal_order_created       |
        | powerhouse_subscription_at_end                         |
        | powerhouse_subscription_at_trial_end                   |
        | powerhouse_subscription_at_end_of_prepaid_term         |

  Rule: 前置（狀態）- Powerhouse 外掛未啟用時不應註冊監聽器

    Example: Powerhouse 未啟用時靜默忽略所有訂閱觸發點
      Given Powerhouse 外掛未啟用
      When 系統執行 TriggerPointService::register_hooks()
      Then 系統不應在任何 "powerhouse_subscription_at_*" hook 上註冊監聯器
      And 系統不應拋出任何錯誤

  Rule: 前置（參數）- 所有 enum value 必須包含 pf/trigger/ 前綴

    Scenario Outline: <case> 的 value 以 pf/trigger/ 開頭
      When 系統讀取 ETriggerPoint::<case>->value
      Then 值應以 "pf/trigger/" 開頭

      Examples:
        | case                           |
        | SUBSCRIPTION_INITIAL_PAYMENT   |
        | SUBSCRIPTION_FAILED            |
        | SUBSCRIPTION_SUCCESS           |
        | SUBSCRIPTION_RENEWAL_ORDER     |
        | SUBSCRIPTION_END               |
        | SUBSCRIPTION_TRIAL_END         |
        | SUBSCRIPTION_PREPAID_END       |
