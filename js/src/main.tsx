import React from 'react'
import ReactDOM from 'react-dom/client'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ReactQueryDevtools } from '@tanstack/react-query-devtools'
import { APP1_SELECTOR, APP2_SELECTOR, env } from '@/utils'
import { StyleProvider } from '@ant-design/cssinjs'
import { ConfigProvider } from 'antd'
import { EnvProvider } from 'antd-toolkit'

const App1 = React.lazy(() => import('./App1'))
const App2 = React.lazy(() => import('./App2'))

const queryClient = new QueryClient({
	defaultOptions: {
		queries: {
			refetchOnWindowFocus: false,
			retry: 0,
		},
	},
})

const app1Nodes = document.querySelectorAll(APP1_SELECTOR)
const app2Nodes = document.querySelectorAll(APP2_SELECTOR)

const mapping = [
	{
		els: app1Nodes,
		App: App1,
	},
	{
		els: app2Nodes,
		App: App2,
	},
]

document.addEventListener('DOMContentLoaded', () => {
	mapping.forEach(({ els, App }) => {
		els.forEach((el) => {
			ReactDOM.createRoot(el).render(
				<React.StrictMode>
					<QueryClientProvider client={queryClient}>
						<StyleProvider hashPriority="low">
							<EnvProvider env={env}>
								<ConfigProvider
									theme={{
										token: {
											colorPrimary: '#1677ff',
											borderRadius: 6,
										},
										components: {
											Segmented: {
												itemSelectedBg: '#1677ff',
												itemSelectedColor: '#ffffff',
											},
										},
									}}
								>
									<App />
								</ConfigProvider>
							</EnvProvider>
						</StyleProvider>
						<ReactQueryDevtools initialIsOpen={false} />
					</QueryClientProvider>
				</React.StrictMode>,
			)
		})
	})
})
