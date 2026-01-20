import { memo } from 'react'
import Table from './Table'
import { List } from '@refinedev/antd'

const ListComponent = () => {
	return (
		<List title="">
			<Table />
		</List>
	)
}

export const PromoLinksList = memo(ListComponent)
