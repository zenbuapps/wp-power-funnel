<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Shared\Enums;

use J7\PowerFunnel\Infrastructure\Line\DTOs\SettingDTO as LineSettingDTO;
use J7\PowerFunnel\Infrastructure\Youtube\DTOs\SettingDTO as YoutubeSettingDTO;
use J7\PowerFunnel\Infrastructure\Youtube\Services\YoutubeService;

/**
 * Option Api 要獲取的設定項
 */
enum EOptionName: string {
	case LINE         = 'line';
	case YOUTUBE      = 'youtube';
	case GOOGLE_OAUTH = 'googleOauth';

	/** 取得設定 */
	public function get_settings(): array {
		$service = YoutubeService::instance();
		return match ($this) {
			self::LINE    => LineSettingDTO::instance()->to_array(),
			self::YOUTUBE => YoutubeSettingDTO::instance()->to_array(),
			self::GOOGLE_OAUTH => [
				'isAuthorized' => $service->is_authorized(),
				'authUrl'      => $service->get_auth_url(),
			],
		};
	}

	/** 儲存 */
	public function save( array $data ): bool {
		return match ($this) {
			self::LINE         => ( new LineSettingDTO($data) )->save(),
			self::YOUTUBE      => ( new YoutubeSettingDTO($data) )->save(),
			self::GOOGLE_OAUTH => true,
		};
	}
}
