/**
 * Workflow 執行監控相關型別定義
 * 對應後端 GET /power-funnel/workflows 與 GET /power-funnel/workflows/{id}
 */

/** Workflow 執行狀態 */
export const WORKFLOW_STATUS = {
	RUNNING: 'running',
	COMPLETED: 'completed',
	FAILED: 'failed',
} as const

export type TWorkflowStatus =
	(typeof WORKFLOW_STATUS)[keyof typeof WORKFLOW_STATUS]

/** 狀態對應的 Ant Design Tag 顏色 */
export const WORKFLOW_STATUS_COLOR: Record<TWorkflowStatus, string> = {
	running: 'blue',
	completed: 'green',
	failed: 'red',
}

/** 狀態對應的繁體中文標籤 */
export const WORKFLOW_STATUS_LABEL: Record<TWorkflowStatus, string> = {
	running: '執行中',
	completed: '已完成',
	failed: '已失敗',
}

/** 節點執行結果碼 */
export const NODE_RESULT_CODE = {
	SUCCESS: 200,
	SKIPPED: 301,
	FAILED: 500,
} as const

export type TNodeResultCode =
	(typeof NODE_RESULT_CODE)[keyof typeof NODE_RESULT_CODE]

/** 分頁資訊 */
export type TPagination = {
	total: number
	totalPages: number
	currentPage: number
	perPage: number
}

/** 節點執行結果 */
export type TNodeResult = {
	code: TNodeResultCode
	message: string
	data: unknown
	executedAt: string | null
}

/** 含結果的工作流節點 */
export type TWorkflowNodeWithResult = {
	nodeId: string
	nodeDefinitionId: string
	params: Record<string, unknown>
	result: TNodeResult | null
}

/** Context callable set 結構 */
export type TContextCallableSet = {
	callable: string
	params: unknown[]
}

/** 清單頁項目 */
export type TWorkflowListItem = {
	workflowId: string
	workflowRuleId: string
	workflowRuleTitle: string
	triggerPoint: string
	status: TWorkflowStatus
	nodeProgress: string
	duration: string
	createdAt: string
	userId: number
	userDisplayName: string
}

/** 詳情頁資料 */
export type TWorkflowDetail = {
	workflowId: string
	workflowRuleId: string
	workflowRuleTitle: string
	triggerPoint: string
	status: TWorkflowStatus
	nodes: TWorkflowNodeWithResult[]
	context: Record<string, string>
	contextCallableSet: TContextCallableSet
	startedAt: string | null
	completedAt: string | null
	duration: string
	createdAt: string
}

/** 清單 API 回應 */
export type TWorkflowListResponse = {
	code: string
	message: string
	data: {
		items: TWorkflowListItem[]
		pagination: TPagination
	}
}

/** 詳情 API 回應 */
export type TWorkflowDetailResponse = {
	code: string
	message: string
	data: TWorkflowDetail
}
