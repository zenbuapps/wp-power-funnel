import { FaLine, FaRobot } from "react-icons/fa6";
import {
    SettingOutlined,
} from '@ant-design/icons'

export const resources = [
    {
        name: 'promo-links',
        list: '/promo-links',
        edit: '/promo-links/edit/:id',
        meta: {
            canDelete: true,
            label: 'LINE 連結',
            icon: <FaLine />,
        },
    },
    {
        name: 'workflow-rules',
        list: '/workflow-rules',
        edit: '/workflow-rules/edit/:id',
        meta: {
            canDelete: true,
            label: '自動化',
            icon: <FaRobot />,
        },
    },
    {
        name: 'settings',
        list: '/settings',
        meta: {
            label: '設定',
            icon: <SettingOutlined />,
        },
    },
]
