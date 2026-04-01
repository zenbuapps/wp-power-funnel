<?php

/**
 * LINE Postback 觸發點整合測試。
 *
 * 驗證 LINE postback 事件能正確觸發 pf/trigger/line_postback_received hook，
 * 以及 context_callable_set 格式符合 Serializable Context Callable 規範。
 *
 * @group trigger-points
 * @group line-trigger
 * @group line-postback
 *
 * @see specs/line-trigger-expansion/features/trigger-point/fire-line-postback-received.feature
 * @see specs/line-trigger-expansion/features/trigger-point/register-line-postback-trigger-point.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * LINE Postback 觸發點測試
 *
 * Feature: 觸發 LINE_POSTBACK_RECEIVED 觸發點
 * Feature: 註冊 LINE_POSTBACK_RECEIVED 觸發點
 */
class LinePostbackTriggerTest extends IntegrationTestCase {

	/** @var array<string, array<string, mixed>> 已觸發的事件記錄 */
	private array $fired_triggers = [];

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		TriggerPointService::register_hooks();
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();
		$this->fired_triggers = [];

		// 監聽 line_postback_received 觸發點
		$hook                                             = ETriggerPoint::LINE_POSTBACK_RECEIVED->value;
		$short_name                                       = str_replace( 'pf/trigger/', '', $hook );
		$this->fired_triggers[ $short_name ]              = [];

		\add_action(
			$hook,
			/**
			 * @param array<string, mixed> $context_callable_set
			 */
			function ( array $context_callable_set ) use ( $short_name ): void {
				$this->fired_triggers[ $short_name ][] = $context_callable_set;
			},
			999
		);
	}

	/**
	 * 建立帶有 postback 資料的 LINE Postback 事件
	 *
	 * @param string|null $user_id      LINE User ID，null 表示無 userId
	 * @param string      $postback_data postback data 字串
	 * @return \LINE\Webhook\Model\PostbackEvent
	 */
	private function make_postback_event( ?string $user_id = 'U_test_user_123', string $postback_data = '{"action":"register","activity_id":"99"}' ): \LINE\Webhook\Model\PostbackEvent {
		$source_data = $user_id ? [ 'type' => 'user', 'userId' => $user_id ] : [ 'type' => 'user' ];
		return \LINE\Webhook\Model\PostbackEvent::fromAssocArray( [
			'type'            => 'postback',
			'mode'            => 'active',
			'timestamp'       => (int) ( \microtime( true ) * 1000 ),
			'webhookEventId'  => 'test_event_' . \uniqid(),
			'replyToken'      => 'test_reply_token',
			'source'          => $source_data,
			'deliveryContext' => [ 'isRedelivery' => false ],
			'postback'        => [
				'data'   => $postback_data,
				'params' => [],
			],
		] );
	}

	// ========== Rule: 收到 LINE postback 事件時觸發 ==========

	/**
	 * Feature: 觸發 LINE_POSTBACK_RECEIVED 觸發點
	 * Example: 收到 LINE postback 事件時觸發
	 *
	 * @group happy
	 */
	public function test_收到LINE_postback事件時觸發line_postback_received(): void {
		// Given 一個有效的 LINE Postback 事件，userId 為 U1234567890
		$event = $this->make_postback_event( 'U1234567890', '{"action":"register","activity_id":"99"}' );

		// When 觸發 LINE webhook 的 postback type-only hook
		\do_action( 'power_funnel/line/webhook/postback', $event );

		// Then pf/trigger/line_postback_received 被觸發
		$this->assertCount( 1, $this->fired_triggers['line_postback_received'], 'line_postback_received 應被觸發一次' );
	}

	/**
	 * Example: context_callable_set 執行後應產生包含 4 個欄位的 context
	 *
	 * @group happy
	 */
	public function test_postback_context包含4個正確欄位(): void {
		// Given 一個有效的 LINE Postback 事件
		$event = $this->make_postback_event( 'U1234567890', '{"action":"register","activity_id":"99"}' );

		// When 觸發 hook
		\do_action( 'power_funnel/line/webhook/postback', $event );

		$this->assertCount( 1, $this->fired_triggers['line_postback_received'], 'line_postback_received 應被觸發' );

		$context_callable_set = $this->fired_triggers['line_postback_received'][0];
		$context              = \call_user_func_array( $context_callable_set['callable'], $context_callable_set['params'] );

		// Then context 應包含 4 個正確欄位
		$this->assertArrayHasKey( 'line_user_id', $context, 'context 應包含 line_user_id' );
		$this->assertArrayHasKey( 'event_type', $context, 'context 應包含 event_type' );
		$this->assertArrayHasKey( 'postback_data', $context, 'context 應包含 postback_data' );
		$this->assertArrayHasKey( 'postback_action', $context, 'context 應包含 postback_action' );

		$this->assertSame( 'U1234567890', $context['line_user_id'], 'line_user_id 應相符' );
		$this->assertSame( 'postback', $context['event_type'], 'event_type 應為 postback' );
		$this->assertSame( '{"action":"register","activity_id":"99"}', $context['postback_data'], 'postback_data 應為原始字串' );
		$this->assertSame( 'register', $context['postback_action'], 'postback_action 應為 register' );
	}

	// ========== Rule: postback data 為非 JSON 格式時處理 ==========

	/**
	 * Feature: 觸發 LINE_POSTBACK_RECEIVED 觸發點
	 * Example: postback data 為非 JSON 格式時 postback_action 為空字串
	 *
	 * @group edge
	 */
	public function test_非JSON_postback_data的postback_action為空字串(): void {
		// Given 一個 postback data 為純文字（非 JSON）的事件
		$event = $this->make_postback_event( 'U1234567890', 'plain_text_data' );

		// When 觸發 hook
		\do_action( 'power_funnel/line/webhook/postback', $event );

		$this->assertCount( 1, $this->fired_triggers['line_postback_received'], 'line_postback_received 應被觸發' );

		$context_callable_set = $this->fired_triggers['line_postback_received'][0];
		$context              = \call_user_func_array( $context_callable_set['callable'], $context_callable_set['params'] );

		// Then postback_data 保留原始字串，postback_action 為空字串
		$this->assertSame( 'plain_text_data', $context['postback_data'], 'postback_data 應保留原始字串' );
		$this->assertSame( '', $context['postback_action'], 'postback_action 應為空字串' );
	}

	// ========== Rule: 缺少 userId 的事件不觸發 ==========

	/**
	 * Feature: 觸發 LINE_POSTBACK_RECEIVED 觸發點
	 * Example: LINE postback 事件缺少來源用戶時不觸發
	 *
	 * @group edge
	 */
	public function test_缺少userId的postback事件不觸發(): void {
		// Given 一個沒有 userId 的 LINE Postback 事件
		$event = $this->make_postback_event( null, '{"action":"register"}' );

		// When 觸發 LINE webhook 的 postback type-only hook
		\do_action( 'power_funnel/line/webhook/postback', $event );

		// Then pf/trigger/line_postback_received 不被觸發
		$this->assertEmpty( $this->fired_triggers['line_postback_received'], '缺少 userId 的 postback 事件不應觸發' );
	}

	// ========== Rule: context_callable 使用 Serializable 格式 ==========

	/**
	 * Feature: 註冊 LINE_POSTBACK_RECEIVED 觸發點
	 * Example: context_callable_set 的 callable 為靜態方法陣列格式
	 *
	 * @group happy
	 */
	public function test_postback_context_callable使用可序列化格式(): void {
		// Given 一個有效的 LINE Postback 事件
		$event = $this->make_postback_event( 'U1234567890', '{"action":"register"}' );

		// When 觸發 hook
		\do_action( 'power_funnel/line/webhook/postback', $event );

		$this->assertCount( 1, $this->fired_triggers['line_postback_received'], 'line_postback_received 應被觸發' );

		$context_callable_set = $this->fired_triggers['line_postback_received'][0];

		// Then callable 應為陣列格式（非 Closure），可被序列化
		$this->assertIsArray( $context_callable_set['callable'], 'callable 應為陣列格式（非 Closure）' );
		$this->assertCount( 2, $context_callable_set['callable'], 'callable 陣列應有兩個元素 [class, method]' );
		$this->assertSame( TriggerPointService::class, $context_callable_set['callable'][0], 'callable[0] 應為 TriggerPointService::class' );
		$this->assertSame( 'resolve_line_postback_context', $context_callable_set['callable'][1], 'callable[1] 應為 resolve_line_postback_context' );

		// params 應為純值陣列（可序列化）
		$this->assertIsArray( $context_callable_set['params'], 'params 應為陣列' );
		foreach ( $context_callable_set['params'] as $param ) {
			$this->assertIsString( $param, 'params 中的每個值應為字串' );
		}

		// 確認可以被 serialize/unserialize
		$serialized   = \serialize( $context_callable_set );
		$unserialized = \unserialize( $serialized );
		$this->assertIsArray( $unserialized, 'context_callable_set 應可被序列化與反序列化' );
		$context = \call_user_func_array( $unserialized['callable'], $unserialized['params'] );
		$this->assertArrayHasKey( 'line_user_id', $context, '反序列化後應能正確執行' );
	}

	// ========== Rule: ETriggerPoint enum 包含 LINE_POSTBACK_RECEIVED ==========

	/**
	 * Feature: 註冊 LINE_POSTBACK_RECEIVED 觸發點
	 * Example: ETriggerPoint enum 新增 LINE_POSTBACK_RECEIVED 後可正確取得 hook 值和標籤
	 *
	 * @group happy
	 */
	public function test_ETriggerPoint包含LINE_POSTBACK_RECEIVED(): void {
		// When 系統讀取 ETriggerPoint::LINE_POSTBACK_RECEIVED
		$trigger_point = ETriggerPoint::LINE_POSTBACK_RECEIVED;

		// Then 該 enum case 的值應為 "pf/trigger/line_postback_received"
		$this->assertSame( 'pf/trigger/line_postback_received', $trigger_point->value, 'hook 值應正確' );

		// And 該 enum case 的 label 應為 "收到 LINE Postback 後"
		$this->assertSame( '收到 LINE Postback 後', $trigger_point->label(), 'label 應正確' );
	}
}
