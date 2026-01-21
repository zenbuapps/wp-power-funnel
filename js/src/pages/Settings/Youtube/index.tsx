import { Heading } from 'antd-toolkit'
import { memo } from 'react'
import { Button, Form, FormItemProps, Input, Popconfirm, Tag } from 'antd'
import { SimpleImage } from 'antd-toolkit'
import useOptions from '@/pages/Settings/hooks/useOptions'
import { CheckCircleOutlined, CloseCircleOutlined } from '@ant-design/icons'
import { SITE_URL } from '@/utils'

const { Item } = Form

const youtubeFields: FormItemProps[] = [
	{
		name: 'channelId',
		label: 'Channel Id',
	},
	{
		name: 'clientId',
		label: 'Client Id',
	},
	{
		name: 'clientSecret',
		label: 'Client Secret',
	},
]

const index = () => {
	const form = Form.useFormInstance()
	const { data } = useOptions({ form })
	const googleOauth = data?.data?.data?.googleOauth
	const isAuthorized = !!googleOauth?.isAuthorized
	const authUrl = googleOauth?.authUrl
	const handleRevoke = () => {}

	return (
		<div className="flex flex-col md:flex-row gap-8">
			<div className="w-full max-w-[400px]">
				<Heading className="mt-8">Google OAuth 連接狀態</Heading>
				{isAuthorized && (
					<>
						<Tag
							icon={<CheckCircleOutlined />}
							color="success"
							className="py-[1px]"
						>
							Google OAuth 授權成功
						</Tag>
						<Popconfirm
							title="撤銷授權"
							description="撤銷授權後，用戶將收不到 Youtube 直播活動場次訊息，下方資訊也需要重新輸入"
							onConfirm={handleRevoke}
							okText="確認"
							cancelText="取消"
						>
							<Button color="danger" variant="outlined" size="small">
								撤銷授權
							</Button>
						</Popconfirm>
					</>
				)}

				{!isAuthorized && (
					<>
						<Tag
							icon={<CloseCircleOutlined />}
							color="error"
							className="py-[1px]"
						>
							Google OAuth 尚未授權
						</Tag>
						<Button
							color="primary"
							variant="outlined"
							size="small"
							target="_blank"
							href={authUrl}
							rel="noreferrer noopener"
							disabled={!authUrl}
						>
							前往授權
						</Button>
					</>
				)}

				<Heading className="mt-8">Youtube 設定</Heading>
				{youtubeFields.map(({ name, label }) => (
					<Item key={name} name={['youtube', name]} label={label}>
						<Input allowClear disabled={isAuthorized} />
					</Item>
				))}
			</div>
			<div className="flex-1 h-auto md:h-[calc(100%-5.375rem)] md:overflow-y-auto">
				<Heading className="mt-8">創建 Line Login 並新增 Liff APP</Heading>
				<p>
					1. 前往{' '}
					<a href="https://developers.line.biz/console/" target="_blank">
						Line Console
					</a>{' '}
					創建 Provider
				</p>
				<SimpleImage
					src={`${SITE_URL}/wp-content/plugins/power-`}
					ratio="aspect-[2.1]"
				/>
			</div>
		</div>
	)
}

export default memo(index)
