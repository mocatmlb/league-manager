<?php
/**
 * Unit Tests: TeamRegistrationService
 *
 * Story 4.1 — TeamRegistrationService Backend
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ActivityLogger.php';
require_once __DIR__ . '/../../includes/TeamRegistrationService.php';

// ---------------------------------------------------------------------------
// Mock infrastructure
// ---------------------------------------------------------------------------

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
    public array $users        = [];
    public array $invitations  = [];
    public array $teams        = [];
    public array $teamOwners   = [];
    public array $locations    = [];
    public array $divisions    = [];
    public array $games        = [];
    public array $roles        = [];
    public array $activityEvents = [];
    public array $queryCalls   = [];
    public int   $nextTeamId   = 100;
    private TRSMockConnection $conn;

    public function __construct() {
        // Intentionally bypass Database real connection initialization.
        $this->conn = new TRSMockConnection();
    }

    public function getConnection(): object { return $this->conn; }

    public function fetchOne($sql, $params = []) {
        $sql = trim($sql);

        // User lookup by id
        if (stripos($sql, 'FROM users WHERE id = :id') !== false) {
            foreach ($this->users as $user) {
                if ((int) $user['id'] === (int) ($params['id'] ?? -1)) {
                    return $user;
                }
            }
            return false;
        }

        // Invitation check — completed status (AC2)
        if (stripos($sql, 'FROM user_invitations WHERE email = :email') !== false) {
            foreach ($this->invitations as $inv) {
                if ($inv['email'] === ($params['email'] ?? '') && $inv['status'] === 'completed') {
                    return $inv;
                }
            }
            return false;
        }

        // Team lookup by team_id (used in approve())
        if (stripos($sql, 'FROM teams WHERE team_id = :id') !== false) {
            foreach ($this->teams as $team) {
                if ((int) $team['team_id'] === (int) ($params['id'] ?? -1)) {
                    return $team;
                }
            }
            return false;
        }

        // User lookup by email (used in approve() to resolve coach)
        if (stripos($sql, 'FROM users WHERE email = :email') !== false) {
            foreach ($this->users as $user) {
                if ($user['email'] === ($params['email'] ?? '')) {
                    return $user;
                }
            }
            return false;
        }

        // Team-owner lookup by team_id (used in deleteRegistration)
        if (stripos($sql, 'FROM team_owners WHERE team_id = :team_id') !== false) {
            foreach ($this->teamOwners as $owner) {
                if ((int) $owner['team_id'] === (int) ($params['team_id'] ?? -1)) {
                    return $owner;
                }
            }
            return false;
        }

        // COUNT of team_owners remaining for user (deleteRegistration role-revert check)
        if (stripos($sql, 'COUNT(*)') !== false
            && stripos($sql, 'FROM team_owners') !== false
            && stripos($sql, 'user_id = :user_id') !== false) {
            $uid = (int) ($params['user_id'] ?? -1);
            $cnt = 0;
            foreach ($this->teamOwners as $owner) {
                if ((int) ($owner['user_id'] ?? 0) === $uid) {
                    $cnt++;
                }
            }
            return ['cnt' => $cnt];
        }

        // Team-owner duplicate guard (approve — exists check, no COUNT)
        if (stripos($sql, 'FROM team_owners WHERE user_id = :user_id') !== false) {
            foreach ($this->teamOwners as $owner) {
                if ((int) $owner['user_id'] === (int) ($params['user_id'] ?? -1)) {
                    return $owner;
                }
            }
            return false;
        }

        // Division must belong to team's season (approve validation)
        if (stripos($sql, 'FROM divisions WHERE division_id') !== false
            && stripos($sql, 'season_id') !== false) {
            foreach ($this->divisions as $div) {
                if ((int) ($div['division_id'] ?? 0) === (int) ($params['division_id'] ?? -1)
                    && (int) ($div['season_id'] ?? 0) === (int) ($params['season_id'] ?? -1)) {
                    return $div;
                }
            }
            return false;
        }

        // Duplicate-season check (AC1 Story 11.3)
        if (stripos($sql, 'submitted_by_user_id') !== false
            && stripos($sql, 'season_id') !== false
            && stripos($sql, "status IN ('pending', 'active')") !== false) {
            $uid = (int) ($params['uid'] ?? -1);
            $sid = (int) ($params['sid'] ?? -1);
            $cnt = 0;
            foreach ($this->teams as $team) {
                if ((int) ($team['submitted_by_user_id'] ?? 0) === $uid
                    && (int) ($team['season_id'] ?? 0) === $sid
                    && in_array($team['status'] ?? '', ['pending', 'active'], true)) {
                    $cnt++;
                }
            }
            return ['cnt' => $cnt];
        }

        // Game assignment count (deleteRegistration guard)
        if (stripos($sql, 'FROM games WHERE home_team_id') !== false) {
            $id = (int) ($params['id'] ?? -1);
            $cnt = 0;
            foreach ($this->games as $game) {
                if ((int) ($game['home_team_id'] ?? 0) === $id
                    || (int) ($game['away_team_id'] ?? 0) === $id) {
                    $cnt++;
                }
            }
            return ['cnt' => $cnt];
        }

        // Roles lookup by name (deleteRegistration role-revert)
        if (stripos($sql, "FROM roles WHERE name") !== false) {
            foreach ($this->roles as $role) {
                if (stripos($sql, "'" . $role['name'] . "'") !== false) {
                    return $role;
                }
            }
            return false;
        }

        return false;
    }

    public function fetchAll($sql, $params = []): array {
        // findDuplicateCandidates() — locations query
        if (stripos($sql, 'FROM locations') !== false
            && (stripos($sql, "active_status = 'Active'") !== false
                || stripos($sql, "status = 'pending'") !== false)) {
            return array_values($this->locations);
        }

        if (stripos($sql, "WHERE t.status = 'pending'") !== false) {
            $pending = array_values(
                array_filter($this->teams, fn($t) => ($t['status'] ?? '') === 'pending')
            );
            $out = [];
            foreach ($pending as $t) {
                $row = [
                    'team_id'             => $t['team_id'],
                    'team_name'           => $t['team_name'],
                    'league_name'         => $t['league_name'],
                    'season_id'           => $t['season_id'],
                    'manager_first_name'  => $t['manager_first_name'],
                    'manager_last_name'   => $t['manager_last_name'],
                    'manager_email'       => $t['manager_email'],
                    'created_date'        => $t['created_date'] ?? null,
                    'submitter_first_name'=> null,
                    'submitter_last_name' => null,
                ];
                foreach ($this->users as $u) {
                    if (($u['email'] ?? '') === ($t['manager_email'] ?? '')) {
                        $row['submitter_first_name'] = $u['first_name'];
                        $row['submitter_last_name']  = $u['last_name'];
                        break;
                    }
                }
                $out[] = $row;
            }
            return $out;
        }
        return [];
    }

    public function query($sql, $params = []) {
        $this->queryCalls[] = ['sql' => $sql, 'params' => $params];

        // Activity log INSERT (from ActivityLogger::log)
        if (stripos($sql, 'INSERT INTO activity_log') !== false) {
            $this->activityEvents[] = [
                'event'   => $params['event'],
                'context' => json_decode($params['context'], true),
            ];
            return new TRSMockStatement(1);
        }

        // Team INSERT — capture row and set lastInsertId for the service
        if (stripos($sql, 'INSERT INTO teams') !== false) {
            $id = $this->nextTeamId++;
            $this->conn->setLastInsertId($id);
            $this->teams[] = [
                'team_id'               => $id,
                'team_name'             => $params['team_name'],
                'league_name'           => $params['league_name'],
                'season_id'             => $params['season_id'],
                'submitted_by_user_id'  => $params['submitted_by_user_id'],
                'manager_first_name'    => $params['manager_first_name'],
                'manager_last_name'     => $params['manager_last_name'],
                'manager_email'         => $params['manager_email'],
                'status'                => 'pending',
                'created_date'          => date('Y-m-d H:i:s'),
            ];
            return new TRSMockStatement(1);
        }

        // Location INSERT
        if (stripos($sql, 'INSERT INTO locations') !== false) {
            $this->locations[] = $params;
            return new TRSMockStatement(1);
        }

        // Team UPDATE on approve()
        if (stripos($sql, "UPDATE teams SET status = 'active'") !== false) {
            foreach ($this->teams as &$team) {
                if ((int) $team['team_id'] === (int) ($params['team_id'] ?? -1)) {
                    $team['status']      = 'active';
                    $team['division_id'] = $params['division_id'];
                }
            }
            unset($team);
            return new TRSMockStatement(1);
        }

        // Team UPDATE on reject()
        if (stripos($sql, "UPDATE teams SET status = 'rejected'") !== false) {
            foreach ($this->teams as &$team) {
                if ((int) $team['team_id'] === (int) ($params['team_id'] ?? -1)) {
                    $team['status'] = 'rejected';
                }
            }
            unset($team);
            return new TRSMockStatement(1);
        }

        // Team UPDATE on update()
        if (stripos($sql, 'UPDATE teams SET team_name') !== false) {
            foreach ($this->teams as &$team) {
                if ((int) $team['team_id'] === (int) ($params['team_id'] ?? -1)) {
                    $team['team_name']            = $params['team_name'] ?? $team['team_name'];
                    $team['season_id']             = $params['season_id'] ?? $team['season_id'];
                    $team['league_name']           = $params['league_name'] ?? $team['league_name'];
                    $team['submitted_by_user_id']  = $params['submitted_by_user_id'] ?? $team['submitted_by_user_id'];
                }
            }
            unset($team);
            return new TRSMockStatement(1);
        }

        // Team DELETE on deleteRegistration()
        if (stripos($sql, 'DELETE FROM teams WHERE team_id') !== false) {
            $id = (int) ($params['team_id'] ?? -1);
            foreach ($this->teams as $k => $team) {
                if ((int) $team['team_id'] === $id) {
                    unset($this->teams[$k]);
                    $this->teams = array_values($this->teams);
                    return new TRSMockStatement(1);
                }
            }
            return new TRSMockStatement(0);
        }

        // Team-owner INSERT
        if (stripos($sql, 'INSERT INTO team_owners') !== false) {
            $this->teamOwners[] = [
                'user_id'     => $params['user_id'],
                'team_id'     => $params['team_id'],
                'assigned_by' => $params['assigned_by'],
            ];
            return new TRSMockStatement(1);
        }

        // Team-owner DELETE on deleteRegistration()
        if (stripos($sql, 'DELETE FROM team_owners WHERE team_id') !== false) {
            $id = (int) ($params['team_id'] ?? -1);
            $this->teamOwners = array_values(
                array_filter($this->teamOwners, fn($o) => (int) ($o['team_id'] ?? 0) !== $id)
            );
            return new TRSMockStatement(1);
        }

        // User role update (deleteRegistration role-revert)
        if (stripos($sql, 'UPDATE users SET role_id') !== false) {
            foreach ($this->users as &$user) {
                if ((int) $user['id'] === (int) ($params['id'] ?? -1)) {
                    $user['role_id'] = $params['role_id'];
                }
            }
            unset($user);
            return new TRSMockStatement(1);
        }

        return new TRSMockStatement(0);
    }
}

class TRSMockEmail {
    public array $calls = [];
    public function triggerNotificationToAddress(string $template, string $email, array $context = []): bool {
        $this->calls[] = ['template' => $template, 'email' => $email, 'context' => $context];
        return true;
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

register_test('AC1: submit creates pending team with correct auto-generated name', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->users[] = ['id' => 1, 'first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane@example.com'];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $teamId = $service->submit(1, [
        'season_id'   => 5,
        'league_name' => 'Metro',
        'locations'   => [],
    ]);

    assert_equals($teamId, 100, 'submit must return the inserted team_id');
    assert_equals(count($db->teams), 1, 'submit must insert one team row');
    assert_equals($db->teams[0]['status'], 'pending', 'new team must have status=pending');
    assert_equals($db->teams[0]['team_name'], 'Metro-Smith', 'team name must be {league}-{last_name}');
    assert_equals($db->teams[0]['season_id'], 5, 'team must carry the supplied season_id');

    Database::setInstance(null);
});

register_test('AC1: submit uses "other" league value in team name', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->users[] = ['id' => 2, 'first_name' => 'Tom', 'last_name' => 'Jones', 'email' => 'tom@example.com'];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $service->submit(2, [
        'season_id'    => 5,
        'league_name'  => 'other',
        'other_league' => 'Custom League',
        'locations'    => [],
    ]);

    assert_equals($db->teams[0]['team_name'], 'Custom League-Jones', 'other league must use other_league value in team name');
    assert_equals($db->teams[0]['league_name'], 'Custom League', 'league_name column must store the other_league value');

    Database::setInstance(null);
});

register_test('Post-Epic-11: invitation-registered user CAN self-register a team (guard removed 2026-05-10)', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->users[]       = ['id' => 3, 'first_name' => 'Bob', 'last_name' => 'Lee', 'email' => 'bob@example.com'];
    $db->invitations[] = ['id' => 1, 'email' => 'bob@example.com', 'status' => 'completed'];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $teamId = $service->submit(3, ['season_id' => 5, 'league_name' => 'Metro', 'locations' => []]);

    assert_true($teamId > 0, 'submit must succeed for invitation-registered user');
    assert_equals(count($db->teams), 1, 'one pending team must be created');
    assert_equals($db->teams[0]['status'], 'pending', 'team must be pending pending admin approval');

    Database::setInstance(null);
});

register_test('AC1: submit inserts up to 5 location rows', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->users[] = ['id' => 4, 'first_name' => 'Ana', 'last_name' => 'Cruz', 'email' => 'ana@example.com'];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $service->submit(4, [
        'season_id'   => 5,
        'league_name' => 'East',
        'locations'   => [
            ['name' => 'Park A', 'address' => '100 Main St', 'notes' => ''],
            ['name' => 'Park B', 'address' => '',            'notes' => 'Near school'],
            ['name' => 'Park C', 'address' => '',            'notes' => ''],
        ],
    ]);

    assert_equals(count($db->locations), 3, 'submit must insert one location row per non-empty location entry');
    assert_equals($db->locations[0]['location_name'], 'Park A', 'location_name must be set from name key');
    assert_equals($db->locations[0]['submitted_by_user_id'], 4, 'submitted_by_user_id must be the coach user_id');

    Database::setInstance(null);
});

register_test('AC3: approve sets team active, assigns team owner, and logs audit events', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    // Pre-seed a pending team
    $db->teams[] = [
        'team_id'            => 200,
        'team_name'          => 'East-Williams',
        'league_name'        => 'East',
        'season_id'          => 5,
        'manager_first_name' => 'Cara',
        'manager_last_name'  => 'Williams',
        'manager_email'      => 'cara@example.com',
        'status'             => 'pending',
        'created_date'       => date('Y-m-d H:i:s'),
    ];
    $db->divisions[] = ['division_id' => 7, 'season_id' => 5];
    $db->users[] = ['id' => 10, 'first_name' => 'Cara', 'last_name' => 'Williams', 'email' => 'cara@example.com'];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $service->approve(200, 99, 7);

    // Team updated to active
    assert_equals($db->teams[0]['status'], 'active', 'approve must set team status to active');
    assert_equals($db->teams[0]['division_id'], 7, 'approve must set division_id');

    // Team owner row inserted
    assert_equals(count($db->teamOwners), 1, 'approve must insert one team_owners row');
    assert_equals($db->teamOwners[0]['user_id'], 10, 'team_owner user_id must be the coach');
    assert_equals($db->teamOwners[0]['team_id'], 200, 'team_owner team_id must match the approved team');
    assert_equals($db->teamOwners[0]['assigned_by'], 99, 'team_owner assigned_by must be the admin user');

    // Audit events
    $events = array_column($db->activityEvents, 'event');
    assert_true(in_array('team.registration_approved', $events, true), 'approve must log team.registration_approved');
    assert_true(in_array('team.owner_assigned', $events, true), 'approve must log team.owner_assigned');

    // Approval email sent
    assert_equals(count($email->calls), 1, 'approve must trigger one notification email');
    assert_equals($email->calls[0]['template'], 'team_registration_approved', 'approval email must use team_registration_approved template');

    Database::setInstance(null);
});

register_test('approve throws RuntimeException when division is not in team season', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->teams[] = [
        'team_id'            => 201,
        'team_name'          => 'West-Lee',
        'league_name'        => 'West',
        'season_id'          => 5,
        'manager_first_name' => 'Pat',
        'manager_last_name'  => 'Lee',
        'manager_email'      => 'pat@example.com',
        'status'             => 'pending',
        'created_date'       => date('Y-m-d H:i:s'),
    ];
    // Division 99 exists only for season 9, not team's season 5
    $db->divisions[] = ['division_id' => 99, 'season_id' => 9];
    $db->users[] = ['id' => 11, 'first_name' => 'Pat', 'last_name' => 'Lee', 'email' => 'pat@example.com'];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $thrown = false;
    try {
        $service->approve(201, 1, 99);
    } catch (RuntimeException $e) {
        $thrown = str_contains($e->getMessage(), 'not valid for this team');
    }

    assert_true($thrown, 'approve must reject division that does not belong to team season');

    Database::setInstance(null);
});

register_test('AC4: getPendingRegistrations returns empty array when no pending teams', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $result = $service->getPendingRegistrations();

    assert_equals($result, [], 'getPendingRegistrations must return empty array when no pending teams exist');

    Database::setInstance(null);
});

register_test('AC4: getPendingRegistrations includes submitter names for pending teams', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->teams[] = [
        'team_id'            => 300,
        'team_name'          => 'North-Diaz',
        'league_name'        => 'North',
        'season_id'          => 5,
        'manager_first_name' => 'Mia',
        'manager_last_name'  => 'Diaz',
        'manager_email'      => 'mia@example.com',
        'status'             => 'pending',
        'created_date'       => date('Y-m-d H:i:s'),
    ];
    $db->users[] = ['id' => 21, 'first_name' => 'Mia', 'last_name' => 'Diaz', 'email' => 'mia@example.com'];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $rows = $service->getPendingRegistrations();

    assert_equals(count($rows), 1, 'must return one pending registration row');
    assert_equals($rows[0]['submitter_first_name'], 'Mia', 'LEFT JOIN must expose submitter first name');
    assert_equals($rows[0]['submitter_last_name'], 'Diaz', 'LEFT JOIN must expose submitter last name');

    Database::setInstance(null);
});

register_test('approve throws RuntimeException when team_id does not exist', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $thrown = false;
    try {
        $service->approve(99999, 1, 1);
    } catch (RuntimeException $e) {
        $thrown = str_contains($e->getMessage(), 'Team not found');
    }

    assert_true($thrown, 'approve must throw when team_id is unknown');

    Database::setInstance(null);
});

register_test('approve throws RuntimeException when team is not pending', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->teams[] = [
        'team_id'            => 400,
        'team_name'          => 'East-Fox',
        'league_name'        => 'East',
        'season_id'          => 5,
        'manager_first_name' => 'Sam',
        'manager_last_name'  => 'Fox',
        'manager_email'      => 'sam@example.com',
        'status'             => 'active',
        'created_date'       => date('Y-m-d H:i:s'),
    ];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $thrown = false;
    try {
        $service->approve(400, 1, 1);
    } catch (RuntimeException $e) {
        $thrown = str_contains($e->getMessage(), 'not pending');
    }

    assert_true($thrown, 'approve must reject non-pending teams');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// Story 11.3 — One Team Per Season Limit
// ---------------------------------------------------------------------------

register_test('11.3 AC1: submit throws RuntimeException when user has pending team for same season', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->users[] = ['id' => 1, 'first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane@example.com'];
    // Pre-seed a pending team for the same user + season
    $db->teams[] = [
        'team_id'              => 500,
        'team_name'            => 'Metro-Smith',
        'league_name'          => 'Metro',
        'season_id'            => 5,
        'submitted_by_user_id' => 1,
        'manager_first_name'   => 'Jane',
        'manager_last_name'    => 'Smith',
        'manager_email'        => 'jane@example.com',
        'status'               => 'pending',
        'created_date'         => date('Y-m-d H:i:s'),
    ];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $thrown = false;
    $msg = '';
    try {
        $service->submit(1, ['season_id' => 5, 'league_name' => 'Metro', 'locations' => []]);
    } catch (RuntimeException $e) {
        $thrown = true;
        $msg = $e->getMessage();
    }

    assert_true($thrown, 'submit must throw RuntimeException when user has pending team for the same season');
    assert_equals($msg, 'You already have a team registration for this season.', 'exception message must match AC1');
    assert_equals(count($db->teams), 1, 'no new team must be inserted when duplicate is detected');

    Database::setInstance(null);
});

register_test('11.3 AC1: submit throws RuntimeException when user has active team for same season', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->users[] = ['id' => 2, 'first_name' => 'Tom', 'last_name' => 'Jones', 'email' => 'tom@example.com'];
    $db->teams[] = [
        'team_id'              => 501,
        'team_name'            => 'East-Jones',
        'league_name'          => 'East',
        'season_id'            => 7,
        'submitted_by_user_id' => 2,
        'manager_first_name'   => 'Tom',
        'manager_last_name'    => 'Jones',
        'manager_email'        => 'tom@example.com',
        'status'               => 'active',
        'created_date'         => date('Y-m-d H:i:s'),
    ];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $thrown = false;
    try {
        $service->submit(2, ['season_id' => 7, 'league_name' => 'West', 'locations' => []]);
    } catch (RuntimeException $e) {
        $thrown = str_contains($e->getMessage(), 'already have a team registration');
    }

    assert_true($thrown, 'submit must throw when user has an active team for the same season');

    Database::setInstance(null);
});

register_test('11.3 AC3: submit succeeds when user\'s only prior registration for season is rejected', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->users[] = ['id' => 3, 'first_name' => 'Ana', 'last_name' => 'Cruz', 'email' => 'ana@example.com'];
    // Pre-seed a rejected team for the same user + season
    $db->teams[] = [
        'team_id'              => 502,
        'team_name'            => 'North-Cruz',
        'league_name'          => 'North',
        'season_id'            => 9,
        'submitted_by_user_id' => 3,
        'manager_first_name'   => 'Ana',
        'manager_last_name'    => 'Cruz',
        'manager_email'        => 'ana@example.com',
        'status'               => 'rejected',
        'created_date'         => date('Y-m-d H:i:s'),
    ];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $teamId = $service->submit(3, ['season_id' => 9, 'league_name' => 'North', 'locations' => []]);

    assert_true($teamId > 0, 'submit must succeed when prior registration for season is rejected');
    assert_equals(count($db->teams), 2, 'a new pending team must be inserted alongside the rejected one');
    assert_equals($db->teams[1]['status'], 'pending', 'new team must have status=pending');
    assert_equals($db->teams[0]['status'], 'rejected', 'rejected record must remain unaffected');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// Story 11.4 — Admin Team Registration Approval (reject path)
// ---------------------------------------------------------------------------

register_test('11.4 AC2: reject() updates pending team status to rejected', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->teams[] = [
        'team_id'              => 600,
        'team_name'            => 'East-Park',
        'league_name'          => 'East',
        'season_id'            => 5,
        'submitted_by_user_id' => 1,
        'manager_first_name'   => 'Pat',
        'manager_last_name'    => 'Park',
        'manager_email'        => 'pat@example.com',
        'status'               => 'pending',
        'created_date'         => date('Y-m-d H:i:s'),
    ];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $service->reject(600, 99, 'Roster incomplete');

    assert_equals($db->teams[0]['status'], 'rejected', 'reject must set team status to rejected');

    Database::setInstance(null);
});

register_test('11.4 AC2: reject() throws RuntimeException when team is not pending', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->teams[] = [
        'team_id'              => 601,
        'team_name'            => 'West-Lo',
        'league_name'          => 'West',
        'season_id'            => 5,
        'submitted_by_user_id' => 1,
        'manager_first_name'   => 'Sam',
        'manager_last_name'    => 'Lo',
        'manager_email'        => 'sam@example.com',
        'status'               => 'active',
        'created_date'         => date('Y-m-d H:i:s'),
    ];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $thrown = false;
    try {
        $service->reject(601, 99);
    } catch (RuntimeException $e) {
        $thrown = str_contains($e->getMessage(), 'not pending');
    }
    assert_true($thrown, 'reject must throw when team is not pending');
    assert_equals($db->teams[0]['status'], 'active', 'team status must be unchanged on failure');

    Database::setInstance(null);
});

register_test('11.4 AC2: reject() logs team.registration_rejected and sends rejection email', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->teams[] = [
        'team_id'              => 602,
        'team_name'            => 'North-Ko',
        'league_name'          => 'North',
        'season_id'            => 5,
        'submitted_by_user_id' => 1,
        'manager_first_name'   => 'Lee',
        'manager_last_name'    => 'Ko',
        'manager_email'        => 'lee@example.com',
        'status'               => 'pending',
        'created_date'         => date('Y-m-d H:i:s'),
    ];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $service->reject(602, 42, 'Duplicate submission.');

    $events = array_column($db->activityEvents, 'event');
    assert_true(in_array('team.registration_rejected', $events, true), 'reject must log team.registration_rejected');

    assert_equals(count($email->calls), 1, 'reject must trigger one notification email');
    assert_equals($email->calls[0]['template'], 'team_registration_rejected', 'rejection email must use team_registration_rejected template');
    assert_equals($email->calls[0]['email'], 'lee@example.com', 'rejection email must go to coach manager_email');
    assert_equals($email->calls[0]['context']['reason'], 'Duplicate submission.', 'reason must be carried in template context');

    Database::setInstance(null);
});

register_test('11.4 AC2: reject() default reason "No reason provided." when none supplied', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->teams[] = [
        'team_id'              => 603,
        'team_name'            => 'South-Vo',
        'league_name'          => 'South',
        'season_id'            => 5,
        'submitted_by_user_id' => 1,
        'manager_first_name'   => 'Min',
        'manager_last_name'    => 'Vo',
        'manager_email'        => 'min@example.com',
        'status'               => 'pending',
        'created_date'         => date('Y-m-d H:i:s'),
    ];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $service->reject(603, 1);

    assert_equals($email->calls[0]['context']['reason'], 'No reason provided.', 'empty reason must default to "No reason provided."');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// Story 11.6 — Admin Create Team Registration
// ---------------------------------------------------------------------------

register_test('11.6 AC1: adminCreate() inserts a pending team for the target user', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->users[] = ['id' => 7, 'first_name' => 'Rio', 'last_name' => 'Park', 'email' => 'rio@example.com'];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $teamId = $service->adminCreate(
        7,
        ['season_id' => 5, 'league_name' => 'Metro', 'team_name' => 'Metro-Park'],
        99
    );

    assert_true($teamId > 0, 'adminCreate must return inserted team_id');
    assert_equals(count($db->teams), 1, 'adminCreate must insert one team');
    assert_equals($db->teams[0]['status'], 'pending', 'admin-created team must be pending');
    assert_equals($db->teams[0]['submitted_by_user_id'], 7, 'submitted_by_user_id must be the target user');
    assert_equals($db->teams[0]['team_name'], 'Metro-Park', 'team_name must use provided value');
    assert_equals($db->teams[0]['manager_email'], 'rio@example.com', 'manager_email must come from target user');

    Database::setInstance(null);
});

register_test('11.6: adminCreate() auto-generates {league}-{last_name} when team_name omitted', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->users[] = ['id' => 8, 'first_name' => 'Eli', 'last_name' => 'Wu', 'email' => 'eli@example.com'];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $service->adminCreate(8, ['season_id' => 5, 'league_name' => 'East'], 99);

    assert_equals($db->teams[0]['team_name'], 'East-Wu', 'team_name must default to {league}-{last_name}');

    Database::setInstance(null);
});

register_test('11.6 AC5: adminCreate() bypasses the 1-per-season limit', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->users[] = ['id' => 9, 'first_name' => 'Bo', 'last_name' => 'Ng', 'email' => 'bo@example.com'];
    // Pre-seed an existing pending team for same user + season
    $db->teams[] = [
        'team_id'              => 700,
        'team_name'            => 'East-Ng',
        'league_name'          => 'East',
        'season_id'            => 5,
        'submitted_by_user_id' => 9,
        'manager_first_name'   => 'Bo',
        'manager_last_name'    => 'Ng',
        'manager_email'        => 'bo@example.com',
        'status'               => 'pending',
        'created_date'         => date('Y-m-d H:i:s'),
    ];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $teamId = $service->adminCreate(9, ['season_id' => 5, 'league_name' => 'West'], 99);

    assert_true($teamId > 0, 'adminCreate must succeed even when user already has a pending team for the season');
    assert_equals(count($db->teams), 2, 'adminCreate must insert a second team for the same user/season');

    Database::setInstance(null);
});

register_test('11.6: adminCreate() logs admin.team_registration_created', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->users[] = ['id' => 10, 'first_name' => 'Iz', 'last_name' => 'Su', 'email' => 'iz@example.com'];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $service->adminCreate(10, ['season_id' => 5, 'league_name' => 'North'], 42);

    $events = array_column($db->activityEvents, 'event');
    assert_true(in_array('admin.team_registration_created', $events, true), 'adminCreate must log admin.team_registration_created');

    Database::setInstance(null);
});

register_test('11.6: adminCreate() throws when target user does not exist', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $thrown = false;
    try {
        $service->adminCreate(9999, ['season_id' => 5, 'league_name' => 'X'], 1);
    } catch (RuntimeException $e) {
        $thrown = str_contains($e->getMessage(), 'User not found');
    }
    assert_true($thrown, 'adminCreate must throw when target user is missing');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// Story 11.7 — Admin Edit / Update / Delete Team Registration
// ---------------------------------------------------------------------------

register_test('11.7 AC1: update() modifies team record for pending team', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->teams[] = [
        'team_id'              => 800,
        'team_name'            => 'East-Old',
        'league_name'          => 'East',
        'season_id'            => 5,
        'submitted_by_user_id' => 1,
        'manager_first_name'   => 'X',
        'manager_last_name'    => 'Y',
        'manager_email'        => 'xy@example.com',
        'status'               => 'pending',
        'created_date'         => date('Y-m-d H:i:s'),
    ];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $service->update(800, [
        'team_name'            => 'West-New',
        'season_id'            => 6,
        'league_name'          => 'West',
        'submitted_by_user_id' => 2,
    ], 99);

    assert_equals($db->teams[0]['team_name'], 'West-New', 'team_name must be updated');
    assert_equals($db->teams[0]['season_id'], 6, 'season_id must be updated');
    assert_equals($db->teams[0]['league_name'], 'West', 'league_name must be updated');
    assert_equals($db->teams[0]['submitted_by_user_id'], 2, 'submitted_by_user_id must be updated');

    $events = array_column($db->activityEvents, 'event');
    assert_true(in_array('admin.team_registration_updated', $events, true), 'update must log admin.team_registration_updated');

    Database::setInstance(null);
});

register_test('11.7 AC3: update() throws RuntimeException when team is active', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->teams[] = [
        'team_id'              => 801,
        'team_name'            => 'Active-Team',
        'league_name'          => 'East',
        'season_id'            => 5,
        'submitted_by_user_id' => 1,
        'manager_first_name'   => 'A',
        'manager_last_name'    => 'B',
        'manager_email'        => 'ab@example.com',
        'status'               => 'active',
        'created_date'         => date('Y-m-d H:i:s'),
    ];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $thrown = false;
    try {
        $service->update(801, [
            'team_name'            => 'Will-Fail',
            'season_id'            => 5,
            'league_name'          => 'East',
            'submitted_by_user_id' => 1,
        ], 99);
    } catch (RuntimeException $e) {
        $thrown = str_contains($e->getMessage(), 'pending or rejected');
    }
    assert_true($thrown, 'update must reject active team with the documented message');
    assert_equals($db->teams[0]['team_name'], 'Active-Team', 'team must be unchanged on failure');

    Database::setInstance(null);
});

register_test('11.7 AC4: deleteRegistration() removes team and team_owners row', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->roles[] = ['id' => 2, 'name' => 'user'];
    $db->users[] = ['id' => 21, 'first_name' => 'Owner', 'last_name' => 'One', 'email' => 'o@example.com', 'role_id' => 5];
    $db->teams[] = [
        'team_id'              => 900,
        'team_name'            => 'Doomed',
        'league_name'          => 'X',
        'season_id'            => 5,
        'submitted_by_user_id' => 21,
        'manager_first_name'   => 'O',
        'manager_last_name'    => 'One',
        'manager_email'        => 'o@example.com',
        'status'               => 'active',
        'created_date'         => date('Y-m-d H:i:s'),
    ];
    $db->teamOwners[] = ['user_id' => 21, 'team_id' => 900, 'assigned_by' => 1];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $service->deleteRegistration(900, 99);

    assert_equals(count($db->teams), 0, 'team row must be deleted');
    assert_equals(count($db->teamOwners), 0, 'team_owners row must be deleted');
    assert_equals($db->users[0]['role_id'], 2, 'owner role must revert to user when no remaining teams');

    $events = array_column($db->activityEvents, 'event');
    assert_true(in_array('admin.team_registration_deleted', $events, true), 'deleteRegistration must log admin.team_registration_deleted');

    Database::setInstance(null);
});

register_test('11.7 AC5: deleteRegistration() throws when team has game assignments', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->teams[] = [
        'team_id'              => 901,
        'team_name'            => 'Has-Games',
        'league_name'          => 'X',
        'season_id'            => 5,
        'submitted_by_user_id' => 1,
        'manager_first_name'   => 'G',
        'manager_last_name'    => 'A',
        'manager_email'        => 'g@example.com',
        'status'               => 'active',
        'created_date'         => date('Y-m-d H:i:s'),
    ];
    $db->games[] = ['home_team_id' => 901, 'away_team_id' => 999];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $thrown = false;
    try {
        $service->deleteRegistration(901, 99);
    } catch (RuntimeException $e) {
        $thrown = str_contains($e->getMessage(), 'has game assignments');
    }
    assert_true($thrown, 'deleteRegistration must throw when team has game assignments');
    assert_equals(count($db->teams), 1, 'team must remain on failure');

    Database::setInstance(null);
});

register_test('11.7: deleteRegistration() throws when team_id does not exist', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $thrown = false;
    try {
        $service->deleteRegistration(99999, 1);
    } catch (RuntimeException $e) {
        $thrown = str_contains($e->getMessage(), 'Team not found');
    }
    assert_true($thrown, 'deleteRegistration must throw for unknown team_id');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// Story 14.1 — findDuplicateCandidates()
// ---------------------------------------------------------------------------

register_test('14.1: findDuplicateCandidates returns empty array when no locations exist', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    // $db->locations is empty by default
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $result = $service->findDuplicateCandidates(['name' => 'Riverside Park', 'address' => '100 Main St']);

    assert_equals($result, [], 'must return empty array when no existing locations to compare against');

    Database::setInstance(null);
});

register_test('14.1: findDuplicateCandidates returns candidate when name similarity >= 70%', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->locations[] = [
        'location_id'   => 1,
        'location_name' => 'Riverside Park',
        'address'       => '100 Main St',
        'city'          => 'Springfield',
        'state'         => 'IL',
        'active_status' => 'Active',
        'status'        => 'active',
    ];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $result = $service->findDuplicateCandidates(['name' => 'Riverside Prk', 'address' => '']);

    assert_true(count($result) >= 1, 'must return candidate when name similarity is >= 70%');
    assert_equals($result[0]['location_id'], 1, 'candidate must include the similar location row');

    Database::setInstance(null);
});

register_test('14.1: findDuplicateCandidates returns candidate on exact case-insensitive address match', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->locations[] = [
        'location_id'   => 2,
        'location_name' => 'City Field',
        'address'       => '200 Oak Avenue',
        'city'          => 'Shelbyville',
        'state'         => 'IL',
        'active_status' => 'Active',
        'status'        => 'active',
    ];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    // Name is completely different but address matches exactly (case-insensitive)
    $result = $service->findDuplicateCandidates(['name' => 'Totally Different Name', 'address' => '200 oak avenue']);

    assert_true(count($result) >= 1, 'must return candidate when address matches case-insensitively');
    assert_equals($result[0]['location_id'], 2, 'candidate must include the address-matched row');

    Database::setInstance(null);
});

register_test('14.1: findDuplicateCandidates returns empty when similarity < 70% and addresses differ', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->locations[] = [
        'location_id'   => 3,
        'location_name' => 'Northside Complex',
        'address'       => '999 Far Away Rd',
        'city'          => 'Shelbyville',
        'state'         => 'IL',
        'active_status' => 'Active',
        'status'        => 'active',
    ];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $result = $service->findDuplicateCandidates(['name' => 'Southside Gym', 'address' => '1 Close St']);

    assert_equals($result, [], 'must return empty when name similarity < 70% and address does not match');

    Database::setInstance(null);
});
