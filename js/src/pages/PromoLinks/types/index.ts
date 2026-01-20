// @ts-ignore
import { TImage, TTerm, TPostStatus } from 'antd-toolkit/wp'

export type TPromoLinkRecord = {
	id: string
	depth: number
	name: string
	slug: string
	date_created: string
	date_modified: string
	status: TPostStatus
	menu_order: number
	permalink: string
	category_ids: TTerm[]
	tag_ids: TTerm[]
	images: TImage[]
	parent_id: string
	keyword: string
	last_n_days: number
	alt_text: string
	action_label: string
}
