import { memo, useRef, useCallback, useMemo } from 'react'
import { Edit, useForm } from '@refinedev/antd'
import { HttpError, useParsed } from '@refinedev/core'
import { toFormData, Heading } from 'antd-toolkit'
import { notificationProps } from 'antd-toolkit/refine'
import { Form, Input, Select, Spin } from 'antd'
import { TWorkflowRuleRecord } from '@/pages/WorkflowRules/types'
import FlowCanvas, { type TFlowCanvasRef } from './FlowCanvas'
import useTriggerPoints from '@/pages/WorkflowRules/hooks/useTriggerPoints'
import useNodeDefinitions from '@/pages/WorkflowRules/hooks/useNodeDefinitions'

const { Item } = Form

/**
 * 自動化規則編輯頁面
 * 上方為基本資訊表單（名稱、觸發點），下方為 React Flow 畫布
 */
const EditComponent = () => {
	const { id } = useParsed()
	const flowRef = useRef<TFlowCanvasRef>(null)
	const {
		groupedOptions,
		labelMap: triggerPointLabelMap,
		groupLabelMap: triggerPointGroupLabelMap,
		isLoading: triggerPointsLoading,
	} = useTriggerPoints()

	const { definitions: nodeDefinitions, definitionsMap: nodeDefinitionsMap } =
		useNodeDefinitions()

	const { formProps, form, saveButtonProps, query, mutation, onFinish } =
		useForm<TWorkflowRuleRecord, HttpError, Partial<TWorkflowRuleRecord>>({
			action: 'edit',
			resource: 'posts',
			id,
			redirect: false,
			...notificationProps,
			queryMeta: {
				variables: {
					meta_keys: ['trigger_point', 'nodes'],
				},
			},
		})

	const record: TWorkflowRuleRecord | undefined = query?.data?.data

	/**
	 * 安全解析 nodes：後端可能回傳 JSON 字串而非陣列
	 * 若為字串則 JSON.parse，解析失敗則回傳空陣列
	 */
	const parsedNodes = useMemo(() => {
		const raw = record?.nodes
		if (!raw) return []
		if (Array.isArray(raw)) return raw
		if (typeof raw === 'string') {
			try {
				const parsed = JSON.parse(raw)
				return Array.isArray(parsed) ? parsed : []
			} catch {
				return []
			}
		}
		return []
	}, [record?.nodes])

	/** 表單提交處理：合併表單欄位與 FlowCanvas 的節點資料 */
	const handleOnFinish = useCallback(() => {
		const values = form.getFieldsValue()
		const nodeDTOs = flowRef.current?.getNodeDTOs() ?? []

		onFinish(
			toFormData({
				...values,
				nodes: JSON.stringify(nodeDTOs),
			}) as Partial<TWorkflowRuleRecord>,
		)
	}, [form, onFinish])

	/** 監聯 trigger_point 變化 */
	const triggerPoint = Form.useWatch('trigger_point', form) as
		| string
		| undefined

	return (
		<div className="sticky-card-actions sticky-tabs-nav">
			<Edit
				resource="posts"
				title={
					<>
						{record?.name}{' '}
						<span className="text-gray-400 text-xs">#{record?.id}</span>
					</>
				}
				headerButtons={() => null}
				saveButtonProps={{
					...saveButtonProps,
					children: '儲存',
					icon: null,
					loading: mutation?.isLoading,
					onClick: handleOnFinish,
				}}
				isLoading={query?.isLoading}
			>
				<Spin spinning={query?.isLoading ?? false}>
					<Form {...formProps} layout="vertical">
						<Heading>基本設定</Heading>
						<div className="grid grid-cols-1 xl:grid-cols-2 gap-x-8 mb-8">
							<Item name={['name']} label="規則名稱">
								<Input allowClear placeholder="輸入自動化規則名稱" />
							</Item>
							<Item name={['trigger_point']} label="觸發條件">
								<Select
									options={groupedOptions}
									loading={triggerPointsLoading}
									placeholder="選擇觸發條件"
									allowClear
									showSearch
									optionFilterProp="label"
									filterOption={(input, option) => {
										const lower = input.toLowerCase()
										const labelMatch =
											(option?.label as string)
												?.toLowerCase()
												.includes(lower) ?? false
										const groupLabel =
											triggerPointGroupLabelMap[option?.value as string] ?? ''
										const groupMatch = groupLabel.toLowerCase().includes(lower)
										return labelMatch || groupMatch
									}}
								/>
							</Item>
						</div>
					</Form>

					<Heading>工作流程</Heading>
					{!query?.isLoading && record && (
						<FlowCanvas
							ref={flowRef}
							nodeDTOs={parsedNodes}
							triggerPoint={triggerPoint ?? record.trigger_point ?? ''}
							triggerPointLabelMap={triggerPointLabelMap}
							nodeDefinitionsMap={nodeDefinitionsMap}
							nodeDefinitions={nodeDefinitions}
						/>
					)}
				</Spin>
			</Edit>
		</div>
	)
}

export const WorkflowRulesEdit = memo(EditComponent)
