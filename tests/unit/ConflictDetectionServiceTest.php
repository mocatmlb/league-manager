<?php
/**
 * Unit Tests: ConflictDetectionService
 *
 * Story 20.1 — Conflict Detection Service & Admin Game Badges
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ConflictDetectionService.php';

// ---------------------------------------------------------------------------
// Mock database
// ---------------------------------------------------------------------------

class CDSMockDatabase extends Database {
    /** @var array[]  Rows to return for team conflict query */
    public array $teamConflictRows = [];
    /** @var array[]  Rows to return for location conflict query */
    public array $locationConflictRows = [];
    /** @var array[]  Captured fetchAll calls */
    public array $fetchAllCalls = [];

    public function __construct() {
        // Bypass real PDO connection
    }

    public function fetchAll($sql, $params = []): array {
        $this->fetchAllCalls[] = ['sql' => $sql, 'params' => $params];
        // Distinguish between team and location bulk queries
        if (stripos($sql, 'conflict_team_name') !== false) {
            return $this->teamConflictRows;
        }
        if (stripos($sql, 'location_name') !== false) {
            return $this->locationConflictRows;
        }
        return [];
    }

    // Unused by this service, but required to satisfy any base-class abstract methods
    public function fetchOne($sql, $params = []) { return null; }
    public function query($sql, $params = []) { return null; }
    public function insert($sql, $params = []) { return 0; }
}

// ---------------------------------------------------------------------------
// Helper to build a minimal game row
// ---------------------------------------------------------------------------

function makeGame(int $id, string $status = 'Scheduled', ?string $time = '10:00:00', ?string $locationId = '5'): array {
    return [
        'game_id'      => $id,
        'game_date'    => '2026-06-15',
        'game_time'    => $time,
        'location_id'  => $locationId,
        'location'     => 'Test Field',
        'home_team_id' => 10,
        'away_team_id' => 11,
        'game_status'  => $status,
    ];
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

register_test('getGameConflicts: returns empty array for empty input', function () {
    $db = new CDSMockDatabase();
    $svc = new ConflictDetectionService($db);
    $result = $svc->getGameConflicts([]);
    assert_equals($result, [], 'Expected empty array for no games');
    assert_equals(count($db->fetchAllCalls), 0, 'Expected no DB queries for empty input');
});

register_test('getGameConflicts: returns empty conflicts when DB returns no rows', function () {
    $db = new CDSMockDatabase();
    $svc = new ConflictDetectionService($db);
    $games = [makeGame(1), makeGame(2)];
    $result = $svc->getGameConflicts($games);
    assert_equals($result[1], [], 'Game 1 should have no conflicts');
    assert_equals($result[2], [], 'Game 2 should have no conflicts');
    assert_equals(count($db->fetchAllCalls), 2, 'Expected two bulk queries (team + location)');
});

register_test('getGameConflicts: detects team conflict', function () {
    $db = new CDSMockDatabase();
    $db->teamConflictRows = [
        [
            'source_game_id'    => 1,
            'conflict_game_id'  => 2,
            'conflict_date'     => '2026-06-15',
            'conflict_time'     => '11:00:00',
            'conflict_team_name' => 'Rockets',
        ],
    ];
    $svc = new ConflictDetectionService($db);
    $games = [makeGame(1), makeGame(2)];
    $result = $svc->getGameConflicts($games);

    assert_equals(count($result[1]), 1, 'Game 1 should have one conflict');
    assert_equals($result[1][0]['type'], 'team', 'Conflict type should be team');
    assert_true(str_contains($result[1][0]['message'], 'Rockets'), 'Message should contain team name');
    assert_equals($result[2], [], 'Game 2 should have no conflicts');
});

register_test('getGameConflicts: detects location conflict', function () {
    $db = new CDSMockDatabase();
    $db->locationConflictRows = [
        [
            'source_game_id'   => 3,
            'conflict_game_id' => 4,
            'conflict_date'    => '2026-06-15',
            'conflict_time'    => '09:30:00',
            'location_name'    => 'Main Park Field',
        ],
    ];
    $svc = new ConflictDetectionService($db);
    $games = [makeGame(3), makeGame(4)];
    $result = $svc->getGameConflicts($games);

    assert_equals(count($result[3]), 1, 'Game 3 should have one conflict');
    assert_equals($result[3][0]['type'], 'location', 'Conflict type should be location');
    assert_true(str_contains($result[3][0]['message'], 'Main Park Field'), 'Message should contain location name');
    assert_equals($result[4], [], 'Game 4 should have no conflicts');
});

register_test('getGameConflicts: excludes Cancelled and Postponed games', function () {
    $db = new CDSMockDatabase();
    $svc = new ConflictDetectionService($db);

    $games = [
        makeGame(10, 'Cancelled'),
        makeGame(11, 'Postponed'),
        makeGame(12, 'Scheduled'),
    ];
    $result = $svc->getGameConflicts($games);

    // Only game 12 is active — DB queries fire but return nothing
    assert_true(!array_key_exists(10, $result), 'Cancelled game should not appear in conflicts map');
    assert_true(!array_key_exists(11, $result), 'Postponed game should not appear in conflicts map');
    assert_true(array_key_exists(12, $result), 'Scheduled game should appear in conflicts map');
});

register_test('getGameConflicts: returns empty when all games are Cancelled or Postponed', function () {
    $db = new CDSMockDatabase();
    $svc = new ConflictDetectionService($db);
    $games = [makeGame(20, 'Cancelled'), makeGame(21, 'Postponed')];
    $result = $svc->getGameConflicts($games);
    assert_equals($result, [], 'All cancelled/postponed should produce empty result');
    assert_equals(count($db->fetchAllCalls), 0, 'No DB queries when all games are excluded');
});

register_test('getGameConflicts: NULL game time flagged as conflict (message shows (Time TBD))', function () {
    $db = new CDSMockDatabase();
    $db->teamConflictRows = [
        [
            'source_game_id'     => 30,
            'conflict_game_id'   => 31,
            'conflict_date'      => '2026-06-15',
            'conflict_time'      => null,  // NULL time → (Time TBD) label
            'conflict_team_name' => 'Eagles',
        ],
    ];
    $svc = new ConflictDetectionService($db);
    $games = [makeGame(30, 'Scheduled', null), makeGame(31)];  // source game also has NULL time
    $result = $svc->getGameConflicts($games);

    assert_equals(count($result[30]), 1, 'Game 30 should have a conflict entry');
    assert_true(str_contains($result[30][0]['message'], '(Time TBD)'), 'NULL conflict_time should produce (Time TBD) in message');
});

register_test('checkScrConflicts: stub returns empty array', function () {
    $db = new CDSMockDatabase();
    $svc = new ConflictDetectionService($db);
    $result = $svc->checkScrConflicts('2026-06-15', '10:00', 'Some Field', 1, 2);
    assert_equals($result, [], 'Stub should return empty array');
});

register_test('getGameConflicts: honors custom conflict window', function () {
    $db = new CDSMockDatabase();
    $svc = new ConflictDetectionService($db, 3600); // 1 hour window
    $games = [makeGame(1)];
    $svc->getGameConflicts($games);
    
    $teamSql = $db->fetchAllCalls[0]['sql'];
    assert_true(str_contains($teamSql, '<= 3600'), 'SQL should contain the custom 1 hour window');
});

register_test('getGameConflicts: performance guard blocks bulk queries for > 500 games', function () {
    $db = new CDSMockDatabase();
    $svc = new ConflictDetectionService($db);
    
    $games = [];
    for ($i = 1; $i <= 501; $i++) {
        $games[] = makeGame($i);
    }
    
    $result = $svc->getGameConflicts($games);
    assert_equals($result, [], 'Expected empty result for huge game set');
    assert_equals(count($db->fetchAllCalls), 0, 'No DB queries should be made for > 500 games');
});
