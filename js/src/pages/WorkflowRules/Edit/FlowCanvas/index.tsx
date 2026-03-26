import {
	memo,
	useCallback,
	useEffect,
	useImperativeHandle,
	forwardRef,
	useMemo,
} from 'react'
import {
	ReactFlow,
	Background,
	Controls,
	type NodeMouseHandler,
} from '@xyflow/react'
import '@xyflow/react/dist/style.css'
import { nodeTypes } from './nodes'
import { edgeTypes } from './edges'
import useFlowActions from './hooks/useFlowActions'
import { nodeDTOsToFlow, createEmptyFlow } from './utils/flowSerializer'
import type { TNodeDTO, TNodeModule, TTriggerPoint } from '@/pages/WorkflowRules/types'
import type { TFlowNodeData } from './types'
import NodeDrawer from '../NodeDrawer'

type TFlowCanvasProps = {
	/** 後端 NodeDTO 陣列 */
	nodeDTOs: TNodeDTO[]
	/** 觸發點 */
	triggerPoint: TTriggerPoint | ''
}

/**
 * FlowCanvas 暴露的方法
 * 透過 ref 讓父元件在儲存時取得 NodeDTO 陣列
 */
export type TFlowCanvasRef = {
	/** 取得當前所有動作節點的 NodeDTO 陣列 */
	getNodeDTOs: () => TNodeDTO[]
}

/**
 * React Flow 畫布元件
 * 負責渲染工作流的節點編輯器
 */
const FlowCanvas = forwardRef<TFlowCanvasRef, TFlowCanvasProps>(
	({ nodeDTOs, triggerPoint }, ref) => {
		/** 從後端資料建立初始 flow */
		const initialFlow = useMemo(() => {
			if (nodeDTOs.length > 0) {
				return nodeDTOsToFlow(nodeDTOs, triggerPoint)
			}
			return createEmptyFlow(triggerPoint)
		}, []) // eslint-disable-line react-hooks/exhaustive-deps

		const {
			nodes,
			edges,
			drawerState,
			selectedNode,
			addNodeBetween,
			removeNode,
			updateNodeData,
			openDrawer,
			closeDrawer,
			getNodeDTOs,
			applyLayout,
		} = useFlowActions(initialFlow.nodes, initialFlow.edges)

		/** 初始套用佈局 */
		useEffect(() => {
			applyLayout(initialFlow.nodes, initialFlow.edges)
		}, []) // eslint-disable-line react-hooks/exhaustive-deps

		/** 當 triggerPoint 變更時重建 flow */
		useEffect(() => {
			const newFlow =
				nodeDTOs.length > 0
					? nodeDTOsToFlow(nodeDTOs, triggerPoint)
					: createEmptyFlow(triggerPoint)
			applyLayout(newFlow.nodes, newFlow.edges)
		}, [triggerPoint]) // eslint-disable-line react-hooks/exhaustive-deps

		/** 暴露 getNodeDTOs 給父元件 */
		useImperativeHandle(ref, () => ({ getNodeDTOs }), [getNodeDTOs])

		/** 點擊節點開啟 Drawer */
		const handleNodeClick: NodeMouseHandler = useCallback(
			(_event, node) => {
				openDrawer(node.id)
			},
			[openDrawer],
		)

		/**
		 * 將 addNodeBetween 注入到每條邊線的 data 中
		 * 讓 CustomEdge 可以呼叫新增節點
		 */
		const edgesWithHandler = useMemo(
			() =>
				edges.map((edge) => ({
					...edge,
					data: {
						...edge.data,
						onAddNode: (
							sourceId: string,
							targetId: string,
							nodeModule: TNodeModule,
						) => addNodeBetween(sourceId, targetId, nodeModule),
					},
				})),
			[edges, addNodeBetween],
		)

		return (
			<>
				<div style={{ width: '100%', height: '600px' }}>
					<ReactFlow
						nodes={nodes}
						edges={edgesWithHandler}
						nodeTypes={nodeTypes}
						edgeTypes={edgeTypes}
						onNodeClick={handleNodeClick}
						fitView
						nodesDraggable={false}
						nodesConnectable={false}
						elementsSelectable={true}
					>
						<Background gap={16} size={1} />
						<Controls />
					</ReactFlow>
				</div>
				<NodeDrawer
					isOpen={drawerState.isOpen}
					node={selectedNode}
					nodeData={selectedNode?.data as TFlowNodeData | undefined}
					onClose={closeDrawer}
					onUpdate={updateNodeData}
					onDelete={removeNode}
				/>
			</>
		)
	},
)

FlowCanvas.displayName = 'FlowCanvas'

export default memo(FlowCanvas)
