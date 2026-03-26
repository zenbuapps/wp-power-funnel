import { memo } from 'react'
import { Form, InputNumber, Select } from 'antd'

const { Item } = Form

type TWaitFormProps = {
	/** 節點參數 */
	args: Record<string, unknown>
}

/** 等待時間單位選項 */
const WAIT_UNIT_OPTIONS = [
	{ label: '分鐘', value: 'minutes' },
	{ label: '小時', value: 'hours' },
	{ label: '天', value: 'days' },
]

/**
 * Wait 節點設定表單
 * 設定等待時間和單位
 */
const WaitForm = memo(({ args }: TWaitFormProps) => {
	return (
		<>
			<Item
				label="等待時間"
				name={['args', 'duration']}
				initialValue={(args?.duration as number) ?? 1}
			>
				<InputNumber min={1} className="w-full" />
			</Item>
			<Item
				label="時間單位"
				name={['args', 'unit']}
				initialValue={(args?.unit as string) ?? 'hours'}
			>
				<Select options={WAIT_UNIT_OPTIONS} />
			</Item>
		</>
	)
})

WaitForm.displayName = 'WaitForm'

export default WaitForm
