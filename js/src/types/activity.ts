export type TActivity = {
	id: string
	// 現在只有 youtube
	activity_provider_id: 'youtube'
	title: string
	description: string
	thumbnail_url: string
	// 秒數 10位
	scheduled_start_time: number
}
