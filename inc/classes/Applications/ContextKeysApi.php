<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Applications;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\WpUtils\Classes\ApiBase;
use J7\WpUtils\Traits\SingletonTrait;

/**
 * 觸發點 Context Keys API
 *
 * 提供 REST 端點查詢指定觸發點可用的 context keys。
 * 前端 YesNoBranchNode 編輯器使用此 API 動態載入 condition_field 下拉選項。
 */
final class ContextKeysApi extends ApiBase {
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
			'endpoint' => 'trigger-points/(?P<triggerPoint>[a-z0-9_/]+)/context-keys',
			'method'   => 'get',
		],
	];

	/** 註冊 hooks */
	public static function register_hooks(): void {
		self::instance();
	}

	/**
	 * 取得指定觸發點的可用 Context Keys
	 *
	 * @param \WP_REST_Request $request REST 請求對象
	 * @return \WP_REST_Response REST 響應對象
	 * @phpstan-ignore-next-line
	 */
	public function get_trigger_points_context_keys_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$trigger_point = $request->get_param('triggerPoint');

		if (empty($trigger_point) || !\is_string($trigger_point)) {
			return new \WP_REST_Response(
				[
					'code'    => 'operation_failed',
					'message' => '必要參數未提供',
					'data'    => [],
				],
				400
			);
		}

		$keys = TriggerPointService::get_context_keys_for_trigger_point($trigger_point);

		return new \WP_REST_Response(
			[
				'code'    => 'operation_success',
				'message' => '操作成功',
				'data'    => $keys,
			]
		);
	}
}
