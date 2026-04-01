@ignore @query
Feature: YesNoBranchNode 表單欄位定義

  Background:
    Given 系統已註冊以下 NodeDefinition：
      | id             | name      | type   |
      | yes_no_branch  | 是/否分支  | action |

  Rule: 後置（回應）- form_fields 應包含條件欄位、運算子、條件值、是分支目標、否分支目標

    Example: YesNoBranchNode 的 form_fields 包含 5 個欄位
      When 系統讀取 YesNoBranchNode 的 form_fields
      Then form_fields 應包含：
        | name              | label          | type   | required |
        | condition_field   | 條件欄位        | select | true     |
        | operator          | 運算子          | select | true     |
        | condition_value   | 條件值          | text   | true     |
        | yes_next_node_id  | 是分支目標節點   | text   | true     |
        | no_next_node_id   | 否分支目標節點   | text   | true     |

  Rule: 後置（回應）- condition_field 的選項應從觸發點 context keys API 動態帶入

    Example: condition_field 為 select 類型且 options 從 API 動態載入
      When 系統讀取 YesNoBranchNode 的 form_fields["condition_field"]
      Then type 應為 "select"
      And options 應透過觸發點 context keys API 動態取得

  Rule: 後置（回應）- operator 選項應包含所有支援的運算子

    Example: operator 的 options 包含 10 種運算子
      When 系統讀取 YesNoBranchNode 的 form_fields["operator"]
      Then options 應包含：
        | value         | label    |
        | gt            | 大於      |
        | gte           | 大於等於  |
        | lt            | 小於      |
        | lte           | 小於等於  |
        | equals        | 等於      |
        | not_equals    | 不等於    |
        | contains      | 包含      |
        | not_contains  | 不包含    |
        | is_empty      | 為空      |
        | is_not_empty  | 不為空    |

  Rule: 前置（參數）- 必要參數必須提供

    Scenario Outline: 缺少 <缺少參數> 時節點定義驗證失敗
      Given YesNoBranchNode 的 form_fields 設定中 <缺少參數> 為空
      Then 該欄位的 required 應為 true

      Examples:
        | 缺少參數          |
        | condition_field   |
        | operator          |
        | condition_value   |
        | yes_next_node_id  |
        | no_next_node_id   |
