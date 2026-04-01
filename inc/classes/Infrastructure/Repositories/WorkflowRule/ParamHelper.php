<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\Powerhouse\Shared\Helpers\ReplaceHelper;

/**
 * 幫助從上下文或者節點身上拿資料
 * 還有做模板訊息的字串取代
 */
final class ParamHelper {

	private const CONTEXT = 'context';

	/** Constructor */
	public function __construct(
		/** @var NodeDTO $node 節點 */
		private readonly NodeDTO $node,
		/** @var WorkflowDTO $workflow 工作流程 */
		private readonly WorkflowDTO $workflow,
	) {
	}

	/**
	 * 取得參數
	 * 如果用戶輸入 context 代表要從上下文拿資料
	 */
	public function try_get_param( string $key ): mixed {
		$maybe_value = $this->node->try_get_param( $key);
		if (self::CONTEXT === $maybe_value) {
			return $this->workflow->context[ $key ] ?? null;
		}

		return $maybe_value;
	}

	/**
	 * 用常用物件取代 + context {{variable}} 取代
	 *
	 * 處理順序：
	 * 1. 先以 workflow.context 中的值取代 {{variable}} 模板變數
	 * 2. 再以物件（user, product, post, order 等）做 ReplaceHelper 取代
	 *
	 * @param string $template 模板字串
	 * @return string 取代後的字串
	 */
	public function replace( string $template ): string {
		// Step 1：context {{variable}} 取代
		$template = $this->replace_context_variables($template);

		// Step 2：物件取代（透過 ReplaceHelper）
		$user         = $this->try_get_param( 'user');
		$product      = $this->try_get_param( 'product');
		$post         = $this->try_get_param( 'post');
		$subscription = $this->try_get_param( 'subscription');
		$activity     = $this->try_get_param( 'activity');

		// 嘗試從 context 中取得 WC_Order 物件（軟依賴 WooCommerce）
		$order = $this->try_get_param( 'order');
		if ($order === null) {
			$order_id = $this->workflow->context['order_id'] ?? null;
			if ($order_id && \function_exists('wc_get_order')) {
				$maybe_order = \wc_get_order( (int) $order_id);
				$order       = ( $maybe_order instanceof \WC_Order ) ? $maybe_order : null;
			}
		}

		// ReplaceHelper 的 constructor 和 replace() 對不支援的物件類型會拋出
		// "Unsupported object type"（EObjectType::get_type(null)），
		// 因此用 try-catch 包裹整段物件取代邏輯，確保 context 變數取代的結果不被影響。
		try {
			$helper = new ReplaceHelper($template);
			foreach ( [ $user, $product, $post, $order, $subscription, $activity ] as $object ) {
				if ($object !== null) {
					$helper = $helper->replace($object);
				}
			}
			return $helper->get_replaced_template();
		} catch (\Throwable $e) {
			// ReplaceHelper 不支援當前物件組合時，回傳 context 變數取代後的結果
			return $template;
		}
	}

	/**
	 * 取代模板中的 {{variable}} 為 workflow.context 對應值
	 *
	 * 僅取代 context 中存在的 key，不存在的保留原樣。
	 *
	 * @param string $template 模板字串
	 * @return string 取代後的字串
	 */
	private function replace_context_variables( string $template ): string {
		$context = $this->workflow->context;
		if (empty($context)) {
			return $template;
		}

		return (string) \preg_replace_callback(
			'/\{\{(\w+)\}\}/',
			static function ( array $matches ) use ( $context ): string {
				$key = $matches[1];
				if (\array_key_exists($key, $context)) {
					return (string) $context[ $key ];
				}
				// 不存在的 key 保留原樣
				return $matches[0];
			},
			$template
		);
	}
}
