<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\DTOs;

use J7\WpUtils\Classes\DTO;

/** 觸發時機點 DTO */
final class TriggerPointDTO extends DTO {

	/** @var string 顯示名稱 */
	public string $name = '未命名的 hook';

	/** @var string hook */
	public string $hook;

	/** @var bool 是否為已停用（枚舉存根）的觸發點 */
	public bool $disabled = false;
}
