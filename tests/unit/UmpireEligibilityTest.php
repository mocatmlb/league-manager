<?php
/**
 * Unit Tests: Story 23.7 - Umpire Program Eligibility Filtering
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ActivityLogger.php';
require_once __DIR__ . '/../../includes/UmpireRosterService.php';
require_once __DIR__ . '/../../includes/UmpireAssignmentService.php';

// ---------------------------------------------------------------------------
// Stubs for global helpers
// ---------------------------------------------------------------------------

if (!function_exists('getSetting')) {
    function getSetting(string $key, string $default = '') {
        return $GLOBALS['_test_settings'][$key] ?? $default;
    }
}

// Re-use Mock classes if not defined (though they are simple enough to redefine or use from helper if we had one)
if (!class_exists('UmpireRosterMockDb')) {
    class UmpireRosterMockDb extends Database {
        public array $lastSql = [];
        public array $lastParams = [];
        public array $queryRows = [];
        public array $fetchOneRows = [];
        public int $rowCount = 0;

        public function __construct() {
            // No-op to avoid private constructor error
        }

        public function query($sql, $params = []) {
            $this->lastSql[] = $sql;
            $this->lastParams[] = $params;
            $rows = array_shift($this->queryRows) ?: [];
            return new UmpireRosterMockStmt($rows);
        }

        public function fetchOne($sql, $params = []) {
            $this->lastSql[] = $sql;
            $this->lastParams[] = $params;
            return array_shift($this->fetchOneRows) ?: false;
        }

        public function beginTransaction() {}
        public function commit() {}
        public function rollback() {}
        public function getConnection() { return $this; }
        public function inTransaction() { return false; }
        public function lastInsertId($name = null) { return "101"; }
        public function rowCount() { return $this->rowCount; }
    }

    class UmpireRosterMockStmt {
        private array $rows;
        public function __construct(array $rows) { $this->rows = $rows; }
        public function fetchAll($mode = null): array { return $this->rows; }
    }
}

// ---------------------------------------------------------------------------
// Roster Service Tests
// ---------------------------------------------------------------------------

register_test('23.7 UmpireRosterService::saveProgramEligibility persists and logs', function () {
    $mock = new UmpireRosterMockDb();
    Database::setInstance($mock);
    $svc = new UmpireRosterService();

    // Mock active programs for validation
    $mock->queryRows = [
        [['program_id' => 1, 'program_name' => 'Baseball']], // getActivePrograms validation in updateProfile (if it calls it)
        [['program_id' => 1, 'program_name' => 'Baseball']], // getActivePrograms validation in saveProgramEligibility
        [], // INSERT INTO umpire_profiles (updateProfile calls this)
        [], // DELETE FROM umpire_program_eligibility
        [], // INSERT INTO umpire_program_eligibility
        [], // ActivityLogger INSERT
    ];
    $mock->fetchOneRows = [
        ['id' => 1], // role_id lookup
    ];

    // Using Reflection to test private method or just test through public updateProfile
    $svc->updateProfile(101, [
        'all_programs' => false,
        'program_ids' => [1]
    ], 1);

    $sqlLog = implode("\n", $mock->lastSql);
    assert_true(strpos($sqlLog, 'DELETE FROM umpire_program_eligibility WHERE umpire_user_id = :uid') !== false, 'Expected DELETE of old rows');
    assert_true(strpos($sqlLog, 'INSERT INTO umpire_program_eligibility') !== false, 'Expected INSERT of new rows');

    $found = false;
    foreach ($mock->lastParams as $params) {
        if (isset($params['event']) && $params['event'] === 'umpire.program_eligibility_updated') {
            $context = json_decode($params['context'], true);
            assert_equals($context['all_programs'], 0, 'Expected all_programs=0 in log');
            assert_equals($context['program_ids'], [1], 'Expected program_ids=[1] in log');
            $found = true;
        }
    }
    assert_true($found, 'Expected audit log entry in SQL log');
});

register_test('23.7 UmpireRosterService::syncProgramEligibility backfills only missing rows', function () {
    $mock = new UmpireRosterMockDb();
    Database::setInstance($mock);
    $svc = new UmpireRosterService();

    $mock->queryRows = [
        [['program_id' => 1, 'program_name' => 'Baseball']], // getActivePrograms
        [['umpire_user_id' => 101]], // selectedModeUmpireIds
    ];
    $mock->rowCount = 1;

    $count = $svc->syncProgramEligibility([1], 1);
    assert_equals($count, 1, 'Expected 1 row inserted');

    $sqlLog = implode("\n", $mock->lastSql);
    assert_true(strpos($sqlLog, 'INSERT IGNORE INTO umpire_program_eligibility') !== false, 'Expected INSERT IGNORE for sync');
});

// ---------------------------------------------------------------------------
// Assignment Service Filtering Tests
// ---------------------------------------------------------------------------

register_test('23.7 UmpireAssignmentService filters drawer roster by program', function () {
    $mock = new UmpireRosterMockDb();
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();

    $mock->fetchOneRows = [
        ['program_id' => 1], // resolveGameProgram
        ['game_id' => 10, 'season_id' => 1, 'assignment_status' => 'Draft'], // fetchGame
        ['id' => 1], // role_id lookup for getRoster
    ];
    
    $mock->queryRows = [
        [['program_id' => 1, 'program_name' => 'Baseball']], // getActivePrograms validation
        [], // resolveGameProgram SQL
        [], // fetchGame SQL
        ['id' => 1], // role_id SQL for getRoster
        // getRoster main SQL rows
        [
            ['id' => 201, 'first_name' => 'Eligible', 'last_name' => 'Ump', 'umpire_level' => 'Blue Shirt', 'is_under_18' => 0, 'all_programs' => true, 'program_ids' => []],
            ['id' => 202, 'first_name' => 'Ineligible', 'last_name' => 'Ump', 'umpire_level' => 'Blue Shirt', 'is_under_18' => 0, 'all_programs' => false, 'program_ids' => [2]],
            ['id' => 203, 'first_name' => 'Specific', 'last_name' => 'Ump', 'umpire_level' => 'Blue Shirt', 'is_under_18' => 0, 'all_programs' => false, 'program_ids' => [1]],
        ],
        [['umpire_user_id' => 201, 'program_id' => 1]], // eligibility fetch for roster
        [], // fetchCurrentGameLoads (fetchCurrentGameLoads calls query once)
    ];

    $drawer = $svc->getGameAssignmentDrawer(10);
    $rosterIds = array_column($drawer['roster'], 'id');
    
    assert_true(in_array(201, $rosterIds), 'All-programs umpire should be included');
    assert_true(!in_array(202, $rosterIds), 'Ineligible program umpire should be excluded');
    assert_true(in_array(203, $rosterIds), 'Specifically eligible umpire should be included');
});

register_test('23.7 UmpireAssignmentService::saveSlot rejects ineligible umpire', function () {
    $mock = new UmpireRosterMockDb();
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();

    $mock->fetchOneRows = [
        ['program_id' => 2], // resolveGameProgram
        ['game_id' => 10, 'season_id' => 5, 'assignment_status' => 'Draft'], // fetchGame
        ['id' => 1], // role_id lookup for getUmpire
        ['id' => 202, 'umpire_level' => 'Blue Shirt', 'is_under_18' => 0, 'all_programs' => false, 'program_ids' => [1]], // fetchOne for getUmpire
        ['id' => 202, 'status' => 'active', 'umpire_level' => 'Blue Shirt', 'is_under_18' => 0], // fetchActiveUmpire
        false, // fetchSlot (existing)
    ];
    $mock->queryRows = [
        [['program_id' => 1, 'program_name' => 'Baseball']], // getActivePrograms validation
        [], // resolveGameProgram SQL
        [], // fetchGame SQL
        ['id' => 1], // role_id SQL for getUmpire
        [], // getUmpire fetch SQL
        [['program_id' => 1]], // eligibility data for getUmpire fetchAll
        [], // fetchActiveUmpire SQL
        [], // fetchSlot SQL
    ];

    $threw = false;
    try {
        $svc->saveSlot(10, 0, 202, 1);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
        assert_equals($e->getMessage(), 'Umpire is not eligible for this game\'s program.', 'Expected specific error message');
    }
    assert_true($threw, 'Expected ineligible save to throw');
});
