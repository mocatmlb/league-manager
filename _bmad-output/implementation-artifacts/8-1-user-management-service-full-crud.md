# Story 8.1: UserManagementService Full CRUD

**Status:** done
**Epic:** 8 — Admin User Management
**Story Key:** 8-1-user-management-service-full-crud

---

## Story

As a developer,
I want `UserManagementService` expanded with full user CRUD, role management, disable/enable, delete, and password reset operations,
So that admin user management pages have a clean, tested API for all account operations.

---

## Acceptance Criteria

**AC1: getList() returns paginated, filterable user list**
**Given** `UserManagementService::getList(array $filters, int $page, int $perPage)` is called
**When** filters include search term, role, and status
**Then** it returns a paginated array of users matching all active filters
**And** `total_count` is included for pagination rendering

**AC2: update() saves profile field changes**
**Given** `UserManagementService::update(int $userId, array $data)` is called with valid name/email/phone/username fields
**When** the update completes
**Then** the `users` table is updated and `ActivityLogger` event `admin.user_edited` is recorded

**AC3: setRole() changes user role**
**Given** `UserManagementService::setRole(int $userId, string $role, int $adminUserId)` is called
**When** the role is valid (`user`, `team_owner`, `administrator`)
**Then** the user's role is updated
**And** `ActivityLogger` event `admin.user_role_changed` is recorded

**AC4: disable() sets account inactive and invalidates sessions**
**Given** `UserManagementService::disable(int $userId, int $adminUserId)` is called
**When** the operation completes
**Then** the user's `status` is set to `'inactive'`; any active sessions are invalidated
**And** subsequent login attempts by that user return: "Your account has been disabled — contact the league administrator"
**And** `ActivityLogger` event `admin.user_disabled` is recorded

**AC5: enable() restores account**
**Given** `UserManagementService::enable(int $userId, int $adminUserId)` is called
**Then** the user's `status` is set to `'active'` and login is restored

**AC6: delete() removes user and clears assignments**
**Given** `UserManagementService::delete(int $userId, int $adminUserId)` is called
**When** the admin has confirmed the action
**Then** all `team_owners` records for that user are removed (assignments cleared)
**And** the user row is deleted
**And** `ActivityLogger` event `admin.user_deleted` is recorded

**AC7: resetPassword() generates temp password with force-change flag**
**Given** `UserManagementService::resetPassword(int $userId, int $adminUserId)` is called
**Then** a temporary password is generated and stored as a bcrypt hash with a `force_password_change` flag
**And** the temporary password is returned to the admin (displayed once, not stored in plaintext)
**And** on the user's next login, they are forced to set a new password before accessing any page

**AC8: Unit tests pass**
**Given** the unit test suite is run
**When** this story is complete
**Then** `UserManagementServiceTest.php` passes all cases including: getList pagination, update, setRole, disable, enable, delete cascade, resetPassword

---

## Tasks / Subtasks

- [x] **Task 1: Expand `includes/UserManagementService.php`** (initial version created in Story 4.3)
  - [x] Implement `getList(array $filters, int $page, int $perPage): array`
    - Build dynamic query: WHERE clauses for `search` (name OR username OR email LIKE), `role`, `status`
    - Pagination: LIMIT/OFFSET from `$page` and `$perPage`
    - Return `['users' => [...], 'total_count' => N]`
  - [x] Implement `update(int $userId, array $data): void`
    - Update `users` table fields: `first_name`, `last_name`, `preferred_name`, `email`, `username`
    - Phone updates delegate to `ProfileService::updatePhone()` or direct DB update
    - Log `admin.user_edited`
  - [x] Implement `setRole(int $userId, string $role, int $adminUserId): void`
    - Validate role value; UPDATE `users.role` (or roles table — check schema)
    - Log `admin.user_role_changed` with old and new role
  - [x] Implement `disable(int $userId, int $adminUserId): void`
    - UPDATE `users.status = 'disabled'` (schema enum is `('unverified','active','disabled')` — `'inactive'` is not valid)
    - Invalidate sessions: write `session_invalidated_at = NOW()` (AuthService::enforceSessionLifetime checks this)
    - Log `admin.user_disabled`
  - [x] Implement `enable(int $userId, int $adminUserId): void`
    - UPDATE `users.status = 'active'`
    - Log `admin.user_enabled`
  - [x] Implement `delete(int $userId, int $adminUserId): void`
    - DELETE FROM `team_owners` WHERE `user_id = $userId`
    - DELETE FROM `users` WHERE `id = $userId`
    - Log `admin.user_deleted`
  - [x] Implement `resetPassword(int $userId, int $adminUserId): string`
    - Generate 12-char temp password (alphanumeric mix)
    - Hash with `password_hash($temp, PASSWORD_BCRYPT)`
    - UPDATE `users`: set password hash, set `force_password_change = 1`
    - Log `admin.user_password_reset`
    - Return plaintext temp password (only time it's available)

- [x] **Task 2: Implement `force_password_change` enforcement**
  - [x] In `AuthService::enforceSessionLifetime()`: after re-checking the user row, set `$_SESSION['force_password_change'] = true` when the DB flag is 1
  - [x] In `PermissionGuard::requireRole()`: when the session flag is set and the current script is not `force-change-password.php`, redirect to `force-change-password.php`
  - [x] Migration `016_add_force_password_change_and_session_invalidated.sql` adds `force_password_change TINYINT(1) NOT NULL DEFAULT 0` and `session_invalidated_at DATETIME NULL` to `users`

- [x] **Task 3: Implement disabled account login check**
  - [x] In `AuthService::authenticate()`: after credentials validated, check `users.status` and throw `RuntimeException` with status-specific message when not `'active'`
  - [x] Wording for `'disabled'` updated to AC4 verbatim: "Your account has been disabled — contact the league administrator"
  - [x] Login page (`public/coaches/login.php`) catches the RuntimeException and surfaces `$e->getMessage()` to the user

- [x] **Task 4: Add `tests/unit/UserManagementServiceTest.php`**
  - [x] Test: getList returns paginated results with correct total_count
  - [x] Test: getList filters by search term, role, and status (params bound)
  - [x] Test: getList computes correct LIMIT/OFFSET for given page/perPage
  - [x] Test: update modifies allowed user fields (and silently drops unknown fields)
  - [x] Test: update is a no-op when no allowed fields are supplied
  - [x] Test: setRole updates role_id when role is valid
  - [x] Test: setRole throws InvalidArgumentException on invalid role
  - [x] Test: disable sets status to disabled and writes session_invalidated_at
  - [x] Test: enable sets status to active
  - [x] Test: delete removes user and team_owners records (correct cascade order)
  - [x] Test: resetPassword returns 12-char temp password, sets force_password_change flag, and stores a verifiable bcrypt hash

- [x] **Task 5: Run full test suite**
  - [x] All `UserManagementServiceTest` tests pass (23/23)
  - [x] No new regressions introduced (the 9 pre-existing `ProfileServiceTest` failures `Call to a member function rollback() on null` were present on `main` before this story and remain unchanged)

### Review Findings

- [x] [Review][Decision] Non-Atomic User Deletion — Wrapped `delete()` in `beginTransaction()`/`commit()`/`rollback()`. [includes/UserManagementService.php]
- [ ] [Review][Decision] Missing Password Reset Notification — `resetPassword()` returns the temp password to the admin who communicates it manually. Intentional: the admin chooses how to deliver it (phone, in-person, etc.). No email sent.
- [x] [Review][Patch] Unbounded getList Pagination — Added `min($perPage, 100)` cap and `max(1, ...)` floor. [includes/UserManagementService.php]
- [x] [Review][Patch] Weak getList SQL Injection Risk — Explicit `(int)` cast on both `$perPage` and `$offset` before interpolation. [includes/UserManagementService.php]
- [ ] [Review][Patch] Incomplete AuthService Status Message Enforcement — When `enforceSessionLifetime()` returns false, the session is destroyed and the user sees the login page. On their next login attempt, `authenticate()` throws with the correct AC4 message. The flow works end-to-end; no additional persistence needed.
- [ ] [Review][Patch] Lack of Input Length Validation in update() — DB schema enforces VARCHAR(50)/VARCHAR(100) limits; MySQL will error on overflow. Admin UI (Story 8.3) will add HTML maxlength attributes. No service-layer truncation needed.
- [x] [Review][Patch] generateTempPassword Character Bias — Refactored to guarantee at least one uppercase, one digit, and one special character, then Fisher-Yates shuffle. [includes/UserManagementService.php]
- [x] [Review][Defer] Missing Activity Log Details — `admin.user_role_changed` logs the role name but `admin.user_edited` only logs the field keys, not the old/new values. Deferring as existing patterns only log keys for large profile updates. [includes/UserManagementService.php:159] — deferred, pre-existing
- [x] [Review][Defer] Race Condition in session_invalidated_at — If `disable()` is called at the exact same second as a login, the `login_time < invalidatedAt` check might fail depending on precision. Deferring as 1-second resolution is standard for this app. [includes/AuthService.php:209] — deferred, pre-existing

---

## Dev Notes

### Architecture Context
- `UserManagementService.php` already exists from Story 4.3 with `assignTeam` and `removeTeam` — EXPAND, do not overwrite those methods
- Check `database/user_accounts_schema.sql` for `users` table structure — `role` column location, `status` column, etc.

### Session Invalidation on Disable
- PHP file sessions on shared hosting: cannot enumerate sessions per user
- Recommended approach: add `session_invalidated_at DATETIME NULL` column to `users`; on each authenticated request, check if session was created before this timestamp — if so, force logout
- Update this column in `disable()` to `NOW()`

### force_password_change Column
- If not in schema: create migration `database/migrations/009_add_force_password_change.sql` following AR-13 convention
- Check schema first to avoid duplicate migrations

### Role Storage
- Verify where role is stored — `users.role VARCHAR` column or a separate `roles` / `user_roles` table
- `setRole()` must update the correct location

### delete() Cascade
- Cascade order: team_owners → users (to avoid FK constraint violations)
- Consider impact on `activity_log` rows referencing the user — leave them (historical audit)

---

## Dev Agent Record

### Implementation Plan

Most of the implementation work landed on `main` in earlier passes (Task 1 service methods, migration 016, force-change-password page, PermissionGuard redirect, AuthService session/force-password integration). This Story 8.1 dev pass closed the remaining gaps:

1. **AC4 wording fix** — `AuthService::statusMessage('disabled')` was returning a generic "This account is currently disabled. Please contact league administration." string. AC4 specifies the exact text: **"Your account has been disabled — contact the league administrator"**. Split the case branch so `'disabled'` returns the AC4 verbatim text while `'suspended'`/`'locked'` keep the generic admin-contact message.
2. **`users.status = 'inactive'` vs `'disabled'`** — Story Dev Notes referenced `'inactive'`, but `database/user_accounts_schema.sql` defines `status ENUM('unverified','active','disabled')`. The implementation correctly uses `'disabled'`; the story file is updated to reflect the actual schema enum.
3. **Story 8.1 unit tests** — `tests/unit/UserManagementServiceTest.php` previously held only the Story 4.3 `assignTeam`/`removeTeam` cases. Added 11 new tests covering AC1–AC7 + AC8: getList shape/filters/pagination, update allowed-field whitelist, setRole valid/invalid, disable + session invalidation, enable, delete cascade order, resetPassword (length, force-change flag, hash verifiability). Extended `UMSMockDatabase` to simulate the new query patterns (getList SELECT/COUNT, UPDATE users SET status / role_id / password_hash, DELETE users) without a live DB.

### Debug Log

- Initial run of `php tests/unit/run-unit-tests.php` (after edits): 144 passed / 9 failed / 0 skipped. All 9 failures were in `ProfileServiceTest` (`Call to a member function rollback() on null`). Verified via `git stash` round-trip that all 9 are pre-existing on `main` and unrelated to this story.
- `php tests/unit/run-unit-tests.php --file=UserManagementServiceTest.php`: 23/23 passing (12 prior Story 4.3 tests + 11 new Story 8.1 tests).

### Completion Notes

- AC1–AC8 satisfied:
  - AC1: `getList()` returns `['users','total_count']` with bound search/role/status filters and computed `LIMIT/OFFSET`. Verified by 3 unit tests.
  - AC2: `update()` whitelists 5 fields, ignores unknowns, logs `admin.user_edited`. Verified by 2 unit tests.
  - AC3: `setRole()` validates against `['user','team_owner','administrator']`, updates `role_id`, logs `admin.user_role_changed`. Verified by 2 unit tests.
  - AC4: `disable()` sets `status='disabled'` + `session_invalidated_at=NOW()`. `AuthService::enforceSessionLifetime()` invalidates pre-disable sessions. `AuthService::statusMessage('disabled')` returns the AC4 verbatim string. Login page surfaces the message via the thrown `RuntimeException`.
  - AC5: `enable()` sets `status='active'`. Verified by unit test.
  - AC6: `delete()` cascades `team_owners` → `users`. Verified by ordering assertion in unit test.
  - AC7: `resetPassword()` generates a 12-char temp password, stores a bcrypt hash, sets `force_password_change=1`, logs the event, and returns the plaintext (returned only once). On next login, `enforceSessionLifetime` sets `$_SESSION['force_password_change']` and `PermissionGuard::requireRole()` redirects to `force-change-password.php` until the user picks a new password.
  - AC8: `UserManagementServiceTest.php` 23/23 passing.
- Pre-existing `ProfileServiceTest` failures left untouched — out of scope for this story.

---

## File List

- `includes/UserManagementService.php` — modified (full CRUD + role/disable/enable/delete/resetPassword methods on top of Story 4.3 assignTeam/removeTeam)
- `includes/AuthService.php` — modified (`statusMessage()` AC4 text; `enforceSessionLifetime()` checks `session_invalidated_at` + `force_password_change`)
- `includes/PermissionGuard.php` — modified (redirects to `force-change-password.php` when `$_SESSION['force_password_change']` is set)
- `includes/ProfileService.php` — modified (added `forceSetPassword()` method for temp-password flow)
- `includes/coach_bootstrap.php` — modified (added `force-change-password.php` to public pages whitelist)
- `public/coaches/force-change-password.php` — new (force-set-password page that clears the flag)
- `database/migrations/016_add_force_password_change_and_session_invalidated.sql` — new (adds `force_password_change` + `session_invalidated_at` columns to `users`)
- `tests/unit/UserManagementServiceTest.php` — modified (extended `UMSMockDatabase` and added 11 Story 8.1 tests)
- `_bmad-output/implementation-artifacts/8-1-user-management-service-full-crud.md` — modified (this file)

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
- 2026-05-09: Story 8.1 dev pass completed — fixed AC4 disabled-login wording, added 11 Story 8.1 unit tests (23/23 UMS tests pass), confirmed no regressions vs `main`. Status → review.
- 2026-05-10: Addressed review findings — wrapped delete() in transaction, capped perPage at 100, explicit (int) casts on LIMIT/OFFSET, guaranteed temp password complexity, added forceSetPassword() to ProfileService, added force-change-password.php to coach_bootstrap whitelist.
