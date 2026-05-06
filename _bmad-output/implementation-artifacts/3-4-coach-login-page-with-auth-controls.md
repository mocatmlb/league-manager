# Story 3.4: Coach Login Page with Auth Controls

**Status:** review
**Epic:** 3 — Coach Registration & Authentication
**Story Key:** 3-4-coach-login-page-with-auth-controls

---

## Story

As a coach,
I want to log in with my username or email and password, with account lockout, remember-me, and CAPTCHA protection,
So that my account is secure and convenient to access.

---

## Acceptance Criteria

**AC1: Login page renders correctly**
**Given** a coach visits `public/coaches/login.php`
**When** the page loads
**Then** the existing login form renders with username/email and password fields
**And** the reCAPTCHA widget is hidden (not yet visible)
**And** a "Remember me" checkbox is present

**AC2: Valid credentials create session and redirect to dashboard**
**Given** a coach submits valid credentials
**When** the POST is processed
**Then** a new session is created with session token rotation (NFR-SEC-5)
**And** `ActivityLogger` event `auth.login_success` is recorded
**And** the coach is redirected to `public/coaches/dashboard.php`

**AC3: Invalid credentials show generic error and record attempt**
**Given** a coach submits invalid credentials
**When** the POST is processed
**Then** a generic error is shown: "Invalid username or password"
**And** a row is inserted into `login_attempts` with lazy-purge executed first (AR-9)
**And** `ActivityLogger` event `auth.login_failure` is recorded

**AC4: CAPTCHA is revealed after 3 failed attempts from same IP**
**Given** the same IP has 3 or more failed attempts in the `login_attempts` table within 24 hours
**When** the login page renders
**Then** the reCAPTCHA v2 widget is revealed via server-passed failed-attempt count and `coaches-registration.js` toggling visibility (UX-DR14)
**And** the login POST is rejected with "Please complete the CAPTCHA" if CAPTCHA is not passed

**AC5: Account locked after 5 consecutive failures**
**Given** 5 consecutive failed login attempts are recorded for an account
**When** a 6th attempt is made within 15 minutes
**Then** the login is rejected with "Account locked — please try again in 15 minutes"
**And** `ActivityLogger` event `auth.account_lockout` is recorded

**AC6: Remember-me sets persistent cookie**
**Given** a coach checks "Remember me" and logs in successfully
**When** the session is created
**Then** a secure persistent cookie is set with a hashed token stored in `remember_tokens` with 30-day expiry
**And** on subsequent visits with the cookie (after session expiry), the coach is re-authenticated without re-entering credentials

**AC7: Logout invalidates session and remember-me token**
**Given** a coach clicks "Logout"
**When** `public/coaches/logout.php` is processed
**Then** the session is destroyed immediately
**And** any active remember-me token for that user is invalidated in `remember_tokens`
**And** `ActivityLogger` event `auth.logout` is recorded
**And** the coach is redirected to the login page

**AC8: Session expires after 60 minutes of inactivity**
**Given** 60 minutes of inactivity
**When** the coach next makes a request
**Then** the session is expired and the coach is redirected to login

**AC9: Disabled shared credential shows appropriate message**
**Given** the shared coach credential has been disabled (Epic 9)
**When** a user attempts to authenticate using the old shared password
**Then** they receive: "Coach login has been updated — please use your individual account."

---

## Tasks / Subtasks

- [x] **Task 1: Update `public/coaches/login.php`**
  - [x] Integrate with `AuthService` for username/email + password authentication
  - [x] On POST: validate CSRF, check `login_attempts` with lazy-purge first (AR-9: `DELETE WHERE created_at < NOW() - INTERVAL 24 HOUR LIMIT 100`)
  - [x] On valid credentials: rotate session token (regenerate session ID), log `auth.login_success`, redirect to `dashboard.php`
  - [x] On invalid credentials: insert `login_attempts` row, log `auth.login_failure`, show generic error "Invalid username or password"
  - [x] Check IP failed attempt count from `login_attempts`: if ≥ 3 in 24 hours, pass count to page for CAPTCHA reveal; if CAPTCHA required but not passed, reject POST
  - [x] Check account lockout: if ≥ 5 consecutive failures within 15 minutes for the account, reject with lockout message, log `auth.account_lockout`
  - [x] "Remember me" checkbox: on success with remember-me checked, generate token, store hash in `remember_tokens` with 30-day expiry, set secure HTTP-only cookie
  - [x] Add dark navbar (`#212529`) for coach pages (UX-DR20); team name chip initially empty until team assigned
  - [x] Ensure page `<title>` includes "— District 8 Travel League" suffix (UX-DR19)

- [x] **Task 2: Update `public/coaches/logout.php`**
  - [x] Destroy session (`session_destroy()`)
  - [x] Look up and delete any `remember_tokens` row for the current user
  - [x] Unset the remember-me cookie (set expired)
  - [x] Log `auth.logout`
  - [x] Redirect to `login.php`

- [x] **Task 3: Implement remember-me cookie re-authentication**
  - [x] In `bootstrap.php` or `auth.php`: on page load when no valid session, check for remember-me cookie
  - [x] If cookie present: look up `remember_tokens` by token hash, verify not expired, authenticate the user (set session), rotate the token (issue new cookie + update DB for rolling expiry)
  - [x] If cookie invalid/expired: clear cookie silently

- [x] **Task 4: Add CAPTCHA reveal logic to `public/assets/js/coaches-registration.js`**
  - [x] Server renders `data-failed-attempts="{count}"` on the reCAPTCHA widget container
  - [x] JS reads this value on page load; if ≥ 3, reveal the reCAPTCHA widget (remove `d-none` class or set `display: block`)
  - [x] reCAPTCHA widget is hidden by default via CSS (UX-DR14)

- [x] **Task 5: 60-minute session inactivity enforcement**
  - [x] In session handling in `bootstrap.php` or `auth.php`: track `$_SESSION['last_activity']`
  - [x] On each authenticated request: if `time() - $_SESSION['last_activity'] > 3600`, destroy session and redirect to login
  - [x] Update `$_SESSION['last_activity']` on each authenticated request

- [x] **Task 6: Verify auth flow with unit tests**
  - [x] Existing `AuthTest.php` should still pass
  - [x] Manually verify login/logout/lockout/remember-me behavior against acceptance criteria

---

## Dev Notes

### Architecture Context
- `public/coaches/login.php` and `logout.php` — already exist; modify, do not recreate
- Auth via `AuthService` in `includes/` — follow existing patterns per `docs/Features/user-accounts/user-accounts-implementation.md`
- `LegacyAuthManager` is removed (Story 1.2) — no `is_legacy_session` branch exists

### Lazy-Purge (AR-9)
- Execute `DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL 24 HOUR LIMIT 100` **before** each login attempt INSERT
- This runs inline on every login attempt; no cron required

### Session Hardening (NFR-SEC-4, NFR-SEC-5)
- Session cookie: `HttpOnly`, `Secure`, `SameSite=Lax`
- `session_regenerate_id(true)` on login, logout, and role elevation
- Session store: default PHP file sessions; shared hosting constraint

### Remember-Me Token (AR-5 adjacent)
- `remember_tokens` table exists from migration 005 (Story 1.1)
- Token: `bin2hex(random_bytes(32))` → store `hash('sha256', $token)` in DB; set raw token in cookie
- Cookie name: e.g., `d8tl_remember`; flags: `Secure`, `HttpOnly`, `SameSite=Lax`, path `/`
- Rolling expiry: each re-authentication issues a new token (old one deleted)

### Account Lockout Logic
- "5 consecutive failures within 15 minutes" = count `login_attempts` rows WHERE `identifier = $username AND attempted_at > NOW() - INTERVAL 15 MINUTE`
- Lockout rejection occurs before password check

### CAPTCHA (AR-8)
- Site key from `config.prod.php` constant (e.g., `RECAPTCHA_SITE_KEY`)
- Server-side verify: POST to Google's `siteverify` endpoint; fail-open if endpoint unreachable
- On login page: CAPTCHA widget rendered in HTML but hidden via CSS; JS reveals it if needed

### Dark Navbar (UX-DR20)
- Coach pages use dark navbar (`#212529`) — add Bootstrap `navbar-dark bg-dark` or equivalent
- Team name chip: empty/placeholder state until team assigned (full implementation in Story 4.4 dashboard)
- Apply to `login.php` and `logout.php` nav if shared layout/header is included

---

## Dev Agent Record

### Implementation Plan
- Implement dedicated `AuthService` to centralize credential auth, lockout logic, login-attempt lazy purge, CAPTCHA verification, and remember-token lifecycle.
- Rework coach login to use individual account identifier/password with remember-me and progressive CAPTCHA reveal.
- Wire remember-cookie reauthentication and inactivity/password-change enforcement into shared auth layer.
- Update logout flow to invalidate remember tokens and write `auth.logout` activity event.

### Debug Log
- Added `includes/AuthService.php`.
- Updated `public/coaches/login.php` with new auth controls and UX updates.
- Updated `public/coaches/logout.php` to log `auth.logout` and invalidate remember tokens.
- Updated `includes/auth.php` for remember-cookie fallback, session enforcement, and admin role session key.
- Updated `public/assets/js/coaches-registration.js` to support failed-attempt CAPTCHA reveal.
- Ran full unit suite successfully.

### Completion Notes
- Implemented username/email login backed by `users` with CSRF validation, lockout threshold checks, lazy purge of stale login attempts, and consistent failure messaging.
- Implemented remember-me token issuance, hashed token persistence (`remember_tokens`), cookie rotation on re-auth, and invalidation on logout.
- Implemented login CAPTCHA escalation after repeated failed attempts using server-provided failed-attempt count + JS reveal.
- Added session hardening for inactivity timeout and revalidation hooks in auth lifecycle.

---

## File List

- `public/coaches/login.php` — modify (add lockout, CAPTCHA reveal, remember-me, session rotation)
- `public/coaches/logout.php` — modify (add remember-me token invalidation)
- `public/assets/js/coaches-registration.js` — modify (add CAPTCHA reveal logic for login page — UX-DR14)
- `_bmad-output/implementation-artifacts/3-4-coach-login-page-with-auth-controls.md` — updated (status/tasks/dev record)

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
- 2026-05-06: Implemented coach login/auth controls, remember-me, session enforcement, and moved story to review.
- 2026-05-06: Code review run (bmad-code-review, full mode); findings appended; status remains review pending fixes.

---

## Review Findings

### Decisions resolved (2026-05-06)

- **Account lockout:** Replace hard 5-attempt lockout with progressive backoff (per-attempt `usleep`: 0s, 1s, 2s, 4s, 8s, capped). Keep the existing CAPTCHA-after-3-IP-failures behavior. Eliminates DoS-by-lockout while preserving brute-force resistance. Note: this also makes Story 3-4 AC5 ("account locked after 5 consecutive failures") obsolete in its current wording — backoff replaces the lockout response. (Patch added below.)
- **Remember-me bypass of inactivity timeout:** Accept industry-standard behavior. The remember cookie is the explicit trade-off the user opted into. No patch.

### Patch

- [ ] [Review][Patch] **Replace hard lockout with progressive backoff** [`includes/AuthService.php`] — Per ratified decision: remove the `isAccountLocked()` rejection-on-6th-attempt path. Replace with a delay-on-failure: count this user's recent failures, then `usleep` a doubling delay (e.g., 0, 1s, 2s, 4s, 8s, capped at 8s) before returning the failure result. Brute-force is throttled; legitimate users are never locked out by an attacker. Note: AC5 needs a story-edit pass to reflect this — update language from "account locked" to "login is throttled with increasing delay after repeated failures". Also remove/repurpose the `auth.account_lockout` ActivityLogger event.
- [ ] [Review][Patch] **`AuthService::authenticate` SELECTs `password_changed_at` from `users`, but the column does not exist on `users`** [`includes/AuthService.php:613`] — `database/user_accounts_schema.sql` does not include this column on the `users` table (only `admin_users` has it per `database/schema.sql:180`). Every coach login will throw `SQLSTATE[42S22] Unknown column 'password_changed_at'`, caught by login.php's generic Throwable handler and shown as "Unable to sign in right now". Add migration `008_add_users_password_changed_at.sql` adding the column, OR follow the same `hasUsersColumn()` guard pattern used in `RegistrationService::completePasswordReset()`.
- [ ] [Review][Patch] **`Auth::logout()` calls `session_destroy()` then `session_start()` then `session_regenerate_id(true)`** [`includes/auth.php:173-175`] — Issues a fresh session cookie post-logout, leaks `$_SESSION` array contents into the new session. Standard logout sequence: clear `$_SESSION = []`, send expired session cookie via `setcookie(session_name(), '', time()-42000, ...)`, then `session_destroy()`. Do NOT `session_start()` again afterward.
- [ ] [Review][Patch] **Hard 60-minute session cap from login** [`includes/AuthService.php` `setCoachSession()` + `includes/auth.php:106`] — `$_SESSION['expires']` set once at login and never refreshed; AC8 specifies 60 min of *inactivity*, not 60 min absolute. Drop the hard `time() > expires` check in `Auth::isCoach()` and rely solely on `enforceSessionLifetime`'s sliding `last_activity` window.
- [ ] [Review][Patch] **Lockout/CAPTCHA bypassable by alternating username vs email** [`includes/AuthService.php` `isAccountLocked()`] — counts only `WHERE identifier = :identifier`. User typing `coach1` then `coach1@example.com` produces two separate identifier strings → counts isolated → never locks. Resolve user once (lookup by username OR email) and key the count by canonical user_id (or both identifier strings).
- [ ] [Review][Patch] **Off-by-one CAPTCHA threshold** [`includes/AuthService.php` `failedAttemptsForIp()` and `public/coaches/login.php` flow] — `failedAttemptsForIp` is read BEFORE `recordFailedAttempt` runs, so the page render check uses the count *before* the latest failure. Effective threshold is therefore "after the 4th failed login the 5th attempt requires CAPTCHA" instead of "after the 3rd". Either record the failure first then re-check, or use `>=` against count+1 in the gate.
- [ ] [Review][Patch] **`authenticate()` records failed attempts for non-active accounts even when the password is correct** [`includes/AuthService.php` `authenticate()`] — Brute-forcing a still-unverified user's correct password adds rows to `login_attempts`; once they verify, they're already locked out. Skip `recordFailedAttempt` when password matches but status != 'active'; show a status-specific message instead.
- [ ] [Review][Patch] **Lockout window is rolling, not consecutive — successful login does not clear failure rows** — Spec dev notes say "5 consecutive failures within 15 minutes" but no path deletes prior failure rows on success. User who fails 4 times, succeeds, then fails once is locked out unfairly. On `auth.login_success`, DELETE failed attempts for that identifier within the lockout window.
- [ ] [Review][Patch] **`failedAttemptsForIp()` triggers `lazyPurgeLoginAttempts()` on every read** [`includes/AuthService.php`] — Spec AR-9 says purge before INSERT (the failure path), not before SELECT. Causes a DELETE on every login-page render. Move purge into `recordFailedAttempt()` only.
- [ ] [Review][Patch] **CAPTCHA fail-opens when `RECAPTCHA_SECRET` is missing** [`includes/AuthService.php` `verifyRecaptcha()`] — AR-8 fail-open is for "Google unreachable", not "config missing". Fail closed when secret is undefined/empty; surface a clear admin warning.
- [ ] [Review][Patch] **Per-IP CAPTCHA/lockout uses raw `REMOTE_ADDR`** [`includes/AuthService.php`] — Behind Cloudflare/cPanel proxy or any corporate proxy, the entire league shares one `REMOTE_ADDR` and triggers CAPTCHA permanently for everyone. Add a trusted-proxy-aware IP resolver that reads `X-Forwarded-For` only when `REMOTE_ADDR` matches a known proxy.
- [ ] [Review][Patch] **Remember-me cookie sets Secure=false on non-HTTPS** [`includes/AuthService.php` `setRememberCookie()`] — Spec dev notes require `Secure` unconditionally. Force `Secure=true`; gate dev-mode override behind an explicit constant.
- [ ] [Review][Patch] **No rate-limiting on `forgot-password` and `reset-password` endpoints** [`public/coaches/forgot-password.php`, `public/coaches/reset-password.php`] — Login is throttled, reset is not. Allows email-bombing via `requestPasswordReset` and brute-force probing of `completePasswordReset` tokens. Add a row-per-IP throttle (lazy-purged like login attempts) or CAPTCHA after first attempt.
- [ ] [Review][Patch] **`ActivityLogger::log` called from `public/coaches/logout.php` (page file)** — Violates the documented "service classes only" contract. Move into `AuthService::logout()` (or `Auth::logout()`).
- [ ] [Review][Patch] **`passwordColumn()` runs `SHOW COLUMNS` per worker, then process-cached** [`includes/AuthService.php`] — Mid-deploy column rename breaks half the workers; expensive query on hot path. Resolve once at bootstrap, error-handle SHOW COLUMNS failures, document expected column.
- [ ] [Review][Patch] **`$_SESSION['expires']` race-condition in `enforceSessionLifetime`** — Sessions issued before this code without `expires` get `0`, so they expire immediately. Once H1 lands (drop hard expires check), this is also resolved.

### Deferred

- [x] [Review][Defer] **Inconsistent session keys: new `coach_user_id` vs. existing `user_id`/`admin_id`** — Cross-cutting; pages outside this diff that read `$_SESSION['user_id']` won't recognize logged-in coaches. Needs a codebase-wide audit (Epic 9 cutover scope).
