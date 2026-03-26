@ignore @command
Feature: 編輯 WorkflowRule 基本資訊

  Background:
    Given 管理員已登入後台
    And 系統中有以下 WorkflowRule：
      | ruleId | title          | status | trigger_point        |
      | 1      | 報名後通知流程   | draft  | registration_created |

  Rule: 前置（狀態）- WorkflowRule 必須存在

    Example: 編輯不存在的 WorkflowRule 時操作失敗
      Given 系統中無 ruleId 為 999 的 WorkflowRule
      When 管理員編輯 WorkflowRule 999
      Then 操作失敗，錯誤為「WorkflowRule 不存在」

  Rule: 前置（參數）- 必要參數必須提供

    Scenario Outline: 缺少 <缺少參數> 時操作失敗
      When 管理員編輯 WorkflowRule <ruleId>，標題為 <title>
      Then 操作失敗，錯誤為「必要參數未提供」

      Examples:
        | 缺少參數 | ruleId | title          |
        | ruleId   |        | VIP 通知流程    |

  Rule: 後置（狀態）- WorkflowRule 的標題應更新成功

    Example: 更新標題後資料正確
      When 管理員將 WorkflowRule 1 的標題更新為 "VIP 通知流程"
      Then 操作成功
      And WorkflowRule 1 的基本資訊應為：
        | title        | status | trigger_point        |
        | VIP 通知流程  | draft  | registration_created |

  Rule: 後置（狀態）- WorkflowRule 的觸發點應可更新

    Example: 更新觸發點後資料正確
      When 管理員將 WorkflowRule 1 的觸發點更新為 "registration_created"
      Then 操作成功
      And WorkflowRule 1 的觸發點為 "registration_created"

  Rule: 後置（狀態）- WorkflowRule 的狀態應可在 draft 與 publish 之間切換

    Example: 將 draft 狀態切換為 publish
      When 管理員將 WorkflowRule 1 的狀態切換為 "publish"
      Then 操作成功
      And WorkflowRule 1 的狀態為 "publish"

    Example: 將 publish 狀態切換為 draft
      Given 系統中有以下 WorkflowRule：
        | ruleId | title        | status  | trigger_point        |
        | 2      | VIP 歡迎流程  | publish | registration_created |
      When 管理員將 WorkflowRule 2 的狀態切換為 "draft"
      Then 操作成功
      And WorkflowRule 2 的狀態為 "draft"
