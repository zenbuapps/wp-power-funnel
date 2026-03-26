import { memo, useState, useEffect, useRef, useCallback } from 'react'
import {
	BaseEdge,
	EdgeLabelRenderer,
	EdgeToolbar,
	getBezierPath,
	type EdgeProps,
} from '@xyflow/react'
import { PlusCircleOutlined } from '@ant-design/icons'
import {
	NODE_MODULE,
	NODE_MODULE_LABELS,
	type TNodeModule,
} from '@/pages/WorkflowRules/types'
import './styles.css'

type TCustomEdgeProps = EdgeProps & {
	data?: {
		/** 插入節點的回調函式 */
		onAddNode?: (
			sourceId: string,
			targetId: string,
			nodeModule: TNodeModule,
		) => void
	}
}

/** 所有可用的節點模組清單 */
const ALL_NODE_MODULES = Object.values(NODE_MODULE) as TNodeModule[]

/**
 * 自訂邊線元件
 * 中間顯示 + 按鈕，點擊後彈出節點類型選單
 */
const CustomEdge = memo((props: TCustomEdgeProps) => {
	const { id, source, target, data, markerEnd } = props
	const [isMenuOpen, setIsMenuOpen] = useState(false)
	const toolbarRef = useRef<HTMLDivElement>(null)
	const iconRef = useRef<HTMLDivElement>(null)

	const [edgePath, labelX, labelY] = getBezierPath(props)

	/** 監聽外部點擊關閉選單 */
	useEffect(() => {
		if (!isMenuOpen) return

		const handleClickOutside = (event: MouseEvent) => {
			const target = event.target as HTMLElement
			if (
				toolbarRef.current &&
				!toolbarRef.current.contains(target) &&
				iconRef.current &&
				!iconRef.current.contains(target)
			) {
				setIsMenuOpen(false)
			}
		}

		/** 延遲添加監聽器，避免立即觸發 */
		const timer = setTimeout(() => {
			document.addEventListener('click', handleClickOutside)
		}, 0)

		return () => {
			clearTimeout(timer)
			document.removeEventListener('click', handleClickOutside)
		}
	}, [isMenuOpen])

	const handleSelectModule = useCallback(
		(nodeModule: TNodeModule) => {
			data?.onAddNode?.(source, target, nodeModule)
			setIsMenuOpen(false)
		},
		[data, source, target],
	)

	return (
		<>
			<BaseEdge
				id={id}
				path={edgePath}
				className="!stroke-gray-300"
				style={{ strokeWidth: 2 }}
				markerEnd={markerEnd}
			/>

			{/* + 按鈕 */}
			<EdgeLabelRenderer>
				<div
					ref={iconRef}
					style={{
						position: 'absolute',
						transform: `translate(-50%, -50%) translate(${labelX}px, ${labelY}px)`,
						pointerEvents: 'all',
					}}
				>
					<button
						type="button"
						className="pf-edge-add-btn"
						onClick={() => setIsMenuOpen(true)}
					>
						<PlusCircleOutlined />
					</button>
				</div>
			</EdgeLabelRenderer>

			{/* 節點類型選單 */}
			<EdgeToolbar
				edgeId={id}
				x={labelX + 20}
				y={labelY - 10}
				alignX="left"
				alignY="top"
				isVisible={isMenuOpen}
			>
				<div
					ref={toolbarRef}
					className="pf-edge-menu"
				>
					{ALL_NODE_MODULES.map((nodeModule) => (
						<button
							type="button"
							key={nodeModule}
							className="pf-edge-menu__item"
							onClick={() => handleSelectModule(nodeModule)}
						>
							{NODE_MODULE_LABELS[nodeModule]}
						</button>
					))}
				</div>
			</EdgeToolbar>
		</>
	)
})

CustomEdge.displayName = 'CustomEdge'

export default CustomEdge
