import { TEnv as ATEnv } from 'antd-toolkit'

export type TEnv = ATEnv & {
	LIFF_ID: string
	LIFF_URL: string
	IS_LOCAL: boolean
	PROMO_LINK_POST_TYPE: string
	REGISTRATION_POST_TYPE: string
	WORKFLOW_POST_TYPE: string
	WORKFLOW_RULE_POST_TYPE: string
}
