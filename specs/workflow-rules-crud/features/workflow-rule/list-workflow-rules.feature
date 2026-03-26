@ignore @query
Feature: 查詢 WorkflowRules 列表

  Background:
    Given 管理員已登入後台
    And 系統中有以下 WorkflowRule：
      | ruleId | title          | status  | trigger_point        | nodes_count | date_created        | date_modified       | modified_by |
      | 1      | 報名後通知流程   | draft   | registration_created | 3           | 2026-03-20 10:00:00 | 2026-03-24 14:30:00 | admin       |
      | 2      | VIP 歡迎流程    | publish | registration_created | 5           | 2026-03-18 09:00:00 | 2026-03-25 11:00:00 | admin       |
      | 3      | 課程提醒流程    | draft   | registration_created | 0           | 2026-03-25 08:00:00 | 2026-03-25 08:00:00 | admin       |

  Rule: 前置（狀態）- 管理員必須已登入後台

    Example: 未登入時查詢列表操作失敗
      Given 管理員未登入後台
      When 管理員查詢 WorkflowRules 列表
      Then 操作失敗，錯誤為「未授權的操作」

  Rule: 前置（參數）- 必要參數必須提供

    Scenario Outline: 缺少 <缺少參數> 時操作失敗
      When 管理員查詢 WorkflowRules 列表，post_type 為 <post_type>
      Then 操作失敗，錯誤為「必要參數未提供」

      Examples:
        | 缺少參數  | post_type |
        | post_type |           |

  Rule: 後置（回應）- 列表應顯示所有 WorkflowRules 的完整欄位

    Example: 查詢所有 WorkflowRules 後應顯示完整列表
      When 管理員查詢 WorkflowRules 列表
      Then 操作成功
      And 查詢結果應包含：
        | title          | trigger_point        | status  | nodes_count | date_created        | date_modified       | modified_by |
        | 報名後通知流程   | registration_created | draft   | 3           | 2026-03-20 10:00:00 | 2026-03-24 14:30:00 | admin       |
        | VIP 歡迎流程    | registration_created | publish | 5           | 2026-03-18 09:00:00 | 2026-03-25 11:00:00 | admin       |
        | 課程提醒流程    | registration_created | draft   | 0           | 2026-03-25 08:00:00 | 2026-03-25 08:00:00 | admin       |

  Rule: 後置（回應）- 列表應支援分頁顯示

    Example: 使用 useTable 內建分頁查詢列表
      Given 系統中有 25 筆 WorkflowRule
      When 管理員查詢 WorkflowRules 列表，頁碼為 1，每頁 10 筆
      Then 操作成功
      And 查詢結果應有 10 筆資料
      And 分頁資訊應為：
        | current | pageSize | total |
        | 1       | 10       | 25    |
