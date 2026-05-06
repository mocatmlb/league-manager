# Story 3.1: Registration Service & Email Verification Backend

**Status:** review
**Epic:** 3 — Coach Registration & Authentication
**Story Key:** 3-1-registration-service-email-verification-backend

---

## Story

As a developer,
I want a `RegistrationService` class that handles account creation, email verification token generation, and verification link processing,
So that registration page files have a clean, tested API for the full account creation flow.

---

## Acceptance Criteria

**AC1: Successful registration creates unverified user and sends verification email**
**Given** valid registration form data (all required fields, passing complexity rules)
**When** `RegistrationService::register(array $data)` is called
**Then** a new row is inserted into `users` with `status = 'unverified'` and `role = 'user'`
**And** the password is stored as a bcrypt hash (plaintext is never stored)
**And** a unique verification token is generated and stored with a 48-hour expiry
**And** a verification email is sent via `EmailService` (blocking — failure surfaces an error)
**And** an `ActivityLogger` event `registration.verification_email_sent` is recorded
**And** the new user's ID is returned

**AC2: Duplicate username throws exception**
**Given** a duplicate username is submitted
**When** `RegistrationService::register()` is called
**Then** a `DuplicateUsernameException` is thrown and no user row is created

**AC3: Duplicate email throws exception**
**Given** a duplicate email is submitted
**When** `RegistrationService::register()` is called
**Then** a `DuplicateEmailException` is thrown and no user row is created

**AC4: Valid token verifies account**
**Given** a valid, unexpired verification token
**When** `RegistrationService::verifyEmail(string $token)` is called
**Then** the user's `status` is updated to `'active'`
**And** the token is consumed (cannot be reused)
**And** an `ActivityLogger` event `registration.account_verified` is recorded
**And** an operational notification email is sent to admin (failure logged, not surfaced)
**And** the user's ID is returned

**AC5: Expired token throws exception**
**Given** an expired verification token (> 48 hours old)
**When** `RegistrationService::verifyEmail()` is called
**Then** an `ExpiredTokenException` is thrown

**AC6: Resend verification generates new token**
**Given** a user with an expired verification token requests resend
**When** `RegistrationService::resendVerification(int $userId)` is called
**Then** a new token is generated replacing the old one and a new verification email is sent

**AC7: Unit tests pass**
**Given** the unit test suite is run
**When** this story is complete
**Then** `RegistrationServiceTest.php` passes all cases including success, duplicate username, duplicate email, expired token, and resend

---

## Tasks / Subtasks

- [x] **Task 1: Implement `RegistrationService` class**
  - [x] Create `includes/RegistrationService.php`
  - [x] Implement `register(array $data): int` — validate, hash password (bcrypt), insert user with `status='unverified'`, `role='user'`, generate + store 48-hour token, send blocking verification email, log `registration.verification_email_sent`
  - [x] Implement `verifyEmail(string $token): int` — look up token, check expiry, update user `status='active'`, consume token, log `registration.account_verified`, send operational admin notification
  - [x] Implement `resendVerification(int $userId): void` — delete old token, generate new 48-hour token, send blocking verification email
  - [x] Define and throw `DuplicateUsernameException`, `DuplicateEmailException`, `ExpiredTokenException` (can be simple PHP classes extending `RuntimeException`)
  - [x] Password complexity validation: ≥8 chars, at least one uppercase, one number, one special character (FR-REG-5)

- [x] **Task 2: Implement `RegistrationServiceTest.php`**
  - [x] Test: register creates user with `status='unverified'` and `role='user'`
  - [x] Test: register stores bcrypt hash, not plaintext
  - [x] Test: register throws `DuplicateUsernameException` on collision
  - [x] Test: register throws `DuplicateEmailException` on collision
  - [x] Test: verifyEmail sets status to `'active'` and consumes token
  - [x] Test: verifyEmail throws `ExpiredTokenException` for expired token
  - [x] Test: verifyEmail throws for already-consumed token
  - [x] Test: resendVerification replaces old token with new one

- [x] **Task 3: Run full test suite and verify all pass**
  - [x] All `RegistrationServiceTest` tests pass
  - [x] No regressions in existing tests (`php tests/unit/run-unit-tests.php`)

---

## Dev Notes

### Architecture Context
- Class goes in `includes/` as PascalCase PHP class file — no namespace required
- Must define or check `D8TL_APP` guard at top of file
- Uses `Database::getInstance()` — `Database::setInstance()` for test injection
- Email sending via `EmailService` (or `PHPMailer`-based existing mailer in `includes/`)
- Logging via `ActivityLogger::log()` (implemented in Story 1.3)

### Database Tables Used
- `users` — insert new row on register; update `status` on verify
- Verification tokens: check if a `email_verification_tokens` table exists or if tokens are stored in a column on `users` — align with the schema from `database/user_accounts_schema.sql`

### Token Generation
- `bin2hex(random_bytes(32))` for 48-char hex token
- Store hash or raw token with expiry `DATETIME` column
- One token per user; replace on resend

### Email Classification (AR-12)
- Verification email: **blocking** — surface failure to caller
- Admin notification on verification: **operational** — log only on failure

### Password Complexity (FR-REG-5)
- ≥8 characters
- At least one uppercase letter
- At least one number
- At least one special character
- Throw named exception or return structured error — do not use generic exceptions

### Test Pattern
- Use `Database::setInstance()` to inject a mock
- Mock `EmailService` to avoid real SMTP calls in tests
- Reset state after each test

---

## Dev Agent Record

### Implementation Plan
- Implement a focused service in `includes/RegistrationService.php` with explicit methods for register, verify, and resend.
- Use `Database::getInstance()` data access with guardrails for duplicate username/email, password complexity, and token expiry.
- Inject `Database` and `EmailService` in constructor for deterministic unit tests; keep runtime defaults for normal app usage.
- Add named domain exceptions (`DuplicateUsernameException`, `DuplicateEmailException`, `ExpiredTokenException`, `InvalidPasswordException`) for explicit failure handling.
- Support both `users.role` and `users.role_id` schemas so registration remains compatible with current DB variants.

### Debug Log
- Added `includes/RegistrationService.php`.
- Added `tests/unit/RegistrationServiceTest.php` with in-memory `Database` mock + mock email service.
- Ran `php tests/unit/run-unit-tests.php --file=RegistrationServiceTest.php` → 8 passed, 0 failed.
- Ran `php tests/unit/run-unit-tests.php` → 62 passed, 0 failed.

### Completion Notes
- Implemented `RegistrationService::register()` with required field validation, password complexity enforcement (FR-REG-5), duplicate checks, bcrypt hashing, verification token generation (48-hour expiry), blocking verification email send, and audit logging (`registration.verification_email_sent`).
- Implemented `RegistrationService::verifyEmail()` with token lookup, expiry enforcement (`ExpiredTokenException`), token consumption, status promotion to `active`, audit logging (`registration.account_verified`), and non-blocking admin operational notification.
- Implemented `RegistrationService::resendVerification()` to replace stale token/expiry and send a fresh blocking verification email.
- Added full unit coverage in `RegistrationServiceTest.php` for success path, duplicate username/email, expired token, consumed token, password hash assertions, and resend behavior.
- Verified no regressions by executing the full unit suite successfully.

---

## File List

- `includes/RegistrationService.php` — new
- `tests/unit/RegistrationServiceTest.php` — new
- `_bmad-output/implementation-artifacts/3-1-registration-service-email-verification-backend.md` — updated (status/tasks/dev record)

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
- 2026-05-05: Implemented RegistrationService + unit tests; all story tasks completed; status moved to review.
- 2026-05-06: Code review run (bmad-code-review, full mode); findings appended; status remains review pending fixes.

---

## Review Findings

### Decisions resolved (2026-05-06)

- **Token hashing at rest:** Accept plaintext storage for MVP. Revisit post-MVP. (No patch.)

### Patch

- [ ] [Review][Patch] **Email pipeline non-functional — no template rows seeded** [`database/migrations/`] — `EmailService::triggerNotification` returns `false` for missing templates; `RegistrationService::sendVerificationEmail` converts that to a thrown `RuntimeException`, breaking registration end-to-end. Templates `registration_verification`, `registration_account_verified`, `auth_password_reset`, `registration_invitation` have no INSERT rows in any migration. Add a seed migration.
- [ ] [Review][Patch] **Verification email link points to `/public/verify-email.php`, file is at `public/coaches/verify-email.php`** [`includes/RegistrationService.php:358`] — every verification link 404s. Fix to `/coaches/verify-email.php` (or full URL via APP_URL). Same issue affects reset link at line 200 (Story 3-5).
- [ ] [Review][Patch] **Registration writes user row before sending verification email; on email failure the row stays** [`includes/RegistrationService.php` `register()`] — squats username + email permanently. Wrap in transaction; rollback on email failure.
- [ ] [Review][Patch] **TOCTOU race on registration uniqueness** [`includes/RegistrationService.php` `register()`] — SELECT-then-INSERT without unique-constraint error handling. Catch PDOException 23000 (duplicate key) and map to `DuplicateUsernameException` / `DuplicateEmailException`. Verify schema has unique indexes on `username` and `email`.
- [ ] [Review][Patch] **`resendVerification(int $userId)` accepts any user_id with no auth/ownership check and reissues tokens for already-active users** [`includes/RegistrationService.php` `resendVerification()`] — combined with the user_id leak in verify-email.php (Story 3-2 finding), allows email-bombing arbitrary coaches and corrupting active accounts. Add status guard (only `unverified` users), and require either an authenticated session or rate-limit by IP+user_id.
- [ ] [Review][Patch] **Email is not normalized in `register()` (no trim/lowercase) but IS in `requestPasswordReset()`** [`includes/RegistrationService.php`] — uniqueness check inconsistent. Normalize the same way in both paths.
- [ ] [Review][Patch] **Username uniqueness depends on DB collation** [`includes/RegistrationService.php` `usernameExists()`] — `Coach1` vs. `coach1` may or may not collide depending on collation. Apply `LOWER()` consistently on read+write or document required collation explicitly.
- [ ] [Review][Patch] **Password complexity error is generic** [`includes/RegistrationService.php` `validatePasswordComplexity()`] — Story 3-2 AC6 requires naming the specific rule violated. Return per-rule messages (or codes) so the page can pick which to show.

### Deferred

- [x] [Review][Defer] **PII (raw email/identifier) logged on every failed auth attempt** [`includes/AuthService.php` `authenticate()`] — deferred, broader audit-log/PII-redaction policy decision spans the whole codebase, not story-specific.
