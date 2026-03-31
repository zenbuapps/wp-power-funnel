@ignore @command
Feature: 觸發 REGISTRATION_APPROVED 觸發點

  Background:
    Given 系統中有以下報名紀錄：
      | registrationId | identityId    | identityProvider | activityId | promoLinkId | status  |
      | 101            | U1234567890   | line             | 501        | 201         | pending |

  Rule: 前置（狀態）- 報名狀態必須從非 success 轉為 success

    Example: 報名狀態從 pending 轉為 success 時觸發
      Given 報名 101 的狀態為 "pending"
      When 系統將報名 101 的狀態更新為 "success"
      Then 系統應觸發 "pf/trigger/registration_approved"
      And context_callable_set 執行後應產生以下 context：
        | key               | value         |
        | registration_id   | 101           |
        | identity_id       | U1234567890   |
        | identity_provider | line          |
        | activity_id       | 501           |
        | promo_link_id     | 201           |

  Rule: 前置（狀態）- 報名狀態未變更為 success 時不應觸發

    Example: 報名狀態從 pending 轉為 rejected 時不觸發 REGISTRATION_APPROVED
      Given 報名 101 的狀態為 "pending"
      When 系統將報名 101 的狀態更新為 "rejected"
      Then 系統不應觸發 "pf/trigger/registration_approved"

  Rule: 前置（參數）- 必要參數必須提供

    Example: 觸發時 context 包含所有必要欄位
      Given 報名 101 的狀態為 "pending"
      When 系統將報名 101 的狀態更新為 "success"
      Then 系統應觸發 "pf/trigger/registration_approved"
      And context_callable_set 的 callable 應為可呼叫函式
      And context_callable_set 的 params 應包含 registration post ID
