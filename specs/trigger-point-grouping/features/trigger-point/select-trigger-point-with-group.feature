@ignore @command
Feature: 以分組方式選擇觸發點

  Background:
    Given 系統中有以下分組觸發點清單：
      | group            | group_label | hook                             | name                    | disabled |
      | registration     | 報名狀態    | pf/trigger/registration_approved | 用戶報名審核通過後       | false    |
      | line_interaction | LINE 互動   | pf/trigger/line_followed         | 用戶關注 LINE 官方帳號後 | false    |
      | line_group       | LINE 群組   | pf/trigger/line_join             | Bot 被加入群組後（即將推出） | true |

  Rule: 後置（狀態）- 選擇非 disabled 的觸發點後應正確儲存

    Example: 在 WorkflowRule 編輯頁面選擇觸發點後值正確儲存
      Given 管理員 "Alice" 開啟 WorkflowRule 編輯頁面
      When 管理員 "Alice" 在觸發條件 Select 中選擇 "pf/trigger/registration_approved"
      Then 操作成功
      And WorkflowRule 的 trigger_point 值應為 "pf/trigger/registration_approved"

  Rule: 前置（狀態）- disabled 的觸發點必須不可選擇

    Example: 選擇 disabled 的枚舉存根時操作失敗
      Given 管理員 "Alice" 開啟 WorkflowRule 編輯頁面
      When 管理員 "Alice" 嘗試選擇 disabled 的觸發點 "pf/trigger/line_join"
      Then 操作失敗，錯誤為「該觸發點尚未啟用」

  Rule: 後置（狀態）- 已儲存的觸發點值應正確回顯

    Example: 重新開啟已儲存的 WorkflowRule 後觸發點正確回顯
      Given WorkflowRule 1 的 trigger_point 為 "pf/trigger/registration_approved"
      When 管理員 "Alice" 開啟 WorkflowRule 1 的編輯頁面
      Then 操作成功
      And 觸發條件 Select 的選中值應為 "pf/trigger/registration_approved"
      And 選中值的顯示文字應為 "用戶報名審核通過後"

  Rule: 前置（參數）- 必要參數必須提供

    Scenario Outline: 缺少 <缺少參數> 時操作失敗
      Given 管理員 "Alice" 開啟 WorkflowRule 編輯頁面
      When 管理員 "Alice" 在觸發條件 Select 中選擇 <trigger_point>
      Then 操作失敗，錯誤為「必要參數未提供」

      Examples:
        | 缺少參數       | trigger_point |
        | trigger_point  |               |
