# Story 25.3: Standalone Availability Query

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As an umpire assignor,
I want to query the availability pool for any upcoming game date/time from a dedicated page,
so that I can plan assignments across multiple games without opening each game drawer individually.

## Acceptance Criteria

1. Given I am an assignor or admin on the standalone availability query page, when I enter a date and time and submit, then the page displays all active umpires in the availability pool for that date/time: umpires with a covering availability window and no conflicting assignment.
2. Given I query a date/time with no available umpires, when the results load, then the page displays a clear empty-state message.
3. Given a coach, team_owner, umpire, or unauthenticated user, when they attempt to access the availability query page, then they are denied access or redirected to login.

## Tasks / Subtasks

- [x] Confirm prerequisite availability foundation before implementation (AC: 1, 2)
  - [x] Verify the current branch still contains Story 25.1's `database/migrations/051_create_umpire_availability_windows.sql`, `includes/UmpireAvailabilityService.php`, and `umpire_availability_windows` schema before starting.
  - [x] Verify `UmpireAvailabilityService::getAvailableUmpireIdsForWindow(DateTimeInterface $startsAt, DateTimeInterface $endsAt): array` is present and remains stable for Story 25.2's assignment drawer.
  - [x] Do not create or alter the availability table in this story. If the 25.1 service/table is missing, stop and restore the prerequisite work rather than inventing a second schema.
- [x] Add reusable availability-pool query output (AC: 1, 2)
  - [x] Extend `includes/UmpireAvailabilityService.php` with a display-record method such as `getAvailabilityPoolForWindow(DateTimeInterface $startsAt, DateTimeInterface $endsAt): array`; keep the existing ID-only method unchanged for the drawer.
  - [x] Require a covering availability window with `starts_at <= :target_start` and `ends_at >= :target_end`.
  - [x] Exclude umpires with overlapping `game_umpire_assignments` in `Draft` or `Published` status where the linked game is not `Cancelled` or `Postponed`.
  - [x] Continue using `TIMESTAMP(s.game_date, COALESCE(s.game_time, '00:00:00'))` for D8 assignment start times; do not reference nonexistent `game_date_time` or `game_umpire_assignments.status` columns.
  - [x] Use the existing D8 game assignment source only. Third-party game conflicts are Story 25.4/25.5 scope and must not be invented here.
  - [x] Return display-ready fields already used by the assignment UI where available: `user_id`, first/last name, email, phone, `umpire_level`, `is_under_18`, and current D8 assignment load.
  - [x] Use one aggregate query for current load grouped by `umpire_user_id`; do not add per-umpire load queries.
- [x] Create the standalone assignor/admin page (AC: 1, 2, 3)
  - [x] Create `public/admin/umpires/availability.php` following the bootstrap pattern in `public/admin/umpires/index.php`, `board.php`, and `settings.php`.
  - [x] Protect the page with `PermissionGuard::requireRole('umpire_assignor', '/login.php')` or `PermissionGuard::requireRole(['admin', 'umpire_assignor'], '/login.php')`; both permit administrators because `PermissionGuard` maps `umpire_assignor` to `['umpire_assignor', 'administrator']`.
  - [x] Accept query input as date plus time, preferably through `GET` so the result URL is shareable; no mutation occurs, so CSRF is not required.
  - [x] Convert the submitted date/time into a local league `DateTimeImmutable` start and compute the end using the same assignment-window duration as `UmpireConflictChecker::assignmentWindowSeconds()`.
  - [x] Render the assumed window in the result summary so the assignor understands what overlap window was queried.
  - [x] Validate missing/invalid date/time with a clear inline error and do not run the pool query until inputs are valid.
  - [x] Render results as a scan-friendly Bootstrap table on desktop and responsive layout on narrow screens, showing name, level, under-18 badge, current load, phone, and email.
  - [x] Render a clear empty state when no available umpires match the requested date/time.
- [x] Add navigation and discoverability (AC: 1, 3)
  - [x] Update the admin Manage > Umpires section in `includes/nav.php` with an `Availability Query` link to `admin/umpires/availability.php`.
  - [x] Update the `umpire_assignor` Umpire Tools dropdown in `includes/nav.php` with the same link.
  - [x] Ensure `isActiveNav('availability', 'umpires')` marks the link active without breaking existing `Assignment Queue`, `Assignment Board`, `Umpire Settings`, `Umpire Roster`, or `Import Umpires` links.
- [x] Preserve adjacent-story boundaries (AC: 1, 2)
  - [x] Do not change the assignment drawer default roster/pool behavior in `public/admin/umpires/assignment-drawer.js`; Story 25.2 owns drawer defaulting and the full-roster toggle.
  - [x] Do not add or mutate umpire availability windows from this page; Story 25.1 owns umpire self-service availability entry and Story 25.6 owns assignor/admin manual availability management at `public/admin/umpires/availability-management.php`.
  - [x] Do not add third-party game creation, third-party assignment visibility, or conflict expansion; Stories 25.4 and 25.5 own that scope.
- [x] Add targeted tests and verification (AC: 1, 2, 3)
  - [x] Add or extend `tests/unit/UmpireAvailabilityServiceTest.php` for the standalone pool method: covering-window inclusion, no-window exclusion, invalid window rejection, Draft/Published conflict exclusion, Cancelled/Postponed game non-exclusion, and aggregate load query shape.
  - [x] Update the existing 25.6 reserved-route assertion in `tests/unit/UmpireAvailabilityServiceTest.php`: after this story creates `public/admin/umpires/availability.php`, the test must assert the query route exists and does not replace `availability-management.php`.
  - [x] Add a lightweight route/nav assertion if practical, or extend existing nav tests to verify `Availability Query` is discoverable for admins and assignors while `Manage Availability` remains discoverable separately.
  - [x] Run `php -l includes/UmpireAvailabilityService.php`, `php -l public/admin/umpires/availability.php`, and `php tests/unit/run-unit-tests.php --file=UmpireAvailabilityServiceTest.php`.
  - [x] If browser verification is available, smoke the page as admin or assignor: valid query with results, valid query empty state, invalid date/time, and non-assignor access rejection.

  ### Review Findings

  - [x] [Review][Patch] Move `normalizeAllDayFromDate` to `UmpireAvailabilityService` to eliminate duplication across pages.
  - [x] [Review][Patch] Update time validation regex in `availability.php` to handle optional seconds and trim whitespace.
  - [x] [Review][Patch] Add defensive check for `modify()` return value in `availability.php` to prevent fatal errors.
  - [x] [Review][Patch] Explicitly use `['admin', 'umpire_assignor']` in `PermissionGuard::requireRole` to match AC 3 exactly.
  - [x] [Review][Patch] Include the window duration (e.g., "75 minutes") in the query result summary as per Task 38.
  - [x] [Review][Defer] Interpolated `$windowSeconds` in SQL — Pre-existing pattern in `UmpireAvailabilityService`, but should eventually use parameters if the value becomes user-controlled. [includes/UmpireAvailabilityService.php:453] — deferred, pre-existing

## Dev Notes

Epic 25 is the P1 availability and third-party events track. Story 25.3 is not the availability-entry page and not the assignment-drawer pool switch. It is the read-only planning page for assignors/admins to query the availability pool without opening game drawers. [Source: _bmad-output/planning-artifacts/epics-umpire-assignment.md#Story-253-Standalone-Availability-Query]

### Current System Context

- Current sprint status has `25-1-umpire-availability-entry`, `25-2-availability-pool-in-assignment-drawer`, `25-6-manual-umpire-availability-management`, and `25-7-visual-calendar-availability-entry` done; this story remains `ready-for-dev`.
- The live repo now contains `database/migrations/051_create_umpire_availability_windows.sql`, `includes/UmpireAvailabilityService.php`, `public/umpires/availability.php`, `public/admin/umpires/availability-management.php`, and `tests/unit/UmpireAvailabilityServiceTest.php`. Treat those as current contracts.
- `UmpireAvailabilityService` already exposes `listForUmpire()`, `createWindow()`, `createWindowsForDates()`, `updateWindow()`, `deleteWindow()`, and `getAvailableUmpireIdsForWindow()`. Story 25.3 should add the display-record query needed by the standalone page without destabilizing the ID-only drawer method.
- Admin/assignor umpire pages live under `public/admin/umpires/` and use `define('D8TL_APP', true)`, `includes/env-loader.php`, `includes/bootstrap.php`, `includes/PermissionGuard.php`, then a `PermissionGuard` role gate. `PermissionGuard::requireRole('umpire_assignor', '/login.php')` permits both assignors and administrators because administrators satisfy the `umpire_assignor` requirement.
- `includes/nav.php` already contains three relevant umpire surfaces: admin Manage > Umpires, assignor-only Umpire Tools, and umpire My Portal. Add `Availability Query` only to the admin/assignor surfaces; do not add it to the umpire portal.
- `public/admin/umpires/availability-management.php` is Story 25.6's manual edit page. Do not reuse or rename it for the read-only query page.

### Availability Pool Semantics

- PRD FR-27 defines the pool as active umpires with at least one availability window covering the requested date/time and no non-Cancelled overlapping assignment. For this page, compute a query window from the submitted start date/time to `start + UmpireConflictChecker::assignmentWindowSeconds()`.
- A covering window must satisfy `starts_at <= target_start` and `ends_at >= target_end`.
- Assignment overlap should use the same half-open overlap convention used elsewhere: existing start `< target_end` and existing end `> target_start`.
- Existing D8 assignments come from `game_umpire_assignments` joined through `games` and `schedules`. Treat `Draft` and `Published` as conflicts. Ignore assignments tied to `Cancelled` or `Postponed` games.
- Story 25.2 fixed the live pool SQL to use `TIMESTAMP(s.game_date, COALESCE(s.game_time, '00:00:00'))`, `gua.assignment_status`, and `UmpireConflictChecker::assignmentWindowSeconds()`. Preserve those exact contracts.
- Do not delete, split, or rewrite availability windows from assignment or query flows. FR-28 requires pool membership to recalculate dynamically from current availability plus assignments. [Source: _bmad-output/planning-artifacts/prd-umpire-assignment.md#Availability-Stretch-P1]

### Service Guardrails

- Prefer extending `UmpireAvailabilityService` from Story 25.1 instead of adding availability SQL to the page or to `UmpireAssignmentService`.
- Keep `getAvailableUmpireIdsForWindow()` returning `int[]`; `UmpireAssignmentService::computeAvailabilityPoolIds()` and `assignment-drawer.js` depend on it for `in_pool`, `availability_pool_count`, and the "Available" picker mode.
- Add a second method or private shared SQL builder for display rows rather than changing the return shape of the existing ID method.
- Keep identity keyed by `users.id`, matching `game_umpire_assignments.umpire_user_id`, `umpire_profiles.user_id`, and `UmpireRosterService` output.
- Use `Database::getInstance()` and prepared statements through the repo `Database` wrapper. Do not introduce MySQLi or raw interpolated SQL.
- Reuse `UmpireConflictChecker::assignmentWindowSeconds()` for the assumed event duration so this page and assignment conflict behavior agree on the scheduling window.
- Keep third-party games out of the SQL until the third-party stories add those tables and expand `UmpireConflictChecker`.

### UI / UX Guardrails

- This is an operational admin tool, not a landing page. The first screen should be a compact query form and, after submission, the result set or empty state.
- Use Bootstrap 5 classes and the existing admin page structure. Avoid a large decorative hero or nested card-heavy layout.
- For 100+ umpires, keep results scannable. A table is acceptable because this page is a planning query, but preserve mobile readability with a responsive table or stacked rows.
- Render all untrusted values with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Results should explain the queried date/time and assumed end time/window. Otherwise an assignor may misread why an umpire is excluded.

### Security / Access

- Only `administrator`/admin and `umpire_assignor` should access this page. Coaches, team owners, regular umpires, and unauthenticated users must be denied or redirected.
- The query is read-only; use `GET` unless there is a strong local reason for `POST`. Do not add CSRF ceremony to a read-only query.
- Validate date and time server-side with strict parsing. Reject invalid calendar dates, missing time, and dates that cannot be parsed into a `DateTimeImmutable`.
- Avoid exposing notes from availability windows in the query results unless product explicitly asks for them. The assignor needs who is available, not free-form personal notes.

### Previous Story Intelligence

- Story 25.1 already defines the availability schema/service contract: `umpire_availability_windows`, `includes/UmpireAvailabilityService.php`, local `DATETIME` values, dynamic pool recalculation, and no mutation from assignment flows.
- Story 25.2 consumed `UmpireAvailabilityService::getAvailableUmpireIdsForWindow()` in `UmpireAssignmentService::computeAvailabilityPoolIds()` and the drawer now treats backend failures as full-roster fallback with an error flag. Do not regress that behavior.
- Story 25.6 reserved `public/admin/umpires/availability.php` for this story and implemented manual admin/assignor changes at `public/admin/umpires/availability-management.php`. Existing tests still assert that the query route does not exist; update that assertion as part of 25.3.
- Story 25.7 added calendar-first availability entry on the umpire portal and kept `getAvailableUmpireIdsForWindow()` stable. Keep 25.3 read-only and do not pull calendar editing behavior into this page.
- Story 23.2/23.6 large-roster work established that umpire selection views must reduce scan burden and avoid dumping huge rosters in repeated picker contexts. For this standalone planning page, use filtering/search or compact responsive presentation if the result set can be large, but do not modify the drawer.
- Prior Epic 23 memory confirms availability belongs to Epic 25 and must not be retrofitted into completed assignment drawer baseline scope.

### Testing Standards

- Unit tests should follow the existing lightweight runner and fake database pattern in `tests/unit/UmpireAssignmentServiceTest.php`.
- Prefer SQL-shape tests for prepared parameters, conflict-exclusion joins, grouped load calculation, preserved `int[]` method behavior, and display-row method behavior where the fake DB cannot simulate MySQL behavior.
- Existing `tests/unit/UmpireAvailabilityServiceTest.php` includes Story 25.6/25.7 assertions. Update only the assertions made stale by creating the 25.3 query page; keep the management-page and calendar-entry regression checks intact.
- Syntax-check every changed PHP file with `php -l`.
- If a full suite is run and unrelated baseline failures remain, document them separately from targeted Story 25.3 results.

### Project Structure Notes

- New files expected:
  - `public/admin/umpires/availability.php`
- Existing files expected to update:
  - `includes/UmpireAvailabilityService.php`
  - `includes/nav.php`
  - `tests/unit/UmpireAvailabilityServiceTest.php`
- Existing files to avoid changing in this story:
  - `public/admin/umpires/assignment-drawer.js`
  - `public/admin/umpires/ajax/get-drawer.php`
  - `includes/UmpireAssignmentService.php`, unless only a shared helper call is unavoidable; avoid changing drawer payload shape
  - `public/admin/umpires/availability-management.php`
  - `public/umpires/availability.php`
  - third-party game or portal assignment files

### Recent Git Intelligence

Recent commits on `staging` include `d3c4b17`/`3292bae` Story 25.1 fixes, `c46c8b8`/`48bc703` Story 25.2 implementation and review fixes, `217d561` Story 25.7 visual calendar entry, `0ea0500` Story 25.6 manual availability management, and `3d450a5` calendar-driven management refinements. The current implementation pattern is story-scoped service/page/test changes with targeted availability unit coverage and browser smoke only when UI behavior changes.

### References

- _bmad-output/project-context.md#Technology-Stack--Versions
- _bmad-output/project-context.md#Critical-Implementation-Rules
- _bmad-output/planning-artifacts/epics-umpire-assignment.md#Story-253-Standalone-Availability-Query
- _bmad-output/planning-artifacts/prd-umpire-assignment.md#Journey-7-Umpire-Enters-and-Manages-Availability
- _bmad-output/planning-artifacts/prd-umpire-assignment.md#Availability-Stretch-P1
- _bmad-output/planning-artifacts/architecture-umpire-assignment.md#Data-Architecture
- _bmad-output/planning-artifacts/ux-umpire-assignment-large-roster.md#Required-Guardrail
- _bmad-output/implementation-artifacts/25-1-umpire-availability-entry.md
- _bmad-output/implementation-artifacts/25-2-availability-pool-in-assignment-drawer.md
- _bmad-output/implementation-artifacts/25-6-manual-umpire-availability-management.md
- _bmad-output/implementation-artifacts/25-7-visual-calendar-availability-entry.md
- includes/UmpireConflictChecker.php
- includes/UmpireAvailabilityService.php
- includes/UmpireAssignmentService.php
- includes/nav.php
- public/admin/umpires/index.php
- public/admin/umpires/board.php
- public/admin/umpires/settings.php
- public/admin/umpires/availability-management.php
- tests/unit/UmpireAssignmentServiceTest.php
- tests/unit/UmpireAvailabilityServiceTest.php

## Dev Agent Record

### Agent Model Used

{{agent_model_name_version}}

### Debug Log References

- Red phase: `php tests/unit/run-unit-tests.php --file=UmpireAvailabilityServiceTest.php` failed as expected with missing `getAvailabilityPoolForWindow()` and missing `public/admin/umpires/availability.php`.
- Green verification: `php -l includes/UmpireAvailabilityService.php`; `php -l public/admin/umpires/availability.php`; `php -l includes/nav.php`; `php -l tests/unit/UmpireAvailabilityServiceTest.php`.
- Targeted tests: `php tests/unit/run-unit-tests.php --file=UmpireAvailabilityServiceTest.php` passed 19/19; `php tests/unit/run-unit-tests.php --file=UmpireConflictCheckerTest.php` passed 7/7.
- Adjacent regression: `php tests/unit/run-unit-tests.php --file=UmpireAssignmentServiceTest.php` passed 110/111 with existing unrelated 24.2 migration-template failure.
- Full regression: `php tests/unit/run-unit-tests.php` passed 411/449 with 38 existing unrelated baseline failures in Cutover/Profile/Registration/Reschedule/Score/24.2/23.7 areas.
- Route smoke: local PHP server GET to `/admin/umpires/availability.php?date=2026-07-01&time=10:00` returned `302 Found` to `/login.php` for unauthenticated access.

### Completion Notes List

- Ultimate context engine analysis completed - comprehensive developer guide created.
- 2026-06-30 validation refreshed stale story context against completed Stories 25.1, 25.2, 25.6, and 25.7; preserved 25.3 as ready-for-dev.
- Implemented `UmpireAvailabilityService::getAvailabilityPoolForWindow()` for display-ready active umpire rows while preserving the existing `getAvailableUmpireIdsForWindow()` `int[]` drawer contract.
- Added read-only `public/admin/umpires/availability.php` with GET date/time query, strict server-side parsing, assignment-window summary, available-umpire table, and empty-state handling.
- Added admin and assignor navigation links for `Availability Query` while preserving the separate `Manage Availability` manual-edit page.
- Updated availability unit coverage for the display query SQL contract, route/nav discoverability, reserved-route transition, and no-CSRF read-only behavior.

### File List

- includes/UmpireAvailabilityService.php
- includes/nav.php
- public/admin/umpires/availability.php
- tests/unit/UmpireAvailabilityServiceTest.php
- _bmad-output/implementation-artifacts/sprint-status.yaml
- _bmad-output/implementation-artifacts/25-3-standalone-availability-query.md

### Change Log

- 2026-06-30: Implemented Story 25.3 standalone availability query; story moved to review.
