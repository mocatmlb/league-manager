# Story 7.1: ProfileService Backend

**Status:** ready
**Epic:** 7 — Coach Profile, Team Schedule & Authenticated Resources
**Story Key:** 7-1-profile-service-backend

---

## Story

As a developer,
I want a `ProfileService` class that handles profile field updates and self-service password changes,
So that the coach profile page has a clean, tested API.

---

## Acceptance Criteria

**AC1: updateName() updates user name fields**
**Given** a coach submits updated name fields (first, last, preferred)
**When** `ProfileService::updateName(int $userId, array $nameData)` is called
**Then** the `users` table is updated with the new values
**And** `ActivityLogger` event `profile.name_updated` is recorded (field names only, no values logged)

**AC2: updatePhone() saves phone with type**
**Given** a coach submits an updated primary phone number and type
**When** `ProfileService::updatePhone(int $userId, string $phone, string $type, string $role = 'primary')` is called
**Then** the phone record is updated or created with the correct type (Home/Work/Cell)
**And** `ActivityLogger` event `profile.phone_updated` is recorded

**AC3: removeSecondaryPhone() deletes secondary only**
**Given** a coach submits a secondary phone number removal
**When** `ProfileService::removeSecondaryPhone(int $userId)` is called
**Then** the secondary phone record is deleted
**And** the primary phone is unaffected (FR-PROFILE-4)

**AC4: changePassword() validates current password and updates**
**Given** a coach submits a password change with correct current password and valid new password
**When** `ProfileService::changePassword(int $userId, string $currentPassword, string $newPassword)` is called
**Then** `password_verify()` confirms the current password against the stored hash
**And** the new password is stored as a bcrypt hash
**And** `ActivityLogger` event `profile.password_changed` is recorded

**AC5: changePassword() throws IncorrectCurrentPasswordException**
**Given** the coach provides an incorrect current password
**When** `ProfileService::changePassword()` is called
**Then** a `IncorrectCurrentPasswordException` is thrown and no password is changed

**AC6: changePassword() throws WeakPasswordException**
**Given** the new password fails FR-REG-5 complexity rules
**When** `ProfileService::changePassword()` is called
**Then** a `WeakPasswordException` is thrown

**AC7: Unit tests pass**
**Given** the unit test suite is run
**When** this story is complete
**Then** `ProfileServiceTest.php` passes all cases including: name update, phone update, secondary phone removal, password change success, wrong current password, weak new password

---

## Tasks / Subtasks

- [ ] **Task 1: Implement `ProfileService` class**
  - [ ] Create `includes/ProfileService.php`
  - [ ] Implement `updateName(int $userId, array $nameData): void`
    - Validate `$nameData` keys: `first_name` (required), `last_name` (required), `preferred_name` (optional)
    - UPDATE `users` SET `first_name`, `last_name`, `preferred_name` WHERE `id = $userId`
    - Log `profile.name_updated` with `['fields_updated' => ['first_name', 'last_name', 'preferred_name']]` (no values)
  - [ ] Implement `updatePhone(int $userId, string $phone, string $type, string $role = 'primary'): void`
    - Check existing phone record for user + role; INSERT or UPDATE accordingly
    - Valid types: `Home`, `Work`, `Cell` — validate before write
    - Log `profile.phone_updated`
  - [ ] Implement `removeSecondaryPhone(int $userId): void`
    - DELETE phone record WHERE `user_id = $userId AND role = 'secondary'`
    - Verify primary phone is unaffected
    - Log `profile.phone_removed`
  - [ ] Implement `changePassword(int $userId, string $currentPassword, string $newPassword): void`
    - Fetch current password hash from `users` for `$userId`
    - `password_verify($currentPassword, $hash)` — throw `IncorrectCurrentPasswordException` if false
    - Validate `$newPassword` against FR-REG-5 complexity rules — throw `WeakPasswordException` if fails
    - `password_hash($newPassword, PASSWORD_BCRYPT)` — UPDATE `users.password`
    - Log `profile.password_changed`
  - [ ] Define `IncorrectCurrentPasswordException` and `WeakPasswordException` (extend `RuntimeException`) — reuse `WeakPasswordException` from `RegistrationService` if already defined there

- [ ] **Task 2: Implement `ProfileServiceTest.php`**
  - [ ] Test: updateName updates all three name fields
  - [ ] Test: updateName logs field names, not values
  - [ ] Test: updatePhone creates new record if none exists
  - [ ] Test: updatePhone updates existing record
  - [ ] Test: removeSecondaryPhone deletes secondary, leaves primary intact
  - [ ] Test: changePassword with correct current password and valid new password succeeds
  - [ ] Test: changePassword throws `IncorrectCurrentPasswordException` for wrong current password
  - [ ] Test: changePassword throws `WeakPasswordException` for non-compliant new password

- [ ] **Task 3: Run full test suite**
  - [ ] All `ProfileServiceTest` tests pass
  - [ ] No regressions in existing tests

---

## Dev Notes

### Architecture Context
- Class in `includes/` — no namespace, define/check `D8TL_APP`
- Phone records stored in a separate `user_phones` table (check `user_accounts_schema.sql`) with `user_id`, `phone`, `type`, `role ENUM('primary','secondary')`
- `WeakPasswordException` — check if already defined in `RegistrationService.php`; if so, reuse rather than redefining

### Phone Table Structure
- Verify `user_phones` or similar table in `database/user_accounts_schema.sql`
- `role`: `primary` or `secondary`; a user has at most one of each
- Use INSERT ... ON DUPLICATE KEY UPDATE or SELECT + conditional INSERT/UPDATE

### Password Complexity (FR-REG-5, FR-PROFILE-6)
- Same rules as registration: ≥8 chars, one uppercase, one number, one special character
- Extract to a shared helper function to avoid duplication

### ActivityLogger Privacy
- Log event names but NOT the actual values (e.g., do not log new phone number or name values)
- Log only field names changed or a generic "password changed" event

---

## Dev Agent Record

### Implementation Plan

### Debug Log

### Completion Notes

---

## File List

- `includes/ProfileService.php` — new
- `tests/unit/ProfileServiceTest.php` — new
- `_bmad-output/implementation-artifacts/7-1-profile-service-backend.md` — new (this file)

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
