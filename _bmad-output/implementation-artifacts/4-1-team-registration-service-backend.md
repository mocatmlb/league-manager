# Story 4.1: TeamRegistrationService Backend

**Status:** done
**Epic:** 4 — Team Registration & Coach Assignment
**Story Key:** 4-1-team-registration-service-backend

---

## Story

As a developer,
I want a `TeamRegistrationService` that handles pending team creation, home field location submission, and admin approval,
So that the team registration pages have a clean, tested API.

---

## Acceptance Criteria

**AC1: submit() creates pending team with auto-generated name and locations**
**Given** valid team registration data and a `user_id` whose caller has already verified coach eligibility (status `active`, role `user`) — enforcement at HTTP/session layer in Story 4.2, not inside this service
**When** `TeamRegistrationService::submit(int $userId, array $data)` is called
**Then** a new row is inserted into `teams` with `status = 'pending'`
**And** the team name is auto-generated as `{league_name}-{coach_last_name}` using the coach's registration league value and last name
**And** if the coach selected "Other" for league, the manually entered value is used in place of `{league_name}`
**And** up to 5 home field location entries are inserted into `locations` with `status = 'pending'` and `submitted_by_user_id` set

**AC2: Invitation-registered coach cannot submit team registration**
**Given** a coach who registered via invitation attempts team registration
**When** `TeamRegistrationService::submit()` is called
**Then** an `InvitationRegisteredUserException` is thrown and no team is created (FR-TEAMREG-11)

**AC3: approve() activates team, assigns coach as Team Owner**
**Given** an admin calls `TeamRegistrationService::approve(int $teamId, int $adminUserId, int $divisionId)`
**When** the approval completes
**Then** the team's `status` is updated to `'active'` and `division_id` is set
**And** the submitting coach is assigned as Team Owner via INSERT into `team_owners`
**And** a notification email is sent to the coach (operational — failure logged only, per AR-12)
**And** `ActivityLogger` events `team.registration_approved` and `team.owner_assigned` are recorded

**AC4: getPendingRegistrations() returns pending teams with submitter info**
**Given** `TeamRegistrationService::getPendingRegistrations()` is called
**When** pending team registrations exist
**Then** it returns an array of teams with `status = 'pending'` including the submitting user's name

**AC5: Unit tests pass**
**Given** the unit test suite is run
**When** this story is complete
**Then** `TeamRegistrationServiceTest.php` passes all cases including: successful submit, invitation-registered user rejection, approve flow, and empty pending list

---

## Tasks / Subtasks

- [x] **Task 1: Implement `TeamRegistrationService` class**
  - [x] Create `includes/TeamRegistrationService.php`
  - [x] Implement `submit(int $userId, array $data): int`
  - [x] Implement `approve(int $teamId, int $adminUserId, int $divisionId): void`
  - [x] Implement `getPendingRegistrations(): array`
  - [x] Define `InvitationRegisteredUserException` (extends `RuntimeException`)

- [x] **Task 2: Implement `TeamRegistrationServiceTest.php`**
  - [x] Test: submit creates pending team with correct auto-generated name
  - [x] Test: submit uses "Other" league value in team name
  - [x] Test: submit throws `InvitationRegisteredUserException` for invitation-registered user
  - [x] Test: approve sets team to active with division and inserts team_owners row
  - [x] Test: getPendingRegistrations returns empty array when no pending teams

- [x] **Task 3: Run full test suite**
  - [x] All `TeamRegistrationServiceTest` tests pass
  - [x] No regressions in existing tests (`php tests/unit/run-unit-tests.php`)

### Review Findings

- [x] [Review][Decision] AC1 vs implementation — coach eligibility — **Resolved (2026-05-06):** caller-only precondition; AC1 updated so verified-coach checks happen at the page/controller layer (Story 4.2). Service loads coach fields needed for registration only.

- [x] [Review][Patch] `approve()` must validate team row before dereferencing — **Applied:** load team + `pending` check + coach lookup before `UPDATE`; unknown team / non-pending / missing coach throw `RuntimeException` before any mutation.

- [x] [Review][Patch] Transaction boundary for `submit()` — **Applied:** `beginTransaction` / `commit` / `rollBack` around team INSERT + location INSERTs; activity log after successful commit.

- [x] [Review][Patch] AC4 test gap — **Applied:** test `getPendingRegistrations` with one pending team asserts `submitter_first_name` / `submitter_last_name`; mock `fetchAll` simulates LEFT JOIN to `users`.

---

## Dev Notes

### Critical Schema Facts — Read These First

**`teams` table** (PK is `team_id`, NOT `id`):
- Required NOT NULL columns: `season_id`, `league_name`, `manager_first_name`, `manager_last_name`
- `division_id` is nullable — omit on INSERT for pending teams
- Migration 003 added: `status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'` (AFTER `division_id`)
- **No `program_id` column on `teams`** — the story task list mentions it but it doesn't exist. Use `season_id` only.

**`locations` table** (PK is `location_id`):
- Required NOT NULL: `location_name` (NOT `name`)
- Optional columns: `address`, `notes` (NOT `details` — there is no `details` column)
- Migration 004 added: `submitted_by_user_id INT NULL` and `status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'`
- Default for existing rows is `'active'` — explicitly set `status = 'pending'` for coach-submitted locations

**`team_owners` table** (from user_accounts_schema.sql):
```sql
CREATE TABLE IF NOT EXISTS team_owners (
    user_id INT NOT NULL,
    team_id INT NOT NULL,
    assigned_by INT NOT NULL,     -- NOT NULL: must pass $adminUserId
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, team_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id)
);
```
The PK is composite `(user_id, team_id)`. Architecture mentions a UNIQUE(user_id) constraint for 1:1 enforcement — this is enforced at the application layer, not the DB (the DB allows multiple teams per user at the schema level). `approve()` must check if the user already owns a team before inserting.

**`users` table** (PK is `id`, from user_accounts_schema.sql):
- Columns used: `id`, `first_name`, `last_name`, `email`, `status`, `role_id`

**`user_invitations` table**: `status ENUM('pending','completed','cancelled','expired')` — `status='completed'` means registered via invitation.

---

### submit() Implementation Blueprint

```php
public function submit(int $userId, array $data): int {
    // 1. Fetch coach from users table
    $user = $this->db->fetchOne(
        'SELECT id, first_name, last_name, email FROM users WHERE id = :id LIMIT 1',
        ['id' => $userId]
    );
    // throw RuntimeException if not found

    // 2. Detect invitation-registered user (AC2)
    $invite = $this->db->fetchOne(
        "SELECT id FROM user_invitations WHERE email = :email AND status = 'completed' LIMIT 1",
        ['email' => $user['email']]
    );
    if ($invite !== false) {
        throw new InvitationRegisteredUserException('Invitation-registered coaches cannot self-register a team.');
    }

    // 3. Determine league name for team name generation
    // $data['league_name'] = selected league display_name (or 'other' sentinel)
    // $data['other_league'] = the manually typed value when 'other' was selected
    $leagueName = (strtolower(trim((string) ($data['league_name'] ?? ''))) === 'other')
        ? trim((string) ($data['other_league'] ?? ''))
        : trim((string) ($data['league_name'] ?? ''));

    // 4. Auto-generate team name: {league_name}-{last_name}
    $teamName = $leagueName . '-' . $user['last_name'];

    // 5. INSERT team (status='pending', no division_id yet)
    $this->db->query(
        "INSERT INTO teams (season_id, league_name, team_name, status,
                            manager_first_name, manager_last_name, manager_email,
                            created_date)
         VALUES (:season_id, :league_name, :team_name, 'pending',
                 :manager_first_name, :manager_last_name, :manager_email,
                 NOW())",
        [
            'season_id'          => (int) $data['season_id'],
            'league_name'        => $leagueName,
            'team_name'          => $teamName,
            'manager_first_name' => $user['first_name'],
            'manager_last_name'  => $user['last_name'],
            'manager_email'      => $user['email'],
        ]
    );
    $teamId = (int) $this->db->getConnection()->lastInsertId();

    // 6. INSERT up to 5 location rows
    $locations = array_slice((array) ($data['locations'] ?? []), 0, 5);
    foreach ($locations as $loc) {
        $locName = trim((string) ($loc['name'] ?? ''));
        if ($locName === '') continue;
        $this->db->query(
            "INSERT INTO locations (location_name, address, notes,
                                    submitted_by_user_id, status, created_date)
             VALUES (:location_name, :address, :notes,
                     :submitted_by_user_id, 'pending', NOW())",
            [
                'location_name'       => $locName,
                'address'             => trim((string) ($loc['address'] ?? '')) ?: null,
                'notes'               => trim((string) ($loc['notes'] ?? '')) ?: null,
                'submitted_by_user_id'=> $userId,
            ]
        );
    }

    // 7. Log and return
    ActivityLogger::log('team.registration_submitted', ['user_id' => $userId, 'team_id' => $teamId]);
    return $teamId;
}
```

**Data contract for `$data`**:
| Key | Type | Required | Notes |
|-----|------|----------|-------|
| `season_id` | int | yes | FK to seasons.season_id |
| `league_name` | string | yes | Selected league display_name, or `'other'` |
| `other_league` | string | when league_name='other' | Manually typed league name |
| `locations` | array | no | Up to 5 entries, each: `['name'=>'...', 'address'=>'...', 'notes'=>'...']` |

---

### approve() Implementation Blueprint

```php
public function approve(int $teamId, int $adminUserId, int $divisionId): void {
    // 1. Update team status and set division
    $this->db->query(
        "UPDATE teams SET status = 'active', division_id = :division_id WHERE team_id = :team_id",
        ['division_id' => $divisionId, 'team_id' => $teamId]
    );

    // 2. Find the submitting coach (manager_email → users.id)
    $team = $this->db->fetchOne(
        'SELECT team_id, team_name, manager_email FROM teams WHERE team_id = :id LIMIT 1',
        ['id' => $teamId]
    );
    $coachUser = $this->db->fetchOne(
        'SELECT id, first_name, email FROM users WHERE email = :email LIMIT 1',
        ['email' => $team['manager_email']]
    );
    $coachUserId = (int) $coachUser['id'];

    // 3. Guard: enforce 1:1 user-to-team at app layer (AR-5)
    // Architecture mandates TeamAlreadyClaimedException — UserManagementService
    // doesn't exist yet in this story, implement the guard inline
    $existing = $this->db->fetchOne(
        'SELECT team_id FROM team_owners WHERE user_id = :user_id LIMIT 1',
        ['user_id' => $coachUserId]
    );
    if ($existing !== false) {
        throw new TeamAlreadyClaimedException('This coach is already assigned to a team.');
    }

    // 4. Assign Team Owner — INSERT into team_owners
    $this->db->query(
        'INSERT INTO team_owners (user_id, team_id, assigned_by, created_at)
         VALUES (:user_id, :team_id, :assigned_by, NOW())',
        ['user_id' => $coachUserId, 'team_id' => $teamId, 'assigned_by' => $adminUserId]
    );

    // 5. Log events
    ActivityLogger::log('team.registration_approved', [
        'team_id' => $teamId, 'admin_user_id' => $adminUserId, 'division_id' => $divisionId,
    ]);
    ActivityLogger::log('team.owner_assigned', [
        'user_id' => $coachUserId, 'team_id' => $teamId, 'admin_user_id' => $adminUserId,
    ]);

    // 6. Operational email — failure logged, not surfaced (AR-12)
    try {
        $this->emailService->triggerNotificationToAddress(
            'team_registration_approved',
            $coachUser['email'],
            ['user_id' => $coachUserId, 'team_id' => $teamId, 'first_name' => $coachUser['first_name']]
        );
    } catch (Throwable $e) {
        error_log('[TeamRegistrationService] Approval email failed: ' . $e->getMessage());
    }
}
```

**Also declare**: `class TeamAlreadyClaimedException extends RuntimeException {}`

---

### getPendingRegistrations() Implementation Blueprint

```php
public function getPendingRegistrations(): array {
    return $this->db->fetchAll(
        "SELECT t.team_id, t.team_name, t.league_name, t.season_id,
                t.manager_first_name, t.manager_last_name, t.manager_email,
                t.created_date,
                u.first_name AS submitter_first_name,
                u.last_name  AS submitter_last_name
         FROM teams t
         LEFT JOIN users u ON u.email = t.manager_email
         WHERE t.status = 'pending'
         ORDER BY t.created_date ASC"
    );
}
```

---

### Class File Structure

```php
<?php
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

if (!class_exists('ActivityLogger')) {
    require_once __DIR__ . '/ActivityLogger.php';
}
if (!class_exists('Logger')) {
    require_once __DIR__ . '/Logger.php';
}

class InvitationRegisteredUserException extends RuntimeException {}
class TeamAlreadyClaimedException extends RuntimeException {}

class TeamRegistrationService {
    private Database $db;
    private object $emailService;

    public function __construct(?Database $db = null, ?object $emailService = null) {
        $this->db = $db ?? Database::getInstance();
        if ($emailService !== null) {
            $this->emailService = $emailService;
            return;
        }
        if (!class_exists('EmailService')) {
            require_once __DIR__ . '/EmailService.php';
        }
        $this->emailService = new EmailService();
    }
    // ...
}
```

Follow this exact pattern from `RegistrationService.php` and `InvitationService.php`. No PSR-4 namespace, no constructor auto-wiring — just `D8TL_APP` guard + optional dependency injection.

---

### Email Template Key

Use `'team_registration_approved'` as the template key for the approval notification. This template may not exist yet in `email_templates` — the email send is operational (failure logged, not thrown), so a missing template won't break the flow.

---

### Testing Pattern

Follow the exact structure from `RegistrationServiceTest.php` and `InvitationServiceTest.php`:

```php
<?php
if (!defined('D8TL_APP')) { define('D8TL_APP', true); }

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ActivityLogger.php';
require_once __DIR__ . '/../../includes/TeamRegistrationService.php';

class TRSMockStatement {
    private int $rowCount;
    public function __construct(int $rowCount = 1) { $this->rowCount = $rowCount; }
    public function rowCount(): int { return $this->rowCount; }
}
class TRSMockConnection {
    private int $lastId = 0;
    public function setLastInsertId(int $id): void { $this->lastId = $id; }
    public function lastInsertId(): string { return (string) $this->lastId; }
    public function beginTransaction(): bool { return true; }
    public function commit(): bool { return true; }
    public function rollBack(): bool { return true; }
}
class TRSMockDatabase extends Database {
    public array $users = [];
    public array $invitations = [];
    public array $teams = [];
    public array $teamOwners = [];
    public array $locations = [];
    public array $activityEvents = [];
    public array $queryCalls = [];
    public int $nextTeamId = 100;
    private TRSMockConnection $conn;

    public function __construct() { $this->conn = new TRSMockConnection(); }
    public function getConnection(): object { return $this->conn; }

    public function fetchOne($sql, $params = []) {
        // Route by SQL pattern — see InvitationServiceTest for reference approach
        // ...
    }
    public function fetchAll($sql, $params = []) { /* ... */ }
    public function query($sql, $params = []) {
        $this->queryCalls[] = ['sql' => $sql, 'params' => $params];
        return new TRSMockStatement(1);
    }
}
```

Use `assert_equals()`, `assert_true()`, `assert_not_null()` from `test-helpers.php`. Throw `RuntimeException` from assertions to trigger test failure.

**What to test**:
1. `submit()` with a normal coach → returns int team ID, verify `queryCalls` contains INSERT into teams with `status='pending'`
2. `submit()` with a coach whose email has a completed invitation → `InvitationRegisteredUserException` thrown
3. `submit()` with `league_name = 'other'` → team name uses `other_league` value
4. `submit()` with locations array → location INSERT calls in `queryCalls`
5. `approve()` → team UPDATE, team_owners INSERT, ActivityLogger calls
6. `getPendingRegistrations()` returns empty array when `fetchAll` returns `[]`

---

### Existing Code Patterns — Do Not Deviate

- `Database::getInstance()` for DB access — never `new Database()` or `new PDO()`
- `Database::setInstance($mockDb)` for test injection
- `ActivityLogger::log(string $event, array $context)` — called from service only, never from page files
- Email: `$this->emailService->triggerNotificationToAddress($template, $email, $context)` — never instantiate PHPMailer directly
- Operational email failure: `error_log(...)` + continue (never throw)
- `fetchOne()` returns `false` on no result (not `null`)
- Guard files with `if (!class_exists(...)) { require_once ... }` before using dependencies

---

### What Does NOT Exist Yet

- `UserManagementService` is NOT in `includes/` — Story 8.1 creates it. `approve()` must implement the `team_owners` INSERT and duplicate guard inline.
- The `team_registration_approved` email template may not exist in `email_templates` — operational failure is acceptable.

---

### ActivityLogger legacyUserType Behavior

Events prefixed `team.*` will resolve to `'public'` in `ActivityLogger::legacyUserType()`. This is correct/expected behavior — the legacy column doesn't have a 'coach' value for team events. No changes needed to ActivityLogger.

---

### Running Tests

```bash
php tests/unit/run-unit-tests.php
```

No external dependencies. Tests must pass clean with zero failures. Check for regressions in existing tests (RegistrationServiceTest, InvitationServiceTest, AuthTest).

---

## Dev Agent Record

### Implementation Plan

Followed exact blueprints from Dev Notes. Service class mirrors the `InvitationService` constructor/DI pattern. `submit()` detects invitation-registered users via `user_invitations.status='completed'` check, generates team name as `{league}-{last_name}` (with 'other' sentinel support), inserts the pending team, inserts up to 5 locations, and logs `team.registration_submitted`. `approve()` updates team status, resolves the coach via `manager_email`, guards against duplicate ownership via inline `team_owners` check, inserts the owner row, logs two audit events, and fires the notification email as an operational side-effect (failures logged only). `getPendingRegistrations()` is a single LEFT JOIN query.

Test mock uses the `TRSMockDatabase / TRSMockConnection` pattern from `InvitationServiceTest`: SQL-pattern routing in `fetchOne`/`query`, `TRSMockConnection.lastInsertId()` to service the post-INSERT id read, `Database::setInstance()` injection so `ActivityLogger::log` calls also hit the mock.

### Debug Log

### Completion Notes

All 6 new `TeamRegistrationServiceTest` tests pass. Full suite: 79 passed, 0 failed, 0 skipped. No regressions.

AC1 ✅ — `submit()` creates pending team with auto-generated name; locations inserted correctly; "other" league sentinel handled.
AC2 ✅ — `submit()` throws `InvitationRegisteredUserException` for invitation-registered user; no team created.
AC3 ✅ — `approve()` activates team, assigns Team Owner in `team_owners`, sends approval email, logs both audit events.
AC4 ✅ — `getPendingRegistrations()` returns empty array when no pending teams exist.
AC5 ✅ — All `TeamRegistrationServiceTest` cases pass; zero regressions in existing test suite.

---

## File List

- `includes/TeamRegistrationService.php` — **new**
- `tests/unit/TeamRegistrationServiceTest.php` — **new**
- `_bmad-output/implementation-artifacts/4-1-team-registration-service-backend.md` — this file

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
- 2026-05-06: Comprehensive dev context added — schema facts, implementation blueprints, test pattern, dependency warnings.
- 2026-05-06: Implementation complete — created TeamRegistrationService.php and TeamRegistrationServiceTest.php; all 6 tests pass, 79 total, 0 regressions. Status → review.
- 2026-05-06: Code review — chose caller-only coach verification (option 2); AC1 **Given** clause updated to match service behavior.
- 2026-05-06: Code review patches applied — transactional `submit()`, safe `approve()` ordering + validation, AC4 non-empty pending test + mock JOIN; status → **done**.
