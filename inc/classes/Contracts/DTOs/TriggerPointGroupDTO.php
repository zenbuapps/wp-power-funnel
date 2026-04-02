<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\DTOs;

use J7\WpUtils\Classes\DTO;

/**
 * 觸發時機點分組 DTO
 *
 * 封裝一個分組的觸發點資料，包含群組 key、中文標籤，以及所屬的觸發點清單。
 * to_array() 會遞迴序列化 $items 陣列中的每個 TriggerPointDTO。
 */
final class TriggerPointGroupDTO extends DTO {

	/** @var string 群組 key（如 registration、line_interaction） */
	public string $group;

	/** @var string 群組中文標籤（如 報名狀態、LINE 互動） */
	public string $group_label;

	/** @var TriggerPointDTO[] 屬於此群組的觸發點項目 */
	public array $items = [];
}
