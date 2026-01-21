import { useCustomMutation, useApiUrl } from '@refinedev/core'
import { FormInstance } from 'antd'
import { useCallback } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { notificationProps } from 'antd-toolkit/refine'

const useSave = ({ form }: { form: FormInstance }) => {
	const apiUrl = useApiUrl('power-funnel')
	const mutation = useCustomMutation()
	const { mutate } = mutation
	const queryClient = useQueryClient()

	const handleSave = useCallback(() => {
		form.validateFields().then((values) => {
			mutate(
				{
					url: `${apiUrl}/options`,
					method: 'post',
					values,
					...notificationProps,
				},
				{
					onSuccess: () => {
						queryClient.invalidateQueries(['get_options'])
					},
				},
			)
		})
	}, [form])

	return {
		handleSave,
		mutation,
	}
}

export default useSave
