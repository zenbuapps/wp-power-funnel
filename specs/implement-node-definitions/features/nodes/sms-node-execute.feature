@ignore @command
Feature: SmsNode 傳送 SMS 簡訊

  SmsNode 透過 WordPress filter 委派 SMS 發送。
  使用 apply_filters('power_funnel/sms/send', $default, $recipient, $content) 呼叫外部 SMS 服務。
  filter 回傳統一結構 array{success: bool, message: string}。

  Background:
    Given 系統已註冊以下 NodeDefinition：
      | id  | name     | type         |
      | sms | 傳送 SMS | send_message |
    And 系統中有以下 Workflow（status=running）：
      | id  | context                                                   |
      | 100 | {"identity_id":"alice","billing_phone":"+886912345678"}   |
    And Workflow 100 有以下節點：
      | id | node_definition_id | params                                                               |
      | n1 | sms                | {"recipient":"{{billing_phone}}","content_tpl":"{{identity_id}} 您好"} |

  Rule: 後置（狀態）- filter 回傳 success=true 時回傳 code 200

    Example: SMS 發送成功
      Given power_funnel/sms/send filter 已掛載，回傳 {"success":true,"message":"SMS 發送成功"}
      When 系統執行節點 "n1"（SmsNode）
      Then 應呼叫 apply_filters('power_funnel/sms/send', ['success'=>false,'message'=>'SMS 發送失敗'], '+886912345678', 'alice 您好')
      And 結果的 code 應為 200
      And 結果的 message 應為 "SMS 發送成功"

  Rule: 後置（狀態）- filter 回傳 success=false 時回傳 code 500

    Example: SMS 發送失敗（無 filter 掛載，使用預設值）
      Given power_funnel/sms/send filter 未掛載（回傳預設值）
      When 系統執行節點 "n1"（SmsNode）
      Then 結果的 code 應為 500
      And 結果的 message 應為 "SMS 發送失敗"

    Example: SMS 服務回傳失敗
      Given power_funnel/sms/send filter 已掛載，回傳 {"success":false,"message":"餘額不足"}
      When 系統執行節點 "n1"（SmsNode）
      Then 結果的 code 應為 500
      And 結果的 message 應為 "餘額不足"

  Rule: 前置（參數）- recipient 必須提供

    Example: recipient 為空時失敗
      Given 節點 "n1" 的 params 中 recipient 為 ""
      When 系統執行節點 "n1"（SmsNode）
      Then 結果的 code 應為 500
      And 結果的 message 應包含 "recipient"

  Rule: 後置（狀態）- content_tpl 支援 {{variable}} 模板替換

    Example: recipient 也支援模板替換
      Given Workflow 100 的 context 為 {"identity_id":"Bob","billing_phone":"+886999888777"}
      And 節點 "n1" 的 recipient 為 "{{billing_phone}}"，content_tpl 為 "{{identity_id}} 提醒"
      When 系統執行節點 "n1"（SmsNode）
      Then apply_filters 的 recipient 參數應為 "+886999888777"
      And apply_filters 的 content 參數應為 "Bob 提醒"
