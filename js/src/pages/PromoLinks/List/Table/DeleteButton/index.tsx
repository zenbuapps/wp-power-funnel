import { memo, useState } from 'react'
import { useModal } from '@refinedev/antd'
import { Button, Alert, Modal, Input } from 'antd'
import { DeleteOutlined } from '@ant-design/icons'
import { trim } from 'lodash-es'
import { useDeleteMany } from '@refinedev/core'

const CONFIRM_WORD = '沒錯，誰來阻止我都沒有用，我就是要刪 LINE 連結'

const DeleteButton = ({
	selectedRowKeys,
	setSelectedRowKeys,
}: {
	selectedRowKeys: React.Key[]
	setSelectedRowKeys: React.Dispatch<React.SetStateAction<React.Key[]>>
}) => {
	const { show, modalProps, close } = useModal()
	const [value, setValue] = useState('')
	const { mutate: deleteMany, isLoading: isDeleting } = useDeleteMany()

	return (
		<>
			<Button
				type="primary"
				danger
				icon={<DeleteOutlined />}
				onClick={show}
				disabled={!selectedRowKeys.length}
				className="m-0"
			>
				批量刪除 LINE 連結
				{selectedRowKeys.length ? ` (${selectedRowKeys.length})` : ''}
			</Button>

			<Modal
				{...modalProps}
				title={`刪除 LINE 連結 ${selectedRowKeys.map((id) => `#${id}`).join(', ')}`}
				centered
				okButtonProps={{
					danger: true,
					disabled: trim(value) !== CONFIRM_WORD,
				}}
				okText="我已知曉影響，確認刪除"
				cancelText="取消"
				onOk={() => {
					deleteMany(
						{
							resource: 'posts',
							ids: selectedRowKeys as string[],
							mutationMode: 'optimistic',
							successNotification: (_data, ids, _resource) => {
								return {
									message: ` LINE 連結 ${ids?.map((id) => `#${id}`).join(', ')} 已刪除成功`,
									type: 'success',
								}
							},
							errorNotification: (_data, _ids, _resource) => {
								return {
									message: 'OOPS，出錯了，請在試一次',
									type: 'error',
								}
							},
						},
						{
							onSuccess: () => {
								close()
								setSelectedRowKeys([])
							},
						},
					)
				}}
				confirmLoading={isDeleting}
			>
				<Alert
					message="危險操作"
					className="mb-2"
					description={
						<>
							<p>刪除 LINE 連結影響範圍包含:</p>
							<ol className="pl-6">
								<li>用戶點擊連結將沒有任何作用</li>
							</ol>
						</>
					}
					type="error"
					showIcon
				/>
				<p className="mb-2">
					您確定要這麼做嗎? 如果您已經知曉刪除 LINE
					連結帶來的影響，並仍想要刪除這些 LINE 連結，請在下方輸入框輸入{' '}
					<b className="italic">{CONFIRM_WORD}</b>{' '}
				</p>
				<Input
					allowClear
					value={value}
					onChange={(e: any) => setValue(e.target.value)}
					placeholder="請輸入上述文字"
					className="mb-2"
				/>
			</Modal>
		</>
	)
}

export default memo(DeleteButton)
