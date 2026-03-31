@ignore @command
Feature: 觸發 WORKFLOW_COMPLETED 觸發點

  Background:
    Given 系統中有以下工作流實例：
      | workflowId | workflowRuleId | triggerPoint                     | status  |
      | 301        | 401            | pf/trigger/registration_created  | running |

  Rule: 前置（狀態）- Workflow 狀態必須從 running 轉為 completed

    Example: 工作流所有節點執行完成後觸發
      Given 工作流 301 的狀態為 "running"
      And 工作流 301 的所有節點已執行完成
      When 系統將工作流 301 的狀態更新為 "completed"
      Then 系統應觸發 "pf/trigger/workflow_completed"
      And context_callable_set 執行後應產生以下 context：
        | key              | value                           |
        | workflow_id      | 301                             |
        | workflow_rule_id | 401                             |
        | trigger_point    | pf/trigger/registration_created |

  Rule: 前置（狀態）- Workflow 狀態未變更為 completed 時不應觸發

    Example: 工作流狀態從 running 轉為 failed 時不觸發 WORKFLOW_COMPLETED
      Given 工作流 301 的狀態為 "running"
      When 系統將工作流 301 的狀態更新為 "failed"
      Then 系統不應觸發 "pf/trigger/workflow_completed"
