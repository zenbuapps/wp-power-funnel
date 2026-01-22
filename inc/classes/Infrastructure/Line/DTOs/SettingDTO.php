<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Line\DTOs;

use J7\WpUtils\Classes\DTO;

/**
 * LINE 設定 DTO
 * 儲存 LINE Messaging API 所需的設定參數
 */
final class SettingDTO extends DTO {

	/** @var string 儲存在 options table 的 option name */
	private const OPTION_NAME = '_power_funnel_line_setting';

	/** LIFF ID */
	public string $liff_id = '';

	/** LIFF URL */
	public string $liff_url = '';

	/**
	 * Channel ID
	 * LINE Developers Console 中的 Channel ID
	 *
	 * @var string
	 */
	public string $channel_id = '';

	/**
	 * Channel Secret
	 * 用於驗證 Webhook 簽章
	 *
	 * @var string
	 */
	public string $channel_secret = '';


	/**
	 * Channel Access Token
	 * 用於驗證 LINE Messaging API 的存取權杖
	 *
	 * @var string
	 */
	public string $channel_access_token = '';

	/**  @return self 取得實例 */
	public static function instance(): self {
		$args = \get_option(self::OPTION_NAME, []);
		$args = \is_array($args) ? $args : [];
		return new self($args);
	}


	/**
	 * 驗證設定是否完整
	 *
	 * @return bool 設定是否完整
	 */
	public function is_valid(): bool {
		return !empty($this->channel_access_token)
		&& !empty($this->channel_id)
		&& !empty($this->channel_secret);
	}

	/**
	 * 儲存設定
	 *
	 * @return bool 是否儲存成功
	 */
	public function save(): bool {
		return \update_option(self::OPTION_NAME, $this->to_array());
	}
}
