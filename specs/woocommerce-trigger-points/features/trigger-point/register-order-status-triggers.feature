@ignore @command
Feature: 註冊 6 個 WooCommerce 訂單狀態觸發點

  Background:
    Given 系統中有以下觸發點 enum：
      | case              | hookValue                          | label        | group       | groupLabel    |
      | ORDER_PENDING     | pf/trigger/order_pending           | 訂單待付款    | woocommerce | WooCommerce   |
      | ORDER_PROCESSING  | pf/trigger/order_processing        | 訂單處理中    | woocommerce | WooCommerce   |
      | ORDER_ON_HOLD     | pf/trigger/order_on_hold           | 訂單保留中    | woocommerce | WooCommerce   |
      | ORDER_CANCELLED   | pf/trigger/order_cancelled         | 訂單已取消    | woocommerce | WooCommerce   |
      | ORDER_REFUNDED    | pf/trigger/order_refunded          | 訂單已退款    | woocommerce | WooCommerce   |
      | ORDER_FAILED      | pf/trigger/order_failed            | 訂單失敗      | woocommerce | WooCommerce   |

  Rule: 後置（狀態）- ETriggerPoint 應包含 6 個新訂單狀態 case

    Scenario Outline: <case> 的 hook value 與 label 正確
      When 系統讀取 ETriggerPoint::<case>
      Then hook value 應為 "<hookValue>"
      And label 應為 "<label>"

      Examples:
        | case              | hookValue                          | label        |
        | ORDER_PENDING     | pf/trigger/order_pending           | 訂單待付款    |
        | ORDER_PROCESSING  | pf/trigger/order_processing        | 訂單處理中    |
        | ORDER_ON_HOLD     | pf/trigger/order_on_hold           | 訂單保留中    |
        | ORDER_CANCELLED   | pf/trigger/order_cancelled         | 訂單已取消    |
        | ORDER_REFUNDED    | pf/trigger/order_refunded          | 訂單已退款    |
        | ORDER_FAILED      | pf/trigger/order_failed            | 訂單失敗      |

  Rule: 後置（狀態）- 所有新觸發點歸屬 woocommerce 群組

    Scenario Outline: <case> 的 group 與 group_label 正確
      When 系統讀取 ETriggerPoint::<case>
      Then group 應為 "woocommerce"
      And group_label 應為 "WooCommerce"

      Examples:
        | case              |
        | ORDER_PENDING     |
        | ORDER_PROCESSING  |
        | ORDER_ON_HOLD     |
        | ORDER_CANCELLED   |
        | ORDER_REFUNDED    |
        | ORDER_FAILED      |

  Rule: 後置（狀態）- 所有新觸發點均為正式實作（非存根）

    Scenario Outline: <case> 的 is_stub 回傳 false
      When 系統讀取 ETriggerPoint::<case>
      Then is_stub 應為 false

      Examples:
        | case              |
        | ORDER_PENDING     |
        | ORDER_PROCESSING  |
        | ORDER_ON_HOLD     |
        | ORDER_CANCELLED   |
        | ORDER_REFUNDED    |
        | ORDER_FAILED      |

  Rule: 後置（狀態）- WooCommerce 啟用時 TriggerPointService 應監聽 6 個 hook

    Scenario Outline: WooCommerce 啟用時註冊 <wcHook> 監聽
      Given WooCommerce 外掛已啟用
      When 系統執行 TriggerPointService::register_hooks()
      Then 系統應在 "<wcHook>" hook 上註冊監聽器

      Examples:
        | wcHook                                |
        | woocommerce_order_status_pending      |
        | woocommerce_order_status_processing   |
        | woocommerce_order_status_on-hold      |
        | woocommerce_order_status_cancelled    |
        | woocommerce_order_status_refunded     |
        | woocommerce_order_status_failed       |

  Rule: 前置（狀態）- WooCommerce 未啟用時不應註冊監聽器

    Example: WooCommerce 未啟用時靜默忽略所有訂單觸發點
      Given WooCommerce 外掛未啟用
      When 系統執行 TriggerPointService::register_hooks()
      Then 系統不應在任何 "woocommerce_order_status_*" hook 上註冊監聽器
      And 系統不應拋出任何錯誤

  Rule: 前置（參數）- 所有 enum value 必須包含 pf/trigger/ 前綴

    Scenario Outline: <case> 的 value 以 pf/trigger/ 開頭
      When 系統讀取 ETriggerPoint::<case>->value
      Then 值應以 "pf/trigger/" 開頭

      Examples:
        | case              |
        | ORDER_PENDING     |
        | ORDER_PROCESSING  |
        | ORDER_ON_HOLD     |
        | ORDER_CANCELLED   |
        | ORDER_REFUNDED    |
        | ORDER_FAILED      |
