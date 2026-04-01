import { Table, TableProps, Tag } from 'antd'
import { TWorkflowRuleRecord } from '@/pages/WorkflowRules/types'
import { ProductName } from 'antd-toolkit/wp'
import { useNavigation } from '@refinedev/core'
import dayjs from 'dayjs'
import useTriggerPoints from '@/pages/WorkflowRules/hooks/useTriggerPoints'

/** 狀態對應的 Tag 顏色 */
const STATUS_COLOR_MAP: Record<string, string> = {
	publish: 'green',
	draft: 'default',
	trash: 'red',
}

/** 狀態對應的標籤文字 */
const STATUS_LABEL_MAP: Record<string, string> = {
	publish: '已發佈',
	draft: '草稿',
	trash: '已刪除',
}

/**
 * 自動化規則列表欄位定義
 * 包含：標題、觸發點、狀態、節點數量、建立日期、修改日期
 */
const useColumns = () => {
	const { edit } = useNavigation()
	const { labelMap: triggerPointLabelMap } = useTriggerPoints()

	const onClick = (record: TWorkflowRuleRecord) => () => {
		edit('workflow-rules', record.id)
	}

	const columns: TableProps<TWorkflowRuleRecord>['columns'] = [
		Table.SELECTION_COLUMN,
		{
			title: '規則名稱',
			dataIndex: 'name',
			width: 260,
			render: (_, record) => (
				<ProductName
					hideImage={true}
					record={record}
					onClick={onClick(record)}
				/>
			),
		},
		{
			title: '觸發點',
			dataIndex: 'trigger_point',
			width: 160,
			render: (value: string) => {
				if (!value) return <span className="text-gray-400">未設定</span>
				return <Tag color="blue">{triggerPointLabelMap[value] ?? value}</Tag>
			},
		},
		{
			title: '狀態',
			dataIndex: 'status',
			width: 100,
			render: (value: string) => (
				<Tag color={STATUS_COLOR_MAP[value] ?? 'default'}>
					{STATUS_LABEL_MAP[value] ?? value}
				</Tag>
			),
		},
		{
			title: '節點數量',
			dataIndex: 'nodes',
			width: 100,
			render: (nodes: TWorkflowRuleRecord['nodes']) => {
				let parsed = nodes
				if (typeof nodes === 'string') {
					try {
						parsed = JSON.parse(nodes)
					} catch {
						parsed = []
					}
				}
				const count = Array.isArray(parsed) ? parsed.length : 0
				return <span>{count} 個節點</span>
			},
		},
		{
			title: '建立日期',
			dataIndex: 'date_created',
			width: 160,
			render: (value: string) =>
				value ? dayjs(value).format('YYYY-MM-DD HH:mm') : '-',
		},
		{
			title: '修改日期',
			dataIndex: 'date_modified',
			width: 160,
			render: (value: string) =>
				value ? dayjs(value).format('YYYY-MM-DD HH:mm') : '-',
		},
	]

	return columns
}

export default useColumns
