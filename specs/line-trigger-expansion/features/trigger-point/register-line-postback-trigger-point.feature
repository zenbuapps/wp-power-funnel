@ignore @command
Feature: 註冊 LINE_POSTBACK_RECEIVED 觸發點

  Rule: 後置（狀態）- ETriggerPoint enum 應包含 LINE_POSTBACK_RECEIVED case

    Example: ETriggerPoint enum 新增 LINE_POSTBACK_RECEIVED 後可正確取得 hook 值和標籤
      When 系統讀取 ETriggerPoint::LINE_POSTBACK_RECEIVED
      Then 該 enum case 的值應為 "pf/trigger/line_postback_received"
      And 該 enum case 的 label 應為 "收到 LINE Postback 後"

  Rule: 後置（狀態）- TriggerPointService 應監聽 postback type-only hook

    Example: TriggerPointService 註冊 postback webhook 監聽後正確觸發
      Given TriggerPointService 已呼叫 register_hooks
      When WebhookService dispatch "power_funnel/line/webhook/postback" 事件
      Then TriggerPointService::on_line_postback_received 應被呼叫

  Rule: 後置（狀態）- context_keys_map 應包含 LINE_POSTBACK_RECEIVED 的 context keys

    Example: 查詢 LINE_POSTBACK_RECEIVED 的 context keys 後回傳正確欄位清單
      When 系統查詢 "pf/trigger/line_postback_received" 的 context keys
      Then 回傳結果應包含以下 keys：
        | key             | label              |
        | line_user_id    | LINE 用戶 ID       |
        | event_type      | 事件類型           |
        | postback_data   | Postback 原始資料  |
        | postback_action | Postback Action    |

  Rule: 前置（參數）- resolve 方法必須為 Serializable Context Callable 格式

    Example: context_callable_set 的 callable 為靜態方法陣列格式
      Given LINE webhook 設定已完成
      When 系統收到 LINE webhook 事件，類型為 "postback"，來源用戶為 "U1234567890"，postback data 為 '{"action":"register"}'
      Then 系統應觸發 "pf/trigger/line_postback_received"
      And context_callable_set 的 callable 應為陣列格式 [TriggerPointService::class, "resolve_line_postback_context"]
      And context_callable_set 的 params 應為純值陣列
