# Story 9.1: CutoverService Backend

**Status:** done
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

- [x] **Task 1: Implement `CutoverService` class**
  - [x] Create `includes/CutoverService.php`
  - [x] Implement `getGapChecklist(): array`
    - SELECT all active-season teams (join with divisions, programs)
    - LEFT JOIN `team_owners` to get owner list per team
    - Set `has_gap = (count of owners == 0)`
    - Return structured array per team
  - [x] Implement `getGapCount(): int`
    - COUNT active-season teams WHERE no row in `team_owners` with matching `team_id`
    - Return integer
  - [x] Implement `disableSharedCredential(int $adminUserId): bool`
    - Call `getGapCount()`; throw `CutoverGapsRemainingException` if > 0
    - UPDATE `settings` SET `value = NULL` (or empty/disabled sentinel) WHERE `key = 'coaches_password'`
    - Log `admin.shared_credential_disabled` with `['admin_user_id' => $adminUserId, 'disabled_at' => date('Y-m-d H:i:s')]`
    - Return `true`
  - [x] Implement `isSharedCredentialActive(): bool`
    - SELECT value from `settings` WHERE `key = 'coaches_password'`
    - Return `true` if value is non-null and non-empty, `false` otherwise
  - [x] Define `CutoverGapsRemainingException` (extends `RuntimeException`)

- [x] **Task 2: Implement `CutoverServiceTest.php`**
  - [x] Test: getGapChecklist returns teams with correct `has_gap` values
  - [x] Test: getGapChecklist returns empty array when no active-season teams
  - [x] Test: getGapCount returns 0 when all teams have owners
  - [x] Test: getGapCount returns N when N teams have no owner
  - [x] Test: disableSharedCredential succeeds and sets credential to disabled state when gap count = 0
  - [x] Test: disableSharedCredential throws `CutoverGapsRemainingException` when gaps remain
  - [x] Test: isSharedCredentialActive returns true when active, false when disabled

- [x] **Task 3: Run full test suite**
  - [x] All `CutoverServiceTest` tests pass
  - [x] No regressions in existing tests

  ### Review Findings

  1. **decision-needed** findings:
     - [x] [Review][Decision] SQL construction in `getGapChecklist` — The query uses string interpolation for placeholders: `{$placeholders}`. While `$teamIds` comes from `array_column` of a DB result, it is still technically dynamic SQL construction. If `team_id` was ever influenced by external input, this could be an injection vector. Should we switch to a more robust query builder or leave as-is given the source?
     - [x] [Review][Decision] Timezone Inconsistency — `date("Y-m-d H:i:s")` uses the server's local time. If the server and admin are in different timezones, the log timestamp might be confusing. Should we standardize to UTC for the activity log?
     - [x] [Review][Decision] Exception Metadata — `CutoverGapsRemainingException` is a bare extension of `RuntimeException`. Should it provide details about which teams are missing owners to help the UI?

  2. **patch** findings:
     - [x] [Review][Patch] SQL error in IN () clause due to empty placeholders string [includes/CutoverService.php:67-76]
     - [x] [Review][Patch] team_owners.user_id references a non-existent user id (Silent drop) [includes/CutoverService.php:73]
     - [x] [Review][Patch] Missing Transaction for disable operation [includes/CutoverService.php:132]
     - [x] [Review][Patch] Invalid adminUserId in Audit Log [includes/CutoverService.php:132]
     - [x] [Review][Patch] Potential exclusion of teams with no program_id [includes/CutoverService.php:50-60]
     - [x] [Review][Patch] Ambiguity in `isSharedCredentialActive()` for missing setting row [includes/CutoverService.php:155]

  3. **defer** findings:
     - [x] [Review][Defer] Hardcoded setting key string [includes/CutoverService.php:140] — deferred, pre-existing
     - [x] [Review][Defer] Fallback to Database::getInstance() in constructor [includes/CutoverService.php:33] — deferred, pre-existing

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

### Deferred Work — Folded into This Story
The following items were deferred from earlier code reviews and belong in the cutover scope:

- **Session key inconsistency:** New code uses `coach_user_id` while existing pages read `user_id`/`admin_id`. Needs codebase-wide audit to unify session keys. [AuthService.php, logout.php] (from Epic 3 review)
- **Plaintext `password_reset_token` storage:** Tokens should be hashed before storage; aligns with credential cleanup. (from Story 3-1/3-5 review)
- **PII logged on failed auth:** Raw email/identifier logged on every failed auth attempt in `AuthService::authenticate()`. Establish audit-log/PII-redaction policy. (from Epic 3 review)
- **Legacy non-email username login breakage:** `type="email"` on login field blocks coaches registered before email-as-username change. Run one-time data migration: `SET username = email` for all existing `users` rows. (from email-as-username review)
- **`password_changed_at` schema-incomplete semantics:** `AuthService::enforceSessionLifetime()` always hits `team_owners` table even when `password_changed_at` column is absent. Verify column exists post-migration. (from Story 5-2 review)

---

## Dev Agent Record

### Implementation Plan

- `CutoverService` in `includes/CutoverService.php` — no namespace, D8TL_APP guard, constructor injection of Database.
- `getGapChecklist()`: two-query approach — first fetch all active-season teams (JOIN seasons/divisions/programs), then bulk-fetch all owners for those team IDs in a second query; assemble owners array per team and compute `has_gap`.
- `getGapCount()`: single COUNT query using `NOT EXISTS` correlated subquery on team_owners.
- `disableSharedCredential()`: guards via `getGapCount()`, issues `UPDATE settings SET setting_value = NULL`, logs via ActivityLogger.
- `isSharedCredentialActive()`: reads `setting_value` for `coaches_password`; returns false if row absent or value null/empty.
- `CutoverGapsRemainingException` defined in same file, extends RuntimeException.
- Setting key confirmed from `database/schema.sql` seed: `coaches_password`; column name is `setting_value` (from `functions.php` patterns).
- Active-season definition: `seasons.season_status = 'Active'` (consistent with admin panel queries).

### Debug Log

### Completion Notes

- Implemented `CutoverService` with all 5 methods and `CutoverGapsRemainingException` (2026-05-10).
- 15 unit tests in `CutoverServiceTest.php` — all pass (15/15).
- Full suite: 170 passed, 0 failed among non-pre-existing tests. The 9 ProfileService failures pre-existed this story (confirmed via `git stash` verification).
- No new dependencies introduced.

---

## File List

- `includes/CutoverService.php` — new
- `tests/unit/CutoverServiceTest.php` — new
- `_bmad-output/implementation-artifacts/9-1-cutover-service-backend.md` — updated
- `_bmad-output/implementation-artifacts/sprint-status.yaml` — updated (9-1 status)

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
- 2026-05-10: Implementation complete — CutoverService + CutoverGapsRemainingException created; 15 unit tests added; all ACs satisfied; status set to review.
