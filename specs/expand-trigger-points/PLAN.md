# Implementation Plan: Expand Workflow Trigger Points

## Overview

Extend the Power Funnel workflow engine from 1 trigger point (`REGISTRATION_CREATED`) to 16 trigger points across 5 categories. Each trigger point is a PHP-backed enum case in `ETriggerPoint` that fires a `do_action('pf/trigger/...')` hook, which published `WorkflowRule` instances listen to and create `Workflow` execution instances. The work includes new enum cases, hook wiring in existing services, recursion guard logic, Action Scheduler integration for time-based triggers, and expanding the `trigger_point` meta from a plain string to a `{hook, params}` object for configurable triggers like `ACTIVITY_BEFORE_START`.

## Scope Mode: HOLD SCOPE

Requirements are well-defined from the clarify session. 15 trigger points across 4 priority tiers with clear decisions. Estimated file impact ~12 files, within manageable range.

## Requirements (restated)

1. Add 15 new `ETriggerPoint` enum cases with labels, grouped as P0/P1/P2/P3
2. Wire `do_action('pf/trigger/...')` calls in existing lifecycle hooks (Registration, LINE Webhook, Workflow status)
3. Build recursion guard for workflow-triggers-workflow chains (max_depth=3, creates failed Workflow + error log on exceed)
4. Integrate Action Scheduler for `ACTIVITY_STARTED` and `ACTIVITY_BEFORE_START` time-based triggers
5. Expand `trigger_point` meta on WorkflowRule from `string` to `{hook: string, params: {before_minutes?: int}}` with backward compatibility
6. Two trigger points (`ACTIVITY_ENDED`, `PROMO_LINK_CLICKED`) are enum-only stubs -- no trigger logic
7. `USER_TAGGED` fires from `tag_user` NodeDefinition's `execute()` completion
8. Front-end auto-reflects new trigger points via existing `/trigger-points` API -- no front-end code changes required

## Known Risks

| # | Risk | Severity | Mitigation |
|---|------|----------|------------|
| 1 | `register_workflow_rules()` is defined but never called from Bootstrap -- trigger points fire but no WorkflowRule listens | High | Must wire `register_workflow_rules` into init sequence; verify in integration tests |
| 2 | Recursion guard state must survive across `do_action` calls within the same PHP request | Medium | Use a static class property counter; reset on request boundary |
| 3 | `trigger_point` meta change (string -> object) breaks existing WorkflowRuleDTO hydration | High | Implement backward-compatible reader: if string, normalize to `{hook: string, params: {}}` |
| 4 | `WorkflowRule\Register::register_meta_fields()` registers `trigger_point` as `type=string` with `sanitize_text_field` -- objects won't survive | High | Update meta registration to handle the new format (change type/sanitize) |
| 5 | Action Scheduler dependency (`as_schedule_single_action`) -- already used by WaitNode but not formally required | Low | WaitNode already uses it; Action Scheduler ships with WooCommerce and is available |
| 6 | LINE webhook `$event->getType()` returns event type strings (follow, unfollow, message) but current `do_action` format is `power_funnel/line/webhook/{type}/{action}` -- `follow`/`unfollow` events may not have an `action` value | Medium | Need to handle null `$action` from `EventWebhookHelper::get_action()` for non-postback events |
| 7 | No existing `tag_user` NodeDefinition class -- only an `ENode::TAG_USER` enum case exists | Medium | Provide a static callable API in TriggerPointService; future TagUserNode will call it |

## Architecture Changes

### Files to modify (existing)

| File | Change |
|------|--------|
| `inc/classes/Shared/Enums/ETriggerPoint.php` | Add 15 new enum cases + labels |
| `inc/classes/Infrastructure/Repositories/Registration/Register.php` | (Unchanged -- existing lifecycle hook already fires `power_funnel/registration/{status}`) |
| `inc/classes/Infrastructure/Line/Services/WebhookService.php` | Add type-only hook dispatch for follow/unfollow/message events |
| `inc/classes/Infrastructure/Repositories/Workflow/Register.php` | (Unchanged -- existing lifecycle hook already fires `power_funnel/workflow/{status}`) |
| `inc/classes/Contracts/DTOs/WorkflowRuleDTO.php` | Parse `trigger_point` meta as string or `{hook, params}` object, normalize |
| `inc/classes/Infrastructure/Repositories/WorkflowRule/Register.php` | Update `register_meta_fields()` for new trigger_point format |
| `inc/classes/Bootstrap.php` | Wire `register_workflow_rules()` call + new TriggerPointService |
| `tests/integration/WorkflowRule/QueryTriggerPointsTest.php` | Update expected count from 1 to 16 |

### Files to create (new)

| File | Purpose |
|------|---------|
| `inc/classes/Domains/Workflow/Services/TriggerPointService.php` | Central service: wires all `do_action('pf/trigger/...')` calls with proper context_callable_set |
| `inc/classes/Domains/Workflow/Services/RecursionGuard.php` | Static depth tracker for workflow-triggers-workflow chains |
| `inc/classes/Domains/Workflow/Services/ActivitySchedulerService.php` | Action Scheduler integration for `ACTIVITY_STARTED` and `ACTIVITY_BEFORE_START` |

## Data Flow Analysis

### P0: Registration Status -> Trigger Point

```
Registration post_status change
  |
  v
transition_post_status (WP hook)
  |
  v
Registration\Register::register_lifecycle()
  |
  +--> do_action("power_funnel/registration/{status}", ...)  [existing]
  |
  v
TriggerPointService::on_registration_{status}()  [NEW]
  |
  +--> build context_callable_set
  |       callable: fn(int $post_id) => [registration_id, identity_id, ...]
  |       params: [$post_id]
  |
  +--> do_action("pf/trigger/registration_{status}", $context_callable_set)
  |
  v
WorkflowRuleDTO::register() callback
  +--> Repository::create_from($rule, $context_callable_set)
  +--> wp_insert_post(pf_workflow, status=running)
  +--> power_funnel/workflow/running hook fires
  +--> WorkflowDTO::try_execute()

  Shadow paths:
  - nil: $post not pf_registration -> early return (existing guard)
  - nil: ERegistrationStatus::tryFrom fails -> no trigger (existing guard)
  - error: wp_insert_post fails -> Exception (existing handling in Repository)
```

### P1: LINE Webhook -> Trigger Point

```
LINE webhook POST /power-funnel/line-callback
  |
  v
WebhookService::post_line_callback_callback()
  |
  +--> EventRequestParser::parseEventRequest()
  |
  +--> foreach event:
  |      do_action("power_funnel/line/webhook/{type}/{action}", $event) [existing]
  |      do_action("power_funnel/line/webhook/{type}", $event)          [NEW type-only hook]
  |
  v
TriggerPointService::on_line_webhook_{type}()  [NEW, hooked to power_funnel/line/webhook/follow, etc.]
  |
  +--> extract line_user_id from $event
  |
  +--> build context_callable_set
  |       callable: fn(string $line_user_id, string $event_type, ...) => [line_user_id, event_type, ...]
  |
  +--> do_action("pf/trigger/line_{type}", $context_callable_set)

  Shadow paths:
  - nil: $event has no source userId -> skip trigger (guard needed)
  - nil: event type not follow/unfollow/message -> no trigger (handled by hook routing)
  - error: EventRequestParser throws -> existing catch in WebhookService
```

### P2: Workflow Status -> Trigger Point + Recursion Guard

```
Workflow post_status change (running -> completed|failed)
  |
  v
Workflow\Register::register_lifecycle()
  |
  +--> do_action("power_funnel/workflow/{status}", $workflow_id)  [existing]
  |
  v
TriggerPointService::on_workflow_{status}($workflow_id)  [NEW]
  |
  +--> build context_callable_set
  |       callable: fn(string $id) => [workflow_id, workflow_rule_id, trigger_point]
  |
  +--> do_action("pf/trigger/workflow_{status}", $context_callable_set)

  Shadow paths:
  - recursion: depth > 3 -> create failed Workflow + error log (via RecursionGuard in WorkflowRuleDTO::register())
  - nil: workflow_id invalid -> get_post returns null -> guard needed
  - error: create_from throws -> Exception propagates
```

### Recursion Guard (applied in WorkflowRuleDTO::register())

```
WorkflowRuleDTO::register() callback fires
  |
  +--> RecursionGuard::enter()
  |
  +--> if RecursionGuard::is_exceeded():
  |       create Workflow with status=failed
  |       Plugin::logger(error)
  |       RecursionGuard::leave()
  |       return
  |
  +--> Repository::create_from($rule, $context_callable_set)
  |
  +--> RecursionGuard::leave()
```

### P3: Activity Schedule -> Trigger Point

```
Activity sync from provider (e.g., YouTube)
  |
  v
ActivitySchedulerService::schedule_activity($activity_id)  [NEW]
  |
  +--> as_unschedule_all_actions(old hook, old args) [if updating]
  |
  +--> as_schedule_single_action(
  |       $start_timestamp,
  |       'power_funnel/activity_trigger/started',
  |       [$activity_id]
  |     )
  |
  +--> for each WorkflowRule with trigger=activity_before_start:
  |       calculate $start_timestamp - before_minutes * 60
  |       as_schedule_single_action(
  |         $before_timestamp,
  |         'power_funnel/activity_trigger/before_start',
  |         [$activity_id, $rule_id]
  |       )

  When Action Scheduler fires:
  +--> TriggerPointService::on_activity_started($activity_id)
  |       build context_callable_set
  |       do_action("pf/trigger/activity_started", $context_callable_set)

  Shadow paths:
  - nil: activity has no scheduled_start_time -> skip scheduling
  - nil: before_minutes <= 0 or missing -> use default 30 or skip
  - stale: activity time updated after scheduling -> must cancel old schedule first
  - error: as_schedule_single_action returns 0 -> log error
```

## Error Handling Registry

| Method/Path | Possible Failure | Error Type | Handling | User Visible? |
|-------------|-----------------|------------|----------|---------------|
| TriggerPointService::on_registration_* | Registration post not found | RuntimeException | Skip trigger, log warning | Silent |
| TriggerPointService::on_line_webhook_* | Event missing userId | Guard check | Skip trigger | Silent |
| TriggerPointService::on_workflow_* | Workflow post not found | RuntimeException | Skip trigger, log warning | Silent |
| RecursionGuard (in WorkflowRuleDTO::register) | Recursion depth exceeded | Business rule | Create failed Workflow + error log | Admin log |
| ActivitySchedulerService::schedule | as_schedule_single_action returns 0 | Scheduling failure | Log error | Silent |
| ActivitySchedulerService::schedule | Activity has no start time | Missing data | Skip scheduling | Silent |
| WorkflowRuleDTO::trigger_point parse | Invalid JSON in meta | TypeError | Fallback to string parse | Silent |

## Failure Mode Registry

| Code Path | Failure Mode | Handled? | Tested? | User Visible? | Recovery Path |
|-----------|-------------|----------|---------|---------------|---------------|
| Registration status change but no matching WorkflowRule | No trigger fires | Yes (by design) | Yes | Silent | None needed |
| LINE webhook with invalid signature | Exception in EventRequestParser | Yes (existing) | No | HTTP 500 | Retry from LINE |
| Workflow recursion depth > 3 | Failed Workflow created | Yes (new) | Yes (new) | Admin log | Manual review |
| Action Scheduler fires but activity deleted | Activity lookup returns null | Yes (new guard) | Yes (new) | Silent | None needed |
| trigger_point meta is legacy string format | DTO parse | Yes (backward compat) | Yes (new) | Silent | Auto-normalize |
| before_minutes is 0 or negative | Invalid schedule | Yes (skip + log) | Yes (new) | Silent | Admin fixes rule |

## Implementation Steps

### Phase 0: Foundation -- ETriggerPoint Enum + Backward-Compatible Meta (P0 prerequisite)

> Execution Agent: `@wp-workflows:wordpress-master`

**Step 0.1** -- Expand ETriggerPoint enum (File: `inc/classes/Shared/Enums/ETriggerPoint.php`)
- Action: Add 15 new enum cases with Chinese labels. Keep `const PREFIX = 'pf/trigger/'`. Update `label()` method.
- Reason: All subsequent phases depend on the enum cases existing.
- Dependency: None
- Risk: Low

**Step 0.2** -- Update WorkflowRuleDTO trigger_point parsing (File: `inc/classes/Contracts/DTOs/WorkflowRuleDTO.php`)
- Action: Change `trigger_point` property to read from meta as either string or `{hook, params}` object. If string, normalize to `{hook: string, params: []}`. Add `get_trigger_hook(): string` method and `get_trigger_params(): array` method. The `register()` method should use `get_trigger_hook()` for `add_action`.
- Reason: Backward compatibility for existing WorkflowRules that store `trigger_point` as a plain string. New rules (e.g., `ACTIVITY_BEFORE_START`) will store `{hook, params: {before_minutes: 30}}`.
- Dependency: None
- Risk: High -- must not break existing rules

**Step 0.3** -- Update WorkflowRule meta registration (File: `inc/classes/Infrastructure/Repositories/WorkflowRule/Register.php`)
- Action: Update `register_meta_fields()` to handle `trigger_point` as either string or JSON object. Change `sanitize_callback` from `sanitize_text_field` to a custom sanitizer that handles both formats. Update `type` and `show_in_rest` schema.
- Reason: REST API must accept and store both string and object formats.
- Dependency: Step 0.2
- Risk: Medium -- REST API schema change

**Step 0.4** -- Wire `register_workflow_rules()` into Bootstrap (File: `inc/classes/Bootstrap.php`)
- Action: Add `\add_action('init', [WorkflowRule\Register::class, 'register_workflow_rules'], 99)` to ensure all published WorkflowRules register their hook listeners on every request. Priority 99 ensures CPT and meta are registered first.
- Reason: Currently `register_workflow_rules()` is defined but never called -- trigger points fire into the void.
- Dependency: None
- Risk: High -- this is critical for the entire feature to work.

### Phase 1: P0 -- Registration Status Triggers (4 trigger points)

> Execution Agent: `@wp-workflows:wordpress-master`

**Step 1.1** -- Create TriggerPointService (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
- Action: Create new service class with `register_hooks()` that hooks into `power_funnel/registration/success`, `power_funnel/registration/rejected`, `power_funnel/registration/cancelled`, `power_funnel/registration/failed`. Each handler builds a `context_callable_set` with a callable that extracts registration context (registration_id, identity_id, identity_provider, activity_id, promo_link_id) from a post ID, then fires `do_action('pf/trigger/registration_{mapped_status}', $context_callable_set)`. Note: `success` status maps to `registration_approved` trigger.
- Reason: Centralized trigger point firing avoids scattering `do_action('pf/trigger/...')` calls across the codebase.
- Dependency: Step 0.1 (enum cases), Step 0.4 (register_workflow_rules)
- Risk: Medium

**Step 1.2** -- Wire TriggerPointService into Bootstrap (File: `inc/classes/Bootstrap.php`)
- Action: Add `Domains\Workflow\Services\TriggerPointService::register_hooks()` call.
- Reason: Service must be registered to listen to hooks.
- Dependency: Step 1.1
- Risk: Low

**Step 1.3** -- Integration tests for P0 triggers
- Action: Create `tests/integration/TriggerPoint/RegistrationTriggerTest.php`. Test that:
  - Updating a registration from `pending` to `success` fires `pf/trigger/registration_approved` with correct context
  - Updating a registration from `pending` to `rejected` fires `pf/trigger/registration_rejected`
  - Updating a registration from `pending` to `rejected` does NOT fire `pf/trigger/registration_approved`
  - Context callable returns correct fields (registration_id, identity_id, identity_provider, activity_id, promo_link_id)
- Test Command: `composer test -- --filter=RegistrationTriggerTest`
- Dependency: Step 1.1, Step 1.2
- Risk: Low

### Phase 2: P1 -- LINE Interaction Triggers (3 trigger points)

> Execution Agent: `@wp-workflows:wordpress-master`

**Step 2.1** -- Update WebhookService to fire type-only hooks (File: `inc/classes/Infrastructure/Line/Services/WebhookService.php`)
- Action: In addition to existing `do_action("power_funnel/line/webhook/{type}/{action}")`, add a type-only hook: `do_action("power_funnel/line/webhook/{type}", $event)`. This allows TriggerPointService to hook into `power_funnel/line/webhook/follow` regardless of action.
- Reason: Follow/unfollow events don't have postback data, so `$action` is null and the existing hook path may not match.
- Dependency: None
- Risk: Medium -- must not break existing postback handlers

**Step 2.2** -- Add LINE trigger handlers to TriggerPointService (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
- Action: Hook into `power_funnel/line/webhook/follow`, `power_funnel/line/webhook/unfollow`, `power_funnel/line/webhook/message`. Each handler extracts line_user_id via EventWebhookHelper, validates it's not null, builds context_callable_set, and fires `do_action('pf/trigger/line_{type}')`. For message events, also extract message text.
- Reason: LINE events need to trigger corresponding workflow trigger points.
- Dependency: Step 0.1, Step 2.1
- Risk: Medium

**Step 2.3** -- Integration tests for P1 triggers
- Action: Create `tests/integration/TriggerPoint/LineTriggerTest.php`. Test:
  - Follow event fires `pf/trigger/line_followed` with `{line_user_id, event_type}`
  - Unfollow event fires `pf/trigger/line_unfollowed`
  - Message event fires `pf/trigger/line_message_received` with `{line_user_id, event_type, message_text}`
  - Event without userId does NOT fire triggers
- Dependency: Step 2.1-2.2
- Risk: Medium -- LINE SDK mocking complexity

### Phase 3: P2 -- Workflow Engine Triggers + Recursion Guard (2 trigger points + guard)

> Execution Agent: `@wp-workflows:wordpress-master`

**Step 3.1** -- Create RecursionGuard (File: `inc/classes/Domains/Workflow/Services/RecursionGuard.php`)
- Action: Create a static class with:
  - `private static int $depth = 0`
  - `const MAX_DEPTH = 3`
  - `public static function enter(): void` (increment depth)
  - `public static function leave(): void` (decrement depth)
  - `public static function is_exceeded(): bool` (depth >= MAX_DEPTH)
  - `public static function depth(): int` (current depth)
  - `public static function reset(): void` (for testing)
- Reason: Prevent infinite workflow chains. Specs mandate max_depth=3 with failed Workflow on exceed.
- Dependency: None
- Risk: Low

**Step 3.2** -- Add workflow trigger handlers to TriggerPointService (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
- Action: Hook into `power_funnel/workflow/completed` and `power_funnel/workflow/failed`. Each handler builds context_callable_set with callable that reads workflow_id, workflow_rule_id, trigger_point from the completed/failed workflow, then fires `do_action('pf/trigger/workflow_completed|failed', $context_callable_set)`.
- Reason: Workflow lifecycle events should be trigger points for other workflows.
- Dependency: Step 0.1
- Risk: Medium

**Step 3.3** -- Integrate RecursionGuard into WorkflowRuleDTO::register() (File: `inc/classes/Contracts/DTOs/WorkflowRuleDTO.php`)
- Action: In the `register()` method's callback (where `Repository::create_from()` is called), wrap with `RecursionGuard::enter()` / `RecursionGuard::leave()`. If `RecursionGuard::is_exceeded()` before entering, create a failed Workflow via `Workflow\Repository` with status=failed and log error using `Plugin::logger()`.
- Reason: The guard must be checked at the point where a new Workflow is about to be created, regardless of which trigger point caused it.
- Dependency: Step 3.1
- Risk: High -- touches the critical workflow creation path

**Step 3.4** -- Integration tests for P2 triggers + recursion guard
- Action: Create `tests/integration/TriggerPoint/WorkflowTriggerTest.php` and `tests/integration/Workflow/RecursionGuardTest.php`. Test:
  - Workflow completing fires `pf/trigger/workflow_completed` with correct context
  - Workflow failing fires `pf/trigger/workflow_failed`
  - Depth=1 and depth=2 allow normal Workflow creation
  - Depth=3 creates a failed Workflow with error log
  - After recursion guard triggers, depth counter decrements properly
- Dependency: Step 3.1-3.3
- Risk: Medium

### Phase 4: P3 -- Activity Time Triggers + User Behavior (4 trigger points, 2 enum-only)

> Execution Agent: `@wp-workflows:wordpress-master`

**Step 4.1** -- Create ActivitySchedulerService (File: `inc/classes/Domains/Workflow/Services/ActivitySchedulerService.php`)
- Action: Create service that:
  - On activity sync (hook into ActivityService or add new hook), schedules `as_schedule_single_action` for `ACTIVITY_STARTED` at the activity's `scheduled_start_time`.
  - For each published WorkflowRule with `trigger_hook = pf/trigger/activity_before_start`, reads `before_minutes` from params, calculates trigger time, and schedules.
  - On activity time update, cancels old schedules (`as_unschedule_all_actions`) and creates new ones.
  - Registers Action Scheduler hook handlers that build context_callable_set and fire the trigger.
  - Validates before_minutes is positive integer; defaults to 30 if missing; skips if 0 or negative.
- Reason: Time-based triggers require scheduling since they don't correspond to any synchronous PHP event.
- Dependency: Step 0.1, Step 0.2 (trigger_params)
- Risk: High -- new scheduling infrastructure

**Step 4.2** -- Wire `USER_TAGGED` trigger into TriggerPointService (File: `inc/classes/Domains/Workflow/Services/TriggerPointService.php`)
- Action: Add a public static method `fire_user_tagged(string $user_id, string $tag_name): void` that builds context_callable_set and fires `do_action('pf/trigger/user_tagged', ...)`. This method will be called by the future `TagUserNode::execute()` implementation.
- Reason: USER_TAGGED fires from within a NodeDefinition's execute(), so we provide a callable API.
- Dependency: Step 0.1
- Risk: Low

**Step 4.3** -- Enum-only stubs: ACTIVITY_ENDED and PROMO_LINK_CLICKED
- Action: Already handled in Step 0.1 (enum cases + labels). No additional wiring needed. Verify labels appear in API response.
- Dependency: Step 0.1
- Risk: None

**Step 4.4** -- Integration tests for P3 triggers
- Action: Create `tests/integration/TriggerPoint/ActivitySchedulerTest.php` and `tests/integration/TriggerPoint/UserTaggedTest.php`. Test:
  - Activity sync creates Action Scheduler job at correct time
  - Activity time update cancels old and creates new schedule
  - Activity without start time skips scheduling
  - `before_minutes` configuration produces correct schedule time
  - `before_minutes` default (30) when not specified
  - `USER_TAGGED` fires with correct context when called
  - Enum-only stubs appear in trigger points list but don't have active hooks
- Dependency: Step 4.1-4.3
- Risk: Medium

### Phase 5: Update Existing Tests + Query Validation

> Execution Agent: `@wp-workflows:wordpress-master`

**Step 5.1** -- Update QueryTriggerPointsTest (File: `tests/integration/WorkflowRule/QueryTriggerPointsTest.php`)
- Action: Update `test_` to assert 16 trigger points. Add assertions for all new trigger point hooks. Remove `markTestIncomplete` calls where tests are now verifiable.
- Dependency: Step 0.1
- Risk: Low

**Step 5.2** -- Verify backward compatibility of trigger_point meta
- Action: Create test in `tests/integration/WorkflowRule/TriggerPointMetaTest.php` that:
  - Creates a WorkflowRule with trigger_point as plain string (legacy format)
  - Creates a WorkflowRule with trigger_point as `{hook, params}` object
  - Both should register correctly and respond to their respective hooks
  - WorkflowRuleDTO::of() should normalize both formats
- Dependency: Step 0.2, Step 0.3
- Risk: Medium

## Test Strategy

### Integration Tests (PHPUnit)

| Test Class | Feature File Reference | Key Scenarios |
|-----------|----------------------|---------------|
| `RegistrationTriggerTest` | `fire-registration-approved.feature` etc. | Status change fires correct trigger, wrong status does not fire, context has all fields |
| `LineTriggerTest` | `fire-line-followed.feature` etc. | Follow/unfollow/message events fire correct triggers, missing userId skips |
| `WorkflowTriggerTest` | `fire-workflow-completed.feature` etc. | Completed/failed fires trigger, context has workflow_id/rule_id/trigger_point |
| `RecursionGuardTest` | `recursion-guard.feature` | depth=1,2 pass; depth=3 creates failed Workflow; depth counter resets |
| `ActivitySchedulerTest` | `fire-activity-started.feature`, `fire-activity-before-start.feature` | Schedules at correct time, cancels on update, before_minutes config works |
| `UserTaggedTest` | `fire-user-tagged.feature` | Fires with user_id and tag_name, not fired on failure |
| `TriggerPointMetaTest` | n/a | Backward compat: string and object formats both work |
| `QueryTriggerPointsTest` (update) | `query-expanded-trigger-points.feature` | 16 trigger points in response, format correct |

### Test Execution
- `composer test` -- full suite
- `composer test -- --filter=RegistrationTriggerTest` -- single class
- `composer test -- --group=trigger-points` -- trigger-points group

### Critical Edge Cases to Cover
- Registration status same-to-same transition (e.g., success -> success) should NOT fire trigger
- LINE event with empty/null userId
- Workflow chain depth exactly at 3 (boundary)
- Workflow chain depth at 4 (over boundary)
- `before_minutes` = 0, negative, missing, non-integer
- Activity deleted between scheduling and firing
- Multiple WorkflowRules with different `before_minutes` for the same trigger point
- Legacy string-format `trigger_point` meta still works after code change

## Risks and Mitigations

- **HIGH: `register_workflow_rules()` not wired** -- Mitigation: Step 0.4 adds it. Without this, the entire trigger-to-workflow pipeline is broken.
- **HIGH: trigger_point meta format change** -- Mitigation: Step 0.2 implements dual-format reading with normalization.
- **HIGH: Recursion guard in critical path** -- Mitigation: RecursionGuard is a simple static counter, minimal overhead.
- **MEDIUM: LINE webhook routing for non-postback events** -- Mitigation: Step 2.1 adds type-only hooks alongside existing type+action hooks.
- **MEDIUM: Action Scheduler timing** -- Mitigation: Integration tests verify scheduling; manual QA for actual timer firing.
- **LOW: TagUserNode not yet implemented** -- Mitigation: Step 4.2 provides a static callable API.

## Success Criteria

- [ ] `ETriggerPoint::cases()` returns 16 cases
- [ ] GET `/trigger-points` API returns 16 trigger points with correct hook and name
- [ ] Changing a registration to `success` fires `pf/trigger/registration_approved` and creates a Workflow (if matching rule exists)
- [ ] LINE follow/unfollow/message events fire corresponding triggers
- [ ] Workflow completed/failed events fire corresponding triggers
- [ ] Workflow chain at depth 3 creates a failed Workflow with error log
- [ ] Activity started triggers fire at scheduled time via Action Scheduler
- [ ] `ACTIVITY_BEFORE_START` respects configurable `before_minutes` from WorkflowRule meta
- [ ] Legacy string-format `trigger_point` meta still works (backward compatible)
- [ ] `ACTIVITY_ENDED` and `PROMO_LINK_CLICKED` appear in API list but have no active triggers
- [ ] All integration tests pass: `composer test -- --group=trigger-points`

## Constraints (what this plan does NOT do)

- Does NOT implement `ACTIVITY_ENDED` trigger logic (no end time data source)
- Does NOT implement `PROMO_LINK_CLICKED` trigger logic (no click tracking mechanism)
- Does NOT add rate limiting to `LINE_MESSAGE_RECEIVED`
- Does NOT create the `TagUserNode` NodeDefinition class (only provides the trigger firing API)
- Does NOT modify any front-end code (React/TypeScript)
- Does NOT add E2E (Playwright) tests -- only PHPUnit integration tests

## Estimated Complexity: Medium-High

12 affected files (8 modified + 4 new). New scheduling infrastructure, critical backward compatibility requirement.
