import { nanoid } from 'nanoid'
import type { TFlowNode, TFlowEdge } from '../types'
import { FLOW_NODE_TYPE, ENTRANCE_NODE_ID, EXIT_NODE_ID } from '../types'
import {
	NODE_MODULE_LABELS,
	NODE_MODULE_TYPE_MAP,
	type TNodeModule,
} from '@/pages/WorkflowRules/types'

/**
 * 建立動作節點
 * @param nodeModule 節點模組名稱
 * @param args 節點參數
 * @param sort 排序序號
 * @returns React Flow 節點物件
 */
export const createActionNode = (
	nodeModule: TNodeModule,
	args: Record<string, unknown> = {},
	sort = 0,
): TFlowNode => ({
	id: nanoid(10),
	type: FLOW_NODE_TYPE.ACTION,
	position: { x: 0, y: 0 },
	data: {
		nodeModule,
		nodeType: NODE_MODULE_TYPE_MAP[nodeModule],
		label: NODE_MODULE_LABELS[nodeModule],
		args,
		sort,
	},
})

/**
 * 建立入口節點
 * @param triggerLabel 觸發點標籤
 * @param triggerPoint 觸發點 hook name
 */
export const createEntranceNode = (
	triggerLabel: string,
	triggerPoint: string,
): TFlowNode => ({
	id: ENTRANCE_NODE_ID,
	type: FLOW_NODE_TYPE.ENTRANCE,
	position: { x: 0, y: 0 },
	data: {
		label: triggerLabel || '觸發條件',
		triggerPoint,
	},
})

/**
 * 建立出口節點
 */
export const createExitNode = (): TFlowNode => ({
	id: EXIT_NODE_ID,
	type: FLOW_NODE_TYPE.EXIT,
	position: { x: 0, y: 0 },
	data: {
		label: '結束',
	},
})

/**
 * 建立兩個節點之間的邊線
 * @param source 來源節點 ID
 * @param target 目標節點 ID
 */
export const createEdge = (source: string, target: string): TFlowEdge => ({
	id: `e-${source}-${target}`,
	source,
	target,
	type: 'custom',
})

/**
 * 在兩個節點之間插入新節點
 * 將原先 source → target 的連線拆分為 source → newNode → target
 *
 * @param nodes 當前節點陣列
 * @param edges 當前邊線陣列
 * @param sourceId 來源節點 ID
 * @param targetId 目標節點 ID
 * @param nodeModule 要插入的節點模組
 */
export const insertNodeBetween = (
	nodes: TFlowNode[],
	edges: TFlowEdge[],
	sourceId: string,
	targetId: string,
	nodeModule: TNodeModule,
): { nodes: TFlowNode[]; edges: TFlowEdge[] } => {
	const newNode = createActionNode(nodeModule)

	/** 移除原先的邊線 */
	const filteredEdges = edges.filter(
		(edge) => !(edge.source === sourceId && edge.target === targetId),
	)

	/** 插入新的邊線 */
	const newEdges = [
		...filteredEdges,
		createEdge(sourceId, newNode.id),
		createEdge(newNode.id, targetId),
	]

	/** 計算新節點的插入位置（在 source 之後） */
	const sourceIndex = nodes.findIndex((n) => n.id === sourceId)
	const newNodes = [...nodes]
	newNodes.splice(sourceIndex + 1, 0, newNode)

	return { nodes: newNodes, edges: newEdges }
}
