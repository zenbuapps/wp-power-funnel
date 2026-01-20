import { memo } from 'react'
import { useTable } from '@refinedev/antd'
import { Table, Spin, Button, TableProps, Card } from 'antd'
import { HttpError, useCreate } from '@refinedev/core'
import { TPromoLinkRecord } from '@/pages/PromoLinks/types'
import useColumns from '@/pages/PromoLinks/List/hooks/useColumns'
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

const Main = () => {
	const env = useEnv<TEnv>()
	const { PROMO_LINK_POST_TYPE } = env
	const { tableProps } = useTable<TPromoLinkRecord, HttpError>({
		resource: 'posts',
		filters: {
			permanent: objToCrudFilters({
				post_type: PROMO_LINK_POST_TYPE,
				meta_keys: ['keyword', 'last_n_days', 'alt_text', 'action_label'],
			}),
			defaultBehavior: 'replace',
		},
	})

	const { rowSelection, selectedRowKeys, setSelectedRowKeys } =
		useRowSelection<TPromoLinkRecord>()

	const columns = useColumns()

	const { mutate: create, isLoading: isCreating } = useCreate({
		resource: 'posts',
		invalidates: ['list'],
		meta: {
			headers: { 'Content-Type': 'multipart/form-data;' },
		},
	})

	const createPromoLink = () => {
		create({
			values: {
				name: '新 LINE 連結',
				post_type: PROMO_LINK_POST_TYPE,
			},
		})
	}

	return (
		<Spin spinning={tableProps?.loading as boolean}>
			<Card>
				<div className="mb-4 flex justify-between">
					<Button
						loading={isCreating}
						type="primary"
						icon={<PlusOutlined />}
						onClick={createPromoLink}
					>
						新增 LINE 連結
					</Button>
					<DeleteButton
						selectedRowKeys={selectedRowKeys}
						setSelectedRowKeys={setSelectedRowKeys}
					/>
				</div>
				<Table
					{...(defaultTableProps as unknown as TableProps<TPromoLinkRecord>)}
					{...tableProps}
					pagination={{
						...tableProps.pagination,
						...getDefaultPaginationProps({ label: '連結' }),
					}}
					rowSelection={rowSelection}
					columns={columns}
					rowKey={(record: TPromoLinkRecord) => record.id.toString()}
				/>
			</Card>
		</Spin>
	)
}

export default memo(Main)
