@ignore @query
Feature: Workflow 列表頁篩選觸發點

  Background:
    Given 系統中有以下分組觸發點清單：
      | group            | group_label | hook                             | name                    | disabled |
      | registration     | 報名狀態    | pf/trigger/registration_approved | 用戶報名審核通過後       | false    |
      | line_interaction | LINE 互動   | pf/trigger/line_followed         | 用戶關注 LINE 官方帳號後 | false    |
    And 系統中有以下 Workflow 執行紀錄：
      | workflowId | triggerPoint                     | status    |
      | 1          | pf/trigger/registration_approved | completed |
      | 2          | pf/trigger/line_followed         | running   |
      | 3          | pf/trigger/registration_approved | failed    |

  Rule: 後置（回應）- 篩選器 Select 應以 OptGroup 分組顯示且支援搜尋

    Example: Workflow 列表頁的觸發點篩選器以分組方式呈現
      When 管理員 "Alice" 開啟 Workflow 列表頁面
      Then 觸發點篩選器 Select 應以 OptGroup 分組顯示
      And 觸發點篩選器 Select 應支援搜尋功能

  Rule: 後置（回應）- 選擇篩選條件後應正確篩選列表

    Example: 選擇觸發點篩選後僅顯示匹配的 Workflow
      When 管理員 "Alice" 在 Workflow 列表頁面選擇觸發點篩選為 "pf/trigger/registration_approved"
      Then 操作成功
      And 列表應顯示以下 Workflow：
        | workflowId | triggerPoint                     | status    |
        | 1          | pf/trigger/registration_approved | completed |
        | 3          | pf/trigger/registration_approved | failed    |

  Rule: 後置（回應）- 清除篩選後應顯示所有 Workflow

    Example: 清除觸發點篩選後顯示全部 Workflow
      Given 管理員 "Alice" 已選擇觸發點篩選為 "pf/trigger/registration_approved"
      When 管理員 "Alice" 清除觸發點篩選
      Then 操作成功
      And 列表應顯示以下 Workflow：
        | workflowId | triggerPoint                     | status    |
        | 1          | pf/trigger/registration_approved | completed |
        | 2          | pf/trigger/line_followed         | running   |
        | 3          | pf/trigger/registration_approved | failed    |

  Rule: 前置（參數）- 必要參數必須提供

    Example: 未選擇篩選條件時顯示所有 Workflow
      When 管理員 "Alice" 開啟 Workflow 列表頁面
      Then 操作成功
      And 列表應顯示所有 Workflow
