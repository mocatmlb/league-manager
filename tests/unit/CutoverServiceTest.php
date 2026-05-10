<?php
/**
 * Unit Tests: CutoverService
 *
 * Story 9.1 — CutoverService Backend
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ActivityLogger.php';
require_once __DIR__ . '/../../includes/CutoverService.php';

// ---------------------------------------------------------------------------
// Mock infrastructure
// ---------------------------------------------------------------------------

class CSMockDatabase extends Database {
    public array $teams       = [];
    public array $owners      = [];
    public array $settings    = [];
    public array $queries     = [];

    public function beginTransaction() { /* Mock */ }
    public function commit() { /* Mock */ }
    public function rollBack() { /* Mock */ }

    public function fetchAll($sql, $params = []) {
        $sql = trim($sql);

        // Teams query (getGapChecklist first fetch)
        if (stripos($sql, "FROM teams t") !== false
            && stripos($sql, "season_status = 'Active'") !== false
            && stripos($sql, "team_owners") === false
        ) {
            return $this->teams;
        }

        // Owners query (getGapChecklist second fetch — IN clause)
        if (stripos($sql, "FROM team_owners o") !== false
            && stripos($sql, "INNER JOIN users u") !== false
        ) {
            // Return owners whose team_id is in params (positional array of team IDs)
            $teamIds = is_array($params) ? array_map('intval', $params) : [];
            $result = [];
            foreach ($this->owners as $o) {
                if (in_array((int) $o['team_id'], $teamIds, true)) {
                    $result[] = $o;
                }
            }
            return $result;
        }

        return [];
    }

    public function fetchOne($sql, $params = []) {
        $sql = trim($sql);

        // Gap count query
        if (stripos($sql, "COUNT(*) AS cnt") !== false
            && stripos($sql, "NOT EXISTS") !== false
        ) {
            // Count teams that have no entry in $this->owners
            $teamsWithOwner = array_unique(array_column($this->owners, 'team_id'));
            $cnt = 0;
            foreach ($this->teams as $t) {
                if (!in_array((int) $t['team_id'], array_map('intval', $teamsWithOwner), true)) {
                    $cnt++;
                }
            }
            return ['cnt' => $cnt];
        }

        // isSharedCredentialActive — settings lookup
        if (stripos($sql, "setting_key = 'coaches_password'") !== false) {
            foreach ($this->settings as $s) {
                if ($s['setting_key'] === 'coaches_password') {
                    return ['setting_value' => $s['setting_value']];
                }
            }
            return false;
        }

        // ActivityLogger INSERT (silently handled via query())
        return false;
    }

    public function query($sql, $params = []) {
        $this->queries[] = ['sql' => trim($sql), 'params' => $params];

        // Simulate UPDATE settings for disableSharedCredential
        if (stripos($sql, "UPDATE settings SET setting_value = NULL") !== false) {
            foreach ($this->settings as &$s) {
                if ($s['setting_key'] === 'coaches_password') {
                    $s['setting_value'] = null;
                }
            }
            unset($s);
        }

        // Return a minimal stmt object (ActivityLogger INSERT, etc.)
        return new class {
            public function rowCount(): int { return 1; }
        };
    }
}

// ---------------------------------------------------------------------------
// Helper: build a team row
// ---------------------------------------------------------------------------

function cs_team(int $id, string $name = 'Team A', string $div = 'Div 1', string $prog = 'Travel'): array {
    return [
        'team_id'      => $id,
        'team_name'    => $name,
        'division_name'=> $div,
        'program_name' => $prog,
    ];
}

function cs_owner(int $teamId, int $userId = 1): array {
    return [
        'team_id'    => $teamId,
        'user_id'    => $userId,
        'first_name' => 'Coach',
        'last_name'  => 'Smith',
        'email'      => 'coach@example.com',
    ];
}

// ---------------------------------------------------------------------------
// Tests: getGapChecklist
// ---------------------------------------------------------------------------

register_test('getGapChecklist — empty when no active-season teams', function () {
    $db = new CSMockDatabase();
    Database::setInstance($db);

    $svc = new CutoverService($db);
    $result = $svc->getGapChecklist();

    assert_equals($result, [], 'should return empty array when no teams');
});

register_test('getGapChecklist — team with owner has has_gap = false', function () {
    $db = new CSMockDatabase();
    $db->teams  = [cs_team(1, 'Red Sox', 'AA', 'Baseball')];
    $db->owners = [cs_owner(1, 10)];
    Database::setInstance($db);

    $svc    = new CutoverService($db);
    $result = $svc->getGapChecklist();

    assert_equals(count($result), 1, 'should return one team');
    assert_equals($result[0]['has_gap'], false, 'team with owner should have has_gap=false');
    assert_equals(count($result[0]['owners']), 1, 'should have one owner');
    assert_equals($result[0]['owners'][0]['user_id'], 10, 'owner user_id should match');
});

register_test('getGapChecklist — team without owner has has_gap = true', function () {
    $db = new CSMockDatabase();
    $db->teams  = [cs_team(2, 'Blue Jays', 'AA', 'Baseball')];
    $db->owners = [];
    Database::setInstance($db);

    $svc    = new CutoverService($db);
    $result = $svc->getGapChecklist();

    assert_equals(count($result), 1, 'should return one team');
    assert_equals($result[0]['has_gap'], true, 'team without owner should have has_gap=true');
    assert_equals($result[0]['owners'], [], 'owners should be empty array');
});

register_test('getGapChecklist — mixed: some have owners, some do not', function () {
    $db = new CSMockDatabase();
    $db->teams = [
        cs_team(1, 'Red Sox', 'AA'),
        cs_team(2, 'Blue Jays', 'AA'),
        cs_team(3, 'Yankees', 'AAA'),
    ];
    $db->owners = [
        cs_owner(1, 10),
        cs_owner(3, 20),
    ];
    Database::setInstance($db);

    $svc    = new CutoverService($db);
    $result = $svc->getGapChecklist();

    assert_equals(count($result), 3, 'should return all three teams');

    $byId = [];
    foreach ($result as $r) { $byId[$r['team_id']] = $r; }

    assert_equals($byId[1]['has_gap'], false, 'team 1 has owner, no gap');
    assert_equals($byId[2]['has_gap'], true, 'team 2 has no owner, gap');
    assert_equals($byId[3]['has_gap'], false, 'team 3 has owner, no gap');
});

register_test('getGapChecklist — team name and metadata are populated', function () {
    $db = new CSMockDatabase();
    $db->teams  = [cs_team(1, 'Cardinals', 'Division B', 'Fall Ball')];
    $db->owners = [cs_owner(1, 5)];
    Database::setInstance($db);

    $svc    = new CutoverService($db);
    $result = $svc->getGapChecklist();

    assert_equals($result[0]['team_name'],     'Cardinals',   'team_name');
    assert_equals($result[0]['division_name'], 'Division B',  'division_name');
    assert_equals($result[0]['program_name'],  'Fall Ball',   'program_name');
});

// ---------------------------------------------------------------------------
// Tests: getGapCount
// ---------------------------------------------------------------------------

register_test('getGapCount — returns 0 when all teams have owners', function () {
    $db = new CSMockDatabase();
    $db->teams  = [cs_team(1), cs_team(2)];
    $db->owners = [cs_owner(1, 10), cs_owner(2, 11)];
    Database::setInstance($db);

    $svc = new CutoverService($db);
    assert_equals($svc->getGapCount(), 0, 'gap count should be 0 when all teams covered');
});

register_test('getGapCount — returns N when N teams have no owner', function () {
    $db = new CSMockDatabase();
    $db->teams  = [cs_team(1), cs_team(2), cs_team(3)];
    $db->owners = [cs_owner(2, 10)]; // only team 2 has an owner
    Database::setInstance($db);

    $svc = new CutoverService($db);
    assert_equals($svc->getGapCount(), 2, 'gap count should be 2 (teams 1 and 3 uncovered)');
});

register_test('getGapCount — returns total team count when no owners exist', function () {
    $db = new CSMockDatabase();
    $db->teams  = [cs_team(1), cs_team(2)];
    $db->owners = [];
    Database::setInstance($db);

    $svc = new CutoverService($db);
    assert_equals($svc->getGapCount(), 2, 'gap count should equal total teams when no owners');
});

// ---------------------------------------------------------------------------
// Tests: disableSharedCredential
// ---------------------------------------------------------------------------

register_test('disableSharedCredential — succeeds and nulls setting when gap count is 0', function () {
    $db = new CSMockDatabase();
    $db->teams    = [cs_team(1)];
    $db->owners   = [cs_owner(1, 10)];
    $db->settings = [['setting_key' => 'coaches_password', 'setting_value' => 'hashed_value']];
    Database::setInstance($db);

    $svc    = new CutoverService($db);
    $result = $svc->disableSharedCredential(99);

    assert_equals($result, true, 'should return true on success');

    // Verify the UPDATE was issued
    $updateFound = false;
    foreach ($db->queries as $q) {
        if (stripos($q['sql'], "UPDATE settings SET setting_value = NULL") !== false) {
            $updateFound = true;
            break;
        }
    }
    assert_true($updateFound, 'UPDATE settings query should have been issued');

    // Verify the setting was nulled in the mock
    $pw = null;
    foreach ($db->settings as $s) {
        if ($s['setting_key'] === 'coaches_password') {
            $pw = $s['setting_value'];
        }
    }
    assert_equals($pw, null, 'coaches_password setting_value should be null after disable');
});

register_test('disableSharedCredential — logs admin.shared_credential_disabled event', function () {
    $db = new CSMockDatabase();
    $db->teams    = [cs_team(1)];
    $db->owners   = [cs_owner(1, 10)];
    $db->settings = [['setting_key' => 'coaches_password', 'setting_value' => 'hashed_value']];
    Database::setInstance($db);

    $svc = new CutoverService($db);
    $svc->disableSharedCredential(42);

    $logFound = false;
    foreach ($db->queries as $q) {
        if (stripos($q['sql'], "INSERT INTO activity_log") !== false) {
            $ctx = json_decode((string) ($q['params']['context'] ?? '{}'), true);
            if (($q['params']['event'] ?? '') === 'admin.shared_credential_disabled'
                && (int) ($ctx['admin_user_id'] ?? 0) === 42
            ) {
                $logFound = true;
                break;
            }
        }
    }
    assert_true($logFound, 'admin.shared_credential_disabled activity log entry should be recorded');
});

register_test('disableSharedCredential — throws CutoverGapsRemainingException when gaps remain', function () {
    $db = new CSMockDatabase();
    $db->teams    = [cs_team(1), cs_team(2)];
    $db->owners   = [cs_owner(1, 10)]; // team 2 has no owner
    $db->settings = [['setting_key' => 'coaches_password', 'setting_value' => 'hashed']];
    Database::setInstance($db);

    $svc       = new CutoverService($db);
    $threw     = false;
    $noUpdate  = true;
    try {
        $svc->disableSharedCredential(99);
    } catch (CutoverGapsRemainingException $e) {
        $threw = true;
        // Ensure the UPDATE was NOT issued
        foreach ($db->queries as $q) {
            if (stripos($q['sql'], "UPDATE settings SET setting_value = NULL") !== false) {
                $noUpdate = false;
            }
        }
    }

    assert_true($threw, 'should throw CutoverGapsRemainingException when gaps remain');
    assert_true($noUpdate, 'UPDATE settings should NOT be issued when exception is thrown');

    if ($threw && isset($e)) {
        $gaps = $e->getGaps();
        assert_equals(count($gaps), 1, 'exception should contain metadata for the 1 gap');
        assert_equals($gaps[0]['team_id'], 2, 'gap team_id should be 2');
    }
});

register_test('disableSharedCredential — throws InvalidArgumentException for invalid adminUserId', function () {
    $db = new CSMockDatabase();
    Database::setInstance($db);
    $svc = new CutoverService($db);

    $threw = false;
    try {
        $svc->disableSharedCredential(0);
    } catch (InvalidArgumentException $e) {
        $threw = true;
    }
    assert_true($threw, 'should throw InvalidArgumentException for ID 0');
});

// ---------------------------------------------------------------------------
// Tests: isSharedCredentialActive
// ---------------------------------------------------------------------------

register_test('isSharedCredentialActive — returns true when setting has a non-empty value', function () {
    $db = new CSMockDatabase();
    $db->settings = [['setting_key' => 'coaches_password', 'setting_value' => '$2y$10$abc...']];
    Database::setInstance($db);

    $svc = new CutoverService($db);
    assert_equals($svc->isSharedCredentialActive(), true, 'should return true when credential is set');
});

register_test('isSharedCredentialActive — returns false when setting is null', function () {
    $db = new CSMockDatabase();
    $db->settings = [['setting_key' => 'coaches_password', 'setting_value' => null]];
    Database::setInstance($db);

    $svc = new CutoverService($db);
    assert_equals($svc->isSharedCredentialActive(), false, 'should return false when credential is null');
});

register_test('isSharedCredentialActive — returns false when setting is empty string', function () {
    $db = new CSMockDatabase();
    $db->settings = [['setting_key' => 'coaches_password', 'setting_value' => '']];
    Database::setInstance($db);

    $svc = new CutoverService($db);
    assert_equals($svc->isSharedCredentialActive(), false, 'should return false when credential is empty string');
});

register_test('isSharedCredentialActive — returns false when setting row does not exist', function () {
    $db = new CSMockDatabase();
    $db->settings = [];
    Database::setInstance($db);

    $svc = new CutoverService($db);
    assert_equals($svc->isSharedCredentialActive(), false, 'should return false when no settings row found');
});
