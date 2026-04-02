# System

## 描述

工作流引擎核心系統。負責節點執行、Action Scheduler 排程、context 參數解析與替換、workflow 狀態管理。

## 關鍵屬性

- 透過 WorkflowDTO::try_execute() 逐節點執行
- 透過 NodeDTO::try_execute() 處理單一節點的執行結果
- 透過 ParamHelper 處理 context 參數替換（"context" 取值、{{variable}} 模板替換）
- 透過 Action Scheduler 排程延遲節點（WaitNode/WaitUntilNode/TimeWindowNode）
- 透過 as_schedule_single_action() 排程下一節點的立即執行
