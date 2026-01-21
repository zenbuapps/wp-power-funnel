<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Applications;

use J7\PowerFunnel\Infrastructure\Youtube\Services\YoutubeService;
use J7\PowerFunnel\Shared\Enums\EOptionName;
use J7\WpUtils\Classes\ApiBase;
use J7\WpUtils\Traits\SingletonTrait;

final class OptionApi extends ApiBase {
	use SingletonTrait;

	/** @var string $namespace */
	protected $namespace = 'power-funnel';

	/**
	 * @var array<array{
	 * endpoint:string,
	 * method:string,
	 * permission_callback?: callable|null,
	 * callback?: callable|null,
	 * schema?: array<string, mixed>|null
	 * }> $apis APIs
	 * */
	protected $apis = [
		[
			'endpoint' => 'options',
			'method'   => 'get',
		],
		[
			'endpoint' => 'options',
			'method'   => 'post',
		],
		[
			'endpoint' => 'revoke-google-oauth',
			'method'   => 'post',
		],
	];

	/** Register hooks */
	public static function register_hooks(): void {
		self::instance();
	}

	/**
	 * 取得設定選項
	 *
	 * @param \WP_REST_Request $request REST請求對象。
	 * @return \WP_REST_Response 返回包含選項資料的REST響應對象。
	 * @phpstan-ignore-next-line
	 */
	public function get_options_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$options = [];
		foreach (EOptionName::cases() as $option_name) {
			$options[ $option_name->value ] = $option_name->get_settings();
		}
		return new \WP_REST_Response(
			[
				'code'    => 'get_options_success',
				'message' => '取得設定成功',
				'data'    => $options,
			]
			);
	}

	/**
	 * 儲存設定選項
	 *
	 * @param \WP_REST_Request $request REST請求對象。
	 * @return \WP_REST_Response 返回包含選項資料的REST響應對象。
	 * @phpstan-ignore-next-line
	 */
	public function post_options_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$body_params = $request->get_params();
		foreach (EOptionName::cases() as $option_name) {
			if (!isset($body_params[ $option_name->value ])) {
				continue;
			}
			$data = $body_params[ $option_name->value ];
			if (!\is_array($data)) {
				continue;
			}
			$option_name->save($data);
		}

		return new \WP_REST_Response(
			[
				'code'    => 'save_options_success',
				'message' => '儲存成功',
				'data'    => null,
			]
			);
	}


	/**
	 * 撤銷 Google OAuth 授權
	 *
	 * @param \WP_REST_Request $request REST請求對象。
	 * @return \WP_REST_Response 返回包含選項資料的REST響應對象。
	 * @phpstan-ignore-next-line
	 */
	public function post_revoke_google_oauth_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$service = YoutubeService::instance();
		$service->revoke_token();

		return new \WP_REST_Response(
			[
				'code'    => 'revoke_google_oauth_success',
				'message' => '撤銷 Google OAuth 成功',
				'data'    => null,
			]
		);
	}
}
