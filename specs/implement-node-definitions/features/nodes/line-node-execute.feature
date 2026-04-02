@ignore @command
Feature: LineNode 傳送 LINE 文字訊息

  LineNode 透過 MessageService 發送 LINE 文字訊息給指定用戶。
  使用 ParamHelper 解析 content_tpl 模板，從 workflow context 取得 line_user_id。

  Background:
    Given 系統已註冊以下 NodeDefinition：
      | id   | name           | type         |
      | line | 傳送 LINE 訊息 | send_message |
    And 系統中有以下 Workflow（status=running）：
      | id  | context                                                    |
      | 100 | {"line_user_id":"U1234567890abcdef","identity_id":"alice"} |
    And Workflow 100 有以下節點：
      | id | node_definition_id | params                                    |
      | n1 | line               | {"content_tpl":"{{identity_id}} 您好！歡迎加入"} |

  Rule: 後置（狀態）- 成功發送 LINE 訊息時回傳 code 200

    Example: content_tpl 模板替換後發送成功
      Given MessageService Channel Access Token 已設定
      And MessageService::send_text_message() 模擬回傳成功
      When 系統執行節點 "n1"（LineNode）
      Then 應呼叫 MessageService::getInstance()->send_text_message("U1234567890abcdef", "alice 您好！歡迎加入")
      And 結果的 code 應為 200
      And 結果的 message 應包含 "LINE 訊息發送成功"

  Rule: 前置（參數）- context 中缺少 line_user_id 時應失敗

    Example: workflow context 中無 line_user_id
      Given Workflow 100 的 context 為 {"identity_id":"alice"}（無 line_user_id）
      When 系統執行節點 "n1"（LineNode）
      Then 結果的 code 應為 500
      And 結果的 message 應包含 "line_user_id"

  Rule: 前置（依賴）- Channel Access Token 未設定時應失敗

    Example: MessageService 建構失敗
      Given MessageService Channel Access Token 未設定
      And MessageService::getInstance() 拋出 Exception
      When 系統執行節點 "n1"（LineNode）
      Then 結果的 code 應為 500
      And 結果的 message 應包含 "Channel Access Token"

  Rule: 後置（狀態）- MessageService 拋出例外時回傳 code 500

    Example: LINE API 回傳錯誤
      Given MessageService Channel Access Token 已設定
      And MessageService::send_text_message() 拋出 Exception("LINE API error")
      When 系統執行節點 "n1"（LineNode）
      Then 結果的 code 應為 500
      And 結果的 message 應包含 "LINE API error"

  Rule: 後置（狀態）- content_tpl 支援 {{variable}} 模板替換

    Example: 多個模板變數替換
      Given Workflow 100 的 context 為 {"line_user_id":"U123","identity_id":"Bob","activity_id":"A99"}
      And 節點 "n1" 的 content_tpl 為 "{{identity_id}} 報名活動 {{activity_id}} 成功"
      When 系統執行節點 "n1"（LineNode）
      Then 發送的訊息內容應為 "Bob 報名活動 A99 成功"
