import { memo, useState } from 'react'
import { Edit, useForm } from '@refinedev/antd'
import { TPromoLinkRecord } from '@/pages/PromoLinks/types'
import { HttpError, useParsed, useCustom, useApiUrl } from '@refinedev/core'
import { toFormData, Heading, CopyText } from 'antd-toolkit'
import { objToCrudFilters, notificationProps } from 'antd-toolkit/refine'
import {
	Alert,
	Button,
	Empty,
	Form,
	Input,
	InputNumber,
	Space,
	Spin,
} from 'antd'
import { TActivity } from '@/types/activity'
import { EyeOutlined } from '@ant-design/icons'
import { getLiffLink } from '@/utils'

const { Item } = Form

const EditComponent = () => {
	const { id } = useParsed()

	// 初始化資料
	const { formProps, form, saveButtonProps, query, mutation, onFinish } =
		useForm<TPromoLinkRecord, HttpError, Partial<TPromoLinkRecord>>({
			action: 'edit',
			resource: 'posts',
			id,
			redirect: false,
			...notificationProps,
			queryMeta: {
				variables: {
					meta_keys: ['keyword', 'last_n_days', 'alt_text', 'action_label'], // 額外暴露的欄位
				},
			},
		})

	const record: TPromoLinkRecord | undefined = query?.data?.data

	// 將 [] 轉為 '[]'，例如，清除原本分類時，如果空的，前端會是 undefined，轉成 formData 時會遺失
	const handleOnFinish = () => {
		const values = form.getFieldsValue()
		onFinish(toFormData(values) as Partial<TPromoLinkRecord>)
	}

	// region 預覽功能

	const apiUrl = useApiUrl('power-funnel')
	const [previewParams, setPreviewParams] = useState<
		| {
				keyword?: string
				last_n_days?: number
		  }
		| undefined
	>(undefined)

	const { data, isFetching } = useCustom<TActivity[]>({
		url: `${apiUrl}/activities`,
		method: 'get',
		config: {
			filters: objToCrudFilters(previewParams || {}),
		},
		queryOptions: {
			enabled: !!previewParams,
		},
		errorNotification: notificationProps.errorNotification,
	})

	const activities = data?.data || []

	const handlePreview = () => {
		const { keyword, last_n_days } = form.getFieldsValue()
		setPreviewParams({ keyword, last_n_days })
	}

	// endregion 預覽功能

	const liffLink = getLiffLink(id!.toString())

	return (
		<div className="sticky-card-actions sticky-tabs-nav">
			<Edit
				resource="posts"
				title={
					<>
						{record?.name}{' '}
						<span className="text-gray-400 text-xs">#{record?.id}</span>
					</>
				}
				headerButtons={() => null}
				saveButtonProps={{
					...saveButtonProps,
					children: '儲存',
					icon: null,
					loading: mutation?.isLoading,
					onClick: handleOnFinish,
				}}
				isLoading={query?.isLoading}
			>
				<Form {...formProps} layout="vertical">
					<div className="grid grid-cols-1 xl:grid-cols-2 gap-x-8">
						<div>
							<Heading>基本設定</Heading>
							<Item
								name={['name']}
								label="LINE 連結名稱 (內部識別用)"
								className="mb-10"
							>
								<Input allowClear />
							</Item>
							<Heading>活動篩選條件</Heading>
							<Item name={['keyword']} label="關鍵字">
								<Input allowClear />
							</Item>
							<Item label="顯示最近 N 天的活動">
								<Space.Compact block>
									<Item name={['last_n_days']} noStyle>
										<InputNumber className="w-full" min={0} />
									</Item>
									<Space.Addon>天</Space.Addon>
								</Space.Compact>
							</Item>
							<Button
								color="primary"
								variant="filled"
								onClick={handlePreview}
								icon={<EyeOutlined />}
								loading={isFetching}
								className="mb-10"
							>
								預覽
							</Button>
							<Heading>其他選項</Heading>
							<Item name={['alt_text']} label="替代文字">
								<Input allowClear />
							</Item>
							<Item name={['action_label']} label="操作標籤">
								<Input allowClear />
							</Item>
						</div>
						<Spin spinning={isFetching}>
							<Heading>預覽</Heading>
							<Alert
								message={
									<>
										預覽時會顯示全部活動，但 LINE Message API 有限制最多只會顯示
										10 個
									</>
								}
								type="warning"
								showIcon
								className="mt-0 mb-4"
							/>
							{!!activities.length && (
								<>
									<div className="w-full overflow-x-auto pb-2">
										<div className="flex flex-nowrap gap-x-2 w-fit">
											{activities.map(
												({ id, title, thumbnail_url }: TActivity) => (
													<div
														key={id}
														className="rounded-lg border border-solid border-gray-200 w-[16rem] overflow-hidden"
													>
														<img
															src={thumbnail_url}
															className="w-full aspect-video object-cover"
															alt={title}
														/>
														<div className="px-3 pt-4 pb-8 font-bold">
															{title}
														</div>
														<div className="border-t border-solid border-gray-200 border-x-0 border-b-0 text-center py-4">
															<span className="text-blue-700 hover:text-blue-500 cursor-pointer font-light">
																立即報名
															</span>
														</div>
													</div>
												),
											)}
										</div>
									</div>
									<div className="flex gap-x-2 mt-4">
										當用戶點擊 <code>{liffLink}</code>{' '}
										<CopyText text={liffLink} /> 連結時，LINE
										會收到上面的活動推播
									</div>
								</>
							)}
							{!activities.length && (
								<div className="flex justify-center w-full">
									<Empty className="my-24" description="找不到活動" />
								</div>
							)}
						</Spin>
					</div>
				</Form>
			</Edit>
		</div>
	)
}

export const PromoLinksEdit = memo(EditComponent)
