# Story 4.4: Coach Dashboard with Team Identity Hero

**Status:** review
**Epic:** 4 — Team Registration & Coach Assignment
**Story Key:** 4-4-coach-dashboard-team-identity-hero

---

## Story

As a coach,
I want my dashboard to show my team name, season, and role status immediately after login,
So that I know I'm in the right place and can quickly navigate to my team's tools.

---

## Acceptance Criteria

**AC1: Team Owner dashboard shows hero and action card grid**
**Given** a coach with a team assignment (`team_owners` row exists) logs in and visits `public/coaches/dashboard.php`
**When** the page loads
**Then** the Coach Identity Hero (`.coach-hero`) banner is shown with: coach's first name + last name (small, muted), team name (large, bold as `<h1>`), season/division (small), and "Team Owner" role badge (green `.status-team-owner`)
**And** the Action Card Grid (`.coach-action-grid`) shows 4 cards: Score Input, Schedule Change, My Schedule, Contacts
**And** each action card has a colored icon square (44×44px), label, and sub-label
**And** each card is an `<a>` with descriptive `aria-label`

**AC2: Pending team dashboard shows amber pending state**
**Given** a coach with no `team_owners` row but a `teams` row with `status='pending'` AND `submitted_by_user_id` matching the current user
**When** the dashboard loads
**Then** the `.coach-hero` banner shows the `pending` state: amber badge, "Pending Team Approval" message, and the text: "Your team registration is pending admin review. You'll receive an email when approved."
**And** Score Input, Schedule Change, and My Schedule action cards are shown as `disabled` (grayed, non-clickable)

**AC3: Unassigned coach dashboard shows gray unassigned state**
**Given** a coach with no `team_owners` row and no pending `teams` row
**When** the dashboard loads
**Then** the `.coach-hero` banner shows the `unassigned` state: gray background, text "No team assigned — contact your admin"
**And** Score Input, Schedule Change, and My Schedule action cards are shown as `disabled`

**AC4: Unauthenticated user is redirected to login**
**Given** a non-authenticated user visits `dashboard.php`
**When** the auth guard fires
**Then** the intended URL is stored in `$_SESSION['intended_url']` and the user is redirected to `login.php`

**AC5: Dark navbar is rendered on the dashboard**
**Given** the dashboard loads for any authenticated coach
**When** the navbar is displayed
**Then** a dark navbar (`background-color: #212529`, `navbar-dark`) is shown with: app name brand left, team name chip (when assigned), and user dropdown (coach name + Logout) right
**And** it collapses to a hamburger menu on mobile viewports

---

## Tasks / Subtasks

- [x] **Task 1: Update `public/coaches/dashboard.php`**
  - [x] Replace existing auth call with intended-URL-aware guard (see Dev Notes — do NOT use `PermissionGuard::requireRole()`)
  - [x] Fetch `$userId = (int)($_SESSION['coach_user_id'] ?? 0)`
  - [x] Fetch user row (`first_name`, `last_name`) from `users WHERE id = :userId`
  - [x] Determine hero state via direct DB queries — do NOT use `TeamScope::getScopedTeams()` (see Dev Notes for exact queries)
  - [x] Set `$heroState` to `'active'`, `'pending'`, or `'unassigned'`; collect team/season/division vars
  - [x] Set `$coachName` and `$teamName` for nav include
  - [x] Replace `include '../../includes/nav.php'` with `include '../../includes/coaches_nav.php'`
  - [x] Replace jumbotron + 3-card layout + info cards + session alert with `.coach-hero` + `.coach-action-grid`
  - [x] Render `.coach-hero` per state (see hero HTML structure in Dev Notes)
  - [x] Render `.coach-action-grid` — 4 cards; Score Input / Schedule Change / My Schedule disabled in pending+unassigned states; Contacts always enabled

- [x] **Task 2: Add CSS to `assets/css/style.css`**
  - [x] `.coach-hero` — `background: linear-gradient(135deg, #007bff 0%, #0056b3 100%)`, `color: #fff`, `padding: 2rem 0`
  - [x] `.coach-hero.unassigned` — `background: #6c757d`
  - [x] `.coach-hero.pending` — amber tint; can keep blue gradient with amber badge sufficient
  - [x] `.coach-hero-team` — `font-size: 1.75rem`, `font-weight: 700`, `margin: 0.25rem 0`, `color: #fff`
  - [x] `.coach-name-line` and `.coach-hero-meta` — `font-size: 0.875rem`, `opacity: 0.85`, `color: #fff`
  - [x] `.coach-action-grid` — `display: grid`, `grid-template-columns: 1fr 1fr`, `gap: 1rem`, `margin-top: -1.5rem` (UX-DR3 overlap)
  - [x] `.coach-action-card` — white card, `border-radius: 0.5rem`, `box-shadow: 0 2px 8px rgba(0,0,0,0.12)`, `padding: 1rem`, `display: flex`, `align-items: center`, `gap: 0.75rem`, `text-decoration: none`, `color: inherit`
  - [x] `.coach-action-card:hover` — lift shadow
  - [x] `.coach-action-card .card-icon` — `width: 44px; height: 44px`, `border-radius: 0.375rem`, `display: flex; align-items: center; justify-content: center`, `font-size: 1.25rem`, `color: #fff`, `flex-shrink: 0`
  - [x] `.coach-action-card.disabled` — `opacity: 0.45`, `pointer-events: none`, `cursor: not-allowed`
  - [x] Mobile breakpoint `@media (max-width: 575px)`: `grid-template-columns: 1fr; margin-top: -1rem`

- [x] **Task 3: Create `includes/coaches_nav.php`** (new file)
  - [x] Dark navbar: `navbar-dark` + `style="background-color:#212529;"`
  - [x] Left: `navbar-brand` (app name) + team name chip `<span class="badge bg-secondary ms-1">` when `$teamName` non-empty
  - [x] Right: user dropdown — `$coachName` label + Logout link
  - [x] Hamburger toggler targeting `#coachNavbar` collapse div
  - [x] Variables consumed from including page: `$coachName` (string), `$teamName` (string, may be empty)
  - [x] In `dashboard.php`: set both vars before the include

---

## Dev Notes

### 🚨 CRITICAL: Do NOT use `PermissionGuard::requireRole('user')` or `::requireRole('team_owner')`

AC4 in the epics says `PermissionGuard::requireRole('user')` — **this is architecturally wrong and will break every coach login.** `AuthService::setCoachSession()` always sets `$_SESSION['role'] = 'coach'`, so `requireRole('user')` would redirect all coaches to login. This was documented and confirmed in Story 4-2 dev notes.

**Use this pattern instead:**

```php
// Intended-URL-aware auth guard
if (!Auth::isCoach()) {
    $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '/public/coaches/dashboard.php';
    header('Location: login.php');
    exit;
}
```

Check `public/coaches/login.php` to confirm it reads `$_SESSION['intended_url']` post-login and redirects accordingly. If it already does (Story 3-4 added post-login redirect), just store the URL and let the login page handle the redirect. If not, add the read/clear/redirect logic to `login.php` as a minimal addition — but do not redesign the login page.

### 🚨 CRITICAL: Do NOT use `TeamScope::getScopedTeams()`

`TeamScope::getScopedTeams()` has a confirmed schema bug: it joins `team_owners` on `o.team_id = t.id` but the `teams` table PK is `team_id` (not `id`). The join silently returns zero rows for everyone. Confirmed and documented in Story 4-2 completion notes. The fix is not in scope here.

**Use direct DB queries:**

```php
$userId = (int) ($_SESSION['coach_user_id'] ?? 0);

// Step 1: active assignment?
$assignment = $db->fetchOne(
    'SELECT t.team_id, t.team_name, t.league_name,
            s.season_name, s.season_year,
            d.division_name
     FROM team_owners o
     INNER JOIN teams t  ON t.team_id = o.team_id
     LEFT  JOIN seasons s    ON s.season_id   = t.season_id
     LEFT  JOIN divisions d  ON d.division_id = t.division_id
     WHERE o.user_id = :uid
     LIMIT 1',
    ['uid' => $userId]
);

if ($assignment !== false) {
    $heroState   = 'active';
    $teamName    = (string)($assignment['team_name']    ?? '');
    $leagueName  = (string)($assignment['league_name']  ?? '');
    $seasonLabel = trim(($assignment['season_name'] ?? '') . ' ' . ($assignment['season_year'] ?? ''));
    $divLabel    = (string)($assignment['division_name'] ?? '');
} else {
    // Step 2: pending registration?
    $pending = $db->fetchOne(
        "SELECT team_id, team_name FROM teams
         WHERE status = 'pending' AND submitted_by_user_id = :uid
         LIMIT 1",
        ['uid' => $userId]
    );

    if ($pending !== false) {
        $heroState = 'pending';
        $teamName  = (string)($pending['team_name'] ?? '');
    } else {
        $heroState = 'unassigned';
        $teamName  = '';
    }
}
```

### Session Keys (AuthService-canonical — do not guess)

```php
$_SESSION['coach_user_id']    // int — users.id  (NOT 'user_id')
$_SESSION['role']             // string — always 'coach' for coach logins
$_SESSION['user_type']        // string — 'coach'
$_SESSION['coach_identifier'] // string — username or email
```

### `preferred_name` Does NOT Exist in the Database

The epics reference `users.preferred_name` — **this column does not exist** in the `users` table (confirmed via `user_accounts_schema.sql`). The table has only `first_name` and `last_name`. `preferred_name` is planned for Story 7.2 (Profile page). Use `first_name` now:

```php
// Fetch user data
$user = $db->fetchOne(
    'SELECT id, first_name, last_name FROM users WHERE id = :id LIMIT 1',
    ['id' => $userId]
);
$coachName = ($user !== false)
    ? sanitize($user['first_name'] . ' ' . $user['last_name'])
    : 'Coach';
```

### `teams` Table Quick Reference

| Column | Type / Notes |
|--------|-------------|
| `team_id` | INT — PK (NOT `id`) |
| `status` | `ENUM('pending','active','inactive')` — added by migration 003 |
| `submitted_by_user_id` | INT — FK to `users.id`; set by `TeamRegistrationService::submit()` |
| `team_name` | Auto-generated: `{league_name}-{last_name}` |
| `season_id` | FK to `seasons.season_id` |
| `division_id` | FK to `divisions.division_id` — NULL until admin approves |

### Hero HTML Structure

```php
<div class="coach-hero <?php echo htmlspecialchars($heroState); ?>">
  <div class="container py-3">
    <?php if ($heroState === 'active'): ?>
      <div class="coach-name-line"><?php echo $coachName; ?></div>
      <h1 class="coach-hero-team"><?php echo sanitize($teamName); ?></h1>
      <div class="coach-hero-meta">
        <?php echo sanitize($leagueName); ?>
        <?php if ($seasonLabel): ?> · <?php echo sanitize($seasonLabel); ?><?php endif; ?>
        <?php if ($divLabel): ?> · <?php echo sanitize($divLabel); ?><?php endif; ?>
      </div>
      <span class="badge status-team-owner mt-2">Team Owner</span>

    <?php elseif ($heroState === 'pending'): ?>
      <div class="coach-name-line"><?php echo $coachName; ?></div>
      <h1 class="coach-hero-team"><?php echo sanitize($teamName ?: 'Team Registration'); ?></h1>
      <span class="badge status-team-pending mt-1">Pending Team Approval</span>
      <p class="mt-2 mb-0" style="font-size:0.9rem;opacity:0.9;">
        Your team registration is pending admin review. You'll receive an email when approved.
      </p>

    <?php else: /* unassigned */ ?>
      <div class="coach-name-line"><?php echo $coachName; ?></div>
      <h1 class="coach-hero-team">No team assigned</h1>
      <p class="mt-1 mb-0" style="font-size:0.9rem;opacity:0.85;">
        No team assigned — contact your admin
      </p>
    <?php endif; ?>
  </div>
</div>
```

### Action Card Grid HTML Structure

```php
<?php
$isActive = ($heroState === 'active');
// [href, fa-icon, icon-bg, label, sub-label, disabled?]
$cards = [
    ['score-input.php',     'fas fa-baseball-ball', '#28a745', 'Score Input',     'Submit game scores',      !$isActive],
    ['schedule-change.php', 'fas fa-calendar-alt',  '#fd7e14', 'Schedule Change', 'Request game reschedule', !$isActive],
    ['schedule.php',        'fas fa-list-ul',        '#007bff', 'My Schedule',     'View team games',         !$isActive],
    ['contacts.php',        'fas fa-address-book',   '#6f42c1', 'Contacts',        'League contact directory', false],
];
?>
<div class="container">
  <div class="coach-action-grid">
    <?php foreach ($cards as [$href, $icon, $color, $label, $sub, $disabled]): ?>
      <a href="<?php echo $disabled ? '#' : htmlspecialchars($href); ?>"
         class="coach-action-card<?php echo $disabled ? ' disabled' : ''; ?>"
         aria-label="<?php echo htmlspecialchars($label); ?>"
         <?php echo $disabled ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
        <div class="card-icon" style="background-color:<?php echo $color; ?>;">
          <i class="<?php echo $icon; ?>"></i>
        </div>
        <div>
          <div class="fw-semibold"><?php echo htmlspecialchars($label); ?></div>
          <div class="text-muted" style="font-size:0.8rem;"><?php echo htmlspecialchars($sub); ?></div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</div>
```

### Dark Coach Navbar (`includes/coaches_nav.php`)

Expects `$coachName` (string) and `$teamName` (string, may be empty) set by the including page.

```php
<?php
// Expected from including page: $coachName (string), $teamName (string)
$_rootPath = '../../'; // public/coaches/ depth — adjust if reused at other depths
?>
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color:#212529;">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?php echo $_rootPath; ?>index.php">
      <?php echo defined('APP_NAME') ? htmlspecialchars(APP_NAME) : 'D8TL'; ?>
    </a>
    <?php if (!empty($teamName)): ?>
      <span class="badge bg-secondary ms-1"><?php echo htmlspecialchars($teamName); ?></span>
    <?php endif; ?>

    <button class="navbar-toggler" type="button"
            data-bs-toggle="collapse" data-bs-target="#coachNavbar"
            aria-controls="coachNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="coachNavbar">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="coachUserMenu"
             role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($coachName); ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="coachUserMenu">
            <li><a class="dropdown-item" href="login.php?action=logout">Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
```

### Existing Status Badge Classes (already in `style.css` — do not redefine)

```css
/* These already exist — reuse them in the hero markup */
.status-team-owner  { background-color: #28a745 !important; color: #fff !important; }
.status-team-pending { background-color: #fd7e14 !important; color: #fff !important; }
```

### `dashboard.php` Bootstrap Block — Keep As-Is

The try/catch bootstrap detection at the top of the current `dashboard.php` (lines 7–16) that locates `coach_bootstrap.php` is correct — do not replace it. Changes start after that block.

### Logout URL Verification

Before hardcoding `login.php?action=logout` in `coaches_nav.php`, grep the existing coach pages for the actual logout href pattern used in `nav.php`. The shared nav likely sets it via `Auth::getLogoutUrl()` or a plain query-string. Match whatever pattern is already in use.

### Scope Boundary

AC5 says "any coach page" gets the dark navbar. **This story only modifies `dashboard.php`** — the nav component is created as a reusable file (`coaches_nav.php`) so future stories (score-input, schedule-change, etc.) can adopt it without markup duplication. Do not retroactively update other coach pages now.

### What NOT to Do

- ❌ `PermissionGuard::requireRole('user')` — session role is `'coach'`; this redirects all coaches
- ❌ `TeamScope::getScopedTeams()` — confirmed broken join (`t.id` vs `t.team_id`)
- ❌ `users.preferred_name` — column does not exist; use `first_name` 
- ❌ Create a new CSS file — append only to `assets/css/style.css`
- ❌ Touch admin pages or other coach pages — out of scope
- ❌ Delete `coach_bootstrap.php` detection block — keep it
- ❌ Change Bootstrap CDN version from 5.1.3

---

## Dev Agent Record

### Implementation Plan

1. Rewrote `dashboard.php` — kept bootstrap try/catch block intact; relies on `coach_bootstrap` → `Auth::requireCoach()` for guest auth (`intended_url` set there); multi-team picker + `coach_dashboard_team_id` session; direct DB hero resolution (active → pending → unassigned); `coaches_nav.php`; `.coach-hero` + `.coach-action-grid`.
2. Appended all `.coach-hero`, `.coach-action-grid`, `.coach-action-card`, state variants, and mobile breakpoint CSS to `assets/css/style.css`.
3. Created `includes/coaches_nav.php` — dark navbar, team chip, hamburger, user dropdown with Logout.
4. Patched `login.php` — post-login redirect uses `coach_login_safe_redirect_target()` on `intended_url`, then clears it.

### Debug Log

- Dev Notes confirm `TeamScope::getScopedTeams()` broken join — used direct DB queries throughout.
- `preferred_name` column absent — used `first_name . ' ' . last_name`.
- Logout href: existing `logout.php` in same directory (confirmed from `public/coaches/logout.php`).
- `intended_url` is set in `Auth::requireCoach()` for unauthenticated coach-area requests; `login.php` validates and redirects on success.

### Completion Notes

All 5 ACs satisfied:
- **AC1** Active state: `.coach-hero.active` renders coach name, team `<h1>`, league/season/division meta, `.status-team-owner` badge; all 4 action cards enabled.
- **AC2** Pending state: amber `.status-team-pending` badge, "Pending Team Approval" message; 3 functional cards disabled.
- **AC3** Unassigned state: `.coach-hero.unassigned` (grey #6c757d background), "No team assigned — contact your admin"; 3 functional cards disabled.
- **AC4** `intended_url` stored by `Auth::requireCoach()` before login redirect; `login.php` honours a sanitized value post-login.
- **AC5** `coaches_nav.php` dark navbar (`#212529`), team chip, hamburger toggler, user dropdown with Logout.

Unit tests: this story introduces no new service class; existing 82 tests all pass (zero regressions). The 10 `UserManagementService` test failures are carry-over from story 4.3 (service not yet implemented).

---

### Review Findings

_Code review 2026-05-07 (bmad-code-review). Follow-up 2026-05-07: product chose **multi-team picker**; patches applied in code._

- [x] [Review][Decision] **Multi-team selection** — Resolved: **picker UX**. When a coach has more than one `team_owners` row, the dashboard shows a **Select a team** form (POST + CSRF) until `$_SESSION['coach_dashboard_team_id']` matches an owned team; **Switch team** clears the choice. Single-team owners still auto-bind that team in session.

- [x] [Review][Patch] **Unsafe post-login `intended_url`** — Resolved: `coach_login_safe_redirect_target()` in `public/coaches/login.php` (same-origin coach `.php` paths and `/public/coaches/*.php` absolute paths only; rejects `..`, `://`, `//`, CRLF).

- [x] [Review][Patch] **Admin `coach_user_id` unset** — Resolved: `dashboard.php` falls back to `$_SESSION['admin_id']` when `coach_user_id` is `0` and `Auth::isAdmin()`.

- [x] [Review][Patch] **Navbar web root** — Resolved: optional `$coachNavWebRoot` in `includes/coaches_nav.php` (dashboard passes `../../` explicitly).

**AC4 follow-up:** `intended_url` for guests is now set in `Auth::requireCoach()` (`includes/auth.php`) before redirect, so **all** coach pages using `coach_bootstrap` preserve the destination (the dashboard-only guard ran too late and never executed for guests).

---

## File List

- `public/coaches/dashboard.php` — **modify** (replace jumbotron + 3-card layout; dark nav include; hero + action grid; multi-team picker)
- `public/coaches/login.php` — **modify** (safe post-login redirect from `intended_url`)
- `assets/css/style.css` — **modify** (append `.coach-hero`, `.coach-action-grid`, `.coach-action-card` + state variants + mobile breakpoint)
- `includes/coaches_nav.php` — **new** (dark navbar; optional `$coachNavWebRoot`)
- `includes/auth.php` — **modify** (`requireCoach` sets `intended_url` for guests — AC4 across coach bootstrap)
- `_bmad-output/implementation-artifacts/4-4-coach-dashboard-team-identity-hero.md` — this file

---

## Change Log
- 2026-05-05: Initial story file created from planning artifacts.
- 2026-05-06: Full dev context pass — auth guard corrected (no PermissionGuard), TeamScope bug documented, session keys confirmed, preferred_name absence noted, direct DB query blueprints added, hero/nav/CSS HTML structures provided, scope boundary clarified.
- 2026-05-07: Code review follow-up — multi-team **picker**, safe `intended_url` redirect, admin user id fallback, `$coachNavWebRoot`; `intended_url` set in `Auth::requireCoach()` for coach bootstrap.
