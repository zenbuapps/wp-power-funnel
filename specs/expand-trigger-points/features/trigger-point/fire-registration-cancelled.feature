@ignore @command
Feature: 觸發 REGISTRATION_CANCELLED 觸發點

  Background:
    Given 系統中有以下報名紀錄：
      | registrationId | identityId    | identityProvider | activityId | promoLinkId | status  |
      | 101            | U1234567890   | line             | 501        | 201         | success |

  Rule: 前置（狀態）- 報名狀態必須從非 cancelled 轉為 cancelled

    Example: 報名狀態從 success 轉為 cancelled 時觸發
      Given 報名 101 的狀態為 "success"
      When 系統將報名 101 的狀態更新為 "cancelled"
      Then 系統應觸發 "pf/trigger/registration_cancelled"
      And context_callable_set 執行後應產生以下 context：
        | key               | value         |
        | registration_id   | 101           |
        | identity_id       | U1234567890   |
        | identity_provider | line          |
        | activity_id       | 501           |
        | promo_link_id     | 201           |

  Rule: 前置（狀態）- 報名狀態未變更為 cancelled 時不應觸發

    Example: 報名狀態從 success 轉為 failed 時不觸發 REGISTRATION_CANCELLED
      Given 報名 101 的狀態為 "success"
      When 系統將報名 101 的狀態更新為 "failed"
      Then 系統不應觸發 "pf/trigger/registration_cancelled"
