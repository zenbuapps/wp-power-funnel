<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\DTOs;

use J7\PowerFunnel\Domains\Workflow\Services\RecursionGuard;
use J7\PowerFunnel\Infrastructure\Repositories\Workflow\Repository;
use J7\WpUtils\Classes\DTO;

/**
 * 多個 Node 會組合成一個 workflow_rule
 */
final class WorkflowRuleDTO extends DTO {

	/** @var string workflow_rule ID */
	public string $id;

	/** @var string workflow_rule 名稱 */
	public string $name;

	/**
	 * @var string 這個 workflow_rule 應該掛在哪裡的 hook name
	 * 向後相容：支援舊版純字串格式與新版 {hook, params} 物件格式
	 */
	public string $trigger_point;

	/** @var array<string, mixed> 觸發點參數，例如 before_minutes */
	public array $trigger_params = [];

	/** @var array<NodeDTO> $nodes 這個 workflow_rule 的節點 */
	public array $nodes;

	/** 取得實例 */
	public static function of( string $post_id ): self {
		$int_post_id = (int) $post_id;
		$nodes_array = \get_post_meta($int_post_id, 'nodes', true);
		$nodes_array = \is_array($nodes_array) ? $nodes_array : [];

		$raw_trigger_point = \get_post_meta($int_post_id, 'trigger_point', true);

		// 向後相容：支援舊版純字串格式與新版 {hook, params} 物件格式
		[ $trigger_hook, $trigger_params ] = self::parse_trigger_point_meta($raw_trigger_point);

		$args = [
			'id'             => $post_id,
			'name'           => \get_the_title($int_post_id),
			'trigger_point'  => $trigger_hook,
			'trigger_params' => $trigger_params,
			'nodes'          => NodeDTO::parse_array( $nodes_array ),
		];
		return new self($args);
	}

	/**
	 * 解析 trigger_point meta 值，支援舊版字串格式與新版物件格式
	 *
	 * @param mixed $raw meta 原始值
	 * @return array{0: string, 1: array<string, mixed>} [hook, params]
	 */
	private static function parse_trigger_point_meta( mixed $raw ): array {
		// 新版：已是陣列格式 {hook: string, params: {...}}
		if (\is_array($raw)) {
			$hook   = isset($raw['hook']) && \is_string($raw['hook']) ? $raw['hook'] : '';
			$params = isset($raw['params']) && \is_array($raw['params']) ? $raw['params'] : [];
			return [ $hook, $params ];
		}

		// 嘗試解析 JSON 字串（新版物件格式儲存為 JSON）
		if (\is_string($raw) && \str_starts_with(\ltrim($raw), '{')) {
			try {
				/** @var array<string, mixed>|false $decoded */
				$decoded = \json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
				if (\is_array($decoded)) {
					$hook   = isset($decoded['hook']) && \is_string($decoded['hook']) ? $decoded['hook'] : '';
					$params = isset($decoded['params']) && \is_array($decoded['params']) ? $decoded['params'] : [];
					return [ $hook, $params ];
				}
			} catch (\JsonException $e) {
				// JSON 解析失敗，降級為舊版字串格式
			}
		}

		// 舊版：純字串格式
		$hook = \is_string($raw) ? $raw : '';
		return [ $hook, [] ];
	}

	/**
	 * 取得觸發點 hook 名稱
	 *
	 * @return string hook 名稱
	 */
	public function get_trigger_hook(): string {
		return $this->trigger_point;
	}

	/**
	 * 取得觸發點參數
	 *
	 * @return array<string, mixed> 參數陣列
	 */
	public function get_trigger_params(): array {
		return $this->trigger_params;
	}

	/** 註冊 workflow_rule */
	public function register(): void {
		\add_action(
			$this->get_trigger_hook(),
			/**
			 * @param array<string, mixed> $context_callable_set callable set
			 */
			function ( array $context_callable_set = [] ): void {
				RecursionGuard::enter();

				if (RecursionGuard::is_exceeded()) {
					// 遞迴深度超過上限，建立失敗的 Workflow 並記錄錯誤
					$workflow_rule_dto = WorkflowRuleDTO::of($this->id);
					try {
						Repository::create_failed_from_recursion_exceeded($workflow_rule_dto, $context_callable_set);
					} catch (\Throwable $e) {
						\J7\PowerFunnel\Plugin::logger(
							"遞迴Guard：建立失敗 Workflow 時發生錯誤: {$e->getMessage()}",
							'error',
							[ 'exception' => $e ]
						);
					}
					RecursionGuard::leave();
					return;
				}

				// trigger_params 匹配檢查：若設定 postback_action 過濾，則提前比對
				$workflow_rule_dto = WorkflowRuleDTO::of($this->id);
				$trigger_params    = $workflow_rule_dto->get_trigger_params();

				if (!empty($trigger_params['postback_action']) && \is_string($trigger_params['postback_action'])) {
					// 提前 resolve context 以取得 postback_action 比對
					if (!empty($context_callable_set['callable']) && !empty($context_callable_set['params'])) {
						try {
							/** @var array<string, string> $context */
							$context = \call_user_func_array($context_callable_set['callable'], $context_callable_set['params']);
							if (
								!\is_array($context) ||
								!isset($context['postback_action']) ||
								$context['postback_action'] !== $trigger_params['postback_action']
							) {
								RecursionGuard::leave();
								return;
							}
						} catch (\Throwable $e) {
							\J7\PowerFunnel\Plugin::logger(
								"WorkflowRuleDTO::register trigger_params 匹配錯誤: {$e->getMessage()}",
								'warning',
								[ 'exception' => $e ]
							);
							RecursionGuard::leave();
							return;
						}
					}
				}

				try {
					Repository::create_from($workflow_rule_dto, $context_callable_set);
				} finally {
					RecursionGuard::leave();
				}
			},
		);
	}

	/** @return ?NodeDTO 取得 Node */
	public function get_node( int $index ): ?NodeDTO {
		return $this->nodes[ $index ] ?? null;
	}
}
