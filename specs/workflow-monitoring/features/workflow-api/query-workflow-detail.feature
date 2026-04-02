@ignore @query
Feature: 查詢 Workflow 執行詳情

  Background:
    Given 系統中有以下 WorkflowRule：
      | workflowRuleId | title          | triggerPoint                       |
      | 10             | 報名通知工作流 | pf/trigger/registration_approved   |
    And 系統中有以下 Workflow 實例：
      | workflowId | workflowRuleId | triggerPoint                     | status    | createdAt            |
      | 100        | 10             | pf/trigger/registration_approved | completed | 2026-04-01T09:00:00Z |
    And Workflow 100 的 nodes 為：
      | nodeId | nodeDefinitionId | params                          |
      | n1     | email            | recipient:context, subject:歡迎  |
      | n2     | wait             | delay_seconds:60                 |
      | n3     | line             | message_tpl:感謝報名             |
    And Workflow 100 的 results 為：
      | nodeId | code | message       | executedAt             |
      | n1     | 200  | 發信成功      | 2026-04-01T09:00:05Z   |
      | n2     | 200  | 等待完成      | 2026-04-01T09:01:05Z   |
      | n3     | 200  | LINE 發送成功 | 2026-04-01T09:01:10Z   |
    And Workflow 100 的 context_callable_set 為：
      | callable                                              | params |
      | TriggerPointService::resolve_registration_context     | 123    |
    And Workflow 100 的 resolved context 為：
      | key             | value                    |
      | user_email      | alice@example.com        |
      | user_name       | Alice                    |
      | activity_title  | React 進階工作坊         |

  Rule: 前置（狀態）- 呼叫者必須為已登入的管理員

    Example: 未登入時查詢 Workflow 詳情操作失敗
      When 未登入的訪客查詢 Workflow 100 的執行詳情
      Then 操作失敗，錯誤為「權限不足」

  Rule: 前置（狀態）- 指定的 Workflow 必須存在

    Example: 查詢不存在的 Workflow 時操作失敗
      When 管理員 "Admin" 查詢 Workflow 999 的執行詳情
      Then 操作失敗，錯誤為「Workflow 不存在」

  Rule: 前置（參數）- 必要參數必須提供

    Example: 未提供 Workflow ID 時操作失敗
      When 管理員 "Admin" 查詢 Workflow 的執行詳情但未指定 ID
      Then 操作失敗，錯誤為「必要參數未提供」

  Rule: 後置（回應）- 詳情應包含 Workflow 基本資訊

    Example: 查詢成功後回傳 Workflow 基本資訊
      When 管理員 "Admin" 查詢 Workflow 100 的執行詳情
      Then 操作成功
      And 查詢結果的基本資訊應為：
        | workflowId | workflowRuleId | workflowRuleTitle | triggerPoint                     | status    | createdAt              |
        | 100        | 10             | 報名通知工作流    | pf/trigger/registration_approved | completed | 2026-04-01T09:00:00Z   |

  Rule: 後置（回應）- 詳情應包含每個節點的定義與執行結果

    Example: 查詢成功後回傳節點清單與結果
      When 管理員 "Admin" 查詢 Workflow 100 的執行詳情
      Then 操作成功
      And 查詢結果的 nodes 應為：
        | nodeId | nodeDefinitionId | resultCode | resultMessage | executedAt             |
        | n1     | email            | 200        | 發信成功      | 2026-04-01T09:00:05Z   |
        | n2     | wait             | 200        | 等待完成      | 2026-04-01T09:01:05Z   |
        | n3     | line             | 200        | LINE 發送成功 | 2026-04-01T09:01:10Z   |

  Rule: 後置（回應）- 詳情應包含 resolved context

    Example: 查詢成功後回傳 resolved context
      When 管理員 "Admin" 查詢 Workflow 100 的執行詳情
      Then 操作成功
      And 查詢結果的 context 應為：
        | key            | value                |
        | user_email     | alice@example.com    |
        | user_name      | Alice                |
        | activity_title | React 進階工作坊     |

  Rule: 後置（回應）- 詳情應包含人類可讀的 context_callable_set

    Example: 查詢成功後回傳精簡的 context_callable_set
      When 管理員 "Admin" 查詢 Workflow 100 的執行詳情
      Then 操作成功
      And 查詢結果的 contextCallableSet 應為：
        | callable                                          | params |
        | TriggerPointService::resolve_registration_context | 123    |

  Rule: 後置（回應）- 詳情應包含計算出的耗時資訊

    Example: 查詢成功後回傳 startedAt、completedAt、duration
      When 管理員 "Admin" 查詢 Workflow 100 的執行詳情
      Then 操作成功
      And 查詢結果的時間資訊應為：
        | startedAt              | completedAt            | duration |
        | 2026-04-01T09:00:05Z   | 2026-04-01T09:01:10Z   | 65s      |
