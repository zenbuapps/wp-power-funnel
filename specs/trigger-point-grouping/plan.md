# Implementation Plan: TriggerPoint Grouping + Search

## Overview

Enhance the TriggerPoint Select component across the application to support grouped display (OptGroup) and search functionality. The backend API will return a nested grouped structure instead of a flat array, and the frontend will render trigger points organized by category with full-text search across both group names and option names.

## Scope Mode: HOLD SCOPE

This is an enhancement to existing UI/API -- scope is well-defined, no expansion needed. Estimated impact: 7 files (under 15 threshold).

## Requirements Recap

1. Backend API returns grouped structure: `{ group, group_label, items: {hook, name, disabled}[] }[]`
2. Deprecated `REGISTRATION_CREATED` excluded from results
3. Enum stubs marked as `disabled: true` with "(coming soon)" suffix in name
4. Frontend Select uses OptGroup with `showSearch`
5. Search matches both group label and option name (case-insensitive)
6. Both WorkflowRules/Edit and Workflows/List updated simultaneously
7. Saved trigger_point values display correctly on reload

## Known Risks

- **Risk**: Ant Design `Select` with `showSearch` + `OptGroup` requires custom `filterOption` to search across group labels. Default `filterOption` only matches option labels.
  - Mitigation: Implement custom `filterOption` that checks both the option label and the group label.
- **Risk**: The `DTO::to_array()` base class serializes public properties. The new `TriggerPointGroupDTO` is a new DTO that wraps groups, not individual trigger points.
  - Mitigation: Create a new `TriggerPointGroupDTO` with `group`, `group_label`, `items` properties. Items remain `TriggerPointDTO[]`.

## Architecture Changes

- `inc/classes/Shared/Enums/ETriggerPoint.php` -- Add `group(): string`, `group_label(): string`, `is_stub(): bool` methods
- `inc/classes/Contracts/DTOs/TriggerPointDTO.php` -- Add `disabled` property
- `inc/classes/Contracts/DTOs/TriggerPointGroupDTO.php` -- **NEW** DTO for grouped response
- `inc/classes/Infrastructure/Repositories/WorkflowRule/Repository.php` -- Refactor `get_trigger_points()` to return grouped structure, exclude REGISTRATION_CREATED
- `js/src/pages/WorkflowRules/hooks/useTriggerPoints.ts` -- Handle grouped API response, produce OptGroup-compatible options
- `js/src/pages/WorkflowRules/Edit/index.tsx` -- Add `showSearch` + `filterOption` to Select
- `js/src/pages/Workflows/List/index.tsx` -- Same Select updates
- `js/src/pages/WorkflowRules/types/index.ts` -- Add grouped trigger point types

## Data Flow

```
ETriggerPoint enum
       |
       v
Repository::get_trigger_points()
  - Iterate ETriggerPoint::cases()
  - Skip REGISTRATION_CREATED
  - For each case: create TriggerPointDTO { hook, name, disabled }
  - Group by ETriggerPoint::group()
  - Wrap in TriggerPointGroupDTO { group, group_label, items }
  - Return TriggerPointGroupDTO[]
       |
       v
TriggerPointApi::get_trigger_points_callback()
  - Call Repository::get_trigger_points()
  - Map to array via to_array()
  - Return WP_REST_Response { code, message, data }
       |
       v
GET /wp-json/power-funnel/trigger-points
       |
       v
useTriggerPoints() hook
  - Parse grouped response
  - Build groupedOptions (OptGroup format for Ant Design)
  - Build labelMap (hook => name, for table column rendering)
  - Build groupLabelMap (hook => group_label, for search)
       |
       v
<Select showSearch filterOption={customFilter} options={groupedOptions}>
  - filterOption: match input against both option.label and group label
  - disabled options rendered but not selectable
```

## Error Handling Registry

| Method/Path | Possible Failure | Error Type | Handling | User Visible? |
|---|---|---|---|---|
| `ETriggerPoint::group()` | Unknown case (impossible with enum) | N/A | Exhaustive match | N/A |
| `Repository::get_trigger_points()` | Empty enum (impossible) | N/A | Returns empty array | Empty Select |
| `GET /trigger-points` API | PHP exception | 500 | WP REST default error handler | Generic error |
| `useTriggerPoints()` | API failure | Network error | Refine.dev error handling | Loading state |
| `useTriggerPoints()` | Malformed response | Runtime error | Fallback to empty options | Empty Select |

## Implementation Steps

### Phase 1: Backend -- ETriggerPoint Enum Enhancement

> Execute Agent: `@wp-workflows:wordpress-master`

1. **Add `group()` method to ETriggerPoint** (File: `inc/classes/Shared/Enums/ETriggerPoint.php`)
   - Action: Add `public function group(): string` method with a mapper array mapping each case to its group key (`registration`, `line_interaction`, `line_group`, `workflow`, `activity`, `user_behavior`, `woocommerce`)
   - Reason: Group key is the primary classification used by API and frontend
   - Depends: None
   - Risk: Low

2. **Add `group_label()` method to ETriggerPoint** (File: `inc/classes/Shared/Enums/ETriggerPoint.php`)
   - Action: Add `public function group_label(): string` method returning Chinese display names (`報名狀態`, `LINE 互動`, `LINE 群組`, `工作流引擎`, `活動時間`, `用戶行為`, `WooCommerce`)
   - Reason: Frontend needs display labels for OptGroup headers
   - Depends: None
   - Risk: Low

3. **Add `is_stub()` method to ETriggerPoint** (File: `inc/classes/Shared/Enums/ETriggerPoint.php`)
   - Action: Add `public function is_stub(): bool` returning true for `LINE_JOIN`, `LINE_LEAVE`, `LINE_MEMBER_JOINED`, `LINE_MEMBER_LEFT`, `ACTIVITY_ENDED`, `PROMO_LINK_CLICKED`
   - Reason: Frontend needs to know which items to render as disabled
   - Depends: None
   - Risk: Low

### Phase 2: Backend -- DTO & Repository

> Execute Agent: `@wp-workflows:wordpress-master`

4. **Add `disabled` property to TriggerPointDTO** (File: `inc/classes/Contracts/DTOs/TriggerPointDTO.php`)
   - Action: Add `public bool $disabled = false;` property
   - Reason: Each trigger point item needs a disabled flag for stubs
   - Depends: None
   - Risk: Low

5. **Create TriggerPointGroupDTO** (File: `inc/classes/Contracts/DTOs/TriggerPointGroupDTO.php` -- **NEW**)
   - Action: Create new DTO extending `DTO` with properties: `public string $group`, `public string $group_label`, `public array $items = []` (where items is `TriggerPointDTO[]`). Override `to_array()` to recursively serialize items.
   - Reason: API response structure requires a group wrapper
   - Depends: Step 4
   - Risk: Low

6. **Refactor Repository::get_trigger_points()** (File: `inc/classes/Infrastructure/Repositories/WorkflowRule/Repository.php`)
   - Action:
     a. Skip `REGISTRATION_CREATED` case in the loop
     b. For stubs, set `disabled = true` and append `（即將推出）` to name
     c. Group DTOs by `$enum->group()` key
     d. Wrap each group in `TriggerPointGroupDTO`
     e. Return `TriggerPointGroupDTO[]` ordered: registration, line_interaction, line_group, workflow, activity, user_behavior, woocommerce
   - Reason: API needs to return the new grouped structure
   - Depends: Steps 1-5
   - Risk: Medium -- this changes the API response format (but user confirmed no backward compatibility needed)
   - Note: The `apply_filters` hook signature changes from `array<string, TriggerPointDTO>` to `TriggerPointGroupDTO[]`. Check if any code hooks into `power_funnel/workflow_rule/trigger_points` and update accordingly.

7. **Update TriggerPointApi response** (File: `inc/classes/Applications/TriggerPointApi.php`)
   - Action: Update `get_trigger_points_callback()` to serialize the new grouped structure. Each `TriggerPointGroupDTO::to_array()` should produce `{ group, group_label, items: [{hook, name, disabled}] }`.
   - Reason: Match the new grouped API response format
   - Depends: Step 6
   - Risk: Low

### Phase 3: Frontend -- Types & Hook

> Execute Agent: `@wp-workflows:react-master`

8. **Add grouped trigger point types** (File: `js/src/pages/WorkflowRules/types/index.ts`)
   - Action: Add types:
     ```typescript
     export type TTriggerPointItem = {
       hook: string
       name: string
       disabled: boolean
     }
     export type TTriggerPointGroup = {
       group: string
       group_label: string
       items: TTriggerPointItem[]
     }
     ```
   - Reason: Type-safe handling of the new API response
   - Depends: None
   - Risk: Low

9. **Refactor useTriggerPoints hook** (File: `js/src/pages/WorkflowRules/hooks/useTriggerPoints.ts`)
   - Action:
     a. Update `TTriggerPointsResponse` to expect `data: TTriggerPointGroup[]`
     b. Build `groupedOptions` in Ant Design OptGroup format:
        ```typescript
        type TGroupedOption = {
          label: string      // group_label
          options: {
            label: string    // item name
            value: string    // item hook
            disabled: boolean
          }[]
        }
        ```
     c. Build `labelMap: Record<string, string>` (hook => name) for table column rendering
     d. Build `groupLabelMap: Record<string, string>` (hook => group_label) for search filtering
     e. Export `groupedOptions`, `labelMap`, `groupLabelMap`, `isLoading`
   - Reason: Transform API response into Ant Design-compatible OptGroup structure
   - Depends: Step 8
   - Risk: Medium -- key refactor point, must maintain backward compatibility with `labelMap` consumers

### Phase 4: Frontend -- Select Components

> Execute Agent: `@wp-workflows:react-master`

10. **Update WorkflowRules/Edit Select** (File: `js/src/pages/WorkflowRules/Edit/index.tsx`)
    - Action:
      a. Replace `options={triggerPointOptions}` with `options={groupedOptions}`
      b. Add `showSearch` prop
      c. Add `filterOption` prop with custom filter function:
         ```typescript
         filterOption={(input, option) => {
           const lower = input.toLowerCase()
           const labelMatch = (option?.label as string)?.toLowerCase().includes(lower)
           const groupLabel = groupLabelMap[option?.value as string] ?? ''
           const groupMatch = groupLabel.toLowerCase().includes(lower)
           return labelMatch || groupMatch
         }}
         ```
      d. Add `optionFilterProp="label"` as base filter prop
    - Reason: Enable grouped display and search
    - Depends: Step 9
    - Risk: Low

11. **Update Workflows/List filter Select** (File: `js/src/pages/Workflows/List/index.tsx`)
    - Action: Same changes as step 10 -- replace `options`, add `showSearch`, add `filterOption`, add `optionFilterProp`
    - Reason: Both Select components should have identical behavior
    - Depends: Step 9
    - Risk: Low

## Test Strategy

> This section is for tdd-coordinator to pass to test-creator.

### Integration Tests (PHP)
- Test `ETriggerPoint::group()` returns correct group key for each case
- Test `ETriggerPoint::group_label()` returns correct Chinese label for each case
- Test `ETriggerPoint::is_stub()` returns true only for the 6 known stubs
- Test `Repository::get_trigger_points()`:
  - Returns 7 groups in correct order
  - Does NOT include REGISTRATION_CREATED
  - Stubs have `disabled = true` and name ends with `（即將推出）`
  - Total items count = 20 (all cases minus REGISTRATION_CREATED)
- Test `TriggerPointGroupDTO::to_array()` produces correct nested structure
- Test commands: `composer test`

### E2E Tests (Playwright) -- optional, lower priority
- Navigate to WorkflowRule Edit page, verify OptGroup display
- Type "LINE" in search, verify only LINE-related options shown
- Type "email", verify empty state
- Select a trigger point, save, reload, verify value persists
- Verify disabled items are not selectable
- Test commands: `pnpm exec playwright test`

### Key Boundary Cases
- Empty search string shows all options
- Case-insensitive search: "line" matches "LINE"
- Disabled options visible but not clickable
- Saved trigger_point value correctly maps to display label on reload

## Dependencies

- No new external libraries needed
- All changes use existing Ant Design `Select` OptGroup API
- No new PHP composer packages

## Constraints

- This plan does NOT change the stored trigger_point value format (still a hook string like `pf/trigger/registration_approved`)
- This plan does NOT add/remove/modify any trigger points -- only changes how they are displayed and organized
- This plan does NOT touch the `apply_filters` hook's extensibility -- third-party code can still filter trigger points, but the filter signature changes from flat to grouped
- The `（即將推出）` suffix is hardcoded in Chinese, matching the existing label() convention

## Estimated Complexity: Low-Medium

7 files modified, 1 file created. No architectural changes, no new dependencies. Primary risk is ensuring the OptGroup + filterOption interaction works correctly in Ant Design.

## Success Criteria

- [ ] API `GET /trigger-points` returns grouped structure with 7 groups
- [ ] REGISTRATION_CREATED not present in API response
- [ ] Enum stubs show as disabled with "(coming soon)" suffix
- [ ] WorkflowRule Edit page shows OptGroup Select with search
- [ ] Workflows List page filter shows OptGroup Select with search
- [ ] Search matches both group name and option name (case-insensitive)
- [ ] Saved trigger_point values display correctly on page reload
- [ ] All existing trigger_point functionality unchanged (storing, triggering workflows)
- [ ] PHPStan Level 9 passes
- [ ] ESLint passes
