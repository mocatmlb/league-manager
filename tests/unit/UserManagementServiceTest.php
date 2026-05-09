<?php
/**
 * Unit Tests: UserManagementService
 *
 * Story 4.3 — Admin Team Assignment & Pending Queue
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ActivityLogger.php';
require_once __DIR__ . '/../../includes/TeamRegistrationService.php'; // defines TeamAlreadyClaimedException

// ---------------------------------------------------------------------------
// Mock infrastructure
// ---------------------------------------------------------------------------

class UMSMockEmail {
    public array $sent = [];
    public bool $shouldThrow = false;

    public function triggerNotificationToAddress(string $key, string $email, array $vars): void {
        if ($this->shouldThrow) {
            throw new RuntimeException('Email send failed');
        }
        $this->sent[] = ['key' => $key, 'email' => $email, 'vars' => $vars];
    }
}

class UMSMockDatabase extends Database {
    public array $users      = [];
    public array $roles      = [];
    public array $teamOwners = [];
    public array $teams      = [];
    public array $queries    = [];

    private int $lastInsertId = 0;

    public function __construct() {
        // Bypass real connection
    }

    public function fetchOne($sql, $params = []) {
        $sql = trim($sql);

        // User lookup by id
        if (stripos($sql, 'FROM users WHERE id = :id') !== false) {
            foreach ($this->users as $u) {
                if ((int) $u['id'] === (int) ($params['id'] ?? -1)) {
                    return $u;
                }
            }
            return false;
        }

        // Role lookup by id
        if (stripos($sql, 'FROM roles WHERE id = :id') !== false) {
            foreach ($this->roles as $r) {
                if ((int) $r['id'] === (int) ($params['id'] ?? -1)) {
                    return $r;
                }
            }
            return false;
        }

        // Role lookup by name
        if (stripos($sql, 'FROM roles WHERE name = :name') !== false) {
            foreach ($this->roles as $r) {
                if ($r['name'] === ($params['name'] ?? '')) {
                    return $r;
                }
            }
            return false;
        }

        // team_owners guard — check existing assignment
        if (stripos($sql, 'FROM team_owners WHERE user_id = :user_id LIMIT 1') !== false) {
            foreach ($this->teamOwners as $to) {
                if ((int) $to['user_id'] === (int) ($params['user_id'] ?? -1)) {
                    return $to;
                }
            }
            return false;
        }

        // team_owners count remaining after removal
        if (stripos($sql, 'COUNT(*) AS cnt FROM team_owners WHERE user_id = :user_id') !== false) {
            $cnt = 0;
            foreach ($this->teamOwners as $to) {
                if ((int) $to['user_id'] === (int) ($params['user_id'] ?? -1)) {
                    $cnt++;
                }
            }
            return ['cnt' => $cnt];
        }

        // information_schema column existence check (hasUsersColumn)
        if (stripos($sql, 'information_schema.COLUMNS') !== false) {
            $col = $params[1] ?? '';
            foreach ($this->users[0] ?? [] as $key => $_) {
                if ($key === $col) {
                    return ['ok' => 1];
                }
            }
            return false;
        }

        // User lookup by first_name/email for removeTeam email
        if (stripos($sql, "SELECT first_name, email FROM users WHERE id = :id") !== false) {
            foreach ($this->users as $u) {
                if ((int) $u['id'] === (int) ($params['id'] ?? -1)) {
                    return ['first_name' => $u['first_name'], 'email' => $u['email']];
                }
            }
            return false;
        }

        return false;
    }

    public function query($sql, $params = []) {
        $this->queries[] = ['sql' => trim($sql), 'params' => $params];

        // Simulate INSERT team_owners
        if (stripos($sql, 'INSERT INTO team_owners') !== false) {
            $this->teamOwners[] = [
                'user_id'     => $params['user_id'],
                'team_id'     => $params['team_id'],
                'assigned_by' => $params['assigned_by'],
            ];
            $this->lastInsertId = 1;
        }

        // Simulate DELETE team_owners — rowCount reflects rows removed
        if (stripos($sql, 'DELETE FROM team_owners') !== false) {
            $userId = (int) ($params['user_id'] ?? -1);
            $teamId = (int) ($params['team_id'] ?? -1);
            $deleted = 0;
            $kept    = [];
            foreach ($this->teamOwners as $to) {
                if ((int) $to['user_id'] === $userId && (int) $to['team_id'] === $teamId) {
                    $deleted++;
                } else {
                    $kept[] = $to;
                }
            }
            $this->teamOwners = $kept;
            return new class($deleted) {
                private int $n;
                public function __construct(int $n) { $this->n = $n; }
                public function rowCount(): int { return $this->n; }
            };
        }

        // Simulate UPDATE users SET role_id
        if (stripos($sql, 'UPDATE users SET role_id') !== false) {
            foreach ($this->users as &$u) {
                if ((int) $u['id'] === (int) ($params['id'] ?? -1)) {
                    $u['role_id'] = $params['role_id'];
                    break;
                }
            }
            unset($u);
        }

        return new class { public function rowCount(): int { return 1; } };
    }

    public function fetchAll($sql, $params = []) {
        return [];
    }
}

// Helper to build a service with the mock DB and capture queries / email calls
function makeUMS(UMSMockDatabase $db, UMSMockEmail $email): object {
    require_once __DIR__ . '/../../includes/UserManagementService.php';
    return new UserManagementService($db, $email);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

register_test('assignTeam: inserts team_owners row', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();

    $db->users = [['id' => 1, 'first_name' => 'Alice', 'email' => 'alice@test.com', 'role_id' => 2]];
    $db->roles = [['id' => 2, 'name' => 'user'], ['id' => 3, 'name' => 'team_owner']];

    $svc = makeUMS($db, $email);
    $svc->assignTeam(1, 10, 99);

    $found = false;
    foreach ($db->teamOwners as $to) {
        if ($to['user_id'] === 1 && $to['team_id'] === 10 && $to['assigned_by'] === 99) {
            $found = true;
        }
    }
    assert_true($found, 'team_owners row should be inserted with correct user/team/admin IDs');
});

register_test('assignTeam: elevates role from user to team_owner', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();

    $db->users = [['id' => 1, 'first_name' => 'Bob', 'email' => 'bob@test.com', 'role_id' => 2]];
    $db->roles = [['id' => 2, 'name' => 'user'], ['id' => 3, 'name' => 'team_owner']];

    $svc = makeUMS($db, $email);
    $svc->assignTeam(1, 10, 99);

    $roleUpdated = false;
    foreach ($db->queries as $q) {
        if (stripos($q['sql'], 'UPDATE users SET role_id') !== false
            && (int) ($q['params']['role_id'] ?? 0) === 3
            && (int) ($q['params']['id'] ?? 0) === 1) {
            $roleUpdated = true;
        }
    }
    assert_true($roleUpdated, 'role_id should be updated to team_owner role ID (3)');
});

register_test('assignTeam: elevates role from non-team_owner role (e.g. coach) to team_owner', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();

    $db->users = [['id' => 1, 'first_name' => 'Pat', 'email' => 'pat@test.com', 'role_id' => 4]];
    $db->roles = [
        ['id' => 2, 'name' => 'user'],
        ['id' => 3, 'name' => 'team_owner'],
        ['id' => 4, 'name' => 'coach'],
    ];

    $svc = makeUMS($db, $email);
    $svc->assignTeam(1, 10, 99);

    $roleUpdated = false;
    foreach ($db->queries as $q) {
        if (stripos($q['sql'], 'UPDATE users SET role_id') !== false
            && (int) ($q['params']['role_id'] ?? 0) === 3
            && (int) ($q['params']['id'] ?? 0) === 1) {
            $roleUpdated = true;
        }
    }
    assert_true($roleUpdated, 'role_id should be updated to team_owner when prior role was coach');
});

register_test('assignTeam: does NOT elevate role when user is already team_owner', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();

    // User already has team_owner role
    $db->users = [['id' => 1, 'first_name' => 'Carol', 'email' => 'carol@test.com', 'role_id' => 3]];
    $db->roles = [['id' => 2, 'name' => 'user'], ['id' => 3, 'name' => 'team_owner']];

    $svc = makeUMS($db, $email);
    $svc->assignTeam(1, 10, 99);

    $roleUpdated = false;
    foreach ($db->queries as $q) {
        if (stripos($q['sql'], 'UPDATE users SET role_id') !== false) {
            $roleUpdated = true;
        }
    }
    assert_true(!$roleUpdated, 'role should NOT be updated when user already has team_owner role');
});

register_test('assignTeam: throws TeamAlreadyClaimedException when user already has a team', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();

    $db->users      = [['id' => 1, 'first_name' => 'Dave', 'email' => 'dave@test.com', 'role_id' => 3]];
    $db->roles      = [['id' => 2, 'name' => 'user'], ['id' => 3, 'name' => 'team_owner']];
    $db->teamOwners = [['user_id' => 1, 'team_id' => 5, 'assigned_by' => 99]];

    $svc = makeUMS($db, $email);
    $thrown = false;
    try {
        $svc->assignTeam(1, 10, 99);
    } catch (TeamAlreadyClaimedException $e) {
        $thrown = true;
    }
    assert_true($thrown, 'TeamAlreadyClaimedException should be thrown if user already has a team');
});

register_test('assignTeam: sends assignment email', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();

    $db->users = [['id' => 1, 'first_name' => 'Eve', 'email' => 'eve@test.com', 'role_id' => 2]];
    $db->roles = [['id' => 2, 'name' => 'user'], ['id' => 3, 'name' => 'team_owner']];

    $svc = makeUMS($db, $email);
    $svc->assignTeam(1, 10, 99);

    assert_true(count($email->sent) === 1, 'One email should be sent');
    assert_equals($email->sent[0]['key'], 'team_assignment_notification', 'Email key should be team_assignment_notification');
    assert_equals($email->sent[0]['email'], 'eve@test.com', 'Email should be sent to coach address');
});

register_test('assignTeam: email failure is logged silently, no exception thrown', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();
    $email->shouldThrow = true;

    $db->users = [['id' => 1, 'first_name' => 'Fred', 'email' => 'fred@test.com', 'role_id' => 2]];
    $db->roles = [['id' => 2, 'name' => 'user'], ['id' => 3, 'name' => 'team_owner']];

    $svc = makeUMS($db, $email);
    $threw = false;
    try {
        $svc->assignTeam(1, 10, 99);
    } catch (Throwable $e) {
        $threw = true;
    }
    assert_true(!$threw, 'Email failure should not propagate as exception');
});

register_test('removeTeam: deletes team_owners row', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();

    $db->users      = [['id' => 1, 'first_name' => 'Gina', 'email' => 'gina@test.com', 'role_id' => 3]];
    $db->roles      = [['id' => 2, 'name' => 'user'], ['id' => 3, 'name' => 'team_owner']];
    $db->teamOwners = [['user_id' => 1, 'team_id' => 10, 'assigned_by' => 99]];

    $svc = makeUMS($db, $email);
    $svc->removeTeam(1, 10, 99);

    $deleteFound = false;
    foreach ($db->queries as $q) {
        if (stripos($q['sql'], 'DELETE FROM team_owners') !== false
            && (int) ($q['params']['user_id'] ?? 0) === 1
            && (int) ($q['params']['team_id'] ?? 0) === 10) {
            $deleteFound = true;
        }
    }
    assert_true($deleteFound, 'DELETE from team_owners should be issued for correct user+team');
});

register_test('removeTeam: reverts role to user when no remaining teams', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();

    $db->users      = [['id' => 1, 'first_name' => 'Hal', 'email' => 'hal@test.com', 'role_id' => 3]];
    $db->roles      = [['id' => 2, 'name' => 'user'], ['id' => 3, 'name' => 'team_owner']];
    $db->teamOwners = [['user_id' => 1, 'team_id' => 10, 'assigned_by' => 99]];

    $svc = makeUMS($db, $email);
    $svc->removeTeam(1, 10, 99);

    $roleReverted = false;
    foreach ($db->queries as $q) {
        if (stripos($q['sql'], 'UPDATE users SET role_id') !== false
            && (int) ($q['params']['role_id'] ?? 0) === 2
            && (int) ($q['params']['id'] ?? 0) === 1) {
            $roleReverted = true;
        }
    }
    assert_true($roleReverted, 'role_id should be reverted to user role ID (2) when no teams remain');
});

register_test('removeTeam: sends removal email', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();

    $db->users      = [['id' => 1, 'first_name' => 'Iris', 'email' => 'iris@test.com', 'role_id' => 3]];
    $db->roles      = [['id' => 2, 'name' => 'user'], ['id' => 3, 'name' => 'team_owner']];
    $db->teamOwners = [['user_id' => 1, 'team_id' => 10, 'assigned_by' => 99]];

    $svc = makeUMS($db, $email);
    $svc->removeTeam(1, 10, 99);

    assert_true(count($email->sent) === 1, 'One email should be sent');
    assert_equals($email->sent[0]['key'], 'team_removal_notification', 'Email key should be team_removal_notification');
});

register_test('removeTeam: email failure is logged silently', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();
    $email->shouldThrow = true;

    $db->users      = [['id' => 1, 'first_name' => 'Jack', 'email' => 'jack@test.com', 'role_id' => 3]];
    $db->roles      = [['id' => 2, 'name' => 'user'], ['id' => 3, 'name' => 'team_owner']];
    $db->teamOwners = [['user_id' => 1, 'team_id' => 10, 'assigned_by' => 99]];

    $svc = makeUMS($db, $email);
    $threw = false;
    try {
        $svc->removeTeam(1, 10, 99);
    } catch (Throwable $e) {
        $threw = true;
    }
    assert_true(!$threw, 'Email failure in removeTeam should not propagate as exception');
});

register_test('removeTeam: no email or extra side effects when DELETE matches no row', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();

    $db->users      = [['id' => 1, 'first_name' => 'Kim', 'email' => 'kim@test.com', 'role_id' => 3]];
    $db->roles      = [['id' => 2, 'name' => 'user'], ['id' => 3, 'name' => 'team_owner']];
    $db->teamOwners = [['user_id' => 1, 'team_id' => 10, 'assigned_by' => 99]];

    $svc = makeUMS($db, $email);
    $svc->removeTeam(1, 999, 99);

    assert_true(count($email->sent) === 0, 'No removal email when assignment row did not exist');
});
