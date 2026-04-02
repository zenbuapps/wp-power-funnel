# Implementation Plan: WooCommerce Trigger Points Expansion

## Overview

Expand the Power Funnel workflow engine's trigger point system with 14 new `ETriggerPoint` enum cases across three categories: 6 WooCommerce order status triggers, 1 customer behavior trigger, and 7 subscription lifecycle triggers. This is a pure PHP backend task modifying exactly 2 files with no frontend changes required.

## Requirements Restatement

The workflow engine currently supports 1 WooCommerce trigger (ORDER_COMPLETED). This plan adds:

1. **6 order status triggers** (P4, group: `woocommerce`) -- pending, processing, on-hold, cancelled, refunded, failed -- all reusing the existing `build_order_context_callable_set()` + `resolve_order_context()` pattern, with `order_status` added as the 10th context key.

2. **1 customer trigger** (P5, group: `customer`) -- CUSTOMER_REGISTERED -- listening on WordPress `user_register` hook with a new `resolve_customer_context()` method returning 5 keys.

3. **7 subscription triggers** (P5, group: `subscription`) -- fully implemented (not stubs), listening on Powerhouse `powerhouse_subscription_at_{action}` hooks with a new `resolve_subscription_context()` method returning 8 keys.

4. **Context Keys API expansion** -- extending `get_context_keys_map()` with mappings for all 14 new triggers plus adding `order_status` to existing ORDER_COMPLETED mapping. No new API endpoints needed.

## Known Risks (from research)

- **Risk: `woocommerce_order_status_on-hold` hook name uses hyphen** -- WooCommerce uses `on-hold` (with hyphen) for the status slug, so the hook is `woocommerce_order_status_on-hold`, not `woocommerce_order_status_on_hold`. Mitigation: Verified in WooCommerce source; the feature spec already accounts for this.

- **Risk: Powerhouse soft dependency detection** -- Power-funnel uses `J7\Powerhouse\*` classes as hard imports elsewhere, but the subscription hooks depend on WooCommerce Subscriptions being active (which Powerhouse wraps). Mitigation: Use `function_exists('wcs_get_subscription')` as the soft dependency guard (same pattern as `function_exists('wc_get_order')` for WC).

- **Risk: `user_register` hook fires for ALL WordPress user registrations, not just WooCommerce customers** -- Admin-created users, plugin-created users, etc. will also trigger CUSTOMER_REGISTERED. Mitigation: This is intentional per the spec; the trigger is named "customer" but uses the generic WP hook. The context will still populate billing fields from user_meta (which may be empty for non-WC users).

- **Risk: PHPStan Level 9 strictness** -- All new code must pass PHPStan Level 9. The `WC_Subscription` class may not be recognized without stubs. Mitigation: Use `@phpstan-ignore-next-line` only if needed, or use `mixed` type with runtime instanceof checks.

## Architecture Changes

- **`inc/classes/Shared/Enums/ETriggerPoint.php`** -- Add 14 enum cases, update `label()`, `group()`, `group_label()`, `is_stub()` methods
- **`inc/classes/Domains/Workflow/Services/TriggerPointService.php`** -- Add hook registrations, handler methods, context resolve methods, context keys map entries

No new files. No database changes. No frontend changes.

## Implementation Steps

### Phase 1: ETriggerPoint Enum Expansion

> Estimated complexity: Low

1. **Add 14 enum cases** (File: `inc/classes/Shared/Enums/ETriggerPoint.php`)
   - Action: Add 6 order cases under P4 section, 1 customer case under new P5 section, 7 subscription cases under new P5 section
   - Reason: All trigger points must be defined as enum cases before they can be referenced
   - Dependency: None
   - Risk: Low

2. **Update `label()` method** (File: `inc/classes/Shared/Enums/ETriggerPoint.php`)
   - Action: Add 14 entries to the `$mapper` array with Chinese labels
   - Reason: Labels are used in the frontend trigger point selector
   - Dependency: Step 1
   - Risk: Low

3. **Update `group()` method** (File: `inc/classes/Shared/Enums/ETriggerPoint.php`)
   - Action: Add 14 entries to the `$mapper` array. 6 order cases map to `woocommerce`, 1 customer case maps to `customer`, 7 subscription cases map to `subscription`
   - Reason: Groups are used for frontend OptGroup display
   - Dependency: Step 1
   - Risk: Low

4. **Update `group_label()` method** (File: `inc/classes/Shared/Enums/ETriggerPoint.php`)
   - Action: Add 2 new entries to `$label_map`: `'customer' => '顧客行為'`, `'subscription' => '訂閱'`
   - Reason: New groups need Chinese labels
   - Dependency: Step 3
   - Risk: Low

5. **Verify `is_stub()` method** (File: `inc/classes/Shared/Enums/ETriggerPoint.php`)
   - Action: No changes needed -- all 14 new cases use `default => false` path. Verify no unintended matches.
   - Reason: All new triggers are fully implemented, not stubs
   - Dependency: Step 1
   - Risk: Low

### Phase 2: Order Status Triggers (6 handlers)

> Estimated complexity: Low

6. **Register 6 order status hook listeners** (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
   - Action: Inside the existing `if ( \function_exists( 'wc_get_order' ) )` block, add 6 `\add_action()` calls for `woocommerce_order_status_pending`, `woocommerce_order_status_processing`, `woocommerce_order_status_on-hold`, `woocommerce_order_status_cancelled`, `woocommerce_order_status_refunded`, `woocommerce_order_status_failed`
   - Reason: Reuse existing WC soft dependency guard
   - Dependency: Step 1
   - Risk: Low -- follows exact same pattern as existing `on_order_completed`

7. **Add 6 order status handler methods** (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
   - Action: Add `on_order_pending()`, `on_order_processing()`, `on_order_on_hold()`, `on_order_cancelled()`, `on_order_refunded()`, `on_order_failed()` -- each following the exact same pattern as `on_order_completed()`, calling `build_order_context_callable_set()` then `do_action()` with the corresponding ETriggerPoint value
   - Reason: Each WC status hook needs a dedicated handler that fires the correct pf/trigger/* hook
   - Dependency: Step 6
   - Risk: Low -- copy-paste pattern from existing `on_order_completed()`

8. **Add `order_status` to `resolve_order_context()`** (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
   - Action: Add `'order_status' => $order->get_status()` to the return array (line ~513, after `billing_phone`)
   - Reason: All order triggers (including existing ORDER_COMPLETED) should include the order's current status in context
   - Dependency: None
   - Risk: Low -- purely additive, backward compatible (existing consumers get an extra key)

### Phase 3: Customer Registered Trigger

> Estimated complexity: Low

9. **Register `user_register` hook listener** (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
   - Action: Add `\add_action('user_register', [ __CLASS__, 'on_customer_registered' ], 10, 1);` in `register_hooks()` -- placed outside the WC soft dependency block since `user_register` is a core WordPress hook
   - Reason: CUSTOMER_REGISTERED should fire regardless of WooCommerce status
   - Dependency: Step 1
   - Risk: Low

10. **Add `on_customer_registered()` handler** (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
    - Action: New handler accepting `int $user_id`, calling `build_customer_context_callable_set($user_id)`, then `do_action(ETriggerPoint::CUSTOMER_REGISTERED->value, $context_callable_set)`
    - Reason: Bridge WordPress user registration to pf/trigger/customer_registered
    - Dependency: Step 9
    - Risk: Low

11. **Add `build_customer_context_callable_set()` method** (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
    - Action: New private static method accepting `int $user_id`, checking `get_userdata($user_id)` returns non-false, returning `['callable' => [self::class, 'resolve_customer_context'], 'params' => [$user_id]]`
    - Reason: Follows Serializable Context Callable pattern
    - Dependency: None
    - Risk: Low

12. **Add `resolve_customer_context()` method** (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
    - Action: New public static method accepting `int $user_id`, returning 5 keys: `customer_id`, `billing_email`, `billing_first_name`, `billing_last_name`, `billing_phone`. Uses `get_user_meta()` for billing fields. Returns empty array if user_id <= 0 or user doesn't exist.
    - Reason: Deferred evaluation pattern -- resolves at execution time, not trigger time
    - Dependency: None
    - Risk: Low

### Phase 4: Subscription Lifecycle Triggers (7 handlers)

> Estimated complexity: Medium

13. **Register 7 subscription hook listeners** (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
    - Action: Add new soft dependency block: `if ( \function_exists( 'wcs_get_subscription' ) )` containing 7 `\add_action()` calls for `powerhouse_subscription_at_initial_payment_complete`, `powerhouse_subscription_at_subscription_failed`, `powerhouse_subscription_at_subscription_success`, `powerhouse_subscription_at_renewal_order_created`, `powerhouse_subscription_at_end`, `powerhouse_subscription_at_trial_end`, `powerhouse_subscription_at_end_of_prepaid_term`
    - Reason: Subscription triggers depend on both Powerhouse and WooCommerce Subscriptions; `wcs_get_subscription` checks both
    - Dependency: Step 1
    - Risk: Medium -- must correctly handle the Powerhouse hook signature `($subscription, $params)`

14. **Add 7 subscription handler methods** (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
    - Action: Add `on_subscription_initial_payment()`, `on_subscription_failed()`, `on_subscription_success()`, `on_subscription_renewal_order()`, `on_subscription_end()`, `on_subscription_trial_end()`, `on_subscription_prepaid_end()`. Each accepts `mixed $subscription` and `array $params`, validates `$subscription instanceof \WC_Subscription`, calls `build_subscription_context_callable_set($subscription->get_id())`, then `do_action()` with corresponding ETriggerPoint value.
    - Reason: Powerhouse hooks pass WC_Subscription object; we extract subscription_id for serializable context
    - Dependency: Step 13
    - Risk: Medium -- WC_Subscription type must be handled carefully for PHPStan

15. **Add `build_subscription_context_callable_set()` method** (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
    - Action: New private static method accepting `int $subscription_id`, checking `wcs_get_subscription($subscription_id)` returns non-false, returning `['callable' => [self::class, 'resolve_subscription_context'], 'params' => [$subscription_id]]`
    - Reason: Follows Serializable Context Callable pattern -- stores only subscription_id, not the WC_Subscription object
    - Dependency: None
    - Risk: Low

16. **Add `resolve_subscription_context()` method** (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
    - Action: New public static method accepting `int $subscription_id`, returning 8 keys: `subscription_id`, `subscription_status`, `customer_id`, `billing_email`, `billing_first_name`, `billing_last_name`, `order_total`, `payment_method`. Uses `wcs_get_subscription()` for deferred evaluation. Returns empty array if subscription_id <= 0, `wcs_get_subscription` not available, or subscription doesn't exist.
    - Reason: Deferred evaluation -- WaitNode may delay execution by hours/days, needs fresh data
    - Dependency: None
    - Risk: Low

### Phase 5: Context Keys Map Expansion

> Estimated complexity: Low

17. **Add `order_status` to `$order_keys` array** (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
    - Action: Append `['key' => 'order_status', 'label' => '訂單狀態']` to the `$order_keys` array in `get_context_keys_map()`
    - Reason: Existing ORDER_COMPLETED and all 6 new order triggers should expose order_status in context keys API
    - Dependency: Step 8
    - Risk: Low -- additive only

18. **Add `$customer_keys` array** (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
    - Action: Define new `$customer_keys` array with 5 entries: `customer_id`, `billing_email`, `billing_first_name`, `billing_last_name`, `billing_phone`
    - Dependency: Step 12
    - Risk: Low

19. **Add `$subscription_keys` array** (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
    - Action: Define new `$subscription_keys` array with 8 entries: `subscription_id`, `subscription_status`, `customer_id`, `billing_email`, `billing_first_name`, `billing_last_name`, `order_total`, `payment_method`
    - Dependency: Step 16
    - Risk: Low

20. **Add 14 mappings to `$context_keys_map`** (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
    - Action: Add 6 order trigger mappings to `$order_keys`, 1 customer mapping to `$customer_keys`, 7 subscription mappings to `$subscription_keys`
    - Dependency: Steps 17-19
    - Risk: Low

## Testing Strategy

- **PHPStan Level 9**: Run `composer analyse` to verify all new code passes static analysis
- **PHPCS**: Run `composer lint` to verify WordPress Coding Standards compliance
- **Smoke Test**: Run `composer test:smoke` to verify no regressions
- **Manual Verification**:
  - Trigger a WooCommerce order status change and verify the corresponding workflow fires
  - Register a new WordPress user and verify CUSTOMER_REGISTERED triggers
  - Create/modify a WooCommerce subscription and verify subscription triggers fire
  - Query the context keys API for each new trigger point and verify correct keys are returned

## Dependencies

- **WooCommerce** (soft dependency): Required for order status triggers; detected via `function_exists('wc_get_order')`
- **WooCommerce Subscriptions + Powerhouse** (soft dependency): Required for subscription triggers; detected via `function_exists('wcs_get_subscription')`
- No new library/package dependencies

## Risk & Mitigation Summary

- **High**: None
- **Medium**: Subscription handler PHPStan typing -- `WC_Subscription` may not be available at analysis time. Mitigation: Use `mixed` type + `instanceof` runtime check in handler signature.
- **Low**: 13 other steps follow established, proven patterns

## Error Handling Strategy

- **Soft dependency not available**: Silently skip hook registration (no error, no log)
- **Entity not found at trigger time**: Log warning via `Plugin::logger()`, return null from `build_*_context_callable_set()`, skip `do_action()`
- **Entity not found at resolve time**: Return empty array `[]` (safe default, existing pattern)
- **Invalid parameters**: Return empty array (guard clause at method start)

## Constraints

- This plan does NOT:
  - Create new files -- all changes are in 2 existing files
  - Add new REST API endpoints -- only expands existing context keys mapping
  - Modify frontend code -- trigger point list is dynamically loaded from API
  - Add database tables or schema changes
  - Implement ORDER_CREATED (explicitly excluded per clarification)
  - Add `refund_amount` to ORDER_REFUNDED context (explicitly excluded per clarification)
  - Add `registration_source` to CUSTOMER_REGISTERED context (explicitly excluded per clarification)

## Estimated Complexity: Low

The entire task is additive, following well-established patterns in the codebase. Each new trigger follows the exact same structure as the existing ORDER_COMPLETED trigger. The subscription triggers add minor complexity due to the Powerhouse hook signature, but the pattern is straightforward.

## Execution Agent

> This is a pure PHP/WordPress backend task. All changes should be executed by **`@wp-workflows:wordpress-master`** agent.

---

**Ready for execution**: This plan modifies exactly 2 files with no architectural changes, no new dependencies, and no frontend impact. Proceed to implementation.
