@ignore @command
Feature: 刪除 WorkflowRule

  Background:
    Given 管理員已登入後台
    And 系統中有以下 WorkflowRule：
      | ruleId | title          | status  |
      | 1      | 報名後通知流程   | draft   |
      | 2      | VIP 歡迎流程    | publish |

  Rule: 前置（參數）- 必要參數必須提供

    Scenario Outline: 缺少 <缺少參數> 時操作失敗
      When 管理員刪除 WorkflowRule <ruleId>
      Then 操作失敗，錯誤為「必要參數未提供」

      Examples:
        | 缺少參數 | ruleId |
        | ruleId   |        |

  Rule: 前置（狀態）- WorkflowRule 必須存在

    Example: 刪除不存在的 WorkflowRule 時操作失敗
      Given 系統中無 ruleId 為 999 的 WorkflowRule
      When 管理員刪除 WorkflowRule 999
      Then 操作失敗，錯誤為「WorkflowRule 不存在」

  Rule: 後置（狀態）- 任何狀態的 WorkflowRule 皆可直接刪除

    Example: 刪除 draft 狀態的 WorkflowRule 後不再出現在列表中
      When 管理員在 Popconfirm 中確認刪除 WorkflowRule 1
      Then 操作成功
      And WorkflowRule 1 不再出現在列表中

    Example: 刪除 publish 狀態的 WorkflowRule 後不再出現在列表中
      When 管理員在 Popconfirm 中確認刪除 WorkflowRule 2
      Then 操作成功
      And WorkflowRule 2 不再出現在列表中

  Rule: 前置（狀態）- 刪除前必須經過 Popconfirm 確認

    Example: 管理員取消 Popconfirm 後 WorkflowRule 不被刪除
      When 管理員點擊刪除 WorkflowRule 1 的按鈕
      And 管理員在 Popconfirm 中點擊取消
      Then WorkflowRule 1 仍存在於列表中
