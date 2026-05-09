# Story 7.1: ProfileService Backend

**Status:** ready-for-dev
**Epic:** 7 — Coach Profile, Team Schedule & Authenticated Resources
**Story Key:** 7-1-profile-service-backend

---

## Story

As a developer,
I want a `ProfileService` class that handles profile field updates and self-service password changes,
so that the coach profile page (Story 7.2) has a clean, tested backend API.

---

## Acceptance Criteria

**AC1: updateName() updates user name fields**
**Given** a coach submits updated name fields (first, last, preferred)
**When** `ProfileService::updateName(int $userId, array $nameData)` is called
**Then** the `users` table is updated with the new values (first_name, last_name, preferred_name)
**And** `ActivityLogger::log('profile.name_updated', ['user_id' => $userId, 'fields_updated' => ['first_name', 'last_name', 'preferred_name']])` is called (field names only — never log actual values)

**AC2: updatePhone() saves phone with type (INSERT or UPDATE)**
**Given** a coach submits an updated primary or secondary phone number and type
**When** `ProfileService::updatePhone(int $userId, string $phone, string $type, string $role = 'primary')` is called
**Then** a row in `user_phones` is inserted or updated (ON DUPLICATE KEY UPDATE) for that user+role
**And** `type` is validated to be one of: `Home`, `Work`, `Cell` (throw `InvalidArgumentException` if not)
**And** `ActivityLogger::log('profile.phone_updated', ['user_id' => $userId, 'role' => $role])` is called

**AC3: removeSecondaryPhone() deletes secondary only**
**Given** a coach requests removal of their secondary phone
**When** `ProfileService::removeSecondaryPhone(int $userId)` is called
**Then** the `user_phones` row with `user_id = $userId AND role = 'secondary'` is deleted
**And** the primary phone row is unaffected
**And** `ActivityLogger::log('profile.phone_removed', ['user_id' => $userId, 'role' => 'secondary'])` is called

**AC4: changePassword() validates current password and updates**
**Given** a coach submits a password change with a correct current password and valid new password
**When** `ProfileService::changePassword(int $userId, string $currentPassword, string $newPassword)` is called
**Then** `password_verify($currentPassword, $storedHash)` confirms the current password
**And** `$newPassword` passes FR-REG-5 complexity rules (≥8 chars, 1 uppercase, 1 number, 1 special char)
**And** `password_hash($newPassword, PASSWORD_BCRYPT)` is stored in `users.password_hash`
**And** `users.password_changed_at` is updated to `NOW()`
**And** `ActivityLogger::log('profile.password_changed', ['user_id' => $userId])` is called

**AC5: changePassword() throws IncorrectCurrentPasswordException**
**Given** the coach provides an incorrect current password
**When** `ProfileService::changePassword()` is called
**Then** `IncorrectCurrentPasswordException` is thrown and no password is updated

**AC6: changePassword() throws WeakPasswordException**
**Given** the new password fails FR-REG-5 complexity rules
**When** `ProfileService::changePassword()` is called
**Then** `WeakPasswordException` is thrown and no password is updated

**AC7: Unit tests pass**
**Given** the unit test suite is run (`php tests/unit/run-unit-tests.php --file=ProfileServiceTest.php`)
**When** this story is complete
**Then** all `ProfileServiceTest.php` cases pass: name update, phone insert, phone update, secondary phone removal, password change success, wrong current password, weak new password

---

## Tasks / Subtasks

- [ ] **Task 1: Database migrations**
  - [ ] Create `database/migrations/014_add_users_preferred_name.sql`
    - ALTER TABLE users ADD COLUMN `preferred_name VARCHAR(50) NULL AFTER last_name` (idempotent, guarded with information_schema check)
    - Pattern: DROP PROCEDURE IF EXISTS / DELIMITER / BEGIN / IF NOT EXISTS information_schema.COLUMNS check / ALTER TABLE / END / CALL / DROP / INSERT IGNORE INTO schema_migrations
  - [ ] Create `database/migrations/015_create_user_phones.sql`
    - CREATE TABLE IF NOT EXISTS `user_phones` with: `id` INT AUTO_INCREMENT PK, `user_id` INT NOT NULL FK→users(id) ON DELETE CASCADE, `phone` VARCHAR(20) NOT NULL, `type` ENUM('Home','Work','Cell') NOT NULL, `role` ENUM('primary','secondary') NOT NULL, `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP, `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY `uq_user_phones_user_role (user_id, role)`, INDEX `idx_user_phones_user_id (user_id)`
    - ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    - Idempotent: wrapped in IF NOT EXISTS or the IF NOT EXISTS on CREATE TABLE is sufficient

- [ ] **Task 2: Implement `ProfileService` class**
  - [ ] Create `includes/ProfileService.php`
  - [ ] File header: `<?php` + `if (!defined('D8TL_APP')) { die('Direct access not permitted'); }`
  - [ ] Lazy-require ActivityLogger at top of file (same pattern as RegistrationService)
  - [ ] Define `IncorrectCurrentPasswordException extends RuntimeException {}` — define with `class_exists` guard since WeakPasswordException lives in RegistrationService.php
  - [ ] Do NOT redefine `WeakPasswordException` — use `class_exists` guard, since RegistrationService.php already defines it; ProfileService.php must `require_once` RegistrationService.php to ensure WeakPasswordException is available
  - [ ] Constructor: `__construct(?Database $db = null)` — `$this->db = $db ?? Database::getInstance()`
  - [ ] `updateName(int $userId, array $nameData): void`
    - Validate: `first_name` (required, non-empty after trim), `last_name` (required), `preferred_name` (optional)
    - `UPDATE users SET first_name = :first_name, last_name = :last_name, preferred_name = :preferred_name, updated_at = NOW() WHERE id = :user_id`
    - `ActivityLogger::log('profile.name_updated', ['user_id' => $userId, 'fields_updated' => ['first_name', 'last_name', 'preferred_name']])`
  - [ ] `updatePhone(int $userId, string $phone, string $type, string $role = 'primary'): void`
    - Validate `$type` is in `['Home', 'Work', 'Cell']` — throw `InvalidArgumentException` if not
    - Validate `$role` is in `['primary', 'secondary']` — throw `InvalidArgumentException` if not
    - Use INSERT ... ON DUPLICATE KEY UPDATE (leverages UNIQUE KEY on user_id+role):
      ```sql
      INSERT INTO user_phones (user_id, phone, type, role, created_at, updated_at)
      VALUES (:user_id, :phone, :type, :role, NOW(), NOW())
      ON DUPLICATE KEY UPDATE phone = VALUES(phone), type = VALUES(type), updated_at = NOW()
      ```
    - `ActivityLogger::log('profile.phone_updated', ['user_id' => $userId, 'role' => $role])`
  - [ ] `removeSecondaryPhone(int $userId): void`
    - `DELETE FROM user_phones WHERE user_id = :user_id AND role = 'secondary'`
    - `ActivityLogger::log('profile.phone_removed', ['user_id' => $userId, 'role' => 'secondary'])`
  - [ ] `changePassword(int $userId, string $currentPassword, string $newPassword): void`
    - Fetch: `SELECT password_hash FROM users WHERE id = :user_id`
    - If no row found, throw `InvalidArgumentException('User not found')`
    - `password_verify($currentPassword, $row['password_hash'])` — throw `IncorrectCurrentPasswordException` if false
    - Validate `$newPassword` complexity (same 4 rules as RegistrationService::validatePasswordComplexity) — throw `WeakPasswordException` if fails
    - `password_hash($newPassword, PASSWORD_BCRYPT)` — UPDATE users SET password_hash = :hash, password_changed_at = NOW(), updated_at = NOW() WHERE id = :user_id
    - `ActivityLogger::log('profile.password_changed', ['user_id' => $userId])`
  - [ ] Private `validateNewPasswordComplexity(string $password): void` (duplicated from RegistrationService — same 4 preg_match rules; throws WeakPasswordException)

- [ ] **Task 3: Implement `ProfileServiceTest.php`**
  - [ ] Create `tests/unit/ProfileServiceTest.php`
  - [ ] Header: `define('D8TL_APP', true)`, require test-helpers.php, database.php, ActivityLogger.php, RegistrationService.php (for WeakPasswordException), ProfileService.php
  - [ ] `PSMockDatabase` extends `Database` — bypasses real PDO, tracks `queryCalls`, `activityEvents`; must stub `fetchOne` for the `SELECT password_hash FROM users` call
  - [ ] Tests (use `test(description, fn)` helper from test-helpers.php):
    - updateName stores first, last, preferred in correct UPDATE SQL
    - updateName logs `profile.name_updated` with fields_updated array (not values)
    - updatePhone inserts new row (INSERT ... ON DUPLICATE KEY UPDATE) for primary
    - updatePhone updates existing row for secondary role
    - updatePhone throws InvalidArgumentException for invalid type
    - removeSecondaryPhone issues DELETE WHERE role = 'secondary'
    - removeSecondaryPhone logs `profile.phone_removed`
    - changePassword with correct current password and valid new password calls UPDATE users
    - changePassword with correct current password and valid new password logs `profile.password_changed`
    - changePassword with correct current password and valid new password updates password_changed_at
    - changePassword throws IncorrectCurrentPasswordException for wrong current password
    - changePassword throws WeakPasswordException for non-compliant new password (test each of: too short, no uppercase, no number, no special char)

- [ ] **Task 4: Run full test suite**
  - [ ] Run `php tests/unit/run-unit-tests.php --file=ProfileServiceTest.php` — all pass
  - [ ] Run full suite `php tests/unit/run-unit-tests.php` — no regressions

---

## Dev Notes

### Migrations — two are required (this is the #1 thing the existing story underspecified)

**Migration 014** — `preferred_name` column on `users`:
- `users` table (in `database/user_accounts_schema.sql`) does NOT have `preferred_name`. It has `first_name`, `last_name`, `phone`, `role_id`, etc.
- Registration form (`public/coaches/register.php:207`) collects it but RegistrationService never persists it (no schema column). Adding this column via 014 enables it.
- Column definition: `preferred_name VARCHAR(50) NULL` — nullable because it was not collected for existing accounts

**Migration 015** — `user_phones` table:
- The `users` table has a single `phone VARCHAR(20) NOT NULL` column (confirmed in `database/user_accounts_schema.sql:43`).
- Story 6.2 notes: "Multi-phone support is Epic 7. For 6.2, use `users.phone` directly."
- ProfileService.updatePhone() writes to `user_phones`, NOT to `users.phone`. The old `users.phone` column remains — do not remove it.
- The UNIQUE KEY on `(user_id, role)` is critical for ON DUPLICATE KEY UPDATE to work correctly.

**Migration file pattern** (from `database/migrations/008_add_users_password_changed_at.sql`):
```sql
DROP PROCEDURE IF EXISTS _d8tl_migrate_014;
DELIMITER $$
CREATE PROCEDURE _d8tl_migrate_014()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'preferred_name'
  ) THEN
    ALTER TABLE users
      ADD COLUMN preferred_name VARCHAR(50) NULL AFTER last_name;
  END IF;
END$$
DELIMITER ;
CALL _d8tl_migrate_014();
DROP PROCEDURE IF EXISTS _d8tl_migrate_014;
INSERT IGNORE INTO schema_migrations (version) VALUES ('014');
```

### WeakPasswordException — DO NOT redefine, reuse from RegistrationService

`WeakPasswordException` is already declared in `includes/RegistrationService.php:24` as `class WeakPasswordException extends RuntimeException {}`. PHP will fatal error if you declare it twice. ProfileService.php must:
1. `require_once __DIR__ . '/RegistrationService.php';` at the top (after D8TL_APP check)
2. Then define only `IncorrectCurrentPasswordException` (does not exist anywhere yet)
3. Use `class_exists('WeakPasswordException')` guard only if you can't guarantee load order

Similarly in test file: require RegistrationService.php BEFORE ProfileService.php.

### Password field name is `password_hash`, not `password`

```sql
-- Correct:
SELECT password_hash FROM users WHERE id = :user_id
UPDATE users SET password_hash = :hash ...
-- Wrong:
SELECT password FROM users ... (old schema used `password`)
```

Confirmed in `database/user_accounts_schema.sql:40`: `password_hash VARCHAR(255) NOT NULL`.

### `password_changed_at` column exists (migration 008 already applied)

Migration 008 added `password_changed_at DATETIME NULL` to users. Update it in `changePassword()` to invalidate active sessions. Pattern from `RegistrationService::completePasswordReset()`:
```php
UPDATE users SET password_hash = :hash, password_changed_at = NOW(), updated_at = NOW() WHERE id = :user_id
```

### Password validation — duplicate the 4 lines (don't call into RegistrationService)

`RegistrationService::validatePasswordComplexity()` is `private`. Profile Service needs its own copy. It's 4 `preg_match` checks — acceptable duplication:
```php
private function validateNewPasswordComplexity(string $password): void {
    if (strlen($password) < 8) throw new WeakPasswordException('Password must be at least 8 characters.');
    if (!preg_match('/[A-Z]/', $password)) throw new WeakPasswordException('Password must contain at least one uppercase letter.');
    if (!preg_match('/\d/', $password)) throw new WeakPasswordException('Password must contain at least one number.');
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) throw new WeakPasswordException('Password must contain at least one special character.');
}
```

### ActivityLogger privacy rule

Log event names and field NAMES — never actual values. For `profile.name_updated`, log `['user_id' => $userId, 'fields_updated' => ['first_name', 'last_name', 'preferred_name']]` — do NOT include the actual name strings. For `profile.phone_updated`, log the role ('primary'/'secondary') — NOT the phone number itself. For `profile.password_changed`, log only `['user_id' => $userId]`.

### No D8TL namespace — match existing files exactly

Despite the architecture doc mentioning `D8TL\` namespace, all actual service classes in `includes/` are non-namespaced global classes (RegistrationService, ScoreService, RescheduleService, etc.). ProfileService must follow the same pattern — no namespace declaration.

### Test mock pattern — match RescheduleServiceTest/ScoreServiceTest

Use prefix naming to avoid class collision between test files: `PSMockDatabase`, `PSMockStatement`. The mock must:
- Override `fetchOne()` to return a fake user row for `SELECT password_hash FROM users`
- Override `query()` to record calls in `$this->queryCalls`
- Track ActivityLogger calls via a shared array (same technique as RS/SS test mocks)
- Constructor: `public function __construct() {}` (bypasses real PDO)

### Files being modified

No existing files are modified in this story — this is purely additive:
- 2 new migration files
- 1 new service class
- 1 new test class

### Story 7.2 dependency

Story 7.2 (Coach Profile Page) will call ProfileService. It will also display `$user['preferred_name']` from the SELECT of users. After migration 014 runs, this column will exist and return NULL for existing users (correct behavior — show empty field).

Story 6.2 (done): `schedule-change.php` pre-populates contact info from `users.phone` (the OLD single-phone column). After story 7.1, the coach's phones are in `user_phones`. Story 7.2's profile page will read from `user_phones`. The old `users.phone` is NOT removed — leave it as-is. Story 6.2 already shipped using `users.phone`; don't break it.

---

## Files

| File | Action |
|------|--------|
| `database/migrations/014_add_users_preferred_name.sql` | NEW |
| `database/migrations/015_create_user_phones.sql` | NEW |
| `includes/ProfileService.php` | NEW |
| `tests/unit/ProfileServiceTest.php` | NEW |

**Depends on:** None (this is the first story in Epic 7; no predecessors required)

---

## Dev Agent Record

### Agent Model Used

claude-sonnet-4-6

### Debug Log References

### Completion Notes List

### File List
