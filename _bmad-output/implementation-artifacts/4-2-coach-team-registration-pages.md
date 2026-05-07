# Story 4.2: Coach Team Registration Pages (Step 2 of Self-Registration)

**Status:** review
**Epic:** 4 — Team Registration & Coach Assignment
**Story Key:** 4-2-coach-team-registration-pages

---

## Story

As a coach,
I want to select a program/season and optionally add home field locations after verifying my email,
So that my team is submitted for admin review and I understand what happens next.

---

## Acceptance Criteria

**AC1: Step 2 page shows program/season list, team name preview, and location repeater**
**Given** a verified coach (status `active`) is logged in and visits `public/coaches/team-register.php`
**When** the page loads
**Then** a "Step 2 of 2: Register Your Team" progress indicator is shown
**And** a list of programs/seasons with open registration is displayed for selection (division field is not shown)
**And** the auto-generated team name preview (`{league_name}-{coach_last_name}`) is shown read-only, updated by JS as the league changes
**And** a home field location repeater is shown with one empty entry block (location name required, address optional)
**And** an "Add Another Location" button is present (disabled when 5 entries exist)

**AC2: "Add Another Location" adds entries up to 5**
**Given** the coach clicks "Add Another Location"
**When** the JS runs (`coaches-registration.js` home field repeater — UX-DR10)
**Then** a new entry block is added up to a maximum of 5
**And** each entry block has a remove button that collapses the block
**And** minimum 1 entry is always visible (remove button hidden on the only remaining entry)

**AC3: Submitting team registration redirects to confirmation**
**Given** the coach submits the team registration form
**When** the POST is processed (PRG pattern)
**Then** `TeamRegistrationService::submit()` is called
**And** the coach is redirected to `public/coaches/team-register-confirm.php`

**AC4: Confirmation page shows appropriate message**
**Given** a coach arrives at `public/coaches/team-register-confirm.php`
**When** the page loads
**Then** the confirmation message reads: "Account created and team registration submitted. An administrator will review your registration and assign you to your team."
**And** no coach-portal nav items requiring Team Owner are accessible

**AC5: Invitation-registered coach sees error**
**Given** a coach who registered via invitation visits `team-register.php` and submits
**When** `TeamRegistrationService::submit()` throws `InvitationRegisteredUserException`
**Then** an error is shown: "Team self-registration is not available for invitation-registered accounts. Contact your administrator."
**And** no team is created

**AC6: Accessibility baseline met**
**Given** the team registration form is rendered
**Then** all inputs have explicit `<label for="">` (UX-DR19)
**And** dynamically added repeater blocks maintain correct `id` and `for` attributes
**And** page `<title>` includes "— District 8 Travel League" suffix

---

## Tasks / Subtasks

- [x] **Task 1: Implement `public/coaches/team-register.php`**
  - [x] Bootstrap with `Auth::requireCoach()` + redirect team owners away to dashboard
  - [x] Fetch current user from DB using `$_SESSION['coach_user_id']`
  - [x] Query available programs/seasons (`season_status = 'Registration'`)
  - [x] Render league selector + JS-driven team name preview
  - [x] Render `.reg-progress` step 2 indicator
  - [x] Render location repeater (1 block shown; "Add Another Location" button)
  - [x] Handle POST: validate CSRF, call `TeamRegistrationService::submit()`, PRG redirect
  - [x] Catch `InvitationRegisteredUserException`, render inline error

- [x] **Task 2: Implement `public/coaches/team-register-confirm.php`**
  - [x] Show confirmation message per AC4
  - [x] No team-owner-restricted nav links
  - [x] Link to public site or login page

- [x] **Task 3: Add home field repeater to `public/assets/js/coaches-registration.js`**
  - [x] ADD new `initHomeFieldRepeater()` function — do NOT remove existing `initLeagueOtherToggle()` or `initLoginCaptchaReveal()` code
  - [x] Add team name preview update function `initTeamNamePreview()`
  - [x] Wire both into the `DOMContentLoaded` handler

- [x] **Task 4: Update `.reg-progress` CSS for step 2 state**
  - [x] Step 1 circle → checkmark (✓) with `step-2-active` state class
  - [x] Bootstrap progress bar at 100%
  - [x] `aria-label="Registration step 2 of 2"` on wrapper; `aria-current="step"` on active step

---

## Dev Notes

### Critical Auth/Session Facts — Read These First

**Do NOT use `PermissionGuard::requireRole('user')`** — the session stores `role = 'coach'` (set in `AuthService`), so this check would redirect every logged-in coach to the login page. Use `Auth::requireCoach()` instead.

**Session keys** (set by `AuthService::login()`):
```php
$_SESSION['coach_user_id']  // int — users.id
$_SESSION['role']           // string — 'coach' (always; NOT 'user' or 'team_owner')
$_SESSION['user_type']      // 'coach'
```

**`Auth::getCurrentUser()` is insufficient** — it returns only `type` and `username` ('Coach' generic). Get real user data with a direct DB query:
```php
$userId = (int) ($_SESSION['coach_user_id'] ?? 0);
$db = Database::getInstance();
$currentCoach = $db->fetchOne(
    'SELECT id, first_name, last_name, email FROM users WHERE id = :id LIMIT 1',
    ['id' => $userId]
);
```

**Redirect team owners away** — if this coach already has a team assigned, redirect to dashboard:
```php
$existingTeam = $db->fetchOne(
    'SELECT team_id FROM team_owners WHERE user_id = :uid LIMIT 1',
    ['uid' => $userId]
);
if ($existingTeam !== false) {
    header('Location: dashboard.php');
    exit;
}
```
Do NOT use `TeamScope::getScopedTeams()` — it contains a schema bug (`o.team_id = t.id` but `teams` PK is `team_id`).

---

### `team-register.php` Page Structure

```php
<?php
try {
    $bootstrapPath = file_exists(__DIR__ . '/../includes/coach_bootstrap.php')
        ? __DIR__ . '/../includes/coach_bootstrap.php'
        : __DIR__ . '/../../includes/coach_bootstrap.php';
    require_once $bootstrapPath;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger">Application error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

Auth::requireCoach();

if (!class_exists('Database')) {
    require_once __DIR__ . '/../../includes/database.php'; // already loaded via bootstrap but guard it
}
if (!class_exists('TeamRegistrationService')) {
    require_once __DIR__ . '/../../includes/TeamRegistrationService.php';
}

$db = Database::getInstance();
$userId = (int) ($_SESSION['coach_user_id'] ?? 0);

// Fetch coach profile
$currentCoach = $db->fetchOne('SELECT id, first_name, last_name, email FROM users WHERE id = :id LIMIT 1', ['id' => $userId]);
if ($currentCoach === false) {
    header('Location: login.php');
    exit;
}

// Redirect if already a team owner
$existingTeam = $db->fetchOne('SELECT team_id FROM team_owners WHERE user_id = :uid LIMIT 1', ['uid' => $userId]);
if ($existingTeam !== false) {
    header('Location: dashboard.php');
    exit;
}

// Load seasons with open registration
$seasons = $db->fetchAll(
    "SELECT s.season_id, s.season_name, s.season_year, p.program_name
     FROM seasons s
     INNER JOIN programs p ON p.program_id = s.program_id
     WHERE s.season_status = 'Registration'
     ORDER BY p.program_name, s.season_year DESC, s.season_name"
);

// Load leagues for the selector
if (!class_exists('LeagueListManager')) {
    require_once __DIR__ . '/../../includes/LeagueListManager.php';
}
$leagues = LeagueListManager::getActiveList();

$pageTitle = 'Register Your Team — District 8 Travel League';
$globalError = '';
$fieldErrors = [];
$formData = ['season_id' => '', 'league_name' => '', 'other_league' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $globalError = 'Form submission error. Please try again.';
    } else {
        $formData['season_id'] = trim((string) ($_POST['season_id'] ?? ''));
        $formData['league_name'] = trim((string) ($_POST['league_name'] ?? ''));
        $formData['other_league'] = trim((string) ($_POST['other_league'] ?? ''));

        if ($formData['season_id'] === '') $fieldErrors['season_id'] = 'Please select a program/season.';
        if ($formData['league_name'] === '') $fieldErrors['league_name'] = 'League selection is required.';
        if ($formData['league_name'] === 'other' && $formData['other_league'] === '') {
            $fieldErrors['other_league'] = 'Enter your league name.';
        }

        // Collect location entries
        $locationNames    = (array) ($_POST['location_name'] ?? []);
        $locationAddresses = (array) ($_POST['location_address'] ?? []);
        $locationNotes    = (array) ($_POST['location_notes'] ?? []);
        $locations = [];
        foreach (array_slice($locationNames, 0, 5) as $i => $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $locations[] = [
                    'name'    => $name,
                    'address' => trim((string) ($locationAddresses[$i] ?? '')),
                    'notes'   => trim((string) ($locationNotes[$i] ?? '')),
                ];
            }
        }

        if ($globalError === '' && empty($fieldErrors)) {
            try {
                $service = new TeamRegistrationService();
                $service->submit($userId, [
                    'season_id'   => (int) $formData['season_id'],
                    'league_name' => $formData['league_name'],
                    'other_league'=> $formData['other_league'],
                    'locations'   => $locations,
                ]);
                header('Location: team-register-confirm.php');
                exit;
            } catch (InvitationRegisteredUserException $e) {
                $globalError = 'Team self-registration is not available for invitation-registered accounts. Contact your administrator.';
            } catch (Throwable $e) {
                $globalError = 'An error occurred. Please try again.';
            }
        }
    }
}
?>
```

---

### HTML Form Key Elements

**Progress indicator** (step 2 active):
```html
<div class="reg-progress step-2-active" aria-label="Registration step 2 of 2">
  <div class="progress mb-3">
    <div class="progress-bar" role="progressbar" style="width:100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
  </div>
  <div class="d-flex justify-content-between">
    <span class="step step-done">✓ Account Created</span>
    <span class="step step-active" aria-current="step">Step 2: Register Your Team</span>
  </div>
</div>
```

**League selector + team name preview**:
```html
<label for="league_name" class="form-label">Your League</label>
<select class="form-select" id="league_name" name="league_name" required>
  <option value="">Select league</option>
  <?php foreach ($leagues as $league): ?>
    <option value="<?php echo sanitize($league['display_name']); ?>"
      <?php echo $formData['league_name'] === $league['display_name'] ? 'selected' : ''; ?>>
      <?php echo sanitize($league['display_name']); ?>
    </option>
  <?php endforeach; ?>
  <option value="other" <?php echo $formData['league_name'] === 'other' ? 'selected' : ''; ?>>Other</option>
</select>

<div id="league-other-container" class="d-none mt-2">
  <label for="other_league" class="form-label">Enter your league name</label>
  <input type="text" class="form-control" id="other_league" name="other_league"
    value="<?php echo sanitize($formData['other_league']); ?>">
</div>

<!-- Team name preview (read-only, updated by JS) -->
<div class="mt-3">
  <label class="form-label">Your Team Name (auto-generated)</label>
  <p class="form-control-plaintext fw-bold" id="team-name-preview">
    <?php
      $leagueForPreview = ($formData['league_name'] === 'other')
        ? $formData['other_league']
        : $formData['league_name'];
      echo sanitize(($leagueForPreview ?: '—') . '-' . $currentCoach['last_name']);
    ?>
  </p>
  <small class="text-muted">Format: {league}-{your last name} (FR-TEAMREG-3, not editable)</small>
</div>
```

**Location repeater** — form field names:
- `location_name[0]`, `location_address[0]`, `location_notes[0]` (NOT `location_details`)
- `location_name[1]` ... up to index 4

**CSRF hidden field**:
```html
<input type="hidden" name="csrf_token" value="<?php echo sanitize(Auth::generateCSRFToken()); ?>">
```

---

### Open Seasons Query

`season_status = 'Registration'` is the correct filter (ENUM value from schema.sql). Display as `"{program_name} — {season_name} {season_year}"`.

---

### `coaches-registration.js` — Add Without Breaking Existing Code

The file already has `initLeagueOtherToggle()` and `initLoginCaptchaReveal()`. ADD these two functions and call them from the existing `DOMContentLoaded` handler:

```js
function initTeamNamePreview() {
    var leagueSelect = document.getElementById('league_name');
    var otherInput   = document.getElementById('other_league');
    var preview      = document.getElementById('team-name-preview');
    var lastName     = preview ? (preview.dataset.lastName || '') : '';

    if (!leagueSelect || !preview) return;

    var update = function () {
        var val = leagueSelect.value === 'other'
            ? (otherInput ? otherInput.value.trim() : '')
            : leagueSelect.value;
        preview.textContent = (val || '—') + (lastName ? '-' + lastName : '');
    };

    leagueSelect.addEventListener('change', update);
    if (otherInput) otherInput.addEventListener('input', update);
}

function initHomeFieldRepeater() {
    var container   = document.getElementById('location-repeater');
    var addBtn      = document.getElementById('add-location-btn');
    var maxEntries  = 5;

    if (!container || !addBtn) return;

    var updateButtons = function () {
        var blocks = container.querySelectorAll('.location-block');
        var count  = blocks.length;
        addBtn.disabled = (count >= maxEntries);
        blocks.forEach(function (block, i) {
            var removeBtn = block.querySelector('.remove-location-btn');
            if (removeBtn) removeBtn.style.display = (count === 1) ? 'none' : '';
            // Update input indices
            block.querySelectorAll('[name]').forEach(function (el) {
                el.name = el.name.replace(/\[\d+\]/, '[' + i + ']');
                el.id   = el.id.replace(/_\d+$/, '_' + i);
            });
            block.querySelectorAll('[for]').forEach(function (el) {
                el.htmlFor = el.htmlFor.replace(/_\d+$/, '_' + i);
            });
        });
    };

    addBtn.addEventListener('click', function () {
        var blocks = container.querySelectorAll('.location-block');
        if (blocks.length >= maxEntries) return;
        var clone = blocks[0].cloneNode(true);
        clone.querySelectorAll('input, textarea').forEach(function (el) { el.value = ''; });
        container.appendChild(clone);
        updateButtons();
    });

    container.addEventListener('click', function (e) {
        if (!e.target.classList.contains('remove-location-btn')) return;
        var blocks = container.querySelectorAll('.location-block');
        if (blocks.length <= 1) return;
        e.target.closest('.location-block').remove();
        updateButtons();
    });

    updateButtons();
}
```

**Wire into DOMContentLoaded** — extend the existing block, don't duplicate it:
```js
document.addEventListener('DOMContentLoaded', function () {
    initLeagueOtherToggle();
    initLoginCaptchaReveal();
    initTeamNamePreview();     // add
    initHomeFieldRepeater();   // add
});
```

**Add `data-last-name` to the preview element** for JS to read:
```html
<p ... id="team-name-preview" data-last-name="<?php echo sanitize($currentCoach['last_name']); ?>">
```

---

### Location Repeater HTML Template (one block)

```html
<div id="location-repeater">
  <div class="location-block card mb-2 p-3">
    <div class="mb-2">
      <label for="location_name_0" class="form-label">Location Name <span class="text-danger">*</span></label>
      <input type="text" class="form-control" id="location_name_0" name="location_name[0]" required>
    </div>
    <div class="mb-2">
      <label for="location_address_0" class="form-label">Address (optional)</label>
      <input type="text" class="form-control" id="location_address_0" name="location_address[0]">
    </div>
    <div class="mb-2">
      <label for="location_notes_0" class="form-label">Additional Details (optional)</label>
      <input type="text" class="form-control" id="location_notes_0" name="location_notes[0]">
    </div>
    <button type="button" class="btn btn-sm btn-outline-danger remove-location-btn" style="display:none">Remove</button>
  </div>
</div>
<button type="button" id="add-location-btn" class="btn btn-outline-secondary btn-sm mt-1">
  + Add Another Location
</button>
```

Note: field names are `location_name[N]`, `location_address[N]`, `location_notes[N]`. The PHP POST handler collects via `$_POST['location_name']`, `$_POST['location_address']`, `$_POST['location_notes']` and maps to `$data['locations'][N]['name']`, `['address']`, `['notes']` for `TeamRegistrationService::submit()`.

---

### `team-register-confirm.php`

Minimal page — no team-owner-gated nav, confirmation message, link to public schedule:
```php
<?php
// Same bootstrap pattern as team-register.php
require_once $bootstrapPath;
Auth::requireCoach();
$pageTitle = 'Registration Submitted — District 8 Travel League';
?>
<!-- Show confirmation message only — no dashboard nav -->
<div class="alert alert-success">
  Account created and team registration submitted. An administrator will review your registration and assign you to your team.
</div>
<a href="/public/index.php" class="btn btn-outline-primary">Return to League Home</a>
```

---

### Schema Facts Relevant to This Story

**`seasons` table**: `season_status ENUM('Planning','Registration','Active','Completed','Archived')`. Query `WHERE season_status = 'Registration'` for the dropdown.

**`programs` table**: `program_id`, `program_name`. JOIN with seasons to build display label.

**`users` table**: `last_name` is `VARCHAR(50) NOT NULL`. Safe to use directly for team name preview.

**`locations` column is `notes`** (not `details`) — POST field `location_notes[N]` maps to it correctly.

---

### Page Asset Pattern (match dashboard.php)

```html
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/css/style.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
```

Script at bottom:
```html
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/coaches-registration.js"></script>
```

Use `<?php include '../../includes/nav.php'; ?>` for navigation (same as dashboard.php). This nav will not show Team Owner options since the coach has no team yet.

---

### What Does NOT Exist Yet

- `includes/TeamRegistrationService.php` — Story 4.1. This story has a hard dependency on 4.1 being merged first. If needed for dev, stub the class with a `submit()` that always throws `RuntimeException`.

---

### CSRF & Output Escaping Pattern

- Verify: `Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')` — same as `register.php:72`
- Generate: `Auth::generateCSRFToken()`
- Output: `sanitize(string)` function (from `functions.php`, loaded via bootstrap) — use for all dynamic output

---

## Dev Agent Record

### Implementation Plan

Four files created/modified:
1. `public/coaches/team-register.php` — new page: Auth + owner redirect guard, DB query for active seasons + leagues, CSRF-validated POST handler calling `TeamRegistrationService::submit()`, PRG redirect on success, catches `InvitationRegisteredUserException`.
2. `public/coaches/team-register-confirm.php` — new minimal confirmation page with no team-owner nav, plain navbar only.
3. `public/assets/js/coaches-registration.js` — added `initTeamNamePreview()`, `initHomeFieldRepeater()`, and `initTeamRegisterLeagueToggle()` (separate from existing `initLeagueOtherToggle()` since the team-register page uses `id="league_name"` vs registration page's `id="league"`). All existing functions preserved.
4. `public/assets/css/style.css` — added `.reg-progress`, `.step-done`, `.step-active`, `.step-2-active` CSS rules.

### Debug Log

- Note: `initLeagueOtherToggle()` on register.php targets `id="league"` while team-register.php targets `id="league_name"`. A separate `initTeamRegisterLeagueToggle()` was added to handle both without conflicts.

### Completion Notes

All 4 tasks complete. 79 unit tests pass (0 regressions). PHP syntax verified on both new files. All ACs satisfied:
- AC1: Step 2 page with progress indicator, program/season list, team name preview, and location repeater ✅
- AC2: JS repeater supports up to 5 entries with add/remove buttons; remove hidden when only 1 block ✅
- AC3: POST calls `TeamRegistrationService::submit()` and PRG-redirects to confirm page ✅
- AC4: Confirmation page shows exact required message; no team-owner nav ✅
- AC5: `InvitationRegisteredUserException` caught and rendered as inline error ✅
- AC6: All inputs have explicit `<label for="">`, repeater JS re-indexes `id`/`for` on clone, page title includes " — District 8 Travel League" suffix ✅

---

## File List

- `public/coaches/team-register.php` — **new**
- `public/coaches/team-register-confirm.php` — **new**
- `public/assets/js/coaches-registration.js` — **modify** (add repeater + preview logic; do NOT remove existing code)
- `_bmad-output/implementation-artifacts/4-2-coach-team-registration-pages.md` — this file

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
- 2026-05-06: Comprehensive dev context added — auth pattern corrections, session keys, schema facts, page/JS blueprints.
- 2026-05-06: Implementation complete — created team-register.php and team-register-confirm.php; extended coaches-registration.js with repeater and preview logic; added reg-progress CSS; status set to review.
