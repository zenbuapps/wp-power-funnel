import { useCustom, useApiUrl } from '@refinedev/core'

/** 觸發條件項目型別（對應後端 TriggerPointDTO） */
export type TTriggerPointItem = {
	hook: string
	name: string
	disabled: boolean
}

/** 觸發條件分組型別（對應後端 TriggerPointGroupDTO） */
export type TTriggerPointGroup = {
	group: string
	group_label: string
	items: TTriggerPointItem[]
}

/** API 回應型別 */
type TTriggerPointsResponse = {
	code: string
	message: string
	data: TTriggerPointGroup[]
}

/** Ant Design OptGroup 單一選項型別 */
type TGroupedOptionItem = {
	label: string
	value: string
	disabled: boolean
}

/** Ant Design OptGroup 型別 */
export type TGroupedOption = {
	label: string
	options: TGroupedOptionItem[]
}

/**
 * 取得所有已註冊觸發條件的分組資料
 *
 * 呼叫 GET /wp-json/power-funnel/trigger-points，
 * 回傳 groupedOptions（供 Ant Design Select OptGroup 使用）、
 * labelMap（hook => 顯示名稱）、groupLabelMap（hook => 群組標籤）與 isLoading 狀態。
 */
const useTriggerPoints = () => {
	const apiUrl = useApiUrl('power-funnel')
	const result = useCustom<TTriggerPointsResponse>({
		url: `${apiUrl}/trigger-points`,
		method: 'get',
		queryOptions: {
			queryKey: ['get_trigger_points'],
			staleTime: 5 * 60 * 1000,
		},
	})

	const groups: TTriggerPointGroup[] = result.data?.data?.data ?? []

	/** OptGroup 格式的選項，供 Ant Design Select 使用 */
	const groupedOptions: TGroupedOption[] = groups.map((group) => ({
		label: group.group_label,
		options: group.items.map((item) => ({
			label: item.name,
			value: item.hook,
			disabled: item.disabled,
		})),
	}))

	/** hook => 顯示名稱，供表格欄位渲染使用 */
	const labelMap: Record<string, string> = groups.reduce(
		(acc, group) => {
			group.items.forEach((item) => {
				acc[item.hook] = item.name
			})
			return acc
		},
		{} as Record<string, string>,
	)

	/** hook => 群組中文標籤，供自訂 filterOption 搜尋群組名稱使用 */
	const groupLabelMap: Record<string, string> = groups.reduce(
		(acc, group) => {
			group.items.forEach((item) => {
				acc[item.hook] = group.group_label
			})
			return acc
		},
		{} as Record<string, string>,
	)

	return {
		groupedOptions,
		labelMap,
		groupLabelMap,
		isLoading: result.isLoading,
	}
}

export default useTriggerPoints
