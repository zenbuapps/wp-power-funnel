/* eslint-disable quote-props */
import '@refinedev/antd/dist/reset.css'
import { saveLiffUserInfo, IS_LOCAL } from '@/utils'
import liff from '@line/liff/core'
import { useState, useEffect } from 'react'
import '@/assets/scss/index.scss'

/**
 * LIFF APP 看到的 loading 畫面
 * @constructor
 */
function App() {
	const [isLoading, setIsLoading] = useState(true)
	const [isError, setIsError] = useState(false)
	const [errorMsg, setErrorMsg] = useState('')

	const saveUserAsync = async () => {
		try {
			await saveLiffUserInfo()
		} catch (error) {
			setIsError(true)
			if (typeof error === 'string') setErrorMsg(error)
			else setErrorMsg(JSON.stringify(error, null, 2))
		} finally {
			console.log('結束')
			setIsLoading(false)
			if (typeof liff.closeWindow === 'function' && !isError) {
				setTimeout(() => {
					liff.closeWindow()
				}, 1000)
			}
		}
	}

	useEffect(() => {
		saveUserAsync()
	}, [])

	return (
		<div className="h-screen flex flex-col justify-center items-center">
			<div className="w-1/3 aspect-square mb-8 text-center">
				{isError && (
					<svg
						className="object-contain w-full error-icon"
						version="1.1"
						xmlns="http://www.w3.org/2000/svg"
						viewBox="0 0 130.2 130.2"
					>
						<circle
							className="stroke-3"
							fill="none"
							strokeMiterlimit="10"
							cx="65.1"
							cy="65.1"
							r="58.1"
						/>
						<line
							className="stroke-3"
							fill="none"
							strokeMiterlimit="10"
							x1="34.4"
							y1="37.9"
							x2="95.8"
							y2="92.3"
						/>
						<line
							className="stroke-3"
							fill="none"
							strokeMiterlimit="10"
							x1="95.8"
							y1="38"
							x2="34.4"
							y2="92.2"
						/>
					</svg>
				)}
				{!isError && isLoading && (
					<svg
						className="object-contain w-full success-icon"
						xmlns="http://www.w3.org/2000/svg"
						viewBox="0 0 150 150"
					>
						<path
							className="stroke-3 animate-circle animate-infinity"
							d="M141,69v6c0,37.5-31.3,67.7-69.2,65.9c-33.8-1.6-61.2-29-62.8-62.8C7.3,40.2,37.6,9,75,9c9.2,0,18.4,2,26.8,5.7"
						></path>
					</svg>
				)}
				{!isError && !isLoading && (
					<svg
						className="object-contain w-full success-icon"
						xmlns="http://www.w3.org/2000/svg"
						viewBox="0 0 150 150"
					>
						<path
							className={`stroke-3 animate-circle`}
							d="M141,69v6c0,37.5-31.3,67.7-69.2,65.9c-33.8-1.6-61.2-29-62.8-62.8C7.3,40.2,37.6,9,75,9c9.2,0,18.4,2,26.8,5.7"
						></path>
						{!isLoading && (
							<path
								className="stroke-3 animate-check"
								d="M139.9,22.2l-66,66.1L54.1,68.5"
							></path>
						)}
					</svg>
				)}
			</div>
			<h2 className="text-2xl">
				{isError && <>發生錯誤，請重試一次</>}
				{!isError ? (isLoading ? '連線中...' : '已完成') : null}
			</h2>
			{IS_LOCAL && errorMsg && (
				<pre className="h-[20rem] overflow-y-auto bg-gray-100 p-2 w-full">
					{errorMsg}
				</pre>
			)}
		</div>
	)
}

export default App
