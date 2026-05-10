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

    /**
     * Optional override for getList SELECT results — when set, fetchAll returns
     * this array for the user-list query so tests can assert pagination/filter
     * shape without simulating the full SQL engine.
     */
    public ?array $getListUsers = null;
    public int $getListTotal = 0;

    private int $lastInsertId = 0;

    public function __construct() {
        // Bypass real connection
    }

    public function beginTransaction(): bool { return true; }
    public function commit(): bool { return true; }
    public function rollback(): bool { return true; }

    public function fetchOne($sql, $params = []) {
        $sql = trim($sql);

        // getList COUNT(*) query
        if (stripos($sql, 'SELECT COUNT(*) AS cnt FROM users u') !== false) {
            return ['cnt' => $this->getListTotal];
        }

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

        // getList SELECT — return a stmt whose fetchAll returns our override
        if (stripos($sql, 'SELECT u.id, u.username, u.email, u.first_name') !== false) {
            $rows = $this->getListUsers ?? [];
            return new class($rows) {
                private array $rows;
                public function __construct(array $rows) { $this->rows = $rows; }
                public function fetchAll() { return $this->rows; }
                public function rowCount(): int { return count($this->rows); }
            };
        }

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
            $teamId = array_key_exists('team_id', $params) ? (int) $params['team_id'] : null;
            $deleted = 0;
            $kept    = [];
            foreach ($this->teamOwners as $to) {
                $matches = (int) $to['user_id'] === $userId
                    && ($teamId === null || (int) $to['team_id'] === $teamId);
                if ($matches) {
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

        // Simulate DELETE users
        if (stripos($sql, 'DELETE FROM users') !== false) {
            $userId = (int) ($params['id'] ?? -1);
            $kept = [];
            foreach ($this->users as $u) {
                if ((int) $u['id'] !== $userId) {
                    $kept[] = $u;
                }
            }
            $this->users = $kept;
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

        // Simulate UPDATE users SET status = 'disabled'  (disable())
        if (stripos($sql, "UPDATE users SET status = 'disabled'") !== false) {
            foreach ($this->users as &$u) {
                if ((int) $u['id'] === (int) ($params['id'] ?? -1)) {
                    $u['status'] = 'disabled';
                    $u['session_invalidated_at'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            unset($u);
        }

        // Simulate UPDATE users SET status = 'active'  (enable())
        if (stripos($sql, "UPDATE users SET status = 'active'") !== false) {
            foreach ($this->users as &$u) {
                if ((int) $u['id'] === (int) ($params['id'] ?? -1)) {
                    $u['status'] = 'active';
                    break;
                }
            }
            unset($u);
        }

        // Simulate UPDATE users SET password_hash (resetPassword)
        if (stripos($sql, 'UPDATE users SET password_hash') !== false) {
            foreach ($this->users as &$u) {
                if ((int) $u['id'] === (int) ($params['id'] ?? -1)) {
                    $u['password_hash'] = $params['hash'];
                    $u['force_password_change'] = 1;
                    break;
                }
            }
            unset($u);
        }

        // Simulate generic UPDATE users SET <fields> (update())
        if (stripos($sql, 'UPDATE users SET ') !== false
            && stripos($sql, 'role_id') === false
            && stripos($sql, 'status =') === false
            && stripos($sql, 'password_hash') === false) {
            foreach ($this->users as &$u) {
                if ((int) $u['id'] === (int) ($params['id'] ?? -1)) {
                    foreach ($params as $k => $v) {
                        if ($k !== 'id') {
                            $u[$k] = $v;
                        }
                    }
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

// ---------------------------------------------------------------------------
// Story 8.1 — Full CRUD tests (getList, update, setRole, disable, enable, delete, resetPassword)
// ---------------------------------------------------------------------------

register_test('Story 8.1 getList: returns paginated users with total_count', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();

    $db->getListUsers = [
        ['id' => 1, 'username' => 'alice',   'email' => 'a@x.com', 'first_name' => 'Alice',   'last_name' => 'A', 'status' => 'active', 'created_at' => '2026-01-01', 'role_name' => 'user'],
        ['id' => 2, 'username' => 'bob',     'email' => 'b@x.com', 'first_name' => 'Bob',     'last_name' => 'B', 'status' => 'active', 'created_at' => '2026-01-02', 'role_name' => 'team_owner'],
    ];
    $db->getListTotal = 47;

    $svc = makeUMS($db, $email);
    $result = $svc->getList([], 1, 25);

    assert_true(isset($result['users']) && isset($result['total_count']),
        'getList must return both users and total_count keys');
    assert_equals(count($result['users']), 2, 'getList should return 2 users in this page');
    assert_equals($result['total_count'], 47, 'getList should propagate total_count from COUNT query');
});

register_test('Story 8.1 getList: filters by search/role/status are bound as params', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();
    $db->getListUsers = [];
    $db->getListTotal = 0;

    $svc = makeUMS($db, $email);
    $svc->getList(['search' => 'smith', 'role' => 'team_owner', 'status' => 'active'], 2, 10);

    // Find the SELECT data query and verify all three filters were bound
    $found = null;
    foreach ($db->queries as $q) {
        if (stripos($q['sql'], 'SELECT u.id, u.username') !== false) {
            $found = $q;
            break;
        }
    }
    assert_not_null($found, 'getList should issue a SELECT against u/users');
    assert_true(strpos($found['params']['search1'] ?? '', 'smith') !== false, 'search term should be bound as :search1 with wildcards');
    assert_equals($found['params']['role_filter'] ?? null, 'team_owner', 'role filter should be bound');
    assert_equals($found['params']['status_filter'] ?? null, 'active', 'status filter should be bound');
});

register_test('Story 8.1 getList: pagination computes correct LIMIT/OFFSET', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();
    $db->getListUsers = [];
    $db->getListTotal = 0;

    $svc = makeUMS($db, $email);
    $svc->getList([], 3, 25);

    $found = null;
    foreach ($db->queries as $q) {
        if (stripos($q['sql'], 'SELECT u.id, u.username') !== false) {
            $found = $q;
            break;
        }
    }
    assert_not_null($found, 'getList should issue a SELECT');
    assert_true(strpos($found['sql'], 'LIMIT 25 OFFSET 50') !== false,
        'page=3 perPage=25 must produce LIMIT 25 OFFSET 50');
});

register_test('Story 8.1 update: updates allowed user fields and ignores unknown fields', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();

    $db->users = [['id' => 1, 'first_name' => 'Old', 'last_name' => 'Name',
                   'email' => 'old@x.com', 'username' => 'olduser', 'role_id' => 2]];

    $svc = makeUMS($db, $email);
    $svc->update(1, [
        'first_name'  => 'New',
        'last_name'   => 'NewLast',
        'email'       => 'new@x.com',
        'username'    => 'newuser',
        // unknown — must be silently dropped
        'is_admin'    => 1,
        'role'        => 'administrator',
    ]);

    // Verify the UPDATE statement was issued with only allowed fields
    $found = null;
    foreach ($db->queries as $q) {
        if (stripos($q['sql'], 'UPDATE users SET') !== false
            && stripos($q['sql'], 'first_name') !== false
            && stripos($q['sql'], 'role_id') === false
            && stripos($q['sql'], 'status =') === false) {
            $found = $q;
        }
    }
    assert_not_null($found, 'update() should issue an UPDATE users SET against allowed fields');
    assert_true(!array_key_exists('is_admin', $found['params']), 'unknown field is_admin should NOT be bound');
    assert_true(!array_key_exists('role', $found['params']), 'unknown field role should NOT be bound');
    assert_equals($found['params']['email'] ?? null, 'new@x.com', 'allowed email field should be bound');
});

register_test('Story 8.1 update: no-op when no allowed fields supplied', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();
    $db->users = [['id' => 1, 'role_id' => 2]];

    $svc = makeUMS($db, $email);
    $svc->update(1, ['unrelated' => 'x']);

    // No UPDATE users statement should be issued
    foreach ($db->queries as $q) {
        if (stripos($q['sql'], 'UPDATE users SET') !== false
            && stripos($q['sql'], 'role_id') === false) {
            assert_true(false, 'update() should not issue UPDATE when only unknown fields are provided');
        }
    }
});

register_test('Story 8.1 setRole: updates role_id when role is valid', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();

    $db->users = [['id' => 1, 'first_name' => 'Sue', 'email' => 's@x.com', 'role_id' => 2, 'role_id_present' => 1]];
    $db->roles = [
        ['id' => 2, 'name' => 'user'],
        ['id' => 3, 'name' => 'team_owner'],
        ['id' => 4, 'name' => 'administrator'],
    ];

    $svc = makeUMS($db, $email);
    $svc->setRole(1, 'administrator', 99);

    $roleUpdated = false;
    foreach ($db->queries as $q) {
        if (stripos($q['sql'], 'UPDATE users SET role_id') !== false
            && (int) ($q['params']['role_id'] ?? 0) === 4
            && (int) ($q['params']['id'] ?? 0) === 1) {
            $roleUpdated = true;
        }
    }
    assert_true($roleUpdated, 'role_id should be set to administrator role id (4)');
});

register_test('Story 8.1 setRole: throws on invalid role', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();
    $db->users = [['id' => 1, 'role_id' => 2]];
    $db->roles = [['id' => 2, 'name' => 'user']];

    $svc = makeUMS($db, $email);
    $threw = false;
    try {
        $svc->setRole(1, 'super_admin', 99);
    } catch (InvalidArgumentException $e) {
        $threw = true;
    }
    assert_true($threw, 'setRole should throw InvalidArgumentException on unknown role');
});

register_test('Story 8.1 disable: sets status=disabled and session_invalidated_at', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();
    $db->users = [['id' => 1, 'status' => 'active']];

    $svc = makeUMS($db, $email);
    $svc->disable(1, 99);

    $found = false;
    foreach ($db->queries as $q) {
        if (stripos($q['sql'], "UPDATE users SET status = 'disabled'") !== false
            && stripos($q['sql'], 'session_invalidated_at = NOW()') !== false
            && (int) ($q['params']['id'] ?? 0) === 1) {
            $found = true;
        }
    }
    assert_true($found, "disable() must UPDATE status='disabled' and session_invalidated_at=NOW()");
    assert_equals($db->users[0]['status'], 'disabled', 'mock user status should now be disabled');
});

register_test('Story 8.1 enable: sets status=active', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();
    $db->users = [['id' => 1, 'status' => 'disabled']];

    $svc = makeUMS($db, $email);
    $svc->enable(1, 99);

    $found = false;
    foreach ($db->queries as $q) {
        if (stripos($q['sql'], "UPDATE users SET status = 'active'") !== false
            && (int) ($q['params']['id'] ?? 0) === 1) {
            $found = true;
        }
    }
    assert_true($found, "enable() must UPDATE users SET status='active'");
    assert_equals($db->users[0]['status'], 'active', 'mock user status should now be active');
});

register_test('Story 8.1 delete: cascades team_owners then users', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();
    $db->users      = [['id' => 1, 'role_id' => 3], ['id' => 2, 'role_id' => 2]];
    $db->teamOwners = [
        ['user_id' => 1, 'team_id' => 10, 'assigned_by' => 99],
        ['user_id' => 1, 'team_id' => 11, 'assigned_by' => 99],
        ['user_id' => 2, 'team_id' => 12, 'assigned_by' => 99],
    ];

    $svc = makeUMS($db, $email);
    $svc->delete(1, 99);

    // Verify both DELETE statements issued in correct order
    $teamOwnerDeleteIdx = -1;
    $userDeleteIdx = -1;
    foreach ($db->queries as $i => $q) {
        if (stripos($q['sql'], 'DELETE FROM team_owners') !== false
            && (int) ($q['params']['user_id'] ?? 0) === 1) {
            $teamOwnerDeleteIdx = $i;
        }
        if (stripos($q['sql'], 'DELETE FROM users') !== false
            && (int) ($q['params']['id'] ?? 0) === 1) {
            $userDeleteIdx = $i;
        }
    }
    assert_true($teamOwnerDeleteIdx >= 0, 'team_owners DELETE must be issued for user 1');
    assert_true($userDeleteIdx >= 0, 'users DELETE must be issued for user 1');
    assert_true($teamOwnerDeleteIdx < $userDeleteIdx, 'team_owners must be deleted BEFORE users (FK cascade order)');

    // Verify state in mock
    $remainingUserIds = array_map(fn($u) => $u['id'], $db->users);
    assert_true(!in_array(1, $remainingUserIds, true), 'user 1 should be removed from users');
    assert_true(in_array(2, $remainingUserIds, true), 'user 2 should remain');

    foreach ($db->teamOwners as $to) {
        assert_true((int) $to['user_id'] !== 1, 'no team_owners rows for user 1 should remain');
    }
});

// ---------------------------------------------------------------------------
// Story 8.2 — Admin User List Page service coverage
// ---------------------------------------------------------------------------

register_test('Story 8.2 getList: returns preferred_name for name display', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();

    $db->getListUsers = [
        ['id' => 1, 'username' => 'coach1', 'email' => 'c@x.com', 'first_name' => 'Robert',
         'last_name' => 'Smith', 'preferred_name' => 'Bob', 'status' => 'active',
         'created_at' => '2026-01-01', 'role_name' => 'team_owner'],
    ];
    $db->getListTotal = 1;

    $svc    = makeUMS($db, $email);
    $result = $svc->getList([], 1, 25);

    assert_equals(count($result['users']), 1, 'getList should return 1 user');
    assert_equals($result['users'][0]['preferred_name'] ?? null, 'Bob',
        'getList result should include preferred_name');
});

register_test('Story 8.2 getList: search term binds :search1–:search4 in data SELECT', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();
    $db->getListUsers = [];
    $db->getListTotal = 0;

    $svc = makeUMS($db, $email);
    $svc->getList(['search' => 'jones'], 1, 25);

    // The data SELECT query goes through query() and is recorded in db->queries
    $dataQuery = null;
    foreach ($db->queries as $q) {
        if (stripos($q['sql'], 'SELECT u.id, u.username') !== false) {
            $dataQuery = $q;
            break;
        }
    }
    assert_not_null($dataQuery, 'getList should issue a data SELECT query');
    $allBound = isset($dataQuery['params']['search1'])
             && isset($dataQuery['params']['search2'])
             && isset($dataQuery['params']['search3'])
             && isset($dataQuery['params']['search4']);
    assert_true($allBound, 'search should bind :search1 :search2 :search3 :search4 in data SELECT');
    assert_true(strpos($dataQuery['params']['search1'], 'jones') !== false,
        'search term should be wrapped in LIKE wildcards');
});

register_test('Story 8.2 getList: empty filters produce no bound params in data SELECT', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();
    $db->getListUsers = [];
    $db->getListTotal = 0;

    $svc = makeUMS($db, $email);
    $svc->getList([], 1, 25);

    $dataQuery = null;
    foreach ($db->queries as $q) {
        if (stripos($q['sql'], 'SELECT u.id, u.username') !== false) {
            $dataQuery = $q;
            break;
        }
    }
    assert_not_null($dataQuery, 'getList should issue a data SELECT query');
    assert_true(empty($dataQuery['params']), 'no filter params should be bound when filters array is empty');
    assert_true(strpos($dataQuery['sql'], 'WHERE') === false,
        'data SELECT should have no WHERE clause when no filters provided');
});

// ---------------------------------------------------------------------------
// Story 8.3 — Admin User Detail Page service coverage
// ---------------------------------------------------------------------------

register_test('Story 8.3 update: throws InvalidArgumentException on invalid email', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();
    $db->users = [['id' => 1, 'email' => 'old@x.com', 'username' => 'joe', 'role_id' => 2]];

    $svc   = makeUMS($db, $email);
    $threw = false;
    try {
        $svc->update(1, ['email' => 'not-an-email']);
    } catch (InvalidArgumentException $e) {
        $threw = true;
    }
    assert_true($threw, 'update() should throw InvalidArgumentException for invalid email format');
});

register_test('Story 8.3 update: throws InvalidArgumentException on empty username', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();
    $db->users = [['id' => 1, 'email' => 'j@x.com', 'username' => 'joe', 'role_id' => 2]];

    $svc   = makeUMS($db, $email);
    $threw = false;
    try {
        $svc->update(1, ['username' => '  ']);
    } catch (InvalidArgumentException $e) {
        $threw = true;
    }
    assert_true($threw, 'update() should throw InvalidArgumentException for blank username');
});

register_test('Story 8.3 update: throws InvalidArgumentException on empty first_name', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();
    $db->users = [['id' => 1, 'role_id' => 2]];

    $svc   = makeUMS($db, $email);
    $threw = false;
    try {
        $svc->update(1, ['first_name' => '']);
    } catch (InvalidArgumentException $e) {
        $threw = true;
    }
    assert_true($threw, 'update() should throw InvalidArgumentException when first_name is blank');
});

register_test('Story 8.3 update: preferred_name is included in allowed fields', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();
    $db->users = [['id' => 1, 'first_name' => 'Joe', 'last_name' => 'Smith',
                   'preferred_name' => null, 'email' => 'j@x.com', 'username' => 'joe', 'role_id' => 2]];

    $svc = makeUMS($db, $email);
    $svc->update(1, ['preferred_name' => 'Joey']);

    $found = null;
    foreach ($db->queries as $q) {
        if (stripos($q['sql'], 'UPDATE users SET') !== false
            && stripos($q['sql'], 'preferred_name') !== false) {
            $found = $q;
        }
    }
    assert_not_null($found, 'update() should include preferred_name in UPDATE when supplied');
    assert_equals($found['params']['preferred_name'] ?? null, 'Joey',
        'preferred_name should be bound as Joey');
});

register_test('Story 8.3 disable: prevents login by setting session_invalidated_at', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();
    $db->users = [['id' => 5, 'status' => 'active']];

    $svc = makeUMS($db, $email);
    $svc->disable(5, 1);

    $found = false;
    foreach ($db->queries as $q) {
        if (stripos($q['sql'], "status = 'disabled'") !== false
            && stripos($q['sql'], 'session_invalidated_at') !== false
            && (int) ($q['params']['id'] ?? 0) === 5) {
            $found = true;
        }
    }
    assert_true($found, 'disable() must set status=disabled AND session_invalidated_at to force logout');
});

register_test('Story 8.3 setRole: throws on invalid role string', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();
    $db->users = [['id' => 1, 'role_id' => 2]];
    $db->roles = [['id' => 2, 'name' => 'user']];

    $svc = makeUMS($db, $email);
    $threw = false;
    try {
        $svc->setRole(1, 'moderator', 99);
    } catch (InvalidArgumentException $e) {
        $threw = true;
    }
    assert_true($threw, 'setRole should reject unknown role "moderator" with InvalidArgumentException');
});

register_test('Story 8.3 resetPassword: bcrypt hash stored must verify against returned plaintext', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();
    $db->users = [['id' => 7, 'password_hash' => 'old_hash', 'force_password_change' => 0]];

    $svc  = makeUMS($db, $email);
    $temp = $svc->resetPassword(7, 99);

    $found = null;
    foreach ($db->queries as $q) {
        if (stripos($q['sql'], 'UPDATE users SET password_hash') !== false
            && (int) ($q['params']['id'] ?? 0) === 7) {
            $found = $q;
            break;
        }
    }
    assert_not_null($found, 'resetPassword must UPDATE users.password_hash');
    assert_true(password_verify($temp, $found['params']['hash'] ?? ''),
        'bcrypt hash in UPDATE must verify against the returned temp password string');
});

register_test('Story 8.3 delete: user 2 unaffected when user 1 is deleted', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();
    $db->users = [
        ['id' => 1, 'role_id' => 2],
        ['id' => 2, 'role_id' => 2],
    ];
    $db->teamOwners = [];

    $svc = makeUMS($db, $email);
    $svc->delete(1, 99);

    $ids = array_map(fn($u) => (int) $u['id'], $db->users);
    assert_true(!in_array(1, $ids, true), 'user 1 should be gone');
    assert_true(in_array(2, $ids, true),  'user 2 should be untouched');
});

register_test('Story 8.1 resetPassword: returns 12-char temp password and sets force_password_change=1', function () {
    $db    = new UMSMockDatabase();
    $email = new UMSMockEmail();
    $db->users = [['id' => 1, 'password_hash' => 'old', 'force_password_change' => 0]];

    $svc = makeUMS($db, $email);
    $temp = $svc->resetPassword(1, 99);

    assert_true(is_string($temp), 'resetPassword should return a string');
    assert_equals(strlen($temp), 12, 'temp password should be 12 characters');

    // The hash UPDATE must be issued with force_password_change = 1 in the SQL text
    $found = null;
    foreach ($db->queries as $q) {
        if (stripos($q['sql'], 'UPDATE users SET password_hash') !== false
            && (int) ($q['params']['id'] ?? 0) === 1) {
            $found = $q;
        }
    }
    assert_not_null($found, 'resetPassword must UPDATE users SET password_hash');
    assert_true(strpos($found['sql'], 'force_password_change = 1') !== false,
        'UPDATE must set force_password_change = 1');

    // Verify the stored hash actually verifies against the returned temp password
    assert_true(password_verify($temp, $found['params']['hash'] ?? ''),
        'stored bcrypt hash must verify against returned plaintext temp password');

    // Mock state assertion
    assert_equals((int) $db->users[0]['force_password_change'], 1,
        'force_password_change flag must be set in stored user row');
});
