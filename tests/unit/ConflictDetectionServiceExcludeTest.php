<?php
/**
 * Unit Tests: ConflictDetectionService — $excludeGameId parameter
 *
 * Story 20.3 — Admin Schedule Change Conflict Prompt
 * Tests the optional 6th parameter added to checkScrConflicts() that
 * prevents self-conflict false positives on same-day reschedules.
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ConflictDetectionService.php';

// ---------------------------------------------------------------------------
// Mock database (captures SQL/params for inspection)
// ---------------------------------------------------------------------------

class CDS203MockDatabase extends Database {
    public array $teamConflictRows    = [];
    public array $locationConflictRows = [];
    public array $fetchAllCalls       = [];
    public ?array $locationRow        = null;

    public function __construct() {
        // Bypass real PDO connection
    }

    public function fetchAll($sql, $params = []): array {
        $this->fetchAllCalls[] = ['sql' => $sql, 'params' => $params];
        if (stripos($sql, 'conflict_team_name') !== false) {
            return $this->teamConflictRows;
        }
        if (stripos($sql, 'location_name') !== false) {
            return $this->locationConflictRows;
        }
        return [];
    }

    public function fetchOne($sql, $params = []) {
        if ($this->locationRow !== null && stripos($sql, 'location_name') !== false) {
            return $this->locationRow;
        }
        return null;
    }
    public function query($sql, $params = []) { return null; }
    public function insert($sql, $params = []) { return 0; }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

register_test('checkScrConflicts: excludeGameId=null behaves same as before (no exclusion clause)', function () {
    $db = new CDS203MockDatabase();
    $db->teamConflictRows = [
        ['conflict_time' => '10:00:00', 'conflict_date' => '2026-07-01', 'conflict_team_name' => 'Eagles'],
    ];
    $svc = new ConflictDetectionService($db);
    $result = $svc->checkScrConflicts('2026-07-01', '10:00', '', 1, 2, null);

    assert_equals(count($result), 1, 'Should still return warning when excludeGameId is null');

    // Verify no AND g.game_id != ? clause was appended
    $teamCall = null;
    foreach ($db->fetchAllCalls as $call) {
        if (stripos($call['sql'], 'conflict_team_name') !== false) {
            $teamCall = $call;
        }
    }
    assert_true($teamCall !== null, 'Team query should have been called');
    assert_true(
        !str_contains($teamCall['sql'], 'game_id != ?'),
        'No exclusion clause should appear when excludeGameId is null'
    );
    assert_equals(count($teamCall['params']), 6, 'Team query should have 6 params when no exclusion');
});

register_test('checkScrConflicts: excludeGameId appended to team conflict query params', function () {
    $db = new CDS203MockDatabase();
    // DB returns no rows — we only care that the exclusion param was passed
    $svc = new ConflictDetectionService($db);
    $svc->checkScrConflicts('2026-07-01', '10:00', '', 5, 6, 99);

    $teamCall = null;
    foreach ($db->fetchAllCalls as $call) {
        if (stripos($call['sql'], 'conflict_team_name') !== false) {
            $teamCall = $call;
        }
    }
    assert_true($teamCall !== null, 'Team query should have been called');
    assert_equals(count($teamCall['params']), 7, 'Team query should have 7 params when excludeGameId is set');
    assert_equals($teamCall['params'][6], 99, 'Last param should be the excludeGameId value');
});

register_test('checkScrConflicts: excludeGameId appended to location conflict query params', function () {
    $db = new CDS203MockDatabase();
    $db->locationRow = ['location_id' => 3];
    $svc = new ConflictDetectionService($db);
    $svc->checkScrConflicts('2026-07-01', '10:00', 'Riverside Park', 5, 6, 42);

    $locCall = null;
    foreach ($db->fetchAllCalls as $call) {
        if (stripos($call['sql'], 'location_name') !== false) {
            $locCall = $call;
        }
    }
    assert_true($locCall !== null, 'Location query should have been called');
    assert_equals(count($locCall['params']), 6, 'Location query should have 6 params when excludeGameId is set');
    assert_equals($locCall['params'][5], 42, 'Last param should be the excludeGameId value');
});

register_test('checkScrConflicts: excluded game_id suppresses its own conflict row', function () {
    $db = new CDS203MockDatabase();
    // Simulate the DB honoring the WHERE g.game_id != ? clause by returning no rows
    // (the mock simply returns whatever we put in teamConflictRows; we set it empty to
    // mirror what the real DB does after the exclusion filters the self-row out)
    $svc = new ConflictDetectionService($db);
    $result = $svc->checkScrConflicts('2026-07-01', '10:00', '', 5, 6, 5);

    assert_equals($result, [], 'No conflicts should be returned when DB returns no rows (exclusion active)');
});

register_test('checkScrConflicts: excludeGameId does not suppress conflicts with OTHER games', function () {
    $db = new CDS203MockDatabase();
    // The DB still returns a row for a *different* game (game_id 12), not the excluded one
    $db->teamConflictRows = [
        ['conflict_time' => '10:00:00', 'conflict_date' => '2026-07-01', 'conflict_team_name' => 'Rockets'],
    ];
    $svc = new ConflictDetectionService($db);
    // Excluding game 99 — Rockets row is for a different game
    $result = $svc->checkScrConflicts('2026-07-01', '10:00', '', 5, 6, 99);

    assert_equals(count($result), 1, 'Conflict with a different game should still be returned');
    assert_true(str_contains($result[0], 'Rockets'), 'Warning should name the conflicting team');
});

register_test('checkScrConflicts: excludeGameId=0 is treated as non-null (appends exclusion)', function () {
    $db = new CDS203MockDatabase();
    $svc = new ConflictDetectionService($db);
    // game_id 0 is technically non-null; the clause should be appended
    // (in practice game IDs start at 1, but the method should not special-case 0)
    $svc->checkScrConflicts('2026-07-01', '10:00', '', 5, 6, 0);

    $teamCall = null;
    foreach ($db->fetchAllCalls as $call) {
        if (stripos($call['sql'], 'conflict_team_name') !== false) {
            $teamCall = $call;
        }
    }
    // excludeGameId = 0 is non-null, so param count jumps to 7
    assert_equals(count($teamCall['params']), 7, 'excludeGameId=0 is non-null and should add the 7th param');
    assert_equals((int)$teamCall['params'][6], 0, 'The appended param should be 0');
});

register_test('checkScrConflicts: both team and location exclusions applied when excludeGameId set', function () {
    $db = new CDS203MockDatabase();
    $db->locationRow = ['location_id' => 8];
    $svc = new ConflictDetectionService($db);
    $svc->checkScrConflicts('2026-07-01', '14:00', 'North Field', 3, 4, 77);

    $teamParams = null;
    $locParams  = null;
    foreach ($db->fetchAllCalls as $call) {
        if (stripos($call['sql'], 'conflict_team_name') !== false) {
            $teamParams = $call['params'];
        }
        if (stripos($call['sql'], 'location_name') !== false) {
            $locParams = $call['params'];
        }
    }

    assert_true($teamParams !== null, 'Team query must have been called');
    assert_true($locParams  !== null, 'Location query must have been called');
    assert_equals($teamParams[6], 77, 'Team query last param should be excludeGameId=77');
    assert_equals($locParams[5],  77, 'Location query last param should be excludeGameId=77');
});
