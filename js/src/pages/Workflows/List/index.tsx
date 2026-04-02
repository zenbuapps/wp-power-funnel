import { memo, useState, useCallback, useMemo } from 'react'
import {
	Table,
	Tag,
	Card,
	Select,
	Input,
	Space,
	Button,
	Spin,
	type TableProps,
} from 'antd'
import { EyeOutlined } from '@ant-design/icons'
import { List } from '@refinedev/antd'
import { useCustom, useApiUrl, useNavigation } from '@refinedev/core'
import dayjs from 'dayjs'
import useTriggerPoints from '@/pages/WorkflowRules/hooks/useTriggerPoints'
import {
	WORKFLOW_STATUS,
	WORKFLOW_STATUS_COLOR,
	WORKFLOW_STATUS_LABEL,
	type TWorkflowListItem,
	type TWorkflowListResponse,
	type TWorkflowStatus,
} from '../types'

/** 篩選狀態 */
type TFilters = {
	status: TWorkflowStatus[]
	triggerPoint: string
	workflowRuleId: string
	search: string
}

/** 排序狀態 */
type TSorter = {
	field: string
	order: 'asc' | 'desc'
}

/** 預設篩選值 */
const DEFAULT_FILTERS: TFilters = {
	status: [],
	triggerPoint: '',
	workflowRuleId: '',
	search: '',
}

/** 狀態篩選選項 */
const STATUS_OPTIONS = Object.values(WORKFLOW_STATUS).map((value) => ({
	label: WORKFLOW_STATUS_LABEL[value],
	value,
}))

/**
 * Workflow 執行清單頁面
 * 顯示所有 Workflow 執行實例，支援篩選、搜尋、排序和分頁
 */
const WorkflowsListComponent = () => {
	const apiUrl = useApiUrl('power-funnel')
	const { show, edit } = useNavigation()
	const {
		groupedOptions,
		labelMap: triggerPointLabelMap,
		groupLabelMap: triggerPointGroupLabelMap,
	} = useTriggerPoints()

	/** 分頁狀態 */
	const [currentPage, setCurrentPage] = useState(1)
	const [pageSize, setPageSize] = useState(10)

	/** 篩選狀態 */
	const [filters, setFilters] = useState<TFilters>(DEFAULT_FILTERS)

	/** 排序狀態 */
	const [sorter, setSorter] = useState<TSorter>({
		field: 'created_at',
		order: 'desc',
	})

	/** 組合查詢參數 */
	const queryConfig = useMemo(() => {
		const params: Record<string, unknown> = {
			page: currentPage,
			per_page: pageSize,
			orderby: sorter.field,
			order: sorter.order,
		}
		if (filters.status.length > 0) {
			params.status = filters.status.join(',')
		}
		if (filters.triggerPoint) {
			params.trigger_point = filters.triggerPoint
		}
		if (filters.workflowRuleId) {
			params.workflow_rule_id = filters.workflowRuleId
		}
		if (filters.search) {
			params.search = filters.search
		}
		return params
	}, [currentPage, pageSize, filters, sorter])

	/** 呼叫清單 API */
	const { data, isLoading } = useCustom<TWorkflowListResponse>({
		url: `${apiUrl}/workflows`,
		method: 'get',
		config: {
			query: queryConfig,
		},
		queryOptions: {
			queryKey: ['workflows_list', queryConfig],
		},
	})

	const items: TWorkflowListItem[] = data?.data?.data?.items ?? []
	const pagination = data?.data?.data?.pagination

	/** 篩選變更處理 */
	const handleStatusChange = useCallback((value: TWorkflowStatus[]) => {
		setFilters((prev) => ({ ...prev, status: value }))
		setCurrentPage(1)
	}, [])

	const handleTriggerPointChange = useCallback((value: string) => {
		setFilters((prev) => ({ ...prev, triggerPoint: value || '' }))
		setCurrentPage(1)
	}, [])

	const handleSearch = useCallback((value: string) => {
		setFilters((prev) => ({ ...prev, search: value }))
		setCurrentPage(1)
	}, [])

	/** 表格排序變更 */
	const handleTableChange: TableProps<TWorkflowListItem>['onChange'] =
		useCallback(
			(_pagination: unknown, _filters: unknown, tableSorter: unknown) => {
				const s = tableSorter as {
					field?: string
					order?: 'ascend' | 'descend' | null
				}
				if (s.field && s.order) {
					const orderMap: Record<string, 'asc' | 'desc'> = {
						ascend: 'asc',
						descend: 'desc',
					}
					setSorter({
						field: s.field as string,
						order: orderMap[s.order] ?? 'desc',
					})
				}
			},
			[],
		)

	/** 分頁變更 */
	const handlePaginationChange = useCallback((page: number, size: number) => {
		setCurrentPage(page)
		setPageSize(size)
	}, [])

	/** 表格欄位定義 */
	const columns: TableProps<TWorkflowListItem>['columns'] = useMemo(
		() => [
			{
				title: 'ID',
				dataIndex: 'workflowId',
				key: 'workflowId',
				width: 80,
				render: (value: string) => value || '-',
			},
			{
				title: '觸發時間',
				dataIndex: 'createdAt',
				key: 'created_at',
				width: 180,
				sorter: true,
				defaultSortOrder: 'descend' as const,
				render: (value: string) =>
					value ? dayjs(value).format('YYYY-MM-DD HH:mm:ss') : '-',
			},
			{
				title: '來源規則',
				dataIndex: 'workflowRuleTitle',
				key: 'workflowRuleTitle',
				width: 200,
				render: (_: unknown, record: TWorkflowListItem) => (
					<a
						onClick={() => edit('workflow-rules', record.workflowRuleId)}
						className="cursor-pointer text-blue-500 hover:text-blue-600"
					>
						{record.workflowRuleTitle}
					</a>
				),
			},
			{
				title: '觸發用戶',
				dataIndex: 'userDisplayName',
				key: 'userDisplayName',
				width: 120,
				render: (value: string) => value || '訪客',
			},
			{
				title: '觸發點',
				dataIndex: 'triggerPoint',
				key: 'triggerPoint',
				width: 180,
				render: (value: string) => {
					if (!value) return <span className="text-gray-400">-</span>
					return <Tag color="blue">{triggerPointLabelMap[value] ?? value}</Tag>
				},
			},
			{
				title: '狀態',
				dataIndex: 'status',
				key: 'status',
				width: 100,
				sorter: true,
				render: (value: TWorkflowStatus) => (
					<Tag color={WORKFLOW_STATUS_COLOR[value] ?? 'default'}>
						{WORKFLOW_STATUS_LABEL[value] ?? value}
					</Tag>
				),
			},
			{
				title: '節點進度',
				dataIndex: 'nodeProgress',
				key: 'nodeProgress',
				width: 100,
				render: (value: string) => value || '-',
			},
			{
				title: '耗時',
				dataIndex: 'duration',
				key: 'duration',
				width: 100,
				sorter: true,
				render: (value: string) => value || '-',
			},
			{
				title: '操作',
				key: 'actions',
				width: 100,
				render: (_: unknown, record: TWorkflowListItem) => (
					<Button
						type="link"
						icon={<EyeOutlined />}
						onClick={() => show('workflows', record.workflowId)}
					>
						查看
					</Button>
				),
			},
		],
		[triggerPointLabelMap, edit, show],
	)

	return (
		<List title="">
			<Spin spinning={isLoading}>
				<Card>
					{/* 篩選列 */}
					<Space wrap className="mb-4 w-full">
						<Select
							mode="multiple"
							placeholder="篩選狀態"
							options={STATUS_OPTIONS}
							value={filters.status}
							onChange={handleStatusChange}
							style={{ minWidth: 200 }}
							allowClear
						/>
						<Select
							placeholder="篩選觸發點"
							options={groupedOptions}
							value={filters.triggerPoint || undefined}
							onChange={handleTriggerPointChange}
							style={{ minWidth: 200 }}
							allowClear
							showSearch
							optionFilterProp="label"
							filterOption={(input, option) => {
								const lower = input.toLowerCase()
								const labelMatch =
									(option?.label as string)?.toLowerCase().includes(lower) ??
									false
								const groupLabel =
									triggerPointGroupLabelMap[option?.value as string] ?? ''
								const groupMatch = groupLabel.toLowerCase().includes(lower)
								return labelMatch || groupMatch
							}}
						/>
						<Input.Search
							placeholder="搜尋訊息..."
							onSearch={handleSearch}
							style={{ width: 240 }}
							allowClear
						/>
					</Space>

					{/* 資料表格 */}
					<Table<TWorkflowListItem>
						dataSource={items}
						columns={columns}
						rowKey={(record) => record.workflowId}
						onChange={handleTableChange}
						pagination={{
							current: currentPage,
							pageSize,
							total: pagination?.total ?? 0,
							showSizeChanger: true,
							showTotal: (total) => `共 ${total} 筆`,
							onChange: handlePaginationChange,
						}}
						scroll={{ x: 1160 }}
						size="middle"
					/>
				</Card>
			</Spin>
		</List>
	)
}

export const WorkflowsList = memo(WorkflowsListComponent)
