<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Applications;

use J7\WpUtils\Classes\ApiBase;
use J7\WpUtils\Traits\SingletonTrait;

/**
 * Workflow 執行監控 API
 *
 * 提供工作流列表與詳情查詢端點。
 */
final class WorkflowApi extends ApiBase {
	use SingletonTrait;

	/** @var string $namespace API 命名空間 */
	protected $namespace = 'power-funnel';

	/**
	 * @var array<array{
	 * endpoint:string,
	 * method:string,
	 * permission_callback?: callable|null,
	 * callback?: callable|null,
	 * schema?: array<string, mixed>|null
	 * }> $apis APIs 端點定義
	 */
	protected $apis = [
		[
			'endpoint' => 'workflows',
			'method'   => 'get',
		],
		[
			'endpoint' => 'workflows/(?P<id>\d+)',
			'method'   => 'get',
		],
	];

	/** 允許的 Workflow 狀態清單 */
	private const VALID_STATUSES = [ 'running', 'completed', 'failed' ];

	/** 註冊 hooks */
	public static function register_hooks(): void {
		self::instance();
	}

	/**
	 * 取得工作流列表
	 *
	 * 支援以下查詢參數：
	 * - per_page (int, default 10)：每頁筆數，必須 > 0
	 * - page (int, default 1)：頁碼，必須 > 0
	 * - status (string)：狀態篩選（running / completed / failed）
	 * - workflow_rule_id (int)：按規則 ID 篩選
	 * - trigger_point (string)：按觸發點篩選
	 * - search (string)：在 results meta 中模糊搜尋
	 * - orderby (string, default created_at)：排序欄位
	 * - order (string, default DESC)：排序方向
	 *
	 * @param \WP_REST_Request $request REST 請求物件。
	 * @return \WP_REST_Response REST 回應物件。
	 * @phpstan-ignore-next-line
	 */
	public function get_workflows_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$params   = $request->get_query_params();
		$per_page = isset($params['per_page']) ? (int) $params['per_page'] : 10;
		$page     = isset($params['page']) ? (int) $params['page'] : 1;

		// 驗證分頁參數
		if ($per_page <= 0 || $page <= 0) {
			return new \WP_REST_Response(
				[
					'code'    => 'invalid_pagination',
					'message' => '分頁參數無效：per_page 與 page 必須大於 0',
					'data'    => null,
				],
				400
			);
		}

		// 驗證 status 參數
		$status = isset($params['status']) ? \sanitize_text_field($params['status']) : '';
		if ('' !== $status && !\in_array($status, self::VALID_STATUSES, true)) {
			return new \WP_REST_Response(
				[
					'code'    => 'invalid_status',
					'message' => '無效的 status 篩選值：僅允許 running / completed / failed',
					'data'    => null,
				],
				400
			);
		}

		$workflow_rule_id = isset($params['workflow_rule_id']) ? (int) $params['workflow_rule_id'] : 0;
		$trigger_point    = isset($params['trigger_point']) ? \sanitize_text_field($params['trigger_point']) : '';
		$search           = isset($params['search']) ? \sanitize_text_field($params['search']) : '';
		$orderby          = isset($params['orderby']) ? \sanitize_text_field($params['orderby']) : 'created_at';
		$order            = isset($params['order']) && 'asc' === strtolower($params['order']) ? 'ASC' : 'DESC';

		// 搜尋時先透過 $wpdb 找符合的 post IDs
		$search_post_ids = null;
		if ('' !== $search) {
			$search_post_ids = $this->search_workflow_by_results_keyword($search);
			// 若搜尋到空結果，直接回傳空清單
			if (empty($search_post_ids)) {
				return new \WP_REST_Response(
					[
						'code'    => 'operation_success',
						'message' => '操作成功',
						'data'    => [
							'items'      => [],
							'pagination' => [
								'total'       => 0,
								'totalPages'  => 0,
								'currentPage' => $page,
								'perPage'     => $per_page,
							],
						],
					],
					200
				);
			}
		}

		// 建立 WP_Query 參數
		$query_args = $this->build_query_args(
			$per_page,
			$page,
			$status,
			$workflow_rule_id,
			$trigger_point,
			$order,
			$search_post_ids
		);

		$query = new \WP_Query($query_args);
		$posts = $query->posts;
		$total = (int) $query->found_posts;

		// 格式化每筆結果
		$items = [];
		foreach ($posts as $post) {
			if (!( $post instanceof \WP_Post )) {
				continue;
			}
			$items[] = $this->format_workflow_list_item($post);
		}

		$total_pages = $per_page > 0 ? (int) ceil($total / $per_page) : 0;

		return new \WP_REST_Response(
			[
				'code'    => 'operation_success',
				'message' => '操作成功',
				'data'    => [
					'items'      => $items,
					'pagination' => [
						'total'       => $total,
						'totalPages'  => $total_pages,
						'currentPage' => $page,
						'perPage'     => $per_page,
					],
				],
			],
			200
		);
	}

	/**
	 * 建立 WP_Query 參數陣列
	 *
	 * @param int        $per_page         每頁筆數
	 * @param int        $page             頁碼
	 * @param string     $status           狀態篩選
	 * @param int        $workflow_rule_id 規則 ID 篩選
	 * @param string     $trigger_point    觸發點篩選
	 * @param string     $order            排序方向
	 * @param int[]|null $post_id_in       限制查詢範圍的 post IDs（來自 search）
	 * @return array<string, mixed>
	 */
	private function build_query_args(
		int $per_page,
		int $page,
		string $status,
		int $workflow_rule_id,
		string $trigger_point,
		string $order,
		?array $post_id_in
	): array {
		// 確保自訂狀態已註冊（避免 WP_Query 因無法識別狀態而過濾掉結果）
		\J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register::register_status();

		$query_args = [
			'post_type'      => 'pf_workflow',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => $order,
			'post_status'    => '' !== $status ? [ $status ] : array_values(self::VALID_STATUSES),
		];

		// 按 post IDs 篩選（搜尋結果）
		if (null !== $post_id_in) {
			$query_args['post__in'] = $post_id_in;
		}

		// 建立 meta_query
		$meta_query = [];

		if ($workflow_rule_id > 0) {
			$meta_query[] = [
				'key'   => 'workflow_rule_id',
				'value' => (string) $workflow_rule_id,
			];
		}

		if ('' !== $trigger_point) {
			$meta_query[] = [
				'key'   => 'trigger_point',
				'value' => $trigger_point,
			];
		}

		if (!empty($meta_query)) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		return $query_args;
	}

	/**
	 * 在 results meta 中模糊搜尋，回傳符合的 post IDs
	 *
	 * @param string $keyword 搜尋關鍵字
	 * @return int[] 符合的 workflow post IDs
	 */
	private function search_workflow_by_results_keyword( string $keyword ): array {
		global $wpdb;

		$like = '%' . $wpdb->esc_like($keyword) . '%';

		$post_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT DISTINCT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = 'pf_workflow'
				AND pm.meta_key = 'results'
				AND pm.meta_value LIKE %s",
				$like
			)
		);

		return array_map('intval', (array) $post_ids);
	}

	/**
	 * 格式化單筆 Workflow post 為列表項目
	 *
	 * @param \WP_Post $post Workflow post 物件
	 * @return array<string, mixed> 格式化後的列表項目
	 */
	private function format_workflow_list_item( \WP_Post $post ): array {
		$workflow_rule_id = (string) \get_post_meta($post->ID, 'workflow_rule_id', true);
		$trigger_point    = (string) \get_post_meta($post->ID, 'trigger_point', true);
		$nodes_raw        = \get_post_meta($post->ID, 'nodes', true);
		$results_raw      = \get_post_meta($post->ID, 'results', true);

		$nodes   = \is_array($nodes_raw) ? $nodes_raw : [];
		$results = \is_array($results_raw) ? $results_raw : [];

		$node_count    = count($nodes);
		$result_count  = count($results);
		$node_progress = "{$result_count}/{$node_count}";

		// 計算 duration（秒）：最後一筆 result 的 executed_at 減去第一筆
		$duration = $this->compute_duration($results);

		// 取得 WorkflowRule 標題
		$workflow_rule_title = '';
		if ('' !== $workflow_rule_id) {
			$rule_post = \get_post( (int) $workflow_rule_id);
			if ($rule_post instanceof \WP_Post) {
				$workflow_rule_title = $rule_post->post_title;
			}
		}

		$user_id = (int) $post->post_author;

		return [
			'workflowId'        => (string) $post->ID,
			'workflowRuleId'    => $workflow_rule_id,
			'workflowRuleTitle' => $workflow_rule_title,
			'triggerPoint'      => $trigger_point,
			'status'            => $post->post_status,
			'nodeProgress'      => $node_progress,
			'duration'          => $duration,
			'createdAt'         => $post->post_date_gmt,
			'userId'            => $user_id,
			'userDisplayName'   => $this->resolve_user_display_name($user_id),
		];
	}

	/**
	 * 計算執行時長字串（秒）
	 *
	 * 根據 results 陣列中第一筆和最後一筆的 executed_at 計算差值。
	 *
	 * @param array<int, array<string, mixed>> $results 執行結果陣列
	 * @return string 時長字串，例如 "5s"；若無法計算則為空字串
	 */
	private function compute_duration( array $results ): string {
		if (empty($results)) {
			return '';
		}

		$first_executed_at = $results[0]['executed_at'] ?? '';
		$last_executed_at  = end($results)['executed_at'] ?? '';

		if ('' === $first_executed_at || '' === $last_executed_at) {
			return '';
		}

		$first_time = \strtotime($first_executed_at);
		$last_time  = \strtotime($last_executed_at);

		if (false === $first_time || false === $last_time) {
			return '';
		}

		$diff_seconds = $last_time - $first_time;
		return "{$diff_seconds}s";
	}

	/**
	 * 解析觸發用戶的顯示名稱
	 *
	 * - user_id = 0：回傳「訪客」（系統自動觸發或未登入）
	 * - 用戶存在：回傳 display_name；若 display_name 為空字串則回傳「訪客」
	 * - 用戶不存在（已刪除）：回傳「訪客」
	 *
	 * @param int $user_id WordPress 用戶 ID
	 * @return string 顯示名稱
	 */
	private function resolve_user_display_name( int $user_id ): string {
		if (0 === $user_id) {
			return '訪客';
		}

		$display_name = \get_the_author_meta('display_name', $user_id);
		return '' !== $display_name ? $display_name : '訪客';
	}

	/**
	 * 取得單一工作流詳情
	 *
	 * 回傳工作流完整執行資訊，包含：
	 * - 基本資訊（workflowId, workflowRuleId, workflowRuleTitle, triggerPoint, status, createdAt）
	 * - nodes 陣列：每個 node 合併對應的執行 result（含 executedAt）
	 * - context：呼叫 context_callable_set 後的 resolved key-value pairs
	 * - contextCallableSet：人類可讀格式（短 class 名 + method，保留 params）
	 * - 耗時資訊：startedAt、completedAt、duration
	 *
	 * @param \WP_REST_Request $request REST 請求物件。
	 * @return \WP_REST_Response REST 回應物件。
	 * @phpstan-ignore-next-line
	 */
	public function get_workflows_with_id_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param('id');

		// 驗證 Workflow 是否存在且 post_type 正確
		$post = \get_post($id);
		if (!( $post instanceof \WP_Post ) || 'pf_workflow' !== $post->post_type) {
			return new \WP_REST_Response(
				[
					'code'    => 'workflow_not_found',
					'message' => 'Workflow 不存在',
					'data'    => null,
				],
				404
			);
		}

		// 取得 meta 資料
		$workflow_rule_id = (string) \get_post_meta($post->ID, 'workflow_rule_id', true);
		$trigger_point    = (string) \get_post_meta($post->ID, 'trigger_point', true);
		$nodes_raw        = \get_post_meta($post->ID, 'nodes', true);
		$results_raw      = \get_post_meta($post->ID, 'results', true);
		$context_callable = \get_post_meta($post->ID, 'context_callable_set', true);

		$nodes   = \is_array($nodes_raw) ? $nodes_raw : [];
		$results = \is_array($results_raw) ? $results_raw : [];

		// 取得 WorkflowRule 標題
		$workflow_rule_title = '';
		if ('' !== $workflow_rule_id) {
			$rule_post = \get_post( (int) $workflow_rule_id);
			if ($rule_post instanceof \WP_Post) {
				$workflow_rule_title = $rule_post->post_title;
			}
		}

		// 建立 result lookup：以 node_id 為 key
		$result_map = [];
		foreach ($results as $result) {
			$node_id = $result['node_id'] ?? '';
			if ('' !== $node_id) {
				$result_map[ $node_id ] = $result;
			}
		}

		// 合併 nodes 與 results
		$formatted_nodes = [];
		foreach ($nodes as $node) {
			$node_id    = $node['id'] ?? '';
			$result_raw = $result_map[ $node_id ] ?? null;

			$formatted_result = null;
			if (null !== $result_raw) {
				$formatted_result = [
					'code'       => isset($result_raw['code']) ? (int) $result_raw['code'] : null,
					'message'    => $result_raw['message'] ?? '',
					'data'       => $result_raw['data'] ?? null,
					'executedAt' => $result_raw['executed_at'] ?? '',
				];
			}

			$formatted_nodes[] = [
				'nodeId'           => $node_id,
				'nodeDefinitionId' => $node['node_definition_id'] ?? '',
				'params'           => $node['params'] ?? [],
				'result'           => $formatted_result,
			];
		}

		// Resolve context：呼叫 context_callable_set（失敗時回傳空陣列）
		$resolved_context = [];
		if (\is_array($context_callable) && isset($context_callable['callable'])) {
			try {
				$callable = $context_callable['callable'];
				$params   = $context_callable['params'] ?? [];
				$result   = \call_user_func_array($callable, (array) $params);
				if (\is_array($result)) {
					$resolved_context = $result;
				}
			} catch (\Throwable $e) {
				$resolved_context = [];
			}
		}

		// 格式化 contextCallableSet 為人類可讀格式
		$formatted_ccs = $this->format_context_callable_set($context_callable);

		// 計算 startedAt、completedAt、duration
		$started_at   = '';
		$completed_at = '';
		$duration     = '';

		if (!empty($results)) {
			$started_at   = $results[0]['executed_at'] ?? '';
			$last_result  = end($results);
			$completed_at = $last_result['executed_at'] ?? '';
			$duration     = $this->compute_duration($results);
		}

		return new \WP_REST_Response(
			[
				'code'    => 'operation_success',
				'message' => '操作成功',
				'data'    => [
					'workflowId'         => (string) $post->ID,
					'workflowRuleId'     => $workflow_rule_id,
					'workflowRuleTitle'  => $workflow_rule_title,
					'triggerPoint'       => $trigger_point,
					'status'             => $post->post_status,
					'nodes'              => $formatted_nodes,
					'context'            => $resolved_context,
					'contextCallableSet' => $formatted_ccs,
					'startedAt'          => $started_at,
					'completedAt'        => $completed_at,
					'duration'           => $duration,
					'createdAt'          => $post->post_date_gmt,
				],
			],
			200
		);
	}

	/**
	 * 將 context_callable_set 格式化為人類可讀格式
	 *
	 * 將 FQCN（如 J7\PowerFunnel\...\TriggerPointService）精簡為短 class 名（TriggerPointService），
	 * 並以 "ClassName::method_name" 格式呈現。params 保留原始值。
	 *
	 * @param mixed $context_callable 原始 context_callable_set meta 值
	 * @return array<string, mixed> 人類可讀格式
	 */
	private function format_context_callable_set( mixed $context_callable ): array {
		if (!\is_array($context_callable) || !isset($context_callable['callable'])) {
			return [];
		}

		$callable = $context_callable['callable'];
		$params   = $context_callable['params'] ?? [];

		$readable_callable = '';

		if (\is_array($callable) && count($callable) >= 2) {
			// [ClassName::class, 'method'] 陣列 callable 格式 // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			$fqcn   = (string) $callable[0];
			$method = (string) $callable[1];

			// 取得短 class 名（去除 namespace）
			$parts      = explode('\\', $fqcn);
			$short_name = end($parts);

			$readable_callable = "{$short_name}::{$method}";
		} elseif (\is_string($callable)) {
			// 純函數名稱格式
			$readable_callable = $callable;
		}

		return [
			'callable' => $readable_callable,
			'params'   => (array) $params,
		];
	}
}
