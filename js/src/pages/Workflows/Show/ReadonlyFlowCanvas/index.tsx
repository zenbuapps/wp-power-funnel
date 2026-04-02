import { memo, useMemo, useCallback } from 'react'
import {
	ReactFlow,
	Background,
	Controls,
	type NodeMouseHandler,
} from '@xyflow/react'
import '@xyflow/react/dist/style.css'
import EntranceNode from '@/pages/WorkflowRules/Edit/FlowCanvas/nodes/EntranceNode'
import ExitNode from '@/pages/WorkflowRules/Edit/FlowCanvas/nodes/ExitNode'
import StatusActionNode from './StatusActionNode'
import useFlowLayout from '@/pages/WorkflowRules/Edit/FlowCanvas/hooks/useFlowLayout'
import {
	FLOW_NODE_TYPE,
	ENTRANCE_NODE_ID,
	EXIT_NODE_ID,
	type TFlowNode,
	type TFlowEdge,
} from '@/pages/WorkflowRules/Edit/FlowCanvas/types'
import { NodeDefinitionsContext } from '@/pages/WorkflowRules/Edit/FlowCanvas/NodeDefinitionsContext'
import { createEdge } from '@/pages/WorkflowRules/Edit/FlowCanvas/utils/nodeFactory'
import type { TNodeDefinition } from '@/pages/WorkflowRules/types'
import {
	WORKFLOW_STATUS,
	type TWorkflowNodeWithResult,
	type TWorkflowStatus,
	type TNodeResultCode,
} from '../../types'
import './styles.css'

type TReadonlyFlowCanvasProps = {
	nodes: TWorkflowNodeWithResult[]
	triggerPoint: string
	triggerPointLabelMap: Record<string, string>
	nodeDefinitionsMap: Record<string, TNodeDefinition>
	workflowStatus: TWorkflowStatus
	onNodeClick?: (_nodeId: string) => void
}

/**
 * 將工作流節點轉換為帶狀態的 React Flow 節點與邊線
 *
 * @param {TWorkflowNodeWithResult[]} workflowNodes        工作流節點陣列
 * @param {string}                    triggerPoint         觸發點 hook 名稱
 * @param {Record<string, string>}    triggerPointLabelMap 觸發點標籤對照表
 * @param {TWorkflowStatus}           workflowStatus       工作流狀態
 */
const buildFlowFromWorkflowNodes = (
	workflowNodes: TWorkflowNodeWithResult[],
	triggerPoint: string,
	triggerPointLabelMap: Record<string, string>,
	workflowStatus: TWorkflowStatus,
): { nodes: TFlowNode[]; edges: TFlowEdge[] } => {
	const triggerLabel = triggerPoint
		? (triggerPointLabelMap[triggerPoint] ?? triggerPoint)
		: '未設定觸發條件'

	const entranceNode: TFlowNode = {
		id: ENTRANCE_NODE_ID,
		type: FLOW_NODE_TYPE.ENTRANCE,
		position: { x: 0, y: 0 },
		data: {
			label: triggerLabel,
			triggerPoint,
		},
	}

	const exitNode: TFlowNode = {
		id: EXIT_NODE_ID,
		type: FLOW_NODE_TYPE.EXIT,
		position: { x: 0, y: 0 },
		data: {
			label: '結束',
		},
	}

	/** 建立帶狀態資訊的 action 節點 */
	const actionNodes: TFlowNode[] = workflowNodes.map((wfNode, index) => {
		const hasResult = wfNode.result !== null
		const isRunning = !hasResult && workflowStatus === WORKFLOW_STATUS.RUNNING

		return {
			id: wfNode.nodeId || `action-${index}`,
			type: FLOW_NODE_TYPE.ACTION,
			position: { x: 0, y: 0 },
			data: {
				nodeModule: wfNode.nodeDefinitionId,
				nodeType: 'action' as const,
				label: '',
				args: wfNode.params,
				sort: index,
				resultCode: hasResult
					? (wfNode.result?.code as TNodeResultCode)
					: undefined,
				isRunning,
			},
		}
	})

	const allNodes: TFlowNode[] = [entranceNode, ...actionNodes, exitNode]

	/** 建立鏈式邊線（使用 default edge，不需要 + 按鈕） */
	const edges: TFlowEdge[] = []
	for (let i = 0; i < allNodes.length - 1; i++) {
		edges.push({
			...createEdge(allNodes[i].id, allNodes[i + 1].id),
			type: 'default',
		})
	}

	return { nodes: allNodes, edges }
}

/**
 * 唯讀版本的節點類型註冊表
 * 使用 StatusActionNode 替代 ActionNode
 */
const readonlyNodeTypes = {
	entrance: EntranceNode,
	action: StatusActionNode,
	exit: ExitNode,
} as const

/**
 * 唯讀流程圖元件
 * 複用 FlowCanvas 的佈局與節點元件，但禁止編輯操作
 * 節點顯示執行狀態（成功/跳過/失敗/執行中）
 */
const ReadonlyFlowCanvas = memo(
	({
		nodes: workflowNodes,
		triggerPoint,
		triggerPointLabelMap,
		nodeDefinitionsMap,
		workflowStatus,
		onNodeClick,
	}: TReadonlyFlowCanvasProps) => {
		const { getLayoutedElements } = useFlowLayout()

		/** 建立帶狀態的 flow 節點並套用佈局 */
		const { nodes, edges } = useMemo(() => {
			const flow = buildFlowFromWorkflowNodes(
				workflowNodes,
				triggerPoint,
				triggerPointLabelMap,
				workflowStatus,
			)
			return getLayoutedElements(flow.nodes, flow.edges)
		}, [
			workflowNodes,
			triggerPoint,
			triggerPointLabelMap,
			workflowStatus,
			getLayoutedElements,
		])

		/** 點擊節點觸發回調 */
		const handleNodeClick: NodeMouseHandler = useCallback(
			(_event, node) => {
				/** 排除入口和出口節點 */
				if (node.id === ENTRANCE_NODE_ID || node.id === EXIT_NODE_ID) {
					return
				}
				onNodeClick?.(node.id)
			},
			[onNodeClick],
		)

		return (
			<NodeDefinitionsContext.Provider value={nodeDefinitionsMap}>
				<div style={{ width: '100%', height: '500px' }}>
					<ReactFlow
						nodes={nodes}
						edges={edges}
						nodeTypes={readonlyNodeTypes}
						onNodeClick={handleNodeClick}
						nodesDraggable={false}
						nodesConnectable={false}
						elementsSelectable={false}
						panOnDrag={true}
						zoomOnScroll={true}
						fitView
					>
						<Background gap={16} size={1} />
						<Controls showInteractive={false} />
					</ReactFlow>
				</div>
			</NodeDefinitionsContext.Provider>
		)
	},
)

ReadonlyFlowCanvas.displayName = 'ReadonlyFlowCanvas'

export default ReadonlyFlowCanvas
