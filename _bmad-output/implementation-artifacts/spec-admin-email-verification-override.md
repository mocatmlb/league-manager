---
title: 'Admin Email Verification Override'
type: 'feature'
created: '2026-05-10'
status: 'done'
baseline_commit: '548ec76c860eb11e6851380fac74256740353c92'
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Admins have no way to unblock a coach whose email verification is stuck — the only path today is for the coach to click the verification link themselves, but that link may have expired, gone to spam, or the coach may be unreachable.

**Approach:** Add two admin-only actions to the existing user detail page for `unverified` accounts: (1) Force Verify — immediately marks the account active and clears the token; (2) Resend Verification Email — issues a fresh token and re-sends the email to the coach.

## Boundaries & Constraints

**Always:**
- Both actions are only visible and functional when `$userStatus === 'unverified'`
- Force-verify sets `status = 'active'`, clears `verification_token` and `verification_expiry`, logs to ActivityLogger
- Both actions use the existing CSRF token + PRG pattern from Story 8.3
- Force-verify goes in `UserManagementService` (works by user ID, consistent with that service's interface)
- Resend delegates to `RegistrationService::resendVerification(string $email)` using the user's email already fetched on the detail page

**Ask First:**
- Should force-verify trigger the `registration_account_verified` admin notification email (same as self-service verify)? Default assumption: yes, for audit consistency — halt if this is wrong.

**Never:**
- Do not apply these actions to `active` or `disabled` accounts
- Do not add new DB columns or migrations — no schema change needed
- Do not create a new page; extend `detail.php` in place

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Force verify happy path | User status = `unverified`, admin submits force-verify POST | Status → `active`, token cleared, activity logged, flash "Account verified." | N/A |
| Force verify wrong state | User status = `active` or `disabled` | Button not rendered; POST guard rejects with flash "Action not applicable." | Defensive `if` check before DB update |
| Resend verify happy path | User status = `unverified`, admin submits resend POST | Fresh token written, verification email sent, flash "Verification email sent." | N/A |
| Resend email failure | SMTP error in `RegistrationService::resendVerification` | Catch `RuntimeException`, flash "Failed to send verification email — check mail settings." | try/catch around resend call |
| Resend on active/disabled | Wrong status | Button not rendered; POST guard rejects with flash "Action not applicable." | Same defensive guard |

</frozen-after-approval>

## Code Map

- `includes/UserManagementService.php` — add `forceVerify(int $userId): void`; mirrors disable/enable pattern; calls ActivityLogger
- `includes/RegistrationService.php` — `resendVerification(string $email)` already exists; no changes needed
- `public/admin/users/detail.php` — add two POST action handlers (`force_verify`, `resend_verification`) and the corresponding UI buttons in the Account Actions card; both guarded by `$userStatus === 'unverified'`
- `tests/unit/UserManagementServiceTest.php` — add unit tests for `forceVerify`

## Tasks & Acceptance

**Execution:**
- [x] `includes/UserManagementService.php` -- add `public function forceVerify(int $userId): void` that UPDATEs `status='active'`, `verification_token=NULL`, `verification_expiry=NULL` WHERE `id=:id AND status='unverified'`; throws `RuntimeException` if rowCount !== 1; calls `ActivityLogger::log('registration.account_verified', ['user_id' => $userId])`
- [x] `public/admin/users/detail.php` -- add `force_verify` POST action: validate CSRF, call `$userMgmt->forceVerify($userId)`, set flash "Account verified.", PRG redirect; add `resend_verification` POST action: validate CSRF, call `$registration->resendVerification($user['email'])` inside try/catch, flash success or error, PRG redirect; inject `RegistrationService` instance at top of file alongside existing services
- [x] `public/admin/users/detail.php` -- add UI section inside Account Actions card (visible only when `$userStatus === 'unverified'`): "Force Verify" button (`btn-success`) with `confirm()` dialog; "Resend Verification Email" button (`btn-outline-primary`) with no confirm required
- [x] `tests/unit/UserManagementServiceTest.php` -- add tests: force-verify transitions `unverified → active`; force-verify on `active` user throws `RuntimeException`; force-verify on non-existent user throws `RuntimeException`

**Acceptance Criteria:**
- Given a user with status `unverified`, when the admin clicks "Force Verify" and confirms, then the account status changes to `active`, the verification token is cleared, an activity log entry is created, and the page reloads with flash "Account verified."
- Given a user with status `unverified`, when the admin clicks "Resend Verification Email", then the coach receives a fresh verification email and the page reloads with flash "Verification email sent."
- Given a user with status `active` or `disabled`, when the detail page loads, then neither "Force Verify" nor "Resend Verification Email" buttons are rendered.
- Given a resend action when SMTP fails, when the POST is processed, then a flash error "Failed to send verification email — check mail settings." is shown and no status change occurs.

## Spec Change Log

**2026-05-10 — Review patches (no loopback):**

- **Admin notification email missing**: Added `RegistrationService::notifyAdminOfVerification()` (public wrapper) and called it from `detail.php` in the `force_verify` branch after DB update.
- **RegistrationService unconditional construction**: Removed shared instantiation; each branch now lazy-loads only when needed.
- **Dead test variable**: Removed unused `$db`/`$email` in non-existent-user test.

KEEP: `forceVerify` SQL `WHERE id=:id AND status='unverified'` is the true DB-level gate; both branches use lazy RegistrationService construction.

## Design Notes

`RegistrationService` is already constructed with `$db` and `$emailService` in registration pages. The admin detail page will need to instantiate it (or accept it as a dependency). Follow the same pattern used at the top of `detail.php` for `UserManagementService` — check how that service is constructed and mirror it.

`forceVerify` should NOT call `sendAdminNotification` directly (that's a private `RegistrationService` method). Instead, log `registration.account_verified` to ActivityLogger. If the "Ask First" admin notification email question resolves to yes, a follow-up task adds a call to an appropriate public `RegistrationService` method.

## Verification

**Commands:**
- `php tests/unit/run-unit-tests.php` -- expected: all tests pass including new `forceVerify` tests

**Manual checks (if no CLI):**
- On `detail.php?id={unverified_user_id}`: confirm both buttons appear in Account Actions
- Submit Force Verify: status badge changes to "Active", flash "Account verified." shown
- Submit Resend Verification Email: flash "Verification email sent." shown, check mail log
- On `detail.php?id={active_user_id}`: confirm neither new button appears

## Suggested Review Order

**Service layer — core logic**

- New `forceVerify` method: SQL gates on `status='unverified'`, rowCount validates atomicity, ActivityLogger records it.
  [`UserManagementService.php:197`](../../includes/UserManagementService.php#L197)

- New `notifyAdminOfVerification` public wrapper delegates to private `sendAdminNotification`.
  [`RegistrationService.php:188`](../../includes/RegistrationService.php#L188)

**Controller — POST handlers**

- `force_verify` branch: status guard → forceVerify → lazy-load RegistrationService → notify → flash → PRG.
  [`detail.php:237`](../../public/admin/users/detail.php#L237)

- `resend_verification` branch: status guard → lazy-load RegistrationService → resendVerification → flash → PRG.
  [`detail.php:256`](../../public/admin/users/detail.php#L256)

**UI — buttons**

- Both buttons rendered only inside `$userStatus === 'unverified'` guard; Force Verify has JS confirm, Resend does not.
  [`detail.php:623`](../../public/admin/users/detail.php#L623)

**Tests**

- Three `forceVerify` unit tests: happy path SQL inspection, active-user throws, non-existent-user throws.
  [`UserManagementServiceTest.php:950`](../../tests/unit/UserManagementServiceTest.php#L950)
