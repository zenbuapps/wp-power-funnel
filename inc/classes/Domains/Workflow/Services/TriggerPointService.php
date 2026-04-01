<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Domains\Workflow\Services;

use J7\PowerFunnel\Plugin;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;

/**
 * 觸發點中央服務
 *
 * 負責監聽各業務域的生命週期事件，並轉換為對應的 pf/trigger/* hook。
 * 透過此服務集中管理所有 do_action('pf/trigger/...') 呼叫，
 * 避免將觸發邏輯散落在各個業務類別中。
 */
final class TriggerPointService {

	/**
	 * 註冊所有觸發點監聽器
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		// P0：報名狀態觸發點
		\add_action('power_funnel/registration/success', [ __CLASS__, 'on_registration_success' ], 10, 3);
		\add_action('power_funnel/registration/rejected', [ __CLASS__, 'on_registration_rejected' ], 10, 3);
		\add_action('power_funnel/registration/cancelled', [ __CLASS__, 'on_registration_cancelled' ], 10, 3);
		\add_action('power_funnel/registration/failed', [ __CLASS__, 'on_registration_failed' ], 10, 3);

		// P1：LINE 互動觸發點（type-only hooks，由 WebhookService::post_line_callback_callback 觸發）
		\add_action('power_funnel/line/webhook/follow', [ __CLASS__, 'on_line_followed' ], 10, 1);
		\add_action('power_funnel/line/webhook/unfollow', [ __CLASS__, 'on_line_unfollowed' ], 10, 1);
		\add_action('power_funnel/line/webhook/message', [ __CLASS__, 'on_line_message_received' ], 10, 1);
		\add_action('power_funnel/line/webhook/postback', [ __CLASS__, 'on_line_postback_received' ], 10, 1);

		// P2：工作流引擎觸發點
		\add_action('power_funnel/workflow/completed', [ __CLASS__, 'on_workflow_completed' ], 10, 1);
		\add_action('power_funnel/workflow/failed', [ __CLASS__, 'on_workflow_failed' ], 10, 1);

		// P4：WooCommerce 訂單觸發點（軟依賴）
		if ( \function_exists( 'wc_get_order' ) ) {
			\add_action('woocommerce_order_status_completed', [ __CLASS__, 'on_order_completed' ], 10, 1);
		}

		// Context Keys filter
		\add_filter('power_funnel/trigger_point/context_keys', [ __CLASS__, 'filter_context_keys' ], 10, 2);
	}

	// ========== P0：報名狀態觸發點處理 ==========

	/**
	 * 報名審核通過時觸發
	 *
	 * @param string   $new_status 新狀態
	 * @param string   $old_status 舊狀態
	 * @param \WP_Post $post       報名文章物件
	 * @return void
	 */
	public static function on_registration_success( string $new_status, string $old_status, \WP_Post $post ): void {
		// 同狀態轉換不觸發
		if ($new_status === $old_status) {
			return;
		}
		$context_callable_set = self::build_registration_context_callable_set($post->ID);
		if ($context_callable_set === null) {
			return;
		}
		\do_action(ETriggerPoint::REGISTRATION_APPROVED->value, $context_callable_set);
	}

	/**
	 * 報名被拒絕時觸發
	 *
	 * @param string   $new_status 新狀態
	 * @param string   $old_status 舊狀態
	 * @param \WP_Post $post       報名文章物件
	 * @return void
	 */
	public static function on_registration_rejected( string $new_status, string $old_status, \WP_Post $post ): void {
		if ($new_status === $old_status) {
			return;
		}
		$context_callable_set = self::build_registration_context_callable_set($post->ID);
		if ($context_callable_set === null) {
			return;
		}
		\do_action(ETriggerPoint::REGISTRATION_REJECTED->value, $context_callable_set);
	}

	/**
	 * 報名取消時觸發
	 *
	 * @param string   $new_status 新狀態
	 * @param string   $old_status 舊狀態
	 * @param \WP_Post $post       報名文章物件
	 * @return void
	 */
	public static function on_registration_cancelled( string $new_status, string $old_status, \WP_Post $post ): void {
		if ($new_status === $old_status) {
			return;
		}
		$context_callable_set = self::build_registration_context_callable_set($post->ID);
		if ($context_callable_set === null) {
			return;
		}
		\do_action(ETriggerPoint::REGISTRATION_CANCELLED->value, $context_callable_set);
	}

	/**
	 * 報名失敗時觸發
	 *
	 * @param string   $new_status 新狀態
	 * @param string   $old_status 舊狀態
	 * @param \WP_Post $post       報名文章物件
	 * @return void
	 */
	public static function on_registration_failed( string $new_status, string $old_status, \WP_Post $post ): void {
		if ($new_status === $old_status) {
			return;
		}
		$context_callable_set = self::build_registration_context_callable_set($post->ID);
		if ($context_callable_set === null) {
			return;
		}
		\do_action(ETriggerPoint::REGISTRATION_FAILED->value, $context_callable_set);
	}

	/**
	 * 建立報名 context_callable_set
	 *
	 * @param int $post_id 報名文章 ID
	 * @return array<string, mixed>|null 若文章不存在則回傳 null
	 */
	private static function build_registration_context_callable_set( int $post_id ): ?array {
		$post = \get_post($post_id);
		if (!$post) {
			Plugin::logger("TriggerPointService：找不到報名文章 #{$post_id}", 'warning');
			return null;
		}

		return [
			'callable' => [ self::class, 'resolve_registration_context' ],
			'params'   => [ $post_id ],
		];
	}

	/**
	 * 解析報名 context（Serializable Context Callable 目標方法）
	 *
	 * @param int $post_id 報名文章 ID
	 * @return array<string, string> context 陣列
	 */
	public static function resolve_registration_context( int $post_id ): array {
		$post = \get_post($post_id);
		if (!$post) {
			return [];
		}
		return [
			'registration_id'   => (string) $post_id,
			'identity_id'       => (string) \get_post_meta($post_id, 'identity_id', true),
			'identity_provider' => (string) \get_post_meta($post_id, 'identity_provider', true),
			'activity_id'       => (string) \get_post_meta($post_id, 'activity_id', true),
			'promo_link_id'     => (string) \get_post_meta($post_id, 'promo_link_id', true),
		];
	}

	// ========== P1：LINE 互動觸發點處理 ==========

	/**
	 * 用戶關注 LINE 官方帳號時觸發
	 *
	 * @param \LINE\Webhook\Model\Event $event LINE 事件
	 * @return void
	 */
	public static function on_line_followed( \LINE\Webhook\Model\Event $event ): void {
		$context_callable_set = self::build_line_context_callable_set($event);
		if ($context_callable_set === null) {
			return;
		}
		\do_action(ETriggerPoint::LINE_FOLLOWED->value, $context_callable_set);
	}

	/**
	 * 用戶取消關注 LINE 官方帳號時觸發
	 *
	 * @param \LINE\Webhook\Model\Event $event LINE 事件
	 * @return void
	 */
	public static function on_line_unfollowed( \LINE\Webhook\Model\Event $event ): void {
		$context_callable_set = self::build_line_context_callable_set($event);
		if ($context_callable_set === null) {
			return;
		}
		\do_action(ETriggerPoint::LINE_UNFOLLOWED->value, $context_callable_set);
	}

	/**
	 * 收到 LINE 訊息時觸發
	 *
	 * @param \LINE\Webhook\Model\Event $event LINE 事件
	 * @return void
	 */
	public static function on_line_message_received( \LINE\Webhook\Model\Event $event ): void {
		$context_callable_set = self::build_line_context_callable_set($event, true);
		if ($context_callable_set === null) {
			return;
		}
		\do_action(ETriggerPoint::LINE_MESSAGE_RECEIVED->value, $context_callable_set);
	}

	/**
	 * 收到 LINE Postback 時觸發
	 *
	 * @param \LINE\Webhook\Model\Event $event LINE 事件
	 * @return void
	 */
	public static function on_line_postback_received( \LINE\Webhook\Model\Event $event ): void {
		if (!($event instanceof \LINE\Webhook\Model\PostbackEvent)) {
			return;
		}
		$context_callable_set = self::build_line_postback_context_callable_set($event);
		if ($context_callable_set === null) {
			return;
		}
		\do_action(ETriggerPoint::LINE_POSTBACK_RECEIVED->value, $context_callable_set);
	}

	/**
	 * 建立 LINE Postback 事件 context_callable_set
	 *
	 * @param \LINE\Webhook\Model\PostbackEvent $event LINE Postback 事件
	 * @return array<string, mixed>|null 若事件無 userId 則回傳 null
	 */
	private static function build_line_postback_context_callable_set( \LINE\Webhook\Model\PostbackEvent $event ): ?array {
		$helper       = new \J7\PowerFunnel\Infrastructure\Line\Shared\Helpers\EventWebhookHelper($event);
		$line_user_id = $helper->get_identity_id();

		if (empty($line_user_id)) {
			Plugin::logger('TriggerPointService：LINE Postback 事件缺少 userId，跳過觸發', 'info');
			return null;
		}

		$postback      = $event->getPostback();
		$postback_data = $postback ? ($postback->getData() ?? '') : '';

		// 嘗試解析 JSON 取出 action key，非 JSON 時 postback_action 為空字串
		$postback_action = '';
		if ($postback_data !== '') {
			try {
				/** @var array<string, mixed>|false $decoded */
				$decoded = \json_decode($postback_data, true, 512, \JSON_THROW_ON_ERROR);
				if (\is_array($decoded) && isset($decoded['action']) && \is_string($decoded['action'])) {
					$postback_action = $decoded['action'];
				}
			} catch (\JsonException $e) {
				// 非 JSON 格式，postback_action 維持空字串
			}
		}

		return [
			'callable' => [self::class, 'resolve_line_postback_context'],
			'params'   => [$line_user_id, 'postback', $postback_data, $postback_action],
		];
	}

	/**
	 * 解析 LINE Postback context（Serializable Context Callable 目標方法）
	 *
	 * @param string $line_user_id    LINE 用戶 ID
	 * @param string $event_type      事件類型（固定為 postback）
	 * @param string $postback_data   Postback 原始資料字串
	 * @param string $postback_action Postback action 值（從 JSON 解析，非 JSON 時為空字串）
	 * @return array<string, string> context 陣列
	 */
	public static function resolve_line_postback_context( string $line_user_id, string $event_type, string $postback_data, string $postback_action ): array {
		return [
			'line_user_id'    => $line_user_id,
			'event_type'      => $event_type,
			'postback_data'   => $postback_data,
			'postback_action' => $postback_action,
		];
	}

	/**
	 * 建立 LINE 事件 context_callable_set
	 *
	 * @param \LINE\Webhook\Model\Event $event           LINE 事件
	 * @param bool                      $include_message 是否包含訊息文字
	 * @return array<string, mixed>|null 若事件無 userId 則回傳 null
	 */
	private static function build_line_context_callable_set( \LINE\Webhook\Model\Event $event, bool $include_message = false ): ?array {
		$helper       = new \J7\PowerFunnel\Infrastructure\Line\Shared\Helpers\EventWebhookHelper($event);
		$line_user_id = $helper->get_identity_id();

		if (empty($line_user_id)) {
			Plugin::logger('TriggerPointService：LINE 事件缺少 userId，跳過觸發', 'info');
			return null;
		}

		$event_type   = $event->getType();
		$message_text = '';

		if ($include_message && $event instanceof \LINE\Webhook\Model\MessageEvent) {
			$message = $event->getMessage();
			if ($message instanceof \LINE\Webhook\Model\TextMessageContent) {
				$message_text = $message->getText() ?? '';
			}
		}

		return [
			'callable' => [ self::class, 'resolve_line_context' ],
			'params'   => [ $line_user_id, $event_type, $message_text ],
		];
	}

	/**
	 * 解析 LINE 事件 context（Serializable Context Callable 目標方法）
	 *
	 * @param string $line_user_id LINE 用戶 ID
	 * @param string $event_type   事件類型
	 * @param string $message_text 訊息文字（非訊息事件時為空字串）
	 * @return array<string, string> context 陣列
	 */
	public static function resolve_line_context( string $line_user_id, string $event_type, string $message_text = '' ): array {
		$data = [
			'line_user_id' => $line_user_id,
			'event_type'   => $event_type,
		];
		if ($message_text !== '') {
			$data['message_text'] = $message_text;
		}
		return $data;
	}

	// ========== P2：工作流引擎觸發點處理 ==========

	/**
	 * 工作流完成時觸發
	 *
	 * @param string $workflow_id 工作流 ID
	 * @return void
	 */
	public static function on_workflow_completed( string $workflow_id ): void {
		$context_callable_set = self::build_workflow_context_callable_set($workflow_id);
		if ($context_callable_set === null) {
			return;
		}
		\do_action(ETriggerPoint::WORKFLOW_COMPLETED->value, $context_callable_set);
	}

	/**
	 * 工作流失敗時觸發
	 *
	 * @param string $workflow_id 工作流 ID
	 * @return void
	 */
	public static function on_workflow_failed( string $workflow_id ): void {
		$context_callable_set = self::build_workflow_context_callable_set($workflow_id);
		if ($context_callable_set === null) {
			return;
		}
		\do_action(ETriggerPoint::WORKFLOW_FAILED->value, $context_callable_set);
	}

	/**
	 * 建立工作流 context_callable_set
	 *
	 * @param string $workflow_id 工作流 ID
	 * @return array<string, mixed>|null 若工作流不存在則回傳 null
	 */
	private static function build_workflow_context_callable_set( string $workflow_id ): ?array {
		$post = \get_post( (int) $workflow_id);
		if (!$post) {
			Plugin::logger("TriggerPointService：找不到工作流 #{$workflow_id}", 'warning');
			return null;
		}

		return [
			'callable' => [ self::class, 'resolve_workflow_context' ],
			'params'   => [ $workflow_id ],
		];
	}

	/**
	 * 解析工作流 context（Serializable Context Callable 目標方法）
	 *
	 * @param string $workflow_id 工作流 ID
	 * @return array<string, string> context 陣列
	 */
	public static function resolve_workflow_context( string $workflow_id ): array {
		return [
			'workflow_id'      => $workflow_id,
			'workflow_rule_id' => (string) \get_post_meta( (int) $workflow_id, 'workflow_rule_id', true),
			'trigger_point'    => (string) \get_post_meta( (int) $workflow_id, 'trigger_point', true),
		];
	}

	// ========== P3：用戶行為觸發點 ==========

	/**
	 * 觸發「用戶被貼標籤」事件
	 * 供 TagUserNode::execute() 呼叫
	 *
	 * @param string $user_id  LINE 用戶 ID
	 * @param string $tag_name 標籤名稱
	 * @return void
	 */
	public static function fire_user_tagged( string $user_id, string $tag_name ): void {
		$context_callable_set = [
			'callable' => [ self::class, 'resolve_user_tagged_context' ],
			'params'   => [ $user_id, $tag_name ],
		];
		\do_action(ETriggerPoint::USER_TAGGED->value, $context_callable_set);
	}

	/**
	 * 解析用戶標籤 context（Serializable Context Callable 目標方法）
	 *
	 * @param string $user_id  LINE 用戶 ID
	 * @param string $tag_name 標籤名稱
	 * @return array<string, string> context 陣列
	 */
	public static function resolve_user_tagged_context( string $user_id, string $tag_name ): array {
		return [
			'user_id'  => $user_id,
			'tag_name' => $tag_name,
		];
	}

	// ========== P4：WooCommerce 訂單觸發點處理 ==========

	/**
	 * WooCommerce 訂單完成時觸發
	 *
	 * @param int $order_id WooCommerce 訂單 ID
	 * @return void
	 */
	public static function on_order_completed( int $order_id ): void {
		$context_callable_set = self::build_order_context_callable_set($order_id);
		if ($context_callable_set === null) {
			return;
		}
		\do_action(ETriggerPoint::ORDER_COMPLETED->value, $context_callable_set);
	}

	/**
	 * 建立訂單 context_callable_set
	 *
	 * @param int $order_id WooCommerce 訂單 ID
	 * @return array<string, mixed>|null 若訂單不存在則回傳 null
	 */
	private static function build_order_context_callable_set( int $order_id ): ?array {
		if (!\function_exists('wc_get_order')) {
			Plugin::logger('TriggerPointService：WooCommerce 未啟用，跳過訂單觸發', 'warning');
			return null;
		}

		$order = \wc_get_order($order_id);
		if (!$order) {
			Plugin::logger("TriggerPointService：找不到訂單 #{$order_id}", 'warning');
			return null;
		}

		return [
			'callable' => [ self::class, 'resolve_order_context' ],
			'params'   => [ $order_id ],
		];
	}

	/**
	 * 解析訂單 context（Serializable Context Callable 目標方法）
	 *
	 * 延遲求值：每次呼叫時從 DB 讀取最新訂單資料，
	 * 確保 WaitNode 延遲後仍能取得最新值。
	 *
	 * @param int $order_id WooCommerce 訂單 ID
	 * @return array<string, string> context 陣列（9 個 keys），訂單不存在時回傳空陣列
	 */
	public static function resolve_order_context( int $order_id ): array {
		if ($order_id <= 0 || !\function_exists('wc_get_order')) {
			return [];
		}

		$order = \wc_get_order($order_id);
		if (!$order) {
			return [];
		}

		// 組裝商品清單摘要
		$line_items = [];
		foreach ($order->get_items() as $item) {
			$line_items[] = $item->get_name() . ' x' . $item->get_quantity();
		}
		$line_items_summary = \implode(', ', $line_items);

		// 配送地址，若為空則使用帳單地址
		$shipping_address = $order->get_formatted_shipping_address();
		if (empty($shipping_address)) {
			$shipping_address = $order->get_formatted_billing_address();
		}

		return [
			'order_id'           => (string) $order->get_id(),
			'order_total'        => (string) $order->get_total(),
			'billing_email'      => $order->get_billing_email(),
			'customer_id'        => (string) $order->get_customer_id(),
			'line_items_summary' => $line_items_summary,
			'shipping_address'   => (string) $shipping_address,
			'payment_method'     => $order->get_payment_method(),
			'order_date'         => $order->get_date_created()?->format('Y-m-d') ?? '',
			'billing_phone'      => $order->get_billing_phone(),
		];
	}

	// ========== Context Keys 查詢 ==========

	/**
	 * Context Keys 靜態映射表
	 *
	 * @var array<string, array<int, array{key: string, label: string}>>|null
	 */
	private static ?array $context_keys_map = null;

	/**
	 * 取得 Context Keys 映射表
	 *
	 * @return array<string, array<int, array{key: string, label: string}>>
	 */
	private static function get_context_keys_map(): array {
		if (self::$context_keys_map !== null) {
			return self::$context_keys_map;
		}

		$order_keys = [
			[
				'key'   => 'order_id',
				'label' => '訂單 ID',
			],
			[
				'key'   => 'order_total',
				'label' => '訂單金額',
			],
			[
				'key'   => 'billing_email',
				'label' => '帳單 Email',
			],
			[
				'key'   => 'customer_id',
				'label' => '客戶 ID',
			],
			[
				'key'   => 'line_items_summary',
				'label' => '商品清單摘要',
			],
			[
				'key'   => 'shipping_address',
				'label' => '配送地址',
			],
			[
				'key'   => 'payment_method',
				'label' => '付款方式',
			],
			[
				'key'   => 'order_date',
				'label' => '訂單日期',
			],
			[
				'key'   => 'billing_phone',
				'label' => '帳單電話',
			],
		];

		$registration_keys = [
			[
				'key'   => 'registration_id',
				'label' => '報名 ID',
			],
			[
				'key'   => 'identity_id',
				'label' => '身分 ID',
			],
			[
				'key'   => 'identity_provider',
				'label' => '身分提供者',
			],
			[
				'key'   => 'activity_id',
				'label' => '活動 ID',
			],
			[
				'key'   => 'promo_link_id',
				'label' => '推廣連結 ID',
			],
		];

		$line_keys = [
			[
				'key'   => 'line_user_id',
				'label' => 'LINE 用戶 ID',
			],
			[
				'key'   => 'event_type',
				'label' => '事件類型',
			],
		];

		$line_postback_keys = [
			[
				'key'   => 'line_user_id',
				'label' => 'LINE 用戶 ID',
			],
			[
				'key'   => 'event_type',
				'label' => '事件類型',
			],
			[
				'key'   => 'postback_data',
				'label' => 'Postback 原始資料',
			],
			[
				'key'   => 'postback_action',
				'label' => 'Postback Action',
			],
		];

		$line_message_keys = [
			[
				'key'   => 'line_user_id',
				'label' => 'LINE 用戶 ID',
			],
			[
				'key'   => 'event_type',
				'label' => '事件類型',
			],
			[
				'key'   => 'message_text',
				'label' => '訊息文字',
			],
		];

		$workflow_keys = [
			[
				'key'   => 'workflow_id',
				'label' => '工作流 ID',
			],
			[
				'key'   => 'workflow_rule_id',
				'label' => '工作流規則 ID',
			],
			[
				'key'   => 'trigger_point',
				'label' => '觸發點',
			],
		];

		$user_tagged_keys = [
			[
				'key'   => 'user_id',
				'label' => '用戶 ID',
			],
			[
				'key'   => 'tag_name',
				'label' => '標籤名稱',
			],
		];

		self::$context_keys_map = [
			ETriggerPoint::ORDER_COMPLETED->value        => $order_keys,

			ETriggerPoint::REGISTRATION_APPROVED->value  => $registration_keys,
			ETriggerPoint::REGISTRATION_REJECTED->value  => $registration_keys,
			ETriggerPoint::REGISTRATION_CANCELLED->value => $registration_keys,
			ETriggerPoint::REGISTRATION_FAILED->value    => $registration_keys,
			ETriggerPoint::REGISTRATION_CREATED->value   => $registration_keys,

			ETriggerPoint::LINE_FOLLOWED->value          => $line_keys,
			ETriggerPoint::LINE_UNFOLLOWED->value        => $line_keys,
			ETriggerPoint::LINE_MESSAGE_RECEIVED->value  => $line_message_keys,
			ETriggerPoint::LINE_POSTBACK_RECEIVED->value => $line_postback_keys,

			ETriggerPoint::WORKFLOW_COMPLETED->value     => $workflow_keys,
			ETriggerPoint::WORKFLOW_FAILED->value        => $workflow_keys,

			ETriggerPoint::USER_TAGGED->value            => $user_tagged_keys,
		];

		return self::$context_keys_map;
	}

	/**
	 * 取得指定觸發點的可用 Context Keys
	 *
	 * @param string $trigger_point_hook 觸發點 hook 名稱
	 * @return array<int, array{key: string, label: string}> context keys 清單
	 */
	public static function get_context_keys_for_trigger_point( string $trigger_point_hook ): array {
		$map = self::get_context_keys_map();
		return $map[ $trigger_point_hook ] ?? [];
	}

	/**
	 * Filter: power_funnel/trigger_point/context_keys
	 *
	 * @param array<int, array{key: string, label: string}> $keys           現有的 keys
	 * @param string                                        $trigger_point  觸發點 hook 名稱
	 * @return array<int, array{key: string, label: string}> context keys
	 */
	public static function filter_context_keys( array $keys, string $trigger_point ): array {
		if (empty($trigger_point)) {
			return [];
		}
		return self::get_context_keys_for_trigger_point($trigger_point);
	}
}
