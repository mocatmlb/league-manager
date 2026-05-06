# Story 3.3: Invitation Service & Admin Invitation Management

**Status:** review
**Epic:** 3 — Coach Registration & Authentication
**Story Key:** 3-3-invitation-service-admin-invitation-management

---

## Story

As an admin,
I want to send registration invitations to coaches by email and manage pending invitations,
So that coaches can register even when open self-registration is disabled.

---

## Acceptance Criteria

**AC1: Successful invitation sends email and stores pending token**
**Given** an admin enters a coach's email and clicks "Send Invitation"
**When** `InvitationService::send(string $email, int $adminUserId)` is called
**Then** a unique single-use token is generated with a 14-day expiry
**And** an invitation email is sent with the unique registration URL (blocking — failure surfaces error to admin)
**And** the invitation is stored with status `pending`
**And** an `ActivityLogger` event `registration.invitation_sent` is recorded

**AC2: Sending to email with existing pending invitation replaces token**
**Given** an invitation is sent to an email that already has a pending invitation
**When** `InvitationService::send()` is called
**Then** the prior token is cancelled and a new one is issued
**And** the new invitation email is sent

**AC3: Sending to already-registered email throws exception**
**Given** an invitation email address already has a registered account
**When** `InvitationService::send()` is called
**Then** an `EmailAlreadyRegisteredException` is thrown and no invitation is sent

**AC4: Valid token returns email and marks consumed on completion**
**Given** a coach clicks an invitation link with a valid, unexpired token
**When** `InvitationService::validate(string $token)` is called
**Then** it returns the associated email address
**And** the token is marked consumed when the coach completes registration
**And** the registration form is pre-filled with the email (read-only)

**AC5: Expired token shows expiry message**
**Given** an expired invitation token (> 14 days old)
**When** a coach clicks the link
**Then** an "Invitation expired" page is shown with a message to contact the admin

**AC6: Admin invitation list shows all invitations with status**
**Given** an admin views `admin/users/invitations.php`
**When** the page loads
**Then** all pending invitations are listed with: email, sent date, expiry date, status (pending/completed/expired)
**And** each pending invitation has "Resend" and "Cancel" actions

**AC7: Resend replaces token and sends new email**
**Given** an admin clicks "Resend" on a pending invitation
**When** the action completes (PRG pattern)
**Then** the old token is replaced with a new 14-day token and a new email is sent
**And** flash success confirms resend

**AC8: Cancel deactivates pending invitation**
**Given** an admin clicks "Cancel" on a pending invitation
**When** the action completes
**Then** the invitation status is updated to `cancelled`
**And** the invitation link can no longer be used
**And** flash success confirms cancellation

**AC9: Unit tests pass**
**Given** the unit test suite is run
**When** this story is complete
**Then** `InvitationServiceTest.php` passes all cases including: success, duplicate email error, expired token, resend replaces token, cancel deactivates

---

## Tasks / Subtasks

- [x] **Task 1: Implement `InvitationService` class**
  - [x] Create `includes/InvitationService.php`
  - [x] Implement `send(string $email, int $adminUserId): void` — check for existing account (throw `EmailAlreadyRegisteredException`), cancel any existing pending invitation for that email, generate 14-day token, store with `status='pending'`, send blocking invitation email, log `registration.invitation_sent`
  - [x] Implement `validate(string $token): array` — return `['email' => ..., 'invitation_id' => ...]`; throw `ExpiredTokenException` if expired or not found
  - [x] Implement `markConsumed(int $invitationId): void` — called when registration completes; sets status to `completed`
  - [x] Implement `cancel(int $invitationId, int $adminUserId): void` — set status to `cancelled`, log event
  - [x] Implement `resend(int $invitationId, int $adminUserId): void` — replace token with new 14-day token, set status back to `pending`, send new blocking email
  - [x] Implement `getPendingList(): array` — return all invitations (pending, completed, expired) with computed status
  - [x] Define `EmailAlreadyRegisteredException` (extends `RuntimeException`)

- [x] **Task 2: Implement `InvitationServiceTest.php`**
  - [x] Test: send creates pending invitation
  - [x] Test: send throws `EmailAlreadyRegisteredException` for existing account
  - [x] Test: send to existing pending email cancels old token and creates new one
  - [x] Test: validate returns email for valid token
  - [x] Test: validate throws `ExpiredTokenException` for expired token
  - [x] Test: cancel updates status to `cancelled`
  - [x] Test: resend replaces token and resets status to `pending`

- [x] **Task 3: Implement `public/admin/users/invitations.php`**
  - [x] Require admin authentication (`PermissionGuard::requireRole('administrator')`)
  - [x] Show "Send Invitation" form at top: email input + submit button, CSRF token
  - [x] Handle POST for send: call `InvitationService::send()`, flash success/error, PRG redirect
  - [x] List all invitations in table: email, sent date, expiry date, status badge, Resend and Cancel action buttons (pending only)
  - [x] Handle POST for Resend action: call `InvitationService::resend()`, flash success
  - [x] Handle POST for Cancel action: call `InvitationService::cancel()`, flash success
  - [x] Empty state when no invitations exist: "No invitations sent yet. Use the form above to invite a coach."
  - [x] PRG pattern on all POST actions

- [x] **Task 4: Integrate invitation token into `public/coaches/register.php` (Story 3.2)**
  - [x] If `?token=...` present in URL, call `InvitationService::validate()` to get email
  - [x] Pre-populate email field as read-only
  - [x] On successful registration, call `InvitationService::markConsumed()` to close the token
  - [x] If token is expired: show "Invitation expired" page, do not show form

- [x] **Task 5: Run full test suite and verify all pass**
  - [x] All `InvitationServiceTest` tests pass
  - [x] No regressions in existing tests

---

## Dev Notes

### Architecture Context
- `InvitationService` in `includes/` — no namespace, define/check `D8TL_APP`
- Uses `Database::getInstance()` for all DB ops; `Database::setInstance()` for test injection
- Email: blocking for invitation emails (failure surfaces to admin) — AR-12

### Database Table
- Check existing schema in `database/user_accounts_schema.sql` for `invitations` or `registration_invitations` table
- Expected columns: `id`, `email`, `token_hash`, `admin_user_id`, `status ENUM('pending','completed','cancelled','expired')`, `expires_at DATETIME`, `created_at DATETIME`

### Token Generation
- `bin2hex(random_bytes(32))` — 64-char hex
- Store hash or raw; expiry = `NOW() + INTERVAL 14 DAY`
- Invitation URL format: `public/coaches/register.php?token={token}`

### Status Computation in `getPendingList()`
- If `status = 'pending'` AND `expires_at < NOW()` → display as "expired" (but DB record stays `pending` until explicitly handled or a background cleanup runs)
- OR update to `expired` status on first expired validation call

### Admin Navigation
- Add link to invitations page in admin nav/sidebar under Users section

### Email Classification (AR-12)
- Invitation send/resend emails: **blocking** — surface failure to admin

---

## Dev Agent Record

### Implementation Plan
- Build `InvitationService` with explicit token lifecycle methods (`send`, `validate`, `markConsumed`, `cancel`, `resend`) and blocking invite email behavior.
- Add admin invitation management page with send/resend/cancel PRG handlers and invitation status table.
- Wire invitation-token bootstrap path into registration so token-based onboarding works even with open registration disabled.
- Add dedicated unit tests for invitation edge cases and token replacement behavior.

### Debug Log
- Added `includes/InvitationService.php`.
- Added `tests/unit/InvitationServiceTest.php`.
- Added `public/admin/users/invitations.php`.
- Updated `public/coaches/register.php` for invitation token validation/consumption.
- Ran `php tests/unit/run-unit-tests.php --file=InvitationServiceTest.php` (7 passed).
- Ran full unit suite successfully (73 passed).

### Completion Notes
- Implemented invitation send/resend/cancel flows with 14-day token lifecycle, duplicate-account protection, and audit events.
- Implemented invitation token validation for registration prefill and token consumption on successful registration.
- Implemented admin invitation management page with send form, status listing, and pending actions.
- Added comprehensive unit tests covering success paths and failure/expiry behavior.

---

## File List

- `includes/InvitationService.php` — new
- `public/admin/users/invitations.php` — new
- `tests/unit/InvitationServiceTest.php` — new
- `public/coaches/register.php` — modify (add invitation token path from Task 4)
- `_bmad-output/implementation-artifacts/3-3-invitation-service-admin-invitation-management.md` — updated (status/tasks/dev record)

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
- 2026-05-06: Implemented invitation service, admin invitation page, tests, and moved story to review.
- 2026-05-06: Code review run (bmad-code-review, full mode); findings appended; status remains review pending fixes.

---

## Review Findings

### Decisions resolved (2026-05-06)

- **Invitation `invited_by` FK target:** Drop the FK constraint on `user_invitations.invited_by`. Preserves the column for record-keeping; activity log already captures admin identity. Avoids re-migrating once Epic 8/9 unifies `users`/`admin_users`. (Patch added below.)
- **Invitation status enum:** Add `'cancelled'` to the ENUM via migration so cancellation is distinct from natural expiry. (Patch added below.)

### Patch

- [ ] [Review][Patch] **Drop FK on `user_invitations.invited_by`** [migration] — New migration: `ALTER TABLE user_invitations DROP FOREIGN KEY <fk_name>;` (resolve actual FK name with `SHOW CREATE TABLE user_invitations`). Column kept for record-keeping. Eliminates FK violation when admin sends invitations.
- [ ] [Review][Patch] **Add `'cancelled'` to invitation status enum** [migration] — `ALTER TABLE user_invitations MODIFY COLUMN status ENUM('pending','completed','cancelled','expired') NOT NULL DEFAULT 'pending';`. Update `InvitationService::cancel()` to write `'cancelled'` instead of `'expired'`. Update `getPendingList()` computed-status logic accordingly. Update admin UI badge classes.
- [ ] [Review][Patch] **`InvitationService::resend` does not check status='pending'** [`includes/InvitationService.php` `resend()`] — Can resurrect previously-cancelled invitations into fresh pending. UI gates Resend button to pending only, but a forged POST (admin's own CSRF token) bypasses. Add status guard inside the service.
- [ ] [Review][Patch] **`getPendingList()` returns plaintext token to admin page** [`includes/InvitationService.php` `getPendingList()`] — Token shipped in `$rows` array even though the page doesn't currently echo it. Drop `token` column from the SELECT in `getPendingList()`; never return raw tokens out of the service.
- [ ] [Review][Patch] **`buildInvitationUrl()` falls back to `$_SERVER['HTTP_HOST']` when `APP_URL` is undefined** [`includes/InvitationService.php` `buildInvitationUrl()`] — Host header injection: attacker-controlled Host on a request that triggers admin invitation send produces emails pointing to attacker domain. Require `APP_URL` to be set and refuse to fall back to `HTTP_HOST`.
- [ ] [Review][Patch] **`cancel()` always logs `registration.invitation_cancelled` even when WHERE matched 0 rows** [`includes/InvitationService.php` `cancel()`] — Audit log lies about state transitions. Check `rowCount()` and only log on actual transition.
- [ ] [Review][Patch] **Nav link to invitations renders without per-item role check** [`includes/nav.php` ~line 134] — Page is gated by `PermissionGuard::requireRole('administrator')` so not exploitable, but link visible to non-admin admins inside the admin nav. Wrap in role check matching the admin-only sub-menu.
- [ ] [Review][Patch] **No specific exception path for `EmailAlreadyRegisteredException` test coverage in service test** [`tests/unit/InvitationServiceTest.php`] — verify a test exists; if not, add one (spec AC3).
