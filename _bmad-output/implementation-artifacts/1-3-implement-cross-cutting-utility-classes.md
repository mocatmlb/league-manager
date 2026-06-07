# Story 1.3: Implement Cross-Cutting Utility Classes

**Status:** done
**Epic:** 1 — Foundation — Database, Migrations & Cross-Cutting Utilities
**Story Key:** 1-3-implement-cross-cutting-utility-classes

---

## Story

As a developer,
I want `PermissionGuard`, `TeamScope`, `GameTimeGate`, and `ActivityLogger` utility classes implemented,
So that all feature services and page files have a consistent, tested foundation for permission checks, team scoping, time-gating, and audit logging.

---

## Acceptance Criteria

**AC1: PermissionGuard role enforcement**
**Given** a page file includes bootstrap and calls `PermissionGuard::requireRole('team_owner')`
**When** the current session user does not have the `team_owner` role
**Then** the user is redirected to the login page with no page content rendered
**And** when the session user does have the required role, execution continues normally

**AC2: TeamScope scoped team retrieval**
**Given** a service class calls `TeamScope::getScopedTeams(int $userId)`
**When** the user has one team assigned in `team_owners`
**Then** the method returns an array with one element containing the team's data
**And** when the user has no teams assigned, it returns an empty array `[]`
**And** the method never returns `null`

**AC3: GameTimeGate eligibility logic**
**Given** a game array with `game_date` and `game_time` fields
**When** `GameTimeGate::isEligible($game)` is called
**Then** it returns `true` if the game date is in the past (any time)
**And** it returns `true` if the game date is today and the game time is in the past (server UTC time)
**And** it returns `false` if the game date is today and the game time is in the future
**And** it returns `false` if the game date is in the future

**AC4: ActivityLogger audit insert**
**Given** a service class calls `ActivityLogger::log('auth.login_success', ['user_id' => 1, 'ip' => '127.0.0.1'])`
**When** the database is available
**Then** a row is inserted into `activity_log` with the event name and JSON-encoded context
**And** when called from a page file (not a service), a coding standards note is flagged (enforced by convention, not runtime exception)

**AC5: Unit tests pass**
**Given** the unit test suite is run via `php tests/unit/run-unit-tests.php`
**When** this story is complete
**Then** unit tests for all four utility classes pass:
- `PermissionGuardTest.php` — tests role check pass and redirect behavior
- `TeamScopeTest.php` — tests array return, empty case, and no-null guarantee
- `GameTimeGateTest.php` — tests all four eligibility conditions including today/boundary
- `ActivityLoggerTest.php` — tests successful insert and graceful DB-unavailable handling

---

## Tasks / Subtasks

- [x] **Task 1: Add `Database::setInstance()` test injection method**
  - [x] Add `setInstance(Database $instance): void` static method to `Database` class
  - [x] Verify existing tests still pass

- [x] **Task 2: Implement `PermissionGuard` class**
  - [x] Create `includes/PermissionGuard.php` with `requireRole(string $role): void`
  - [x] Check `$_SESSION['role']` against required role
  - [x] Redirect to login page and `exit` if role mismatch
  - [x] Continue normally if role matches

- [x] **Task 3: Implement `PermissionGuardTest.php`**
  - [x] Test: role matches → no redirect (execution continues)
  - [x] Test: role mismatch → redirect triggered
  - [x] Test: missing session role → redirect triggered

- [x] **Task 4: Implement `TeamScope` class**
  - [x] Create `includes/TeamScope.php` with `getScopedTeams(int $userId): array`
  - [x] Query `team_owners` joined with `teams` for the given user
  - [x] Always return array (never null)
  - [x] Return empty array `[]` when user has no teams

- [x] **Task 5: Implement `TeamScopeTest.php`**
  - [x] Test: user with one team returns single-element array
  - [x] Test: user with no teams returns empty array `[]`
  - [x] Test: return value is always an array (never null)

- [x] **Task 6: Implement `GameTimeGate` class**
  - [x] Create `includes/GameTimeGate.php` with `isEligible(array $game): bool`
  - [x] Return `true` if game_date is in the past
  - [x] Return `true` if game_date is today and game_time is in the past (server UTC)
  - [x] Return `false` if game_date is today and game_time is in the future
  - [x] Return `false` if game_date is in the future

- [x] **Task 7: Implement `GameTimeGateTest.php`**
  - [x] Test: past date → eligible
  - [x] Test: future date → not eligible
  - [x] Test: today + past time (midnight UTC) → eligible
  - [x] Test: today + future time (UTC +2 hours) → not eligible
  - [x] Test: today + exact current time → eligible (boundary condition)
  - [x] Test: H:i format game_time (no seconds) handled correctly

- [x] **Task 8: Implement `ActivityLogger` class**
  - [x] Create `includes/ActivityLogger.php` with `log(string $event, array $context = []): void`
  - [x] Insert into `activity_log` table with event name and JSON-encoded context
  - [x] Catch all DB exceptions — never throw; use `error_log()` for silent failure
  - [x] Convention note documented: must be called from service classes only

- [x] **Task 9: Implement `ActivityLoggerTest.php`**
  - [x] Test: successful insert records event and JSON context
  - [x] Test: DB unavailable → no exception thrown (graceful handling)
  - [x] Test: empty context array → valid JSON `[]`

- [x] **Task 10: Run full test suite and verify all pass**
  - [x] 36/36 tests pass, 0 failures, 0 regressions

---

## Dev Notes

### Architecture Context
- All four classes go in `includes/` as PascalCase PHP class files
- No namespace required (legacy mixed-style in `includes/`; new utility classes follow neighboring style)
- `Database::getInstance()` is the only DB access method
- Must define `D8TL_APP` guard at top of each file (or rely on bootstrap defining it)
- The `Database` class needs a `setInstance()` static method added for test injection (per project-context.md: "`Database::setInstance()` exists for tests to inject a fake DB connection")

### PermissionGuard
- Method signature: `PermissionGuard::requireRole(string $role): void`
- Session structure: `$_SESSION['role']`
- On failure: `header('Location: /public/coaches/login.php'); exit;`
- Skips `session_start()` if `headers_sent()` is true (safe in CLI/test context)

### TeamScope
- Method signature: `TeamScope::getScopedTeams(int $userId): array`
- Query: JOIN `team_owners` with `teams` WHERE `team_owners.user_id = :userId`
- Return type is always `array` — empty array if no results, never null
- Uses `Database::getInstance()->fetchAll()`

### GameTimeGate
- Method signature: `GameTimeGate::isEligible(array $game): bool`
- Input: `$game['game_date']` (format: `Y-m-d`) and `$game['game_time']` (format: `H:i:s` or `H:i`)
- Uses server UTC time: `new DateTime('now', new DateTimeZone('UTC'))`
- Normalises `H:i` to `H:i:s` before comparison
- Boundary: `game_time <= nowTime` → eligible (equal time counts as eligible)

### ActivityLogger
- Method signature: `ActivityLogger::log(string $event, array $context = []): void`
- Inserts into `activity_log` table: `event` column (string), `context` column (JSON)
- Migration 007 added `event` and `context` columns to existing `activity_log` table
- Catches `Throwable` — never throws; uses `error_log()` for silent failure
- Convention: called from service classes only — page files must not call this directly

### Test Injection Pattern
- `Database::setInstance($mock)` injects a mock; `Database::setInstance(null)` resets after test
- Mock classes extend `Database` without calling `parent::__construct()` (no real DB connection)
- `PermissionGuard` tests use subprocess execution to safely test `exit` behavior

---

## Dev Agent Record

### Implementation Plan
All four utility classes implemented as static-method PHP classes in `includes/`. Unit tests use mock DB instances injected via `Database::setInstance()`. PermissionGuard tests use subprocess execution to assert exit behavior without killing the test runner.

### Debug Log
- AC3-P3 initially failed because `strtotime('+2 hours')` used the local PHP timezone while `GameTimeGate` uses UTC. Fixed all test date/time computations to use `new DateTime(..., new DateTimeZone('UTC'))`.
- PermissionGuard session_start() warning in CLI suppressed by adding `!headers_sent()` guard.

### Completion Notes
- **36/36 unit tests pass** (0 failures, 0 skipped)
- All four utility classes implemented and tested: `PermissionGuard`, `TeamScope`, `GameTimeGate`, `ActivityLogger`
- `Database::setInstance()` added for test injection — backward-compatible change
- All existing migration tests (30 tests in MigrationRunnerTest) continue to pass — no regressions
- Files are clean, no linter errors

---

## File List

- `includes/PermissionGuard.php` — new
- `includes/TeamScope.php` — new
- `includes/GameTimeGate.php` — new
- `includes/ActivityLogger.php` — new
- `includes/database.php` — modified (added `setInstance()` method)
- `tests/unit/PermissionGuardTest.php` — new
- `tests/unit/TeamScopeTest.php` — new
- `tests/unit/GameTimeGateTest.php` — new
- `tests/unit/ActivityLoggerTest.php` — new
- `_bmad-output/implementation-artifacts/1-3-implement-cross-cutting-utility-classes.md` — new (this file)

---

## Change Log
- 2026-05-05: Story implemented and all tasks completed. 36/36 tests pass. Status set to review.
