<?php
/**
 * 測試用失敗節點定義 Stub。
 *
 * 繼承 BaseNodeDefinition 但覆寫 __construct() 以跳過 Plugin::$url 與 FormFieldDTO 依賴。
 * execute() 方法永遠拋出 RuntimeException，讓 NodeDTO::try_execute() 產生 code=500。
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\Workflow\Stubs;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\BaseNodeDefinition;
use J7\PowerFunnel\Shared\Enums\ENodeType;

/**
 * 測試用失敗節點定義（永遠拋出例外）
 */
final class TestFailNodeDefinition extends BaseNodeDefinition {

	/** @var string Node ID */
	public string $id = 'test_fail_node';

	/** @var string Node 名稱 */
	public string $name = '測試失敗節點';

	/** @var string Node 描述 */
	public string $description = '測試用，永遠拋出例外';

	/** @var ENodeType Node 分類 */
	public ENodeType $type = ENodeType::SEND_MESSAGE;

	/**
	 * 覆寫 __construct()，跳過 Plugin::$url 與 FormFieldDTO 的依賴
	 * （測試環境中 FormFieldDTO 所在套件未載入）
	 */
	public function __construct() {
		// 不呼叫 parent::__construct()，避免 Plugin::$url 觸發
		$this->icon        = '';
		$this->form_fields = [];
	}

	/**
	 * 執行節點（永遠拋出例外）
	 *
	 * @param NodeDTO     $node     節點
	 * @param WorkflowDTO $workflow 工作流
	 * @return WorkflowResultDTO
	 * @throws \RuntimeException 永遠拋出
	 */
	public function execute( NodeDTO $node, WorkflowDTO $workflow ): WorkflowResultDTO {
		throw new \RuntimeException('LINE API token 過期');
	}
}
