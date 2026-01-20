<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\DTOs;

use J7\WpUtils\Classes\DTO;

/** 通用的活動 DTO，可能來自外部，不一定是 WP_Post */
final class ActivityDTO extends DTO {

	/** @var string 活動 ID */
	public string $id;

	/** @var string 活動 provider */
	public string $activity_provider_id;

	/** @var string 活動 title，不可為空字串，至少必須有一個空格 */
	public string $title = ' ';

	/** @var string 活動 description，不可為空字串，至少必須有一個空格 */
	public string $description = ' ';

	/** @var string 活動 縮圖 */
	public string $thumbnail_url = '';

	/** @var array<string, mixed> 活動 meta_data */
	public array $meta = [];

	/** @var \DateTime 排程的活動開始時間 */
	public \DateTime $scheduled_start_time;

	/**
	 * 活動的排成時間是否在未來 N 天內會進行
	 *
	 * @param int $n 天數
	 * @return bool 是否在未來 N 天內
	 */
	public function is_within_last_n_days( int $n ): bool {
		$now      = new \DateTime();
		$interval = $now->diff($this->scheduled_start_time);
		return ( $interval->days <= $n ) && $interval->invert === 0; // invert === 0 表示 scheduled_start_time 是未來時間
	}

	/**
	 * 活動標題是否包含關鍵字
	 *
	 * @param string $keyword 關鍵字
	 * @return bool 是否包含關鍵字
	 */
	public function is_content_keyword( string $keyword ): bool {
		return \str_contains( $this->title, $keyword );
	}

	/** 覆寫 to_array */
	public function to_array(): array {
		return [
			'id'                   => $this->id,
			'activity_provider_id' => $this->activity_provider_id,
			'title'                => $this->title,
			'description'          => $this->description,
			'thumbnail_url'        => $this->thumbnail_url,
			'meta'                 => $this->meta,
			'scheduled_start_time' => $this->scheduled_start_time->getTimestamp(),
		];
	}
}
