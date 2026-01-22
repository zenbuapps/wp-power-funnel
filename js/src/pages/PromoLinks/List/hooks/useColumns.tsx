import { Table, TableProps } from 'antd'
import { TPromoLinkRecord } from '@/pages/PromoLinks/types'
import { ProductName } from 'antd-toolkit/wp'
import { useNavigation } from '@refinedev/core'
import { LiffUrl } from '@/components'

const useColumns = () => {
	const { edit } = useNavigation()
	const onClick = (record: TPromoLinkRecord) => () => {
		edit('promo-links', record.id)
	}
	const columns: TableProps<TPromoLinkRecord>['columns'] = [
		Table.SELECTION_COLUMN,
		{
			title: '商品名稱',
			dataIndex: 'name',
			width: 300,
			render: (_, record) => (
				<ProductName
					hideImage={true}
					record={record}
					onClick={onClick(record)}
				/>
			),
		},
		{
			title: '關鍵字',
			dataIndex: 'keyword',
			width: 80,
		},
		{
			title: '最近 N 天的活動',
			dataIndex: 'last_n_days',
			width: 80,
		},
		{
			title: '操作',
			dataIndex: '_actions',
			width: 160,
			render: (_, record) => <LiffUrl id={record.id} />,
		},
	]

	return columns
}

export default useColumns
