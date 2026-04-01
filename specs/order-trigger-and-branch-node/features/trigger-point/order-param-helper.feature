@ignore @query
Feature: ParamHelper 訂單變數替換

  Background:
    Given 系統中有以下 Workflow（status=running）：
      | id  | context                                                                                           |
      | 100 | {"order_id":"1001","order_total":"2500","billing_email":"alice@example.com","customer_id":"42"}    |
    And Workflow 100 有以下節點：
      | id | node_definition_id | params                                                                              |
      | n1 | email              | {"recipient":"context","subject_tpl":"訂單 {{order_id}} 確認","content_tpl":"感謝您的購買，訂單金額為 {{order_total}} 元"} |
    And WooCommerce 外掛已啟用
    And 系統中有以下 WooCommerce 訂單：
      | orderId | orderTotal | billingEmail       | customerId |
      | 1001    | 2500       | alice@example.com  | 42         |

  Rule: 後置（回應）- ParamHelper::replace() 應透過 WC_Order 物件支援 order 相關 {{variable}} 替換

    Example: 模板中的 {{order_id}} 被替換為實際訂單 ID
      Given context 中 order_id 為 "1001"
      When 系統以 ParamHelper::replace() 處理 subject_tpl "訂單 {{order_id}} 確認"
      Then ParamHelper 應使用 wc_get_order(1001) 取得 WC_Order 物件
      And 結果應為 "訂單 1001 確認"

    Example: 模板中的 {{order_total}} 被替換為實際金額
      Given context 中 order_id 為 "1001"
      When 系統以 ParamHelper::replace() 處理 content_tpl "感謝您的購買，訂單金額為 {{order_total}} 元"
      Then 結果應為 "感謝您的購買，訂單金額為 2500 元"

  Rule: 後置（回應）- WC_Order 物件不存在時 order 相關變數不應被替換

    Example: 訂單已刪除時 order 變數保留原樣
      Given context 中 order_id 為 "9999"
      And 訂單 9999 不存在
      When 系統以 ParamHelper::replace() 處理模板 "訂單 {{order_id}} 資訊"
      Then 結果應為 "訂單 {{order_id}} 資訊"

  Rule: 後置（回應）- WooCommerce 未啟用時 order 變數不應被替換

    Example: WooCommerce 未啟用時 order 變數保留原樣
      Given WooCommerce 外掛未啟用
      When 系統以 ParamHelper::replace() 處理模板 "訂單 {{order_id}} 資訊"
      Then 結果應為 "訂單 {{order_id}} 資訊"
      And 系統不應拋出任何錯誤

  Rule: 後置（回應）- context 中不存在的變數不應被替換

    Example: 未知變數保留原樣
      When 系統以 ParamHelper::replace() 處理模板 "訂單 {{unknown_field}} 資訊"
      Then 結果應為 "訂單 {{unknown_field}} 資訊"

  Rule: 前置（參數）- 必要參數必須提供

    Example: recipient 為 "context" 時從 workflow.context 取值
      When 系統呼叫 ParamHelper::try_get_param("recipient")
      Then 結果應從 workflow.context 中取得對應值
