# Story 7.3: Coach Team Schedule View

**Status:** done
**Epic:** 7 — Coach Profile, Team Schedule & Authenticated Resources
**Story Key:** 7-3-coach-team-schedule-view

---

## Story

As a Team Owner coach,
I want to view my team's full schedule with sortable and filterable columns,
so that I can quickly find specific games or review the full season at a glance.

---

## Acceptance Criteria

**AC1: Permission enforced and full team schedule loaded**
**Given** a Team Owner coach navigates to `public/coaches/schedule.php`
**When** the page loads
**Then** `PermissionGuard::requireRole('team_owner')` is enforced (updated hierarchy from Story 7.2 now also passes)
**And** `CoachScheduleService::getTeamSchedule($userId)` returns all games for the coach's assigned team(s) regardless of game status
**And** the schedule table displays columns: Game #, Date, Time, Away Team, Home Team, Location, Score

**AC2: Column headers sort the table (UX-DR12)**
**Given** the coach clicks a column header
**When** the sort JS fires (`coaches-schedule.js`)
**Then** the table rows are sorted ascending by that column; a visual indicator (▲) appears on the header
**And** clicking the same header again sorts descending; the indicator changes to ▼

**AC3: Column filter inputs filter rows with AND logic (UX-DR12)**
**Given** the coach types in one or more column filter inputs
**When** the filter JS fires (on input)
**Then** only rows matching ALL active filters are shown (AND logic across columns)

**AC4: Date column supports date-range filtering (UX-DR12)**
**Given** the Date column filter is used
**When** the coach enters a From date, a To date, or both
**Then** only games within that range are shown (From ≤ game_date ≤ To)

**AC5: Clear Filters restores the full schedule**
**Given** the coach clicks "Clear Filters"
**When** the action fires
**Then** all text inputs and date inputs are cleared and all rows are restored

**AC6: Empty state when no games (UX-DR16)**
**Given** no games are scheduled for the coach's team
**When** the page loads
**Then** an `alert alert-info` empty state is shown: "No games scheduled for your team yet. Check back after your team assignment is confirmed."
**And** the filter controls and table are not rendered

**AC7: Unit tests pass**
**Given** `php tests/unit/run-unit-tests.php --file=CoachScheduleServiceTest.php`
**When** this story is complete
**Then** all tests pass: no teams returns empty array; games returned regardless of game_status; score field populated when status=Completed; score field empty when not Completed

---

## Tasks / Subtasks

- [x] **Task 1: Implement `CoachScheduleService`**
  - [x] Create `includes/CoachScheduleService.php`
  - [x] File header: `if (!defined('D8TL_APP')) { die('Direct access not permitted'); }`
  - [x] Constructor: `__construct(?Database $db = null)` — `$this->db = $db ?? Database::getInstance()`
  - [x] Implement `getTeamSchedule(int $userId): array`:
    - Call `TeamScope::getScopedTeams($userId)` — if empty, return `[]` immediately
    - Extract `$teamIds = array_column($teams, 'team_id')`
    - Build `$placeholders = implode(',', array_fill(0, count($teamIds), '?'))`
    - SQL:
      ```sql
      SELECT g.game_number, g.game_status,
             g.home_score, g.away_score,
             g.home_team_id, g.away_team_id,
             s.game_date, s.game_time, s.location,
             ht.team_name AS home_team_name,
             at.team_name AS away_team_name
      FROM games g
      LEFT JOIN schedules s ON g.game_id = s.game_id
      JOIN teams ht ON g.home_team_id = ht.team_id
      JOIN teams at ON g.away_team_id = at.team_id
      WHERE (g.home_team_id IN ({$placeholders})
          OR g.away_team_id IN ({$placeholders}))
      ORDER BY s.game_date ASC, s.game_time ASC
      ```
    - Params: `array_merge(array_values($teamIds), array_values($teamIds))` (same double-pass pattern as ScoreService)
    - **No game_status filter** — return ALL statuses (FR-COACHSCHEDULE-5)
    - Return result of `fetchAll()`

- [x] **Task 2: Write `CoachScheduleServiceTest.php`**
  - [x] Create `tests/unit/CoachScheduleServiceTest.php`
  - [x] Header: `define('D8TL_APP', true)`, require test-helpers, database.php, ActivityLogger.php, TeamScope.php, CoachScheduleService.php
  - [x] `CSSMockDatabase extends Database` — constructor bypasses PDO; `fetchAll()` returns teams for `team_owners` query, games for schedule query; `fetchOne` returns false; `query()` is no-op (no writes)
  - [x] Tests:
    - `getTeamSchedule()` with no assigned teams returns `[]`
    - `getTeamSchedule()` returns home games (home_team_id matches)
    - `getTeamSchedule()` returns away games (away_team_id matches)
    - `getTeamSchedule()` returns games of ALL statuses (include 'Completed', 'Cancelled', 'Postponed' in fixture and assert all appear)
    - Game row includes: game_number, game_date, game_time, location, home_team_name, away_team_name, home_score, away_score, game_status
    - Score fields are null when status = 'Active' (not yet scored)
    - Score fields are populated when status = 'Completed'

- [x] **Task 3: Create `public/coaches/schedule.php`**
  - [x] Bootstrap (env-loader pattern — same as schedule-change.php):
    ```php
    require_once __DIR__ . '/../../includes/env-loader.php';
    require_once EnvLoader::getPath('includes/coach_bootstrap.php');
    require_once EnvLoader::getPath('includes/PermissionGuard.php');
    require_once EnvLoader::getPath('includes/TeamScope.php');
    require_once EnvLoader::getPath('includes/CoachScheduleService.php');
    ```
  - [x] Auth: `PermissionGuard::requireRole('team_owner', '/coaches/login.php')`
  - [x] `$db = Database::getInstance(); $userId = (int) ($_SESSION['coach_user_id'] ?? 0); $service = new CoachScheduleService($db);`
  - [x] Load: `$games = $service->getTeamSchedule($userId)`
  - [x] Load nav vars:
    - `$user = $db->fetchOne('SELECT first_name, last_name FROM users WHERE id = :id', ['id' => $userId])`
    - `$teamRow = $db->fetchOne('SELECT t.team_name FROM teams t JOIN team_owners o ON t.team_id = o.team_id WHERE o.user_id = :id LIMIT 1', ['id' => $userId])`
    - `$coachName = htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')))`
    - `$teamName = htmlspecialchars((string) ($teamRow['team_name'] ?? ''))`
  - [x] `$pageTitle = 'Team Schedule — District 8 Travel League'`

- [x] **Task 4: Render `schedule.php` HTML**
  - [x] Doctype, Bootstrap 5.1.3 CSS CDN + FA 6.0.0 CSS CDN (same as score-input.php); NO DataTables CDN
  - [x] Include `coaches_nav.php` (path: `__DIR__ . '/../../includes/coaches_nav.php'`)
  - [x] `<div class="container mt-4"><div class="row"><div class="col-12">`
  - [x] Page heading: `<h1>Team Schedule</h1>` + back-to-dashboard link
  - [x] **If `empty($games)`**: render `alert alert-info` with exact AC6 text; skip table entirely
  - [x] **Else**: render filter controls + table:
    - "Clear Filters" button above table: `<button id="clearFilters" class="btn btn-outline-secondary btn-sm mb-3">Clear Filters</button>`
    - `<div class="table-responsive"><table id="scheduleTable" class="table table-striped table-hover">`
    - `<thead>` with two rows:
      - Row 1: sortable headers — each `<th>` with `data-col="0"` (index), `style="cursor:pointer"`, text + sort indicator span: `<th data-col="0">Game # <span class="sort-indicator"></span></th>`, columns: Game #, Date, Time, Away Team, Home Team, Location, Score
      - Row 2: filter inputs — `<th>` containing filter input; Date column gets two `<input type="date">` (id="dateFrom", id="dateTo"); all other text columns get `<input type="text" class="col-filter form-control form-control-sm" data-col="N" placeholder="Filter...">`
    - `<tbody>` rows: encode `game_date` as `data-date="YYYY-MM-DD"` on the Date `<td>` for sort/filter JS; Score cell: if `game_status === 'Completed' && $game['away_score'] !== null` → `{$game['away_score']} – {$game['home_score']}`; else `<span class="text-muted">—</span>`
  - [x] Script tag at bottom: `<script src="../../assets/js/coaches-schedule.js"></script>`
  - [x] Bootstrap 5.1.3 JS bundle CDN at bottom (no jQuery, no DataTables)
  - [x] Inline `<footer>` (same pattern as score-input.php)

- [x] **Task 5: Create `public/assets/js/coaches-schedule.js`**
  - [x] Self-contained vanilla JS (no jQuery) — IIFE or `DOMContentLoaded` listener
  - [x] **Sort logic**:
    - Track `{ col: null, dir: 'asc' }` state
    - On header `<th[data-col]>` click: if same col toggle dir; else set new col, dir='asc'
    - Read all `<tbody>` rows into array, sort by `td.children[col].dataset.sort || td.children[col].textContent.trim()`
    - Date cells: `data-date="YYYY-MM-DD"` used for sort value (lexicographically correct)
    - Score column: sort numerically by away score value (or push empty scores to bottom)
    - Re-append sorted rows to `<tbody>`
    - Update all sort indicator spans: sorted col gets ▲ or ▼; others get blank
  - [x] **Filter logic** (called on every `input` event on any filter):
    - Collect active text filters: `col-filter` inputs with non-empty value → `{ col: N, val: lower }`
    - Collect date range from `#dateFrom` and `#dateTo`
    - For each row: check all active text filters (row text at col contains val, case-insensitive) AND date range (row `data-date` between dateFrom and dateTo) — hide row if any fails, show if all pass
  - [x] **Clear Filters**: clear all `.col-filter` inputs, clear `#dateFrom`/`#dateTo`, show all rows, reset sort state + indicators
  - [x] Wire up: `querySelectorAll('th[data-col]')` for sort; `querySelectorAll('.col-filter')` + `#dateFrom` + `#dateTo` for filter (addEventListener 'input'); `#clearFilters` for clear

- [x] **Task 6: Verify**
  - [x] `php tests/unit/run-unit-tests.php` — full suite passes, no regressions
  - [x] Manual browser check: load as team_owner; verify sort (click header twice); type a filter; set date range; clear filters; verify empty state with a user who has no games

---

## Dev Notes

### No DataTables — pure vanilla JS in coaches-schedule.js

The public `schedule.php` uses DataTables (jQuery + `dataTables.bootstrap5.min.js` CDN). The coach schedule page does **not** use DataTables. UX-DR12 explicitly specifies `coaches-schedule.js` — a dedicated custom file. The coach pages don't load jQuery (score-input.php and schedule-change.php load only `bootstrap.bundle.min.js`). Use vanilla JS throughout.

If DataTables is tempting, note it requires jQuery to be loaded on the page, which adds ~90KB. The custom implementation for a simple sort+filter on a single table is ~60–80 lines and keeps the page consistent with other coach pages.

### SQL — double-pass team IDs (same as ScoreService)

The WHERE clause is `home_team_id IN (?,?) OR away_team_id IN (?,?)` — the team IDs array is passed **twice** (`array_merge(..., ...)`). This is the same pattern as `ScoreService::getEligibleGames()` and `getCompletedGames()`. Do not use named parameters here — use positional `?` to match the existing pattern.

### No game_status filter — all statuses returned

Unlike `ScoreService::getEligibleGames()` (excludes 'Completed') and `RescheduleService::getEligibleGames()` (excludes 'Completed' and 'Cancelled'), `getTeamSchedule()` returns **every** game involving the team regardless of status. This is FR-COACHSCHEDULE-5. Do not add a `game_status != '...'` clause.

### Score display format

```php
// In the table row:
if ($game['game_status'] === 'Completed' && $game['away_score'] !== null) {
    echo $game['away_score'] . ' – ' . $game['home_score'];
} else {
    echo '<span class="text-muted">—</span>';
}
```
The away score is listed first (away – home), matching the public schedule's column order of Away Team then Home Team.

### Date sorting — use data-date attribute

The `game_date` from the DB is `YYYY-MM-DD` format. Store it on the `<td>` as `data-date="2026-05-15"`. In JS, the sort reads `td.dataset.date` for the Date column instead of `textContent` (which would be `May 15, 2026` after PHP formatting). This gives correct lexicographic date sorting without parsing.

```php
// In the Date <td>:
echo '<td data-date="' . htmlspecialchars($game['game_date'] ?? '') . '">'
   . htmlspecialchars(formatDate($game['game_date'] ?? ''))
   . '</td>';
```

### sort-indicator span — accessible

Add `aria-sort="none"` to all sortable `<th>` initially. Update to `aria-sort="ascending"` or `aria-sort="descending"` when sorted. The visual indicator span (▲/▼) is `aria-hidden="true"`.

### TeamScope::getScopedTeams uses Database::getInstance() internally

`TeamScope` calls `Database::getInstance()` directly (not injected). In tests, set the mock via `Database::setInstance($mock)` before calling the service, so TeamScope picks it up. This is the same approach used in ScoreServiceTest and RescheduleServiceTest.

### CoachScheduleService has no ActivityLogger calls

This is a read-only service. No writes, no ActivityLogger calls needed.

### test mock prefix — use CSS (CoachScheduleService) prefix

Use `CSSMockDatabase` and `CSSMockStatement` to avoid class collision with other test files in the same runner.

---

## Files

| File | Action |
|------|--------|
| `includes/CoachScheduleService.php` | NEW |
| `tests/unit/CoachScheduleServiceTest.php` | NEW |
| `public/coaches/schedule.php` | NEW |
| `public/assets/js/coaches-schedule.js` | NEW |

**Depends on:** Story 7.2 (PermissionGuard role-hierarchy update must be applied — `requireRole('team_owner')` relies on the updated map)

---

## Dev Agent Record

### Agent Model Used

claude-opus-4-6

### Debug Log References

None

### Completion Notes List

- Implemented CoachScheduleService with getTeamSchedule() returning all games regardless of status (FR-COACHSCHEDULE-5)
- 7 unit tests passing: no teams → empty, home games, away games, all statuses, field coverage, null scores, populated scores
- Schedule page with env-loader bootstrap, PermissionGuard team_owner, coaches_nav
- Vanilla JS sort (asc/desc with ▲/▼ indicators, aria-sort), text filters (AND logic), date-range filter, Clear Filters
- All ACs verified in browser: sort, filter, clear, table rendering, score display

### File List

- `includes/CoachScheduleService.php` — NEW
- `tests/unit/CoachScheduleServiceTest.php` — NEW
- `public/coaches/schedule.php` — NEW
- `public/assets/js/coaches-schedule.js` — NEW

### Change Log

- 2026-05-09: Story 7.3 implemented — CoachScheduleService, tests, schedule page with sort/filter JS
