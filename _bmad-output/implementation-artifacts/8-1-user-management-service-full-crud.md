# Story 8.1: UserManagementService Full CRUD

**Status:** ready
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

- [ ] **Task 1: Expand `includes/UserManagementService.php`** (initial version created in Story 4.3)
  - [ ] Implement `getList(array $filters, int $page, int $perPage): array`
    - Build dynamic query: WHERE clauses for `search` (name OR username OR email LIKE), `role`, `status`
    - Pagination: LIMIT/OFFSET from `$page` and `$perPage`
    - Return `['users' => [...], 'total_count' => N]`
  - [ ] Implement `update(int $userId, array $data): void`
    - Update `users` table fields: `first_name`, `last_name`, `preferred_name`, `email`, `username`
    - Phone updates delegate to `ProfileService::updatePhone()` or direct DB update
    - Log `admin.user_edited`
  - [ ] Implement `setRole(int $userId, string $role, int $adminUserId): void`
    - Validate role value; UPDATE `users.role` (or roles table — check schema)
    - Log `admin.user_role_changed` with old and new role
  - [ ] Implement `disable(int $userId, int $adminUserId): void`
    - UPDATE `users.status = 'inactive'`
    - Invalidate sessions: add `force_logout` flag or track session invalidation timestamp on `users` record
    - Log `admin.user_disabled`
  - [ ] Implement `enable(int $userId, int $adminUserId): void`
    - UPDATE `users.status = 'active'`
    - Log `admin.user_enabled`
  - [ ] Implement `delete(int $userId, int $adminUserId): void`
    - DELETE FROM `team_owners` WHERE `user_id = $userId`
    - DELETE FROM `users` WHERE `id = $userId`
    - Log `admin.user_deleted`
  - [ ] Implement `resetPassword(int $userId, int $adminUserId): string`
    - Generate 12-char temp password (alphanumeric mix)
    - Hash with `password_hash($temp, PASSWORD_BCRYPT)`
    - UPDATE `users`: set password hash, set `force_password_change = 1`
    - Log `admin.user_password_reset`
    - Return plaintext temp password (only time it's available)

- [ ] **Task 2: Implement `force_password_change` enforcement**
  - [ ] In `bootstrap.php` or `auth.php`: after successful authentication, check `users.force_password_change`
  - [ ] If flag is set: redirect user to a change-password page before any other content
  - [ ] Add `force_password_change TINYINT(1) DEFAULT 0` column to `users` if not already present — add a migration if needed

- [ ] **Task 3: Implement disabled account login check**
  - [ ] In login handler (`login.php` from Story 3.4): after credentials validated, check `users.status`
  - [ ] If `status = 'inactive'`: reject login with "Your account has been disabled — contact the league administrator"
  - [ ] This check should be in `AuthService` or the login page, not in `UserManagementService`

- [ ] **Task 4: Add `tests/unit/UserManagementServiceTest.php`**
  - [ ] Test: getList returns paginated results with correct total_count
  - [ ] Test: getList filters by search term, role, and status
  - [ ] Test: update modifies user fields and logs event
  - [ ] Test: setRole updates role and logs event
  - [ ] Test: disable sets status to inactive
  - [ ] Test: enable sets status to active
  - [ ] Test: delete removes user and team_owners records
  - [ ] Test: resetPassword returns temp password and sets force_password_change flag

- [ ] **Task 5: Run full test suite**
  - [ ] All `UserManagementServiceTest` tests pass
  - [ ] No regressions in existing tests

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

### Debug Log

### Completion Notes

---

## File List

- `includes/UserManagementService.php` — modify (expand with full CRUD, disable, delete, reset-password methods)
- `tests/unit/UserManagementServiceTest.php` — new
- `database/migrations/009_add_force_password_change.sql` — new (if column doesn't exist in schema)
- `_bmad-output/implementation-artifacts/8-1-user-management-service-full-crud.md` — new (this file)

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
