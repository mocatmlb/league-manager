<?php
/**
 * Unit Tests: UmpireAssignmentService
 *
 * Story 23.1 — Unassigned Games Queue & Assignment Board
 * AC: 1 (window filter), 2 (zero window = no filter), 6 (save settings), 7 (reject invalid input)
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ActivityLogger.php';

// ---------------------------------------------------------------------------
// Stubs for global helpers
// Stubs are guarded so this file can run alone or inside the full suite.
// ---------------------------------------------------------------------------

$GLOBALS['__updateSetting_calls'] = [];

if (!function_exists('getSetting')) {
    function getSetting(string $key, string $default = '') {
        return $GLOBALS['_test_settings'][$key] ?? $default;
    }
}

if (!function_exists('updateSetting')) {
    function updateSetting(string $key, string $value): void {
        $GLOBALS['__updateSetting_calls'][] = ['key' => $key, 'value' => $value];
    }
}

// ---------------------------------------------------------------------------
// Mock Database
// ---------------------------------------------------------------------------

class UmpireAssignmentMockDb extends Database {
    public array $lastSql      = [];
    public array $lastParams   = [];
    public array $rows         = [];
    public array $queryRows    = [];
    public ?array $fetchOneRow = null;  // null = return false; set to row array to return that row
    public array $fetchOneRows = [];

    public function __construct() {}

    public function query($sql, $params = []) {
        $this->lastSql[]    = $sql;
        $this->lastParams[] = $params;
        $rows = !empty($this->queryRows) ? array_shift($this->queryRows) : $this->rows;
        return new UmpireAssignmentMockStmt($rows);
    }

    public function fetchOne($sql, $params = []) {
        $this->lastSql[] = $sql;
        $this->lastParams[] = $params;
        if (!empty($this->fetchOneRows)) {
            return array_shift($this->fetchOneRows);
        }
        return $this->fetchOneRow ?? false;
    }
}

class UmpireAssignmentMockStmt {
    private array $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function fetchAll($mode = null): array { return $this->rows; }
}

// ---------------------------------------------------------------------------
// Load service under test
// ---------------------------------------------------------------------------

require_once __DIR__ . '/../../includes/UmpireAssignmentService.php';

// ---------------------------------------------------------------------------
// Tests: getQueueWindowDays
// ---------------------------------------------------------------------------

register_test('23.1 getQueueWindowDays returns integer from getSetting', function () {
    $GLOBALS['_test_settings']['unassigned_queue_days'] = '7';
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    assert_equals($svc->getQueueWindowDays(), 7, 'Expected 7 from setting');
    unset($GLOBALS['_test_settings']['unassigned_queue_days']);
});

register_test('23.1 getQueueWindowDays defaults to 14 when setting absent', function () {
    unset($GLOBALS['_test_settings']['unassigned_queue_days']);
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    assert_equals($svc->getQueueWindowDays(), 14, 'Expected default 14');
});

// ---------------------------------------------------------------------------
// Tests: saveQueueWindowDays validation (AC 7)
// ---------------------------------------------------------------------------

register_test('23.1 saveQueueWindowDays rejects negative days (AC 7)', function () {
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $threw = false;
    try {
        $svc->saveQueueWindowDays(-1, 1);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
    }
    assert_true($threw, 'Expected InvalidArgumentException for negative days');
});

register_test('23.1 saveQueueWindowDays accepts zero (AC 2 + AC 6)', function () {
    $GLOBALS['__updateSetting_calls'] = [];
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $svc->saveQueueWindowDays(0, 1);
    $calls = $GLOBALS['__updateSetting_calls'];
    assert_true(count($calls) >= 1, 'Expected updateSetting to be called');
    assert_equals($calls[0]['key'],   'unassigned_queue_days', 'Expected correct setting key');
    assert_equals($calls[0]['value'], '0',                     'Expected value "0"');
});

register_test('23.1 saveQueueWindowDays writes positive value to settings (AC 6)', function () {
    $GLOBALS['__updateSetting_calls'] = [];
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $svc->saveQueueWindowDays(30, 1);
    $calls = $GLOBALS['__updateSetting_calls'];
    assert_true(count($calls) >= 1, 'Expected updateSetting to be called');
    assert_equals($calls[0]['value'], '30', 'Expected value "30"');
});

// ---------------------------------------------------------------------------
// Tests: getUnassignedQueue SQL shape (AC 1, 2)
// ---------------------------------------------------------------------------

register_test('23.1 getUnassignedQueue includes window clause when windowDays > 0 (AC 1)', function () {
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $svc->getUnassignedQueue(14);
    $sql    = $mock->lastSql[0] ?? '';
    $params = $mock->lastParams[0] ?? [];
    assert_true(strpos($sql, 's.game_date >= CURDATE()') !== false, 'Expected lower date bound when window > 0');
    assert_true(strpos($sql, 'DATE_ADD') !== false,   'Expected DATE_ADD window clause in SQL');
    assert_true(strpos($sql, ':window_days') !== false, 'Expected :window_days param in SQL');
    assert_equals($params['window_days'] ?? null, 14, 'Expected window_days param = 14');
});

register_test('23.1 getUnassignedQueue omits window clause when windowDays = 0 (AC 2)', function () {
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $svc->getUnassignedQueue(0);
    $sql    = $mock->lastSql[0] ?? '';
    $params = $mock->lastParams[0] ?? [];
    assert_true(strpos($sql, 'DATE_ADD') === false, 'Expected no DATE_ADD when window=0');
    assert_true(strpos($sql, 's.game_date >= CURDATE()') === false, 'Expected no lower date bound when window=0');
    assert_true(!isset($params['window_days']),       'Expected no window_days param when window=0');
});

register_test('23.1 getUnassignedQueue counts only supported slot indexes', function () {
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $svc->getUnassignedQueue(14);
    $sql = $mock->lastSql[0] ?? '';
    assert_true(strpos($sql, 'gua.slot_index IN (0, 1)') !== false, 'Expected slot_index filter in queue SQL');
});

register_test('23.1 getUnassignedQueue returns array of rows', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->rows = [
        ['game_id' => 1, 'home_team' => 'Eagles', 'away_team' => 'Tigers', 'filled_slots' => 0],
        ['game_id' => 2, 'home_team' => 'Lions',  'away_team' => 'Bears',  'filled_slots' => 1],
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->getUnassignedQueue(14);
    assert_equals(count($result), 2, 'Expected 2 rows returned');
    assert_equals($result[0]['game_id'], 1, 'Expected first game_id = 1');
});

// ---------------------------------------------------------------------------
// Tests: getAssignmentBoard status badge logic (AC 3)
// ---------------------------------------------------------------------------

register_test('23.1 getAssignmentBoard: 0 slots filled → Unassigned/secondary (AC 3)', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->rows = [
        ['game_id' => 1, 'home_team' => 'A', 'away_team' => 'B',
         'game_date' => '2026-07-01', 'game_time' => '09:00:00',
         'game_status' => 'Active', 'division_id' => 1, 'division_name' => 'D1',
         'location_name' => 'Park', 'game_number' => 'G001',
         'draft_slots' => 0, 'published_slots' => 0, 'slot_summary' => null],
    ];
    Database::setInstance($mock);
    $svc    = new UmpireAssignmentService();
    $result = $svc->getAssignmentBoard();
    assert_equals($result[0]['board_status'], 'Unassigned', 'Expected Unassigned');
    assert_equals($result[0]['status_class'], 'secondary',  'Expected secondary class');
    assert_equals($result[0]['filled_slots'], 0, 'Expected filled_slots = 0');
});

register_test('23.1 getAssignmentBoard: 2 published slots → Published/success (AC 3)', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->rows = [
        ['game_id' => 2, 'home_team' => 'A', 'away_team' => 'B',
         'game_date' => '2026-07-01', 'game_time' => '09:00:00',
         'game_status' => 'Active', 'division_id' => 1, 'division_name' => 'D1',
         'location_name' => 'Park', 'game_number' => 'G002',
         'draft_slots' => 0, 'published_slots' => 2,
         'slot_summary' => 'John Smith|0|Published;;Jane Doe|1|Published'],
    ];
    Database::setInstance($mock);
    $svc    = new UmpireAssignmentService();
    $result = $svc->getAssignmentBoard();
    assert_equals($result[0]['board_status'], 'Published',   'Expected Published');
    assert_equals($result[0]['status_class'], 'success',     'Expected success class');
    assert_equals($result[0]['filled_slots'], 2, 'Expected filled_slots = 2');
    assert_equals($result[0]['slots'][0]['name'], 'John Smith', 'Expected slot 0 name');
    assert_equals($result[0]['slots'][1]['name'], 'Jane Doe',   'Expected slot 1 name');
});

register_test('23.1 getAssignmentBoard: any draft slot → Draft/warning (AC 3)', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->rows = [
        ['game_id' => 3, 'home_team' => 'A', 'away_team' => 'B',
         'game_date' => '2026-07-01', 'game_time' => '09:00:00',
         'game_status' => 'Active', 'division_id' => 1, 'division_name' => 'D1',
         'location_name' => 'Park', 'game_number' => 'G003',
         'draft_slots' => 1, 'published_slots' => 0,
         'slot_summary' => 'Mike Jones|0|Draft'],
    ];
    Database::setInstance($mock);
    $svc    = new UmpireAssignmentService();
    $result = $svc->getAssignmentBoard();
    assert_equals($result[0]['board_status'], 'Draft',   'Expected Draft');
    assert_equals($result[0]['status_class'], 'warning', 'Expected warning class');
});

register_test('23.1 getAssignmentBoard: 1 published 0 draft → Partial/info (AC 3)', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->rows = [
        ['game_id' => 4, 'home_team' => 'A', 'away_team' => 'B',
         'game_date' => '2026-07-01', 'game_time' => '09:00:00',
         'game_status' => 'Active', 'division_id' => 1, 'division_name' => 'D1',
         'location_name' => 'Park', 'game_number' => 'G004',
         'draft_slots' => 0, 'published_slots' => 1,
         'slot_summary' => 'Sara Lee|0|Published'],
    ];
    Database::setInstance($mock);
    $svc    = new UmpireAssignmentService();
    $result = $svc->getAssignmentBoard();
    assert_equals($result[0]['board_status'], 'Partial', 'Expected Partial');
    assert_equals($result[0]['status_class'], 'info',    'Expected info class');
});

register_test('23.1 getAssignmentBoard counts only supported slot indexes', function () {
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $svc->getAssignmentBoard();
    $sql = $mock->lastSql[0] ?? '';
    assert_true(strpos($sql, 'gua.slot_index IN (0, 1)') !== false, 'Expected slot_index filter in board SQL');
});

// ---------------------------------------------------------------------------
// Tests: Story 23.2 drawer data and slot mutation behavior
// ---------------------------------------------------------------------------

register_test('23.2 getGameAssignmentDrawer returns game, slots, labels, roster, load, and migration mode', function () {
    $GLOBALS['_test_settings']['umpire_slot_1_label'] = 'Plate';
    $GLOBALS['_test_settings']['umpire_slot_2_label'] = 'Bases';
    $_SESSION['umpire_migration_mode'] = true;

    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        [
            'game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Scheduled',
            'division_name' => 'Majors', 'home_team' => 'Home', 'away_team' => 'Away',
            'game_date' => '2026-07-01', 'game_time' => '18:00:00', 'location_name' => 'Field 1',
        ],
        ['id' => 7],
    ];
    $mock->queryRows = [
        [],
        [['id' => 101, 'first_name' => 'Ann', 'last_name' => 'Blue', 'email' => 'a@example.test', 'phone' => '555', 'status' => 'active', 'umpire_level' => 'Blue Shirt', 'is_under_18' => 1, 'date_of_birth' => '2010-01-01']],
        [],
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->getGameAssignmentDrawer(10);

    assert_equals($result['game']['game_id'], 10, 'Expected game details');
    assert_equals($result['slot_labels'][0], 'Plate', 'Expected slot 1 label from settings');
    assert_equals($result['slot_labels'][1], 'Bases', 'Expected slot 2 label from settings');
    assert_equals($result['slots'][0]['status'], 'Open', 'Expected open slot default');
    assert_equals($result['roster'][0]['current_game_load'], 0, 'Expected merged game load default');
    assert_true($result['migration_mode'], 'Expected migration mode from roster service');

    unset($_SESSION['umpire_migration_mode'], $GLOBALS['_test_settings']['umpire_slot_1_label'], $GLOBALS['_test_settings']['umpire_slot_2_label']);
});

register_test('23.2 getGameAssignmentDrawer merges aggregate current game load counts', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_status' => 'Scheduled'],
        ['id' => 7],
    ];
    $mock->queryRows = [
        [],
        [['id' => 101, 'first_name' => 'Ann', 'last_name' => 'Blue', 'email' => 'a@example.test', 'phone' => '555', 'status' => 'active', 'umpire_level' => 'Blue Shirt', 'is_under_18' => 0, 'date_of_birth' => null]],
        [['umpire_user_id' => 101, 'current_game_load' => 3]],
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->getGameAssignmentDrawer(10);

    assert_equals($result['roster'][0]['current_game_load'], 3, 'Expected aggregate load count');
});

register_test('23.2 saveSlot validates supported slot index', function () {
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $threw = false;
    try {
        $svc->saveSlot(10, 2, 101, 1);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
    }
    assert_true($threw, 'Expected invalid slot index to throw');
});

register_test('23.2 saveSlot rejects inactive or non-profile umpire', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_status' => 'Scheduled'],
        ['id' => 7],
        false,
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $threw = false;
    try {
        $svc->saveSlot(10, 0, 101, 1);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
    }
    assert_true($threw, 'Expected missing active umpire profile to throw');
});

register_test('23.2 saveSlot rejects Published existing slot', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_status' => 'Scheduled'],
        ['id' => 7],
        ['id' => 101, 'status' => 'active', 'umpire_level' => 'Black Shirt'],
        ['is_under_18' => 0, 'date_of_birth' => null],
        ['assignment_status' => 'Published'],
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $threw = false;
    try {
        $svc->saveSlot(10, 0, 101, 1);
    } catch (\RuntimeException $e) {
        $threw = true;
    }
    assert_true($threw, 'Expected Published slot save rejection');
});

register_test('23.2 saveSlot upserts Draft assignment and logs normal assignment', function () {
    unset($_SESSION['umpire_migration_mode']);
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_status' => 'Scheduled'],
        ['id' => 7],
        ['id' => 101, 'status' => 'active', 'umpire_level' => 'Black Shirt'],
        ['is_under_18' => 0, 'date_of_birth' => null],
        false,
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->saveSlot(10, 0, 101, 1);
    $sqlLog = implode("\n", $mock->lastSql);

    assert_equals($result['slot']['status'], 'Draft', 'Expected Draft slot result');
    assert_true(strpos($sqlLog, 'ON DUPLICATE KEY UPDATE') !== false, 'Expected upsert SQL');
    assert_true(strpos($sqlLog, 'SELECT is_under_18, date_of_birth FROM umpire_profiles') !== false, 'Expected under-18 reconciliation query');
    $loggedEvents = array_column(array_filter($mock->lastParams, static function ($p) { return isset($p['event']); }), 'event');
    assert_true(in_array('umpire.assigned', $loggedEvents, true), 'Expected umpire.assigned event in activity log');
    assert_true(!in_array('umpire.migrated', $loggedEvents, true), 'Expected umpire.migrated NOT logged in non-migration mode');
});

register_test('23.2 saveSlot stores migration mode flag when enabled', function () {
    $_SESSION['umpire_migration_mode'] = true;
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_status' => 'Scheduled'],
        ['id' => 7],
        ['id' => 101, 'status' => 'active', 'umpire_level' => 'Black Shirt'],
        ['is_under_18' => 0, 'date_of_birth' => null],
        false,
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $svc->saveSlot(10, 0, 101, 1);
    $upsertParams = [];
    foreach ($mock->lastParams as $params) {
        if (isset($params['migration_mode'])) {
            $upsertParams = $params;
            break;
        }
    }
    assert_equals($upsertParams['migration_mode'] ?? null, 1, 'Expected migration mode flag in upsert');

    $loggedEvents = array_column(array_filter($mock->lastParams, static function ($p) { return isset($p['event']); }), 'event');
    assert_true(in_array('umpire.migrated', $loggedEvents, true), 'Expected umpire.migrated event logged in migration mode');
    assert_true(!in_array('umpire.assigned', $loggedEvents, true), 'Expected umpire.assigned NOT logged in migration mode');
    unset($_SESSION['umpire_migration_mode']);
});

register_test('23.2 unassignSlot rejects Published existing slot', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_status' => 'Scheduled'],
        ['assignment_status' => 'Published', 'umpire_user_id' => 101],
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $threw = false;
    try {
        $svc->unassignSlot(10, 0, 1);
    } catch (\RuntimeException $e) {
        $threw = true;
        assert_equals($e->getCode(), 409, 'Expected 409 code for Published slot unassign rejection');
    }
    assert_true($threw, 'Expected Published slot unassign to throw RuntimeException');
});

register_test('23.2 unassignSlot opens an existing Draft slot and clears notification state', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_status' => 'Scheduled'],
        ['assignment_status' => 'Draft', 'umpire_user_id' => 101],
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->unassignSlot(10, 0, 1);
    $sqlLog = implode("\n", $mock->lastSql);

    assert_equals($result['slot']['status'], 'Open', 'Expected open slot result');
    assert_true(strpos($sqlLog, 'last_notified_at = NULL') !== false, 'Expected notification timestamp clear');
    assert_true(strpos($sqlLog, 'last_notified_hash = NULL') !== false, 'Expected notification hash clear');
});

register_test('23.2 POST AJAX endpoints reject invalid CSRF with HTTP 403 JSON path', function () {
    foreach (['save-slot.php', 'unassign-slot.php'] as $file) {
        $source = file_get_contents(__DIR__ . '/../../public/admin/umpires/ajax/' . $file);
        assert_true(strpos($source, "Auth::verifyCSRFToken(\$_POST['csrf_token'] ?? '')") !== false, 'Expected CSRF verification in ' . $file);
        assert_true(strpos($source, "'Invalid CSRF token.', 403") !== false, 'Expected 403 JSON error in ' . $file);
        assert_true(strpos($source, "['success' => false, 'error' =>") !== false, 'Expected JSON error envelope in ' . $file);
    }
});
