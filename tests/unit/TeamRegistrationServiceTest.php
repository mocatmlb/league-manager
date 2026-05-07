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

        // Team-owner duplicate guard
        if (stripos($sql, 'FROM team_owners WHERE user_id = :user_id') !== false) {
            foreach ($this->teamOwners as $owner) {
                if ((int) $owner['user_id'] === (int) ($params['user_id'] ?? -1)) {
                    return $owner;
                }
            }
            return false;
        }

        return false;
    }

    public function fetchAll($sql, $params = []): array {
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
                'team_id'            => $id,
                'team_name'          => $params['team_name'],
                'league_name'        => $params['league_name'],
                'season_id'          => $params['season_id'],
                'manager_first_name' => $params['manager_first_name'],
                'manager_last_name'  => $params['manager_last_name'],
                'manager_email'      => $params['manager_email'],
                'status'             => 'pending',
                'created_date'       => date('Y-m-d H:i:s'),
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

        // Team-owner INSERT
        if (stripos($sql, 'INSERT INTO team_owners') !== false) {
            $this->teamOwners[] = [
                'user_id'     => $params['user_id'],
                'team_id'     => $params['team_id'],
                'assigned_by' => $params['assigned_by'],
            ];
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

register_test('AC2: submit throws InvitationRegisteredUserException for invitation-registered user', function () {
    $db    = new TRSMockDatabase();
    $email = new TRSMockEmail();
    $db->users[]       = ['id' => 3, 'first_name' => 'Bob', 'last_name' => 'Lee', 'email' => 'bob@example.com'];
    $db->invitations[] = ['id' => 1, 'email' => 'bob@example.com', 'status' => 'completed'];
    Database::setInstance($db);
    $service = new TeamRegistrationService($db, $email);

    $thrown = false;
    try {
        $service->submit(3, ['season_id' => 5, 'league_name' => 'Metro', 'locations' => []]);
    } catch (InvitationRegisteredUserException $e) {
        $thrown = true;
    }

    assert_true($thrown, 'submit must throw InvitationRegisteredUserException for invitation-registered user');
    assert_equals(count($db->teams), 0, 'no team must be created when exception is thrown');

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
