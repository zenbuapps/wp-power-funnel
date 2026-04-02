@ignore @command
Feature: 觸發 CUSTOMER_REGISTERED 觸發點

  Background:
    Given WordPress 系統運作中

  Rule: 後置（狀態）- 新用戶註冊時應觸發 CUSTOMER_REGISTERED

    Example: WordPress 新用戶註冊時觸發
      Given 系統中尚無 user_id 為 100 的用戶
      When WordPress 完成用戶註冊，user_id 為 100
      Then 系統應觸發 "pf/trigger/customer_registered"
      And context_callable_set 的 callable 應為 [TriggerPointService::class, "resolve_customer_context"]
      And context_callable_set 的 params 應為 [100]

  Rule: 後置（狀態）- context_callable_set 必須符合 Serializable Context Callable 模式

    Example: context_callable_set 可被安全序列化
      When WordPress 完成用戶註冊，user_id 為 100
      Then context_callable_set 的 callable 應為 string[] 格式（非 Closure）
      And context_callable_set 的 params 應僅包含純值（int）

  Rule: 前置（參數）- user_register hook 傳入 user_id

    Example: user_register hook 的第一個參數為 user_id
      When WordPress 觸發 user_register hook
      Then hook 的第一個參數應為 int 型別的 user_id

  Rule: 後置（狀態）- 監聽 user_register hook（非 WooCommerce 專屬）

    Example: 不論 WooCommerce 是否啟用都應監聽 user_register
      Given WooCommerce 外掛未啟用
      When 系統執行 TriggerPointService::register_hooks()
      Then 系統應在 "user_register" hook 上註冊監聽器
