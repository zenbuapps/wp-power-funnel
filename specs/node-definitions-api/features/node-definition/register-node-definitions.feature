@ignore @command
Feature: 註冊 NodeDefinition

  Background:
    Given 系統已載入 Power Funnel 外掛

  Rule: 後置（狀態）- 系統應註冊全部 10 種預設 NodeDefinition

    Example: 外掛啟動後所有預設節點定義已註冊
      Given 系統已載入 Power Funnel 外掛
      When 系統執行 apply_filters('power_funnel/workflow_rule/node_definitions', [])
      Then 操作成功
      And 回傳的節點定義應包含：
        | id             | name              | type          |
        | email          | 傳送 Email        | send_message  |
        | sms            | 傳送 SMS          | send_message  |
        | line           | 傳送 LINE 訊息    | send_message  |
        | webhook        | 發送 Webhook 通知 | send_message  |
        | wait           | 等待              | action        |
        | wait_until     | 等待至            | action        |
        | time_window    | 等待至時間窗口    | action        |
        | yes_no_branch  | 是/否分支         | action        |
        | split_branch   | 分支              | action        |
        | tag_user       | 標籤用戶          | action        |

  Rule: 後置（狀態）- 每個 NodeDefinition 應包含 form_fields schema

    Example: email 節點的 form_fields 包含收件人、主旨、內文、訊息模板
      Given 系統已載入 Power Funnel 外掛
      When 系統執行 apply_filters('power_funnel/workflow_rule/node_definitions', [])
      Then 操作成功
      And "email" 節點的 form_fields 應包含：
        | name           | label      | type            | required |
        | recipient      | 收件人     | text            | true     |
        | subject_tpl    | 主旨       | text            | true     |
        | content_tpl    | 內文       | template_editor | true     |
        | message_tpl_id | 訊息模板   | select          | false    |

    Example: sms 節點的 form_fields 包含收件人、內文
      Given 系統已載入 Power Funnel 外掛
      When 系統執行 apply_filters('power_funnel/workflow_rule/node_definitions', [])
      Then 操作成功
      And "sms" 節點的 form_fields 應包含：
        | name        | label  | type     | required |
        | recipient   | 收件人 | text     | true     |
        | content_tpl | 內文   | textarea | true     |

    Example: line 節點的 form_fields 包含內文
      Given 系統已載入 Power Funnel 外掛
      When 系統執行 apply_filters('power_funnel/workflow_rule/node_definitions', [])
      Then 操作成功
      And "line" 節點的 form_fields 應包含：
        | name        | label | type            | required |
        | content_tpl | 內文  | template_editor | true     |

    Example: webhook 節點的 form_fields 包含 URL、方法、標頭、內文
      Given 系統已載入 Power Funnel 外掛
      When 系統執行 apply_filters('power_funnel/workflow_rule/node_definitions', [])
      Then 操作成功
      And "webhook" 節點的 form_fields 應包含：
        | name     | label    | type     | required |
        | url      | URL      | text     | true     |
        | method   | HTTP 方法 | select  | true     |
        | headers  | 標頭     | json     | false    |
        | body_tpl | 內文     | textarea | false    |

    Example: wait 節點的 form_fields 包含等待時間、單位
      Given 系統已載入 Power Funnel 外掛
      When 系統執行 apply_filters('power_funnel/workflow_rule/node_definitions', [])
      Then 操作成功
      And "wait" 節點的 form_fields 應包含：
        | name     | label    | type   | required |
        | duration | 等待時間 | number | true     |
        | unit     | 時間單位 | select | true     |

    Example: wait_until 節點的 form_fields 包含目標日期時間
      Given 系統已載入 Power Funnel 外掛
      When 系統執行 apply_filters('power_funnel/workflow_rule/node_definitions', [])
      Then 操作成功
      And "wait_until" 節點的 form_fields 應包含：
        | name     | label        | type | required |
        | datetime | 目標日期時間 | date | true     |

    Example: time_window 節點的 form_fields 包含開始時間、結束時間、時區
      Given 系統已載入 Power Funnel 外掛
      When 系統執行 apply_filters('power_funnel/workflow_rule/node_definitions', [])
      Then 操作成功
      And "time_window" 節點的 form_fields 應包含：
        | name       | label    | type   | required |
        | start_time | 開始時間 | text   | true     |
        | end_time   | 結束時間 | text   | true     |
        | timezone   | 時區     | select | false    |

    Example: yes_no_branch 節點的 form_fields 包含條件欄位、運算子、條件值
      Given 系統已載入 Power Funnel 外掛
      When 系統執行 apply_filters('power_funnel/workflow_rule/node_definitions', [])
      Then 操作成功
      And "yes_no_branch" 節點的 form_fields 應包含：
        | name            | label    | type   | required |
        | condition_field | 條件欄位 | text   | true     |
        | operator        | 運算子   | select | true     |
        | condition_value | 條件值   | text   | true     |

    Example: split_branch 節點的 form_fields 包含分支條件
      Given 系統已載入 Power Funnel 外掛
      When 系統執行 apply_filters('power_funnel/workflow_rule/node_definitions', [])
      Then 操作成功
      And "split_branch" 節點的 form_fields 應包含：
        | name     | label    | type | required |
        | branches | 分支條件 | json | true     |

    Example: tag_user 節點的 form_fields 包含標籤、動作
      Given 系統已載入 Power Funnel 外掛
      When 系統執行 apply_filters('power_funnel/workflow_rule/node_definitions', [])
      Then 操作成功
      And "tag_user" 節點的 form_fields 應包含：
        | name   | label | type   | required |
        | tags   | 標籤  | select | true     |
        | action | 動作  | select | true     |

  Rule: 後置（狀態）- 第三方可透過 filter hook 擴充節點定義

    Example: 第三方外掛透過 filter 新增自訂節點
      Given 系統已載入 Power Funnel 外掛
      And 第三方外掛透過 add_filter 註冊了自訂節點 "custom_node"
      When 系統執行 apply_filters('power_funnel/workflow_rule/node_definitions', [])
      Then 操作成功
      And 回傳的節點定義應包含 id 為 "custom_node" 的節點
