@ignore @command
Feature: 新增 WorkflowRule

  Background:
    Given 管理員已登入後台

  Rule: 前置（狀態）- 管理員必須已登入後台

    Example: 未登入時新增操作失敗
      Given 管理員未登入後台
      When 管理員新增一條 WorkflowRule
      Then 操作失敗，錯誤為「未授權的操作」

  Rule: 前置（參數）- 必要參數必須提供

    Scenario Outline: 缺少 <缺少參數> 時操作失敗
      When 管理員新增一條 WorkflowRule，post_type 為 <post_type>，status 為 <status>
      Then 操作失敗，錯誤為「必要參數未提供」

      Examples:
        | 缺少參數  | post_type         | status |
        | post_type |                   | draft  |
        | status    | pf_workflow_rule  |        |

  Rule: 後置（狀態）- 新建的 WorkflowRule 應為 draft 狀態且標題為空

    Example: 新增 WorkflowRule 後狀態為 draft 並自動跳轉 Edit 頁
      When 管理員新增一條 WorkflowRule
      Then 操作成功
      And 新建的 WorkflowRule 應為：
        | status | title | trigger_point | nodes |
        | draft  |       |               | []    |
      And 頁面自動跳轉至該 WorkflowRule 的 Edit 頁面
