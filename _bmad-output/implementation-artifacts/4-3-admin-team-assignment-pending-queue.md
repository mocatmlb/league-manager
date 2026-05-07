# Story 4.3: Admin Team Assignment & Pending Queue

**Status:** done
**Epic:** 4 — Team Registration & Coach Assignment
**Story Key:** 4-3-admin-team-assignment-pending-queue

**⚠️ Completion Rule:** This story is only complete when ALL THREE work areas (A, B, and C) are fully implemented and all acceptance criteria pass.

---

## Story

As an admin,
I want to view pending team registrations and assign coaches to teams,
So that coaches gain their Team Owner identity and can access team features.

---

## Acceptance Criteria

**AC1: Pending team registrations appear above active teams list**
**Given** pending team registrations exist and an admin views `admin/teams/index.php`
**When** the page loads
**Then** a "Pending Team Registrations" section is shown above the existing active teams list
**And** each pending entry shows: coach name, auto-generated team name, submitted league, program/season requested, submitted date, and an "Approve" action

**AC2: Approving a pending registration activates team and assigns Team Owner**
**Given** an admin clicks "Approve" on a pending team registration
**When** they select a division and confirm
**Then** `TeamRegistrationService::approve()` is called
**And** the team status changes to `active` with the selected division assigned
**And** the coach's role is elevated to `team_owner`
**And** the coach receives an email notification
**And** the pending entry disappears from the queue

**AC3: Admin can assign a coach to a team from user detail page**
**Given** an admin navigates to `admin/users/detail.php` for a verified coach
**When** the admin clicks "Assign to Team"
**Then** a list of active-season teams is shown
**And** selecting a team and confirming calls `UserManagementService::assignTeam()`
**And** the coach's role is elevated to `team_owner` if this is their first team
**And** a notification email is sent and `team.owner_assigned` is logged

**AC4: Admin can remove a team assignment**
**Given** an admin removes a team assignment
**When** the removal is confirmed
**Then** the `team_owners` record is deleted
**And** if the coach has no remaining teams, their role reverts to `user`
**And** `team.owner_removed` is logged and notification email is sent

**AC5: Second team assignment attempt shows friendly error**
**Given** a coach already has a team and admin attempts to assign a second
**When** `TeamAlreadyClaimedException` is thrown
**Then** a user-friendly error is shown: "This coach already has a team assigned. Multiple team assignments are not supported in this version."

---

## Tasks / Subtasks

- [x] **Area A — Pending Queue in `admin/teams/index.php`** (MODIFY existing file)
  - [x] Add `case 'approve_registration':` to existing POST switch — call `TeamRegistrationService::approve()`, PRG flash redirect
  - [x] Render "Pending Team Registrations" section ABOVE existing active teams table
  - [x] Each row: coach name, team name, league, program/season, submitted date, "Approve" button
  - [x] Approve button opens inline division-select form (or modal) — POST `action=approve_registration`, `team_id`, `division_id`, CSRF token
  - [x] Empty state: "No pending registrations" when queue is empty

- [x] **Area B — `admin/users/detail.php`** (NEW file — Story 8.3 extends it, do not over-build)
  - [x] User summary card: name, email, username, role, status, current team assignment
  - [x] "Assign to Team" section: dropdown of active-season teams, POST → `UserManagementService::assignTeam()`
  - [x] "Remove Assignment" button (when team assigned): confirm → `UserManagementService::removeTeam()`
  - [x] Flash error on `TeamAlreadyClaimedException`
  - [x] PRG redirect with `$_SESSION['flash_message']` / `$_SESSION['flash_error']`

- [x] **Area C — `includes/UserManagementService.php`** (NEW file — initial version only)
  - [x] Implement `assignTeam(int $userId, int $teamId, int $adminUserId): void`
  - [x] Implement `removeTeam(int $userId, int $teamId, int $adminUserId): void`
  - [x] Do NOT add `getList`, `update`, `setRole`, `disable`, `enable`, `delete`, `resetPassword` — those are Story 8.1

---

## Dev Notes

### Critical Facts — Read These First

**`TeamAlreadyClaimedException` is already defined in `TeamRegistrationService.php`** (Story 4.1). Do NOT redefine it. In `UserManagementService.php`, load TeamRegistrationService before checking for it:
```php
if (!class_exists('TeamRegistrationService')) {
    require_once __DIR__ . '/TeamRegistrationService.php';
}
// TeamAlreadyClaimedException is now available — no need to redeclare
```

**`team_owners` has NO `UNIQUE(user_id)` DB constraint** — the original story note is incorrect. The actual DB schema has `PRIMARY KEY (user_id, team_id)`, which allows a user to be assigned to multiple teams at the DB level. The 1:1 restriction is **app-layer only** in `UserManagementService::assignTeam()`. Query `team_owners WHERE user_id = $userId` to check before inserting.

**Admin session keys** (set in `auth.php`):
```php
$_SESSION['admin_id']        // int — admin_users.id
$_SESSION['admin_username']  // string
```
Use `(int) ($_SESSION['admin_id'] ?? 0)` as `$adminUserId` in service calls. NOT `admin_user_id`.

**`users.role_id`** is the role column (FK to `roles.id`). There is NO standalone `role` varchar column on users. To elevate role, look up the `team_owner` role ID from the `roles` table. But handle both cases (same as RegistrationService):
```php
private function getRoleId(string $roleName): ?int {
    $row = $this->db->fetchOne(
        'SELECT id FROM roles WHERE name = :name LIMIT 1',
        ['name' => $roleName]
    );
    return $row !== false ? (int) $row['id'] : null;
}

private function setUserRole(int $userId, string $roleName): void {
    if ($this->hasUsersColumn('role_id')) {
        $roleId = $this->getRoleId($roleName);
        if ($roleId !== null) {
            $this->db->query(
                'UPDATE users SET role_id = :role_id, updated_at = NOW() WHERE id = :id',
                ['role_id' => $roleId, 'id' => $userId]
            );
        }
    } elseif ($this->hasUsersColumn('role')) {
        $this->db->query(
            'UPDATE users SET role = :role, updated_at = NOW() WHERE id = :id',
            ['role' => $roleName, 'id' => $userId]
        );
    }
}

private function hasUsersColumn(string $column): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) return false;
    return $this->db->fetchOne(
        'SELECT 1 AS ok FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
         LIMIT 1',
        ['users', $column]
    ) !== false;
}
```

**`team_owners.assigned_by` is NOT NULL** — always pass `$adminUserId`.

**`teams` PK is `team_id`** (not `id`). Use `WHERE team_id = :team_id` in all team queries.

---

### Area C — `UserManagementService` Implementation Blueprint

```php
<?php
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

if (!class_exists('TeamRegistrationService')) {
    require_once __DIR__ . '/TeamRegistrationService.php';
}
if (!class_exists('ActivityLogger')) {
    require_once __DIR__ . '/ActivityLogger.php';
}
if (!class_exists('Logger')) {
    require_once __DIR__ . '/Logger.php';
}

class UserManagementService {
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

    public function assignTeam(int $userId, int $teamId, int $adminUserId): void {
        // Guard: 1:1 enforcement (app-layer, no DB unique constraint)
        $existing = $this->db->fetchOne(
            'SELECT team_id FROM team_owners WHERE user_id = :user_id LIMIT 1',
            ['user_id' => $userId]
        );
        if ($existing !== false) {
            throw new TeamAlreadyClaimedException(
                'This coach already has a team assigned. Multiple team assignments are not supported in this version.'
            );
        }

        // INSERT team_owners row
        $this->db->query(
            'INSERT INTO team_owners (user_id, team_id, assigned_by, created_at)
             VALUES (:user_id, :team_id, :assigned_by, NOW())',
            ['user_id' => $userId, 'team_id' => $teamId, 'assigned_by' => $adminUserId]
        );

        // Elevate role to team_owner (FR-ASSIGN-2): only if current role is 'user'
        $user = $this->db->fetchOne(
            'SELECT id, first_name, email, role_id FROM users WHERE id = :id LIMIT 1',
            ['id' => $userId]
        );
        if ($user !== false) {
            $userRoleId = (int) ($user['role_id'] ?? 0);
            $userRole = $this->db->fetchOne(
                'SELECT name FROM roles WHERE id = :id LIMIT 1',
                ['id' => $userRoleId]
            );
            $currentRoleName = (string) ($userRole['name'] ?? '');
            if ($currentRoleName === 'user') {
                $this->setUserRole($userId, 'team_owner');
            }

            // Operational email — failure logged, not surfaced (AR-12)
            try {
                $this->emailService->triggerNotificationToAddress(
                    'team_assignment_notification',
                    (string) $user['email'],
                    ['user_id' => $userId, 'team_id' => $teamId, 'first_name' => $user['first_name']]
                );
            } catch (Throwable $e) {
                error_log('[UserManagementService] assignTeam email failed: ' . $e->getMessage());
            }
        }

        ActivityLogger::log('team.owner_assigned', [
            'user_id' => $userId,
            'team_id' => $teamId,
            'admin_user_id' => $adminUserId,
        ]);
    }

    public function removeTeam(int $userId, int $teamId, int $adminUserId): void {
        $this->db->query(
            'DELETE FROM team_owners WHERE user_id = :user_id AND team_id = :team_id',
            ['user_id' => $userId, 'team_id' => $teamId]
        );

        // Check remaining teams — if none, revert role to 'user' (FR-ASSIGN-5)
        $remaining = $this->db->fetchOne(
            'SELECT COUNT(*) AS cnt FROM team_owners WHERE user_id = :user_id',
            ['user_id' => $userId]
        );
        if ($remaining !== false && (int) $remaining['cnt'] === 0) {
            $this->setUserRole($userId, 'user');
        }

        // Operational email — failure logged, not surfaced (AR-12)
        $user = $this->db->fetchOne(
            'SELECT first_name, email FROM users WHERE id = :id LIMIT 1',
            ['id' => $userId]
        );
        if ($user !== false) {
            try {
                $this->emailService->triggerNotificationToAddress(
                    'team_removal_notification',
                    (string) $user['email'],
                    ['user_id' => $userId, 'team_id' => $teamId, 'first_name' => $user['first_name']]
                );
            } catch (Throwable $e) {
                error_log('[UserManagementService] removeTeam email failed: ' . $e->getMessage());
            }
        }

        ActivityLogger::log('team.owner_removed', [
            'user_id' => $userId,
            'team_id' => $teamId,
            'admin_user_id' => $adminUserId,
        ]);
    }

    // ... private helpers: setUserRole(), getRoleId(), hasUsersColumn() — see blueprints above
}
```

---

### Area A — Modifying `admin/teams/index.php`

**Do NOT replace any existing code.** Add to the existing POST switch and add a new HTML section.

**New POST case** (insert into existing `switch ($action)` block):
```php
case 'approve_registration':
    try {
        $teamId   = (int) ($_POST['team_id'] ?? 0);
        $divisionId = (int) ($_POST['division_id'] ?? 0);
        $adminUserId = (int) ($_SESSION['admin_id'] ?? 0);

        if ($teamId === 0 || $divisionId === 0) {
            $error = 'Please select a division before approving.';
            break;
        }

        if (!class_exists('TeamRegistrationService')) {
            require_once EnvLoader::getPath('includes/TeamRegistrationService.php');
        }
        $service = new TeamRegistrationService();
        $service->approve($teamId, $adminUserId, $divisionId);

        $_SESSION['flash_message'] = 'Team registration approved successfully.';
        header('Location: index.php');
        exit;
    } catch (TeamAlreadyClaimedException $e) {
        $error = 'This coach already has a team assigned. Multiple team assignments are not supported in this version.';
    } catch (Throwable $e) {
        Logger::error('Team registration approval failed', ['error' => $e->getMessage()]);
        $error = 'Approval failed: ' . $e->getMessage();
    }
    break;
```

**Read flash message at top of GET rendering** (before HTML output):
```php
$flashMessage = $_SESSION['flash_message'] ?? '';
$flashError   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);
```

**New HTML section** (insert ABOVE the existing teams table, after any existing `$message`/`$error` display):
```php
<?php
if (!class_exists('TeamRegistrationService')) {
    require_once EnvLoader::getPath('includes/TeamRegistrationService.php');
}
$pendingRegistrations = (new TeamRegistrationService())->getPendingRegistrations();

// Query divisions for approve modal (grouped by season for usability)
$allDivisions = $db->fetchAll(
    'SELECT d.division_id, d.division_name, s.season_name, s.season_year, p.program_name
     FROM divisions d
     INNER JOIN seasons s ON s.season_id = d.season_id
     INNER JOIN programs p ON p.program_id = s.program_id
     ORDER BY p.program_name, s.season_year DESC, d.division_name'
);
?>

<div class="card mb-4">
  <div class="card-header bg-warning text-dark">
    <h5 class="mb-0">Pending Team Registrations (<?php echo count($pendingRegistrations); ?>)</h5>
  </div>
  <div class="card-body p-0">
    <?php if (empty($pendingRegistrations)): ?>
      <p class="p-3 mb-0 text-muted">No pending registrations.</p>
    <?php else: ?>
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>Coach</th><th>Team Name</th><th>League</th>
          <th>Season</th><th>Submitted</th><th>Action</th>
        </tr></thead>
        <tbody>
        <?php foreach ($pendingRegistrations as $reg):
          // Fetch season info for this team
          $season = $db->fetchOne(
            'SELECT s.season_name, s.season_year, p.program_name
             FROM teams t
             INNER JOIN seasons s ON s.season_id = t.season_id
             INNER JOIN programs p ON p.program_id = s.program_id
             WHERE t.team_id = :id LIMIT 1',
            ['id' => $reg['team_id']]
          );
        ?>
          <tr>
            <td><?php echo sanitize($reg['manager_first_name'] . ' ' . $reg['manager_last_name']); ?></td>
            <td><strong><?php echo sanitize($reg['team_name']); ?></strong></td>
            <td><?php echo sanitize($reg['league_name']); ?></td>
            <td>
              <?php if ($season): ?>
                <?php echo sanitize($season['program_name'] . ' — ' . $season['season_name'] . ' ' . $season['season_year']); ?>
              <?php endif; ?>
            </td>
            <td><?php echo sanitize($reg['created_date']); ?></td>
            <td>
              <!-- Inline approval form -->
              <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="approve_registration">
                <input type="hidden" name="team_id" value="<?php echo (int) $reg['team_id']; ?>">
                <select name="division_id" class="form-select form-select-sm d-inline w-auto" required>
                  <option value="">— Select Division —</option>
                  <?php foreach ($allDivisions as $div): ?>
                    <option value="<?php echo (int) $div['division_id']; ?>">
                      <?php echo sanitize($div['program_name'] . ': ' . $div['division_name'] . ' (' . $div['season_year'] . ')'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-success btn-sm ms-1">Approve</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
```

---

### Area B — `admin/users/detail.php` Structure

New file. Use the same EnvLoader bootstrap pattern as `teams/index.php`. Story 8.3 will ADD to this file — keep it clean and extension-friendly. Do NOT build full CRUD here.

```php
<?php
// [same EnvLoader bootstrap as teams/index.php]
Auth::requireAdmin();
$db = Database::getInstance();
$adminUserId = (int) ($_SESSION['admin_id'] ?? 0);

$userId = (int) ($_GET['id'] ?? 0);
if ($userId === 0) {
    header('Location: ../index.php'); exit;
}

$user = $db->fetchOne(
    'SELECT u.id, u.first_name, u.last_name, u.email, u.username, u.status, r.name AS role_name
     FROM users u
     LEFT JOIN roles r ON r.id = u.role_id
     WHERE u.id = :id LIMIT 1',
    ['id' => $userId]
);
if ($user === false) {
    header('Location: ../index.php'); exit;
}

// Current team assignment
$currentTeam = $db->fetchOne(
    'SELECT t.team_id, t.team_name, t.league_name
     FROM team_owners to2
     INNER JOIN teams t ON t.team_id = to2.team_id
     WHERE to2.user_id = :uid LIMIT 1',
    ['uid' => $userId]
);

// Flash messages
$flashMessage = $_SESSION['flash_message'] ?? '';
$flashError   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    } else {
        $action = $_POST['action'] ?? '';
        if (!class_exists('UserManagementService')) {
            require_once EnvLoader::getPath('includes/UserManagementService.php');
        }
        $service = new UserManagementService();

        if ($action === 'assign_team') {
            $teamId = (int) ($_POST['team_id'] ?? 0);
            try {
                $service->assignTeam($userId, $teamId, $adminUserId);
                $_SESSION['flash_message'] = 'Team assigned successfully.';
                header('Location: detail.php?id=' . $userId); exit;
            } catch (TeamAlreadyClaimedException $e) {
                $error = 'This coach already has a team assigned. Multiple team assignments are not supported in this version.';
            } catch (Throwable $e) {
                $error = 'Assignment failed: ' . $e->getMessage();
            }
        } elseif ($action === 'remove_team') {
            $teamId = (int) ($_POST['team_id'] ?? 0);
            try {
                $service->removeTeam($userId, $teamId, $adminUserId);
                $_SESSION['flash_message'] = 'Team assignment removed.';
                header('Location: detail.php?id=' . $userId); exit;
            } catch (Throwable $e) {
                $error = 'Removal failed: ' . $e->getMessage();
            }
        }
    }
}

// Active-season teams for assignment dropdown
$activeTeams = $db->fetchAll(
    "SELECT t.team_id, t.team_name, t.league_name, s.season_name, s.season_year
     FROM teams t
     INNER JOIN seasons s ON s.season_id = t.season_id
     WHERE t.status = 'active' AND s.season_status = 'Active'
     ORDER BY t.team_name"
);
```

---

### `admin/users/detail.php` — Key HTML Sections

**User summary card:**
```html
<div class="card mb-4">
  <div class="card-header"><h5>User: <?php echo sanitize($user['first_name'] . ' ' . $user['last_name']); ?></h5></div>
  <div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-3">Email</dt><dd class="col-sm-9"><?php echo sanitize($user['email']); ?></dd>
      <dt class="col-sm-3">Username</dt><dd class="col-sm-9"><?php echo sanitize($user['username']); ?></dd>
      <dt class="col-sm-3">Role</dt><dd class="col-sm-9"><?php echo sanitize($user['role_name']); ?></dd>
      <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><?php echo sanitize($user['status']); ?></dd>
    </dl>
  </div>
</div>
```

**Team assignment section:**
```html
<div class="card mb-4">
  <div class="card-header"><h5>Team Assignment</h5></div>
  <div class="card-body">
    <?php if ($currentTeam !== false): ?>
      <p><strong>Assigned Team:</strong> <?php echo sanitize($currentTeam['team_name']); ?> (<?php echo sanitize($currentTeam['league_name']); ?>)</p>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="remove_team">
        <input type="hidden" name="team_id" value="<?php echo (int) $currentTeam['team_id']; ?>">
        <button type="submit" class="btn btn-danger btn-sm"
          onclick="return confirm('Remove this team assignment?')">Remove Assignment</button>
      </form>
    <?php else: ?>
      <p class="text-muted">No team assigned.</p>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="assign_team">
        <select name="team_id" class="form-select w-auto d-inline" required>
          <option value="">— Select Team —</option>
          <?php foreach ($activeTeams as $team): ?>
            <option value="<?php echo (int) $team['team_id']; ?>">
              <?php echo sanitize($team['team_name'] . ' (' . $team['season_name'] . ' ' . $team['season_year'] . ')'); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm ms-2">Assign to Team</button>
      </form>
    <?php endif; ?>
  </div>
</div>
```

---

### Schema & Constraint Quick Reference

| Table | PK | Notes |
|-------|-----|-------|
| `teams` | `team_id` | status column from migration 003 |
| `team_owners` | `(user_id, team_id)` | assigned_by NOT NULL; no UNIQUE(user_id) at DB level |
| `users` | `id` | role via `role_id` FK to `roles` |
| `roles` | `id` | `name` values: `'user'`, `'team_owner'`, `'team_official'`, `'administrator'` |
| `divisions` | `division_id` | FK to seasons |
| `seasons` | `season_id` | `season_status ENUM('Planning','Registration','Active','Completed','Archived')` |

**Role elevation/reversion** (`users.role_id`):
- `user` → `team_owner` on `assignTeam()` when user currently has `user` role
- `team_owner` → `user` on `removeTeam()` when no remaining `team_owners` rows exist

---

### Email Templates

Use these template keys — the emails are operational (failure logged, not surfaced):
- `'team_assignment_notification'` — sent to coach on `assignTeam()`
- `'team_removal_notification'` — sent to coach on `removeTeam()`

Templates may not exist in `email_templates` yet — operational failure is acceptable. Use `triggerNotificationToAddress()` (same pattern as `InvitationService` and `RegistrationService`).

---

### Existing Code Patterns — Follow These Exactly

**Admin bootstrap** (copy from `teams/index.php` lines 7-31):
```php
$__dir = __DIR__;
$__found = false;
for ($__i = 0; $__i < 6; $__i++) {
    if (file_exists($__dir . '/includes/env-loader.php')) {
        require_once $__dir . '/includes/env-loader.php';
        $__found = true; break;
    }
    $__dir = dirname($__dir);
}
// [handle $__found = false case]
@include_once EnvLoader::getPath('includes/admin_bootstrap.php');
Auth::requireAdmin();
```

**Page asset links** (match existing admin pages):
```html
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/assets/css/style.css" rel="stylesheet">  <!-- or relative path -->
```

**CSRF**: `Auth::verifyCSRFToken()` / `Auth::generateCSRFToken()` — same as existing teams/index.php.

**Output**: `sanitize(string)` for all dynamic output.

**Service require pattern**: Use `EnvLoader::getPath('includes/ServiceName.php')` not hardcoded `__DIR__` paths in page files (admin pages are at different depths than includes/).

---

### What Does NOT Exist Yet

- `includes/UserManagementService.php` — created in this story (Area C)
- `public/admin/users/detail.php` — created in this story (Area B); Story 8.3 extends it
- `public/admin/users/` directory has only `invitations.php` right now; `detail.php` is a new file in the same directory

---

### Story 8.3 Compatibility Note

Story 8.3 **extends** `admin/users/detail.php` — it does not replace it. Structure the file so that:
1. The user summary card and team assignment section are complete blocks
2. Leave a clear comment `<!-- Story 8.3: full CRUD edit form goes here -->` in the layout where the edit form will be inserted
3. Do not hard-code assumptions about what else will be on the page

---

## Dev Agent Record

### Implementation Plan

1. **Area C first** — `UserManagementService` written with full TDD: 10 unit tests (RED), then service implementation (GREEN), 92/92 pass.
2. **Area A** — added `approve_registration` POST case into existing switch; injected `$flashMessage`/`$flashError` PRG read block; inserted pending queue card above active teams table with inline per-row division-select form.
3. **Area B** — new `admin/users/detail.php` with user summary card, team assignment section (assign or remove depending on current state), PRG redirect, `TeamAlreadyClaimedException` flash error, Story 8.3 comment placeholder.

### Debug Log

- `team_owners` has no `UNIQUE(user_id)` DB constraint — app-layer guard via `fetchOne` before INSERT confirmed correct.
- `teams` PK is `team_id` (not `id`) — used throughout.
- `team_owners.assigned_by` is NOT NULL — always passed `$adminUserId`.
- `users.role_id` FK to `roles.id` — `setUserRole()` / `hasUsersColumn()` pattern matches existing `TeamRegistrationService` approach.
- Flash messages in `teams/index.php`: added both `$flashMessage`/`$flashError` (from PRG) **and** preserved existing `$message`/`$error` (inline POST errors) — both paths render correctly.

### Completion Notes

All 5 ACs satisfied across all three work areas:
- **AC1** Pending queue renders above active teams; inline division-select + Approve per row; empty-state text.
- **AC2** `approve_registration` → `TeamRegistrationService::approve()` → team active, coach elevated, email sent, PRG redirect clears queue entry.
- **AC3** `detail.php` assign flow → `UserManagementService::assignTeam()` → role elevated if `user`; `team.owner_assigned` logged.
- **AC4** `detail.php` remove flow → `UserManagementService::removeTeam()` → role reverted to `user` when no teams remain; `team.owner_removed` logged.
- **AC5** `TeamAlreadyClaimedException` caught on both approve and assign paths; user-friendly message displayed.

Tests: 92 passed, 0 failed, 0 skipped (10 new `UserManagementService` tests + 82 pre-existing).

---

## File List

- `includes/UserManagementService.php` — **new** (initial version: `assignTeam` + `removeTeam` only)
- `public/admin/teams/index.php` — **modify** (add pending queue section + approve_registration case)
- `public/admin/users/detail.php` — **new** (user summary + team assignment UI; Story 8.3 extends this)
- `_bmad-output/implementation-artifacts/4-3-admin-team-assignment-pending-queue.md` — this file

---

## Change Log
- 2026-05-05: Story file created, status set to ready.
- 2026-05-06: Comprehensive dev context added — exception ownership, DB constraint corrections, admin session keys, role elevation blueprint, full Area A/B/C code structure.
- 2026-05-07: Code review patches applied — scoped division UI + server validation, removeTeam rowCount guard, admin_id checks, generic error UI + logging, team email templates migration 011, dashboard back link, assign team_id server validation.

### Review Findings

- [x] [Review][Resolved] Role elevation in `UserManagementService::assignTeam()` — **Decision:** Option 1 — elevate to `team_owner` whenever `resolveRoleName($role_id) !== 'team_owner'` (implemented in code + unit test for non-`user` prerequisite role).

- [x] [Review][Patch] Pending approval division list scoped to each pending team’s `season_id`; `TeamRegistrationService::approve()` validates division belongs to team season [`public/admin/teams/index.php`, `includes/TeamRegistrationService.php`].

- [x] [Review][Patch] `removeTeam()` returns early when `DELETE` affects zero rows (no email/log) [`includes/UserManagementService.php`].

- [x] [Review][Patch] Guard when `$_SESSION['admin_id']` &lt; 1 on approve and on user detail [`public/admin/teams/index.php`, `public/admin/users/detail.php`].

- [x] [Review][Patch] `Throwable` handlers use `Logger::error` + generic user-facing message [`public/admin/users/detail.php`, `public/admin/teams/index.php` approve path].

- [x] [Review][Patch] Email templates seeded — `database/migrations/011_seed_team_email_templates.sql` (`team_registration_approved`, `team_assignment_notification`, `team_removal_notification`).

- [x] [Review][Patch] Back link label corrected to “Back to Dashboard” [`public/admin/users/detail.php`].

- [x] [Review][Patch] Assign POST validates `team_id` against active-season teams query before `assignTeam()` [`public/admin/users/detail.php`].

- [x] [Review][Defer] N+1 query pattern loading season/program per pending row [`public/admin/teams/index.php` pending loop ~373–382] — deferred, pre-existing page-performance concern; batch JOIN in `getPendingRegistrations()` would be cleaner at higher volume.
