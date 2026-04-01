@ignore @command
Feature: 分支迴圈防護

  Background:
    Given 系統已註冊以下 NodeDefinition：
      | id             | name       | type   |
      | yes_no_branch  | 是/否分支   | action |
      | email          | 傳送 Email  | send_message |
    And 系統中有以下 Workflow（status=running）：
      | id  | context                                    |
      | 100 | {"order_id":"1001","order_total":"2500"}    |
    And Workflow 100 有以下節點：
      | id | node_definition_id | params                                                                                                           |
      | n1 | yes_no_branch      | {"condition_field":"order_total","operator":"gt","condition_value":"1000","yes_next_node_id":"n2","no_next_node_id":"n3"} |
      | n2 | email              | {"recipient":"context","subject_tpl":"VIP","content_tpl":"VIP"} |
      | n3 | yes_no_branch      | {"condition_field":"order_total","operator":"gt","condition_value":"500","yes_next_node_id":"n1","no_next_node_id":"n2"} |

  Rule: 前置（狀態）- 已執行過的節點不可再次執行

    Example: next_node_id 指向已執行過的節點時 Workflow 標記為 failed
      Given Workflow 100 的 results 有 2 筆：
        | node_id | code | next_node_id |
        | n1      | 200  | n3           |
        | n3      | 200  | n1           |
      When 系統呼叫 WorkflowDTO::try_execute()
      Then 系統偵測到節點 "n1" 已在 results 中存在
      And Workflow 100 的狀態應設為 "failed"
      And 錯誤訊息應為「偵測到節點迴圈：節點 n1 已執行過」

  Rule: 前置（狀態）- 正常的非迴圈分支應可正常執行

    Example: 不同分支路徑的節點不會觸發迴圈防護
      Given Workflow 100 的 results 有 1 筆：
        | node_id | code | next_node_id |
        | n1      | 200  | n2           |
      When 系統呼叫 WorkflowDTO::try_execute()
      Then 系統應正常執行節點 "n2"（n2 不在 results 中）

  Rule: 後置（狀態）- 迴圈偵測失敗時應記錄詳細資訊

    Example: 迴圈偵測觸發時記錄 warning 日誌
      Given Workflow 100 的 results 有 2 筆，next_node_id 指向已執行的節點
      When 系統偵測到迴圈
      Then 系統應記錄 warning 日誌，包含 workflow_id、重複的 node_id

  Rule: 前置（參數）- 必要參數必須提供

    Example: results 中的 node_id 不可為空
      Given Workflow 100 的 results 有 1 筆，node_id 為已知值
      When 系統檢查迴圈防護
      Then 系統應能正確比對 node_id
