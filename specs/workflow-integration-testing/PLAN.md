# Implementation Plan: Workflow Integration Testing

## Overview

Supplement the workflow engine's integration test coverage across three dimensions: (1) complete the 7 `markTestIncomplete` tests in `ActionSchedulerChainingTest`, (2) add a new `WorkflowContextPassingTest` verifying context key replacement via ParamHelper, and (3) add a new `WorkflowEndToEndTest` validating full ORDER_COMPLETED chain including WaitNode crossing and YesNoBranchNode branching.

## Scope Mode: HOLD SCOPE

This is a test-only task. No new features, APIs, or entity changes. The scope is precisely defined by the existing feature specs and the Issue requirements.

## Requirements Restatement

1. **ActionSchedulerChainingTest** -- Remove all `markTestIncomplete` calls, implement 7 tests verifying `NodeDTO::try_execute()` AS scheduling behavior:
   - Non-delay node success -> engine schedules via AS
   - Delay node (scheduled=true) -> engine does NOT double-schedule
   - Node failure -> no AS scheduling
   - Last node success -> workflow transitions to completed
   - WorkflowResultDTO.scheduled default = false
   - WorkflowResultDTO.scheduled can be set to true
   - YesNoBranchNode with next_node_id -> engine still schedules

2. **WorkflowContextPassingTest** (new file) -- Verify context_callable_set resolution and `{{variable}}` template replacement:
   - `recipient = "context"` -> resolved from context's customer_email -> wp_mail receives correct address
   - Multiple `{{variable}}` in templates simultaneously replaced
   - context_callable_set survives serialize/unserialize round-trip
   - Empty context_callable_set -> empty context, fallback values used

3. **WorkflowEndToEndTest** (new file) -- Full chain E2E:
   - ORDER_COMPLETED trigger -> Workflow created (running)
   - TagUserNode + EmailNode + WaitNode + WebhookNode sequential execution
   - WaitNode pauses workflow -> manual AS trigger resumes -> WebhookNode completes -> completed
   - YesNoBranchNode yes/no path selection
   - Branch after-path continuation

## Known Risks

- **Risk**: `ReplaceHelper` null-object bug -- `ReplaceHelper::__construct()` calls `EObjectType::get_type(null)` which throws in test env.
  - Mitigation: Use existing `test_email` node definition pattern (inject via filter, directly calls `wp_mail()`). For E2E tests, also create `test_webhook` and `test_tag_user` stubs.

- **Risk**: Action Scheduler dedup -- `as_schedule_single_action()` may return 0 for identical hook+args in same second.
  - Mitigation: Use `as_has_scheduled_action()` for assertion (not exact action_id count). Clear AS queue in `tear_down()`.

- **Risk**: `wp_mail` in test env -- PHPMailer validates From address, `wordpress@localhost` fails.
  - Mitigation: Existing pattern: `remove_all_filters('pre_wp_mail')` + `add_filter('wp_mail_from', fn() => 'test@example.com')`.

- **Risk**: `wp_slash` / `wp_unslash` strips backslashes from class names in `meta_input`.
  - Mitigation: Existing pattern: `wp_slash($meta)` before `wp_insert_post`.

## Architecture Changes

No production code changes. Test-only files:

| File | Change Type | Description |
|------|------------|-------------|
| `tests/integration/Workflow/ActionSchedulerChainingTest.php` | Modify | Remove 7 `markTestIncomplete`, implement full test bodies |
| `tests/integration/Workflow/WorkflowContextPassingTest.php` | New | 4 test methods for context passing |
| `tests/integration/Workflow/WorkflowEndToEndTest.php` | New | 5-6 test methods for E2E chain |

## Data Flow Analysis

```
ORDER_COMPLETED trigger
  |
  v
do_action('pf/trigger/order_completed', $context_callable_set)
  |
  v
WorkflowRuleDTO::register() callback
  |
  v
Repository::create_from($rule_dto, $context_callable_set)
  |
  v
wp_insert_post(pf_workflow, status=running, meta: nodes + context_callable_set)
  |
  v
transition_post_status -> do_action('power_funnel/workflow/running')
  |
  v
Register::start_workflow($workflow_id)
  |
  v
WorkflowDTO::of($id) -> try_execute()
  |
  +---> get_current_index() -> NodeDTO[0]
  |       |
  |       v
  |     NodeDTO::try_execute($workflow_dto)
  |       |
  |       +---> can_execute(match_callback) ?
  |       |       NO  -> code=301, as_schedule next
  |       |       YES -> Repository::get_node_definition()
  |       |               |
  |       |               v
  |       |             definition.execute($node, $workflow)
  |       |               |
  |       |               +---> code=200, scheduled=false -> engine as_schedule next
  |       |               +---> code=200, scheduled=true  -> engine SKIP schedule (WaitNode did it)
  |       |               +---> code=500 / exception      -> workflow FAILED, NO schedule
  |       |
  |       v
  |     add_result() -> update_post_meta('results')
  |
  +---> AS fires 'power_funnel/workflow/running'
  |       |
  |       v
  |     try_execute() again -> next node
  |
  +---> get_current_index() returns null -> set_status(COMPLETED)
```

Shadow paths:
- **nil**: `context_callable_set` empty -> `context = []` -> ParamHelper falls back to raw param value
- **empty**: `nodes = []` -> `get_current_index()` returns null immediately -> COMPLETED
- **error**: Node definition not found -> RuntimeException -> FAILED
- **error**: `as_schedule_single_action()` returns 0 -> WaitNode code=500

## Error Handling Registry

| Method/Path | Failure Mode | Error Type | Handling | User Visible? |
|-------------|-------------|------------|----------|---------------|
| NodeDTO::try_execute() | definition not found | RuntimeException | catch -> code=500, FAILED | In results |
| NodeDTO::try_execute() | execute() throws | Throwable | catch -> code=500, FAILED | In results |
| WaitNode::execute() | AS returns 0 | Logic | code=500 message | In results |
| WorkflowDTO::try_execute() | non-running status | Guard | early return | Silent |
| WorkflowDTO::get_context() | callable not callable | Guard | return [] | Silent |

## Implementation Steps

### Phase 1: ActionSchedulerChainingTest (modify existing)

**File**: `tests/integration/Workflow/ActionSchedulerChainingTest.php`

1. **Implement configure_dependencies()** (risk: low)
   - Action: Uncomment `Register::register_hooks()` for both WorkflowRule and Workflow
   - Add `remove_all_filters('pre_wp_mail')` + `wp_mail_from` filter
   - Inject `test_email` node definition via filter (same pattern as WorkflowExecutionTest)
   - Reason: All 7 tests need node definitions and workflow hooks registered

2. **Add helper: create_workflow_post()** (risk: low)
   - Action: Copy pattern from WorkflowExecutionTest -- create draft, then bypass hooks to set running
   - Accept `$meta_input` for customizing nodes/results
   - Reason: Reduce boilerplate across 7 tests

3. **Add tear_down() with AS cleanup** (risk: low)
   - Action: `as_unschedule_all_actions('power_funnel/workflow/running')` in `tear_down()`
   - Reason: Prevent AS state leaking between tests

4. **Implement test_EmailNode成功後引擎排程as_schedule_single_action()** (risk: low)
   - Action: Create workflow with 2 test_email nodes, call `try_execute()`, assert `as_has_scheduled_action()` returns true
   - Dependency: Steps 1-3

5. **Implement test_WaitNode回傳scheduled_true時引擎不排程()** (risk: medium)
   - Action: Create workflow with WaitNode + test_email, call `try_execute()`, count AS actions -- WaitNode self-schedules, engine should NOT add another
   - Note: Use `as_get_scheduled_actions()` to count exact number of pending actions
   - Dependency: Steps 1-3

6. **Implement test_節點回傳code_500時不排程()** (risk: low)
   - Action: Create workflow with `non_existent` node, clear AS, call `try_execute()`, assert no AS actions and status=failed
   - Dependency: Steps 1-3

7. **Implement test_最後一個節點成功後排程觸發completed()** (risk: low)
   - Action: Single-node workflow, call `try_execute()` twice -- first executes node, second sees null index -> completed
   - Dependency: Steps 1-3

8. **Implement test_WorkflowResultDTO預設scheduled為false()** (risk: low)
   - Action: `new WorkflowResultDTO([...])` -> `assertFalse($dto->scheduled)`
   - No dependencies on workflow post creation

9. **Implement test_WorkflowResultDTO可設定scheduled為true()** (risk: low)
   - Action: `new WorkflowResultDTO([..., 'scheduled' => true])` -> `assertTrue($dto->scheduled)`
   - No dependencies on workflow post creation

10. **Implement test_YesNoBranchNode回傳next_node_id時引擎仍排程()** (risk: medium)
    - Action: Create workflow with YesNoBranchNode + 2 email nodes, set context with `order_total=2500`, call `try_execute()`, assert AS scheduled
    - Need: context_callable_set with TestCallable returning order_total
    - Dependency: Steps 1-3

### Phase 2: WorkflowContextPassingTest (new file)

**File**: `tests/integration/Workflow/WorkflowContextPassingTest.php`

1. **Create test class skeleton** (risk: low)
   - Action: Create class extending `IntegrationTestCase`, implement `configure_dependencies()` with same pattern as WorkflowExecutionTest
   - Add `create_workflow_post()` helper accepting context_callable_set

2. **test_context中的customer_email被替換到wp_mail收件人()** (risk: medium)
   - Action: Set `TestCallable::$test_context = ['customer_email' => 'buyer@example.com']`, create workflow with `test_email` node having `recipient => 'context'`, hook `wp_mail` filter to capture `$to`, call `try_execute()`, assert captured `$to === 'buyer@example.com'`
   - Key insight: The `test_email` node definition in WorkflowExecutionTest reads `$node->params['recipient']` directly. For context replacement to work, we need a node definition that uses ParamHelper OR we test at a higher level where the context resolution happens in WorkflowDTO.
   - **Important**: Looking at the code, `WorkflowDTO::get_context()` resolves the callable and stores result in `$this->context`. But the individual node definitions are responsible for calling `ParamHelper` to do the replacement. The `test_email` node in WorkflowExecutionTest does NOT use ParamHelper -- it reads params directly.
   - **Resolution**: For this test, we need a node definition that checks if `recipient === 'context'` and then reads from `$workflow->context`. Looking at `NodeDTO::try_get_param()` -- this just returns `$this->params[$key]`. The ParamHelper is used inside real node definitions (EmailNode, WebhookNode etc.). Since real EmailNode has the ReplaceHelper bug, we create a `test_context_email` node that manually checks for `"context"` keyword and reads from `$workflow->context`.
   - Dependency: Step 1

3. **test_模板字串中多個variable同時替換()** (risk: high)
   - Action: This requires `ParamHelper::replace()` which uses `ReplaceHelper` -- the null-object bug makes this impossible with the real implementation.
   - **Resolution**: Skip direct template replacement test. Instead, test that `WorkflowDTO::context` is correctly populated and accessible. The template replacement is ParamHelper's job which is already tested at unit level in the context-param-system feature.
   - Alternative: Test the context resolution chain only -- `context_callable_set -> call_user_func_array -> context array` -- then verify the context array has correct keys/values.

4. **test_context_callable_set經serialize_unserialize後可呼叫()** (risk: low)
   - Action: Write `context_callable_set` to `wp_postmeta` via `update_post_meta`, read back via `get_post_meta`, verify `is_callable()` and `call_user_func_array()` returns expected context
   - Dependency: Step 1

5. **test_空的context_callable_set回傳空陣列()** (risk: low)
   - Action: Create workflow with empty `context_callable_set`, build DTO, assert `$dto->context === []`
   - Dependency: Step 1

### Phase 3: WorkflowEndToEndTest (new file)

**File**: `tests/integration/Workflow/WorkflowEndToEndTest.php`

1. **Create test class skeleton** (risk: low)
   - Action: Create class extending `IntegrationTestCase`
   - `configure_dependencies()`: Register WorkflowRule + Workflow hooks, inject test node definitions (test_email, test_webhook, test_tag_user) via filter, setup wp_mail mocks, setup `pre_http_request` filter for WebhookNode
   - Add `create_workflow_rule_post()` and `create_workflow_post()` helpers
   - `tear_down()`: `as_unschedule_all_actions()`, cleanup

2. **Create test node stubs** (risk: low)
   - `test_tag_user`: Reads `tags` + `action` from params, reads `line_user_id` from context, writes to `pf_user_tags_{line_user_id}` option. Returns code=200.
   - `test_webhook`: Returns code=200 with message "Webhook sent". Does NOT make real HTTP call.
   - These can be anonymous classes in `configure_dependencies()` (same pattern as WorkflowExecutionTest's test_email).

3. **test_ORDER_COMPLETED觸發後建立running_workflow()** (risk: medium)
   - Action: Create published WorkflowRule with `trigger_point = 'pf/trigger/order_completed'`, 4 nodes. Temporarily remove `start_workflow` hook. Fire `do_action('pf/trigger/order_completed', $context_callable_set)`. Assert workflow post created with status=running.
   - Dependency: Steps 1-2

4. **test_四個節點依序執行至WaitNode暫停()** (risk: medium)
   - Action: Create running workflow (bypass trigger, direct DB). Call `try_execute()` three times:
     1. TagUserNode -> code=200, verify tags in option
     2. test_email -> code=200
     3. WaitNode -> code=200, scheduled=true, workflow still running
   - Assert results count = 3, status = running
   - Dependency: Steps 1-2

5. **test_WaitNode到期後繼續執行至completed()** (risk: medium)
   - Action: Continue from step 4 state (3 results already). Call `do_action('power_funnel/workflow/running', ['workflow_id' => $wf_id])` to simulate AS expiry. Assert WebhookNode executed (code=200), results = 4, status = completed.
   - Note: Can combine steps 4+5 into one test method for simplicity.
   - Dependency: Steps 1-2

6. **test_YesNoBranchNode_yes路徑執行()** (risk: medium)
   - Action: Create workflow with YesNoBranchNode (condition: order_total > 1000) + n_vip (test_email) + n_regular (test_email). Context has order_total=1500. Execute until completed. Assert n_vip in results, n_regular NOT in results.
   - Dependency: Steps 1-2

7. **test_YesNoBranchNode_no路徑執行()** (risk: low)
   - Action: Same as step 6 but with order_total=500. Assert n_regular in results, n_vip NOT.
   - Dependency: Steps 1-2

## Test Strategy

- **Integration tests**: All 3 test files run via `composer test` (PHPUnit in wp-env)
- **Test execution command**: `composer test -- --group=workflow`
- **Key boundary cases**:
  - Empty nodes array -> immediate completed
  - Single node workflow -> execute + completed in 2 calls
  - WaitNode as last node -> scheduled + manual trigger -> completed
  - YesNoBranchNode condition true vs false
  - Non-existent node definition -> failed + no AS scheduling
  - Empty context_callable_set -> empty context array

## Dependencies

- **Action Scheduler** (bundled with WooCommerce, available in wp-env): `as_schedule_single_action()`, `as_has_scheduled_action()`, `as_unschedule_all_actions()`
- **TestCallable**: Already exists at `tests/integration/TestCallable.php`
- **TestSuccessNodeDefinition / TestFailNodeDefinition**: Already exist at `tests/integration/Workflow/Stubs/`
- **No new external dependencies**

## Constraints

- No production code changes
- No new node definitions in production code
- Test stubs stay in test directory (anonymous classes in configure_dependencies or Stubs/ directory)
- All tests must be idempotent (tear_down cleans all state)
- No real HTTP requests (pre_http_request filter)
- No real email sending (wp_mail mocked or captured)

## Estimated Complexity: Medium

The individual tests are straightforward (mostly setup + call + assert), but the E2E tests require careful orchestration of workflow state across multiple `try_execute()` calls and AS trigger simulation.

## Execution Agent

> This plan should be executed by **`@wp-workflows:wordpress-master`** agent (PHP/WordPress backend testing).

## Success Criteria

- [ ] `ActionSchedulerChainingTest` -- all 7 tests pass, zero `markTestIncomplete`
- [ ] `WorkflowContextPassingTest` -- context key resolution verified via wp_mail capture
- [ ] `WorkflowEndToEndTest` -- ORDER_COMPLETED trigger -> 4 nodes -> WaitNode crossing -> completed
- [ ] `WorkflowEndToEndTest` -- YesNoBranchNode yes/no paths correctly select branch
- [ ] `composer test` passes with no new `markTestIncomplete`
- [ ] All `tear_down()` methods clean AS pending actions
