@ignore @command
Feature: YesNoBranchNode 條件分支執行

  Background:
    Given 系統已註冊以下 NodeDefinition：
      | id             | name      | type   |
      | yes_no_branch  | 是/否分支  | action |
      | email          | 傳送 Email | send_message |
    And 系統中有以下 Workflow（status=running）：
      | id  | context                                                                                        |
      | 100 | {"order_id":"1001","order_total":"2500","billing_email":"alice@example.com","customer_id":"42"} |
    And Workflow 100 有以下節點：
      | id  | node_definition_id | params                                                                                                           |
      | n1  | yes_no_branch      | {"condition_field":"order_total","operator":"gt","condition_value":"1000","yes_next_node_id":"n2","no_next_node_id":"n3"} |
      | n2  | email              | {"recipient":"context","subject_tpl":"VIP 歡迎","content_tpl":"感謝您的大額訂單"}                                     |
      | n3  | email              | {"recipient":"context","subject_tpl":"感謝購買","content_tpl":"感謝您的訂單"}                                         |

  Rule: 後置（狀態）- 條件為 true 時應走 yes 分支

    Example: order_total 2500 > 1000 為 true，走 yes_next_node_id "n2"
      Given Workflow 100 的 context 中 order_total 為 "2500"
      When 系統執行節點 "n1"（YesNoBranchNode）
      Then 結果的 code 應為 200
      And 結果的 next_node_id 應為 "n2"
      And 結果的 message 應包含 "條件成立"

  Rule: 後置（狀態）- 條件為 false 時應走 no 分支

    Example: order_total 500 > 1000 為 false，走 no_next_node_id "n3"
      Given Workflow 100 的 context 中 order_total 為 "500"
      When 系統執行節點 "n1"（YesNoBranchNode）
      Then 結果的 code 應為 200
      And 結果的 next_node_id 應為 "n3"
      And 結果的 message 應包含 "條件不成立"

  Rule: 後置（狀態）- 各運算子應正確判斷

    Example: equals 運算子比較字串
      Given Workflow 100 的 context 中 billing_email 為 "alice@example.com"
      And 節點 "n1" 的 operator 為 "equals"，condition_field 為 "billing_email"，condition_value 為 "alice@example.com"
      When 系統執行節點 "n1"（YesNoBranchNode）
      Then 結果的 next_node_id 應為 "n2"

    Example: contains 運算子判斷子字串
      Given Workflow 100 的 context 中 billing_email 為 "alice@gmail.com"
      And 節點 "n1" 的 operator 為 "contains"，condition_field 為 "billing_email"，condition_value 為 "@gmail.com"
      When 系統執行節點 "n1"（YesNoBranchNode）
      Then 結果的 next_node_id 應為 "n2"

    Example: is_empty 運算子判斷空值
      Given Workflow 100 的 context 中 billing_email 為 ""
      And 節點 "n1" 的 operator 為 "is_empty"，condition_field 為 "billing_email"
      When 系統執行節點 "n1"（YesNoBranchNode）
      Then 結果的 next_node_id 應為 "n2"

    Example: gte 運算子數值比較（邊界值）
      Given Workflow 100 的 context 中 order_total 為 "1000"
      And 節點 "n1" 的 operator 為 "gte"，condition_field 為 "order_total"，condition_value 為 "1000"
      When 系統執行節點 "n1"（YesNoBranchNode）
      Then 結果的 next_node_id 應為 "n2"

  Rule: 前置（狀態）- condition_field 在 context 中不存在時應走 no 分支

    Example: context 中無 nonexistent_field 時走否分支
      Given Workflow 100 的 context 中不包含 key "nonexistent_field"
      And 節點 "n1" 的 condition_field 為 "nonexistent_field"
      When 系統執行節點 "n1"（YesNoBranchNode）
      Then 結果的 code 應為 200
      And 結果的 next_node_id 應為 "n3"
      And 結果的 message 應包含 "條件不成立"

  Rule: 後置（狀態）- 數值比較時應將字串轉為數值

    Example: order_total 字串 "2500" 與 "1000" 的數值比較
      Given Workflow 100 的 context 中 order_total 為 "2500"（字串）
      And 節點 "n1" 的 operator 為 "gt"，condition_value 為 "1000"
      When 系統執行節點 "n1"（YesNoBranchNode）
      Then 系統應將兩邊都轉為數值後比較
      And 結果的 next_node_id 應為 "n2"

  Rule: 前置（參數）- 必要參數必須提供

    Scenario Outline: 缺少 <缺少參數> 時節點執行失敗
      Given 節點 "n1" 的 params 中 <缺少參數> 為空
      When 系統執行節點 "n1"（YesNoBranchNode）
      Then 結果的 code 應為 500
      And 結果的 message 應為「必要參數未提供」

      Examples:
        | 缺少參數          |
        | condition_field   |
        | operator          |
        | yes_next_node_id  |
        | no_next_node_id   |
