<?php

declare (strict_types = 1);

namespace J7\PowerFunnel\Infrastructure\Youtube\DTOs;

use J7\WpUtils\Classes\DTO;

/** Setting DTO 取得設定 */
final class SettingDTO extends DTO {

	/** @var string 儲存在 options table 的 option name */
	private const OPTION_NAME = '_power_funnel_youtube_setting';

	/** @var string 用戶端 Id */
	public string $clientId = '';

	/** @var string 用戶端密碼 */
	public string $clientSecret = '';

	/** @var string Redirect Uri */
	public string $redirectUri = '';


	/** 取得實例 */
	public static function instance(): self {
		$args                = \get_option(self::OPTION_NAME, []);
		$args                = \is_array($args) ? $args : [];
		$args['redirectUri'] = \site_url();
		return new self($args);
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
