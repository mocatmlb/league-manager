# Story 7.2: Coach Profile Page

**Status:** ready-for-dev
**Epic:** 7 — Coach Profile, Team Schedule & Authenticated Resources
**Story Key:** 7-2-coach-profile-page

---

## Story

As an authenticated coach,
I want to update my name, phone numbers, and password from a profile page,
so that my account information stays current and my password remains secure.

---

## Acceptance Criteria

**AC1: Page loads with current data for any authenticated coach**
**Given** an authenticated coach navigates to `public/coaches/profile.php`
**When** the page loads
**Then** the page passes the auth gate (any logged-in coach — 'coach' OR 'team_owner' session role)
**And** the form displays current values for: first name, last name, preferred name, primary phone + type, secondary phone + type
**And** the team name (if assigned) is shown as a read-only field with label "Team Name (managed by admin)"
**And** a separate "Change Password" section is shown with: current password, new password, confirm new password

**AC2: Profile update (name + phone) uses PRG**
**Given** the coach submits the profile form with valid name fields
**When** the POST is processed (CSRF validated, action='update_profile')
**Then** `ProfileService::updateName($userId, $nameData)` is called
**And** if primary phone is non-empty, `ProfileService::updatePhone($userId, $phone, $type, 'primary')` is called
**And** if secondary phone is non-empty, `ProfileService::updatePhone($userId, $phone, $type, 'secondary')` is called
**And** if secondary phone was cleared, `ProfileService::removeSecondaryPhone($userId)` is called
**And** a PRG redirect occurs with `$_SESSION['flash_success'] = 'Profile updated.'`

**AC3: Password change uses PRG**
**Given** the coach submits the Change Password form with correct current password, matching new + confirm, passing complexity
**When** the POST is processed (action='change_password')
**Then** `ProfileService::changePassword($userId, $currentPassword, $newPassword)` is called
**And** a PRG redirect occurs with `$_SESSION['flash_success'] = 'Password changed.'`

**AC4: IncorrectCurrentPasswordException renders inline error**
**Given** the coach submits an incorrect current password
**When** `ProfileService::changePassword()` throws `IncorrectCurrentPasswordException`
**Then** the page re-renders (no redirect) with `alert alert-danger`: "Current password is incorrect."
**And** all password inputs are empty (never re-populate from POST)

**AC5: WeakPasswordException renders inline error**
**Given** the new password fails complexity
**When** `ProfileService::changePassword()` throws `WeakPasswordException`
**Then** the page re-renders with `alert alert-danger` showing the exception message
**And** password inputs are empty

**AC6: New password / confirm mismatch caught before service call**
**Given** new password and confirm password do not match
**When** the POST handler checks them client-side is bypassed
**Then** the page re-renders with `alert alert-danger`: "New passwords do not match."
**And** `ProfileService::changePassword()` is NOT called

**AC7: CSRF failure redirects with flash error**
**Given** the CSRF token is missing or invalid on either form
**When** the POST is processed
**Then** PRG redirect with `$_SESSION['flash_error'] = 'Invalid form submission. Please try again.'`

---

## Tasks / Subtasks

- [ ] **Task 1: Update `PermissionGuard::requireRole()` to support 'user' minimum-level check**
  - [ ] Edit `includes/PermissionGuard.php`
  - [ ] Current code does exact match: `if ($sessionRole !== $role)` — fails for 'user' since sessions hold 'coach' or 'team_owner', never 'user'
  - [ ] Add a static role-hierarchy map and check against it:
    ```php
    private static array $ROLE_SATISFIES = [
        'user'       => ['coach', 'team_owner', 'team_official', 'administrator'],
        'team_owner' => ['team_owner', 'administrator'],
        'admin'      => ['administrator'],
    ];
    public static function requireRole(string $role, string $loginUrl = '/public/coaches/login.php'): void {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        $sessionRole = $_SESSION['role'] ?? null;
        $allowed = self::$ROLE_SATISFIES[$role] ?? [$role];
        if (!in_array($sessionRole, $allowed, true)) {
            header('Location: ' . $loginUrl);
            exit;
        }
    }
    ```
  - [ ] Backward compatibility: `requireRole('team_owner')` still requires session role = 'team_owner' (only 'team_owner' or 'administrator' pass — existing pages are unaffected)
  - [ ] Run `php tests/unit/run-unit-tests.php --file=PermissionGuardTest.php` after the change to verify no regressions; add a test case for `requireRole('user')` accepting 'coach' and 'team_owner' if the test file doesn't cover it

- [ ] **Task 2: Bootstrap, auth, and POST handling in `public/coaches/profile.php`**
  - [ ] Bootstrap (env-loader pattern — match `schedule-change.php`, NOT the old try/catch from `dashboard.php`):
    ```php
    require_once __DIR__ . '/../../includes/env-loader.php';
    require_once EnvLoader::getPath('includes/coach_bootstrap.php');
    require_once EnvLoader::getPath('includes/PermissionGuard.php');
    require_once EnvLoader::getPath('includes/RegistrationService.php');  // WeakPasswordException
    require_once EnvLoader::getPath('includes/ProfileService.php');
    ```
  - [ ] Auth: `PermissionGuard::requireRole('user', '/coaches/login.php')`
  - [ ] `$db = Database::getInstance(); $userId = (int) ($_SESSION['coach_user_id'] ?? 0); $service = new ProfileService($db);`
  - [ ] **POST — action='update_profile':**
    - Validate CSRF: `Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')` — fail → PRG with `$_SESSION['flash_error']`
    - `$nameData = ['first_name' => trim($_POST['first_name'] ?? ''), 'last_name' => trim($_POST['last_name'] ?? ''), 'preferred_name' => trim($_POST['preferred_name'] ?? '')]`
    - `$primaryPhone = trim($_POST['primary_phone'] ?? ''); $primaryType = $_POST['primary_type'] ?? '';`
    - `$secondaryPhone = trim($_POST['secondary_phone'] ?? ''); $secondaryType = $_POST['secondary_type'] ?? '';`
    - try/catch block: call `$service->updateName($userId, $nameData)`, then phone logic (see AC2), then PRG; catch `Throwable` → set `$error`, fall through
  - [ ] **POST — action='change_password':**
    - Validate CSRF — fail → PRG with flash_error
    - `$currentPassword = $_POST['current_password'] ?? ''; $newPassword = $_POST['new_password'] ?? ''; $confirm = $_POST['confirm_password'] ?? '';`
    - If `$newPassword !== $confirm`: `$error = 'New passwords do not match.';` fall through (no service call)
    - Otherwise: try `$service->changePassword($userId, $currentPassword, $newPassword)` → PRG with flash_success
    - `IncorrectCurrentPasswordException` → `$error = 'Current password is incorrect.'`
    - `WeakPasswordException $e` → `$error = $e->getMessage()`
    - `Throwable` → `$error = 'Password change failed — please try again.'`
    - All exception paths fall through to re-render; NEVER put `$currentPassword`/`$newPassword` back into `$_POST` or any template variable

- [ ] **Task 3: GET — load page data**
  - [ ] Read + clear flash:
    ```php
    $flashSuccess = $_SESSION['flash_success'] ?? ''; unset($_SESSION['flash_success']);
    $flashError   = $_SESSION['flash_error']   ?? ''; unset($_SESSION['flash_error']);
    ```
  - [ ] Load user row: `SELECT first_name, last_name, preferred_name, email FROM users WHERE id = :id`
  - [ ] Load phones: `SELECT phone, type, role FROM user_phones WHERE user_id = :user_id ORDER BY FIELD(role,'primary','secondary')` — iterate to extract `$primaryPhone/$primaryType` and `$secondaryPhone/$secondaryType`; default to `''` if no row for that role
  - [ ] Load team: `SELECT t.team_name FROM teams t JOIN team_owners o ON t.team_id = o.team_id WHERE o.user_id = :id LIMIT 1`
  - [ ] Set nav vars: `$coachName = htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')))` and `$teamName = htmlspecialchars((string) ($teamRow['team_name'] ?? ''))`

- [ ] **Task 4: Render the page (HTML)**
  - [ ] Doctype, Bootstrap 5 + FA CSS CDN (same versions as score-input.php — `bootstrap@5.1.3`, FA `6.0.0`)
  - [ ] Include `coaches_nav.php` (path: `__DIR__ . '/../../includes/coaches_nav.php'`)
  - [ ] `<div class="container mt-4"><div class="row"><div class="col-12">`
  - [ ] Flash alerts (if `$flashSuccess` non-empty → `alert alert-success`; if `$flashError` or `$error` non-empty → `alert alert-danger`) with `role="alert"`
  - [ ] **Profile Info Card** (`<div class="card mb-4">`):
    - Header: "Profile Information"
    - `<form method="POST">` with `<input type="hidden" name="action" value="update_profile">` + CSRF token
    - `<input type="hidden" name="csrf_token" value="...">`
    - Name fields: first_name, last_name (both required), preferred_name (optional) — all `form-control-lg`; all `htmlspecialchars()`-escaped for values
    - Email field: `<input type="email" class="form-control-plaintext" readonly value="<?= htmlspecialchars($user['email'] ?? '') ?>">` with small text "(contact admin to change email)"
    - Team name (if `$teamName !== ''`): `<input type="text" class="form-control-plaintext" readonly value="<?= $teamName ?>">` with label "Team Name (managed by admin)"
    - Primary Phone: two columns — phone input (type="tel", name="primary_phone") + type select (name="primary_type", options: '', Home, Work, Cell) — pre-populated from `$primaryPhone`/`$primaryType`
    - Secondary Phone: same structure (optional) — pre-populated from `$secondaryPhone`/`$secondaryType`; note about "clear to remove"
    - Submit: `<button type="submit" class="btn btn-primary btn-lg">Save Profile</button>`
  - [ ] **Change Password Card** (`<div class="card mb-4">`):
    - Header: "Change Password"
    - `<form method="POST">` with `action='change_password'` hidden input + CSRF
    - current_password (type="password", required, autocomplete="current-password")
    - new_password (type="password", required, autocomplete="new-password")
    - confirm_password (type="password", required, autocomplete="new-password")
    - All three are ALWAYS empty on render — never populated from `$_POST`
    - Submit: `<button type="submit" class="btn btn-warning btn-lg">Change Password</button>`
  - [ ] Inline `<footer class="bg-light mt-5 py-4">` (same pattern as score-input.php)
  - [ ] Bootstrap + FA JS CDN at bottom

- [ ] **Task 5: Verify no regressions**
  - [ ] `php tests/unit/run-unit-tests.php` — all existing tests still pass
  - [ ] Manual browser check: load profile page as a 'coach' role and as a 'team_owner' role user
  - [ ] Test profile update, password change success, wrong current password error, mismatch error

---

## Dev Notes

### PermissionGuard 'user' role is broken — must fix before profile page works

`PermissionGuard::requireRole()` does an exact session role match. Sessions contain `'coach'` (no team assigned) or `'team_owner'` (has a team). The word `'user'` from the epics spec refers to the minimum database role, but it never appears as a session value. Without Task 1's fix, `requireRole('user')` will redirect 100% of visitors to login. This is not optional.

**The fix is backward-safe**: only the new `$ROLE_SATISFIES` map for `'user'` changes behavior. Existing calls to `requireRole('team_owner')` are unchanged (session role must still be exactly `'team_owner'`).

### Load RegistrationService.php to get WeakPasswordException

```php
require_once EnvLoader::getPath('includes/RegistrationService.php');  // defines WeakPasswordException
require_once EnvLoader::getPath('includes/ProfileService.php');        // defines IncorrectCurrentPasswordException; requires WeakPasswordException to exist first
```
Loading ProfileService.php first would cause a fatal error since it `require_once`s RegistrationService internally — but explicit ordering here prevents surprises.

### Phone fields: user_phones table only, not users.phone

Data source for display and update is `user_phones` (created by Story 7.1 migration 015). `users.phone` was the old single-phone column — do NOT read it for the profile form. A coach who registered before the profile page existed will see blank phone fields, which is correct.

### Error vs. flash variable scoping

```
$error (string)     — set by exception handlers in the current request; shown as inline alert in same render
$flashSuccess       — set in $_SESSION before PRG redirect; read and cleared on next GET
$flashError         — set in $_SESSION before PRG redirect (CSRF failures only); read and cleared on next GET
```

On profile update POST exception: set `$error`; then load page data (phones, name, team) fresh from DB before rendering, using `$_POST` values only for the name/phone inputs (so the coach doesn't lose their edits). Do NOT use `$_POST` for the password section under any circumstances.

### Schedule-change.php pre-populates from users.phone — leave it alone

`schedule-change.php` (Story 6.2, done) reads `users.phone` for contact pre-population. DO NOT change that query. The two sources are intentionally separate: the old single-phone column feeds schedule-change.php; the new `user_phones` table feeds the profile page.

### coaches_nav.php expects $coachName and $teamName

```php
$coachName = htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
$teamName  = htmlspecialchars((string) ($teamRow['team_name'] ?? ''));
// Then:
include __DIR__ . '/../../includes/coaches_nav.php';
```
Without these, the nav will show blank coach name and omit the team badge.

### No unit test for this story — it's a UI page

Verification is manual: load the page, submit each form section (success + each error path). Run the full unit test suite to confirm no regressions from the PermissionGuard change.

---

## Files

| File | Action |
|------|--------|
| `includes/PermissionGuard.php` | UPDATE (add $ROLE_SATISFIES map, backward-compatible) |
| `public/coaches/profile.php` | NEW |

**Depends on:** Story 7.1 (ProfileService.php, migrations 014 and 015 must be applied before testing)

---

## Dev Agent Record

### Agent Model Used

claude-sonnet-4-6

### Debug Log References

### Completion Notes List

### File List
