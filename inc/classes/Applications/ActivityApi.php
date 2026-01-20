<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Applications;

use J7\PowerFunnel\Domains\Activity\Services\ActivityService;
use J7\WpUtils\Classes\ApiBase;
use J7\WpUtils\Traits\SingletonTrait;

final class ActivityApi extends ApiBase {
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
			'endpoint' => 'activities',
			'method'   => 'get',
		],
	];

	/** Register hooks */
	public static function register_hooks(): void {
		self::instance();
	}

	/**
	 * 獲取活動
	 *
	 * @param \WP_REST_Request $request REST請求對象。
	 * @return \WP_REST_Response 返回包含選項資料的REST響應對象。
	 * @phpstan-ignore-next-line
	 */
	public function get_activities_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$params        = $request->get_query_params();
		$service       = ActivityService::instance( );
		$activity_dtos = $service->get_activities( $params );
		$activities    = \array_map(static fn( $activity_dto ) => $activity_dto->to_array(), $activity_dtos);
		return new \WP_REST_Response( $activities );
	}
}
