import { memo, useContext } from 'react'
import { Handle, Position, type NodeProps } from '@xyflow/react'
import type { TFlowNodeData } from '@/pages/WorkflowRules/Edit/FlowCanvas/types'
import { NODE_TYPE } from '@/pages/WorkflowRules/types'
import { NodeDefinitionsContext } from '@/pages/WorkflowRules/Edit/FlowCanvas/NodeDefinitionsContext'
import {
	CheckCircleFilled,
	CloseCircleFilled,
	MinusCircleFilled,
	LoadingOutlined,
} from '@ant-design/icons'
import { Spin } from 'antd'
import { NODE_RESULT_CODE, type TNodeResultCode } from '../../types'
import '@/pages/WorkflowRules/Edit/FlowCanvas/nodes/styles.css'
import './styles.css'

type TStatusActionNodeData = TFlowNodeData & {
	resultCode?: TNodeResultCode
	isRunning?: boolean
}

type TProps = NodeProps & {
	data: TStatusActionNodeData
}

/**
 * 根據結果碼取得 BEM 修飾 class
 *
 * @param {TNodeResultCode} resultCode 節點結果碼
 * @param {boolean}         isRunning  是否正在執行中
 */
const getStatusClass = (
	resultCode?: TNodeResultCode,
	isRunning?: boolean,
): string => {
	if (isRunning) return 'pf-flow-node--status-running'
	if (resultCode === undefined) return ''
	switch (resultCode) {
		case NODE_RESULT_CODE.SUCCESS:
			return 'pf-flow-node--status-success'
		case NODE_RESULT_CODE.SKIPPED:
			return 'pf-flow-node--status-skipped'
		case NODE_RESULT_CODE.FAILED:
			return 'pf-flow-node--status-failed'
		default:
			return ''
	}
}

/**
 * 狀態徽章元件
 *
 * @param {Object}          root0            元件屬性
 * @param {TNodeResultCode} root0.resultCode 節點結果碼
 * @param {boolean}         root0.isRunning  是否正在執行中
 */
const StatusBadge = ({
	resultCode,
	isRunning,
}: {
	resultCode?: TNodeResultCode
	isRunning?: boolean
}) => {
	if (isRunning) {
		return (
			<span className="pf-flow-node__badge pf-flow-node__badge--running">
				<Spin indicator={<LoadingOutlined style={{ fontSize: 12 }} />} />
			</span>
		)
	}
	if (resultCode === undefined) return null
	switch (resultCode) {
		case NODE_RESULT_CODE.SUCCESS:
			return (
				<span className="pf-flow-node__badge pf-flow-node__badge--success">
					<CheckCircleFilled />
				</span>
			)
		case NODE_RESULT_CODE.SKIPPED:
			return (
				<span className="pf-flow-node__badge pf-flow-node__badge--skipped">
					<MinusCircleFilled />
				</span>
			)
		case NODE_RESULT_CODE.FAILED:
			return (
				<span className="pf-flow-node__badge pf-flow-node__badge--failed">
					<CloseCircleFilled />
				</span>
			)
		default:
			return null
	}
}

/**
 * 帶狀態 overlay 的動作節點元件
 * 根據執行結果顯示不同的邊框顏色和狀態徽章
 */
const StatusActionNode = memo((props: TProps) => {
	const { data } = props
	const nodeDefinitions = useContext(NodeDefinitionsContext)
	const definition = nodeDefinitions[data.nodeModule]
	const label = definition?.name ?? data.label
	const iconUrl = definition?.icon
	const isSendMessage =
		(definition?.type ?? data.nodeType) === NODE_TYPE.SEND_MESSAGE
	const colorClass = isSendMessage
		? 'pf-flow-node__icon--blue'
		: 'pf-flow-node__icon--orange'
	const statusClass = getStatusClass(data.resultCode, data.isRunning)

	return (
		<div className={`pf-flow-node pf-flow-node--action ${statusClass}`}>
			<Handle
				type="target"
				position={Position.Top}
				style={{ opacity: 0, pointerEvents: 'none' }}
			/>
			<div className="pf-flow-node__header">
				<span className={`pf-flow-node__icon ${colorClass}`}>
					{iconUrl ? (
						<img
							src={iconUrl}
							alt={label}
							className="w-4 h-4"
							style={{ width: 16, height: 16 }}
						/>
					) : (
						<span>?</span>
					)}
				</span>
				<span className="pf-flow-node__title">{label}</span>
				<StatusBadge resultCode={data.resultCode} isRunning={data.isRunning} />
			</div>
			<Handle
				type="source"
				position={Position.Bottom}
				style={{ opacity: 0, pointerEvents: 'none' }}
			/>
		</div>
	)
})

StatusActionNode.displayName = 'StatusActionNode'

export default StatusActionNode
