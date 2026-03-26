import { memo } from 'react'
import { Form, Input, Alert } from 'antd'

const { Item } = Form
const { TextArea } = Input

type TDefaultFormProps = {
	/** 節點參數 */
	args: Record<string, unknown>
	/** 節點模組名稱 */
	nodeModule: string
}

/**
 * 預設節點設定表單
 * 適用於尚未實作專用表單的節點類型
 * 提供基本的 JSON 參數編輯能力
 */
const DefaultForm = memo(({ args, nodeModule }: TDefaultFormProps) => {
	return (
		<>
			<Alert
				message={`「${nodeModule}」節點的詳細設定表單尚在開發中`}
				type="info"
				showIcon
				className="mb-4"
			/>
			<Item
				label="自訂參數 (JSON)"
				name={['args', '_raw']}
				initialValue={JSON.stringify(args ?? {}, null, 2)}
			>
				<TextArea rows={6} placeholder='{"key": "value"}' />
			</Item>
		</>
	)
})

DefaultForm.displayName = 'DefaultForm'

export default DefaultForm
