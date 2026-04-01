<?php

declare( strict_types = 1 );

namespace J7\PowerFunnel\Contracts\DTOs;

use J7\WpUtils\Classes\DTO;

/** Workflow 執行結果 DTO */
final class WorkflowResultDTO extends DTO {

	/** @var array<string> 必須的屬性（node_id 為必填） */
	protected array $require_properties = [ 'node_id' ];

	/** @var string 執行的節點 id */
	public string $node_id;

	/** @var int 狀態碼 */
	public int $code = 0;
	/** @var string 訊息 */
	public string $message = '';
	/** @var mixed 處理的結果值 */
	public mixed $data = null;

	/** @var string 下一個要執行的節點 ID（空字串表示繼續線性執行） */
	public string $next_node_id = '';

	/** @var string 節點執行時間戳（ISO 8601 格式），預設空字串以向下相容舊資料 */
	public string $executed_at = '';

	/** 是否成功 */
	public function is_success(): bool {
		return $this->code === 200;
	}
}
