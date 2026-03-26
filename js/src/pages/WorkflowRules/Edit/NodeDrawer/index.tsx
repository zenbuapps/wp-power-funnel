import { memo, useCallback } from 'react'
import { Drawer, Form, Button, Space, Popconfirm } from 'antd'
import { DeleteOutlined, SaveOutlined } from '@ant-design/icons'
import type { TFlowNode, TFlowNodeData } from '../FlowCanvas/types'
import { NODE_MODULE, NODE_MODULE_LABELS } from '@/pages/WorkflowRules/types'
import { EmailForm, WaitForm, DefaultForm } from './forms'

type TNodeDrawerProps = {
	/** 是否開啟 */
	isOpen: boolean
	/** 當前選取的節點 */
	node: TFlowNode | null
	/** 節點 data（型別縮窄後） */
	nodeData: TFlowNodeData | undefined
	/** 關閉 Drawer */
	onClose: () => void
	/** 更新節點 data */
	onUpdate: (nodeId: string, data: Partial<TFlowNodeData>) => void
	/** 刪除節點 */
	onDelete: (nodeId: string) => void
}

/**
 * 節點設定抽屜元件
 * 根據節點類型渲染對應的設定表單
 */
const NodeDrawer = memo(
	({
		isOpen,
		node,
		nodeData,
		onClose,
		onUpdate,
		onDelete,
	}: TNodeDrawerProps) => {
		const [form] = Form.useForm()

		/** 儲存節點設定 */
		const handleSave = useCallback(() => {
			if (!node || !nodeData) return
			const values = form.getFieldsValue()
			const args = values.args ?? {}

			/** 如果使用 DefaultForm 的 _raw 欄位，嘗試解析 JSON */
			if (typeof args._raw === 'string') {
				try {
					const parsed = JSON.parse(args._raw) as Record<
						string,
						unknown
					>
					onUpdate(node.id, { args: parsed })
				} catch {
					/** JSON 解析失敗則保留原本 */
					onUpdate(node.id, { args: nodeData.args })
				}
			} else {
				onUpdate(node.id, { args })
			}
			onClose()
		}, [node, nodeData, form, onUpdate, onClose])

		/** 刪除節點 */
		const handleDelete = useCallback(() => {
			if (!node) return
			onDelete(node.id)
		}, [node, onDelete])

		if (!node || !nodeData) return null

		const label =
			NODE_MODULE_LABELS[nodeData.nodeModule] ?? nodeData.nodeModule

		/** 根據節點模組渲染對應表單 */
		const renderForm = () => {
			switch (nodeData.nodeModule) {
				case NODE_MODULE.EMAIL:
					return <EmailForm args={nodeData.args} />
				case NODE_MODULE.WAIT:
					return <WaitForm args={nodeData.args} />
				default:
					return (
						<DefaultForm
							args={nodeData.args}
							nodeModule={label}
						/>
					)
			}
		}

		return (
			<Drawer
				open={isOpen}
				onClose={onClose}
				title={`設定：${label}`}
				width={400}
				extra={
					<Space>
						<Popconfirm
							title="確認刪除此節點？"
							onConfirm={handleDelete}
							okText="刪除"
							cancelText="取消"
							okButtonProps={{ danger: true }}
						>
							<Button
								danger
								icon={<DeleteOutlined />}
								size="small"
							>
								刪除
							</Button>
						</Popconfirm>
						<Button
							type="primary"
							icon={<SaveOutlined />}
							size="small"
							onClick={handleSave}
						>
							套用
						</Button>
					</Space>
				}
			>
				<Form form={form} layout="vertical">
					{renderForm()}
				</Form>
			</Drawer>
		)
	},
)

NodeDrawer.displayName = 'NodeDrawer'

export default NodeDrawer
