import { useEffect } from 'react'
import { useCustom, useApiUrl } from '@refinedev/core'
import { FormInstance } from 'antd'
import { TOptions } from '@/types'

type TOptionResponse = {
	code: string
	data: TOptions
	message: string
}

const useOptions = (props: { form: FormInstance } | undefined = undefined) => {
	const form = props?.form
	const apiUrl = useApiUrl('power-funnel')
	const result = useCustom<TOptionResponse>({
		url: `${apiUrl}/options`,
		method: 'get',
		queryOptions: {
			queryKey: ['get_options'],
		},
	})

	const { isSuccess } = result
	useEffect(() => {
		if (isSuccess && form) {
			console.log(result)
			const values = result.data?.data?.data
			form.setFieldsValue(values)
		}
	}, [isSuccess])

	return result
}

export default useOptions
