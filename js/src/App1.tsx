/* eslint-disable quote-props */
import '@/assets/scss/admin.scss'

import { Refine } from '@refinedev/core'
import { PromoLinksEdit, PromoLinksList, Settings } from '@/pages'

import { ErrorComponent, ThemedLayoutV2, ThemedSiderV2 } from '@refinedev/antd'
import '@refinedev/antd/dist/reset.css'
import routerBindings, {
	DocumentTitleHandler,
	NavigateToResource,
	UnsavedChangesNotifier,
} from '@refinedev/react-router'
import { HashRouter, Outlet, Route, Routes } from 'react-router'
import { resources } from '@/resources'
import { ConfigProvider } from 'antd'
import { useEnv } from 'antd-toolkit'
import { TEnv } from '@/types'
import { notificationProvider, dataProvider } from 'antd-toolkit/refine'

function App() {
	const { KEBAB, API_URL, AXIOS_INSTANCE } = useEnv<TEnv>()
	return (
		<div className="overflow-x-auto">
			<div className="w-[1200px] xl:w-full">
				<HashRouter>
					<Refine
						dataProvider={{
							default: dataProvider(`${API_URL}/v2/powerhouse`, AXIOS_INSTANCE),
							'wp-rest': dataProvider(`${API_URL}/wp/v2`, AXIOS_INSTANCE),
							'wc-rest': dataProvider(`${API_URL}/wc/v3`, AXIOS_INSTANCE),
							'wc-store': dataProvider(
								`${API_URL}/wc/store/v1`,
								AXIOS_INSTANCE,
							),
							'power-funnel': dataProvider(
								`${API_URL}/${KEBAB}`,
								AXIOS_INSTANCE,
							),
						}}
						notificationProvider={notificationProvider}
						routerProvider={routerBindings}
						resources={resources}
						options={{
							syncWithLocation: true,
							warnWhenUnsavedChanges: true,
							projectId: 'power-funnel',
							reactQuery: {
								clientConfig: {
									defaultOptions: {
										queries: {
											staleTime: 1000 * 60 * 10,
											cacheTime: 1000 * 60 * 10,
											retry: 0,
										},
									},
								},
							},
						}}
					>
						<Routes>
							<Route
								element={
									<ConfigProvider
										theme={{
											components: {
												Collapse: {
													contentPadding: '8px 8px',
												},
											},
										}}
									>
										<ThemedLayoutV2
											Sider={(props: any) => <ThemedSiderV2 {...props} fixed />}
											Title={() => <></>}
										>
											<Outlet />
										</ThemedLayoutV2>
									</ConfigProvider>
								}
							>
								<Route
									index
									element={<NavigateToResource resource="promo-links" />}
								/>
								<Route path="promo-links">
									<Route index element={<PromoLinksList />} />
									<Route path="edit/:id" element={<PromoLinksEdit />} />
								</Route>
								<Route path="settings" element={<Settings />} />
								<Route path="*" element={<ErrorComponent />} />
							</Route>
						</Routes>
						<UnsavedChangesNotifier />
						<DocumentTitleHandler />
					</Refine>
				</HashRouter>
			</div>
		</div>
	)
}

export default App
