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
        if (($GLOBALS['__updateSetting_throw_on_key'] ?? null) === $key) {
            throw new RuntimeException('Mock updateSetting failure for ' . $key);
        }
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
    public ?string $throwOnInsertNotificationType = null;
    public bool $transactionStarted = false;
    public bool $transactionCommitted = false;
    public bool $transactionRolledBack = false;
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
        if ($table === 'umpire_pending_notifications'
            && $this->throwOnInsertNotificationType !== null
            && (($data['notification_type'] ?? '') === $this->throwOnInsertNotificationType)) {
            throw new RuntimeException('Mock insert failure for notification type ' . $this->throwOnInsertNotificationType);
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

    public function beginTransaction() {
        $this->transactionStarted = true;
        $this->transactionCommitted = false;
        $this->transactionRolledBack = false;
        return true;
    }

    public function commit() {
        $this->transactionCommitted = true;
        return true;
    }

    public function rollBack() {
        $this->transactionRolledBack = true;
        return true;
    }
}

class UmpireAssignmentMockStmt {
    private array $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function fetch($mode = null) {
        if (empty($this->rows)) {
            return false;
        }
        return array_shift($this->rows);
    }
    public function fetchAll($mode = null): array { return $this->rows; }
}

function umpire_assignment_seed_settings_actor(UmpireAssignmentMockDb $mock, string $role = 'administrator'): void {
    array_unshift($mock->fetchOneRows, ['role_name' => $role]);
}

function umpire_assignment_publish_game_row(array $overrides = []): array {
    return array_merge([
        'game_id' => 10,
        'game_number' => 'G010',
        'game_status' => 'Scheduled',
        'division_name' => 'Junior',
        'home_team' => 'Home',
        'away_team' => 'Away',
        'game_date' => '2026-07-01',
        'game_time' => '18:00:00',
        'location_id' => 0,
        'location_name' => 'Field 1',
    ], $overrides);
}

function umpire_assignment_notification_hash(
    int $gameId,
    int $slotIndex,
    int $umpireUserId,
    string $gameDate = '2026-07-01',
    string $gameTime = '18:00:00',
    int $locationId = 0
): string {
    $scheduledAt = trim($gameDate . ' ' . ($gameTime !== '' ? $gameTime : '00:00:00'));
    return hash('sha256', implode('|', [$gameId, $slotIndex, $umpireUserId, $scheduledAt, $locationId]));
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
    umpire_assignment_seed_settings_actor($mock);
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
    umpire_assignment_seed_settings_actor($mock);
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
    umpire_assignment_seed_settings_actor($mock);
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
        ['program_id' => 0],
    ];
    $mock->queryRows = [
        [],
        [['id' => 101, 'first_name' => 'Ann', 'last_name' => 'Blue', 'email' => 'a@example.test', 'phone' => '555', 'status' => 'active', 'umpire_level' => 'Blue Shirt', 'is_under_18' => 0, 'date_of_birth' => null]],
        [],
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
        ['program_id' => 0],
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
        ['program_id' => 0],
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

register_test('23.2 saveSlot rejects same umpire in another active slot on the same game', function () {
    unset($_SESSION['umpire_migration_mode']);
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_status' => 'Scheduled'],
        ['program_id' => 0],
        ['id' => 7],
        ['id' => 101, 'status' => 'active', 'umpire_level' => 'Black Shirt'],
        ['is_under_18' => 0, 'date_of_birth' => null],
        false,
        ['assignment_id' => 44, 'slot_index' => 1],
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();

    $threw = false;
    try {
        $svc->saveSlot(10, 0, 101, 1);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
        assert_equals($e->getMessage(), 'Selected umpire is already assigned to another slot on this game.', 'Expected duplicate same-game message');
    }
    assert_true($threw, 'Expected duplicate same-game assignment to reject');
    $sqlLog = implode("\n", $mock->lastSql);
    assert_true(strpos($sqlLog, 'slot_index <> :slot_index') !== false, 'Expected other-slot duplicate guard query');
});

register_test('23.6 saveSlot allows current slot umpire when duplicate guard scans same game', function () {
    unset($_SESSION['umpire_migration_mode']);
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Scheduled', 'game_date' => '2026-07-01', 'game_time' => '18:00:00'],
        ['program_id' => 0],
        ['id' => 7],
        ['id' => 101, 'status' => 'active', 'umpire_level' => 'Black Shirt'],
        ['is_under_18' => 0, 'date_of_birth' => null],
        ['assignment_id' => 22, 'assignment_status' => 'Draft', 'umpire_user_id' => 101],
        false,
    ];
    $mock->queryRows = [[]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();

    $result = $svc->saveSlot(10, 0, 101, 1);

    assert_equals($result['slot']['umpire_user_id'], 101, 'Expected same-slot umpire to remain assignable');
    $sqlLog = implode("\n", $mock->lastSql);
    assert_true(strpos($sqlLog, 'slot_index <> :slot_index') !== false, 'Expected duplicate guard to exclude current slot');
    assert_true(strpos($sqlLog, 'ON DUPLICATE KEY UPDATE') !== false, 'Expected normal save path to continue');
});

register_test('23.3 saveSlot rejects conflicting assignment with structured 409 payload', function () {
    unset($_SESSION['umpire_migration_mode']);
    $GLOBALS['_test_settings']['conflict_window_minutes'] = '90';
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Scheduled', 'game_date' => '2026-07-01', 'game_time' => '18:00:00', 'location_id' => 1, 'location_name' => 'Field 1'],
        ['program_id' => 0],
        ['id' => 7],
        ['id' => 101, 'status' => 'active', 'umpire_level' => 'Black Shirt'],
        ['is_under_18' => 0, 'date_of_birth' => null],
        ['assignment_id' => 22, 'assignment_status' => 'Draft', 'umpire_user_id' => 202],
    ];
    $mock->queryRows = [[
        ['assignment_id' => 55, 'game_id' => 99, 'game_number' => 'G099', 'game_date' => '2026-07-01', 'game_time' => '18:00:00', 'location_id' => 1, 'home_team' => 'Home', 'away_team' => 'Away', 'location_name' => 'Field 1', 'assignment_status' => 'Draft'],
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

register_test('24.5 saveSlot allows proximity-only different-location overlap with travel warning', function () {
    unset($_SESSION['umpire_migration_mode']);
    $GLOBALS['_test_settings']['conflict_window_minutes'] = '20';
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Scheduled', 'game_date' => '2026-07-01', 'game_time' => '18:30:00', 'location_id' => 2, 'location_name' => 'Field 2', 'pending_scr_count' => 0],
        ['program_id' => 0],
        ['id' => 7],
        ['id' => 101, 'status' => 'active', 'umpire_level' => 'Black Shirt'],
        ['is_under_18' => 0, 'date_of_birth' => null],
        false,
        false,
        false,
    ];
    $mock->queryRows = [
        [[
            'assignment_id' => 55,
            'game_id' => 99,
            'slot_index' => 0,
            'game_date' => '2026-07-01',
            'game_time' => '18:00:00',
            'location_id' => 1,
            'location_name' => 'Field 1',
            'assignment_status' => 'Draft',
        ]],
        [],
        [],
        [[
            'assignment_id' => 55,
            'game_id' => 99,
            'slot_index' => 0,
            'game_date' => '2026-07-01',
            'game_time' => '18:00:00',
            'location_id' => 1,
            'location_name' => 'Field 1',
        ]],
        [[
            'umpire_user_id' => 101,
        ]],
        [],
        [],
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->saveSlot(10, 0, 101, 1);

    assert_equals($result['slot']['umpire_user_id'], 101, 'Expected proximity-only save to succeed');
    $warnings = $result['warnings'] ?? [];
    assert_true(count($warnings) > 0, 'Expected travel warning payload');
    assert_equals($warnings[0]['type'] ?? null, 'back_to_back_travel', 'Expected back-to-back travel warning type');
    unset($GLOBALS['_test_settings']['conflict_window_minutes']);
});

register_test('23.3 saveSlot allows admin conflict override and logs PII-free context', function () {
    unset($_SESSION['umpire_migration_mode']);
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Scheduled', 'game_date' => '2026-07-01', 'game_time' => '18:00:00', 'location_id' => 1, 'location_name' => 'Field 1'],
        ['program_id' => 0],
        ['id' => 7],
        ['id' => 101, 'status' => 'active', 'umpire_level' => 'Black Shirt'],
        ['is_under_18' => 0, 'date_of_birth' => null],
        ['assignment_id' => 22, 'assignment_status' => 'Draft', 'umpire_user_id' => 202],
    ];
    $mock->queryRows = [[
        ['assignment_id' => 55, 'game_id' => 99, 'game_number' => 'G099', 'game_date' => '2026-07-01', 'game_time' => '18:00:00', 'location_id' => 1, 'home_team' => 'Home', 'away_team' => 'Away', 'location_name' => 'Field 1', 'assignment_status' => 'Published'],
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
        ['program_id' => 0],
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
        ['program_id' => 0],
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
        ['program_id' => 0],
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
    assert_equals($result['notified'] ?? null, 1, 'Expected one notified slot');
    assert_equals($result['suppressed'] ?? null, 0, 'Expected zero suppressed slots');

    $queue = $mock->insertRows[0] ?? null;
    assert_equals($queue['table'] ?? null, 'email_queue', 'Expected email_queue insert');
    assert_true(strpos($queue['data']['subject'] ?? '', 'D8 Assignment: 07/01/2026 6:00 PM') !== false, 'Expected assignment subject');
    assert_equals($queue['data']['reply_to_email'] ?? null, 'assignor@example.test', 'Expected per-message Reply-To email');
    assert_equals($queue['data']['reply_to_name'] ?? null, 'Alex Assignor', 'Expected per-message Reply-To name');

    $sqlLog = implode("\n", $mock->lastSql);
    assert_true(strpos($sqlLog, "assignment_status = 'Published'") !== false, 'Expected Published update SQL');
    assert_true(strpos($sqlLog, 'last_notified_hash = :last_notified_hash') !== false, 'Expected notification hash update');
    assert_true(strpos($sqlLog, 'assigned_by_user_id = COALESCE(:actor_user_id, assigned_by_user_id)') !== false, 'Expected publish actor to be stored when available');
    $publishUpdateParams = array_values(array_filter($mock->lastParams, static function ($params) {
        return array_key_exists('last_notified_hash', $params);
    }));
    assert_equals(5, $publishUpdateParams[0]['actor_user_id'] ?? null, 'Expected users-table publish actor id in slot update');

    $publishedLogs = array_values(array_filter($mock->lastParams, static function ($p) {
        return ($p['event'] ?? '') === 'umpire.published';
    }));
    assert_equals(count($publishedLogs), 1, 'Expected one publish audit log');
    $context = json_decode($publishedLogs[0]['context'], true);
    assert_equals($context['email_queue_id'] ?? null, 8801, 'Expected queue id in audit context');
    assert_true(!isset($context['email']) && !isset($context['phone']) && !isset($context['first_name']) && !isset($context['last_name']), 'Expected PII-free publish context');

    unset($GLOBALS['_test_settings']['umpire_slot_1_label'], $GLOBALS['_test_settings']['umpire_slot_2_label']);
});

register_test('23.4 publishGame rejects zero filled Draft and Published slots', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        umpire_assignment_publish_game_row(),
    ];
    $mock->queryRows = [[]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $threw = false;
    try {
        $svc->publishGame(10, 5, null, false);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
        assert_true(strpos($e->getMessage(), 'Draft or Published') !== false, 'Expected filled slot requirement message');
    }
    assert_true($threw, 'Expected zero filled slots to reject');
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
        [
            'assignment_id' => 1010,
            'umpire_user_id' => 201,
            'slot_index' => 0,
            'assignment_status' => 'Published',
            'migration_mode' => 0,
            'email' => 'plate@example.test',
            'last_notified_hash' => umpire_assignment_notification_hash(10, 0, 201),
        ],
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

// ---------------------------------------------------------------------------
// Tests: Story 24.4 delta notification on re-publish
// ---------------------------------------------------------------------------

register_test('24.4 publishGame allows all-Published re-publish with unchanged hashes and no emails', function () {
    $storedHash = umpire_assignment_notification_hash(10, 0, 201);
    $storedHashTwo = umpire_assignment_notification_hash(10, 1, 202);
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        umpire_assignment_publish_game_row(),
        ['id' => 5, 'first_name' => 'Alex', 'last_name' => 'Assignor', 'email' => 'assignor@example.test', 'phone' => '555'],
    ];
    $mock->queryRows = [[
        [
            'assignment_id' => 1010, 'umpire_user_id' => 201, 'slot_index' => 0,
            'assignment_status' => 'Published', 'migration_mode' => 0, 'email' => 'plate@example.test',
            'last_notified_at' => '2026-06-01 10:00:00', 'last_notified_hash' => $storedHash,
        ],
        [
            'assignment_id' => 1011, 'umpire_user_id' => 202, 'slot_index' => 1,
            'assignment_status' => 'Published', 'migration_mode' => 0, 'email' => 'base@example.test',
            'last_notified_at' => '2026-06-01 10:00:00', 'last_notified_hash' => $storedHashTwo,
        ],
    ]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->publishGame(10, 5, null, false);

    assert_equals($result['published'], 0, 'Expected no Draft to Published transitions');
    assert_equals($result['notified'], 0, 'Expected no emails queued');
    assert_equals($result['suppressed'], 2, 'Expected both slots suppressed');
    assert_true(!$result['warned'], 'Expected no partial warning on pure re-publish');
    assert_equals(count($mock->insertRows), 0, 'Expected no email_queue insert');
    $sqlLog = implode("\n", $mock->lastSql);
    assert_true(strpos($sqlLog, 'last_notified_at = NOW()') === false, 'Expected unchanged slots to preserve last_notified_at');
});

register_test('24.4 publishGame emails only changed Draft slot when other Published slot hash matches', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->nextInsertId = 8901;
    $mock->fetchOneRows = [
        umpire_assignment_publish_game_row(),
        ['id' => 5, 'first_name' => 'Alex', 'last_name' => 'Assignor', 'email' => 'assignor@example.test', 'phone' => '555'],
        ['template_name' => 'umpire_assignment_published', 'subject_template' => 'D8 Assignment: {game_date} {game_time} — {slot_label}', 'body_template' => 'Game {game_number}', 'is_active' => 1],
    ];
    $mock->queryRows = [[
        [
            'assignment_id' => 1010, 'umpire_user_id' => 201, 'slot_index' => 0,
            'assignment_status' => 'Published', 'migration_mode' => 0, 'email' => 'plate@example.test',
            'last_notified_at' => '2026-06-01 10:00:00',
            'last_notified_hash' => umpire_assignment_notification_hash(10, 0, 201),
        ],
        [
            'assignment_id' => 1011, 'umpire_user_id' => 203, 'slot_index' => 1,
            'assignment_status' => 'Draft', 'migration_mode' => 0, 'email' => 'newbase@example.test',
        ],
    ]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->publishGame(10, 5, null, false);

    assert_equals($result['published'], 1, 'Expected one Draft slot published');
    assert_equals($result['notified'], 1, 'Expected one email queued');
    assert_equals($result['suppressed'], 1, 'Expected unchanged Published slot suppressed');
    assert_equals(count($mock->insertRows), 1, 'Expected one email_queue insert');
    $sqlLog = implode("\n", $mock->lastSql);
    assert_true(strpos($sqlLog, "assignment_status = 'Published'") !== false, 'Expected Draft slot published');
    assert_true(strpos($sqlLog, 'last_notified_at = NOW()') !== false, 'Expected notified slot timestamp update');
});

register_test('24.4 publishGame re-notifies all Published slots when schedule changes', function () {
    $oldHashOne = umpire_assignment_notification_hash(10, 0, 201, '2026-07-01', '18:00:00', 0);
    $oldHashTwo = umpire_assignment_notification_hash(10, 1, 202, '2026-07-01', '18:00:00', 0);
    $mock = new UmpireAssignmentMockDb();
    $mock->nextInsertId = 8910;
    $mock->fetchOneRows = [
        umpire_assignment_publish_game_row(['game_date' => '2026-07-15', 'game_time' => '19:30:00']),
        ['id' => 5, 'first_name' => 'Alex', 'last_name' => 'Assignor', 'email' => 'assignor@example.test', 'phone' => '555'],
        ['template_name' => 'umpire_assignment_published', 'subject_template' => 'D8 Assignment: {game_date} {game_time} — {slot_label}', 'body_template' => 'Game {game_number}', 'is_active' => 1],
        ['template_name' => 'umpire_assignment_published', 'subject_template' => 'D8 Assignment: {game_date} {game_time} — {slot_label}', 'body_template' => 'Game {game_number}', 'is_active' => 1],
    ];
    $mock->queryRows = [[
        [
            'assignment_id' => 1010, 'umpire_user_id' => 201, 'slot_index' => 0,
            'assignment_status' => 'Published', 'migration_mode' => 0, 'email' => 'plate@example.test',
            'last_notified_hash' => $oldHashOne,
        ],
        [
            'assignment_id' => 1011, 'umpire_user_id' => 202, 'slot_index' => 1,
            'assignment_status' => 'Published', 'migration_mode' => 0, 'email' => 'base@example.test',
            'last_notified_hash' => $oldHashTwo,
        ],
    ]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->publishGame(10, 5, null, false);

    assert_equals($result['published'], 0, 'Expected no status transitions');
    assert_equals($result['notified'], 2, 'Expected both umpires re-notified');
    assert_equals($result['suppressed'], 0, 'Expected no suppressed slots');
    assert_equals(count($mock->insertRows), 2, 'Expected two email_queue inserts');
    $notifySqlCount = count(array_filter($mock->lastSql, static function ($sql) {
        return strpos($sql, "assignment_status = 'Published'") !== false
            && strpos($sql, 'last_notified_hash = :last_notified_hash') !== false
            && strpos($sql, "assignment_status = 'Draft'") === false;
    }));
    assert_true($notifySqlCount >= 2, 'Expected markSlotNotified updates for both slots');
});

register_test('24.4 publishGame re-notifies all Published slots when location_id changes', function () {
    $oldHash = umpire_assignment_notification_hash(10, 0, 201, '2026-07-01', '18:00:00', 5);
    $mock = new UmpireAssignmentMockDb();
    $mock->nextInsertId = 8920;
    $mock->fetchOneRows = [
        umpire_assignment_publish_game_row(['location_id' => 9]),
        ['id' => 5, 'first_name' => 'Alex', 'last_name' => 'Assignor', 'email' => 'assignor@example.test', 'phone' => '555'],
        ['template_name' => 'umpire_assignment_published', 'subject_template' => 'D8 Assignment: {game_date} {game_time} — {slot_label}', 'body_template' => 'Game {game_number}', 'is_active' => 1],
    ];
    $mock->queryRows = [[
        [
            'assignment_id' => 1010, 'umpire_user_id' => 201, 'slot_index' => 0,
            'assignment_status' => 'Published', 'migration_mode' => 0, 'email' => 'plate@example.test',
            'last_notified_hash' => $oldHash,
        ],
    ]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->publishGame(10, 5, null, false);

    assert_equals($result['notified'], 1, 'Expected location change to queue email');
    assert_equals($result['suppressed'], 0, 'Expected no suppressed slots');
    assert_equals(count($mock->insertRows), 1, 'Expected one email_queue insert');
});

register_test('24.4 publishGame note-only re-publish sends no emails when hashes match', function () {
    $storedHash = umpire_assignment_notification_hash(10, 0, 201);
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        umpire_assignment_publish_game_row(),
        ['id' => 5, 'first_name' => 'Alex', 'last_name' => 'Assignor', 'email' => 'assignor@example.test', 'phone' => '555'],
    ];
    $mock->queryRows = [[
        [
            'assignment_id' => 1010, 'umpire_user_id' => 201, 'slot_index' => 0,
            'assignment_status' => 'Published', 'migration_mode' => 0, 'email' => 'plate@example.test',
            'last_notified_hash' => $storedHash,
        ],
    ]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->publishGame(10, 5, null, false);

    assert_equals($result['notified'], 0, 'Expected note-only re-publish to skip emails');
    assert_equals($result['suppressed'], 1, 'Expected unchanged slot suppressed');
    assert_equals(count($mock->insertRows), 0, 'Expected no email_queue insert');
});

register_test('24.4 publishGame pure re-publish with partial crew does not require confirm_partial', function () {
    $storedHash = umpire_assignment_notification_hash(10, 0, 201);
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        umpire_assignment_publish_game_row(),
        ['id' => 5, 'first_name' => 'Alex', 'last_name' => 'Assignor', 'email' => 'assignor@example.test', 'phone' => '555'],
    ];
    $mock->queryRows = [[
        [
            'assignment_id' => 1010, 'umpire_user_id' => 201, 'slot_index' => 0,
            'assignment_status' => 'Published', 'migration_mode' => 0, 'email' => 'plate@example.test',
            'last_notified_hash' => $storedHash,
        ],
    ]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->publishGame(10, 5, null, false);

    assert_equals($result['notified'], 0, 'Expected unchanged partial crew re-publish without confirm');
    assert_true(!$result['warned'], 'Expected no partial warning on pure re-publish');
});

register_test('24.4 publishGame logs umpire.published only for notified slots', function () {
    $storedHash = umpire_assignment_notification_hash(10, 0, 201);
    $mock = new UmpireAssignmentMockDb();
    $mock->nextInsertId = 8930;
    $mock->fetchOneRows = [
        umpire_assignment_publish_game_row(),
        ['id' => 5, 'first_name' => 'Alex', 'last_name' => 'Assignor', 'email' => 'assignor@example.test', 'phone' => '555'],
        ['template_name' => 'umpire_assignment_published', 'subject_template' => 'D8 Assignment: {game_date} {game_time} — {slot_label}', 'body_template' => 'Game {game_number}', 'is_active' => 1],
    ];
    $mock->queryRows = [[
        [
            'assignment_id' => 1010, 'umpire_user_id' => 201, 'slot_index' => 0,
            'assignment_status' => 'Published', 'migration_mode' => 0, 'email' => 'plate@example.test',
            'last_notified_hash' => $storedHash,
        ],
        [
            'assignment_id' => 1011, 'umpire_user_id' => 203, 'slot_index' => 1,
            'assignment_status' => 'Draft', 'migration_mode' => 0, 'email' => 'newbase@example.test',
        ],
    ]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $svc->publishGame(10, 5, null, false);

    $assignedLogs = array_values(array_filter($mock->lastParams, static function ($p) {
        return ($p['event'] ?? '') === 'umpire.assigned';
    }));
    $publishedLogs = array_values(array_filter($mock->lastParams, static function ($p) {
        return ($p['event'] ?? '') === 'umpire.published';
    }));
    assert_equals(count($assignedLogs), 1, 'Expected assigned log only for Draft slot');
    assert_equals(count($publishedLogs), 1, 'Expected published log only for notified slot');
});

register_test('24.4 assignment drawer source exposes re-publish when all filled slots are Published', function () {
    $source = file_get_contents(__DIR__ . '/../../public/admin/umpires/assignment-drawer.js');
    assert_true(strpos($source, 'ajax/publish.php') !== false, 'Expected publish endpoint call');
    assert_true(strpos($source, 'confirm_partial') !== false, 'Expected partial confirmation retry');
    assert_true(strpos($source, 'Publish') !== false, 'Expected Publish button text');
    assert_true(strpos($source, 'Re-Publish') !== false, 'Expected Re-Publish button text');
    assert_true(strpos($source, 'function hasFilledPublishedSlot(slots)') !== false, 'Expected published slot helper');
    assert_true(strpos($source, 'function hasPublishableFilledSlot(slots)') !== false, 'Expected combined publish visibility helper');
    assert_true(strpos($source, 'hasPublishableFilledSlot(slots || {})') !== false, 'Expected publish panel to use combined visibility gate');
    assert_true(strpos($source, 'Re-send assignment email only for changed slots or schedule updates.') !== false, 'Expected delta helper copy');
    assert_true(strpos($source, 'No changes to notify') !== false, 'Expected unchanged re-publish success copy');
    assert_true(strpos($source, 'updatePageRow(data)') !== false, 'Expected row refresh path');
});

register_test('23.6 assignment drawer source defaults to slot overview without repeated roster controls', function () {
    $source = file_get_contents(__DIR__ . '/../../public/admin/umpires/assignment-drawer.js');
    assert_true(strpos($source, 'function renderSlotOverview()') !== false, 'Expected slot overview render path');
    assert_true(strpos($source, 'function renderPickerView(slotIndex)') !== false, 'Expected focused picker render path');
    assert_true(strpos($source, "document.createElement('select')") === false, 'Expected no default roster select rendering');
    assert_true(strpos($source, 'function renderRosterLine') === false, 'Expected no repeated full roster line renderer');
    assert_true(strpos($source, 'renderPublishPanel(data.slots || {})') !== false, 'Expected publish panel to remain after overview');
});

register_test('23.6 assignment drawer source supports searchable filtered picker rows', function () {
    $source = file_get_contents(__DIR__ . '/../../public/admin/umpires/assignment-drawer.js');
    foreach ([
        'data-assignment-picker-search',
        'data-picker-result-count',
        'data-picker-results',
        'data-umpire-result-id',
        'filterPickerRoster',
        'low-load',
        'blue-shirt',
        'black-shirt',
        'under-18',
        'Search name, level, phone, or email',
        'Keep typing to narrow results.',
    ] as $token) {
        assert_true(strpos($source, $token) !== false, 'Expected picker token: ' . $token);
    }
    assert_true(strpos($source, 'fullName(umpire),') !== false, 'Expected name search source');
    assert_true(strpos($source, 'umpire.umpire_level') !== false, 'Expected level search/filter source');
    assert_true(strpos($source, 'umpire.phone') !== false, 'Expected phone search source');
    assert_true(strpos($source, 'umpire.email') !== false, 'Expected email search source');
});

register_test('23.6 assignment drawer source excludes other slot selections and refreshes after mutation', function () {
    $source = file_get_contents(__DIR__ . '/../../public/admin/umpires/assignment-drawer.js');
    assert_true(strpos($source, 'otherAssignedUmpireIds(slotIndex, data.slots || {})') !== false, 'Expected picker to exclude opposite slot umpire ids');
    assert_true(strpos($source, 'unavailableIds.indexOf(Number(umpire.id)) === -1') !== false, 'Expected unavailable ids filter');
    assert_true(strpos($source, "requestJson('ajax/get-drawer.php?game_id=' + encodeURIComponent(activeGameId)") !== false, 'Expected drawer refresh after save/unassign');
    assert_true(strpos($source, "postSlot('ajax/save-slot.php'") !== false, 'Expected existing save endpoint');
    assert_true(strpos($source, "postSlot('ajax/unassign-slot.php'") !== false, 'Expected existing unassign endpoint');
    assert_true(strpos($source, 'renderOverrideError(status, error') !== false, 'Expected override error flow preserved');
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
    assert_equals(count($pendingRows), 3, 'Expected cascade rows plus one assignor alert');
    assert_equals($pendingRows[0]['data']['notification_type'] ?? null, 'cascade_cancelled', 'Expected cascade notification type');
    assert_equals($pendingRows[0]['data']['trigger_event_ref'] ?? null, 'SCR-55', 'Expected trigger ref');
    $assignorRows = array_values(array_filter($pendingRows, static function ($row) {
        return ($row['data']['notification_type'] ?? '') === 'assignor_alert';
    }));
    assert_equals(count($assignorRows), 1, 'Expected one assignor alert row');
    assert_equals($assignorRows[0]['data']['trigger_event_ref'] ?? null, 'SCR-55', 'Expected assignor alert trigger ref');
    assert_equals($assignorRows[0]['data']['umpire_user_id'] ?? null, 202, 'Expected anchor published umpire user id');

    $logs = array_values(array_filter($mock->lastParams, static function ($p) {
        return ($p['event'] ?? '') === 'umpire.cascade_cancelled';
    }));
    assert_equals(count($logs), 2, 'Expected one cascade audit event per assignment');
    $context = json_decode($logs[0]['context'], true);
    assert_equals($context['notification_id'] ?? null, 9100, 'Expected notification id in audit context');
    assert_equals($context['trigger_event_ref'] ?? null, 'SCR-55', 'Expected trigger ref in audit context');
    assert_true(!isset($context['email']) && !isset($context['phone']) && !isset($context['first_name']) && !isset($context['last_name']), 'Expected PII-free cascade audit context');

    $alertLogs = array_values(array_filter($mock->lastParams, static function ($p) {
        return ($p['event'] ?? '') === 'umpire.assignor_scr_alert_queued';
    }));
    assert_equals(count($alertLogs), 1, 'Expected one assignor alert audit event');
    $alertContext = json_decode($alertLogs[0]['context'], true);
    assert_equals($alertContext['trigger_event_ref'] ?? null, 'SCR-55', 'Expected assignor alert trigger ref in audit context');
    assert_equals($alertContext['released_assignment_ids'] ?? null, [1011], 'Expected only published assignment ids in audit context');
    assert_equals($alertContext['released_umpire_user_ids'] ?? null, [202], 'Expected only published umpire ids in audit context');
    assert_true(!isset($alertContext['email']) && !isset($alertContext['first_name']) && !isset($alertContext['last_name']), 'Expected PII-free assignor alert audit context');
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

// ---------------------------------------------------------------------------
// Tests: Story 24.6 assignor SCR alert queuing
// ---------------------------------------------------------------------------

register_test('24.6 onScheduleChanged queues one assignor alert for SCR with published releases', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->nextInsertId = 9200;
    $mock->fetchOneRows = [[
        'game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Scheduled',
        'game_date' => '2026-07-08', 'game_time' => '18:00:00',
    ]];
    $mock->queryRows = [[
        ['assignment_id' => 1010, 'game_id' => 10, 'umpire_user_id' => 201, 'slot_index' => 0, 'assignment_status' => 'Published'],
        ['assignment_id' => 1011, 'game_id' => 10, 'umpire_user_id' => 202, 'slot_index' => 1, 'assignment_status' => 'Published'],
    ]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->onScheduleChanged(10, 'SCR-77', ['actor_user_id' => 5]);

    assert_true($result, 'Expected SCR cascade success');
    $pendingRows = array_values(array_filter($mock->insertRows, static function ($row) {
        return ($row['table'] ?? '') === 'umpire_pending_notifications';
    }));
    $cascadeRows = array_values(array_filter($pendingRows, static function ($row) {
        return ($row['data']['notification_type'] ?? '') === 'cascade_cancelled';
    }));
    $assignorRows = array_values(array_filter($pendingRows, static function ($row) {
        return ($row['data']['notification_type'] ?? '') === 'assignor_alert';
    }));
    assert_equals(count($cascadeRows), 2, 'Expected cascade rows for both published assignments');
    assert_equals(count($assignorRows), 1, 'Expected exactly one assignor alert row');
    assert_equals($assignorRows[0]['data']['trigger_event_ref'] ?? null, 'SCR-77', 'Expected SCR trigger ref on assignor alert');
});

register_test('24.6 onScheduleChanged skips assignor alert for draft-only SCR cascade', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [[
        'game_id' => 10, 'game_status' => 'Scheduled',
        'game_date' => '2026-07-01', 'game_time' => '18:00:00',
    ]];
    $mock->queryRows = [[
        ['assignment_id' => 1010, 'game_id' => 10, 'umpire_user_id' => 201, 'slot_index' => 0, 'assignment_status' => 'Draft'],
        ['assignment_id' => 1011, 'game_id' => 10, 'umpire_user_id' => 202, 'slot_index' => 1, 'assignment_status' => 'Draft'],
    ]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->onScheduleChanged(10, 'SCR-88');

    assert_true($result, 'Expected draft-only SCR cascade success');
    $assignorRows = array_values(array_filter($mock->insertRows, static function ($row) {
        return ($row['table'] ?? '') === 'umpire_pending_notifications'
            && ($row['data']['notification_type'] ?? '') === 'assignor_alert';
    }));
    assert_equals(count($assignorRows), 0, 'Expected no assignor alert for draft-only cascade');
});

register_test('24.6 onScheduleChanged skips assignor alert when no assignments are affected', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [[
        'game_id' => 10, 'game_status' => 'Scheduled',
        'game_date' => '2026-07-01', 'game_time' => '18:00:00',
    ]];
    $mock->queryRows = [[]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->onScheduleChanged(10, 'SCR-99');

    assert_true($result, 'Expected no-op SCR cascade success');
    assert_equals(count($mock->insertRows), 0, 'Expected no pending rows');
});

register_test('24.6 onScheduleChanged skips assignor alert for non-SCR triggers', function () {
    foreach (['GAME-CANCELLED-10', 'DIRECT-SCHEDULE-12'] as $triggerRef) {
        $mock = new UmpireAssignmentMockDb();
        $mock->fetchOneRows = [[
            'game_id' => 10, 'game_status' => 'Cancelled',
            'game_date' => '2026-07-01', 'game_time' => '18:00:00',
        ]];
        $mock->queryRows = [[
            ['assignment_id' => 1010, 'game_id' => 10, 'umpire_user_id' => 201, 'slot_index' => 0, 'assignment_status' => 'Published'],
        ]];
        Database::setInstance($mock);
        $svc = new UmpireAssignmentService();

        $result = $svc->onScheduleChanged(10, $triggerRef);
        assert_true($result, 'Expected non-SCR cascade success for ' . $triggerRef);
        $assignorRows = array_values(array_filter($mock->insertRows, static function ($row) {
            return ($row['data']['notification_type'] ?? '') === 'assignor_alert';
        }));
        assert_equals(count($assignorRows), 0, 'Expected no assignor alert for ' . $triggerRef);
        $cascadeRows = array_values(array_filter($mock->insertRows, static function ($row) {
            return ($row['data']['notification_type'] ?? '') === 'cascade_cancelled';
        }));
        assert_equals(count($cascadeRows), 1, 'Expected cascade row for ' . $triggerRef);
    }
});

register_test('24.6 onScheduleChanged returns false when assignor alert insert fails after cascade rows queue', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->throwOnInsertNotificationType = 'assignor_alert';
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

    assert_true(!$result, 'Expected assignor alert insert failure to return false');
    $pendingRows = array_values(array_filter($mock->insertRows, static function ($row) {
        return ($row['table'] ?? '') === 'umpire_pending_notifications';
    }));
    assert_equals(count($pendingRows), 1, 'Expected cascade row queued before assignor alert failure');
    assert_equals($pendingRows[0]['data']['notification_type'] ?? null, 'cascade_cancelled', 'Expected cascade row before assignor alert failure');
});

// ---------------------------------------------------------------------------
// Tests: 24.1 getUmpireAssignments
// ---------------------------------------------------------------------------

register_test('24.1 getUmpireAssignments returns formatted assignment rows', function () {
    $GLOBALS['_test_settings'] = [
        'umpire_slot_1_label' => 'Umpire 1',
        'umpire_slot_2_label' => 'Umpire 2',
    ];
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $mock->queryRows = [
        [
            [
                'game_id' => '10',
                'game_number' => 'G-101',
                'game_date' => '2026-07-15',
                'game_time' => '10:00:00',
                'location_name' => 'Field A',
                'division_name' => 'Intermediate',
                'home_team' => 'Hawks',
                'away_team' => 'Eagles',
                'slot_index' => '0',
                'assigned_by_user_id' => '5',
                'assignor_first_name' => 'Jane',
                'assignor_last_name' => 'Assignor',
                'assignor_email' => 'jane@test.com',
                'assignor_phone' => '555-0100',
                'filled_slots' => '2',
            ],
        ],
    ];
    $svc = new UmpireAssignmentService();
    $result = $svc->getUmpireAssignments(42);

    assert_equals(1, count($result), 'Expected 1 assignment row');
    $row = $result[0];
    assert_equals(10, $row['game_id'], 'Expected game_id');
    assert_equals('G-101', $row['game_number'], 'Expected game_number');
    assert_equals('2026-07-15', $row['game_date'], 'Expected game_date');
    assert_equals('10:00:00', $row['game_time'], 'Expected game_time');
    assert_equals('Field A', $row['location_name'], 'Expected location_name');
    assert_equals('Intermediate', $row['division_name'], 'Expected division_name');
    assert_equals(0, $row['slot_index'], 'Expected slot_index 0');
    assert_equals('Umpire 1', $row['slot_label'], 'Expected slot label from setting');
    assert_equals('$50', $row['fee_text'], 'Expected fee for 2-umpire Intermediate');
    assert_equals('Jane Assignor', $row['assignor_name'], 'Expected assignor name');
    assert_equals('555-0100', $row['assignor_phone'], 'Expected assignor phone');
    assert_equals('tel:5550100', $row['assignor_phone_tel'], 'Expected tel link');
    assert_equals('jane@test.com', $row['assignor_email'], 'Expected assignor email');

    unset($GLOBALS['_test_settings']);
});

register_test('24.1 getUmpireAssignments SQL filters by umpire_user_id and Published status', function () {
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $mock->queryRows = [[]];
    $svc = new UmpireAssignmentService();
    $svc->getUmpireAssignments(42);

    $sql = $mock->lastSql[0] ?? '';
    assert_true(strpos($sql, 'gua.umpire_user_id = :uid') !== false, 'Expected umpire_user_id filter');
    assert_true(strpos($sql, "assignment_status = 'Published'") !== false, 'Expected Published filter');
    $params = $mock->lastParams[0] ?? [];
    assert_equals(42, $params['uid'] ?? null, 'Expected uid param 42');
});

register_test('24.1 getUmpireAssignments counts only distinct Published crew slots for fee', function () {
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $mock->queryRows = [[]];
    $svc = new UmpireAssignmentService();
    $svc->getUmpireAssignments(42);

    $sql = $mock->lastSql[0] ?? '';
    assert_true(strpos($sql, 'COUNT(DISTINCT gua2.slot_index)') !== false, 'Expected distinct slot count');
    assert_true(strpos($sql, "gua2.assignment_status = 'Published'") !== false, 'Expected fee crew count to ignore Draft slots');
});

register_test('24.1 getUmpireAssignments uses a single latest schedule row per game', function () {
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $mock->queryRows = [[]];
    $svc = new UmpireAssignmentService();
    $svc->getUmpireAssignments(42);

    $sql = $mock->lastSql[0] ?? '';
    assert_true(strpos($sql, 'JOIN schedules s ON s.schedule_id = (') !== false, 'Expected schedule join through one selected schedule row');
    assert_true(strpos($sql, 'ORDER BY s2.modified_date DESC, s2.schedule_id DESC') !== false, 'Expected latest schedule selection');
    assert_true(strpos($sql, 'LIMIT 1') !== false, 'Expected single schedule row selection');
});

register_test('24.1 getUmpireAssignments excludes Cancelled and Postponed games', function () {
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $mock->queryRows = [[]];
    $svc = new UmpireAssignmentService();
    $svc->getUmpireAssignments(1);

    $sql = $mock->lastSql[0] ?? '';
    assert_true(strpos($sql, "NOT IN ('Cancelled', 'Postponed')") !== false, 'Expected Cancelled/Postponed exclusion');
});

register_test('24.1 getUmpireAssignments orders by game_date ASC, game_time ASC', function () {
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $mock->queryRows = [[]];
    $svc = new UmpireAssignmentService();
    $svc->getUmpireAssignments(1);

    $sql = $mock->lastSql[0] ?? '';
    assert_true(strpos($sql, 'ORDER BY s.game_date ASC, s.game_time ASC') !== false, 'Expected date/time ordering');
});

register_test('24.1 getUmpireAssignments returns empty array when no results', function () {
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $mock->queryRows = [[]];
    $svc = new UmpireAssignmentService();
    $result = $svc->getUmpireAssignments(999);
    assert_equals([], $result, 'Expected empty array');
});

register_test('24.1 getUmpireAssignments fee uses division fallback text', function () {
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $mock->queryRows = [
        [
            [
                'game_id' => '11',
                'game_date' => '2026-07-20',
                'game_time' => '14:00:00',
                'location_name' => 'Field B',
                'division_name' => 'Unknown Division',
                'home_team' => 'Titans',
                'away_team' => 'Spartans',
                'slot_index' => '1',
                'assigned_by_user_id' => '5',
                'assignor_first_name' => null,
                'assignor_last_name' => null,
                'assignor_email' => null,
                'assignor_phone' => null,
                'filled_slots' => '1',
            ],
        ],
    ];
    $svc = new UmpireAssignmentService();
    $result = $svc->getUmpireAssignments(1);
    assert_equals(1, count($result), 'Expected 1 row');
    assert_equals('Confirm fee with assignor', $result[0]['fee_text'], 'Expected fallback fee text');
    assert_equals('Contact your assignor', $result[0]['assignor_name'], 'Expected fallback assignor name');
    assert_equals('', $result[0]['assignor_phone'], 'Expected empty phone fallback');
    assert_equals('', $result[0]['assignor_email'], 'Expected empty email fallback');
});

register_test('24.1 umpire portal source denies missing session user id and formats invalid dates as TBD', function () {
    $source = file_get_contents(__DIR__ . '/../../public/umpires/index.php');
    assert_true(strpos($source, '$userId <= 0') !== false, 'Expected missing users-table id guard');
    assert_true(strpos($source, "header('Location: /login.php')") !== false, 'Expected invalid session redirect');
    assert_true(strpos($source, 'function umpirePortalFormatDate') !== false, 'Expected safe date formatter');
    assert_true(strpos($source, 'function umpirePortalFormatTime') !== false, 'Expected safe time formatter');
    assert_true(strpos($source, "return \$ts !== false ? date('g:i A', \$ts) : 'TBD';") !== false, 'Expected invalid time fallback');
});

register_test('24.1 umpire logout rejects non-POST before CSRF logout', function () {
    $source = file_get_contents(__DIR__ . '/../../public/umpires/logout.php');
    assert_true(strpos($source, "\$_SERVER['REQUEST_METHOD'] !== 'POST'") !== false, 'Expected POST-only method guard');
    assert_true(strpos($source, "header('Allow: POST')") !== false, 'Expected Allow header for rejected methods');
    assert_true(strpos($source, 'http_response_code(405)') !== false, 'Expected 405 for non-POST logout');
    assert_true(strpos($source, 'Auth::verifyCSRFToken($token)') !== false, 'Expected POST CSRF validation');
});

// ---------------------------------------------------------------------------
// Tests: Story 24.2 umpire decline workflow
// ---------------------------------------------------------------------------

register_test('24.2 declineAssignment marks Published assignment Declined, clears notification state, emails assignor, and logs hours', function () {
    $GLOBALS['_test_settings'] = [
        'umpire_decline_lockout_hours' => '48',
        'umpire_slot_1_label' => 'Plate',
    ];
    $futureDate = date('Y-m-d', strtotime('+7 days'));
    $mock = new UmpireAssignmentMockDb();
    $mock->nextInsertId = 9300;
    $mock->fetchOneRows = [
        [
            'assignment_id' => '501',
            'game_id' => '77',
            'umpire_user_id' => '42',
            'slot_index' => '0',
            'assignment_status' => 'Published',
            'published' => '1',
            'assigned_by_user_id' => '5',
            'game_number' => 'G077',
            'game_status' => 'Scheduled',
            'division_name' => 'Junior',
            'home_team' => 'Hawks',
            'away_team' => 'Eagles',
            'game_date' => $futureDate,
            'game_time' => '18:30:00',
            'location_name' => 'Field A',
            'umpire_first_name' => 'Pat',
            'umpire_last_name' => 'Blue',
            'umpire_email' => 'pat@example.test',
            'assignor_first_name' => 'Alex',
            'assignor_last_name' => 'Assignor',
            'assignor_email' => 'assignor@example.test',
            'assignor_phone' => '555-0100',
            'filled_slots' => '2',
        ],
        [
            'template_name' => 'umpire_decline_alert',
            'subject_template' => 'Declined {game_date} {game_time} {slot_label}',
            'body_template' => '{umpire_name} declined {division_name} at {location}; {hours_until_game_start} hours remain.',
            'is_active' => 1,
        ],
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->declineAssignment(501, 42);

    assert_equals($result['assignment_id'], 501, 'Expected returned assignment id');
    assert_equals($result['assignment_status'], 'Declined', 'Expected declined status in result');
    assert_true(($result['hours_until_game_start'] ?? 0) > 48, 'Expected outside lockout window');
    assert_equals($result['assignor']['name'] ?? null, 'Alex Assignor', 'Expected assignor contact in result');

    $sqlLog = implode("\n", $mock->lastSql);
    assert_true(strpos($sqlLog, "assignment_status = 'Declined'") !== false, 'Expected Declined update');
    assert_true(strpos($sqlLog, 'published = 0') !== false, 'Expected published cleared');
    assert_true(strpos($sqlLog, 'last_notified_at = NULL') !== false, 'Expected notification timestamp cleared');
    assert_true(strpos($sqlLog, 'last_notified_hash = NULL') !== false, 'Expected notification hash cleared');

    $queue = $mock->insertRows[0] ?? null;
    assert_equals($queue['table'] ?? null, 'email_queue', 'Expected email_queue insert');
    assert_equals($queue['data']['template_name'] ?? null, 'umpire_decline_alert', 'Expected decline template');
    assert_equals($queue['data']['reply_to_email'] ?? null, 'assignor@example.test', 'Expected Reply-To assignor email');
    assert_equals($queue['data']['reply_to_name'] ?? null, 'Alex Assignor', 'Expected Reply-To assignor name');
    assert_true(strpos($queue['data']['body'] ?? '', 'Pat Blue') !== false, 'Expected umpire name in body');
    assert_true(strpos($queue['data']['body'] ?? '', 'Field A') !== false, 'Expected field in body');

    $logs = array_values(array_filter($mock->lastParams, static function ($p) {
        return ($p['event'] ?? '') === 'umpire.declined';
    }));
    assert_equals(count($logs), 1, 'Expected one decline audit event');
    $context = json_decode($logs[0]['context'], true);
    assert_equals($context['assignment_id'] ?? null, 501, 'Expected assignment id in audit context');
    assert_equals($context['game_id'] ?? null, 77, 'Expected game id in audit context');
    assert_equals($context['slot_index'] ?? null, 0, 'Expected slot index in audit context');
    assert_equals($context['umpire_user_id'] ?? null, 42, 'Expected umpire id in audit context');
    assert_true(($context['hours_until_game_start'] ?? 0) > 48, 'Expected hours in audit context');
    assert_true(!isset($context['email']) && !isset($context['phone']) && !isset($context['first_name']) && !isset($context['last_name']), 'Expected PII-free decline audit context');

    unset($GLOBALS['_test_settings']);
});

register_test('24.2 declineAssignment rejects assignments inside configured lockout with structured payload', function () {
    $GLOBALS['_test_settings']['umpire_decline_lockout_hours'] = '48';
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [[
        'assignment_id' => '502',
        'game_id' => '78',
        'umpire_user_id' => '42',
        'slot_index' => '1',
        'assignment_status' => 'Published',
        'published' => '1',
        'assigned_by_user_id' => '5',
        'game_status' => 'Scheduled',
        'game_date' => date('Y-m-d', strtotime('+12 hours')),
        'game_time' => date('H:i:s', strtotime('+12 hours')),
        'location_name' => 'Field B',
        'division_name' => 'Intermediate',
        'umpire_first_name' => 'Pat',
        'umpire_last_name' => 'Blue',
        'assignor_first_name' => 'Alex',
        'assignor_last_name' => 'Assignor',
        'assignor_email' => 'assignor@example.test',
        'assignor_phone' => '555-0100',
        'filled_slots' => '1',
    ]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();

    $threw = false;
    try {
        $svc->declineAssignment(502, 42);
    } catch (\RuntimeException $e) {
        $threw = true;
        assert_equals($e->getCode(), 409, 'Expected HTTP 409 code');
        assert_true(method_exists($e, 'getPayload'), 'Expected structured lockout payload');
        $payload = $e->getPayload();
        assert_equals($payload['lockout_hours'] ?? null, 48, 'Expected configured lockout hours');
        assert_true(($payload['hours_until_game_start'] ?? 99) <= 48, 'Expected hours until game in payload');
        assert_true(strpos($payload['assignor_contact'] ?? '', 'assignor@example.test') !== false, 'Expected assignor contact in payload');
    }
    assert_true($threw, 'Expected lockout exception');
    assert_true(strpos(implode("\n", $mock->lastSql), "assignment_status = 'Declined'") === false, 'Expected no decline update during lockout');
    unset($GLOBALS['_test_settings']['umpire_decline_lockout_hours']);
});

register_test('24.2 declineAssignment rejects wrong umpire and non-Published assignment', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [[
        'assignment_id' => '503',
        'game_id' => '79',
        'umpire_user_id' => '99',
        'slot_index' => '0',
        'assignment_status' => 'Published',
        'published' => '1',
        'game_status' => 'Scheduled',
        'game_date' => date('Y-m-d', strtotime('+7 days')),
        'game_time' => '18:00:00',
    ]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $threw = false;
    try {
        $svc->declineAssignment(503, 42);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
    }
    assert_true($threw, 'Expected wrong umpire to reject');

    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [[
        'assignment_id' => '504',
        'game_id' => '80',
        'umpire_user_id' => '42',
        'slot_index' => '0',
        'assignment_status' => 'Draft',
        'published' => '0',
        'game_status' => 'Scheduled',
        'game_date' => date('Y-m-d', strtotime('+7 days')),
        'game_time' => '18:00:00',
    ]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $threw = false;
    try {
        $svc->declineAssignment(504, 42);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
    }
    assert_true($threw, 'Expected non-Published assignment to reject');
});

register_test('24.2 getUmpireAssignments returns assignment id and decline availability metadata', function () {
    $GLOBALS['_test_settings']['umpire_decline_lockout_hours'] = '48';
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $mock->queryRows = [[
        [
            'assignment_id' => '501',
            'game_id' => '10',
            'game_number' => 'G-101',
            'game_date' => date('Y-m-d', strtotime('+7 days')),
            'game_time' => '10:00:00',
            'location_name' => 'Field A',
            'division_name' => 'Intermediate',
            'home_team' => 'Hawks',
            'away_team' => 'Eagles',
            'slot_index' => '0',
            'assigned_by_user_id' => '5',
            'assignor_first_name' => 'Jane',
            'assignor_last_name' => 'Assignor',
            'assignor_email' => 'jane@test.com',
            'assignor_phone' => '555-0100',
            'filled_slots' => '2',
        ],
    ]];
    $svc = new UmpireAssignmentService();
    $result = $svc->getUmpireAssignments(42);

    assert_equals($result[0]['assignment_id'] ?? null, 501, 'Expected assignment id for decline link');
    assert_true($result[0]['decline_allowed'] ?? false, 'Expected decline outside lockout');
    assert_equals($result[0]['decline_lockout_hours'] ?? null, 48, 'Expected lockout metadata');
    assert_true(($result[0]['hours_until_game_start'] ?? 0) > 48, 'Expected computed hours metadata');
    unset($GLOBALS['_test_settings']['umpire_decline_lockout_hours']);
});

register_test('24.2 decline page source has role gate, CSRF 403, accessible button, lockout copy, and 44px touch target', function () {
    $source = file_get_contents(__DIR__ . '/../../public/umpires/decline.php');
    assert_true(strpos($source, "PermissionGuard::requireRole('umpire', '/login.php')") !== false, 'Expected umpire role gate');
    assert_true(strpos($source, "Auth::verifyCSRFToken(\$_POST['csrf_token'] ?? '')") !== false, 'Expected CSRF verification');
    assert_true(strpos($source, 'http_response_code(403)') !== false, 'Expected invalid CSRF 403');
    assert_true(strpos($source, 'name="assignment_id"') !== false, 'Expected assignment_id form input');
    assert_true(strpos($source, '<button') !== false && strpos($source, 'type="submit"') !== false, 'Expected native submit button');
    assert_true(strpos($source, 'min-height: 44px') !== false, 'Expected 44px touch target CSS');
    assert_true(strpos($source, 'Decline not available within') !== false, 'Expected lockout message');
    assert_true(strpos($source, 'assignor_contact') !== false, 'Expected assignor contact rendering');
});

register_test('24.1 getUmpireAssignmentsGrouped buckets assignments into today, future, past', function () {
    $today = date('Y-m-d');
    $future = date('Y-m-d', strtotime('+5 days'));
    $past = date('Y-m-d', strtotime('-5 days'));
    $GLOBALS['_test_settings']['umpire_decline_lockout_hours'] = '48';
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $mock->rows = [
        [
            'assignment_id' => '1',
            'game_id' => '10',
            'game_date' => $today,
            'game_time' => '10:00:00',
            'location_name' => 'Field A',
            'division_name' => 'Intermediate',
            'slot_index' => '0',
            'assigned_by_user_id' => '5',
            'assignor_first_name' => 'Jane',
            'assignor_last_name' => 'Assignor',
            'assignor_email' => 'jane@test.com',
            'assignor_phone' => '555-0100',
            'filled_slots' => '2',
            'home_team' => 'A',
            'away_team' => 'B',
        ],
        [
            'assignment_id' => '2',
            'game_id' => '20',
            'game_date' => $future,
            'game_time' => '14:00:00',
            'location_name' => 'Field B',
            'division_name' => 'Junior',
            'slot_index' => '1',
            'assigned_by_user_id' => '5',
            'assignor_first_name' => 'Jane',
            'assignor_last_name' => 'Assignor',
            'assignor_email' => 'jane@test.com',
            'assignor_phone' => '555-0100',
            'filled_slots' => '2',
            'home_team' => 'C',
            'away_team' => 'D',
        ],
        [
            'assignment_id' => '3',
            'game_id' => '30',
            'game_date' => $past,
            'game_time' => '09:00:00',
            'location_name' => 'Field C',
            'division_name' => 'Senior',
            'slot_index' => '0',
            'assigned_by_user_id' => '5',
            'assignor_first_name' => 'Jane',
            'assignor_last_name' => 'Assignor',
            'assignor_email' => 'jane@test.com',
            'assignor_phone' => '555-0100',
            'filled_slots' => '2',
            'home_team' => 'E',
            'away_team' => 'F',
        ],
    ];
    $svc = new UmpireAssignmentService();
    $result = $svc->getUmpireAssignmentsGrouped(42);

    assert_true(isset($result['today'], $result['future'], $result['past']), 'Expected all three buckets');
    assert_equals(count($result['today']), 1, 'Expected 1 today assignment');
    assert_equals(count($result['future']), 1, 'Expected 1 future assignment');
    assert_equals(count($result['past']), 1, 'Expected 1 past assignment');
    assert_equals((int) $result['today'][0]['assignment_id'], 1, 'Expected today assignment id 1');
    assert_equals((int) $result['future'][0]['assignment_id'], 2, 'Expected future assignment id 2');
    assert_equals((int) $result['past'][0]['assignment_id'], 3, 'Expected past assignment id 3');
    unset($GLOBALS['_test_settings']['umpire_decline_lockout_hours']);
});

register_test('24.1 getUmpireAssignmentsGrouped returns empty buckets when no assignments', function () {
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $mock->rows = [];
    $svc = new UmpireAssignmentService();
    $result = $svc->getUmpireAssignmentsGrouped(42);

    assert_equals(count($result['today']), 0, 'Expected empty today');
    assert_equals(count($result['future']), 0, 'Expected empty future');
    assert_equals(count($result['past']), 0, 'Expected empty past');
});

register_test('24.1 getUmpireAssignmentsGrouped past bucket is reversed (DESC order)', function () {
    $today = date('Y-m-d');
    $past1 = date('Y-m-d', strtotime('-10 days'));
    $past2 = date('Y-m-d', strtotime('-5 days'));
    $GLOBALS['_test_settings']['umpire_decline_lockout_hours'] = '48';
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $mock->rows = [
        [
            'assignment_id' => '1',
            'game_id' => '10',
            'game_date' => $past1,
            'game_time' => '10:00:00',
            'location_name' => 'Field A',
            'division_name' => 'Intermediate',
            'slot_index' => '0',
            'assigned_by_user_id' => '5',
            'assignor_first_name' => 'Jane',
            'assignor_last_name' => 'Assignor',
            'assignor_email' => 'jane@test.com',
            'assignor_phone' => '555-0100',
            'filled_slots' => '2',
            'home_team' => 'A',
            'away_team' => 'B',
        ],
        [
            'assignment_id' => '2',
            'game_id' => '20',
            'game_date' => $past2,
            'game_time' => '14:00:00',
            'location_name' => 'Field B',
            'division_name' => 'Junior',
            'slot_index' => '1',
            'assigned_by_user_id' => '5',
            'assignor_first_name' => 'Jane',
            'assignor_last_name' => 'Assignor',
            'assignor_email' => 'jane@test.com',
            'assignor_phone' => '555-0100',
            'filled_slots' => '2',
            'home_team' => 'C',
            'away_team' => 'D',
        ],
    ];
    $svc = new UmpireAssignmentService();
    $result = $svc->getUmpireAssignmentsGrouped(42);

    assert_equals(count($result['past']), 2, 'Expected 2 past assignments');
    assert_equals((int) $result['past'][0]['assignment_id'], 2, 'Expected most recent past first (DESC)');
    assert_equals((int) $result['past'][1]['assignment_id'], 1, 'Expected older past second');
    assert_equals(count($result['today']), 0, 'Expected no today');
    assert_equals(count($result['future']), 0, 'Expected no future');
    unset($GLOBALS['_test_settings']['umpire_decline_lockout_hours']);
});

register_test('24.1 getUmpireDeclineLog returns decline entries from activity_log', function () {
    $today = date('Y-m-d');
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $mock->rows = [
        [
            'log_id' => '100',
            'declined_at' => '2026-06-20 14:30:00',
            'hours_until_game_start' => '72.5',
            'ctx_slot_index' => '1',
            'game_id' => '10',
            'game_number' => 'G-101',
            'game_date' => $today,
            'game_time' => '10:00:00',
            'location_name' => 'Field A',
            'division_name' => 'Intermediate',
        ],
    ];
    $svc = new UmpireAssignmentService();
    $result = $svc->getUmpireDeclineLog(42);

    assert_equals(count($result), 1, 'Expected 1 decline entry');
    assert_equals((int) $result[0]['log_id'], 100, 'Expected log_id 100');
    assert_equals($result[0]['game_date'], $today, 'Expected game date');
    assert_equals($result[0]['slot_label'], 'Umpire 2', 'Expected slot 2 label');
    assert_equals($result[0]['hours_until_game_start'], 72.5, 'Expected hours until game start');
});

register_test('24.1 getUmpireDeclineLog returns empty array when no declines', function () {
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $mock->rows = [];
    $svc = new UmpireAssignmentService();
    $result = $svc->getUmpireDeclineLog(999);

    assert_equals(count($result), 0, 'Expected empty decline log');
});

register_test('24.1 getUmpireDeclineLog SQL filters by event and umpire_user_id', function () {
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $mock->rows = [];
    $svc = new UmpireAssignmentService();
    $svc->getUmpireDeclineLog(42);

    $sql = $mock->lastSql[0] ?? '';
    assert_true(strpos($sql, "event = 'umpire.declined'") !== false, 'Expected event filter');
    assert_true(strpos($sql, 'umpire_user_id') !== false, 'Expected umpire_user_id filter');
    assert_true(in_array(42, $mock->lastParams[0] ?? [], true)
        || strpos(implode(' ', $mock->lastParams[0] ?? []), '42') !== false, 'Expected uid param 42');
});

register_test('24.2 migration seeds active decline alert template body', function () {
    $source = file_get_contents(__DIR__ . '/../../database/migrations/049_update_umpire_decline_alert_template.sql');
    $line = '';
    foreach (explode("\n", $source) as $candidate) {
        if (strpos($candidate, "'umpire_decline_alert'") !== false) {
            $line = $candidate;
            break;
        }
    }
    assert_true($line !== '', 'Expected decline alert template seed');
    assert_true(strpos($line, '[Stub') === false, 'Expected real decline alert template body, not stub');
    assert_true(strpos($source, '{umpire_name}') !== false, 'Expected umpire_name token');
    assert_true(strpos($source, '{hours_until_game_start}') !== false, 'Expected hours token');
    assert_true(strpos($source, 'is_active = VALUES(is_active)') !== false, 'Expected duplicate update to activate template');
    assert_true(strpos($source, "INSERT IGNORE INTO schema_migrations (version) VALUES ('049')") !== false, 'Expected migration tracking row');
});

// ---------------------------------------------------------------------------
// Tests: Story 24.3 configurable slot labels
// ---------------------------------------------------------------------------

register_test('24.3 getSlotLabels returns defaults when settings are absent', function () {
    unset($GLOBALS['_test_settings']['umpire_slot_1_label'], $GLOBALS['_test_settings']['umpire_slot_2_label']);
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $labels = $svc->getSlotLabels();

    assert_equals($labels[0], 'Umpire 1', 'Expected default slot 1 label');
    assert_equals($labels[1], 'Umpire 2', 'Expected default slot 2 label');
});

register_test('24.3 getSlotLabels falls back when persisted labels are blank', function () {
    $GLOBALS['_test_settings'] = [
        'umpire_slot_1_label' => '   ',
        'umpire_slot_2_label' => '',
    ];
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $labels = $svc->getSlotLabels();

    assert_equals($labels[0], 'Umpire 1', 'Expected blank slot 1 setting to fall back');
    assert_equals($labels[1], 'Umpire 2', 'Expected blank slot 2 setting to fall back');
    unset($GLOBALS['_test_settings']);
});

register_test('24.3 saveSlotLabels rejects empty labels', function () {
    $mock = new UmpireAssignmentMockDb();
    umpire_assignment_seed_settings_actor($mock);
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $threw = false;
    try {
        $svc->saveSlotLabels('Plate', '   ', 1);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
    }
    assert_true($threw, 'Expected InvalidArgumentException for empty slot 2 label');
});

register_test('24.3 saveSlotLabels rejects labels over 64 characters', function () {
    $mock = new UmpireAssignmentMockDb();
    umpire_assignment_seed_settings_actor($mock);
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $threw = false;
    try {
        $svc->saveSlotLabels(str_repeat('A', 65), 'Bases', 1);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
    }
    assert_true($threw, 'Expected InvalidArgumentException for over-length slot label');
});

register_test('24.3 saveSlotLabels accepts 64 multibyte characters', function () {
    $GLOBALS['__updateSetting_calls'] = [];
    $GLOBALS['_test_settings'] = [
        'umpire_slot_1_label' => 'Umpire 1',
        'umpire_slot_2_label' => 'Umpire 2',
    ];
    $mock = new UmpireAssignmentMockDb();
    umpire_assignment_seed_settings_actor($mock);
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $svc->saveSlotLabels(str_repeat('é', 64), 'Bases', 1);

    $values = array_column($GLOBALS['__updateSetting_calls'], 'value', 'key');
    assert_equals($values['umpire_slot_1_label'] ?? null, str_repeat('é', 64), 'Expected 64 multibyte characters accepted');
    unset($GLOBALS['_test_settings']);
});

register_test('24.3 saveSlotLabels writes both setting keys for authorized actor', function () {
    $GLOBALS['__updateSetting_calls'] = [];
    $GLOBALS['_test_settings'] = [
        'umpire_slot_1_label' => 'Umpire 1',
        'umpire_slot_2_label' => 'Umpire 2',
    ];
    $mock = new UmpireAssignmentMockDb();
    umpire_assignment_seed_settings_actor($mock);
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $svc->saveSlotLabels('Plate', 'Bases', 1);

    $keys = array_column($GLOBALS['__updateSetting_calls'], 'key');
    assert_true(in_array('umpire_slot_1_label', $keys, true), 'Expected slot 1 setting write');
    assert_true(in_array('umpire_slot_2_label', $keys, true), 'Expected slot 2 setting write');
    $values = array_column($GLOBALS['__updateSetting_calls'], 'value', 'key');
    assert_equals($values['umpire_slot_1_label'] ?? null, 'Plate', 'Expected slot 1 value persisted');
    assert_equals($values['umpire_slot_2_label'] ?? null, 'Bases', 'Expected slot 2 value persisted');
    unset($GLOBALS['_test_settings']);
});

register_test('24.3 saveSlotLabels rolls back if second setting write fails', function () {
    $GLOBALS['__updateSetting_calls'] = [];
    $GLOBALS['__updateSetting_throw_on_key'] = 'umpire_slot_2_label';
    $GLOBALS['_test_settings'] = [
        'umpire_slot_1_label' => 'Umpire 1',
        'umpire_slot_2_label' => 'Umpire 2',
    ];
    $mock = new UmpireAssignmentMockDb();
    umpire_assignment_seed_settings_actor($mock);
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $threw = false;
    try {
        $svc->saveSlotLabels('Plate', 'Bases', 1);
    } catch (\RuntimeException $e) {
        $threw = true;
    }

    assert_true($threw, 'Expected failed second setting write to throw');
    assert_true($mock->transactionStarted, 'Expected transaction to start before label writes');
    assert_true($mock->transactionRolledBack, 'Expected transaction rollback on failed label write');
    unset($GLOBALS['__updateSetting_throw_on_key'], $GLOBALS['_test_settings']);
});

register_test('24.3 saveSlotLabels denies unauthorized actor at service layer', function () {
    $mock = new UmpireAssignmentMockDb();
    umpire_assignment_seed_settings_actor($mock, 'umpire');
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $threw = false;
    try {
        $svc->saveSlotLabels('Plate', 'Bases', 42);
    } catch (\RuntimeException $e) {
        $threw = true;
    }
    assert_true($threw, 'Expected RuntimeException for unauthorized settings write');
});

register_test('24.3 saveQueueWindowDays denies unauthorized actor at service layer', function () {
    $mock = new UmpireAssignmentMockDb();
    umpire_assignment_seed_settings_actor($mock, 'umpire');
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $threw = false;
    try {
        $svc->saveQueueWindowDays(14, 42);
    } catch (\RuntimeException $e) {
        $threw = true;
    }
    assert_true($threw, 'Expected RuntimeException for unauthorized queue-window settings write');
});

register_test('24.3 saveDeclineLockoutHours denies unauthorized actor at service layer', function () {
    $mock = new UmpireAssignmentMockDb();
    umpire_assignment_seed_settings_actor($mock, 'umpire');
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $threw = false;
    try {
        $svc->saveDeclineLockoutHours(48, 42);
    } catch (\RuntimeException $e) {
        $threw = true;
    }
    assert_true($threw, 'Expected RuntimeException for unauthorized decline-lockout settings write');
});

register_test('24.3 getUmpireAssignments uses customized slot labels via getSlotLabels', function () {
    $GLOBALS['_test_settings'] = [
        'umpire_slot_1_label' => 'Plate',
        'umpire_slot_2_label' => 'Bases',
    ];
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $mock->queryRows = [[
        [
            'game_id' => '10',
            'game_number' => 'G-101',
            'game_date' => '2026-07-15',
            'game_time' => '10:00:00',
            'location_name' => 'Field A',
            'division_name' => 'Intermediate',
            'home_team' => 'Hawks',
            'away_team' => 'Eagles',
            'slot_index' => '0',
            'assigned_by_user_id' => '5',
            'assignor_first_name' => 'Jane',
            'assignor_last_name' => 'Assignor',
            'assignor_email' => 'jane@test.com',
            'assignor_phone' => '555-0100',
            'filled_slots' => '2',
        ],
    ]];
    $svc = new UmpireAssignmentService();
    $result = $svc->getUmpireAssignments(42);

    assert_equals($result[0]['slot_label'], 'Plate', 'Expected customized portal slot label');
    unset($GLOBALS['_test_settings']);
});

register_test('24.3 getUmpireDeclineLog uses customized slot labels via getSlotLabels', function () {
    $GLOBALS['_test_settings'] = [
        'umpire_slot_1_label' => 'Plate',
        'umpire_slot_2_label' => 'Bases',
    ];
    $mock = new UmpireAssignmentMockDb();
    Database::setInstance($mock);
    $mock->rows = [[
        'log_id' => '100',
        'declined_at' => '2026-06-20 14:30:00',
        'hours_until_game_start' => '72.5',
        'ctx_slot_index' => '1',
        'game_id' => '10',
        'game_number' => 'G-101',
        'game_date' => date('Y-m-d'),
        'game_time' => '10:00:00',
        'location_name' => 'Field A',
        'division_name' => 'Intermediate',
    ]];
    $svc = new UmpireAssignmentService();
    $result = $svc->getUmpireDeclineLog(42);

    assert_equals($result[0]['slot_label'], 'Bases', 'Expected customized decline log slot label');
    unset($GLOBALS['_test_settings']);
});

register_test('24.3 publishGame email context uses customized slot labels via getSlotLabels', function () {
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
    $svc->publishGame(10, 5, null, true);

    $queue = $mock->insertRows[0] ?? null;
    assert_true(strpos($queue['data']['body'] ?? '', 'Plate') !== false, 'Expected customized slot label in publish email body');
    unset($GLOBALS['_test_settings']['umpire_slot_1_label'], $GLOBALS['_test_settings']['umpire_slot_2_label']);
});

register_test('24.3 umpire settings source exposes settings POST actions with CSRF and input names', function () {
    $source = file_get_contents(__DIR__ . '/../../public/admin/umpires/settings.php');
    assert_true(strpos($source, "PermissionGuard::requireRole(['admin', 'umpire_assignor'], '/login.php')") !== false, 'Expected role gate');
    assert_true(strpos($source, "'save_settings'") !== false, 'Expected save_settings action');
    assert_true(strpos($source, "'save_decline_lockout'") !== false, 'Expected save_decline_lockout action');
    assert_true(strpos($source, "'save_slot_labels'") !== false, 'Expected save_slot_labels action');
    assert_true(strpos($source, 'Auth::verifyCSRFToken($_POST[\'csrf_token\']') !== false, 'Expected CSRF guard on slot label save');
    assert_true(strpos($source, 'name="unassigned_queue_days"') !== false, 'Expected queue window input name');
    assert_true(strpos($source, 'name="decline_lockout_hours"') !== false, 'Expected decline lockout input name');
    assert_true(strpos($source, 'name="umpire_slot_1_label"') !== false, 'Expected slot 1 input name');
    assert_true(strpos($source, 'name="umpire_slot_2_label"') !== false, 'Expected slot 2 input name');
    assert_true(strpos($source, 'Assignment Settings') !== false, 'Expected inclusive settings card heading');
    assert_true(strpos($source, 'saveQueueWindowDays') !== false, 'Expected queue window save path');
    assert_true(strpos($source, 'saveDeclineLockoutHours') !== false, 'Expected decline lockout save path preserved');
});

register_test('24.3 assignment queue source no longer owns settings forms', function () {
    $source = file_get_contents(__DIR__ . '/../../public/admin/umpires/index.php');
    assert_true(strpos($source, "'save_settings'") === false, 'Queue page should not handle settings saves');
    assert_true(strpos($source, "'save_decline_lockout'") === false, 'Queue page should not handle decline lockout saves');
    assert_true(strpos($source, "'save_slot_labels'") === false, 'Queue page should not handle slot label saves');
    assert_true(strpos($source, 'name="unassigned_queue_days"') === false, 'Queue page should not render queue window settings input');
    assert_true(strpos($source, 'name="decline_lockout_hours"') === false, 'Queue page should not render decline lockout settings input');
    assert_true(strpos($source, 'name="umpire_slot_1_label"') === false, 'Queue page should not render slot label settings input');
    assert_true(strpos($source, 'href="settings.php"') !== false, 'Queue page should link to the dedicated settings page');
});

register_test('24.3 navigation exposes umpire settings for admin and assignor menus', function () {
    $source = file_get_contents(__DIR__ . '/../../includes/nav.php');
    assert_true(substr_count($source, 'admin/umpires/settings.php') >= 2, 'Expected settings link in admin and assignor menus');
    assert_true(substr_count($source, "isActiveNav('settings', 'umpires')") >= 2, 'Expected active nav handling for settings links');
    assert_true(substr_count($source, 'Umpire Settings') >= 2, 'Expected visible Umpire Settings labels');
});

register_test('24.3 assignment board source renders configured slot labels in headers', function () {
    $source = file_get_contents(__DIR__ . '/../../public/admin/umpires/board.php');
    assert_true(strpos($source, 'getSlotLabels()') !== false, 'Expected configured slot labels from service');
    assert_true(strpos($source, 'htmlspecialchars($slotLabels[0]') !== false, 'Expected escaped slot 1 header');
    assert_true(strpos($source, 'htmlspecialchars($slotLabels[1]') !== false, 'Expected escaped slot 2 header');
    assert_true(strpos($source, 'data-board-slot') !== false, 'Expected board slot behavior preserved');
});

// ---------------------------------------------------------------------------
// Tests: Story 24.5 assignment quality warnings
// ---------------------------------------------------------------------------

register_test('24.5 collectAssignmentQualityWarnings returns dual under-18 warning', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Scheduled', 'game_date' => '2026-07-01', 'game_time' => '18:00:00', 'location_id' => 1, 'location_name' => 'Field 1', 'pending_scr_count' => 0],
        ['is_under_18' => 1, 'date_of_birth' => '2010-01-01'],
        ['is_under_18' => 1, 'date_of_birth' => '2011-01-01'],
    ];
    $mock->queryRows = [
        [
            ['slot_index' => 0, 'umpire_user_id' => 201],
            ['slot_index' => 1, 'umpire_user_id' => 202],
        ],
        [],
        [],
        [
            ['umpire_user_id' => 201],
            ['umpire_user_id' => 202],
        ],
        [
            ['slot_index' => 0, 'is_under_18' => 1],
            ['slot_index' => 1, 'is_under_18' => 1],
        ],
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $warnings = $svc->collectAssignmentQualityWarnings(10);

    assert_equals(count($warnings), 1, 'Expected one dual under-18 warning');
    assert_equals($warnings[0]['type'] ?? null, 'dual_under_18', 'Expected dual under-18 warning type');
    assert_equals(
        $warnings[0]['message'] ?? null,
        'Both assigned umpires are under 18 - a supervising adult may be required',
        'Expected dual under-18 message'
    );
});

register_test('24.5 collectAssignmentQualityWarnings omits travel warning at exact 45-minute boundary', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Scheduled', 'game_date' => '2026-07-01', 'game_time' => '18:45:00', 'location_id' => 2, 'location_name' => 'Field 2', 'pending_scr_count' => 0],
        ['is_under_18' => 0, 'date_of_birth' => null],
    ];
    $mock->queryRows = [
        [
            ['slot_index' => 0, 'umpire_user_id' => 201],
        ],
        [],
        [
            ['umpire_user_id' => 201],
        ],
        [],
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $warnings = $svc->collectAssignmentQualityWarnings(10);

    assert_equals(count($warnings), 0, 'Expected no travel warning at exact 45-minute boundary');
});

register_test('24.5 getGameAssignmentDrawer includes warnings and pending SCR metadata', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Scheduled', 'division_name' => 'Junior', 'home_team' => 'Home', 'away_team' => 'Away', 'game_date' => '2026-07-01', 'game_time' => '18:00:00', 'location_id' => 1, 'location_name' => 'Field 1', 'pending_scr_count' => 2],
        ['program_id' => 0],
        ['id' => 7],
    ];
    $mock->queryRows = [
        [[
            'assignment_id' => 1010, 'game_id' => 10, 'umpire_user_id' => 201, 'slot_index' => 0,
            'assignment_status' => 'Draft', 'published' => 0, 'migration_mode' => 0,
            'first_name' => 'Pat', 'last_name' => 'Blue', 'email' => 'pat@example.test', 'phone' => '555',
            'umpire_level' => 'Black Shirt', 'is_under_18' => 0,
        ]],
        [['id' => 201, 'first_name' => 'Pat', 'last_name' => 'Blue', 'email' => 'pat@example.test', 'phone' => '555', 'status' => 'active', 'umpire_level' => 'Black Shirt', 'is_under_18' => 0, 'date_of_birth' => null]],
        [],
        [],
        [],
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->getGameAssignmentDrawer(10);

    assert_true(isset($result['warnings']), 'Expected warnings array in drawer payload');
    assert_true($result['game']['has_pending_scr'] ?? false, 'Expected pending SCR flag on game');
    assert_equals($result['game']['pending_scr_count'] ?? null, 2, 'Expected pending SCR count on game');
});

register_test('24.5 getAssignmentBoard includes pending SCR metadata', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->queryRows = [[
        [
            'game_id' => 10,
            'game_number' => 'G010',
            'game_status' => 'Scheduled',
            'division_name' => 'Junior',
            'home_team' => 'Home',
            'away_team' => 'Away',
            'game_date' => '2026-07-01',
            'game_time' => '18:00:00',
            'location_name' => 'Field 1',
            'draft_slots' => 1,
            'published_slots' => 0,
            'slot_summary' => 'Pat Blue|0|Draft',
            'pending_scr_count' => 1,
        ],
    ]];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->getAssignmentBoard();

    assert_equals($result[0]['has_pending_scr'] ?? null, true, 'Expected board row pending SCR flag');
    assert_equals($result[0]['pending_scr_count'] ?? null, 1, 'Expected board row pending SCR count');
});

register_test('24.5 assignment drawer source renders advisory warnings and tentative sync', function () {
    $source = file_get_contents(__DIR__ . '/../../public/admin/umpires/assignment-drawer.js');
    assert_true(strpos($source, 'function renderAdvisoryWarnings(warnings)') !== false, 'Expected advisory warnings renderer');
    assert_true(strpos($source, 'data-advisory-warnings') !== false, 'Expected advisory warnings container marker');
    assert_true(strpos($source, 'syncTentativeBadge') !== false, 'Expected tentative badge sync helper');
    assert_true(strpos($source, 'data-board-tentative') !== false, 'Expected board tentative marker');
    assert_true(strpos($source, 'data-queue-tentative') !== false, 'Expected queue tentative marker');
    assert_true(strpos($source, 'has_pending_scr') !== false, 'Expected pending SCR client handling');
});

register_test('24.5 board and queue sources render tentative badges', function () {
    $board = file_get_contents(__DIR__ . '/../../public/admin/umpires/board.php');
    $queue = file_get_contents(__DIR__ . '/../../public/admin/umpires/index.php');
    assert_true(strpos($board, 'data-board-tentative') !== false, 'Expected board tentative badge');
    assert_true(strpos($queue, 'data-queue-tentative') !== false, 'Expected queue tentative badge');
});

// ---------------------------------------------------------------------------
// Story 25.2 — Availability Pool in Assignment Drawer
// ---------------------------------------------------------------------------

register_test('25.2 getGameAssignmentDrawer marks umpire in_pool true when returned by availability service', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Scheduled',
         'game_date' => '2026-07-01', 'game_time' => '18:00:00',
         'division_name' => 'Junior', 'home_team' => 'Home', 'away_team' => 'Away',
         'location_id' => 0, 'location_name' => 'Field 1', 'pending_scr_count' => 0],
        ['id' => 7],
        ['program_id' => 0],
    ];
    $mock->queryRows = [
        [],  // assignment rows
        [['id' => 101, 'first_name' => 'Ann', 'last_name' => 'Blue', 'email' => 'a@t.com', 'phone' => '555', 'status' => 'active', 'umpire_level' => 'Blue Shirt', 'is_under_18' => 0, 'date_of_birth' => null]],
        [],  // eligibility
        [],  // loads
        [['umpire_user_id' => 101]],  // pool query — umpire 101 is available
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->getGameAssignmentDrawer(10);

    assert_true($result['roster'][0]['in_pool'] ?? false, '25.2: Umpire in availability pool should have in_pool=true');
    assert_equals($result['availability_pool_count'] ?? -1, 1, '25.2: availability_pool_count should be 1');
});

register_test('25.2 getGameAssignmentDrawer marks umpire in_pool false when not returned by availability service', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Scheduled',
         'game_date' => '2026-07-01', 'game_time' => '18:00:00',
         'division_name' => 'Junior', 'home_team' => 'Home', 'away_team' => 'Away',
         'location_id' => 0, 'location_name' => 'Field 1', 'pending_scr_count' => 0],
        ['id' => 7],
        ['program_id' => 0],
    ];
    $mock->queryRows = [
        [],  // assignment rows
        [['id' => 101, 'first_name' => 'Ann', 'last_name' => 'Blue', 'email' => 'a@t.com', 'phone' => '555', 'status' => 'active', 'umpire_level' => 'Blue Shirt', 'is_under_18' => 0, 'date_of_birth' => null]],
        [],  // eligibility
        [],  // loads
        [],  // pool query — empty, umpire 101 has no availability window
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->getGameAssignmentDrawer(10);

    assert_true(!($result['roster'][0]['in_pool'] ?? true), '25.2: Umpire not in pool should have in_pool=false');
    assert_equals($result['availability_pool_count'] ?? -1, 0, '25.2: availability_pool_count should be 0 when pool is empty');
});

register_test('25.2 getGameAssignmentDrawer differentiates in-pool vs out-of-pool umpires', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Scheduled',
         'game_date' => '2026-07-01', 'game_time' => '18:00:00',
         'division_name' => 'Junior', 'home_team' => 'Home', 'away_team' => 'Away',
         'location_id' => 0, 'location_name' => 'Field 1', 'pending_scr_count' => 0],
        ['id' => 7],
        ['program_id' => 0],
    ];
    $mock->queryRows = [
        [],  // assignment rows
        [
            ['id' => 101, 'first_name' => 'Ann', 'last_name' => 'Blue', 'email' => 'a@t.com', 'phone' => '555', 'status' => 'active', 'umpire_level' => 'Blue Shirt', 'is_under_18' => 0, 'date_of_birth' => null],
            ['id' => 102, 'first_name' => 'Bob', 'last_name' => 'Black', 'email' => 'b@t.com', 'phone' => '556', 'status' => 'active', 'umpire_level' => 'Black Shirt', 'is_under_18' => 0, 'date_of_birth' => null],
        ],
        [],  // eligibility
        [],  // loads
        [['umpire_user_id' => 101]],  // pool query — only 101 in pool; 102 has no window or conflicting assignment
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->getGameAssignmentDrawer(10);

    $rosterById = [];
    foreach ($result['roster'] as $u) {
        $rosterById[(int) $u['id']] = $u;
    }
    assert_true($rosterById[101]['in_pool'] ?? false, '25.2: Umpire 101 should be in pool');
    assert_true(!($rosterById[102]['in_pool'] ?? true), '25.2: Umpire 102 should not be in pool');
    assert_equals($result['availability_pool_count'] ?? -1, 1, '25.2: Pool count should be 1');
    assert_equals(count($result['roster']), 2, '25.2: Full roster should still contain both umpires');
});

register_test('25.2 availability_pool_count only counts roster-visible eligible pool members', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Scheduled',
         'game_date' => '2026-07-01', 'game_time' => '18:00:00',
         'division_name' => 'Junior', 'home_team' => 'Home', 'away_team' => 'Away',
         'location_id' => 0, 'location_name' => 'Field 1', 'pending_scr_count' => 0],
        ['id' => 7],
        ['program_id' => 99],
    ];
    $mock->queryRows = [
        [],
        [
            ['id' => 101, 'first_name' => 'Ann', 'last_name' => 'Blue', 'email' => 'a@t.com', 'phone' => '555', 'status' => 'active', 'umpire_level' => 'Blue Shirt', 'is_under_18' => 0, 'date_of_birth' => null],
            ['id' => 102, 'first_name' => 'Bob', 'last_name' => 'Black', 'email' => 'b@t.com', 'phone' => '556', 'status' => 'active', 'umpire_level' => 'Black Shirt', 'is_under_18' => 0, 'date_of_birth' => null],
        ],
        [
            ['umpire_user_id' => 101, 'program_id' => 99],
            ['umpire_user_id' => 102, 'program_id' => 100],
        ],
        [],
        [['umpire_user_id' => 101], ['umpire_user_id' => 102], ['umpire_user_id' => 999]],
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->getGameAssignmentDrawer(10);

    assert_equals(count($result['roster']), 1, '25.2: Program eligibility filters roster before pool count');
    assert_equals($result['roster'][0]['id'] ?? null, 101, '25.2: Eligible roster member remains visible');
    assert_equals($result['availability_pool_count'] ?? -1, 1, '25.2: Count should match visible in-pool roster members');
    assert_true(!($result['availability_pool_error'] ?? true), '25.2: Pool should not be marked failed');
});

register_test('25.2 getGameAssignmentDrawer falls back to full roster when availability pool fails', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Scheduled',
         'game_date' => 'not-a-date', 'game_time' => '18:00:00',
         'division_name' => 'Junior', 'home_team' => 'Home', 'away_team' => 'Away',
         'location_id' => 0, 'location_name' => 'Field 1', 'pending_scr_count' => 0],
        ['id' => 7],
        ['program_id' => 0],
    ];
    $mock->queryRows = [
        [],
        [
            ['id' => 101, 'first_name' => 'Ann', 'last_name' => 'Blue', 'email' => 'a@t.com', 'phone' => '555', 'status' => 'active', 'umpire_level' => 'Blue Shirt', 'is_under_18' => 0, 'date_of_birth' => null],
            ['id' => 102, 'first_name' => 'Bob', 'last_name' => 'Black', 'email' => 'b@t.com', 'phone' => '556', 'status' => 'active', 'umpire_level' => 'Black Shirt', 'is_under_18' => 0, 'date_of_birth' => null],
        ],
        [],
        [],
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->getGameAssignmentDrawer(10);

    assert_true($result['availability_pool_error'] ?? false, '25.2: Pool computation error should be visible in payload');
    assert_equals($result['availability_pool_count'] ?? -1, 2, '25.2: Fallback pool count should match full visible roster');
    foreach ($result['roster'] as $umpire) {
        assert_true($umpire['in_pool'] ?? false, '25.2: Fallback should not hide roster members by default');
    }
});

register_test('25.2 getGameAssignmentDrawer pool computation is additive — preserves all existing roster fields', function () {
    $mock = new UmpireAssignmentMockDb();
    $mock->fetchOneRows = [
        ['game_id' => 10, 'game_number' => 'G010', 'game_status' => 'Scheduled',
         'game_date' => '2026-07-01', 'game_time' => '18:00:00',
         'division_name' => 'Junior', 'home_team' => 'Home', 'away_team' => 'Away',
         'location_id' => 0, 'location_name' => 'Field 1', 'pending_scr_count' => 0],
        ['id' => 7],
        ['program_id' => 0],
    ];
    $mock->queryRows = [
        [],
        [['id' => 101, 'first_name' => 'Ann', 'last_name' => 'Blue', 'email' => 'a@t.com', 'phone' => '555', 'status' => 'active', 'umpire_level' => 'Blue Shirt', 'is_under_18' => 1, 'date_of_birth' => '2008-06-01']],
        [],
        [['umpire_user_id' => 101, 'current_game_load' => 2]],
        [['umpire_user_id' => 101]],
    ];
    Database::setInstance($mock);
    $svc = new UmpireAssignmentService();
    $result = $svc->getGameAssignmentDrawer(10);

    $u = $result['roster'][0];
    assert_equals($u['id'] ?? null, 101, '25.2: id preserved');
    assert_equals($u['umpire_level'] ?? null, 'Blue Shirt', '25.2: umpire_level preserved');
    assert_equals($u['current_game_load'] ?? null, 2, '25.2: current_game_load preserved');
    assert_equals($u['is_under_18'] ?? null, 1, '25.2: is_under_18 preserved');
    assert_true($u['in_pool'] ?? false, '25.2: in_pool added');
    assert_true(array_key_exists('availability_pool_count', $result), '25.2: availability_pool_count in payload');
});

register_test('25.2 assignment-drawer.js contains pool/roster toggle and mode-specific empty state', function () {
    $js = file_get_contents(__DIR__ . '/../../public/admin/umpires/assignment-drawer.js');
    assert_true(strpos($js, "pickerMode = 'pool'") !== false, '25.2: pickerMode defaults to pool');
    assert_true(strpos($js, "data-pool-mode") !== false, '25.2: pool mode toggle buttons present');
    assert_true(strpos($js, 'aria-pressed') !== false, '25.2: accessible aria-pressed on toggle buttons');
    assert_true(strpos($js, 'in_pool') !== false, '25.2: JS filters by in_pool flag');
    assert_true(strpos($js, 'available of') !== false, '25.2: pool mode count shows available-of context');
    assert_true(strpos($js, 'roster umpires') !== false, '25.2: full-roster mode count label present');
    assert_true(strpos($js, 'No umpires are available for this game time') !== false, '25.2: pool empty state message present');
    assert_true(strpos($js, 'Switch to All Umpires') !== false, '25.2: pool empty state hint to switch modes present');
});
