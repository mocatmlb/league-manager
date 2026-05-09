# Story 7.3: Coach Team Schedule View

**Status:** ready
**Epic:** 7 — Coach Profile, Team Schedule & Authenticated Resources
**Story Key:** 7-3-coach-team-schedule-view

---

## Story

As a Team Owner coach,
I want to view my team's full schedule with sortable and filterable columns,
So that I can quickly find specific games or review the full season at a glance.

---

## Acceptance Criteria

**AC1: Permission enforced and full team schedule loaded**
**Given** a Team Owner coach navigates to `public/coaches/schedule.php`
**When** the page loads
**Then** `PermissionGuard::requireRole('team_owner')` is enforced
**And** `CoachScheduleService::getTeamSchedule($userId)` returns all games for the coach's assigned team(s) regardless of game status
**And** the schedule table displays columns: Game Number, Date, Time, Away Team, Home Team, Location, Score
**And** the column structure matches the existing master public schedule (FR-COACHSCHEDULE-6)

**AC2: Column headers sort the table**
**Given** the coach clicks a column header
**When** the sort JS fires (`coaches-schedule.js`, UX-DR12)
**Then** the table rows are sorted ascending by that column
**And** clicking the same header again sorts descending

**AC3: Column filter inputs filter rows**
**Given** the coach types in a column filter input
**When** the filter JS fires
**Then** only rows matching the filter are shown in that column
**And** filtering multiple columns simultaneously applies all filters (AND logic)

**AC4: Date column supports date-range filtering**
**Given** the Date column filter is used
**When** the coach enters a date range
**Then** only games within that range are shown

**AC5: Clear Filters restores full schedule**
**Given** the coach clicks "Clear Filters"
**When** the action fires
**Then** all filters are cleared and the full team schedule is restored

**AC6: Empty state shown when no games scheduled**
**Given** no games are scheduled for the coach's team
**When** the page loads
**Then** an `alert alert-info` empty state is shown: "No games scheduled for your team yet. Check back after your team assignment is confirmed." (UX-DR16)

---

## Tasks / Subtasks

- [ ] **Task 1: Implement `CoachScheduleService` class**
  - [ ] Create `includes/CoachScheduleService.php`
  - [ ] Implement `getTeamSchedule(int $userId): array`
    - Get scoped team IDs via `TeamScope::getScopedTeams($userId)`
    - SELECT all games WHERE home_team_id OR away_team_id IN scoped teams, ordered by game_date ASC, game_time ASC
    - Include columns: `game_number`, `game_date`, `game_time`, `away_team_id` + name, `home_team_id` + name, location name, home_score + away_score
    - Return full list regardless of game status

- [ ] **Task 2: Create `public/coaches/schedule.php`**
  - [ ] Enforce `PermissionGuard::requireRole('team_owner')` at top
  - [ ] Call `CoachScheduleService::getTeamSchedule($userId)`
  - [ ] Render empty state `alert alert-info` if no games (UX-DR16)
  - [ ] Render schedule table: columns: Game #, Date, Time, Away Team, Home Team, Location, Score
  - [ ] Add column header sort controls (click to sort asc/desc, visual indicator)
  - [ ] Add column filter inputs below each header (text search for text columns, date-range for Date column)
  - [ ] Add "Clear Filters" button
  - [ ] Include `<script src="...coaches-schedule.js">` reference

- [ ] **Task 3: Create `public/assets/js/coaches-schedule.js`**
  - [ ] Independent column sorting: click header → sort asc; click again → sort desc; visual indicator on sorted column
  - [ ] Independent column filtering: text input filters → filter rows client-side (AND logic across columns)
  - [ ] Date column: two `<input type="date">` fields for range (from/to); filter rows with date between values
  - [ ] "Clear Filters" button: clear all filter inputs and restore full table
  - [ ] Implemented in `coaches-schedule.js` (AR-14); jQuery for DOM manipulation

- [ ] **Task 4: Verify column structure matches public schedule**
  - [ ] Check existing `public/schedule.php` column order and field labels
  - [ ] Mirror exactly: Game Number, Date, Time, Away Team, Home Team, Location, Score (FR-COACHSCHEDULE-6)

---

## Dev Notes

### Architecture Context
- `CoachScheduleService` in `includes/` — no namespace, define/check `D8TL_APP`
- `coaches-schedule.js` is a dedicated JS file per AR-14 — do not add schedule logic to `coaches-registration.js`
- Client-side sort/filter via jQuery (Bootstrap 5 already in stack)

### Score Display
- Show `away_score - home_score` format or blank if no score recorded (status != `completed`)
- Match the format used in the existing public schedule

### Date-Range Filter
- Two `<input type="date">` inputs: "From" and "To"
- Client-side: `game_date >= from AND game_date <= to`
- Clear filters button resets both to empty

### Mobile (NFR-COMPAT-1)
- Table should be horizontally scrollable on mobile viewports (Bootstrap `table-responsive`)
- At minimum ≥ 375px width must show the table with scroll

### Performance (NFR-PERF-3)
- Coach dashboard loads in under 3 seconds for up to 3 teams — ensure single query fetches all team games

---

## Dev Agent Record

### Implementation Plan

### Debug Log

### Completion Notes

---

## File List

- `includes/CoachScheduleService.php` — new
- `public/coaches/schedule.php` — new
- `public/assets/js/coaches-schedule.js` — new (UX-DR12)
- `_bmad-output/implementation-artifacts/7-3-coach-team-schedule-view.md` — new (this file)

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
