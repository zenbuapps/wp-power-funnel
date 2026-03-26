// @ts-ignore
import { TPostStatus } from 'antd-toolkit/wp'

/**
 * 節點模組列舉
 * 對應後端 ENode enum
 */
export const NODE_MODULE = {
	EMAIL: 'email',
	SMS: 'sms',
	LINE: 'line',
	WEBHOOK: 'webhook',
	WAIT: 'wait',
	WAIT_UNTIL: 'wait_until',
	TIME_WINDOW: 'time_window',
	YES_NO_BRANCH: 'yes_no_branch',
	SPLIT_BRANCH: 'split_branch',
	TAG_USER: 'tag_user',
} as const

export type TNodeModule = (typeof NODE_MODULE)[keyof typeof NODE_MODULE]

/**
 * 節點類型列舉
 * 對應後端 ENodeType enum
 */
export const NODE_TYPE = {
	SEND_MESSAGE: 'send_message',
	ACTION: 'action',
} as const

export type TNodeType = (typeof NODE_TYPE)[keyof typeof NODE_TYPE]

/**
 * 觸發點列舉
 * 對應後端 ETriggerPoint enum
 */
export const TRIGGER_POINT = {
	REGISTRATION_CREATED: 'pf/trigger/registration_created',
} as const

export type TTriggerPoint =
	(typeof TRIGGER_POINT)[keyof typeof TRIGGER_POINT]

/** 觸發點標籤對照 */
export const TRIGGER_POINT_LABELS: Record<TTriggerPoint, string> = {
	[TRIGGER_POINT.REGISTRATION_CREATED]: '用戶報名後',
}

/** 節點模組標籤對照 */
export const NODE_MODULE_LABELS: Record<TNodeModule, string> = {
	[NODE_MODULE.EMAIL]: '傳送 Email',
	[NODE_MODULE.SMS]: '傳送 SMS',
	[NODE_MODULE.LINE]: '傳送 LINE 訊息',
	[NODE_MODULE.WEBHOOK]: '發送 Webhook 通知',
	[NODE_MODULE.WAIT]: '等待',
	[NODE_MODULE.WAIT_UNTIL]: '等待至',
	[NODE_MODULE.TIME_WINDOW]: '等待至時間窗口',
	[NODE_MODULE.YES_NO_BRANCH]: '是/否分支',
	[NODE_MODULE.SPLIT_BRANCH]: '分支',
	[NODE_MODULE.TAG_USER]: '標籤用戶',
}

/** 節點模組對應類型 */
export const NODE_MODULE_TYPE_MAP: Record<TNodeModule, TNodeType> = {
	[NODE_MODULE.EMAIL]: NODE_TYPE.SEND_MESSAGE,
	[NODE_MODULE.SMS]: NODE_TYPE.SEND_MESSAGE,
	[NODE_MODULE.LINE]: NODE_TYPE.SEND_MESSAGE,
	[NODE_MODULE.WEBHOOK]: NODE_TYPE.SEND_MESSAGE,
	[NODE_MODULE.WAIT]: NODE_TYPE.ACTION,
	[NODE_MODULE.WAIT_UNTIL]: NODE_TYPE.ACTION,
	[NODE_MODULE.TIME_WINDOW]: NODE_TYPE.ACTION,
	[NODE_MODULE.YES_NO_BRANCH]: NODE_TYPE.ACTION,
	[NODE_MODULE.SPLIT_BRANCH]: NODE_TYPE.ACTION,
	[NODE_MODULE.TAG_USER]: NODE_TYPE.ACTION,
}

/**
 * 後端 NodeDTO 結構
 * 對應 PHP Contracts\DTOs\NodeDTO
 */
export type TNodeDTO = {
	/** 節點模組名稱 */
	node_module: TNodeModule
	/** 節點類型 */
	node_type: TNodeType
	/** 排序序號 */
	sort: number
	/** 節點特定參數 */
	args: Record<string, unknown>
	/** 條件匹配回調 */
	match_callback?: string
}

/**
 * WorkflowRule CPT 記錄型別
 * 對應後端 pf_workflow_rule post type
 */
export type TWorkflowRuleRecord = {
	/** 文章 ID */
	id: string
	/** 規則名稱 */
	name: string
	/** 文章狀態 */
	status: TPostStatus
	/** 建立日期 */
	date_created: string
	/** 修改日期 */
	date_modified: string
	/** 作者 ID */
	author: number
	/** 觸發點 hook name */
	trigger_point: TTriggerPoint | ''
	/** 節點 DTO 陣列 */
	nodes: TNodeDTO[]
	/** 文章層級 */
	depth: number
	/** 別名 */
	slug: string
}
