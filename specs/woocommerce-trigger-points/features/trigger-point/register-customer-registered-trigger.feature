@ignore @command
Feature: 註冊 CUSTOMER_REGISTERED 觸發點

  Background:
    Given 系統中有以下觸發點 enum：
      | case                 | hookValue                            | label      | group    | groupLabel |
      | CUSTOMER_REGISTERED  | pf/trigger/customer_registered       | 新顧客註冊  | customer | 顧客行為    |

  Rule: 後置（狀態）- ETriggerPoint 應包含 CUSTOMER_REGISTERED case

    Example: CUSTOMER_REGISTERED 的 hook value 為 pf/trigger/customer_registered
      When 系統讀取 ETriggerPoint::CUSTOMER_REGISTERED
      Then hook value 應為 "pf/trigger/customer_registered"
      And label 應為 "新顧客註冊"

  Rule: 後置（狀態）- CUSTOMER_REGISTERED 歸屬 customer 群組

    Example: group 與 group_label 正確
      When 系統讀取 ETriggerPoint::CUSTOMER_REGISTERED
      Then group 應為 "customer"
      And group_label 應為 "顧客行為"

  Rule: 後置（狀態）- CUSTOMER_REGISTERED 為正式實作（非存根）

    Example: is_stub 回傳 false
      When 系統讀取 ETriggerPoint::CUSTOMER_REGISTERED
      Then is_stub 應為 false

  Rule: 後置（狀態）- TriggerPointService 應監聽 user_register hook

    Example: 系統啟動時註冊 user_register 監聽
      When 系統執行 TriggerPointService::register_hooks()
      Then 系統應在 "user_register" hook 上註冊監聽器

  Rule: 前置（參數）- enum value 必須包含 pf/trigger/ 前綴

    Example: CUSTOMER_REGISTERED 的 value 以 pf/trigger/ 開頭
      When 系統讀取 ETriggerPoint::CUSTOMER_REGISTERED->value
      Then 值應以 "pf/trigger/" 開頭
