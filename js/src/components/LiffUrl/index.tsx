import useLiffUrl from '@/pages/Settings/hooks/useLiffUrl'
import { Button, Input, Space } from 'antd'
import { CopyText } from 'antd-toolkit'
import { FC } from 'react'

export const LiffUrl: FC<{ id: string }> = ({ id }) => {
	const { url, hasLiffUrl } = useLiffUrl(id)
	if (!hasLiffUrl) {
		return <span className="text-red-500">請先前往「設定」設定 Liff Url</span>
	}
	return (
		<>
			<Space.Compact block>
				<Input readOnly value={url} />
				<Button type="default" icon={<CopyText text={url} />} />
			</Space.Compact>
		</>
	)
}
