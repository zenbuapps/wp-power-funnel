<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Youtube\Services;

/**
 * Class GoogleOAuthService
 * Google OAuth 2.0 認證服務
 *
 * @see https://developers.google.com/identity/protocols/oauth2/web-server
 */
final class GoogleOAuthService {

	/** @var string Google OAuth 2.0 授權端點 */
	private const AUTHORIZATION_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

	/** @var string Google OAuth 2.0 Token 端點 */
	private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

	/** @var string YouTube 唯讀權限範圍 */
	public const SCOPE_YOUTUBE_READONLY = 'https://www.googleapis.com/auth/youtube.readonly';

	/** @var string 用戶端 ID */
	private string $client_id;

	/** @var string 用戶端密碼 */
	private string $client_secret;

	/** @var string 重導向 URI */
	private string $redirect_uri;

	/**
	 * Constructor
	 *
	 * @param string $client_id 用戶端 ID
	 * @param string $client_secret 用戶端密碼
	 * @param string $redirect_uri 重導向 URI
	 */
	public function __construct( string $client_id, string $client_secret, string $redirect_uri ) {
		$this->client_id     = $client_id;
		$this->client_secret = $client_secret;
		$this->redirect_uri  = $redirect_uri;
	}

	/**
	 * 建立授權 URL
	 *
	 * @param string      $scope 權限範圍
	 * @param string      $access_type 存取類型 (online|offline)
	 * @param string      $prompt 提示類型 (none|consent|select_account)
	 * @param string|null $state 狀態參數，用於防止 CSRF
	 * @return string 授權 URL
	 */
	public function create_auth_url(
		string $scope = self::SCOPE_YOUTUBE_READONLY,
		string $access_type = 'offline',
		string $prompt = 'select_account consent',
		?string $state = null
	): string {
		$params = [
			'client_id'     => $this->client_id,
			'redirect_uri'  => $this->redirect_uri,
			'response_type' => 'code',
			'scope'         => $scope,
			'access_type'   => $access_type,
			'prompt'        => $prompt,
		];

		if ( null !== $state ) {
			$params['state'] = $state;
		}

		return self::AUTHORIZATION_ENDPOINT . '?' . \http_build_query( $params );
	}

	/**
	 * 使用授權碼交換 Access Token
	 *
	 * @param string $authorization_code 授權碼
	 * @return array<string, mixed> Token 資料
	 * @throws \Exception 當 API 請求失敗時拋出異常
	 */
	public function fetch_access_token_with_auth_code( string $authorization_code ): array {
		$body = [
			'client_id'     => $this->client_id,
			'client_secret' => $this->client_secret,
			'code'          => $authorization_code,
			'grant_type'    => 'authorization_code',
			'redirect_uri'  => $this->redirect_uri,
		];

		return $this->request_token( $body );
	}

	/**
	 * 使用 Refresh Token 刷新 Access Token
	 *
	 * @param string $refresh_token Refresh Token
	 * @return array<string, mixed> 新的 Token 資料
	 * @throws \Exception 當 API 請求失敗時拋出異常
	 */
	public function fetch_access_token_with_refresh_token( string $refresh_token ): array {
		$body = [
			'client_id'     => $this->client_id,
			'client_secret' => $this->client_secret,
			'refresh_token' => $refresh_token,
			'grant_type'    => 'refresh_token',
		];

		return $this->request_token( $body );
	}

	/**
	 * 檢查 Token 是否已過期
	 *
	 * @param array{access_token?: string, expires_in?: int, created?: int} $token Token 資料
	 * @return bool 是否已過期
	 */
	public static function is_access_token_expired( array $token ): bool {
		if ( empty( $token['access_token'] ) ) {
			return true;
		}

		// 如果沒有 created 或 expires_in 欄位，視為已過期
		if ( ! isset( $token['created'] ) || ! isset( $token['expires_in'] ) ) {
			return true;
		}

		// 預留 30 秒緩衝時間
		$expires_at = $token['created'] + $token['expires_in'] - 30;

		return \time() >= $expires_at;
	}

	/**
	 * 向 Token 端點發送請求
	 *
	 * @param array<string, string> $body 請求主體
	 * @return array<string, mixed> Token 資料
	 * @throws \Exception 當 API 請求失敗時拋出異常
	 */
	private function request_token( array $body ): array {
		$response = \wp_remote_post(
			self::TOKEN_ENDPOINT,
			[
				'headers' => [
					'Content-Type' => 'application/x-www-form-urlencoded',
				],
				'body'    => $body,
				'timeout' => 30,
			]
		);

		if ( \is_wp_error( $response ) ) {
			throw new \Exception(
				sprintf(
					'Google OAuth Token 請求失敗: %s',
					$response->get_error_message()
				)
			);
		}

		$status_code   = \wp_remote_retrieve_response_code( $response );
		$response_body = \wp_remote_retrieve_body( $response );

		/** @var array<string, mixed>|null $data */
		$data = \json_decode( $response_body, true );

		if ( null === $data ) {
			throw new \Exception( 'Google OAuth Token 回應解析失敗' );
		}

		if ( $status_code >= 400 ) {
			$error_message = '';
			if ( isset( $data['error_description'] ) && \is_string( $data['error_description'] ) ) {
				$error_message = $data['error_description'];
			} elseif ( isset( $data['error'] ) && \is_string( $data['error'] ) ) {
				$error_message = $data['error'];
			} else {
				$error_message = '未知錯誤';
			}
			throw new \Exception(
				\sprintf(
					'Google OAuth Token 請求失敗 (%d): %s',
					$status_code,
					$error_message
				)
			);
		}

		// 加入 created 時間戳記，用於後續檢查過期
		$data['created'] = time();

		return $data;
	}
}
