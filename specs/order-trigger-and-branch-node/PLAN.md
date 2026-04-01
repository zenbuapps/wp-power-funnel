# Implementation Plan: Order Completed Trigger Point & YesNoBranch Node

## Overview

Extend the Power Funnel workflow engine with two capabilities: (1) an ORDER_COMPLETED trigger point that fires when a WooCommerce order reaches completed status, and (2) full implementation of the YesNoBranchNode with non-linear workflow execution support. Together these enable workflows like "when an order completes, check if the total exceeds a threshold, then send different emails based on the result."

## Scope Mode: HOLD SCOPE

Well-defined feature expansion with 9 feature specs, 1 API spec, 1 entity model, and a clarify log already completed. Estimated impact: 8 files (7 modified, 1 created). No scope creep.

## Requirements

### Part A: ORDER_COMPLETED Trigger Point
- New `ETriggerPoint::ORDER_COMPLETED` enum case (hook: `pf/trigger/order_completed`, label: `訂單完成後`)
- TriggerPointService monitors `woocommerce_order_status_completed` hook (WooCommerce soft dependency)
- `resolve_order_context()` deferred evaluation returns 9 fields: order_id, order_total, billing_email, customer_id, line_items_summary, shipping_address, payment_method, order_date, billing_phone
- ParamHelper::replace() gains `$order` object replacement via `wc_get_order()` from context's `order_id`
- New REST API: `GET /power-funnel/trigger-points/{triggerPoint}/context-keys`
- Context key schemas defined as static data in TriggerPointService

### Part B: YesNoBranchNode
- Update form_fields: condition_field (select), operator (10 operators), condition_value, yes_next_node_id, no_next_node_id
- Implement `execute()`: evaluate condition, return WorkflowResultDTO with `next_node_id`
- WorkflowResultDTO gains `next_node_id` (string, default `''`)
- WorkflowDTO::get_current_index() supports non-linear jump via `next_node_id`
- Cycle prevention: fail workflow if a node_id appears twice in results
- Missing node detection: fail workflow if `next_node_id` target does not exist
- Full backward compatibility with linear execution

## Data Flow Analysis

### ORDER_COMPLETED Trigger Flow

```
WC_Order status -> completed
     |
     v
woocommerce_order_status_completed hook (order_id)
     |
     v
TriggerPointService::on_order_completed(order_id)
     |
     +--[wc_get_order() returns false?]--> log warning, return (nil path)
     |
     v
build_order_context_callable_set(order_id)
     |
     v
do_action('pf/trigger/order_completed', context_callable_set)
     |
     v
WorkflowRuleDTO::register() picks up, creates Workflow
     |
     v
resolve_order_context(order_id) -- deferred, called at node execution time
     |
     +--[wc_get_order() returns false?]--> return [] (safe default)
     |
     v
Returns 9 context keys from WC_Order object
```

### YesNoBranchNode Execution Flow

```
WorkflowDTO::try_execute()
     |
     v
get_current_index() -- checks last result's next_node_id
     |
     +--[last result has next_node_id?]--+
     |      |                             |
     |      v                             v
     |  find index of next_node_id    use results.count (linear)
     |      |                             |
     |      +--[node not found?]--> set_status(FAILED), return
     |      |
     |      +--[node_id in results?]--> cycle detected, set_status(FAILED), return
     |      |
     v      v
NodeDTO::try_execute()
     |
     v
YesNoBranchNode::execute(node, workflow)
     |
     v
Get context[condition_field]
     |
     +--[field missing?]--> result = false (no branch)
     |
     v
Compare with operator + condition_value
     |
     +--[true]--> next_node_id = yes_next_node_id
     |
     +--[false]--> next_node_id = no_next_node_id
     |
     v
return WorkflowResultDTO(code=200, next_node_id=...)
```

## Error Handling Registry

| Method/Path | Possible Failure | Error Type | Handling | User Visible? |
|---|---|---|---|---|
| `TriggerPointService::on_order_completed` | `wc_get_order()` returns false | Data missing | Log warning, skip trigger | Silent |
| `TriggerPointService::on_order_completed` | WooCommerce not active | Dependency missing | Hook not registered at all | Silent |
| `resolve_order_context()` | Order deleted between trigger and execution | Data missing | Return empty array `[]` | Silent |
| `resolve_order_context()` | order_id = 0 | Invalid param | Return empty array `[]` | Silent |
| `ParamHelper::replace()` | WooCommerce not active | Dependency missing | Skip order replacement, keep `{{order_*}}` | Template shown raw |
| `ParamHelper::replace()` | Order not found via `wc_get_order()` | Data missing | Skip order replacement, keep `{{order_*}}` | Template shown raw |
| `YesNoBranchNode::execute()` | Missing required params | Invalid config | Return code 500 | Workflow fails |
| `YesNoBranchNode::execute()` | condition_field not in context | Data missing | Treat as false -> no branch | Workflow continues |
| `WorkflowDTO::get_current_index()` | next_node_id target not found | Invalid config | `set_status(FAILED)` | Workflow fails |
| `WorkflowDTO::get_current_index()` | Cycle detected | Logic error | `set_status(FAILED)` | Workflow fails |
| `ContextKeysApi` | Invalid/unknown triggerPoint | Invalid param | Return `[]` | API returns empty |
| `ContextKeysApi` | Missing triggerPoint param | Missing param | Return 400 | API error response |

## Failure Mode Registry

| Code Path | Failure Mode | Handled? | Tested? | User Visible? | Recovery Path |
|---|---|---|---|---|---|
| WooCommerce deactivated after trigger registered | Hook silently not registered | Yes | Feature: register-order-completed-trigger | Silent | Re-activate WooCommerce |
| Order deleted between trigger and WaitNode resume | resolve returns `[]` | Yes | Feature: resolve-order-context | Workflow may fail at node | Manual re-run |
| Branch targets invalid node_id | Workflow fails | Yes | Feature: workflow-non-linear-execution | Status = failed | Fix WorkflowRule config |
| Branch creates cycle | Workflow fails | Yes | Feature: branch-cycle-prevention | Status = failed | Fix WorkflowRule config |
| Non-numeric string for numeric operators | Cast to 0.0 | Yes | Feature: yes-no-branch-execute | Correct comparison | N/A |

## Architecture Changes

### Modified Files

| # | File | Change |
|---|---|---|
| 1 | `inc/classes/Shared/Enums/ETriggerPoint.php` | Add `ORDER_COMPLETED` case + label |
| 2 | `inc/classes/Contracts/DTOs/WorkflowResultDTO.php` | Add `next_node_id` property |
| 3 | `inc/classes/Domains/Workflow/Services/TriggerPointService.php` | Add WooCommerce listener, resolve methods, context keys schema |
| 4 | `inc/classes/Infrastructure/Repositories/WorkflowRule/ParamHelper.php` | Add `$order` object replacement via `wc_get_order()` |
| 5 | `inc/classes/Infrastructure/Repositories/WorkflowRule/NodeDefinitions/YesNoBranchNode.php` | Rewrite form_fields, implement execute() |
| 6 | `inc/classes/Contracts/DTOs/WorkflowDTO.php` | Non-linear execution, cycle detection, completion logic |
| 7 | `inc/classes/Bootstrap.php` | Register ContextKeysApi |

### New Files

| # | File | Purpose |
|---|---|---|
| 8 | `inc/classes/Applications/ContextKeysApi.php` | REST API: `GET /trigger-points/{triggerPoint}/context-keys` |

## Implementation Steps

### Phase 1: Contracts & Shared Layer (Foundation)

> These are pure data changes with no behavioral impact. Must be in place before any higher-layer code can reference them.

1. **ETriggerPoint: Add ORDER_COMPLETED** (File: `inc/classes/Shared/Enums/ETriggerPoint.php`)
   - Action: Add new section `// ========== P4: WooCommerce 觸發點 ==========` with `case ORDER_COMPLETED = self::PREFIX . 'order_completed';`. Add entry in `label()` mapper: `self::ORDER_COMPLETED->value => '訂單完成後'`.
   - Reason: All trigger-point-related code depends on this enum case existing.
   - Dependency: None
   - Risk: Low
   - Execution Agent: `@wp-workflows:wordpress-master`

2. **WorkflowResultDTO: Add next_node_id** (File: `inc/classes/Contracts/DTOs/WorkflowResultDTO.php`)
   - Action: Add property `public string $next_node_id = '';`. Default empty string ensures backward compatibility -- existing serialized results without this field receive `''` via DTO constructor, preserving linear behavior.
   - Reason: YesNoBranchNode must communicate which node to execute next. Empty string means "continue linearly."
   - Dependency: None
   - Risk: Low
   - Execution Agent: `@wp-workflows:wordpress-master`

### Phase 2: Trigger Point Infrastructure (ORDER_COMPLETED)

> Build the trigger point end-to-end before tackling the branch node. Can be independently verified.

3. **TriggerPointService: WooCommerce order listener + resolve** (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
   - Action: In `register_hooks()`, add with soft dependency guard:
     ```php
     // P4: WooCommerce 訂單觸發點（軟依賴）
     if ( function_exists( 'wc_get_order' ) ) {
         \add_action('woocommerce_order_status_completed', [ __CLASS__, 'on_order_completed' ], 10, 1);
     }
     ```
   - Add three new methods following existing P0/P1/P2 patterns:
     - `on_order_completed( int $order_id ): void` -- validates order via `wc_get_order()`, builds context_callable_set, fires `do_action(ETriggerPoint::ORDER_COMPLETED->value, $context_callable_set)`. If `wc_get_order()` returns false, log warning and return.
     - `build_order_context_callable_set( int $order_id ): ?array` -- returns `['callable' => [self::class, 'resolve_order_context'], 'params' => [$order_id]]` or null if order invalid.
     - `resolve_order_context( int $order_id ): array` -- deferred evaluation target method. Calls `wc_get_order($order_id)`. If order gone, returns `[]`. Otherwise returns 9 keys:
       - `order_id` => `(string) $order->get_id()`
       - `order_total` => `(string) $order->get_total()`
       - `billing_email` => `$order->get_billing_email()`
       - `customer_id` => `(string) $order->get_customer_id()`
       - `line_items_summary` => implode comma-separated `"{product_name} x{qty}"` from `$order->get_items()`
       - `shipping_address` => `$order->get_formatted_shipping_address()` or `$order->get_formatted_billing_address()` if empty
       - `payment_method` => `$order->get_payment_method()`
       - `order_date` => `$order->get_date_created()?->format('Y-m-d') ?? ''`
       - `billing_phone` => `$order->get_billing_phone()`
   - Reason: Follows established patterns. Soft dependency ensures no crash when WooCommerce absent.
   - Dependency: Step 1 (ETriggerPoint enum)
   - Risk: Low
   - Execution Agent: `@wp-workflows:wordpress-master`

4. **TriggerPointService: Context keys schema** (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
   - Action: Add `public static function get_context_keys_for_trigger_point( string $trigger_point_hook ): array`. Returns `array<int, array{key: string, label: string}>`. Define static mapping for all existing trigger points plus `order_completed`:
     - `pf/trigger/order_completed` => 9 keys with labels (訂單 ID, 訂單金額, 帳單 Email, 客戶 ID, 商品清單摘要, 配送地址, 付款方式, 訂單日期, 帳單電話)
     - `pf/trigger/registration_approved` (and other registration triggers) => 5 keys (registration_id, identity_id, identity_provider, activity_id, promo_link_id)
     - `pf/trigger/line_followed` (and other line triggers) => 2-3 keys (line_user_id, event_type, message_text for message_received)
     - `pf/trigger/workflow_completed` / `workflow_failed` => 3 keys (workflow_id, workflow_rule_id, trigger_point)
     - `pf/trigger/user_tagged` => 2 keys (user_id, tag_name)
     - Unknown trigger points => return `[]`
   - Reason: REST API endpoint and frontend condition_field dropdown need this data.
   - Dependency: Step 3
   - Risk: Low
   - Execution Agent: `@wp-workflows:wordpress-master`

5. **ParamHelper: $order object replacement via wc_get_order()** (File: `inc/classes/Infrastructure/Repositories/WorkflowRule/ParamHelper.php`)
   - Action: Modify `replace()` method. Currently line 47 has `$order = $this->try_get_param('order')` which returns null because `order` is not a node param. Replace this with logic that checks context for `order_id` and WooCommerce availability:
     ```php
     $order = null;
     $order_id = $this->workflow->context['order_id'] ?? null;
     if ($order_id && function_exists('wc_get_order')) {
         $maybe_order = wc_get_order((int) $order_id);
         $order = $maybe_order instanceof \WC_Order ? $maybe_order : null;
     }
     ```
   - The rest of the chain `$helper->replace($order)` stays the same -- ReplaceHelper handles null gracefully (no replacement).
   - Reason: Context's `order_id` is the authoritative source for the order, not a node param. WooCommerce soft dependency guard prevents crashes.
   - Dependency: Step 3 (resolve_order_context provides order_id in context)
   - Risk: Medium -- must verify ReplaceHelper handles WC_Order objects. If ReplaceHelper cannot process WC_Order, need to create an adapter. However, based on the existing pattern where $user, $product etc. are passed, ReplaceHelper likely uses duck typing on WordPress/WooCommerce objects.
   - Execution Agent: `@wp-workflows:wordpress-master`

6. **ContextKeysApi: New REST endpoint** (File: `inc/classes/Applications/ContextKeysApi.php` -- NEW)
   - Action: Create new API class following `TriggerPointApi` / `NodeDefinitionApi` patterns:
     - Namespace: `J7\PowerFunnel\Applications`
     - Extends `ApiBase`, uses `SingletonTrait`
     - `$namespace = 'power-funnel'`
     - Register endpoint `trigger-points/(?P<triggerPoint>[a-z0-9_/]+)/context-keys` with GET method
     - Callback `get_trigger_points_context_keys_callback()`: extract triggerPoint from route param, call `TriggerPointService::get_context_keys_for_trigger_point($trigger_point)`, wrap in standard response format `['code' => 'operation_success', 'message' => '操作成功', 'data' => $keys]`
     - Handle missing param: return `WP_REST_Response` with code `operation_failed` and 400 status
   - Reason: Frontend YesNoBranchNode editor needs dynamic context key options for condition_field dropdown.
   - Dependency: Step 4
   - Risk: Low
   - Execution Agent: `@wp-workflows:wordpress-master`

7. **Bootstrap: Register ContextKeysApi** (File: `inc/classes/Bootstrap.php`)
   - Action: Add `Applications\ContextKeysApi::register_hooks();` in `Bootstrap::register_hooks()`, alongside existing API registrations (after `Applications\NodeDefinitionApi::register_hooks();`).
   - Dependency: Step 6
   - Risk: Low
   - Execution Agent: `@wp-workflows:wordpress-master`

### Phase 3: YesNoBranchNode Implementation

> Implement the node definition after infrastructure is in place.

8. **YesNoBranchNode: Update form_fields** (File: `inc/classes/Infrastructure/Repositories/WorkflowRule/NodeDefinitions/YesNoBranchNode.php`)
   - Action: Rewrite constructor to define 5 form fields:
     - `condition_field`: type=`select`, required=true, label=`條件欄位`, description=`選擇要比較的欄位（選項從觸發點 Context Keys API 動態載入）`, sort=0
     - `operator`: type=`select`, required=true, label=`運算子`, sort=1, options with 10 operators:
       - `gt`/大於, `gte`/大於等於, `lt`/小於, `lte`/小於等於, `equals`/等於, `not_equals`/不等於, `contains`/包含, `not_contains`/不包含, `is_empty`/為空, `is_not_empty`/不為空
     - `condition_value`: type=`text`, required=true, label=`條件值`, placeholder=`比較值`, sort=2
     - `yes_next_node_id`: type=`text`, required=true, label=`是分支目標節點`, sort=3
     - `no_next_node_id`: type=`text`, required=true, label=`否分支目標節點`, sort=4
   - Reason: Existing form_fields only had 3 fields with incomplete operator list. Need full 5 fields with all 10 operators per spec.
   - Dependency: None (form_fields is static config)
   - Risk: Low
   - Execution Agent: `@wp-workflows:wordpress-master`

9. **YesNoBranchNode: Implement execute()** (File: `inc/classes/Infrastructure/Repositories/WorkflowRule/NodeDefinitions/YesNoBranchNode.php`)
   - Action: Replace `throw BadMethodCallException` stub with full logic:
     1. Extract params: `condition_field`, `operator`, `condition_value`, `yes_next_node_id`, `no_next_node_id`
     2. Validate required params (condition_field, operator, yes_next_node_id, no_next_node_id). If any missing, return `new WorkflowResultDTO(['node_id' => $node->id, 'code' => 500, 'message' => '必要參數未提供'])`
     3. Get actual_value: `$workflow->context[$condition_field] ?? ''`
     4. Evaluate condition via private method `evaluate_condition(string $actual_value, string $operator, string $condition_value): bool`:
        - `gt`: `(float) $actual_value > (float) $condition_value`
        - `gte`: `(float) $actual_value >= (float) $condition_value`
        - `lt`: `(float) $actual_value < (float) $condition_value`
        - `lte`: `(float) $actual_value <= (float) $condition_value`
        - `equals`: `$actual_value === $condition_value`
        - `not_equals`: `$actual_value !== $condition_value`
        - `contains`: `str_contains($actual_value, $condition_value)`
        - `not_contains`: `!str_contains($actual_value, $condition_value)`
        - `is_empty`: `$actual_value === ''`
        - `is_not_empty`: `$actual_value !== ''`
        - default: `false`
     5. Determine next_node_id: `$result ? $yes_next_node_id : $no_next_node_id`
     6. Return `new WorkflowResultDTO(['node_id' => $node->id, 'code' => 200, 'next_node_id' => $next_node_id, 'message' => $result ? '條件成立' : '條件不成立'])`
   - Note: Does NOT call `$workflow->do_next()`. The caller (`NodeDTO::try_execute()`) handles adding the result, and `do_next()` is triggered by the normal flow.
   - Reason: Core branch logic per spec. All operators and edge cases (missing field, numeric cast) handled.
   - Dependency: Steps 2 (next_node_id), 8 (form_fields)
   - Risk: Medium -- numeric comparison edge cases (non-numeric strings cast to 0.0).
   - Execution Agent: `@wp-workflows:wordpress-master`

### Phase 4: Workflow Engine Non-Linear Execution

> Most critical change. Modifies the core execution loop. Must be done after WorkflowResultDTO has next_node_id.

10. **WorkflowDTO: Non-linear get_current_index() + cycle detection** (File: `inc/classes/Contracts/DTOs/WorkflowDTO.php`)
    - Action: Rewrite `get_current_index()`:
      ```
      1. If results is empty, return 0 (first node)
      2. Get last result: $last_result = end($this->results)
      3. If $last_result->next_node_id !== '' (non-empty string):
         a. CYCLE CHECK: scan all results for any with node_id === $last_result->next_node_id
            - If found: log warning, set_status(FAILED), record error "偵測到節點迴圈：節點 {node_id} 已執行過", return null
         b. TARGET CHECK: try $this->get_index($last_result->next_node_id)
            - If throws (not found): set_status(FAILED), record error "找不到目標節點 {next_node_id}", return null
         c. Return the found index
      4. If $last_result->next_node_id === '' (linear mode):
         a. Check if workflow previously used branching (any result has next_node_id !== '')
            - If yes AND this result has next_node_id === '': this is end of branch path, return null (completed)
         b. Standard linear: if results_count >= nodes_count return null, else return results_count
      ```
    - Also update `try_execute()`:
      ```php
      $current_index = $this->get_current_index();
      if ($current_index === null) {
          // Only mark completed if not already failed (get_current_index may have set FAILED)
          if ($this->status === EWorkflowStatus::RUNNING) {
              $this->set_status(EWorkflowStatus::COMPLETED);
          }
          return;
      }
      ```
    - Reason: This is the architectural heart of non-linear execution. The backward-compatible path (empty next_node_id, no previous branching) preserves existing behavior exactly.
    - Dependency: Step 2 (next_node_id property)
    - Risk: **HIGH** -- modifies core execution loop.
    - Mitigation: Change is additive. Only when `next_node_id !== ''` does new behavior activate. All existing NodeDefinition.execute() returns produce `next_node_id = ''` by default. Extensive integration tests cover both linear and branched workflows.
    - Execution Agent: `@wp-workflows:wordpress-master`

## Test Strategy

> For tdd-coordinator to hand to test-creator. Tests should be written BEFORE implementation (TDD).

### Integration Tests (PHPUnit)

| # | Test Class | Feature File | Key Test Cases |
|---|---|---|---|
| 1 | `RegisterOrderCompletedTriggerTest` | `register-order-completed-trigger.feature` | Enum value/label, hook registered with WC active, hook NOT registered with WC inactive |
| 2 | `FireOrderCompletedTest` | `fire-order-completed.feature` | Fires with valid order, skips nonexistent order, context_callable_set format |
| 3 | `ResolveOrderContextTest` | `resolve-order-context.feature` | Returns 9 keys for valid order, returns [] for deleted order/id=0, deferred eval gets latest data |
| 4 | `OrderParamHelperTest` | `order-param-helper.feature` | Template replacement works, WC inactive keeps raw, order deleted keeps raw, unknown vars kept |
| 5 | `QueryTriggerPointContextKeysTest` | `query-trigger-point-context-keys.feature` | Correct keys for order_completed, correct keys for registration_approved, [] for unknown, 400 for missing |
| 6 | `YesNoBranchFormFieldsTest` | `yes-no-branch-form-fields.feature` | 5 fields correct, 10 operators, condition_field is select |
| 7 | `YesNoBranchExecuteTest` | `yes-no-branch-execute.feature` | True->yes branch, false->no branch, all 10 operators, missing field->no, numeric cast, missing params->500 |
| 8 | `WorkflowNonLinearExecutionTest` | `workflow-non-linear-execution.feature` | Jump to next_node_id, linear without next_node_id, nonexistent target->failed, branch completion |
| 9 | `BranchCyclePreventionTest` | `branch-cycle-prevention.feature` | Cycle detected->failed, non-cyclic proceeds, cycle logs warning |

### Test Execution

```bash
composer test        # Full integration test suite
composer test:smoke  # Smoke tests only
```

### Key Edge Cases

- WooCommerce deactivated mid-workflow (resolve returns [], ParamHelper skips)
- Order deleted between trigger and node execution
- Non-numeric strings for numeric operators (cast to 0.0)
- Empty string condition_value for is_empty/is_not_empty
- Three-node workflow: branch -> email -> completed (mixed linear/non-linear)
- WorkflowResultDTO backward compatibility: old serialized results without next_node_id
- Boundary value: gte with equal values (2500 >= 2500)

## Risk & Mitigation Summary

| Risk | Level | Mitigation |
|---|---|---|
| Core execution loop change breaks existing workflows | **High** | `next_node_id` defaults to `''`; linear path code unchanged; extensive integration tests |
| Completion logic for branched workflows | **High** | Spec examples define exact behavior; branch-mode detection based on result history |
| WooCommerce dependency at runtime | **Medium** | `function_exists('wc_get_order')` guard everywhere; tested active/inactive |
| ReplaceHelper handling WC_Order objects | **Medium** | Verify during implementation; fallback: skip replacement if incompatible |
| Serialized results backward compatibility | **Low** | Default `''` on new property; DTO constructor handles missing keys |

## Dependencies

- **WooCommerce** (soft dependency): `wc_get_order()`, `WC_Order` API for HPOS compatibility
- **Powerhouse** (existing dependency): `ReplaceHelper`, `FormFieldDTO`, `ApiBase`, `DTO`
- **Action Scheduler** (existing, not modified): WaitNode scheduling unaffected

## Error Handling Strategy

Per the spec's clarify decisions:
- **Quick fail for invalid config**: Missing required params -> code 500 immediately
- **Safe defaults for missing data**: Deleted order -> empty context, missing context field -> false branch
- **Silent skip for soft dependencies**: WooCommerce not active -> no hook registered, no errors

## Constraints (What this plan does NOT do)

- Does NOT implement ReactFlow frontend editor for YesNoBranchNode
- Does NOT implement E2E tests (integration tests only)
- Does NOT modify existing WaitNode, EmailNode, or other NodeDefinitions
- Does NOT add new database tables (uses existing wp_postmeta serialization)
- Does NOT implement ACTIVITY_ENDED or PROMO_LINK_CLICKED trigger points
- Does NOT change the RecursionGuard mechanism
- Does NOT implement SplitBranchNode (only YesNoBranchNode)

## Estimated Complexity: Medium-High

Core complexity in Steps 10 (non-linear execution + cycle detection + completion logic). The rest follow established patterns.

## File Summary (Absolute Paths)

| # | File | Action | Phase |
|---|---|---|---|
| 1 | `C:\Users\User\LocalSites\turbo\app\public\wp-content\plugins\power-funnel\inc\classes\Shared\Enums\ETriggerPoint.php` | Modify | 1 |
| 2 | `C:\Users\User\LocalSites\turbo\app\public\wp-content\plugins\power-funnel\inc\classes\Contracts\DTOs\WorkflowResultDTO.php` | Modify | 1 |
| 3 | `C:\Users\User\LocalSites\turbo\app\public\wp-content\plugins\power-funnel\inc\classes\Domains\Workflow\Services\TriggerPointService.php` | Modify | 2 |
| 4 | `C:\Users\User\LocalSites\turbo\app\public\wp-content\plugins\power-funnel\inc\classes\Infrastructure\Repositories\WorkflowRule\ParamHelper.php` | Modify | 2 |
| 5 | `C:\Users\User\LocalSites\turbo\app\public\wp-content\plugins\power-funnel\inc\classes\Applications\ContextKeysApi.php` | **Create** | 2 |
| 6 | `C:\Users\User\LocalSites\turbo\app\public\wp-content\plugins\power-funnel\inc\classes\Bootstrap.php` | Modify | 2 |
| 7 | `C:\Users\User\LocalSites\turbo\app\public\wp-content\plugins\power-funnel\inc\classes\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\YesNoBranchNode.php` | Modify | 3 |
| 8 | `C:\Users\User\LocalSites\turbo\app\public\wp-content\plugins\power-funnel\inc\classes\Contracts\DTOs\WorkflowDTO.php` | Modify | 4 |
