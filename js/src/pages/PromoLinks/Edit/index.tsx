import { memo, useState} from 'react'
import {Edit, useForm} from '@refinedev/antd'
import {TPromoLinkRecord} from '@/pages/PromoLinks/types'
import {HttpError, useParsed, useCustom, useApiUrl} from '@refinedev/core'
import {toFormData, Heading} from 'antd-toolkit'
import {objToCrudFilters, notificationProps} from 'antd-toolkit/refine'
import { Button, Form, Input, InputNumber } from "antd"
import { SITE_URL } from "@/utils"

const {Item} = Form

const EditComponent = () => {
    const {id} = useParsed()

    // 初始化資料
    const { formProps, form, saveButtonProps, query, mutation, onFinish} =
            useForm<TPromoLinkRecord, HttpError, Partial<TPromoLinkRecord>>({
                action: 'edit',
                resource: 'posts',
                id,
                redirect: false,
                successNotification: false,
                errorNotification: false,
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
    const [previewParams, setPreviewParams] = useState<{
        keyword?: string,
        last_n_days?: number,
    }|undefined>(undefined)

    const { data } = useCustom<any>({
        url: `${apiUrl}/activities`,
        method: "get",
        config: {
            filters: objToCrudFilters(previewParams || {}),
        },
        queryOptions:{
            enabled: !!previewParams,
        },
        ...notificationProps
    });

    console.log(data)
    const handlePreview = () => {
        const { keyword, last_n_days } = form.getFieldsValue()
        setPreviewParams({ keyword, last_n_days })
    }

    // endregion 預覽功能


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
                       <div className="grid grid-cols-1 xl:grid-cols-2 gap-x-4">
                           <div>
                               <Item name={['name']} label="LINE 連結名稱 (內部識別用)" className="mb-10">
                                   <Input allowClear />
                               </Item>
                               <Heading>活動篩選條件</Heading>
                               <Item name={['keyword']} label="關鍵字">
                                   <Input allowClear />
                               </Item>
                               <Item name={['last_n_days']} label="顯示最近 N 天的活動" className="mb-10">
                                   <InputNumber className="w-full" min={0} addonAfter="天" />
                               </Item>
                               <Heading>其他選項</Heading>
                               <Item name={['alt_text']} label="替代文字">
                                   <Input allowClear />
                               </Item>
                               <Item name={['action_label']} label="操作標籤">
                                   <Input allowClear />
                               </Item>
                           </div>
                           <div>
                               <Button variant="filled" onClick={handlePreview}>預覽</Button>
                               PREVIEW
                           </div>
                       </div>
                   </Form>
                </Edit>
            </div>
    )
}

export const PromoLinksEdit = memo(EditComponent)
