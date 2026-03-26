import { memo } from 'react'
import { Button, Popconfirm } from 'antd'
import { DeleteOutlined } from '@ant-design/icons'
import { useDeleteMany } from '@refinedev/core'

type TDeleteButtonProps = {
	/** 已選取的列 key 陣列 */
	selectedRowKeys: React.Key[]
	/** 設定已選取列 key 的 setter */
	setSelectedRowKeys: React.Dispatch<React.SetStateAction<React.Key[]>>
}

/**
 * 批量刪除按鈕元件
 * 使用 Popconfirm 確認後執行批量刪除
 */
const DeleteButton = ({
	selectedRowKeys,
	setSelectedRowKeys,
}: TDeleteButtonProps) => {
	const { mutate: deleteMany, isLoading: isDeleting } = useDeleteMany()

	const handleConfirm = () => {
		deleteMany(
			{
				resource: 'posts',
				ids: selectedRowKeys as string[],
				mutationMode: 'optimistic',
				successNotification: (_data, ids) => ({
					message: `自動化規則 ${ids?.map((id) => `#${id}`).join(', ')} 已刪除成功`,
					type: 'success',
				}),
				errorNotification: () => ({
					message: '刪除失敗，請再試一次',
					type: 'error',
				}),
			},
			{
				onSuccess: () => {
					setSelectedRowKeys([])
				},
			},
		)
	}

	return (
		<Popconfirm
			title="確認刪除"
			description={`確定要刪除 ${selectedRowKeys.length} 條自動化規則嗎？`}
			onConfirm={handleConfirm}
			okText="確認刪除"
			cancelText="取消"
			okButtonProps={{ danger: true, loading: isDeleting }}
		>
			<Button
				type="primary"
				danger
				icon={<DeleteOutlined />}
				disabled={!selectedRowKeys.length}
				className="m-0"
			>
				批量刪除
				{selectedRowKeys.length ? ` (${selectedRowKeys.length})` : ''}
			</Button>
		</Popconfirm>
	)
}

export default memo(DeleteButton)
