# Story 6.2: Reschedule Request Page

**Status:** done
**Epic:** 6 — Team-Scoped Reschedule Requests
**Story Key:** 6-2-reschedule-request-page

---

## Story

As a Team Owner coach,
I want to submit a reschedule request for one of my team's games, with my contact info pre-filled,
So that I can request a schedule change without re-entering information I've already provided.

---

## Acceptance Criteria

**AC1: Permission enforced and eligible games loaded**
**Given** a Team Owner coach navigates to `public/coaches/schedule-change.php`
**When** the page loads
**Then** `PermissionGuard::requireRole('team_owner', '/admin/login.php')` is enforced (replaces `Auth::requireCoach()`)
**And** `RescheduleService::getEligibleGames($userId)` builds the game list

**AC2: Empty state when no eligible games**
**Given** zero eligible games exist (all scored or cancelled)
**When** the page loads
**Then** an `alert alert-info` empty state is shown: "No games are available to reschedule — scored and cancelled games are not eligible." (UX-DR16)

**AC3: Game selection reveals detail panel and form**
**Given** the coach selects a game from the dropdown
**When** the selection JS fires
**Then** the Game Detail Reveal Panel (`.game-detail-panel`) shows: current date, time, location (UX-DR5, `aria-live="polite"`)
**And** the request form fields are revealed: new date (required), new time (required), new location (required), reason (required)
**And** the contact info section is pre-populated (read-only) from the coach's profile: full name (`first_name last_name`), primary phone (`users.phone`), email (`users.email`) (UX-DR8)
**And** a "Update in your profile →" link pointing to `profile.php` is shown alongside the pre-populated fields

**AC4: Successful submission with PRG and flash**
**Given** the coach submits the form with all required fields valid and CSRF token present
**When** the POST is processed
**Then** `RescheduleService::submit($userId, $gameId, $requestData)` is called
**And** on success, a PRG redirect occurs (header Location to same page)
**And** the flash `alert alert-success` reads: "Request submitted. You will receive an email when your request is reviewed." (UX-DR17)

**AC5: Error preserves form input**
**Given** a server error occurs during submission (exception thrown)
**When** the error is caught
**Then** an `alert alert-danger` is shown and all entered values are re-rendered into the form fields (UX-DR18)
**And** no PRG redirect occurs — re-render the form with the values intact

**AC6: Pending requests listed with cancel action**
**Given** the coach has submitted reschedule requests
**When** the page renders
**Then** `RescheduleService::getCoachRequests($userId)` is called and the results are displayed in a table below the form: Game #, Date, Requested Date, Status, Actions
**And** rows with status `Pending` show a "Cancel" button
**And** clicking Cancel shows a confirmation: "Cancel this request?"
**And** on confirmation a POST is submitted with `action=cancel` and `request_id`
**And** `RescheduleService::cancel($requestId, $userId)` is called; on success PRG redirect with flash: "Reschedule request cancelled."

**AC7: TeamScopeViolationException returns 403**
**Given** a coach submits a manipulated `game_id` not in their eligible list
**When** `RescheduleService::submit()` throws `TeamScopeViolationException`
**Then** a `403` status is set and an `alert alert-danger` error is shown; no request is saved

---

## Tasks / Subtasks

- [x] **Task 1: Update `public/coaches/schedule-change.php` — bootstrap and auth**
  - [x] Replace bootstrap block (the try/catch path resolver) with the standard pattern:
    ```php
    require_once __DIR__ . '/../../includes/env-loader.php';
    require_once EnvLoader::getPath('includes/coach_bootstrap.php');
    require_once EnvLoader::getPath('includes/PermissionGuard.php');
    require_once EnvLoader::getPath('includes/RescheduleService.php');
    ```
  - [x] Replace `Auth::requireCoach()` with `PermissionGuard::requireRole('team_owner', '/admin/login.php')`
  - [x] Resolve `$currentUser = Auth::getCurrentUser()` and `$userId = (int)($currentUser['id'] ?? 0)`
  - [x] Fetch coach contact info for pre-population: `SELECT first_name, last_name, phone, email FROM users WHERE id = :id`

- [x] **Task 2: POST handling (PRG pattern)**
  - [x] Instantiate `$service = new RescheduleService(Database::getInstance())`
  - [x] Read `$action = $_POST['action'] ?? ''`
  - [x] **Submit path** (`$action === 'submit'`):
    - Validate CSRF; on failure set `$error` and fall through to render
    - Build `$requestData = ['requested_date' => ..., 'requested_time' => ..., 'requested_location' => ..., 'reason' => ...]`
    - Call `$service->submit($userId, (int)$_POST['game_id'], $requestData)` in try/catch
    - `TeamScopeViolationException` → `http_response_code(403)`, set `$error`, fall through (no redirect)
    - `Throwable` → set `$error`, preserve `$_POST` values, fall through
    - On success: `$_SESSION['flash_success'] = 'Request submitted. You will receive an email when your request is reviewed.'` then `header('Location: schedule-change.php'); exit;`
  - [x] **Cancel path** (`$action === 'cancel'`):
    - Validate CSRF
    - Call `$service->cancel((int)$_POST['request_id'], $userId)` in try/catch
    - On success: `$_SESSION['flash_success'] = 'Reschedule request cancelled.'` then redirect
    - `RequestNotCancellableException` or `Throwable`: set `$error`, fall through

- [x] **Task 3: Page data loading**
  - [x] Call `$eligibleGames = $service->getEligibleGames($userId)`
  - [x] Call `$coachRequests = $service->getCoachRequests($userId)`
  - [x] Read-and-clear flash: `$message = $_SESSION['flash_success'] ?? ''; unset($_SESSION['flash_success']);`
  - [x] Build `$locations` list for new location dropdown: `SELECT location_name FROM locations ORDER BY location_name` (reuse existing query)

- [x] **Task 4: Render — form and game detail panel**
  - [x] Flash message: show `alert alert-success` if `$message` set; `alert alert-danger` if `$error` set
  - [x] Empty state: if `$eligibleGames` empty → `alert alert-info` with exact string from AC2; skip the form section
  - [x] Game dropdown: `<select id="game-select">` with each option showing `Game #{game_number} — {game_date} — {away_team_name} @ {home_team_name}`; value = `game_id`; encode game details as `data-*` attributes for JS reveal
  - [x] Game Detail Reveal Panel: `<div class="game-detail-panel" aria-live="polite" style="display:none">` — shows current Date, Time, Location populated by JS on selection (UX-DR5)
  - [x] Request form fields (hidden until game selected, revealed by JS): new date, new time, new location (dropdown from `$locations`), reason textarea — all `required`; hidden `game_id` input populated by JS
  - [x] Contact info section (read-only, pre-populated from `$currentUser`): Name, Phone, Email displayed as static text with `"Update in your profile →"` link to `profile.php` (UX-DR8); do NOT use editable inputs for these
  - [x] CSRF token hidden input; submit button "Request Reschedule"
  - [x] On POST error (`$error` set and `$_POST` contains form values): re-populate new date, time, location, reason from `$_POST` so UX-DR18 is satisfied

- [x] **Task 5: Render — pending requests table**
  - [x] If `$coachRequests` non-empty: render table with columns: Game #, Current Date, Requested Date, Reason, Status badge, Actions
  - [x] Status badges: `Pending` → `badge bg-warning text-dark`, `Approved` → `badge bg-success`, `Denied` → `badge bg-danger`
  - [x] Cancel button (Pending rows only): `<button onclick="return confirm('Cancel this request?')">Cancel</button>` inside inline form with `action=cancel`, `request_id`, CSRF token
  - [x] If `$coachRequests` empty: show nothing (no empty state needed for the requests section)

- [x] **Task 6: JavaScript — game selection reveal**
  - [x] On `#game-select` change: read selected option's `data-*` attributes (date, time, location), populate `.game-detail-panel` fields, set hidden `game_id` input, `display` the panel and form fields
  - [x] On page load with `$_POST['game_id']` preserved (error re-render): auto-trigger the reveal for that game_id
  - [x] Keep JS minimal — no framework, inline `<script>` at bottom of file (existing file uses this pattern)

---

## Dev Notes

### Existing file is a significant rewrite — read it first
`public/coaches/schedule-change.php` (296 lines) uses legacy auth, no team scoping, direct DB inserts, and free-text contact fields. The rewrite replaces the entire POST handler and game query. Do not preserve the legacy `$db->update('games', ['game_status' => 'Pending Change', ...])` — game status is not changed on reschedule request submission in the new system.

### Contact pre-population uses users.phone (single field)
The `users` table currently has a single `phone VARCHAR(20)` column. Multi-phone support (primary/secondary with types) is Epic 7. For 6.2, use `users.phone` directly. Do not query a `user_phones` table — it does not exist yet.

### PermissionGuard path for coaches
Coach pages use `PermissionGuard::requireRole('team_owner', '/admin/login.php')` — not the admin login. Match the pattern from `public/coaches/score-input.php`.

### Current game date/time/location comes from the schedules table — this is correct
`admin/schedules/index.php` keeps `schedules` and `schedule_history` in sync on every approval. `getEligibleGames()` JOINs `schedules` and will always return the current approved schedule. The Game Detail Reveal Panel will therefore always show the live schedule, not a stale one.

### Do not change game_status to 'Pending Change'
The legacy code set `game_status = 'Pending Change'` on submission. The new system does NOT change game status — the request exists in `schedule_change_requests` and the game status is unchanged. Remove that UPDATE entirely.

### requested_by in schedule_change_requests
`RescheduleService::submit()` populates `requested_by` from the user record for email template compatibility. The page does NOT need to collect contact_name/contact_phone/contact_email POST fields — those are all handled inside the service.

### UX-DR5 data attributes pattern
Encode game detail data on the `<option>` elements:
```html
<option value="<?= $g['game_id'] ?>"
        data-date="<?= htmlspecialchars($g['game_date']) ?>"
        data-time="<?= htmlspecialchars($g['game_time']) ?>"
        data-location="<?= htmlspecialchars($g['location'] ?? '') ?>">
  Game #<?= $g['game_number'] ?> — ...
</option>
```

### PRG redirect URL
Use `header('Location: schedule-change.php'); exit;` (relative, same file). Match the pattern from `score-input.php`.

---

## Files

| File | Action |
|------|--------|
| `public/coaches/schedule-change.php` | UPDATE (significant rewrite) |

**Depends on:** Story 6.1 (`RescheduleService.php` and migration 013 must be complete first)

---

## Dev Agent Record

### Review Findings

- [x] [Review][Patch] **schedule-change.php sends role-failed coaches to wrong login** [`public/coaches/schedule-change.php:15`] — Removed second argument from PermissionGuard::requireRole to match score-input.php pattern (defaults to coach login). ✅ Fixed.

- [x] [Review][Patch] **No game_id <= 0 guard before calling submit()** [`public/coaches/schedule-change.php:54`] — Added $gameId <= 0 validation before calling service, matching score-input.php pattern. ✅ Fixed.

---

### Completion Notes

Rewrote `schedule-change.php` from scratch. Replaced legacy try/catch path resolver bootstrap with `env-loader.php` + `EnvLoader::getPath()` pattern. Replaced `Auth::requireCoach()` with `PermissionGuard::requireRole('team_owner', '/admin/login.php')`. Uses `$_SESSION['coach_user_id']` for `$userId` (consistent with score-input.php). Full PRG on success; fall-through re-render preserving `$_POST` on error (UX-DR18). `http_response_code(403)` on `TeamScopeViolationException` (AC7). Game Detail Reveal Panel uses `aria-live="polite"` (UX-DR5). Contact info displayed as static read-only text with "Update in your profile →" link (UX-DR8). Removed legacy `game_status = 'Pending Change'` UPDATE and free-text contact fields entirely. JS is minimal inline at bottom — no framework. Status badges use exact Bootstrap classes from spec. 118/118 unit tests pass.

### Change Log

- 2026-05-09: Rewrote public/coaches/schedule-change.php — all ACs satisfied (depends on 6-1)
