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

if (!defined('EMAIL_DEV_LOG_ONLY')) {
    define('EMAIL_DEV_LOG_ONLY', true);
}

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
    public array $insertRows   = [];
    public array $updateRows   = [];
    public array $throwOnInsertTables = [];
    public int $nextInsertId   = 1000;

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

    public function insert($table, $data) {
        if (in_array($table, $this->throwOnInsertTables, true)) {
            throw new RuntimeException('Mock insert failure for ' . $table);
        }
        $this->insertRows[] = ['table' => $table, 'data' => $data];
        return $this->nextInsertId++;
    }

    public function update($table, $data, $where, $whereParams = []) {
        $this->updateRows[] = [
            'table' => $table,
            'data' => $data,
            'where' => $where,
            'whereParams' => $whereParams,
        ];
        $this->lastSql[] = 'UPDATE ' . $table . ' SET ' . implode(', ', array_keys($data)) . ' WHERE ' . $where;
        $this->lastParams[] = array_merge($data, $whereParams);
        return new UmpireAssignmentMockStmt([]);
    }
}

class UmpireAssignmentMockStmt {
    private array $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function fetch($mode = null) { return $this->rows[0] ?? false; }
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

register_test('23.3 saveSlot rejects conflicting assignment with structured 409 payload', function () {
    unset($_SESSION['umpire_migration_mode']);
    $GLOBALS['_test_settings']['conflict_window_minutes'] = '90';
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Scheduled', 'game_date' => '2026-07-01', 'game_time' => '18:00:00'],
        ['id' => 7],
        ['id' => 101, 'status' => 'active', 'umpire_level' => 'Black Shirt'],
        ['is_under_18' => 0, 'date_of_birth' => null],
        ['assignment_id' => 22, 'assignment_status' => 'Draft', 'umpire_user_id' => 202],
    ];
    $mock->queryRows = [[
        ['assignment_id' => 55, 'game_id' => 99, 'game_number' => 'G099', 'game_date' => '2026-07-01', 'game_time' => '18:30:00', 'home_team' => 'Home', 'away_team' => 'Away', 'location_name' => 'Field 1', 'assignment_status' => 'Draft'],
    ]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();

    $threw = false;
    try {
        $svc->saveSlot(10, 0, 101, 1);
    } catch (\RuntimeException $e) {
        $threw = true;
        assert_equals($e->getCode(), 409, 'Expected conflict rejection to map to HTTP 409');
        assert_true(method_exists($e, 'getPayload'), 'Expected structured payload accessor');
        $payload = $e->getPayload();
        assert_true($payload['requires_override'] ?? false, 'Expected override flag');
        assert_equals($payload['conflict']['assignment_id'] ?? null, 55, 'Expected conflict payload');
    }
    assert_true($threw, 'Expected conflicting save to throw');
    $conflictParams = array_values(array_filter($mock->lastParams, static function ($p) {
        return isset($p['target_start'], $p['target_end']);
    }));
    assert_equals($conflictParams[0]['target_end'] ?? null, '2026-07-01 19:30:00', 'Expected assignment target window to honor conflict settings');
    unset($GLOBALS['_test_settings']['conflict_window_minutes']);
});

register_test('23.3 saveSlot allows admin conflict override and logs PII-free context', function () {
    unset($_SESSION['umpire_migration_mode']);
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Scheduled', 'game_date' => '2026-07-01', 'game_time' => '18:00:00'],
        ['id' => 7],
        ['id' => 101, 'first_name' => 'Pat', 'last_name' => 'Blue', 'email' => 'pat@example.test', 'phone' => '555', 'status' => 'active', 'umpire_level' => 'Black Shirt'],
        ['is_under_18' => 0, 'date_of_birth' => null],
        ['assignment_id' => 22, 'assignment_status' => 'Draft', 'umpire_user_id' => 202],
    ];
    $mock->queryRows = [[
        ['assignment_id' => 55, 'game_id' => 99, 'game_number' => 'G099', 'game_date' => '2026-07-01', 'game_time' => '18:30:00', 'home_team' => 'Home', 'away_team' => 'Away', 'location_name' => 'Field 1', 'assignment_status' => 'Published'],
    ]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->saveSlot(10, 0, 101, 1, true, 'Assignor approved overlap', null);

    assert_equals($result['slot']['status'], 'Draft', 'Expected override save to produce Draft slot');
    $logParams = array_values(array_filter($mock->lastParams, static function ($p) {
        return ($p['event'] ?? '') === 'umpire.override';
    }));
    assert_equals(count($logParams), 1, 'Expected one override log');
    $context = json_decode($logParams[0]['context'], true);
    assert_equals($context['reason'] ?? null, 'Assignor approved overlap', 'Expected override reason');
    assert_equals($context['target_game_id'] ?? null, 10, 'Expected target game id');
    assert_equals($context['prior_umpire_user_id'] ?? null, 202, 'Expected prior umpire id');
    assert_equals($context['new_umpire_user_id'] ?? null, 101, 'Expected new umpire id');
    assert_equals($context['conflicting_assignment_id'] ?? null, 55, 'Expected conflicting assignment id');
    assert_true(!isset($context['email']) && !isset($context['phone']) && !isset($context['first_name']) && !isset($context['last_name']), 'Expected PII-free override context');
});

register_test('23.3 saveSlot legacy admin writes NULL assigned_by_user_id and logs actor_admin_id', function () {
    unset($_SESSION['umpire_migration_mode']);
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Scheduled', 'game_date' => '2026-07-01', 'game_time' => '18:00:00'],
        ['id' => 7],
        ['id' => 101, 'status' => 'active', 'umpire_level' => 'Black Shirt'],
        ['is_under_18' => 0, 'date_of_birth' => null],
        ['assignment_id' => 22, 'assignment_status' => 'Published', 'umpire_user_id' => 202],
    ];
    $mock->queryRows = [[]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $svc->saveSlot(10, 0, 101, null, true, 'Legacy admin correction', 77);

    $upsertParams = [];
    foreach ($mock->lastParams as $params) {
        if (isset($params['migration_mode'])) {
            $upsertParams = $params;
            break;
        }
    }
    assert_true(array_key_exists('actor_user_id', $upsertParams), 'Expected actor_user_id param to be present');
    assert_null($upsertParams['actor_user_id'], 'Expected NULL assigned_by_user_id for legacy admin');
    $logParams = array_values(array_filter($mock->lastParams, static function ($p) {
        return ($p['event'] ?? '') === 'umpire.override';
    }));
    $context = json_decode($logParams[0]['context'], true);
    assert_equals($context['actor_admin_id'] ?? null, 77, 'Expected legacy actor admin id in audit context');
    assert_null($context['actor_user_id'] ?? null, 'Expected no users-table actor id');
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

register_test('23.3 unassignSlot requires admin override for Published slot', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_status' => 'Scheduled'],
        ['assignment_id' => 22, 'assignment_status' => 'Published', 'umpire_user_id' => 101],
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();

    $threw = false;
    try {
        $svc->unassignSlot(10, 0, 1, false, 'not enough');
    } catch (\RuntimeException $e) {
        $threw = true;
        assert_equals($e->getCode(), 409, 'Expected 409 for assignor Published mutation');
        assert_true(method_exists($e, 'getPayload'), 'Expected structured override payload');
        assert_true($e->getPayload()['requires_override'] ?? false, 'Expected override required flag');
    }
    assert_true($threw, 'Expected Published unassign rejection');
});

register_test('23.3 unassignSlot allows admin Published override and logs override event', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_status' => 'Scheduled'],
        ['assignment_id' => 22, 'assignment_status' => 'Published', 'umpire_user_id' => 101],
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->unassignSlot(10, 0, 1, true, 'Crew changed after publish');

    assert_equals($result['slot']['status'], 'Open', 'Expected open slot after override unassign');
    $sqlLog = implode("\n", $mock->lastSql);
    assert_true(strpos($sqlLog, 'last_notified_at = NULL') !== false, 'Expected notification timestamp clear');
    assert_true(strpos($sqlLog, 'last_notified_hash = NULL') !== false, 'Expected notification hash clear');
    $loggedEvents = array_column(array_filter($mock->lastParams, static function ($p) { return isset($p['event']); }), 'event');
    assert_true(in_array('umpire.override', $loggedEvents, true), 'Expected umpire.override event');
    assert_true(!in_array('umpire.unassigned', $loggedEvents, true), 'Expected no separate unassigned log for override');
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

register_test('23.3 POST AJAX endpoints expose override contract and session actor mapping', function () {
    foreach (['save-slot.php', 'unassign-slot.php'] as $file) {
        $source = file_get_contents(__DIR__ . '/../../public/admin/umpires/ajax/' . $file);
        assert_true(strpos($source, 'override_reason') !== false, 'Expected override_reason support in ' . $file);
        assert_true(strpos($source, "\$_SESSION['role'] ?? ''") !== false && strpos($source, "'administrator'") !== false, 'Expected administrator session check in ' . $file);
        assert_true(strpos($source, "\$_SESSION['coach_user_id']") !== false, 'Expected users-table actor mapping in ' . $file);
        assert_true(strpos($source, "\$_SESSION['admin_id']") !== false, 'Expected legacy admin actor mapping in ' . $file);
        assert_true(strpos($source, 'getPayload') !== false, 'Expected structured 409 payload passthrough in ' . $file);
    }
});

// ---------------------------------------------------------------------------
// Tests: Story 23.4 publish assignments and notification behavior
// ---------------------------------------------------------------------------

register_test('23.4 publishGame publishes filled Draft slots, queues email, updates notification hash, and logs queue id', function () {
    $GLOBALS['_test_settings']['umpire_slot_1_label'] = 'Plate';
    $GLOBALS['_test_settings']['umpire_slot_2_label'] = 'Bases';

    $mock = new UmpireAssignmentMockDb();
    $mock->nextInsertId = 8801;
    $mock->fetchOneRows = [
        [
            'game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Scheduled',
            'division_name' => 'Junior', 'home_team' => 'Home', 'away_team' => 'Away',
            'game_date' => '2026-07-01', 'game_time' => '18:00:00', 'location_name' => 'Field 1',
        ],
        ['id' => 5, 'first_name' => 'Alex', 'last_name' => 'Assignor', 'email' => 'assignor@example.test', 'phone' => '(555) 222-3333'],
        ['template_name' => 'umpire_assignment_published', 'subject_template' => 'D8 Assignment: {game_date} {game_time} — {slot_label}', 'body_template' => 'Game {game_number} {slot_label} {fee_per_team} {assignor_phone_tel}', 'is_active' => 1],
    ];
    $mock->queryRows = [
        [[
            'assignment_id' => 1010, 'game_id' => 10, 'umpire_user_id' => 201, 'slot_index' => 0,
            'assignment_status' => 'Draft', 'published' => 0, 'migration_mode' => 0,
            'first_name' => 'Pat', 'last_name' => 'Blue', 'email' => 'pat@example.test', 'phone' => '555',
        ]],
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->publishGame(10, 5, null, true);

    assert_equals($result['published'], 1, 'Expected one published slot');
    assert_true($result['warned'], 'Expected partial crew warning flag after confirmed partial publish');

    $queue = $mock->insertRows[0] ?? null;
    assert_equals($queue['table'] ?? null, 'email_queue', 'Expected email_queue insert');
    assert_true(strpos($queue['data']['subject'] ?? '', 'D8 Assignment: 07/01/2026 6:00 PM') !== false, 'Expected assignment subject');
    assert_equals($queue['data']['reply_to_email'] ?? null, 'assignor@example.test', 'Expected per-message Reply-To email');
    assert_equals($queue['data']['reply_to_name'] ?? null, 'Alex Assignor', 'Expected per-message Reply-To name');

    $sqlLog = implode("\n", $mock->lastSql);
    assert_true(strpos($sqlLog, "assignment_status = 'Published'") !== false, 'Expected Published update SQL');
    assert_true(strpos($sqlLog, 'last_notified_hash = :last_notified_hash') !== false, 'Expected notification hash update');

    $publishedLogs = array_values(array_filter($mock->lastParams, static function ($p) {
        return ($p['event'] ?? '') === 'umpire.published';
    }));
    assert_equals(count($publishedLogs), 1, 'Expected one publish audit log');
    $context = json_decode($publishedLogs[0]['context'], true);
    assert_equals($context['email_queue_id'] ?? null, 8801, 'Expected queue id in audit context');
    assert_true(!isset($context['email']) && !isset($context['phone']) && !isset($context['first_name']) && !isset($context['last_name']), 'Expected PII-free publish context');

    unset($GLOBALS['_test_settings']['umpire_slot_1_label'], $GLOBALS['_test_settings']['umpire_slot_2_label']);
});

register_test('23.4 publishGame rejects zero filled Draft slots', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_status' => 'Scheduled'],
    ];
    $mock->queryRows = [[]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $threw = false;
    try {
        $svc->publishGame(10, 5, null, false);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
    }
    assert_true($threw, 'Expected zero filled Draft slots to reject');
});

register_test('23.4 publishGame requires confirmation for partial crew', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_status' => 'Scheduled'],
    ];
    $mock->queryRows = [[
        ['assignment_id' => 1010, 'umpire_user_id' => 201, 'slot_index' => 0, 'assignment_status' => 'Draft', 'migration_mode' => 0, 'email' => 'pat@example.test'],
    ]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $threw = false;
    try {
        $svc->publishGame(10, 5, null, false);
    } catch (\RuntimeException $e) {
        $threw = true;
        assert_equals($e->getCode(), 409, 'Expected confirmation warning to use 409');
        assert_true(method_exists($e, 'getPayload'), 'Expected structured payload');
        $payload = $e->getPayload();
        assert_true($payload['requires_confirmation'] ?? false, 'Expected requires_confirmation flag');
        assert_equals($payload['warning']['filled_slots'] ?? null, 1, 'Expected filled slot count');
    }
    assert_true($threw, 'Expected partial crew warning');
});

register_test('23.4 publishGame does not warn when Published plus Draft slots fill expected crew', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->nextInsertId = 8810;
    $mock->fetchOneRows = [
        [
            'game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Scheduled',
            'division_name' => 'Junior', 'home_team' => 'Home', 'away_team' => 'Away',
            'game_date' => '2026-07-01', 'game_time' => '18:00:00', 'location_name' => 'Field 1',
        ],
        ['id' => 5, 'first_name' => 'Alex', 'last_name' => 'Assignor', 'email' => 'assignor@example.test', 'phone' => '555'],
        ['template_name' => 'umpire_assignment_published', 'subject_template' => 'D8 Assignment: {game_date} {game_time} — {slot_label}', 'body_template' => 'Game {game_number} {fee_per_team}', 'is_active' => 1],
    ];
    $mock->queryRows = [[
        ['assignment_id' => 1010, 'umpire_user_id' => 201, 'slot_index' => 0, 'assignment_status' => 'Published', 'migration_mode' => 0, 'email' => 'plate@example.test'],
        ['assignment_id' => 1011, 'umpire_user_id' => 202, 'slot_index' => 1, 'assignment_status' => 'Draft', 'migration_mode' => 0, 'email' => 'base@example.test'],
    ]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->publishGame(10, 5, null, false);

    assert_equals($result['published'], 1, 'Expected only the Draft slot to publish');
    assert_true(!$result['warned'], 'Expected no partial warning when total filled crew is two');
});

register_test('23.4 publishGame publishes migration-mode Draft without email queue or notification fields', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_status' => 'Scheduled'],
        ['id' => 5, 'first_name' => 'Alex', 'last_name' => 'Assignor', 'email' => 'assignor@example.test', 'phone' => '555'],
    ];
    $mock->queryRows = [[
        ['assignment_id' => 1010, 'umpire_user_id' => 201, 'slot_index' => 0, 'assignment_status' => 'Draft', 'migration_mode' => 1, 'email' => 'pat@example.test'],
    ]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->publishGame(10, 5, null, true);

    assert_equals($result['published'], 1, 'Expected migration row to publish');
    assert_equals(count($mock->insertRows), 0, 'Expected no email_queue insert for migration row');
    $sqlLog = implode("\n", $mock->lastSql);
    assert_true(strpos($sqlLog, 'last_notified_at = NULL') !== false, 'Expected notification timestamp remains NULL');
    assert_true(strpos($sqlLog, 'last_notified_hash = NULL') !== false, 'Expected notification hash remains NULL');
});

register_test('23.4 publish AJAX endpoint has CSRF, role gate, JSON envelope, actor mapping, and confirm partial support', function () {
    $source = file_get_contents(__DIR__ . '/../../public/admin/umpires/ajax/publish.php');
    assert_true(strpos($source, "PermissionGuard::requireRole(['admin', 'umpire_assignor'], '/login.php')") !== false, 'Expected role gate');
    assert_true(strpos($source, "Auth::verifyCSRFToken(\$_POST['csrf_token'] ?? '')") !== false, 'Expected CSRF verification');
    assert_true(strpos($source, 'confirm_partial') !== false, 'Expected confirm_partial input');
    assert_true(strpos($source, "\$_SESSION['coach_user_id']") !== false, 'Expected users-table actor mapping');
    assert_true(strpos($source, "\$_SESSION['admin_id']") !== false, 'Expected legacy admin actor mapping');
    assert_true(strpos($source, "['success' => true, 'data' =>") !== false, 'Expected success JSON envelope');
});

register_test('23.4 assignment drawer source supports publish button, partial warning retry, and refresh path', function () {
    $source = file_get_contents(__DIR__ . '/../../public/admin/umpires/assignment-drawer.js');
    assert_true(strpos($source, 'ajax/publish.php') !== false, 'Expected publish endpoint call');
    assert_true(strpos($source, 'confirm_partial') !== false, 'Expected partial confirmation retry');
    assert_true(strpos($source, 'Publish') !== false, 'Expected Publish button text');
    assert_true(strpos($source, 'updatePageRow(data)') !== false, 'Expected row refresh path');
});

// ---------------------------------------------------------------------------
// Tests: Story 23.5 cascade release notifications
// ---------------------------------------------------------------------------

register_test('23.5 onScheduleChanged cancels active assignments, inserts pending rows, and logs PII-free cascade events', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->nextInsertId = 9100;
    $mock->fetchOneRows = [[
        'game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Postponed',
        'division_name' => 'Junior', 'home_team' => 'Home', 'away_team' => 'Away',
        'game_date' => '2026-07-01', 'game_time' => '18:00:00', 'location_name' => 'Field 1',
    ]];
    $mock->queryRows = [[
        ['assignment_id' => 1010, 'game_id' => 10, 'umpire_user_id' => 201, 'slot_index' => 0, 'assignment_status' => 'Draft', 'published' => 0, 'migration_mode' => 0],
        ['assignment_id' => 1011, 'game_id' => 10, 'umpire_user_id' => 202, 'slot_index' => 1, 'assignment_status' => 'Published', 'published' => 1, 'migration_mode' => 1],
    ]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->onScheduleChanged(10, 'SCR-55', ['actor_user_id' => 5, 'source' => 'unit']);

    assert_true($result, 'Expected cascade success');
    $sqlLog = implode("\n", $mock->lastSql);
    assert_true(strpos($sqlLog, "assignment_status = 'Cancelled'") !== false, 'Expected Cancelled update');
    assert_true(strpos($sqlLog, 'published = 0') !== false, 'Expected published flag cleared');
    assert_true(strpos($sqlLog, 'last_notified_at = NULL') === false, 'Expected cascade not to clear notification timestamp');
    assert_true(strpos($sqlLog, 'last_notified_hash = NULL') === false, 'Expected cascade not to clear notification hash');

    $pendingRows = array_values(array_filter($mock->insertRows, static function ($row) {
        return ($row['table'] ?? '') === 'umpire_pending_notifications';
    }));
    assert_equals(count($pendingRows), 2, 'Expected one pending notification per affected assignment');
    assert_equals($pendingRows[0]['data']['notification_type'] ?? null, 'cascade_cancelled', 'Expected cascade notification type');
    assert_equals($pendingRows[0]['data']['trigger_event_ref'] ?? null, 'SCR-55', 'Expected trigger ref');

    $logs = array_values(array_filter($mock->lastParams, static function ($p) {
        return ($p['event'] ?? '') === 'umpire.cascade_cancelled';
    }));
    assert_equals(count($logs), 2, 'Expected one cascade audit event per assignment');
    $context = json_decode($logs[0]['context'], true);
    assert_equals($context['notification_id'] ?? null, 9100, 'Expected notification id in audit context');
    assert_equals($context['trigger_event_ref'] ?? null, 'SCR-55', 'Expected trigger ref in audit context');
    assert_true(!isset($context['email']) && !isset($context['phone']) && !isset($context['first_name']) && !isset($context['last_name']), 'Expected PII-free cascade audit context');
});

register_test('23.5 onScheduleChanged returns true without rows when no active assigned slots are affected', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [[
        'game_id' => 10, 'game_status' => 'Cancelled',
        'game_date' => '2026-07-01', 'game_time' => '18:00:00',
    ]];
    $mock->queryRows = [[]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->onScheduleChanged(10, 'GAME-CANCELLED-10');

    assert_true($result, 'Expected no-op cascade to succeed');
    assert_equals(count($mock->insertRows), 0, 'Expected no pending rows');
    $events = array_column(array_filter($mock->lastParams, static function ($p) { return isset($p['event']); }), 'event');
    assert_true(!in_array('umpire.cascade_cancelled', $events, true), 'Expected no cascade audit events');
});

register_test('23.5 onScheduleChanged catches pending notification insert failure and returns false', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->throwOnInsertTables = ['umpire_pending_notifications'];
    $mock->fetchOneRows = [[
        'game_id' => 10, 'game_status' => 'Postponed',
        'game_date' => '2026-07-01', 'game_time' => '18:00:00',
    ]];
    $mock->queryRows = [[
        ['assignment_id' => 1010, 'game_id' => 10, 'umpire_user_id' => 201, 'slot_index' => 0, 'assignment_status' => 'Published'],
    ]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->onScheduleChanged(10, 'SCR-55');

    assert_true(!$result, 'Expected insert failure to return false, not throw');
    $sqlLog = implode("\n", $mock->lastSql);
    assert_true(strpos($sqlLog, "assignment_status = 'Cancelled'") !== false, 'Expected cancellation attempted before insert failure');
});

register_test('23.5 live trigger sources call onScheduleChanged before commit and preserve existing notifications', function () {
    $checks = [
        [
            'file' => __DIR__ . '/../../public/admin/schedules/index.php',
            'tokens' => ['SCR-{$requestId}', 'DIRECT-SCHEDULE-{$newRequestId}'],
            'notifications' => ['onSchedulePostponed', 'onScheduleChangeApprove'],
        ],
        [
            'file' => __DIR__ . '/../../includes/RescheduleService.php',
            'tokens' => ['SCR-{$requestId}'],
            'notifications' => ['onSchedulePostponed'],
        ],
        [
            'file' => __DIR__ . '/../../public/admin/games/index.php',
            'tokens' => ['GAME-CANCELLED-{$gameId}', 'GAME-POSTPONED-{$gameId}'],
            'notifications' => ['onScheduleCancellation', 'onSchedulePostponed'],
        ],
        [
            'file' => __DIR__ . '/../../public/admin/games/index_full.php',
            'tokens' => ['GAME-CANCELLED-{$gameId}', 'GAME-POSTPONED-{$gameId}'],
            'notifications' => [],
        ],
    ];

    foreach ($checks as $check) {
        $source = file_get_contents($check['file']);
        assert_true(strpos($source, 'UmpireAssignmentService') !== false, 'Expected service include/use in ' . basename($check['file']));
        foreach ($check['tokens'] as $token) {
            $pos = strpos($source, $token);
            assert_true($pos !== false, 'Expected trigger token ' . $token . ' in ' . basename($check['file']));
            $commitPos = strpos($source, 'commit()', $pos);
            assert_true($commitPos !== false, 'Expected commit after trigger token ' . $token . ' in ' . basename($check['file']));
            assert_true($commitPos > $pos, 'Expected cascade trigger before following commit in ' . basename($check['file']));
        }
        foreach ($check['notifications'] as $notification) {
            assert_true(strpos($source, $notification) !== false, 'Expected existing notification ' . $notification . ' to remain in ' . basename($check['file']));
        }
    }
});
