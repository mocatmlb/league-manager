# Story 1.2: Remove Legacy Auth

Status: review

## Story

As a developer,
I want `LegacyAuthManager` and all shared-password code paths removed from the codebase,
so that there is no ambiguity about which auth system is active and no dead code to maintain.

## Acceptance Criteria

1. **Given** `LegacyAuthManager.php` exists in `includes/`  
   **When** this story is complete  
   **Then** `LegacyAuthManager.php` no longer exists in the repository  
   **And** no `require` or `require_once` of `LegacyAuthManager.php` exists in any file  
   **And** no reference to `LegacyAuthManager` class exists in any PHP file  
   **And** no reference to `LEGACY_SHARED_PASSWORD` constant exists in `config.php`, `config.prod.php`, `config.staging.php`, or `.env.example`  
   **And** no `is_legacy_session` branch exists in session handling code  
   **And** `coaches_password` is not actively used in any auth flow (the setting may remain in the DB as a disabled record per migration 006, but no code reads it for authentication)

2. **Given** an existing admin or coach session  
   **When** a user visits any protected page after this change  
   **Then** they are authenticated via the standard `AuthService` path without error  
   **And** no PHP warnings or notices are thrown due to missing legacy class references

3. **Given** unit tests previously testing `LegacyAuthManager`  
   **When** this story is complete  
   **Then** those test files are deleted and the test runner reports no failures from missing files

## Tasks / Subtasks

- [x] Remove shared-password coach auth method from `includes/auth.php` (AC: 1)
  - [x] Remove `authenticateCoach()` method that reads `coaches_password` from settings
  - [x] Remove `DEFAULT_COACHES_PASSWORD` usages from the method
- [x] Remove `DEFAULT_COACHES_PASSWORD` constant from `includes/config.php` (AC: 1)
- [x] Remove `DEFAULT_COACHES_PASSWORD` constant from `includes/config.prod.php` (AC: 1)
- [x] Remove `DEFAULT_COACHES_PASSWORD` constant from `includes/config.staging.php` (AC: 1)
- [x] Update `public/coaches/login.php` to remove `Auth::authenticateCoach()` call (AC: 1, 2)
  - [x] Replace single-password coach login with a placeholder that directs to the new `AuthService` flow (or gracefully informs coaches that individual login is required)
- [x] Remove `update_coach_password` handler from `public/admin/settings/index.php` (AC: 1)
  - [x] Remove the case that updates `coaches_password` setting via `updateSetting()`
- [x] Run full test suite and confirm no regressions (AC: 2, 3)

## Dev Notes

### Codebase Analysis (Pre-Implementation)

The codebase does NOT have a `LegacyAuthManager.php` file in `includes/` — it was never created. Instead, the legacy shared-password logic lives directly in the `Auth` class in `includes/auth.php`:

- **`Auth::authenticateCoach()`** — reads `coaches_password` from the `settings` DB table and uses `password_verify()`. This is the shared-password auth method to remove.
- **`Auth::isCoach()`**, **`Auth::requireCoach()`**, **`Auth::isLoggedIn()`** — these methods check `$_SESSION['user_type'] === 'coach'`; they support the legacy session but are also used by the broader app. They must NOT be removed — only the shared-password `authenticateCoach()` method itself is removed.
- **`public/coaches/login.php`** — currently calls `Auth::authenticateCoach($password)`. The login page must be updated to remove this call.
- **`public/admin/settings/index.php`** (line ~101) — has a `case 'update_coach_password'` handler that hashes and saves `coaches_password` to settings. Remove this case.
- **`includes/config.php`** (line 32) — defines `DEFAULT_COACHES_PASSWORD`. Remove this constant.
- **`includes/config.prod.php`** (line 36) — defines `DEFAULT_COACHES_PASSWORD`. Remove this constant.
- **`includes/config.staging.php`** (line 33) — defines `DEFAULT_COACHES_PASSWORD`. Remove this constant.
- No `.env.example` file exists in the project root.
- No `LegacyAuthManager.php` file exists — no file deletion needed.
- No `LegacyAuthManagerTest.php` test file exists — no test deletion needed.
- No `LEGACY_SHARED_PASSWORD` constant exists anywhere.
- No `is_legacy_session` branch exists.

### What "Remove" Means for `coaches/login.php`

The story spec says to remove the `authenticateCoach()` call. The current login page is a single-password shared login. After removal:

- The form POST handler should no longer call `Auth::authenticateCoach()`.
- Per AR-1: `coaches_password` active usage must be removed. The login page should display a message indicating the shared coach password login is being replaced, or simply return an error. 
- Do NOT add `AuthService` integration in this story — that belongs to Epic 3. The login page after this story should gracefully inform the user that this login method is being retired, keeping the page functional but non-authenticating via the old path.

### Settings Page — What to Remove

From `public/admin/settings/index.php`:
- Remove the `case 'update_coach_password':` block (lines ~90-109) from the POST handler switch.
- Leave the existing "Coach Access" section template HTML for now — that will be repurposed in Epic 3.

### Testing

- Run `php tests/unit/run-unit-tests.php` to confirm no regressions.
- No new unit tests are needed for this story — it is purely removal.
- After removal, confirm the app boots without PHP warnings by checking that no `DEFAULT_COACHES_PASSWORD` constant is referenced anywhere in executed code paths.

### Architecture References

- AR-1: `LegacyAuthManager.php` must be deleted and all references removed; shared `coaches_password` credential formally deprecated via migration 006
- Migration 006 in `database/migrations/006_remove_legacy_auth.sql` already records the formal deprecation — no new migration needed.

### Project Context Rules

- PHP 8.1; PDO; no new dependencies.
- Do NOT remove `Auth::isCoach()`, `Auth::requireCoach()`, or `Auth::isLoggedIn()` — these are still used by the app.
- Keep diffs focused — do not remove unrelated code.

## Dev Agent Record

### Agent Model Used

claude-sonnet-4-6 (Cursor Agent)

### Debug Log References

(none yet)

### Completion Notes List

- `LegacyAuthManager.php` did not exist in the codebase — the legacy shared-password logic was embedded directly in `Auth::authenticateCoach()` in `includes/auth.php`. Removed the method entirely.
- `DEFAULT_COACHES_PASSWORD` constant removed from all three config files: `config.php`, `config.prod.php`, `config.staging.php`. No `.env.example` file exists in the project.
- `public/coaches/login.php` — replaced the `Auth::authenticateCoach()` call with a deprecation notice; the form now returns an error message directing coaches to use individual accounts.
- `public/admin/settings/index.php` — removed the `update_coach_password` case from the POST handler switch.
- `public/admin/settings/sections/users-coach.php` — replaced the coach password update form with a retirement notice (the form posted `update_coach_password` which was just removed from the handler).
- No `LegacyAuthManagerTest.php` test file existed — no test deletion needed.
- No `LEGACY_SHARED_PASSWORD` constant or `is_legacy_session` branch existed anywhere.
- 21/21 unit tests pass with zero regressions.
- Final grep confirms zero remaining references to `authenticateCoach`, `DEFAULT_COACHES_PASSWORD`, `LEGACY_SHARED_PASSWORD`, `LegacyAuthManager`, `is_legacy_session`, or `update_coach_password` across all PHP files.

### File List

- `includes/auth.php` — MODIFY (removed `authenticateCoach()` method)
- `includes/config.php` — MODIFY (removed `DEFAULT_COACHES_PASSWORD` constant)
- `includes/config.prod.php` — MODIFY (removed `DEFAULT_COACHES_PASSWORD` constant)
- `includes/config.staging.php` — MODIFY (removed `DEFAULT_COACHES_PASSWORD` constant)
- `public/coaches/login.php` — MODIFY (removed `Auth::authenticateCoach()` call; replaced with deprecation notice)
- `public/admin/settings/index.php` — MODIFY (removed `update_coach_password` POST handler case)
- `public/admin/settings/sections/users-coach.php` — MODIFY (replaced coach password form with retirement notice)

## Change Log

| Date | Change |
|------|--------|
| 2026-05-05 | Story created from epics.md Story 1.2 |
| 2026-05-05 | Story implemented and all ACs satisfied; 21/21 tests passing |
