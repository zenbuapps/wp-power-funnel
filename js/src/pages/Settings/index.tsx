import { Button, Form, Tabs, TabsProps } from 'antd'
import { memo } from 'react'
import Line from './Line'
import Youtube from './Youtube'
import useOptions from '@/pages/Settings/hooks/useOptions'
import useSave from '@/pages/Settings/hooks/useSave'

const items: TabsProps['items'] = [
	{
		key: 'line',
		label: 'Line Api 設定',
		children: <Line />,
	},
	{
		key: 'youtube',
		label: 'Youtube Api 設定',
		children: <Youtube />,
	},
]

const SettingsPage = () => {
	const [form] = Form.useForm()
	const { handleSave, mutation } = useSave({ form })
	const { isLoading: isSaveLoading } = mutation
	const { isLoading: isGetLoading } = useOptions({ form })

	return (
		<Form layout="vertical" form={form} onFinish={handleSave}>
			<Tabs
				tabBarExtraContent={{
					left: (
						<Button
							className="mr-8"
							type="primary"
							htmlType="submit"
							loading={isSaveLoading}
							disabled={isGetLoading}
						>
							儲存
						</Button>
					),
				}}
				defaultActiveKey="line"
				items={items}
			/>
		</Form>
	)
}

export const Settings = memo(SettingsPage)
