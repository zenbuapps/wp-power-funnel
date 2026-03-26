import { memo } from 'react'
import { Handle, Position, type NodeProps } from '@xyflow/react'
import type { TFlowNodeData } from '../types'
import {
	NODE_MODULE,
	NODE_MODULE_LABELS,
	NODE_TYPE,
	type TNodeModule,
} from '@/pages/WorkflowRules/types'
import {
	FaEnvelope,
	FaComment,
	FaLine,
	FaGlobe,
	FaClock,
	FaCalendarCheck,
	FaWindowRestore,
	FaCodeBranch,
	FaTag,
	FaQuestion,
} from 'react-icons/fa6'
import type { IconType } from 'react-icons'
import './styles.css'

type TProps = NodeProps & {
	data: TFlowNodeData
}

/** 節點模組對應的圖示 */
const NODE_ICON_MAP: Record<TNodeModule, IconType> = {
	[NODE_MODULE.EMAIL]: FaEnvelope,
	[NODE_MODULE.SMS]: FaComment,
	[NODE_MODULE.LINE]: FaLine,
	[NODE_MODULE.WEBHOOK]: FaGlobe,
	[NODE_MODULE.WAIT]: FaClock,
	[NODE_MODULE.WAIT_UNTIL]: FaCalendarCheck,
	[NODE_MODULE.TIME_WINDOW]: FaWindowRestore,
	[NODE_MODULE.YES_NO_BRANCH]: FaQuestion,
	[NODE_MODULE.SPLIT_BRANCH]: FaCodeBranch,
	[NODE_MODULE.TAG_USER]: FaTag,
}

/**
 * 動作節點元件
 * 根據 nodeModule 顯示不同圖示和標籤
 * 同時具有頂部 target Handle 和底部 source Handle
 */
const ActionNode = memo((props: TProps) => {
	const { data } = props
	const Icon = NODE_ICON_MAP[data.nodeModule] ?? FaQuestion
	const label = NODE_MODULE_LABELS[data.nodeModule] ?? data.label
	const isSendMessage = data.nodeType === NODE_TYPE.SEND_MESSAGE
	const colorClass = isSendMessage
		? 'pf-flow-node__icon--blue'
		: 'pf-flow-node__icon--orange'

	return (
		<div className="pf-flow-node pf-flow-node--action">
			<Handle
				type="target"
				position={Position.Top}
				style={{ opacity: 0, pointerEvents: 'none' }}
			/>
			<div className="pf-flow-node__header">
				<span className={`pf-flow-node__icon ${colorClass}`}>
					<Icon />
				</span>
				<span className="pf-flow-node__title">{label}</span>
			</div>
			<Handle
				type="source"
				position={Position.Bottom}
				style={{ opacity: 0, pointerEvents: 'none' }}
			/>
		</div>
	)
})

ActionNode.displayName = 'ActionNode'

export default ActionNode
