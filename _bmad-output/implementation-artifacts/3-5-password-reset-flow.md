# Story 3.5: Password Reset Flow

**Status:** review
**Epic:** 3 — Coach Registration & Authentication
**Story Key:** 3-5-password-reset-flow

---

## Story

As a coach,
I want to reset my password via a time-limited email link,
So that I can regain access to my account if I forget my password.

---

## Acceptance Criteria

**AC1: Forgot password request sends reset email**
**Given** a coach submits their email on the "Forgot Password" page
**When** `RegistrationService::requestPasswordReset(string $email)` is called
**Then** a unique reset token with 24-hour expiry is generated and stored
**And** a password reset email is sent with the reset link (blocking — failure surfaces error)
**And** `ActivityLogger` event `auth.password_reset_requested` is recorded
**And** if the email does not match any account, the same "check your email" confirmation is shown (no account enumeration)

**AC2: Valid reset link shows new password form**
**Given** a coach clicks a valid, unexpired reset link
**When** the reset form page loads
**Then** a form is displayed with new password and confirm password fields

**AC3: Valid new password updates and redirects to login**
**Given** the coach submits a valid new password (meeting FR-REG-5 complexity)
**When** the POST is processed
**Then** the password is updated as a bcrypt hash
**And** the reset token is consumed
**And** all active sessions for the user are invalidated
**And** `ActivityLogger` event `auth.password_reset_completed` is recorded
**And** the coach is redirected to login with a flash: "Password updated — please log in"

**AC4: Expired reset link shows expiry page**
**Given** an expired reset token (> 24 hours old)
**When** the coach clicks the link
**Then** an "This link has expired" page is shown with a link to request a new reset

**AC5: Account enumeration is prevented**
**Given** a coach submits an email address that does not match any account
**When** the "Forgot Password" form is submitted
**Then** the same "check your email" confirmation message is shown as for a valid email
**And** no indication is given whether an account exists for that email

**AC6: Accessibility baseline met on both pages**
**Given** either the forgot-password or reset-password page is rendered
**Then** all inputs have explicit `<label for="">` and meet UX-DR19 standards
**And** page `<title>` includes "— District 8 Travel League" suffix

---

## Tasks / Subtasks

- [x] **Task 1: Add password reset methods to `includes/RegistrationService.php`**
  - [x] Implement `requestPasswordReset(string $email): void`
    - [x] Look up user by email silently (no error if not found)
    - [x] If found: generate token `bin2hex(random_bytes(32))`, store with `expires_at = NOW() + INTERVAL 24 HOUR`, linked to `user_id`
    - [x] Send blocking reset email with link: `public/coaches/reset-password.php?token={token}`
    - [x] Log `auth.password_reset_requested`
    - [x] Whether or not account exists, do nothing special — caller always shows "check your email"
  - [x] Implement `completePasswordReset(string $token, string $newPassword): void`
    - [x] Look up token; throw `ExpiredTokenException` if not found or expired
    - [x] Validate `$newPassword` against FR-REG-5 complexity; throw `WeakPasswordException` if fails
    - [x] Hash new password with `password_hash($newPassword, PASSWORD_BCRYPT)`
    - [x] Update user's `password` column
    - [x] Consume (delete) the token
    - [x] Invalidate all active sessions for the user (e.g., delete from session store or set a `force_logout` flag)
    - [x] Log `auth.password_reset_completed`

- [x] **Task 2: Implement `public/coaches/forgot-password.php`**
  - [x] Render "Forgot Password" form: email input, CSRF token, submit button
  - [x] Handle POST: validate CSRF, call `RegistrationService::requestPasswordReset()`
  - [x] PRG redirect to a "check your email" confirmation page (or re-render with flash)
  - [x] Always show the same confirmation regardless of whether email matched an account (AC5)
  - [x] Add "Forgot Password?" link to `public/coaches/login.php`

- [x] **Task 3: Implement `public/coaches/reset-password.php`**
  - [x] On GET: validate `?token=...` query param; if invalid or expired show "link expired" page with link to `forgot-password.php`
  - [x] On valid token: render form with new password and confirm password fields, CSRF token, hidden token field
  - [x] Handle POST: validate CSRF, validate token still valid, validate passwords match, call `RegistrationService::completePasswordReset()`
  - [x] On success: PRG redirect to `login.php` with flash success "Password updated — please log in"
  - [x] On `WeakPasswordException`: re-render form with inline error, preserve token in hidden field

- [x] **Task 4: Verify in test suite**
  - [x] Add test cases to `RegistrationServiceTest.php` for `requestPasswordReset` and `completePasswordReset`
  - [x] Test: requestPasswordReset for unknown email does not throw and records nothing harmful
  - [x] Test: completePasswordReset with valid token updates password hash
  - [x] Test: completePasswordReset throws `ExpiredTokenException` for expired token
  - [x] Test: completePasswordReset throws `WeakPasswordException` for non-compliant password

---

## Dev Notes

### Architecture Context
- Methods added to existing `RegistrationService.php` (Story 3.1 created base class)
- New page files in `public/coaches/` — include bootstrap, define `D8TL_APP`
- No `PermissionGuard` on forgot/reset pages — publicly accessible (unauthenticated)

### Token Storage
- Check `database/user_accounts_schema.sql` for existing `password_reset_tokens` table or column on `users`
- Expected: `id`, `user_id INT UNSIGNED`, `token_hash VARCHAR(64)`, `expires_at DATETIME`, `created_at DATETIME`
- One active reset token per user at a time; replace on repeated requests

### Session Invalidation
- On `completePasswordReset()`: PHP file sessions make per-user invalidation tricky
- Approach: add a `password_changed_at` timestamp to `users` and check it on each authenticated request — if session timestamp < `password_changed_at`, force logout
- OR: store session IDs per user (complex) — use the timestamp approach for shared hosting simplicity

### Email Classification (AR-12)
- Password reset email: **blocking** — surface failure to user

### Password Complexity (FR-REG-5)
- Same rules as registration: ≥8 chars, one uppercase, one number, one special character
- Reuse the same validation logic from `RegistrationService::register()` (extract to private method)

### No Account Enumeration (AC5)
- `requestPasswordReset()` should have consistent response time regardless of whether email matched — consider `usleep()` or simply not branching the response

---

## Dev Agent Record

### Implementation Plan
- Extend `RegistrationService` with explicit password-reset request and completion methods using existing users token fields.
- Build unauthenticated forgot/reset pages with CSRF, PRG, and non-enumerating response behavior.
- Reuse FR-REG-5 password complexity validator through dedicated `WeakPasswordException` path.
- Add unit tests for unknown-email safety and reset token/password edge cases.

### Debug Log
- Updated `includes/RegistrationService.php` with `requestPasswordReset()` and `completePasswordReset()`.
- Added `public/coaches/forgot-password.php`.
- Added `public/coaches/reset-password.php`.
- Added/updated reset tests in `tests/unit/RegistrationServiceTest.php`.
- Ran full unit suite successfully.

### Completion Notes
- Added blocking password reset email request flow with silent handling for unknown emails to prevent account enumeration.
- Added reset completion flow with expiry validation, password complexity validation, token consumption, bcrypt update, remember-token invalidation, and activity logging.
- Added forgot/reset coach pages with CSRF protection and login redirect flash on successful reset.
- Added service-level tests covering reset success, expired token handling, weak password handling, and unknown-email request behavior.

---

## File List

- `public/coaches/forgot-password.php` — new
- `public/coaches/reset-password.php` — new
- `includes/RegistrationService.php` — modify (add `requestPasswordReset()` and `completePasswordReset()` methods)
- `tests/unit/RegistrationServiceTest.php` — modify (password reset tests)
- `_bmad-output/implementation-artifacts/3-5-password-reset-flow.md` — updated (status/tasks/dev record)

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
- 2026-05-06: Implemented password reset flow/pages/tests and moved story to review.
- 2026-05-06: Code review run (bmad-code-review, full mode); findings appended; status remains review pending fixes.

---

## Review Findings

### Patch

- [ ] [Review][Patch] **Reset email link is `/public/coaches/reset-password.php`** [`includes/RegistrationService.php:200`] — Production docroot IS `public/`, so `/public/...` produces 404. Should be `/coaches/reset-password.php` or fully qualified via APP_URL. Same defect category as Story 3-1 verification link (C3).
- [ ] [Review][Patch] **`completePasswordReset` does NOT regenerate the calling browser's session ID** [`includes/RegistrationService.php` `completePasswordReset()`] — Combined with `password_changed_at` column missing on `users` (Story 3-4 finding), the spec's "all active sessions invalidated" behavior is currently a no-op. After Story 3-4's column-add patch lands, also call `session_regenerate_id(true)` in the page handler on success.
- [ ] [Review][Patch] **Account-enumeration timing leak** [`includes/RegistrationService.php` `requestPasswordReset()`] — Unknown email returns immediately at the user-not-found check; known email runs `generateToken`, executes UPDATE, sends blocking email. Response-time difference is large. Spec dev notes flag this. Equalize with a constant-time path: e.g., always run a dummy `password_hash` and `usleep(rand(150_000, 350_000))` on the unknown-email branch.
- [ ] [Review][Patch] **`requestPasswordReset` throws `RuntimeException('Failed to send password reset email.')` on email failure for known emails only** [`includes/RegistrationService.php` ~line 1357] — leaks account existence (unknown emails return silently; known emails throw on email backend failure → page shows generic error). AC5 says "the same 'check your email' confirmation message is shown" regardless. Catch and log the email failure, then return without throwing on this code path.
- [ ] [Review][Patch] **`reset-password.php` validates token twice with race window** [`public/coaches/reset-password.php`] — Page does its own SELECT for `$expired`, then service re-validates. Between the two checks the token can be consumed; user-facing error becomes generic "Unable to reset password right now" masking "already used". Single source of truth: let service throw a typed exception (`ConsumedTokenException`) the page can map to a clear message.
- [ ] [Review][Patch] **Sessions issued before any `password_changed_at` column migration will have empty stored timestamp** [`includes/AuthService.php` `enforceSessionLifetime()`] — Once Story 3-4's column-add migration lands, existing sessions stored `coach_password_changed_at` as `''` and the comparison `(string) … !== (string) …` becomes a permanent bypass. Treat empty-stored as "force re-auth on next request" or normalize on read.

### Deferred

- [x] [Review][Defer] **Plaintext `users.password_reset_token` storage** — Covered by the cross-story decision-needed in Story 3-1 (token hashing).
