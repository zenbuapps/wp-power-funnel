import { memo } from 'react'
import { Form, Input } from 'antd'

const { Item } = Form
const { TextArea } = Input

type TEmailFormProps = {
	/** 節點參數 */
	args: Record<string, unknown>
}

/**
 * Email 節點設定表單
 * 設定收件人、主旨、內文等參數
 */
const EmailForm = memo(({ args }: TEmailFormProps) => {
	return (
		<>
			<Item
				label="收件人"
				name={['args', 'to']}
				initialValue={(args?.to as string) ?? ''}
				rules={[{ type: 'email', message: '請輸入有效的 Email' }]}
			>
				<Input placeholder="user@example.com" allowClear />
			</Item>
			<Item
				label="主旨"
				name={['args', 'subject']}
				initialValue={(args?.subject as string) ?? ''}
			>
				<Input placeholder="Email 主旨" allowClear />
			</Item>
			<Item
				label="內文"
				name={['args', 'body']}
				initialValue={(args?.body as string) ?? ''}
			>
				<TextArea rows={4} placeholder="Email 內容" />
			</Item>
		</>
	)
})

EmailForm.displayName = 'EmailForm'

export default EmailForm
