export type TOptions = {
	line: {
		channel_access_token: string
		channel_id: string
		channel_secret: string
		liff_id: string
		liff_url: string
	}
	youtube: {
		channelId: string
		clientId: string
		clientSecret: string
		redirectUri: string
	}
	googleOauth: {
		isAuthorized: boolean
		authUrl: string
	}
}
