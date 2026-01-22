import useOptions from '@/pages/Settings/hooks/useOptions'

const useLiffUrl = (id: string) => {
	const { data: options } = useOptions()
	const liffUrl = options?.data?.data?.line?.liff_url || ''
	return {
		url: `${liffUrl}/?promoLinkId=${id}`,
		hasLiffUrl: !!liffUrl,
	}
}

export default useLiffUrl
