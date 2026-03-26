@ignore @command
Feature: 儲存 WorkflowRule

  Background:
    Given 管理員已登入後台
    And 系統中有以下 WorkflowRule：
      | ruleId | title          | status | trigger_point        |
      | 1      | 報名後通知流程   | draft  | registration_created |

  Rule: 前置（狀態）- WorkflowRule 必須存在

    Example: 儲存不存在的 WorkflowRule 時操作失敗
      Given 系統中無 ruleId 為 999 的 WorkflowRule
      When 管理員儲存 WorkflowRule 999
      Then 操作失敗，錯誤為「WorkflowRule 不存在」

  Rule: 前置（參數）- 必要參數必須提供

    Scenario Outline: 缺少 <缺少參數> 時操作失敗
      When 管理員儲存 WorkflowRule <ruleId>，標題為 <title>
      Then 操作失敗，錯誤為「必要參數未提供」

      Examples:
        | 缺少參數 | ruleId | title          |
        | ruleId   |        | 報名後通知流程   |

  Rule: 後置（狀態）- 手動點擊儲存按鈕後基本資訊與節點資料應一併寫入

    Example: 儲存含基本資訊與節點的 WorkflowRule 後資料正確
      Given WorkflowRule 1 的畫布有以下節點（由上至下）：
        | nodeType  | label |
        | entrance  | 觸發  |
        | email     | Email |
        | wait      | Wait  |
        | exit      | 結束  |
      And 管理員已將標題修改為 "更新後的通知流程"
      And 管理員已將觸發點修改為 "registration_created"
      When 管理員點擊儲存按鈕（useForm onFinish）
      Then 操作成功
      And WorkflowRule 1 的基本資訊應為：
        | title            | status | trigger_point        |
        | 更新後的通知流程    | draft  | registration_created |
      And WorkflowRule 1 的 nodes meta 應包含 3 個節點（不含 entrance/exit 的虛擬節點，或含，取決於後端 NodeDTO 結構）

  Rule: 後置（狀態）- 儲存後頁面應保持在 Edit 頁不跳轉

    Example: 儲存成功後保持在 Edit 頁
      When 管理員點擊儲存按鈕（useForm onFinish）
      Then 操作成功
      And 頁面保持在 WorkflowRule 1 的 Edit 頁面
      And 顯示儲存成功提示訊息
