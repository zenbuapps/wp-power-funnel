import GetOS from '@line/liff/get-os'
import GetAppLanguage from '@line/liff/get-app-language'
import IsLoggedIn from '@line/liff/is-logged-in'
import Login from '@line/liff/login'
import GetProfile from '@line/liff/get-profile'
import GetLineVersion from '@line/liff/get-line-version'
import IsInClient from '@line/liff/is-in-client'
import CloseWindow from '@line/liff/close-window'
import { LIFF_ID, NONCE, SITE_URL } from '@/utils'

import liff from '@line/liff/core'
import axios, { AxiosInstance } from 'axios'

type TUser = {
	userId: string
	name: string
	picture: string | undefined
	os: string | undefined
	version: string
	lineVersion: null | string
	isInClient: boolean
	isLoggedIn: boolean
}

const axiosInstance: AxiosInstance = axios.create({
	timeout: 30000,
	headers: {
		'X-WP-Nonce': NONCE,
		'Content-Type': 'application/json',
	},
})

// 初始化 LIFF
async function initLiff() {
	liff.use(new GetOS())
	liff.use(new GetAppLanguage())
	liff.use(new IsLoggedIn())
	liff.use(new Login())
	liff.use(new GetProfile())
	liff.use(new GetLineVersion())
	liff.use(new IsInClient())
	liff.use(new CloseWindow())
	await liff.init({ liffId: LIFF_ID })
	console.log('LIFF 初始化成功')
}

// 取得使用者資訊
async function getUserProfile(): Promise<TUser | null> {
	if (!liff.isLoggedIn()) {
		liff.login()
		return null
	}
	const profile = await liff.getProfile()

	return {
		userId: profile.userId,
		name: profile.displayName,
		picture: profile.pictureUrl,
		os: liff.getOS(),
		version: liff.getVersion(),
		lineVersion: liff.getLineVersion(),
		isInClient: liff.isInClient(),
		isLoggedIn: liff.isLoggedIn(),
	}
}

// 發送 API 到後端
async function sendUserToBackend(user: TUser) {
	if (!user) return

	const urlParams = Object.fromEntries(
		new URLSearchParams(window.location.search),
	)
	const res = await axiosInstance.post(
		`${SITE_URL}/wp-json/power-funnel/liff`,
		{
			urlParams,
			...user,
		},
	)
	console.log('後端回應:', res)
}

// 主流程
export async function saveLiffUserInfo() {
	await initLiff()
	const user = await getUserProfile()
	console.log('user:', user)
	// 故意不 await 請求送出就算完成
	sendUserToBackend(user as TUser)
}
