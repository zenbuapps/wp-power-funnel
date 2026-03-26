import { useCallback, useState, useRef } from 'react'
import type { TFlowNode, TFlowEdge, TFlowNodeData } from '../types'
import { ENTRANCE_NODE_ID, EXIT_NODE_ID } from '../types'
import { insertNodeBetween } from '../utils/nodeFactory'
import { removeNodeFromFlow, nodesToNodeDTOs } from '../utils/flowSerializer'
import type { TNodeModule, TNodeDTO } from '@/pages/WorkflowRules/types'
import useFlowLayout from './useFlowLayout'

type TDrawerState = {
	/** 是否開啟 */
	isOpen: boolean
	/** 選取的節點 ID */
	selectedNodeId: string | null
}

/**
 * Flow 畫布操作 Hook
 * 管理節點新增、刪除、更新、Drawer 狀態等操作
 *
 * @param initialNodes 初始節點
 * @param initialEdges 初始邊線
 */
const useFlowActions = (
	initialNodes: TFlowNode[],
	initialEdges: TFlowEdge[],
) => {
	const [nodes, setNodes] = useState<TFlowNode[]>(initialNodes)
	const [edges, setEdges] = useState<TFlowEdge[]>(initialEdges)
	const [drawerState, setDrawerState] = useState<TDrawerState>({
		isOpen: false,
		selectedNodeId: null,
	})

	/** 使用 ref 保持最新的 nodes / edges 引用，避免閉包陳舊值 */
	const nodesRef = useRef(nodes)
	nodesRef.current = nodes
	const edgesRef = useRef(edges)
	edgesRef.current = edges

	const { getLayoutedElements } = useFlowLayout()

	/** 在兩個節點之間插入新節點 */
	const addNodeBetween = useCallback(
		(sourceId: string, targetId: string, nodeModule: TNodeModule) => {
			const result = insertNodeBetween(
				nodesRef.current,
				edgesRef.current,
				sourceId,
				targetId,
				nodeModule,
			)
			const layouted = getLayoutedElements(result.nodes, result.edges)
			setNodes(layouted.nodes)
			setEdges(layouted.edges)
		},
		[getLayoutedElements],
	)

	/** 刪除指定節點 */
	const removeNode = useCallback(
		(nodeId: string) => {
			if (nodeId === ENTRANCE_NODE_ID || nodeId === EXIT_NODE_ID) return
			const result = removeNodeFromFlow(
				nodesRef.current,
				edgesRef.current,
				nodeId,
			)
			const layouted = getLayoutedElements(result.nodes, result.edges)
			setNodes(layouted.nodes)
			setEdges(layouted.edges)
			/** 關閉 Drawer（如果正在編輯該節點） */
			setDrawerState((prev) =>
				prev.selectedNodeId === nodeId
					? { isOpen: false, selectedNodeId: null }
					: prev,
			)
		},
		[getLayoutedElements],
	)

	/** 更新節點 data */
	const updateNodeData = useCallback(
		(nodeId: string, data: Partial<TFlowNodeData>) => {
			setNodes((prevNodes) =>
				prevNodes.map((node) =>
					node.id === nodeId
						? { ...node, data: { ...node.data, ...data } }
						: node,
				),
			)
		},
		[],
	)

	/** 開啟節點設定 Drawer */
	const openDrawer = useCallback((nodeId: string) => {
		if (nodeId === ENTRANCE_NODE_ID || nodeId === EXIT_NODE_ID) return
		setDrawerState({ isOpen: true, selectedNodeId: nodeId })
	}, [])

	/** 關閉 Drawer */
	const closeDrawer = useCallback(() => {
		setDrawerState({ isOpen: false, selectedNodeId: null })
	}, [])

	/** 取得當前的 NodeDTO 陣列（用於表單儲存） */
	const getNodeDTOs = useCallback((): TNodeDTO[] => {
		return nodesToNodeDTOs(nodesRef.current)
	}, [])

	/** 取得當前選取的節點 */
	const selectedNode = drawerState.selectedNodeId
		? nodes.find((n) => n.id === drawerState.selectedNodeId) ?? null
		: null

	/** 套用佈局（供外部初始化使用） */
	const applyLayout = useCallback(
		(newNodes: TFlowNode[], newEdges: TFlowEdge[]) => {
			const layouted = getLayoutedElements(newNodes, newEdges)
			setNodes(layouted.nodes)
			setEdges(layouted.edges)
		},
		[getLayoutedElements],
	)

	return {
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
	}
}

export default useFlowActions
