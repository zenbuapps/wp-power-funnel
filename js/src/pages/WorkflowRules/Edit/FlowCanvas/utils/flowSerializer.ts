import type { TFlowNode, TFlowNodeData } from '../types'
import { FLOW_NODE_TYPE, ENTRANCE_NODE_ID, EXIT_NODE_ID } from '../types'
import {
	TRIGGER_POINT_LABELS,
	type TNodeDTO,
	type TTriggerPoint,
} from '@/pages/WorkflowRules/types'
import {
	createEntranceNode,
	createExitNode,
	createEdge,
} from './nodeFactory'
import type { TFlowEdge } from '../types'

/**
 * 將 React Flow 節點陣列轉換回 NodeDTO 陣列
 * 排除 entrance / exit 節點，只保留 action 節點
 *
 * @param nodes React Flow 節點陣列
 * @returns 後端 NodeDTO 陣列
 */
export const nodesToNodeDTOs = (nodes: TFlowNode[]): TNodeDTO[] => {
	return nodes
		.filter((node) => node.type === FLOW_NODE_TYPE.ACTION)
		.map((node, index) => {
			const data = node.data as TFlowNodeData
			return {
				node_module: data.nodeModule,
				node_type: data.nodeType,
				sort: index,
				args: data.args ?? {},
				...(data.matchCallback
					? { match_callback: data.matchCallback }
					: {}),
			}
		})
}

/**
 * 將後端 NodeDTO 陣列轉換為 React Flow 節點與邊線
 * 自動加入 entrance 和 exit 節點
 *
 * @param nodeDTOs 後端 NodeDTO 陣列
 * @param triggerPoint 觸發點
 * @returns React Flow 節點與邊線
 */
export const nodeDTOsToFlow = (
	nodeDTOs: TNodeDTO[],
	triggerPoint: TTriggerPoint | '',
): { nodes: TFlowNode[]; edges: TFlowEdge[] } => {
	const triggerLabel = triggerPoint
		? (TRIGGER_POINT_LABELS[triggerPoint] ?? triggerPoint)
		: '未設定觸發條件'

	const entranceNode = createEntranceNode(triggerLabel, triggerPoint)
	const exitNode = createExitNode()

	/** 根據 sort 排序 */
	const sorted = [...nodeDTOs].sort((a, b) => a.sort - b.sort)

	/** 建立 action 節點 */
	const actionNodes: TFlowNode[] = sorted.map((dto, index) => ({
		id: `action-${index}-${dto.node_module}`,
		type: FLOW_NODE_TYPE.ACTION,
		position: { x: 0, y: 0 },
		data: {
			nodeModule: dto.node_module,
			nodeType: dto.node_type,
			label: '', // 由 ActionNode 元件根據 nodeModule 顯示
			args: dto.args ?? {},
			matchCallback: dto.match_callback,
			sort: dto.sort,
		},
	}))

	const allNodes: TFlowNode[] = [entranceNode, ...actionNodes, exitNode]

	/** 建立鏈式邊線 */
	const edges: TFlowEdge[] = []
	for (let i = 0; i < allNodes.length - 1; i++) {
		edges.push(createEdge(allNodes[i].id, allNodes[i + 1].id))
	}

	return { nodes: allNodes, edges }
}

/**
 * 建立空白的 Flow（只有 entrance + exit）
 *
 * @param triggerPoint 觸發點
 * @returns 初始的節點與邊線
 */
export const createEmptyFlow = (
	triggerPoint: TTriggerPoint | '',
): { nodes: TFlowNode[]; edges: TFlowEdge[] } => {
	return nodeDTOsToFlow([], triggerPoint)
}

/**
 * 移除指定節點並重建邊線
 *
 * @param nodes 當前節點陣列
 * @param edges 當前邊線陣列
 * @param nodeId 要移除的節點 ID
 * @returns 更新後的節點與邊線
 */
export const removeNodeFromFlow = (
	nodes: TFlowNode[],
	edges: TFlowEdge[],
	nodeId: string,
): { nodes: TFlowNode[]; edges: TFlowEdge[] } => {
	/** 不允許刪除入口/出口節點 */
	if (nodeId === ENTRANCE_NODE_ID || nodeId === EXIT_NODE_ID) {
		return { nodes, edges }
	}

	/** 找到上游和下游節點 */
	const incomingEdge = edges.find((e) => e.target === nodeId)
	const outgoingEdge = edges.find((e) => e.source === nodeId)

	/** 移除相關邊線 */
	const filteredEdges = edges.filter(
		(e) => e.source !== nodeId && e.target !== nodeId,
	)

	/** 如果上下游都存在，建立新連線 */
	const newEdges =
		incomingEdge && outgoingEdge
			? [
					...filteredEdges,
					createEdge(incomingEdge.source, outgoingEdge.target),
				]
			: filteredEdges

	/** 移除節點 */
	const newNodes = nodes.filter((n) => n.id !== nodeId)

	return { nodes: newNodes, edges: newEdges }
}
