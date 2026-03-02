<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Applications;

use J7\PowerFunnel\Contracts\DTOs\ActivityDTO;
use J7\PowerFunnel\Contracts\DTOs\RegistrationDTO;
use J7\PowerFunnel\Domains\Activity\Services\ActivityService;
use J7\PowerFunnel\Infrastructure\Line\Services\MessageService;
use J7\PowerFunnel\Infrastructure\Line\Shared\Helpers\EventWebhookHelper;
use J7\PowerFunnel\Infrastructure\Repositories\Registration\Repository;
use J7\PowerFunnel\Plugin;
use J7\PowerFunnel\Shared\Enums\EAction;
use J7\PowerFunnel\Shared\Enums\EIdentityProvider;
use J7\PowerFunnel\Shared\Enums\ELineActionType;
use J7\PowerFunnel\Shared\Enums\ERegistrationStatus;
use J7\Powerhouse\Contracts\DTOs\MessageTemplateDTO;
use J7\Powerhouse\Shared\Enums\EContentType;
use J7\Powerhouse\Shared\Helpers\ReplaceHelper;
use LINE\Webhook\Model\Event;

/**
 * 用戶報名指定活動
 */
final class RegisterActivityViaLine {

	/** Register hooks */
	public static function register_hooks(): void {

		$line_action_type = ELineActionType::POSTBACK;
		$action           = EAction::REGISTER;
		// 用 LINE 報名活動
		\add_action( "power_funnel/line/webhook/{$line_action_type->value}/{$action->value}", [ __CLASS__, 'line_postback' ] );

		// 是否可以報名，是否已經註冊過
		\add_filter( 'power_funnel/registration/can_register', [ __CLASS__ ,'check_registered' ], 10, 4 );

		// 報名活動狀態改變時發 LINE 通知
		foreach (ERegistrationStatus::cases() as $status) {
			\add_action( "power_funnel/registration/{$status->value}", [ __CLASS__, 'line' ], 10, 3 );

			if (ERegistrationStatus::PENDING === $status) {
				// 是否需要自動審核成功
				\add_action( "power_funnel/registration/{$status->value}", [ __CLASS__, 'auto_success' ], 20, 3 );
			}
		}
	}


	/**
	 * 用戶報名指定活動
	 *
	 * @param Event $event LINE 事件
	 * @return void
	 */
	public static function line_postback( Event $event ): void {
		$helper        = new EventWebhookHelper( $event);
		$activity_id   = $helper->get_activity_id();
		$identity_id   = $helper->get_identity_id();
		$promo_link_id = $helper->get_promo_link_id();

		if (!$activity_id || !$identity_id) {
			throw new \Exception( "活動 ID #{$activity_id} 或用戶 ID {$identity_id} 無法取得" );
		}
		$activity_dto = ActivityService::instance()->get_activity( $activity_id );
		if (!$activity_dto) {
			throw new \Exception( "找不到活動 #{$activity_id}" );
		}
		$identity_provider = $helper->get_identity_provider();

		$can_register = \apply_filters( 'power_funnel/registration/can_register', true, $identity_id, $identity_provider, $activity_dto );
		if (!$can_register) {
			return;
		}

		$args = [
			'post_title' => "{$identity_provider->value} 用戶報名 {$activity_dto->title}",
			'meta_input' => [
				'activity_id'       => $activity_id,
				'identity_id'       => $identity_id,
				'promo_link_id'     => $promo_link_id,
				'identity_provider' => $identity_provider->value,
			],
		];
		Repository::create( $args);
	}

	/** @retrun bool 檢查用戶是否已經報名過此活動 */
	public static function check_registered( bool $can_register, string $identity_id, EIdentityProvider $identity_provider, ActivityDTO $activity_dto ): bool {
		if (!$can_register) {
			return false;
		}
		$registered_registration = Repository::get_registered_registration( $identity_id, $identity_provider, $activity_dto->id);
		if ($registered_registration) {
			$registration_dto = RegistrationDTO::of( $registered_registration );
			self::send_text_message( $registration_dto, 'registered');
			return false;
		}

		return $can_register;
	}



	/**
	 * 文章狀態改變時
	 *
	 * @param string   $new_status 新狀態
	 * @param string   $old_status 就狀態
	 * @param \WP_Post $post 文章物件
	 */
	public static function line( string $new_status, string $old_status, \WP_Post $post ): void {
		try {
			$status           = ERegistrationStatus::from( $new_status );
			$registration_dto = RegistrationDTO::of( $post );

			self::send_text_message( $registration_dto, $status);
		} catch (\Throwable $e) {
			Plugin::logger( "用戶報名 #{$post->ID} 狀態轉為{$status->label()}時，發 line 失敗: {$e->getMessage()}", 'error');
		}
	}

	/**
	 * 自動審核成功
	 *
	 * @param string   $new_status 新狀態
	 * @param string   $old_status 就狀態
	 * @param \WP_Post $post 文章物件
	 */
	public static function auto_success( string $new_status, string $old_status, \WP_Post $post ): void {
		$registration_dto = RegistrationDTO::of( $post );
		if ( !$registration_dto->auto_approved) {
			return;
		}

		\wp_update_post(
			[
				'ID'          => $registration_dto->id,
				'post_status' => ERegistrationStatus::SUCCESS->value,
			]
		);
	}

	/**
	 * 發送 line 文字訊息
	 *
	 * @param RegistrationDTO            $registration_dto 活動報名 dto
	 * @param ERegistrationStatus|string $status 狀態
	 *
	 * @return void
	 */
	public static function send_text_message( RegistrationDTO $registration_dto, ERegistrationStatus|string $status ): void {
		$activity_dto = $registration_dto->activity;
		$user_dto     = $registration_dto->user;

		$service = MessageService::instance();
		$service->send_text_message( $registration_dto->user->id, "已收到您 {$registration_dto->activity->title} 的報名");

		$message_tpl_id = $registration_dto->promo_link->get_message_tpl_id( $status );
		if (!$message_tpl_id) {
			return;
		}
		$message_tpl_dto  = MessageTemplateDTO::of( $message_tpl_id );
		$replaced_message = ( new ReplaceHelper($message_tpl_dto->content) )->replace( $activity_dto )->replace( $user_dto )->get_replaced_template();
		if (!$replaced_message || $message_tpl_dto->content_type !== EContentType::PLAIN_TEXT) { // 只接受純文字
			return;
		}

		$service->send_text_message( $registration_dto->user->id, $replaced_message);
	}
}
