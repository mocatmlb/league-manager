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
    /** @var array|null  Row returned for location_id lookup (null = not found in locations table) */
    public ?array $locationRow = null;

    public function __construct() {
        // Bypass real PDO connection
    }

    public function fetchAll($sql, $params = []): array {
        $this->fetchAllCalls[] = ['sql' => $sql, 'params' => $params];
        // Distinguish between team and location bulk queries by unique column aliases
        if (stripos($sql, 'conflict_team_name') !== false) {
            return $this->teamConflictRows;
        }
        if (stripos($sql, 'location_name') !== false) {
            return $this->locationConflictRows;
        }
        return [];
    }

    public function fetchOne($sql, $params = []) {
        // Return location row when performing location_id lookup
        if ($this->locationRow !== null && stripos($sql, 'location_name') !== false) {
            return $this->locationRow;
        }
        return null;
    }
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

register_test('checkScrConflicts: no conflicts returns empty array', function () {
    $db = new CDSMockDatabase();
    $svc = new ConflictDetectionService($db);
    $result = $svc->checkScrConflicts('2026-06-15', '10:00', 'Some Field', 1, 2);
    assert_equals($result, [], 'No conflicts should return empty array');
});

register_test('checkScrConflicts: team conflict returns warning with team name', function () {
    $db = new CDSMockDatabase();
    $db->teamConflictRows = [
        [
            'conflict_time'      => '10:00:00',
            'conflict_date'      => '2026-07-20',
            'conflict_team_name' => 'Red Sox',
        ],
    ];
    $svc = new ConflictDetectionService($db);
    $result = $svc->checkScrConflicts('2026-07-20', '10:00', '', 5, 6);

    assert_equals(count($result), 1, 'Should return one warning');
    assert_true(str_contains($result[0], 'Red Sox'), 'Warning should contain team name');
    assert_true(str_contains($result[0], 'Potential Team Conflict'), 'Warning should indicate team conflict type');
});

register_test('checkScrConflicts: location conflict returns warning with location name', function () {
    $db = new CDSMockDatabase();
    $db->locationRow = ['location_id' => 5];
    $db->locationConflictRows = [
        [
            'conflict_time' => '14:00:00',
            'conflict_date' => '2026-07-20',
            'location_name' => 'Riverside Park',
        ],
    ];
    $svc = new ConflictDetectionService($db);
    $result = $svc->checkScrConflicts('2026-07-20', '14:00', 'Riverside Park', 5, 6);

    assert_equals(count($result), 1, 'Should return one warning');
    assert_true(str_contains($result[0], 'Riverside Park'), 'Warning should contain location name');
    assert_true(str_contains($result[0], 'Location Conflict'), 'Warning should indicate location conflict type');
});

register_test('checkScrConflicts: both conflicts returns two warnings', function () {
    $db = new CDSMockDatabase();
    $db->teamConflictRows = [
        [
            'conflict_time'      => '10:00:00',
            'conflict_date'      => '2026-07-20',
            'conflict_team_name' => 'Yankees',
        ],
    ];
    $db->locationRow = ['location_id' => 7];
    $db->locationConflictRows = [
        [
            'conflict_time' => '10:00:00',
            'conflict_date' => '2026-07-20',
            'location_name' => 'Yankee Field',
        ],
    ];
    $svc = new ConflictDetectionService($db);
    $result = $svc->checkScrConflicts('2026-07-20', '10:00', 'Yankee Field', 5, 6);

    assert_equals(count($result), 2, 'Should return two warnings when both conflicts exist');
    $combined = implode(' ', $result);
    assert_true(str_contains($combined, 'Yankees'), 'Warnings should include team name');
    assert_true(str_contains($combined, 'Yankee Field'), 'Warnings should include location name');
});

register_test('checkScrConflicts: empty proposed time still runs conflict check', function () {
    $db = new CDSMockDatabase();
    $db->teamConflictRows = [
        [
            'conflict_time'      => null,
            'conflict_date'      => '2026-07-20',
            'conflict_team_name' => 'Tigers',
        ],
    ];
    $svc = new ConflictDetectionService($db);
    $result = $svc->checkScrConflicts('2026-07-20', '', '', 5, 6);

    assert_equals(count($result), 1, 'Empty time should still produce warning for NULL-time games');
    assert_true(str_contains($result[0], 'TBD'), 'NULL conflict time should show TBD in warning');
});

register_test('checkScrConflicts: location not in locations table skips location check', function () {
    $db = new CDSMockDatabase();
    // locationRow stays null — location not found in DB
    $db->locationConflictRows = [
        ['conflict_time' => '10:00:00', 'conflict_date' => '2026-07-20', 'location_name' => 'Ghost Field'],
    ];
    $svc = new ConflictDetectionService($db);
    $result = $svc->checkScrConflicts('2026-07-20', '10:00', 'Unknown Location', 5, 6);

    assert_equals($result, [], 'Unknown location should skip location check and return no location warnings');
    $locationFetchAllCalled = false;
    foreach ($db->fetchAllCalls as $call) {
        if (stripos($call['sql'], 'location_name') !== false) {
            $locationFetchAllCalled = true;
        }
    }
    assert_equals($locationFetchAllCalled, false, 'Location fetchAll should not be called when location_id lookup fails');
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
