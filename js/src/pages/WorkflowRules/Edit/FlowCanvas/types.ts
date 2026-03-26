import type { Node, Edge } from '@xyflow/react'
import type { TNodeModule, TNodeType } from '@/pages/WorkflowRules/types'

/**
 * React Flow 節點 data 結構
 * 用於 ActionNode 的自訂資料
 */
export type TFlowNodeData = {
	/** 節點模組名稱 */
	nodeModule: TNodeModule
	/** 節點類型 */
	nodeType: TNodeType
	/** 節點顯示標籤 */
	label: string
	/** 節點特定參數 */
	args: Record<string, unknown>
	/** 條件匹配回調 */
	matchCallback?: string
	/** 排序序號 */
	sort: number
} & Record<string, unknown>

/**
 * 入口節點 data 結構
 */
export type TEntranceNodeData = {
	/** 觸發點標籤 */
	label: string
	/** 觸發點描述 */
	triggerPoint: string
} & Record<string, unknown>

/**
 * 出口節點 data 結構
 */
export type TExitNodeData = {
	/** 結束標籤 */
	label: string
} & Record<string, unknown>

/** React Flow 自訂節點類型名稱 */
export const FLOW_NODE_TYPE = {
	ENTRANCE: 'entrance',
	ACTION: 'action',
	EXIT: 'exit',
} as const

/** 入口節點固定 ID */
export const ENTRANCE_NODE_ID = 'entrance'

/** 出口節點固定 ID */
export const EXIT_NODE_ID = 'exit'

/** React Flow 節點型別 */
export type TFlowNode = Node<TFlowNodeData | TEntranceNodeData | TExitNodeData>

/** React Flow 邊線型別 */
export type TFlowEdge = Edge
