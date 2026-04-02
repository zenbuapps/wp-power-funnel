import { memo, useState, useCallback, useMemo } from 'react'
import { Descriptions, Tag, Spin, Card, Space } from 'antd'
import { Show } from '@refinedev/antd'
import { useCustom, useApiUrl, useNavigation } from '@refinedev/core'
import { useParams } from 'react-router'
import dayjs from 'dayjs'
import useTriggerPoints from '@/pages/WorkflowRules/hooks/useTriggerPoints'
import useNodeDefinitions from '@/pages/WorkflowRules/hooks/useNodeDefinitions'
import ReadonlyFlowCanvas from './ReadonlyFlowCanvas'
import NodeResultDrawer from './NodeResultDrawer'
import TriggerInfoPanel from './TriggerInfoPanel'
import {
	WORKFLOW_STATUS_COLOR,
	WORKFLOW_STATUS_LABEL,
	type TWorkflowDetailResponse,
	type TWorkflowDetail,
	type TWorkflowNodeWithResult,
	type TWorkflowStatus,
} from '../types'

/**
 * Workflow 執行詳情頁面
 * 顯示流程圖（含執行狀態）、節點結果 Drawer、觸發資訊面板
 */
const WorkflowsShowComponent = () => {
	const { id } = useParams<{ id: string }>()
	const apiUrl = useApiUrl('power-funnel')
	const { edit } = useNavigation()
	const { labelMap: triggerPointLabelMap } = useTriggerPoints()
	const { definitionsMap: nodeDefinitionsMap } = useNodeDefinitions()

	/** 節點結果 Drawer 狀態 */
	const [drawerOpen, setDrawerOpen] = useState(false)
	const [selectedNodeId, setSelectedNodeId] = useState<string | null>(null)

	/** 呼叫詳情 API */
	const { data, isLoading } = useCustom<TWorkflowDetailResponse>({
		url: `${apiUrl}/workflows/${id}`,
		method: 'get',
		queryOptions: {
			queryKey: ['workflow_detail', id],
			enabled: !!id,
		},
	})

	const detail: TWorkflowDetail | undefined = data?.data?.data

	/** 點擊節點開啟 Drawer */
	const handleNodeClick = useCallback((nodeId: string) => {
		setSelectedNodeId(nodeId)
		setDrawerOpen(true)
	}, [])

	/** 關閉 Drawer */
	const handleDrawerClose = useCallback(() => {
		setDrawerOpen(false)
		setSelectedNodeId(null)
	}, [])

	/** 找到選中的節點資料 */
	const selectedNode: TWorkflowNodeWithResult | null = useMemo(() => {
		if (!detail || !selectedNodeId) return null
		return detail.nodes.find((n) => n.nodeId === selectedNodeId) ?? null
	}, [detail, selectedNodeId])

	if (isLoading) {
		return (
			<Show title="Workflow 詳情">
				<div className="flex items-center justify-center py-20">
					<Spin size="large" />
				</div>
			</Show>
		)
	}

	if (!detail) {
		return (
			<Show title="Workflow 詳情">
				<div className="py-10 text-center text-gray-400">
					找不到此 Workflow 紀錄
				</div>
			</Show>
		)
	}

	const triggerPointLabel =
		triggerPointLabelMap[detail.triggerPoint] ?? detail.triggerPoint

	return (
		<Show title="Workflow 詳情">
			<Space direction="vertical" size="large" className="w-full">
				{/* A. 頂部摘要列 */}
				<Card>
					<Descriptions column={{ xs: 1, sm: 2, md: 3 }} bordered size="small">
						<Descriptions.Item label="Workflow ID">
							{detail.workflowId}
						</Descriptions.Item>
						<Descriptions.Item label="來源規則">
							<a
								onClick={() => edit('workflow-rules', detail.workflowRuleId)}
								className="cursor-pointer text-blue-500 hover:text-blue-600"
							>
								{detail.workflowRuleTitle}
							</a>
						</Descriptions.Item>
						<Descriptions.Item label="觸發點">
							<Tag color="blue">{triggerPointLabel}</Tag>
						</Descriptions.Item>
						<Descriptions.Item label="狀態">
							<Tag
								color={
									WORKFLOW_STATUS_COLOR[detail.status as TWorkflowStatus] ??
									'default'
								}
							>
								{WORKFLOW_STATUS_LABEL[detail.status as TWorkflowStatus] ??
									detail.status}
							</Tag>
						</Descriptions.Item>
						<Descriptions.Item label="觸發時間">
							{detail.createdAt
								? dayjs(detail.createdAt).format('YYYY-MM-DD HH:mm:ss')
								: '-'}
						</Descriptions.Item>
						<Descriptions.Item label="耗時">
							{detail.duration || '-'}
						</Descriptions.Item>
					</Descriptions>
				</Card>

				{/* B. ReactFlow 唯讀流程圖 */}
				<Card title="執行流程">
					<ReadonlyFlowCanvas
						nodes={detail.nodes}
						triggerPoint={detail.triggerPoint}
						triggerPointLabelMap={triggerPointLabelMap}
						nodeDefinitionsMap={nodeDefinitionsMap}
						workflowStatus={detail.status}
						onNodeClick={handleNodeClick}
					/>
				</Card>

				{/* D. 觸發資訊面板 */}
				<TriggerInfoPanel
					triggerPoint={detail.triggerPoint}
					triggerPointLabel={triggerPointLabel}
					workflowRuleId={detail.workflowRuleId}
					workflowRuleTitle={detail.workflowRuleTitle}
					contextCallableSet={detail.contextCallableSet}
					context={detail.context}
				/>
			</Space>

			{/* C. 節點結果 Drawer */}
			<NodeResultDrawer
				isOpen={drawerOpen}
				node={selectedNode}
				onClose={handleDrawerClose}
			/>
		</Show>
	)
}

export const WorkflowsShow = memo(WorkflowsShowComponent)
