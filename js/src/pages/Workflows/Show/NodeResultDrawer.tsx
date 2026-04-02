import { memo, useMemo } from 'react'
import { Drawer, Descriptions, Tag, Empty } from 'antd'
import dayjs from 'dayjs'
import {
	NODE_RESULT_CODE,
	type TWorkflowNodeWithResult,
	type TNodeResultCode,
} from '../types'

type TNodeResultDrawerProps = {
	isOpen: boolean
	node: TWorkflowNodeWithResult | null
	onClose: () => void
}

/** 結果碼對應的 Tag 顏色 */
const RESULT_CODE_COLOR: Record<TNodeResultCode, string> = {
	[NODE_RESULT_CODE.SUCCESS]: 'green',
	[NODE_RESULT_CODE.SKIPPED]: 'default',
	[NODE_RESULT_CODE.FAILED]: 'red',
}

/** 結果碼對應的標籤 */
const RESULT_CODE_LABEL: Record<TNodeResultCode, string> = {
	[NODE_RESULT_CODE.SUCCESS]: '200 成功',
	[NODE_RESULT_CODE.SKIPPED]: '301 跳過',
	[NODE_RESULT_CODE.FAILED]: '500 失敗',
}

/**
 * 節點結果 Drawer
 * 顯示選中節點的詳細執行資訊
 */
const NodeResultDrawer = memo(
	({ isOpen, node, onClose }: TNodeResultDrawerProps) => {
		/** 格式化節點參數為可讀字串 */
		const formattedParams = useMemo(() => {
			if (!node?.params || Object.keys(node.params).length === 0) {
				return null
			}
			return JSON.stringify(node.params, null, 2)
		}, [node?.params])

		/** 格式化額外資料 */
		const formattedData = useMemo(() => {
			if (node?.result?.data === null || node?.result?.data === undefined) {
				return null
			}
			return JSON.stringify(node.result.data, null, 2)
		}, [node?.result?.data])

		return (
			<Drawer
				title="節點執行結果"
				open={isOpen}
				onClose={onClose}
				width={480}
				destroyOnClose
			>
				{node ? (
					<Descriptions column={1} bordered size="small">
						<Descriptions.Item label="節點 ID">{node.nodeId}</Descriptions.Item>
						<Descriptions.Item label="節點類型">
							{node.nodeDefinitionId}
						</Descriptions.Item>
						{node.result ? (
							<>
								<Descriptions.Item label="結果碼">
									<Tag
										color={
											RESULT_CODE_COLOR[node.result.code as TNodeResultCode] ??
											'default'
										}
									>
										{RESULT_CODE_LABEL[node.result.code as TNodeResultCode] ??
											String(node.result.code)}
									</Tag>
								</Descriptions.Item>
								<Descriptions.Item label="訊息">
									{node.result.message || '-'}
								</Descriptions.Item>
								{formattedData && (
									<Descriptions.Item label="額外資料">
										<pre className="m-0 max-h-60 overflow-auto rounded bg-gray-50 p-2 text-xs">
											{formattedData}
										</pre>
									</Descriptions.Item>
								)}
								<Descriptions.Item label="執行時間">
									{node.result.executedAt
										? dayjs(node.result.executedAt).format(
												'YYYY-MM-DD HH:mm:ss',
											)
										: '-'}
								</Descriptions.Item>
							</>
						) : (
							<Descriptions.Item label="狀態">
								<Tag color="default">尚未執行</Tag>
							</Descriptions.Item>
						)}
						{formattedParams && (
							<Descriptions.Item label="節點參數">
								<pre className="m-0 max-h-60 overflow-auto rounded bg-gray-50 p-2 text-xs">
									{formattedParams}
								</pre>
							</Descriptions.Item>
						)}
					</Descriptions>
				) : (
					<Empty description="請選擇一個節點" />
				)}
			</Drawer>
		)
	},
)

NodeResultDrawer.displayName = 'NodeResultDrawer'

export default NodeResultDrawer
