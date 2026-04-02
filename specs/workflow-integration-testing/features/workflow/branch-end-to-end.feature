@ignore @command
Feature: YesNoBranchNode 分支路徑全鏈路測試

  驗證 YesNoBranchNode 根據 context 中的條件值選擇正確的分支路徑，
  被執行的分支節點出現在 results 中，未被執行的分支節點不出現。

  測試策略：
  - context 帶有 order_total 用於條件判斷
  - YesNoBranchNode 後接 yes 分支節點（VIP EmailNode）和 no 分支節點（普通 EmailNode）
  - 使用 test_email 節點定義（繞過 ReplaceHelper null-object bug）
  - 分支判斷後 workflow 應走到 completed

  Background:
    Given 系統已註冊以下 NodeDefinition：
      | id            | name          | type         |
      | yes_no_branch | 是/否分支      | action       |
      | test_email    | 測試 Email 節點 | send_message |
    And TestCallable::$test_context 設定為：
      | key            | value             |
      | order_id       | 1001              |
      | order_total    | 1500              |
      | customer_email | alice@example.com |
    And context_callable_set 為 [TestCallable::class, 'return_test_context']

  Rule: 後置（狀態）- 條件成立時 yes 分支節點應被執行

    Example: order_total 1500 > 1000 走 yes 分支，VIP 節點被執行
      Given 系統中有以下 Workflow（status=running）：
        | id  | context                                                                 |
        | 300 | {"order_id":"1001","order_total":"1500","customer_email":"alice@example.com"} |
      And Workflow 300 有以下節點：
        | id    | node_definition_id | params |
        | n1    | yes_no_branch      | {"condition_field":"order_total","operator":"gt","condition_value":"1000","yes_next_node_id":"n_vip","no_next_node_id":"n_regular"} |
        | n_vip | test_email         | {"recipient":"alice@example.com","subject_tpl":"VIP 歡迎","content_tpl":"感謝大額訂單"} |
        | n_regular | test_email     | {"recipient":"alice@example.com","subject_tpl":"感謝購買","content_tpl":"感謝您的訂單"} |
      And Workflow 300 的 results 為空
      When 系統呼叫 WorkflowDTO::try_execute() 直到 workflow 完成
      Then Workflow 300 的 status 應為 "completed"
      And results 中應包含 node_id "n1" 且 code 為 200
      And results 中應包含 node_id "n_vip" 且 code 為 200
      And results 中不應包含 node_id "n_regular"

  Rule: 後置（狀態）- 條件不成立時 no 分支節點應被執行

    Example: order_total 500 > 1000 不成立走 no 分支，普通節點被執行
      Given TestCallable::$test_context 中 order_total 設為 "500"
      And 系統中有以下 Workflow（status=running）：
        | id  | context                                                                |
        | 301 | {"order_id":"1001","order_total":"500","customer_email":"alice@example.com"} |
      And Workflow 301 有以下節點：
        | id    | node_definition_id | params |
        | n1    | yes_no_branch      | {"condition_field":"order_total","operator":"gt","condition_value":"1000","yes_next_node_id":"n_vip","no_next_node_id":"n_regular"} |
        | n_vip | test_email         | {"recipient":"alice@example.com","subject_tpl":"VIP 歡迎","content_tpl":"感謝大額訂單"} |
        | n_regular | test_email     | {"recipient":"alice@example.com","subject_tpl":"感謝購買","content_tpl":"感謝您的訂單"} |
      And Workflow 301 的 results 為空
      When 系統呼叫 WorkflowDTO::try_execute() 直到 workflow 完成
      Then Workflow 301 的 status 應為 "completed"
      And results 中應包含 node_id "n1" 且 code 為 200
      And results 中應包含 node_id "n_regular" 且 code 為 200
      And results 中不應包含 node_id "n_vip"

  Rule: 後置（狀態）- 分支後接續的節點仍應正常執行

    Example: YesNoBranchNode 後還有 EmailNode 時三個節點依序執行
      Given 系統中有以下 Workflow（status=running）：
        | id  | context                                                                 |
        | 302 | {"order_id":"1001","order_total":"1500","customer_email":"alice@example.com"} |
      And Workflow 302 有以下節點：
        | id       | node_definition_id | params |
        | n1       | yes_no_branch      | {"condition_field":"order_total","operator":"gt","condition_value":"1000","yes_next_node_id":"n_vip","no_next_node_id":"n_regular"} |
        | n_vip    | test_email         | {"recipient":"alice@example.com","subject_tpl":"VIP 歡迎","content_tpl":"感謝大額訂單"} |
        | n_regular | test_email        | {"recipient":"alice@example.com","subject_tpl":"感謝購買","content_tpl":"感謝您的訂單"} |
        | n_final  | test_email         | {"recipient":"alice@example.com","subject_tpl":"訂單確認","content_tpl":"處理完成"} |
      And Workflow 302 的 results 為空
      When 系統呼叫 WorkflowDTO::try_execute() 直到 workflow 完成
      Then Workflow 302 的 status 應為 "completed"
      And results 中應包含 node_id "n1" 且 code 為 200
      And results 中應包含 node_id "n_vip" 且 code 為 200
      And results 中應包含 node_id "n_final" 且 code 為 200
      And results 中不應包含 node_id "n_regular"
