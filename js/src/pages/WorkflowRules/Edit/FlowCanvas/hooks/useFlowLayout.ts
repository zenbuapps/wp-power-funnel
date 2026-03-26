import { useCallback } from 'react'
import Dagre from '@dagrejs/dagre'
import type { TFlowNode, TFlowEdge } from '../types'

/** 節點預設寬度 */
const NODE_WIDTH = 250

/** 節點預設高度 */
const NODE_HEIGHT = 80

/** 水平間距 */
const NODE_SEP = 80

/** 垂直間距 */
const RANK_SEP = 150

/**
 * Dagre 自動佈局 Hook
 * 將節點依照垂直（上到下）方向進行自動佈局排列
 *
 * @returns getLayoutedElements 函式
 */
const useFlowLayout = () => {
	const getLayoutedElements = useCallback(
		(
			nodes: TFlowNode[],
			edges: TFlowEdge[],
		): { nodes: TFlowNode[]; edges: TFlowEdge[] } => {
			const g = new Dagre.graphlib.Graph().setDefaultEdgeLabel(() => ({}))

			g.setGraph({
				rankdir: 'TB',
				nodesep: NODE_SEP,
				ranksep: RANK_SEP,
			})

			nodes.forEach((node) => {
				g.setNode(node.id, { width: NODE_WIDTH, height: NODE_HEIGHT })
			})

			edges.forEach((edge) => {
				g.setEdge(edge.source, edge.target)
			})

			Dagre.layout(g)

			const layoutedNodes = nodes.map((node) => {
				const { x, y } = g.node(node.id)
				return {
					...node,
					position: {
						x: x - NODE_WIDTH / 2,
						y: y - NODE_HEIGHT / 2,
					},
				}
			})

			return { nodes: layoutedNodes, edges }
		},
		[],
	)

	return { getLayoutedElements }
}

export default useFlowLayout
