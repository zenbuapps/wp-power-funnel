<?php

declare( strict_types = 1 );

namespace J7\PowerFunnel\Shared\Enums;

/**
 * 觸發時機點
 * 預先註冊的 hook name
 * apply_filter(string $trigger_point, WorkflowContextDTO $context);
 */
enum ETriggerPoint : string {
	private const PREFIX = 'pf/trigger/';

	// ========== P0: 報名狀態觸發點 ==========

	/** @deprecated 保留向後相容，新觸發點請使用 REGISTRATION_APPROVED */
	case REGISTRATION_CREATED = self::PREFIX . 'registration_created';

	case REGISTRATION_APPROVED  = self::PREFIX . 'registration_approved';
	case REGISTRATION_REJECTED  = self::PREFIX . 'registration_rejected';
	case REGISTRATION_CANCELLED = self::PREFIX . 'registration_cancelled';
	case REGISTRATION_FAILED    = self::PREFIX . 'registration_failed';

	// ========== P1: LINE 互動觸發點 ==========

	case LINE_FOLLOWED          = self::PREFIX . 'line_followed';
	case LINE_UNFOLLOWED        = self::PREFIX . 'line_unfollowed';
	case LINE_MESSAGE_RECEIVED  = self::PREFIX . 'line_message_received';
	case LINE_POSTBACK_RECEIVED = self::PREFIX . 'line_postback_received';

	/** 枚舉存根：Bot 被加入群組（目前無群組事件實作，僅列出供前端顯示） */
	case LINE_JOIN = self::PREFIX . 'line_join';

	/** 枚舉存根：Bot 被移出群組（目前無群組事件實作，僅列出供前端顯示） */
	case LINE_LEAVE = self::PREFIX . 'line_leave';

	/** 枚舉存根：新成員加入群組（目前無群組事件實作，僅列出供前端顯示） */
	case LINE_MEMBER_JOINED = self::PREFIX . 'line_member_joined';

	/** 枚舉存根：成員離開群組（目前無群組事件實作，僅列出供前端顯示） */
	case LINE_MEMBER_LEFT = self::PREFIX . 'line_member_left';

	// ========== P2: 工作流引擎觸發點 ==========

	case WORKFLOW_COMPLETED = self::PREFIX . 'workflow_completed';
	case WORKFLOW_FAILED    = self::PREFIX . 'workflow_failed';

	// ========== P3: 活動時間觸發點 ==========

	case ACTIVITY_STARTED      = self::PREFIX . 'activity_started';
	case ACTIVITY_BEFORE_START = self::PREFIX . 'activity_before_start';

	/** 枚舉存根：活動已結束（目前無結束時間資料來源，僅列出供前端顯示） */
	case ACTIVITY_ENDED = self::PREFIX . 'activity_ended';

	// ========== P3: 用戶行為觸發點 ==========

	case USER_TAGGED = self::PREFIX . 'user_tagged';

	/** 枚舉存根：推廣連結被點擊（目前無點擊追蹤機制，僅列出供前端顯示） */
	case PROMO_LINK_CLICKED = self::PREFIX . 'promo_link_clicked';

	// ========== P4: WooCommerce 觸發點 ==========

	case ORDER_COMPLETED = self::PREFIX . 'order_completed';

	/** 標籤 */
	public function label(): string {
		$mapper = [
			self::REGISTRATION_CREATED->value   => '用戶報名後（舊）',
			self::REGISTRATION_APPROVED->value  => '用戶報名審核通過後',
			self::REGISTRATION_REJECTED->value  => '用戶報名被拒絕後',
			self::REGISTRATION_CANCELLED->value => '用戶取消報名後',
			self::REGISTRATION_FAILED->value    => '用戶報名失敗後',

			self::LINE_FOLLOWED->value          => '用戶關注 LINE 官方帳號後',
			self::LINE_UNFOLLOWED->value        => '用戶取消關注 LINE 官方帳號後',
			self::LINE_MESSAGE_RECEIVED->value  => '收到 LINE 訊息後',
			self::LINE_POSTBACK_RECEIVED->value => '收到 LINE Postback 後',
			self::LINE_JOIN->value              => 'Bot 被加入群組後',
			self::LINE_LEAVE->value             => 'Bot 被移出群組後',
			self::LINE_MEMBER_JOINED->value     => '新成員加入群組後',
			self::LINE_MEMBER_LEFT->value       => '成員離開群組後',

			self::WORKFLOW_COMPLETED->value     => '工作流完成後',
			self::WORKFLOW_FAILED->value        => '工作流失敗後',

			self::ACTIVITY_STARTED->value       => '活動開始時',
			self::ACTIVITY_BEFORE_START->value  => '活動開始前',
			self::ACTIVITY_ENDED->value         => '活動結束後',

			self::USER_TAGGED->value            => '用戶被貼標籤後',
			self::PROMO_LINK_CLICKED->value     => '推廣連結被點擊後',

			self::ORDER_COMPLETED->value        => '訂單完成後',
		];
		return $mapper[ $this->value ];
	}

	/**
	 * 所屬群組 key（用於 API 分組結構）
	 *
	 * @return string 群組 key
	 */
	public function group(): string {
		$mapper = [
			self::REGISTRATION_CREATED->value   => 'registration',
			self::REGISTRATION_APPROVED->value  => 'registration',
			self::REGISTRATION_REJECTED->value  => 'registration',
			self::REGISTRATION_CANCELLED->value => 'registration',
			self::REGISTRATION_FAILED->value    => 'registration',

			self::LINE_FOLLOWED->value          => 'line_interaction',
			self::LINE_UNFOLLOWED->value        => 'line_interaction',
			self::LINE_MESSAGE_RECEIVED->value  => 'line_interaction',
			self::LINE_POSTBACK_RECEIVED->value => 'line_interaction',

			self::LINE_JOIN->value              => 'line_group',
			self::LINE_LEAVE->value             => 'line_group',
			self::LINE_MEMBER_JOINED->value     => 'line_group',
			self::LINE_MEMBER_LEFT->value       => 'line_group',

			self::WORKFLOW_COMPLETED->value     => 'workflow',
			self::WORKFLOW_FAILED->value        => 'workflow',

			self::ACTIVITY_STARTED->value       => 'activity',
			self::ACTIVITY_BEFORE_START->value  => 'activity',
			self::ACTIVITY_ENDED->value         => 'activity',

			self::USER_TAGGED->value            => 'user_behavior',
			self::PROMO_LINK_CLICKED->value     => 'user_behavior',

			self::ORDER_COMPLETED->value        => 'woocommerce',
		];
		return $mapper[ $this->value ];
	}

	/**
	 * 所屬群組中文標籤（用於前端 OptGroup 顯示）
	 *
	 * @return string 群組中文標籤
	 */
	public function group_label(): string {
		$label_map = [
			'registration'     => '報名狀態',
			'line_interaction' => 'LINE 互動',
			'line_group'       => 'LINE 群組',
			'workflow'         => '工作流引擎',
			'activity'         => '活動時間',
			'user_behavior'    => '用戶行為',
			'woocommerce'      => 'WooCommerce',
		];
		return $label_map[ $this->group() ];
	}

	/**
	 * 是否為枚舉存根（目前尚未實作的觸發點）
	 *
	 * 存根觸發點在 API 回應中標記為 disabled，名稱帶有「（即將推出）」後綴。
	 *
	 * @return bool 是否為枚舉存根
	 */
	public function is_stub(): bool {
		return match ($this) {
			self::LINE_JOIN,
			self::LINE_LEAVE,
			self::LINE_MEMBER_JOINED,
			self::LINE_MEMBER_LEFT,
			self::ACTIVITY_ENDED,
			self::PROMO_LINK_CLICKED => true,
			default                  => false,
		};
	}
}
