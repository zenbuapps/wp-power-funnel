import { memo } from 'react'
import { Form, FormItemProps, Input } from 'antd'
import { Heading, SimpleImage } from 'antd-toolkit'
import { SITE_URL } from '@/utils'

const { Item } = Form
const LINE_FIELDS: FormItemProps[] = [
	{
		name: 'liff_id',
		label: 'Liff Id',
	},
	{
		name: 'channel_id',
		label: 'Channel Id',
	},
	{
		name: 'channel_secret',
		label: 'Channel Secret',
	},
	{
		name: 'channel_access_token',
		label: 'Channel Access Token',
	},
]

const TUTORIALS: React.ReactNode[][] = [
	[
		<>
			前往{' '}
			<a href="https://developers.line.biz/console/" target="_blank">
				Line Console
			</a>{' '}
			創建 Provider
		</>,
	],
	['輸入 Provider 名稱'],
	['創建 Line Login Channel'],
	['勾選 Web app'],
	['前往 Line Login'],
	['看到 Channel ID 先不用理會，不是這個'],
	['看到 Channel secret 先不用理會，不是這個'],
	['新增 Liff App'],
	[
		'Size 選擇 Full (推薦)',
		`Endpoint 填寫 ${SITE_URL}/liff`,
		'Scope 選擇 openid & profile',
	],
	['新增好友選項，選擇任何一個 On 都可以'],
	['複製 Liff ID'],
	[
		<>
			前往{' '}
			<a href="https://developers.line.biz/console/" target="_blank">
				Line Console
			</a>
		</>,
		'選擇要連接的 Line 官方帳號',
	],
	['點擊設定'],
	['啟用 Message API'],
	['選擇服務提供者，即剛剛創建的 Line Login'],
	['填寫完資料，確認'],
	[
		'複製 Channel ID & Channel secret',
		`輸入 ${SITE_URL}/wp-json/power-funnel/line-callback`,
	],
	[
		<>
			回到{' '}
			<a href="https://developers.line.biz/console/" target="_blank">
				Line Console
			</a>
		</>,
	],
	['將 Liff APP 連接 Line 官方帳號'],
	[
		<>
			回到{' '}
			<a href="https://developers.line.biz/console/" target="_blank">
				Line Console
			</a>
		</>,
	],
	['前往 Message API'],
	['發行 Access Token'],
	['複製 Channel access token'],
]

const HEADINGS = {
	1: '創建 Line Login 並新增 Liff APP',
	12: '啟用 Line 官方帳號 Message API',
	18: '將 Line Login 連接到你的官方帳號',
	20: '發行 Line Login Access Token',
}

const index = () => {
	return (
		<div className="flex flex-col md:flex-row gap-8">
			<div className="w-full max-w-[400px]">
				<Heading className="mt-8">Line Api 設定</Heading>
				{LINE_FIELDS.map(({ name, label }) => (
					<Item key={name} name={['line', name]} label={label}>
						<Input allowClear />
					</Item>
				))}
			</div>
			<div className="flex-1 md:h-[calc(100vh-8rem)] md:overflow-y-auto">
				{TUTORIALS.map((items, index) => {
					const order = index + 1
					// @ts-ignore
					const heading: string | undefined = HEADINGS[order] || undefined
					return (
						<>
							{heading && <Heading className="mt-8">{heading}</Heading>}
							{items.map((item, i) => (
								<p>
									{i === 0 && `${order}. `}
									{item}
								</p>
							))}
							<SimpleImage
								src={`${SITE_URL}/wp-content/plugins/power-funnel/inc/assets/line/${String(order).padStart(2, '0')}.jpg`}
								ratio="aspect-[2.1]"
								className="mb-8"
							/>
						</>
					)
				})}
			</div>
		</div>
	)
}

export default memo(index)
