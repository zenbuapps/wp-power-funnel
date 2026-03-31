@ignore @command
Feature: 工作流遞迴防護

  Background:
    Given 系統中有以下工作流規則：
      | workflowRuleId | triggerPoint                    | status  |
      | 401            | pf/trigger/workflow_completed   | publish |
    And 遞迴深度上限為 3

  Rule: 前置（狀態）- 工作流觸發鏈的深度不得超過 3

    Example: 深度未超過上限時正常觸發
      Given 當前觸發鏈深度為 1
      When 工作流 301 完成，系統觸發 "pf/trigger/workflow_completed"
      Then 系統應建立新的 Workflow 實例
      And 新 Workflow 的狀態應為 "running"

    Example: 深度達到上限時建立失敗的 Workflow 實例
      Given 當前觸發鏈深度為 3
      When 工作流 301 完成，系統觸發 "pf/trigger/workflow_completed"
      Then 系統應建立 status=failed 的 Workflow 實例
      And 系統應記錄 error log「工作流遞迴深度超過上限 3」

  Rule: 前置（參數）- 遞迴深度上限必須為正整數

    Example: 遞迴深度上限預設為 3
      Given 系統未自定義遞迴深度上限
      Then 遞迴深度上限應為 3

  Rule: 後置（狀態）- 深度計數應隨觸發鏈遞增

    Example: 第一層觸發時深度為 1
      Given 無正在進行的觸發鏈
      When 系統觸發 "pf/trigger/registration_approved"
      And WorkflowRule 401 建立 Workflow 實例並開始執行
      Then 新 Workflow 的觸發鏈深度應為 1

    Example: 工作流完成後觸發另一個工作流時深度遞增
      Given 工作流 301 的觸發鏈深度為 1
      When 工作流 301 完成，觸發 "pf/trigger/workflow_completed"
      And WorkflowRule 401 建立新的 Workflow 實例
      Then 新 Workflow 的觸發鏈深度應為 2
