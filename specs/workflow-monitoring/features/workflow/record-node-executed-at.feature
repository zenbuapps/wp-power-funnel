@ignore @command
Feature: 記錄節點執行時間戳

  Background:
    Given 系統中有以下 WorkflowRule：
      | workflowRuleId | title          | status  | triggerPoint                       |
      | 10             | 報名通知工作流 | publish | pf/trigger/registration_approved   |
    And WorkflowRule 10 包含以下節點：
      | nodeId | nodeDefinitionId | params                          |
      | n1     | email            | recipient:context, subject:歡迎  |
      | n2     | wait             | delay_seconds:60                 |
      | n3     | line             | message_tpl:感謝報名             |

  Rule: 後置（狀態）- 節點執行成功時 WorkflowResultDTO 應包含 executed_at 時間戳

    Example: 節點執行成功後結果包含 executed_at
      Given 系統觸發 Workflow 實例 100，來源 WorkflowRule 10
      And 當前時間為 "2026-04-01T10:00:00Z"
      When 系統執行 Workflow 100 的節點 "n1"，結果為成功
      Then 操作成功
      And Workflow 100 的 results 應包含：
        | nodeId | code | executedAt             |
        | n1     | 200  | 2026-04-01T10:00:00Z   |

  Rule: 後置（狀態）- 節點被跳過時 WorkflowResultDTO 應包含 executed_at 時間戳

    Example: 節點被跳過後結果包含 executed_at
      Given 系統觸發 Workflow 實例 101，來源 WorkflowRule 10
      And 當前時間為 "2026-04-01T10:05:00Z"
      When 系統執行 Workflow 101 的節點 "n1"，match_callback 不滿足
      Then 操作成功
      And Workflow 101 的 results 應包含：
        | nodeId | code | executedAt             |
        | n1     | 301  | 2026-04-01T10:05:00Z   |

  Rule: 後置（狀態）- 節點執行失敗時 WorkflowResultDTO 應包含 executed_at 時間戳

    Example: 節點執行失敗後結果包含 executed_at
      Given 系統觸發 Workflow 實例 102，來源 WorkflowRule 10
      And 當前時間為 "2026-04-01T10:10:00Z"
      When 系統執行 Workflow 102 的節點 "n1"，執行過程中拋出例外 "LINE API token 過期"
      Then 操作成功
      And Workflow 102 的 results 應包含：
        | nodeId | code | message              | executedAt             |
        | n1     | 500  | LINE API token 過期  | 2026-04-01T10:10:00Z   |

  Rule: 前置（參數）- 必要參數必須提供

    Example: 缺少 node_id 時記錄失敗
      Given 系統觸發 Workflow 實例 103，來源 WorkflowRule 10
      When 系統嘗試記錄一筆不含 node_id 的 WorkflowResultDTO
      Then 操作失敗，錯誤為「node_id 為必要欄位」
