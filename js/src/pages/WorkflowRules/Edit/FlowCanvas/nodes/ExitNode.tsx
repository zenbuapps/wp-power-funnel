import { memo } from 'react'
import { Handle, Position, type NodeProps } from '@xyflow/react'
import type { TExitNodeData } from '../types'
import { FaFlagCheckered } from 'react-icons/fa6'
import './styles.css'

type TProps = NodeProps & {
	data: TExitNodeData
}

/**
 * 出口節點元件
 * 顯示工作流的結束點，只有頂部的 target Handle
 */
const ExitNode = memo((props: TProps) => {
	const { data } = props
	return (
		<div className="pf-flow-node pf-flow-node--exit">
			<Handle
				type="target"
				position={Position.Top}
				style={{ opacity: 0, pointerEvents: 'none' }}
			/>
			<div className="pf-flow-node__header">
				<span className="pf-flow-node__icon pf-flow-node__icon--green">
					<FaFlagCheckered />
				</span>
				<span className="pf-flow-node__title">{data.label}</span>
			</div>
		</div>
	)
})

ExitNode.displayName = 'ExitNode'

export default ExitNode
