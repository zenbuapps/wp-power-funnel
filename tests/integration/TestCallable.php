<?php
/**
 * 測試用靜態 Callable 輔助類別。
 *
 * 提供符合 NodeDTO validate() 要求的 2 元素 callable array。
 * 使用方式：[TestCallable::class, 'return_true'] 或 [TestCallable::class, 'return_false']
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration;

/**
 * 測試用靜態 Callable 輔助類別
 */
final class TestCallable {

	/**
	 * 永遠回傳 true（用於測試 match_callback）
	 *
	 * @param mixed ...$args 忽略的參數
	 * @return true
	 */
	public static function return_true( mixed ...$args ): bool {
		return true;
	}

	/**
	 * 永遠回傳 false（用於測試 match_callback）
	 *
	 * @param mixed ...$args 忽略的參數
	 * @return false
	 */
	public static function return_false( mixed ...$args ): bool {
		return false;
	}

	/**
	 * 測試用 context 暫存區
	 *
	 * @var array<string, string>
	 */
	public static array $test_context = [];

	/**
	 * 回傳測試用 context（Serializable Context Callable 目標方法）
	 *
	 * @return array<string, string>
	 */
	public static function return_test_context(): array {
		return self::$test_context;
	}
}
