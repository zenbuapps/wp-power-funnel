<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Shared\Enums\ENodeType;
use J7\PowerFunnel\Shared\Enums\EWorkflowStatus;
use J7\Powerhouse\Contracts\DTOs\FormFieldDTO;

/** 時間窗口節點定義 */
final class TimeWindowNode extends BaseNodeDefinition {

	// region 前端顯示屬性

	/** @var string Node ID */
	public string $id = 'time_window';

	/** @var string Node 名稱 */
	public string $name = '等待至時間窗口';

	/** @var string Node 描述 */
	public string $description = '等待至時間窗口';

	/** @var string Node icon */
	public string $icon;

	/** @var ENodeType Node 分類 */
	public ENodeType $type = ENodeType::ACTION;

	/** @var array<string, FormFieldDTO> 欄位資料 */
	public array $form_fields = [];

	// endregion 前端顯示屬性

	/** Constructor */
	public function __construct() {
		parent::__construct();
		$this->form_fields = [
			'start_time' => new FormFieldDTO(
				[
					'name'        => 'start_time',
					'label'       => '開始時間',
					'type'        => 'text',
					'required'    => true,
					'placeholder' => '09:00',
					'description' => '時間窗口的開始時間',
					'sort'        => 0,
				]
			),
			'end_time'   => new FormFieldDTO(
				[
					'name'        => 'end_time',
					'label'       => '結束時間',
					'type'        => 'text',
					'required'    => true,
					'placeholder' => '18:00',
					'description' => '時間窗口的結束時間',
					'sort'        => 1,
				]
			),
			'timezone'   => new FormFieldDTO(
				[
					'name'        => 'timezone',
					'label'       => '時區',
					'type'        => 'select',
					'required'    => false,
					'description' => '時間窗口的時區',
					'sort'        => 2,
					'options'     => [
						[
							'value' => 'Asia/Taipei',
							'label' => 'Asia/Taipei (UTC+8)',
						],
						[
							'value' => 'Asia/Tokyo',
							'label' => 'Asia/Tokyo (UTC+9)',
						],
						[
							'value' => 'UTC',
							'label' => 'UTC',
						],
					],
				]
			),
		];
	}

	/**
	 * 執行回調：確保後續節點在指定時間窗口內執行
	 *
	 * @param NodeDTO     $node 節點
	 * @param WorkflowDTO $workflow 當前 workflow 資料
	 *
	 * @return WorkflowResultDTO 結果
	 */
	public function execute( NodeDTO $node, WorkflowDTO $workflow ): WorkflowResultDTO {
		// 取得 start_time（必填，格式 HH:MM）
		$start_time = (string) ( $node->params['start_time'] ?? '' );
		if ( $start_time === '' ) {
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 500,
					'message' => 'TimeWindowNode 執行失敗：缺少 start_time',
				]
			);
		}

		// 取得 end_time（必填，格式 HH:MM）
		$end_time = (string) ( $node->params['end_time'] ?? '' );
		if ( $end_time === '' ) {
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 500,
					'message' => 'TimeWindowNode 執行失敗：缺少 end_time',
				]
			);
		}

		// 取得時區（空字串時使用 WordPress 站台時區）
		$timezone_str = (string) ( $node->params['timezone'] ?? '' );
		if ( $timezone_str === '' ) {
			$timezone_str = \wp_timezone_string();
		}

		$tz = new \DateTimeZone( $timezone_str );

		// 計算排程時間戳
		$schedule_timestamp = $this->calculate_schedule_timestamp( $start_time, $end_time, $tz );

		// 判斷是否立即排程（window 內）或延遲排程
		$is_immediate = ( $schedule_timestamp === \time() );

		// 使用 Action Scheduler 排程
		$action_id = \as_schedule_single_action(
			$schedule_timestamp,
			'power_funnel/workflow/' . EWorkflowStatus::RUNNING->value,
			[ 'workflow_id' => $workflow->id ]
		);

		if ( ! $action_id ) {
			return new WorkflowResultDTO(
				[
					'node_id' => $node->id,
					'code'    => 500,
					'message' => 'TimeWindowNode 執行失敗：排程失敗',
				]
			);
		}

		$message = $is_immediate ? '目前在時間窗口內，立即排程' : "排程至 {$start_time}";

		return new WorkflowResultDTO(
			[
				'node_id'   => $node->id,
				'code'      => 200,
				'message'   => $message,
				'scheduled' => true,
			]
		);
	}

	/**
	 * 計算應排程的 Unix timestamp
	 *
	 * 支援三種窗口類型：
	 * - start == end：24 小時窗口，立即排程
	 * - start < end（正常窗口）：now 在 [start, end) 則立即，否則排程至 start
	 * - start > end（跨日窗口）：now >= start 或 now < end 則立即，否則排程至今天 start
	 *
	 * @param string        $start_time 開始時間（HH:MM）
	 * @param string        $end_time   結束時間（HH:MM）
	 * @param \DateTimeZone $tz         時區
	 * @return int Unix timestamp
	 */
	private function calculate_schedule_timestamp( string $start_time, string $end_time, \DateTimeZone $tz ): int {
		$now = new \DateTimeImmutable( 'now', $tz );

		// 今天的 start / end DateTime
		$today_start = new \DateTimeImmutable( $now->format( 'Y-m-d' ) . ' ' . $start_time . ':00', $tz );
		$today_end   = new \DateTimeImmutable( $now->format( 'Y-m-d' ) . ' ' . $end_time . ':00', $tz );

		// start == end：24 小時窗口，立即排程
		if ( $start_time === $end_time ) {
			return \time();
		}

		// 取得當前 HH:MM 字串以比較
		$now_time = $now->format( 'H:i' );
		$s        = $start_time;
		$e        = $end_time;

		if ( $s < $e ) {
			// 正常窗口（非跨日）：[start, end)
			if ( $now_time >= $s && $now_time < $e ) {
				// 在窗口內：立即排程
				return \time();
			}

			if ( $now_time < $s ) {
				// 在窗口前：排程至今天 start
				return $today_start->getTimestamp();
			}

			// 在窗口後：排程至明天 start
			$tomorrow_start = $today_start->modify( '+1 day' );
			return $tomorrow_start->getTimestamp();
		}

		// 跨日窗口（start > end）：[start, 24:00) ∪ [00:00, end)
		if ( $now_time >= $s || $now_time < $e ) {
			// 在窗口內：立即排程
			return \time();
		}

		// 不在窗口內（end <= now < start）：排程至今天 start
		return $today_start->getTimestamp();
	}
}
