@ignore @query
Feature: 查詢 NodeDefinition 列表

  Background:
    Given 系統中有以下已註冊的 NodeDefinition：
      | id    | name       | description | icon                                                                           | type         |
      | email | 傳送 Email | 傳送 Email  | https://example.com/wp-content/plugins/power-funnel/inc/assets/icons/email.svg | send_message |
      | wait  | 等待       | 等待        | https://example.com/wp-content/plugins/power-funnel/inc/assets/icons/wait.svg  | action       |

  Rule: 前置（狀態）- 請求者必須為已登入的管理員

    Example: 未登入的使用者查詢節點定義時操作失敗
      Given 使用者未登入
      When 使用者查詢 GET /wp-json/power-funnel/node-definitions
      Then 操作失敗，錯誤為「未授權的操作」

  Rule: 前置（參數）- 必要參數必須提供

    Example: 請求不帶任何查詢參數也可成功（無必要參數）
      Given 管理員 "Admin" 已登入
      When 管理員 "Admin" 查詢 GET /wp-json/power-funnel/node-definitions
      Then 操作成功

  Rule: 後置（回應）- 回應應包含所有已註冊的 NodeDefinition

    Example: 查詢成功時回傳完整節點定義列表
      Given 管理員 "Admin" 已登入
      When 管理員 "Admin" 查詢 GET /wp-json/power-funnel/node-definitions
      Then 操作成功
      And 查詢結果應包含：
        | id    | name       | description | icon                                                                           | type         |
        | email | 傳送 Email | 傳送 Email  | https://example.com/wp-content/plugins/power-funnel/inc/assets/icons/email.svg | send_message |
        | wait  | 等待       | 等待        | https://example.com/wp-content/plugins/power-funnel/inc/assets/icons/wait.svg  | action       |

  Rule: 後置（回應）- 每個 NodeDefinition 的 form_fields 應包含擴充後的 FormFieldDTO 屬性

    Example: form_fields 每個欄位包含完整的 FormFieldDTO 屬性
      Given 管理員 "Admin" 已登入
      When 管理員 "Admin" 查詢 GET /wp-json/power-funnel/node-definitions
      Then 操作成功
      And "email" 節點的 form_fields 每個欄位應包含：
        | 屬性          | 類型    | 說明                                                                          |
        | name          | string  | 欄位 key，對應 NodeDTO.args 的 key                                           |
        | label         | string  | 顯示標籤                                                                      |
        | type          | string  | 欄位類型（text/number/select/textarea/template_editor/switch/date/json）      |
        | required      | boolean | 是否必填                                                                      |
        | default_value | mixed   | 預設值                                                                        |
        | placeholder   | string  | placeholder 文字                                                              |
        | description   | string  | 欄位說明（tooltip）                                                           |
        | options       | array   | select 類型的選項 [{value, label}]                                            |
        | validation    | array   | 額外驗證規則 [{rule, value, message}]                                         |
        | sort          | integer | 欄位排序                                                                      |
        | depends_on    | array   | 條件顯示 [{field, operator, value}]                                           |
