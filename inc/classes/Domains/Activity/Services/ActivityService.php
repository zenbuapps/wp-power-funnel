<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Domains\Activity\Services;

use J7\PowerFunnel\Contracts\DTOs\ActivityDTO;
use J7\PowerFunnel\Contracts\Interfaces\IActivityProvider;
use J7\WpUtils\Traits\SingletonTrait;

/** ActivityService */
final class ActivityService {
	use SingletonTrait;

	/** @var array<string, IActivityProvider> $activity_providers */
	private array $activity_providers;

	/** Constructor */
	private function __construct() {
		// @phpstan-ignore-next-line
		$this->activity_providers = \apply_filters('power_funnel/activity_providers', []);
	}

	/**
	 * 查詢活動
	 * 標題包含關鍵字
	 * 取得最近 N 天的活動
	 *
	 * @param array{
	 * id?: string,
	 * keyword?: string,
	 * last_n_days?: int,
	 * } $params 參數
	 *
	 * @return array<ActivityDTO> 活動 DTO 陣列
	 */
	public function get_activities( array $params = [] ): array {
		$id             = $params['id'] ?? null;
		$keyword        = $params['keyword'] ?? '';
		$last_n_days    = $params['last_n_days'] ?? 0;
		$all_activities = $this->get_all_activities();

		if (!$id && !$keyword && !$last_n_days) {
			return $all_activities;
		}

		$activities = [];
		foreach ($all_activities as $activity) {
			// 如果有指定 id 就直接查找
			if ($id && $activity->id === $id) {
				return [ $activity ];
			}
			if ($keyword && $last_n_days) {
				if ( $activity->is_content_keyword( $keyword ) && $activity->is_within_last_n_days( $last_n_days ) ) {
					$activities[] = $activity;
				}
				continue;
			}

			if ($keyword) {
				if ( $activity->is_content_keyword( $keyword ) ) {
					$activities[] = $activity;
				}
				continue;
			}

			if ( $activity->is_within_last_n_days( $last_n_days ) ) {
				$activities[] = $activity;
			}
		}

		return $activities;
	}


	/** 取得活動 */
	public function get_activity( string $id ): ?ActivityDTO {
		$activities = $this->get_activities(
			[
				'id' => $id,
			]
			);
		return \reset($activities) ?: null;
	}
	/**
	 * 取得所有活動
	 *
	 * @param string $provider_id 從指定的活動提供商身上取得活動
	 *
	 * @return array<ActivityDTO> 活動 DTO 陣列
	 */
	private function get_all_activities( string $provider_id = '' ): array {
		if ($provider_id) {
			return isset($this->activity_providers[ $provider_id ]) ? $this->activity_providers[ $provider_id ]->get_activities() : [];
		}

		$activities = [];
		foreach ( $this->activity_providers as $provider ) {
			$activities = [
				...$activities,
				...$provider->get_activities(),
			];
		}
		return $activities;
	}
}
