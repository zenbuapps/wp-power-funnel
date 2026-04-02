@ignore @query
Feature: 查詢 Workflow 執行清單

  Background:
    Given 系統中有以下用戶：
      | userId | displayName |
      | 5      | Alice       |
      | 99     | DeletedUser |
    And 用戶 99 已被刪除
    And 系統中有以下 WorkflowRule：
      | workflowRuleId | title          | triggerPoint                       |
      | 10             | 報名通知工作流 | pf/trigger/registration_approved   |
      | 20             | 活動提醒工作流 | pf/trigger/activity_before_start   |
    And 系統中有以下 Workflow 實例：
      | workflowId | workflowRuleId | triggerPoint                       | status    | postAuthor | createdAt              |
      | 100        | 10             | pf/trigger/registration_approved   | completed | 5          | 2026-04-01T09:00:00Z   |
      | 101        | 10             | pf/trigger/registration_approved   | failed    | 99         | 2026-04-01T09:30:00Z   |
      | 102        | 20             | pf/trigger/activity_before_start   | running   | 0          | 2026-04-01T10:00:00Z   |
    And Workflow 100 的 results 為：
      | nodeId | code | message      | executedAt             |
      | n1     | 200  | 發信成功     | 2026-04-01T09:00:05Z   |
      | n2     | 200  | 等待完成     | 2026-04-01T09:01:05Z   |
      | n3     | 200  | LINE 發送成功| 2026-04-01T09:01:10Z   |
    And Workflow 101 的 results 為：
      | nodeId | code | message              | executedAt             |
      | n1     | 200  | 發信成功             | 2026-04-01T09:30:05Z   |
      | n2     | 500  | LINE API token 過期  | 2026-04-01T09:30:10Z   |

  Rule: 前置（狀態）- 呼叫者必須為已登入的管理員

    Example: 未登入時查詢 Workflow 清單操作失敗
      When 未登入的訪客查詢 Workflow 執行清單
      Then 操作失敗，錯誤為「權限不足」

  Rule: 前置（參數）- 必要參數必須提供

    Scenario Outline: 分頁參數無效時操作失敗
      When 管理員 "Admin" 查詢 Workflow 執行清單，per_page 為 <per_page>，page 為 <page>
      Then 操作失敗，錯誤為「分頁參數無效」

      Examples:
        | per_page | page |
        | 0        | 1    |
        | 10       | 0    |
        | -1       | 1    |

  Rule: 前置（參數）- status 篩選值必須為有效的 Workflow 狀態

    Example: 傳入無效 status 時操作失敗
      When 管理員 "Admin" 查詢 Workflow 執行清單，篩選 status 為 "invalid_status"
      Then 操作失敗，錯誤為「無效的 status 篩選值」

  Rule: 後置（回應）- 清單應包含 Workflow 摘要資訊與分頁 meta

    Example: 查詢全部 Workflow 後回傳摘要清單含用戶資訊
      When 管理員 "Admin" 查詢 Workflow 執行清單，per_page 為 10，page 為 1
      Then 操作成功
      And 查詢結果應包含：
        | workflowId | workflowRuleTitle | triggerPoint                       | status    | nodeProgress | duration | userId | userDisplayName |
        | 102        | 活動提醒工作流    | pf/trigger/activity_before_start   | running   | 0/3          |          | 0      | 訪客            |
        | 101        | 報名通知工作流    | pf/trigger/registration_approved   | failed    | 2/3          | 5s       | 99     | 訪客            |
        | 100        | 報名通知工作流    | pf/trigger/registration_approved   | completed | 3/3          | 70s      | 5      | Alice           |
      And 分頁資訊應為：
        | total | totalPages | currentPage | perPage |
        | 3     | 1          | 1           | 10      |

  Rule: 後置（回應）- 觸發用戶為已登入用戶時應顯示 display_name

    Example: 已登入用戶觸發的 Workflow 應顯示用戶名稱
      When 管理員 "Admin" 查詢 Workflow 執行清單，per_page 為 10，page 為 1
      Then 操作成功
      And Workflow 100 的用戶資訊應為：
        | userId | userDisplayName |
        | 5      | Alice           |

  Rule: 後置（回應）- post_author 為 0 時應顯示「訪客」

    Example: Action Scheduler 自動觸發的 Workflow 應顯示訪客
      When 管理員 "Admin" 查詢 Workflow 執行清單，per_page 為 10，page 為 1
      Then 操作成功
      And Workflow 102 的用戶資訊應為：
        | userId | userDisplayName |
        | 0      | 訪客            |

  Rule: 後置（回應）- 觸發用戶已被刪除時應顯示「訪客」

    Example: 觸發用戶已被刪除的 Workflow 應顯示訪客
      When 管理員 "Admin" 查詢 Workflow 執行清單，per_page 為 10，page 為 1
      Then 操作成功
      And Workflow 101 的用戶資訊應為：
        | userId | userDisplayName |
        | 99     | 訪客            |

  Rule: 後置（回應）- 按 status 篩選應僅回傳符合條件的 Workflow

    Example: 篩選 status 為 failed 後僅回傳失敗的 Workflow
      When 管理員 "Admin" 查詢 Workflow 執行清單，篩選 status 為 "failed"
      Then 操作成功
      And 查詢結果應包含：
        | workflowId | status |
        | 101        | failed |

  Rule: 後置（回應）- 按 workflow_rule_id 篩選應僅回傳指定規則的 Workflow

    Example: 篩選 workflow_rule_id 為 20 後僅回傳該規則的 Workflow
      When 管理員 "Admin" 查詢 Workflow 執行清單，篩選 workflow_rule_id 為 20
      Then 操作成功
      And 查詢結果應包含：
        | workflowId | workflowRuleId |
        | 102        | 20             |

  Rule: 後置（回應）- 按 trigger_point 篩選應僅回傳指定觸發點的 Workflow

    Example: 篩選 trigger_point 後僅回傳匹配的 Workflow
      When 管理員 "Admin" 查詢 Workflow 執行清單，篩選 trigger_point 為 "pf/trigger/activity_before_start"
      Then 操作成功
      And 查詢結果應包含：
        | workflowId | triggerPoint                     |
        | 102        | pf/trigger/activity_before_start |

  Rule: 後置（回應）- 關鍵字搜尋應模糊搜尋 results message

    Example: 搜尋 "token" 後回傳包含該關鍵字的 Workflow
      When 管理員 "Admin" 查詢 Workflow 執行清單，搜尋關鍵字為 "token"
      Then 操作成功
      And 查詢結果應包含：
        | workflowId |
        | 101        |

  Rule: 後置（回應）- 無符合條件的 Workflow 時應回傳空陣列

    Example: 篩選條件無匹配結果時回傳空清單
      When 管理員 "Admin" 查詢 Workflow 執行清單，篩選 workflow_rule_id 為 999
      Then 操作成功
      And 查詢結果應包含：
        | workflowId |
