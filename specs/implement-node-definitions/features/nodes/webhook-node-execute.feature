@ignore @command
Feature: WebhookNode 發送 HTTP Webhook

  WebhookNode 使用 wp_remote_request() 發送 HTTP 請求到指定 URL。
  支援 GET / POST / PUT / DELETE 方法，可自訂 headers 與 body。
  body_tpl 支援 {{variable}} 模板替換。

  Background:
    Given 系統已註冊以下 NodeDefinition：
      | id      | name            | type         |
      | webhook | 發送 Webhook 通知 | send_message |
    And 系統中有以下 Workflow（status=running）：
      | id  | context                                                    |
      | 100 | {"identity_id":"alice","order_id":"1001","order_total":"2500"} |
    And Workflow 100 有以下節點：
      | id | node_definition_id | params |
      | n1 | webhook            | {"url":"https://example.com/webhook","method":"POST","headers":"{\"Content-Type\":\"application/json\"}","body_tpl":"{\"user\":\"{{identity_id}}\",\"order\":\"{{order_id}}\"}"} |

  Rule: 後置（狀態）- HTTP 2xx 回應時回傳 code 200

    Example: POST 請求回傳 200 OK
      Given wp_remote_request() 模擬回傳 HTTP status 200
      When 系統執行節點 "n1"（WebhookNode）
      Then 應呼叫 wp_remote_request("https://example.com/webhook", ...)
      And 請求的 method 應為 "POST"
      And 請求的 headers 應包含 "Content-Type: application/json"
      And 請求的 body 應為 '{"user":"alice","order":"1001"}'
      And 結果的 code 應為 200
      And 結果的 message 應包含 "Webhook 發送成功"

    Example: POST 請求回傳 201 Created
      Given wp_remote_request() 模擬回傳 HTTP status 201
      When 系統執行節點 "n1"（WebhookNode）
      Then 結果的 code 應為 200

  Rule: 後置（狀態）- HTTP 非 2xx 回應時回傳 code 500

    Example: 目標伺服器回傳 500
      Given wp_remote_request() 模擬回傳 HTTP status 500
      When 系統執行節點 "n1"（WebhookNode）
      Then 結果的 code 應為 500
      And 結果的 message 應包含 "HTTP 500"

    Example: 目標伺服器回傳 404
      Given wp_remote_request() 模擬回傳 HTTP status 404
      When 系統執行節點 "n1"（WebhookNode）
      Then 結果的 code 應為 500
      And 結果的 message 應包含 "HTTP 404"

  Rule: 後置（狀態）- wp_remote_request 回傳 WP_Error 時回傳 code 500

    Example: 網路連線失敗
      Given wp_remote_request() 回傳 WP_Error("http_request_failed", "cURL error 28: Connection timed out")
      When 系統執行節點 "n1"（WebhookNode）
      Then 結果的 code 應為 500
      And 結果的 message 應包含 "Connection timed out"

  Rule: 前置（參數）- url 必須提供

    Example: url 為空時失敗
      Given 節點 "n1" 的 params 中 url 為 ""
      When 系統執行節點 "n1"（WebhookNode）
      Then 結果的 code 應為 500
      And 結果的 message 應包含 "url"

  Rule: 後置（狀態）- headers 為空時使用空陣列

    Example: 不提供 headers 時正常發送
      Given 節點 "n1" 的 params 中 headers 為 ""
      When 系統執行節點 "n1"（WebhookNode）
      Then 請求的 headers 應為空陣列
      And 結果的 code 應為 200

  Rule: 後置（狀態）- body_tpl 為空時 body 為空字串

    Example: GET 請求不帶 body
      Given 節點 "n1" 的 method 為 "GET"，body_tpl 為 ""
      When 系統執行節點 "n1"（WebhookNode）
      Then 請求的 body 應為空字串
      And 結果的 code 應為 200

  Rule: 後置（狀態）- headers JSON 格式錯誤時回傳 code 500

    Example: headers 不是合法 JSON
      Given 節點 "n1" 的 params 中 headers 為 "not-json"
      When 系統執行節點 "n1"（WebhookNode）
      Then 結果的 code 應為 500
      And 結果的 message 應包含 "headers"
