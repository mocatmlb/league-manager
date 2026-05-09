# Story 9.1: CutoverService Backend

**Status:** ready
**Epic:** 9 — Migration Cutover & Shared Credential Deprecation
**Story Key:** 9-1-cutover-service-backend

---

## Story

As a developer,
I want a `CutoverService` class that provides the pre-cutover gap checklist and the shared credential disable operation,
So that the admin cutover panel has a safe, tested API for the most consequential action in the system.

---

## Acceptance Criteria

**AC1: getGapChecklist() returns all active-season teams with gap status**
**Given** `CutoverService::getGapChecklist()` is called
**When** active-season teams exist
**Then** it returns an array of all active-season teams, each with: team name, division, program, list of assigned Team Owners (may be empty), and a boolean `has_gap`
**And** teams with zero assigned Team Owners have `has_gap = true`

**AC2: getGapCount() returns count of teams with no Team Owner**
**Given** `CutoverService::getGapCount()` is called
**Then** it returns the integer count of active-season teams with zero assigned Team Owners

**AC3: disableSharedCredential() succeeds when gap count is 0**
**Given** `CutoverService::disableSharedCredential(int $adminUserId)` is called
**When** `getGapCount()` returns 0
**Then** the `coaches_password` setting in the `settings` table is set to a disabled/null state so no auth path can use it
**And** `ActivityLogger` event `admin.shared_credential_disabled` is recorded with admin user ID and timestamp
**And** `true` is returned

**AC4: disableSharedCredential() throws when gaps remain**
**Given** `CutoverService::disableSharedCredential()` is called
**When** `getGapCount()` returns > 0
**Then** a `CutoverGapsRemainingException` is thrown and no change is made (FR-USERMGMT-9)

**AC5: isSharedCredentialActive() reflects current state**
**Given** `CutoverService::isSharedCredentialActive()` is called
**Then** it returns `true` if the shared credential is still enabled, `false` if disabled

**AC6: Unit tests pass**
**Given** the unit test suite is run
**When** this story is complete
**Then** `CutoverServiceTest.php` passes all cases including: gap checklist with and without gaps, gap count, disable success, disable throws when gaps remain, isSharedCredentialActive

---

## Tasks / Subtasks

- [ ] **Task 1: Implement `CutoverService` class**
  - [ ] Create `includes/CutoverService.php`
  - [ ] Implement `getGapChecklist(): array`
    - SELECT all active-season teams (join with divisions, programs)
    - LEFT JOIN `team_owners` to get owner list per team
    - Set `has_gap = (count of owners == 0)`
    - Return structured array per team
  - [ ] Implement `getGapCount(): int`
    - COUNT active-season teams WHERE no row in `team_owners` with matching `team_id`
    - Return integer
  - [ ] Implement `disableSharedCredential(int $adminUserId): bool`
    - Call `getGapCount()`; throw `CutoverGapsRemainingException` if > 0
    - UPDATE `settings` SET `value = NULL` (or empty/disabled sentinel) WHERE `key = 'coaches_password'`
    - Log `admin.shared_credential_disabled` with `['admin_user_id' => $adminUserId, 'disabled_at' => date('Y-m-d H:i:s')]`
    - Return `true`
  - [ ] Implement `isSharedCredentialActive(): bool`
    - SELECT value from `settings` WHERE `key = 'coaches_password'`
    - Return `true` if value is non-null and non-empty, `false` otherwise
  - [ ] Define `CutoverGapsRemainingException` (extends `RuntimeException`)

- [ ] **Task 2: Implement `CutoverServiceTest.php`**
  - [ ] Test: getGapChecklist returns teams with correct `has_gap` values
  - [ ] Test: getGapChecklist returns empty array when no active-season teams
  - [ ] Test: getGapCount returns 0 when all teams have owners
  - [ ] Test: getGapCount returns N when N teams have no owner
  - [ ] Test: disableSharedCredential succeeds and sets credential to disabled state when gap count = 0
  - [ ] Test: disableSharedCredential throws `CutoverGapsRemainingException` when gaps remain
  - [ ] Test: isSharedCredentialActive returns true when active, false when disabled

- [ ] **Task 3: Run full test suite**
  - [ ] All `CutoverServiceTest` tests pass
  - [ ] No regressions in existing tests

---

## Dev Notes

### Architecture Context
- Class in `includes/` — no namespace, define/check `D8TL_APP`
- Depends on `ActivityLogger` (Story 1.3)
- "Active-season teams" definition: teams with `status = 'active'` associated with a currently active season — align with how the existing codebase defines an active season

### Settings Table
- `settings` table key for shared password: check existing codebase for key name (likely `coaches_password` or `shared_coach_password`)
- "Disabled" state: set `value = NULL` or `value = ''` — ensure login code checks for null/empty

### FR-RESTRICTIONS-1/2/7 Note
- These restrictions are already enforced by `PermissionGuard::requireRole('administrator')` on all admin pages
- `CutoverService` does NOT add new permission checks for these — they are pre-existing guarantees
- Do not write redundant enforcement code in this story

### Active-Season Definition
- Check existing `seasons` table for an `is_active` or `status` column
- Active-season teams = teams assigned to a season where `seasons.status = 'active'` (or equivalent)

---

## Dev Agent Record

### Implementation Plan

### Debug Log

### Completion Notes

---

## File List

- `includes/CutoverService.php` — new
- `tests/unit/CutoverServiceTest.php` — new
- `_bmad-output/implementation-artifacts/9-1-cutover-service-backend.md` — new (this file)

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
