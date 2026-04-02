@ignore @command
Feature: 配置 WorkflowRule 的 React Flow 節點

  Background:
    Given 管理員已登入後台
    And 系統中有以下 WorkflowRule：
      | ruleId | title          | status | trigger_point        |
      | 1      | 報名後通知流程   | draft  | registration_created |

  Rule: 後置（狀態）- 新建 WorkflowRule 的畫布應有 Entrance 和 Exit 初始節點

    Example: 開啟新建 WorkflowRule 的 Edit 頁時畫布有初始節點
      Given WorkflowRule 1 的節點為空
      When 管理員開啟 WorkflowRule 1 的編輯頁面
      Then 畫布應顯示以下節點：
        | nodeType  | label |
        | entrance  | 觸發  |
        | exit      | 結束  |
      And Entrance 節點與 Exit 節點之間有一條 Edge

  Rule: 前置（參數）- 新增的節點類型必須為有效的 ENode 值

    Example: 新增無效節點類型時操作失敗
      Given WorkflowRule 1 的畫布有 Entrance 和 Exit 節點
      When 管理員在 Edge 上點擊 + 按鈕並選擇節點類型 "invalid_type"
      Then 操作失敗，錯誤為「無效的節點類型」

  Rule: 後置（狀態）- 透過 Edge 上的 + 按鈕新增節點後應插入在兩節點之間

    Example: 在 Entrance 和 Exit 之間新增一個 EMAIL 節點
      Given WorkflowRule 1 的畫布有以下節點：
        | nodeType  | label |
        | entrance  | 觸發  |
        | exit      | 結束  |
      When 管理員在 Entrance 與 Exit 之間的 Edge 上點擊 + 按鈕並選擇 "email"
      Then 畫布應顯示以下節點（由上至下）：
        | nodeType  | label |
        | entrance  | 觸發  |
        | email     | Email |
        | exit      | 結束  |
      And Dagre 自動佈局重新排列節點（垂直 TB 方向）

    Example: 在已有節點之間再新增一個 WAIT 節點
      Given WorkflowRule 1 的畫布有以下節點（由上至下）：
        | nodeType  | label |
        | entrance  | 觸發  |
        | email     | Email |
        | exit      | 結束  |
      When 管理員在 Email 與 Exit 之間的 Edge 上點擊 + 按鈕並選擇 "wait"
      Then 畫布應顯示以下節點（由上至下）：
        | nodeType  | label |
        | entrance  | 觸發  |
        | email     | Email |
        | wait      | Wait  |
        | exit      | 結束  |

  Rule: 後置（狀態）- 點擊節點應開啟右側抽屜顯示設定表單

    Example: 點擊 EMAIL 節點後開啟右側抽屜
      Given WorkflowRule 1 的畫布有一個 EMAIL 節點
      When 管理員點擊 EMAIL 節點
      Then 右側抽屜 (Drawer) 開啟
      And 抽屜中顯示 EMAIL 節點的設定表單

  Rule: 後置（狀態）- 節點類型選單應包含所有可用的節點類型

    Example: Edge 上的 + 按鈕彈出完整的節點類型選單
      Given WorkflowRule 1 的畫布有 Entrance 和 Exit 節點
      When 管理員點擊 Edge 上的 + 按鈕
      Then 節點類型選單應包含：
        | nodeType       | label         |
        | email          | Email         |
        | sms            | SMS           |
        | line           | LINE          |
        | webhook        | Webhook       |
        | wait           | Wait          |
        | wait_until     | Wait Until    |
        | time_window    | Time Window   |
        | yes_no_branch  | Yes/No Branch |
        | tag_user       | Tag User      |

  Rule: 後置（狀態）- 刪除節點後應從畫布移除且相鄰節點自動重新連線

    Example: 刪除 EMAIL 節點後畫布更新
      Given WorkflowRule 1 的畫布有以下節點（由上至下）：
        | nodeType  | label |
        | entrance  | 觸發  |
        | email     | Email |
        | exit      | 結束  |
      When 管理員在右側抽屜中刪除 EMAIL 節點
      Then 畫布應顯示以下節點（由上至下）：
        | nodeType  | label |
        | entrance  | 觸發  |
        | exit      | 結束  |
      And Entrance 節點與 Exit 節點之間有一條 Edge

    Example: 不可刪除 Entrance 節點
      Given WorkflowRule 1 的畫布有 Entrance 和 Exit 節點
      When 管理員嘗試刪除 Entrance 節點
      Then 操作失敗，錯誤為「無法刪除觸發節點」

    Example: 不可刪除 Exit 節點
      Given WorkflowRule 1 的畫布有 Entrance 和 Exit 節點
      When 管理員嘗試刪除 Exit 節點
      Then 操作失敗，錯誤為「無法刪除結束節點」
