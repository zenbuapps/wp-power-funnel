import { memo } from 'react'
import { Handle, Position, type NodeProps } from '@xyflow/react'
import type { TEntranceNodeData } from '../types'
import { FaPlay } from 'react-icons/fa6'
import './styles.css'

type TProps = NodeProps & {
	data: TEntranceNodeData
}

/**
 * 入口節點元件
 * 顯示工作流的觸發條件，只有底部的 source Handle
 */
const EntranceNode = memo((props: TProps) => {
	const { data } = props
	return (
		<div className="pf-flow-node pf-flow-node--entrance">
			<div className="pf-flow-node__header">
				<span className="pf-flow-node__icon pf-flow-node__icon--green">
					<FaPlay />
				</span>
				<span className="pf-flow-node__title">{data.label}</span>
			</div>
			{data.triggerPoint && (
				<div className="pf-flow-node__meta">
					<span className="pf-flow-node__meta-value">
						{data.triggerPoint}
					</span>
				</div>
			)}
			<Handle
				type="source"
				position={Position.Bottom}
				style={{ opacity: 0, pointerEvents: 'none' }}
			/>
		</div>
	)
})

EntranceNode.displayName = 'EntranceNode'

export default EntranceNode
