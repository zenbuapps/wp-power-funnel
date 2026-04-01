@ignore @command
Feature: 註冊 ORDER_COMPLETED 觸發點

  Background:
    Given 系統中有以下觸發點 enum：
      | case              | hookValue                        | label           |
      | ORDER_COMPLETED   | pf/trigger/order_completed       | 訂單完成後       |

  Rule: 後置（狀態）- ETriggerPoint 應包含 ORDER_COMPLETED case

    Example: ORDER_COMPLETED 的 hook value 為 pf/trigger/order_completed
      When 系統讀取 ETriggerPoint::ORDER_COMPLETED
      Then hook value 應為 "pf/trigger/order_completed"
      And label 應為 "訂單完成後"

  Rule: 後置（狀態）- WooCommerce 啟用時 TriggerPointService 應監聽 woocommerce_order_status_completed

    Example: WooCommerce 啟用時註冊 hook 監聽
      Given WooCommerce 外掛已啟用
      When 系統執行 TriggerPointService::register_hooks()
      Then 系統應在 "woocommerce_order_status_completed" hook 上註冊監聽器

  Rule: 前置（狀態）- WooCommerce 未啟用時不應註冊監聽器

    Example: WooCommerce 未啟用時靜默忽略
      Given WooCommerce 外掛未啟用
      When 系統執行 TriggerPointService::register_hooks()
      Then 系統不應在 "woocommerce_order_status_completed" hook 上註冊監聽器
      And 系統不應拋出任何錯誤

  Rule: 前置（參數）- 必要參數必須提供

    Example: ORDER_COMPLETED 的 enum value 必須包含 pf/trigger/ 前綴
      When 系統讀取 ETriggerPoint::ORDER_COMPLETED->value
      Then 值應以 "pf/trigger/" 開頭
