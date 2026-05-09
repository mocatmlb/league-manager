# Story 5.1: ScoreService Backend

**Status:** done
**Epic:** 5 — Team-Scoped Score Submission
**Story Key:** 5-1-score-service-backend

---

## Story

As a developer,
I want a `ScoreService` class that enforces team-scoping, time-gating, and standings updates for score submission,
So that score submission page files have a clean, tested API with all permission rules enforced server-side.

---

## Acceptance Criteria

**AC1: submit() records score with full enforcement**
**Given** a Team Owner submits a score for a game involving their assigned team that is past/elapsed
**When** `ScoreService::submit(int $userId, int $gameId, int $homeScore, int $awayScore)` is called
**Then** `TeamScope::getScopedTeams($userId)` is called to verify the game involves the coach's team
**And** `GameTimeGate::isEligible($game)` is called to verify the game is past/elapsed
**And** the home and away scores are saved to the game record
**And** the game `status` is set to `'completed'` (FR-SCORE-7)
**And** standings are updated immediately (existing standings update logic reused)
**And** an operational notification email is sent to admin (failure logged only)
**And** `ActivityLogger` event `score.submitted` is recorded with `user_id`, `game_id`, and both scores

**AC2: submit() throws TeamScopeViolationException for wrong team**
**Given** the coach attempts to submit a score for a game not involving their team
**When** `ScoreService::submit()` is called
**Then** a `TeamScopeViolationException` is thrown and no score is saved (FR-RESTRICTIONS-4)

**AC3: submit() throws GameNotEligibleException for future game**
**Given** the coach attempts to submit a score for a future game
**When** `ScoreService::submit()` is called
**Then** a `GameNotEligibleException` is thrown and no score is saved (FR-RESTRICTIONS-5)

**AC4: edit() updates score and recalculates standings**
**Given** a game already has a recorded score and the coach submits an edit
**When** `ScoreService::edit(int $userId, int $gameId, int $homeScore, int $awayScore)` is called
**Then** the score is updated and standings recalculated
**And** `ActivityLogger` event `score.edited` is recorded with old and new score values

**AC5: getEligibleGames() returns only past/elapsed unscored games for coach's team**
**Given** `ScoreService::getEligibleGames(int $userId)` is called
**When** the coach has assigned teams
**Then** it returns only games where: the game involves the coach's team AND `GameTimeGate::isEligible()` returns true AND game status is not `completed`

**AC6: Unit tests pass**
**Given** the unit test suite is run
**When** this story is complete
**Then** `ScoreServiceTest.php` passes all cases including: successful submit, team scope violation, future game rejection, edit with standings recalc, and eligible games filtering

---

## Tasks / Subtasks

- [x] **Task 1: Implement `ScoreService` class**
  - [x] Create `includes/ScoreService.php`
  - [x] Implement `submit(int $userId, int $gameId, int $homeScore, int $awayScore): void`
    - Get scoped teams via `TeamScope::getScopedTeams($userId)`
    - Load game record; verify game involves coach's team (home_team_id or away_team_id in scoped teams) — throw `TeamScopeViolationException` if not
    - Call `GameTimeGate::isEligible($game)` — throw `GameNotEligibleException` if false
    - UPDATE game: `home_score`, `away_score`, `status='completed'`
    - Call existing standings update logic (find and reuse existing function/class)
    - Send operational admin notification (log failure only)
    - Log `score.submitted` with `['user_id', 'game_id', 'home_score', 'away_score']`
  - [x] Implement `edit(int $userId, int $gameId, int $homeScore, int $awayScore): void`
    - Same team scope and time gate enforcement as submit
    - Capture old scores before update for log context
    - UPDATE game scores, recalculate standings
    - Log `score.edited` with old and new scores
  - [x] Implement `getEligibleGames(int $userId): array`
    - Get scoped team IDs via `TeamScope::getScopedTeams($userId)`
    - SELECT games WHERE (home_team_id IN $teamIds OR away_team_id IN $teamIds) AND status != 'completed'
    - Filter results through `GameTimeGate::isEligible()` in PHP
    - Return filtered array
  - [x] Define `TeamScopeViolationException` and `GameNotEligibleException` (extend `RuntimeException`)

- [x] **Task 2: Implement `ScoreServiceTest.php`**
  - [x] Test: submit saves scores and sets status to `completed`
  - [x] Test: submit throws `TeamScopeViolationException` for game not involving coach's team
  - [x] Test: submit throws `GameNotEligibleException` for future game
  - [x] Test: edit updates scores and logs old/new values
  - [x] Test: getEligibleGames returns only past/elapsed unscored games for coach's teams
  - [x] Test: getEligibleGames returns empty array when no eligible games

- [x] **Task 3: Run full test suite**
  - [x] All `ScoreServiceTest` tests pass
  - [x] No regressions in existing tests

### Review Findings

- [x] [Review][Patch] Require `game_status === 'Completed'` before `edit()` mutates scores — `edit()` docblock and AC4 assume an already-completed scored game, but only team scope and `GameTimeGate` run; a past/eligible game still in `Active` (e.g. missing prior `submit()` or inconsistent DB) can be updated via `edit()`, bypassing the `submit()` path and `score.submitted` semantics [`includes/ScoreService.php:77-82`] — fixed 2026-05-08 (`enforceCompletedForEdit`)
- [x] [Review][Patch] Guard games missing schedule fields before time-gate — `loadGame` / `getEligibleGames` use `LEFT JOIN schedules`; when `game_date` is null, `GameTimeGate::isEligible()` compares null to `Y-m-d` and PHP treats the game as in the past, incorrectly marking it eligible [`includes/ScoreService.php:123-132`, `139-152`, `169-174`] — fixed 2026-05-08 (`hasScheduleForTimeGate` / `enforceTimeGate`, list filter)

---

## Dev Notes

### Architecture Context
- Class in `includes/` — no namespace, define/check `D8TL_APP`
- Depends on `TeamScope` (Story 1.3) and `GameTimeGate` (Story 1.3) — both must be complete
- Depends on `ActivityLogger` (Story 1.3)
- Reuse existing standings update logic — locate in existing `includes/` files (check `functions.php` or similar)

### Standings Update
- Find existing standings recalculation function in the codebase before writing new code
- Call it directly from `ScoreService::submit()` and `edit()`

### Score Validation
- Scores: integer, 0–99 inclusive (per UX-DR2 `min="0" max="99"`)
- Validate in service layer before UPDATE

### Email Classification (AR-12)
- Admin notification of score submission: **operational** — log only on failure, do not surface to coach

### edit() vs submit()
- `edit()` requires the game to already have `status='completed'` OR calls same eligibility check
- Refer to epics.md: FR-SCORE-4 says "requires an explicit edit action" — the service method is triggered by an explicit "edit score" UI action

---

## Dev Agent Record

### Implementation Plan

ScoreService depends on TeamScope, GameTimeGate, and ActivityLogger (all from Story 1.3).
Standings are computed dynamically from game rows via getDivisionStandings() — no explicit
recalculation call is needed; updating home_score/away_score/game_status is sufficient.
Admin notification uses the existing sendNotification() helper wrapped in try/catch with
function_exists() guard for test isolation. Scores validated 0–99 before any DB access.

### Debug Log

### Completion Notes

- ScoreService implemented with submit(), edit(), getEligibleGames(), and private helpers
  enforceTeamScope(), enforceTimeGate(), validateScores(), loadGame().
- TeamScopeViolationException and GameNotEligibleException defined in same file.
- Standings: getDivisionStandings() computes dynamically — no separate update step required.
- game_status set to 'Completed' (capital C) to match existing codebase convention.
- 11 unit tests in `ScoreServiceTest` (ACs plus code-review regressions: missing schedule on submit,
  edit rejected when not `Completed`, `getEligibleGames` excludes rows without schedule).
- Code review (2026-05-08): `enforceCompletedForEdit()`, `hasScheduleForTimeGate()` before `GameTimeGate`.
- Full unit suite: 106/106 passing after review fixes.

---

## File List

- `includes/ScoreService.php` — new
- `tests/unit/ScoreServiceTest.php` — new
- `_bmad-output/implementation-artifacts/5-1-score-service-backend.md` — updated

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
- 2026-05-07: Implementation complete — ScoreService, exceptions, unit tests. Status → review.
- 2026-05-08: Code review patches applied (`Completed` guard on `edit`, schedule guard for time gate). Status → done.
