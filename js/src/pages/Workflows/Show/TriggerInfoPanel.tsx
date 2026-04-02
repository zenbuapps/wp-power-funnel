import { memo, useMemo } from 'react'
import { Collapse, Descriptions, Table, Tag, type TableProps } from 'antd'
import { useNavigation } from '@refinedev/core'
import type { TContextCallableSet } from '../types'

type TTriggerInfoPanelProps = {
	triggerPoint: string
	triggerPointLabel: string
	workflowRuleId: string
	workflowRuleTitle: string
	contextCallableSet: TContextCallableSet
	context: Record<string, string>
}

/** Context 表格的資料型別 */
type TContextRow = {
	key: string
	value: string
}

/** Context 表格欄位 */
const CONTEXT_COLUMNS: TableProps<TContextRow>['columns'] = [
	{
		title: 'Key',
		dataIndex: 'key',
		width: 200,
	},
	{
		title: 'Value',
		dataIndex: 'value',
	},
]

/**
 * 將 contextCallableSet 轉為人類可讀字串
 * 例如 "TriggerPointService::resolve_registration_context(123)"
 *
 * @param {TContextCallableSet} callableSet context callable set 結構
 */
const formatCallable = (callableSet?: TContextCallableSet): string => {
	if (!callableSet?.callable) return '-'
	const { callable, params = [] } = callableSet
	const paramsStr = params.map((p) => JSON.stringify(p)).join(', ')
	return `${callable}(${paramsStr})`
}

/**
 * 簡化版觸發資訊面板
 * 以 Collapse 呈現觸發點、來源規則、context 來源、resolved context
 */
const TriggerInfoPanel = memo(
	({
		triggerPoint,
		triggerPointLabel,
		workflowRuleId,
		workflowRuleTitle,
		contextCallableSet,
		context,
	}: TTriggerInfoPanelProps) => {
		const { edit } = useNavigation()

		/** 將 context 物件轉為表格資料 */
		const contextRows: TContextRow[] = useMemo(
			() =>
				Object.entries(context).map(([key, value]) => ({
					key,
					value,
				})),
			[context],
		)

		/** 人類可讀的 callable 字串 */
		const callableDisplay = useMemo(
			() => formatCallable(contextCallableSet),
			[contextCallableSet],
		)

		const collapseItems = [
			{
				key: 'trigger-info',
				label: '觸發資訊',
				children: (
					<div className="space-y-4">
						<Descriptions column={1} bordered size="small">
							<Descriptions.Item label="觸發點">
								<Tag color="blue">{triggerPointLabel}</Tag>
								<code className="ml-2 text-xs text-gray-500">
									{triggerPoint}
								</code>
							</Descriptions.Item>
							<Descriptions.Item label="來源規則">
								<a
									onClick={() => edit('workflow-rules', workflowRuleId)}
									className="cursor-pointer text-blue-500 hover:text-blue-600"
								>
									{workflowRuleTitle}
								</a>
								<span className="ml-2 text-xs text-gray-400">
									(ID: {workflowRuleId})
								</span>
							</Descriptions.Item>
							<Descriptions.Item label="Context 來源">
								<code className="text-xs">{callableDisplay}</code>
							</Descriptions.Item>
						</Descriptions>

						{contextRows.length > 0 && (
							<div>
								<h4 className="mb-2 text-sm font-medium">Resolved Context</h4>
								<Table<TContextRow>
									dataSource={contextRows}
									columns={CONTEXT_COLUMNS}
									rowKey="key"
									pagination={false}
									size="small"
									bordered
								/>
							</div>
						)}
					</div>
				),
			},
		]

		return (
			<Collapse items={collapseItems} defaultActiveKey={['trigger-info']} />
		)
	},
)

TriggerInfoPanel.displayName = 'TriggerInfoPanel'

export default TriggerInfoPanel
