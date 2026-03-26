import { memo } from 'react'
import { useTable } from '@refinedev/antd'
import { Table, Spin, Button, TableProps, Card } from 'antd'
import { HttpError, useCreate, useNavigation } from '@refinedev/core'
import { TWorkflowRuleRecord } from '@/pages/WorkflowRules/types'
import useColumns from '@/pages/WorkflowRules/List/hooks/useColumns'
import { PlusOutlined } from '@ant-design/icons'
import DeleteButton from './DeleteButton'
import { TEnv } from '@/types'
import {
	useRowSelection,
	getDefaultPaginationProps,
	defaultTableProps,
	useEnv,
} from 'antd-toolkit'
import { objToCrudFilters } from 'antd-toolkit/refine'

/**
 * 自動化規則列表表格
 * 包含新增、批量刪除功能
 */
const Main = () => {
	const env = useEnv<TEnv>()
	const { WORKFLOW_RULE_POST_TYPE } = env
	const { edit } = useNavigation()

	const { tableProps } = useTable<TWorkflowRuleRecord, HttpError>({
		resource: 'posts',
		filters: {
			permanent: objToCrudFilters({
				post_type: WORKFLOW_RULE_POST_TYPE,
				meta_keys: ['trigger_point', 'nodes'],
			}),
			defaultBehavior: 'replace',
		},
	})

	const { rowSelection, selectedRowKeys, setSelectedRowKeys } =
		useRowSelection<TWorkflowRuleRecord>()

	const columns = useColumns()

	const { mutate: create, isLoading: isCreating } = useCreate({
		resource: 'posts',
		invalidates: ['list'],
		meta: {
			headers: { 'Content-Type': 'multipart/form-data;' },
		},
	})

	/** 建立空白規則後跳轉至編輯頁 */
	const createWorkflowRule = () => {
		create(
			{
				values: {
					name: '新自動化規則',
					post_type: WORKFLOW_RULE_POST_TYPE,
				},
			},
			{
				onSuccess: (data) => {
					const newId = data?.data?.id
					if (newId) {
						edit('workflow-rules', newId)
					}
				},
			},
		)
	}

	return (
		<Spin spinning={tableProps?.loading as boolean}>
			<Card>
				<div className="mb-4 flex justify-between">
					<Button
						loading={isCreating}
						type="primary"
						icon={<PlusOutlined />}
						onClick={createWorkflowRule}
					>
						新增自動化規則
					</Button>
					<DeleteButton
						selectedRowKeys={selectedRowKeys}
						setSelectedRowKeys={setSelectedRowKeys}
					/>
				</div>
				<Table
					{...(defaultTableProps as unknown as TableProps<TWorkflowRuleRecord>)}
					{...tableProps}
					pagination={{
						...tableProps.pagination,
						...getDefaultPaginationProps({ label: '規則' }),
					}}
					rowSelection={
						rowSelection as TableProps<TWorkflowRuleRecord>['rowSelection']
					}
					columns={columns}
					rowKey={(record: TWorkflowRuleRecord) => record.id.toString()}
				/>
			</Card>
		</Spin>
	)
}

export default memo(Main)
