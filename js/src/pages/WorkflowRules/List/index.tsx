import { memo } from 'react'
import Table from './Table'
import { List } from '@refinedev/antd'

/**
 * 自動化規則列表頁面
 * 顯示所有 WorkflowRule 的列表
 */
const ListComponent = () => {
	return (
		<List title="">
			<Table />
		</List>
	)
}

export const WorkflowRulesList = memo(ListComponent)
