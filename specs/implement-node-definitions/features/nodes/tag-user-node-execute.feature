@ignore @command
Feature: TagUserNode 新增/移除用戶標籤

  TagUserNode 操作用戶的標籤（user_meta: pf_user_tags）。
  支援 add（新增）與 remove（移除）兩種動作。
  新增標籤後觸發 TriggerPointService::fire_user_tagged()。
  tags 欄位型別為 tags_input（純字串陣列）。

  Background:
    Given 系統已註冊以下 NodeDefinition：
      | id       | name     | type   |
      | tag_user | 標籤用戶 | action |
    And 系統中有以下 Workflow（status=running）：
      | id  | context                                       |
      | 100 | {"line_user_id":"U123","identity_id":"alice"} |
    And Workflow 100 有以下節點：
      | id | node_definition_id | params                                      |
      | n1 | tag_user           | {"tags":["vip","premium"],"action":"add"} |

  Rule: 後置（狀態）- action=add 時新增標籤到 user_meta

    Example: 新增標籤到無標籤的用戶
      Given 用戶 "U123" 的 pf_user_tags 為 []
      When 系統執行節點 "n1"（TagUserNode）
      Then 用戶 "U123" 的 pf_user_tags 應為 ["vip","premium"]
      And 結果的 code 應為 200
      And 結果的 message 應包含 "標籤新增成功"

    Example: 新增標籤到已有標籤的用戶（不重複）
      Given 用戶 "U123" 的 pf_user_tags 為 ["existing","vip"]
      When 系統執行節點 "n1"（TagUserNode）
      Then 用戶 "U123" 的 pf_user_tags 應為 ["existing","vip","premium"]
      And 不應包含重複的 "vip"

  Rule: 後置（副作用）- action=add 時對每個新標籤觸發 fire_user_tagged

    Example: 新增 2 個標籤應觸發 2 次 fire_user_tagged
      Given 用戶 "U123" 的 pf_user_tags 為 []
      When 系統執行節點 "n1"（TagUserNode）
      Then 應呼叫 TriggerPointService::fire_user_tagged("U123", "vip")
      And 應呼叫 TriggerPointService::fire_user_tagged("U123", "premium")

    Example: 標籤已存在時不重複觸發
      Given 用戶 "U123" 的 pf_user_tags 為 ["vip"]
      When 系統執行節點 "n1"（TagUserNode）
      Then 應呼叫 TriggerPointService::fire_user_tagged("U123", "premium")
      And 不應呼叫 TriggerPointService::fire_user_tagged("U123", "vip")

  Rule: 後置（狀態）- action=remove 時移除標籤

    Example: 移除用戶的標籤
      Given 節點 "n1" 的 action 為 "remove"
      And 用戶 "U123" 的 pf_user_tags 為 ["vip","premium","regular"]
      When 系統執行節點 "n1"（TagUserNode）
      Then 用戶 "U123" 的 pf_user_tags 應為 ["regular"]
      And 結果的 code 應為 200
      And 結果的 message 應包含 "標籤移除成功"

    Example: 移除不存在的標籤不報錯
      Given 節點 "n1" 的 action 為 "remove"
      And 用戶 "U123" 的 pf_user_tags 為 ["other"]
      When 系統執行節點 "n1"（TagUserNode）
      Then 用戶 "U123" 的 pf_user_tags 應為 ["other"]
      And 結果的 code 應為 200

  Rule: 後置（副作用）- action=remove 時不觸發 fire_user_tagged

    Example: 移除標籤不觸發事件
      Given 節點 "n1" 的 action 為 "remove"
      And 用戶 "U123" 的 pf_user_tags 為 ["vip","premium"]
      When 系統執行節點 "n1"（TagUserNode）
      Then 不應呼叫 TriggerPointService::fire_user_tagged

  Rule: 前置（參數）- tags 必須為非空陣列

    Example: tags 為空陣列時失敗
      Given 節點 "n1" 的 params 中 tags 為 []
      When 系統執行節點 "n1"（TagUserNode）
      Then 結果的 code 應為 500
      And 結果的 message 應包含 "tags"

  Rule: 前置（參數）- action 必須為 add 或 remove

    Example: action 為無效值時失敗
      Given 節點 "n1" 的 params 中 action 為 "invalid"
      When 系統執行節點 "n1"（TagUserNode）
      Then 結果的 code 應為 500
      And 結果的 message 應包含 "action"

  Rule: 前置（參數）- context 中必須有可識別的 user_id

    Example: context 中無 line_user_id 時失敗
      Given Workflow 100 的 context 為 {"identity_id":"alice"}（無 line_user_id）
      When 系統執行節點 "n1"（TagUserNode）
      Then 結果的 code 應為 500
      And 結果的 message 應包含 "user_id"
