@ignore @command
Feature: Workflow 非線性執行（支援 next_node_id 跳轉）

  Background:
    Given 系統已註冊以下 NodeDefinition：
      | id             | name       | type         |
      | yes_no_branch  | 是/否分支   | action       |
      | email          | 傳送 Email  | send_message |
    And 系統中有以下 Workflow（status=running）：
      | id  | context                                                   |
      | 100 | {"order_id":"1001","order_total":"2500"}                   |
    And Workflow 100 有以下節點：
      | id | node_definition_id | params                                                                                                           |
      | n1 | yes_no_branch      | {"condition_field":"order_total","operator":"gt","condition_value":"1000","yes_next_node_id":"n2","no_next_node_id":"n3"} |
      | n2 | email              | {"recipient":"context","subject_tpl":"VIP","content_tpl":"VIP 歡迎"}                                               |
      | n3 | email              | {"recipient":"context","subject_tpl":"普通","content_tpl":"感謝訂購"}                                               |

  Rule: 後置（狀態）- 前一個 result 帶有 next_node_id 時應跳轉到對應節點

    Example: n1 結果帶 next_node_id="n2"，下一個執行 n2（跳過線性順序）
      Given Workflow 100 的 results 有 1 筆：
        | node_id | code | next_node_id |
        | n1      | 200  | n2           |
      When 系統呼叫 WorkflowDTO::try_execute()
      Then 系統應執行節點 "n2"（而非線性的下一個）

    Example: n1 結果帶 next_node_id="n3"，下一個執行 n3
      Given Workflow 100 的 results 有 1 筆：
        | node_id | code | next_node_id |
        | n1      | 200  | n3           |
      When 系統呼叫 WorkflowDTO::try_execute()
      Then 系統應執行節點 "n3"

  Rule: 後置（狀態）- 不帶 next_node_id 的結果應維持線性執行（向下相容）

    Example: 普通節點不帶 next_node_id，按線性順序執行
      Given Workflow 100 的 results 有 1 筆：
        | node_id | code | next_node_id |
        | n1      | 200  |              |
      When 系統呼叫 WorkflowDTO::try_execute()
      Then 系統應執行 nodes[1]（線性下一個）

  Rule: 前置（狀態）- next_node_id 對應的節點必須存在

    Example: next_node_id 指向不存在的節點時 Workflow 標記為 failed
      Given Workflow 100 的 results 有 1 筆：
        | node_id | code | next_node_id |
        | n1      | 200  | nonexistent  |
      When 系統呼叫 WorkflowDTO::try_execute()
      Then Workflow 100 的狀態應設為 "failed"
      And 錯誤訊息應為「找不到目標節點 nonexistent」

  Rule: 後置（狀態）- 分支執行完成後 Workflow 應正確判定完成

    Example: 分支節點執行後僅執行一個分支路徑即完成
      Given Workflow 100 的 results 有 2 筆：
        | node_id | code | next_node_id |
        | n1      | 200  | n2           |
        | n2      | 200  |              |
      When 系統呼叫 WorkflowDTO::try_execute()
      Then Workflow 100 的狀態應設為 "completed"

  Rule: 前置（參數）- 必要參數必須提供

    Example: WorkflowResultDTO 的 next_node_id 欄位為可選
      Given 一個不帶 next_node_id 的 WorkflowResultDTO
      Then next_node_id 應預設為空字串或 null
