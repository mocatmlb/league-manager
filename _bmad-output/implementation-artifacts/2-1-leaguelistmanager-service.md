# Story 2.1: LeagueListManager Service

**Status:** done
**Epic:** 2 — Admin League List Management
**Story Key:** 2-1-leaguelistmanager-service

---

## Story

As a developer,
I want a `LeagueListManager` service class that encapsulates all CRUD operations on the `league_list` table,
So that admin page files have a clean, tested API for managing league entries without raw SQL.

---

## Acceptance Criteria

**AC1: getActiveList returns ordered active entries**
**Given** the `league_list` table exists (from migration 001)
**When** `LeagueListManager::getActiveList()` is called
**Then** it returns an array of active entries ordered by `sort_order` ascending
**And** deactivated entries (`is_active = 0`) are excluded

**AC2: create inserts with correct defaults**
**Given** a valid display name string
**When** `LeagueListManager::create(string $displayName)` is called
**Then** a new row is inserted with `is_active = 1` and `sort_order` set to `MAX(sort_order) + 1`
**And** the new entry's `id` is returned

**AC3: update modifies display_name**
**Given** an existing active league entry
**When** `LeagueListManager::update(int $id, string $displayName)` is called
**Then** the entry's `display_name` is updated and `updated_at` is refreshed
**And** calling with a non-existent `id` returns `false` without throwing

**AC4: deactivate sets is_active=0**
**Given** an existing active entry
**When** `LeagueListManager::deactivate(int $id)` is called
**Then** the entry's `is_active` is set to `0`
**And** the entry is excluded from `getActiveList()` results
**And** the entry remains in the database for historical reference

**AC5: reactivate restores entry**
**Given** a previously deactivated entry
**When** `LeagueListManager::reactivate(int $id)` is called
**Then** the entry's `is_active` is set to `1` and it appears at the bottom of `getActiveList()` results

**AC6: reorder updates sort_order**
**Given** an ordered array of entry IDs
**When** `LeagueListManager::reorder(array $orderedIds)` is called
**Then** each entry's `sort_order` is updated to match the position in the array (1-indexed)
**And** the change is reflected immediately in subsequent `getActiveList()` calls

**AC7: Unit tests pass**
**Given** the unit test suite is run
**When** this story is complete
**Then** `LeagueListManagerTest.php` passes all cases including empty list, create, update, deactivate, reactivate, and reorder

---

## Tasks / Subtasks

- [x] **Task 1: Implement `LeagueListManager` class**
  - [x] Create `includes/LeagueListManager.php` with static methods
  - [x] Implement `getActiveList(): array` — ordered by sort_order ASC, is_active=1 only
  - [x] Implement `getAll(): array` — all entries including deactivated (for admin display)
  - [x] Implement `create(string $displayName): int` — inserts with MAX(sort_order)+1
  - [x] Implement `update(int $id, string $displayName): bool` — updates name + updated_at
  - [x] Implement `deactivate(int $id): bool` — sets is_active=0
  - [x] Implement `reactivate(int $id): bool` — sets is_active=1
  - [x] Implement `reorder(array $orderedIds): void` — updates sort_order per position

- [x] **Task 2: Implement `LeagueListManagerTest.php`**
  - [x] Test: getActiveList returns only active entries in sort_order
  - [x] Test: getActiveList returns empty array when no active entries
  - [x] Test: create inserts with is_active=1 and correct sort_order
  - [x] Test: create returns the new entry's id
  - [x] Test: update changes display_name and returns true
  - [x] Test: update with non-existent id returns false
  - [x] Test: deactivate sets is_active=0
  - [x] Test: deactivated entry excluded from getActiveList
  - [x] Test: reactivate sets is_active=1
  - [x] Test: reorder updates sort_order correctly

- [x] **Task 3: Run full test suite and verify all pass**
  - [x] All LeagueListManagerTest tests pass (16/16)
  - [x] No regressions in existing tests (52/52 total pass)

### Review Findings

- [x] [Review][Patch] Reorder payload is not validated for completeness/uniqueness and active-scope constraints [includes/LeagueListManager.php]
- [x] [Review][Patch] Reorder updates are not atomic and can partially apply on failure [includes/LeagueListManager.php]
- [x] [Review][Patch] Update/deactivate/reactivate can misreport "not found" on no-op state changes [includes/LeagueListManager.php]
- [x] [Review][Patch] AC5 test does not explicitly assert reactivated entry appears at bottom of active list [tests/unit/LeagueListManagerTest.php]

---

## Dev Notes

### Architecture Context
- Class goes in `includes/` as PascalCase PHP class file — `LeagueListManager.php`
- No namespace required (legacy mixed-style in `includes/`)
- Must define or check `D8TL_APP` guard at top
- Uses `Database::getInstance()` with `fetchAll()`, `fetchOne()`, `query()` methods
- `Database::setInstance()` exists for test injection

### LeagueListManager Methods
- `getActiveList()`: `SELECT * FROM league_list WHERE is_active = 1 ORDER BY sort_order ASC`
- `getAll()`: `SELECT * FROM league_list ORDER BY is_active DESC, sort_order ASC` (active first, then deactivated)
- `create($displayName)`: INSERT with `sort_order = COALESCE(MAX(sort_order), 0) + 1`, `is_active = 1`
- `update($id, $displayName)`: UPDATE `display_name`, `updated_at = NOW()` WHERE id = :id
- `deactivate($id)`: UPDATE `is_active = 0`, `updated_at = NOW()` WHERE id = :id
- `reactivate($id)`: UPDATE `is_active = 1`, `updated_at = NOW()` WHERE id = :id; sets sort_order to MAX+1 so it appears at bottom
- `reorder($orderedIds)`: Loop with 1-indexed position, UPDATE sort_order per id

### Test Pattern
- Use MockDB extending Database with tracked queries (same pattern as ActivityLoggerTest)
- Mock must support `query()`, `fetchAll()`, `fetchOne()` for assertions
- Reset Database instance after each test

---

## Dev Agent Record

### Implementation Plan
Implement static-method PHP class `LeagueListManager` in `includes/`. Unit tests use mock DB injected via `Database::setInstance()`. All six CRUD methods implemented and tested.

### Debug Log

### Completion Notes
- **16/16 unit tests pass** (0 failures, 0 skipped)
- **52/52 total suite tests pass** — zero regressions
- `LeagueListManager.php` implemented with all 7 methods: `getActiveList`, `getAll`, `create`, `update`, `deactivate`, `reactivate`, `reorder`
- `test-helpers.php` updated to include canonical assert helper definitions (guards added to `MigrationRunnerTest.php` to prevent redeclaration)

---

## File List

- `includes/LeagueListManager.php` — new
- `tests/unit/LeagueListManagerTest.php` — new
- `_bmad-output/implementation-artifacts/2-1-leaguelistmanager-service.md` — new (this file)

---

## Change Log
- 2026-05-05: Story file created, status set to in-progress.
- 2026-05-05: Implementation complete. 16/16 tests pass. Status set to review.
