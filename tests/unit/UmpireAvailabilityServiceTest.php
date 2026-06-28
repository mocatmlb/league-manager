<?php
/**
 * Unit Tests: UmpireAvailabilityService
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ActivityLogger.php';

if (!function_exists('getSetting')) {
    function getSetting(string $key, string $default = '') {
        return $GLOBALS['_test_settings'][$key] ?? $default;
    }
}

// Mock Database for testing
class UmpireAvailabilityMockDb extends Database {
    public array $lastSql      = [];
    public array $lastParams   = [];
    public array $queryRows    = [];
    public array $insertRows   = [];
    public array $updateRows   = [];
    public array $deleteRows   = [];
    public int $nextInsertId   = 5000;

    public function __construct() {}

    public function query($sql, $params = []) {
        $this->lastSql[]    = $sql;
        $this->lastParams[] = $params;
        $rows = !empty($this->queryRows) ? array_shift($this->queryRows) : [];
        return new UmpireAvailabilityMockStmt($rows);
    }

    public function fetchOne($sql, $params = []) {
        $this->lastSql[] = $sql;
        $this->lastParams[] = $params;
        $rows = !empty($this->queryRows) ? array_shift($this->queryRows) : [];
        return $rows[0] ?? false;
    }

    public function insert($table, $data) {
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
        return new UmpireAvailabilityMockStmt([]);
    }

    public function delete($table, $where, $params = []) {
        $this->deleteRows[] = ['table' => $table, 'where' => $where, 'params' => $params];
        return true;
    }
}

class UmpireAvailabilityMockStmt {
    private array $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function fetchAll() { return $this->rows; }
    public function fetch() { return array_shift($this->rows); }
    public function rowCount() { return count($this->rows); }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

register_test('UmpireAvailabilityService: List windows for umpire', function() {
    $db = new UmpireAvailabilityMockDb();
    Database::setInstance($db);
    
    $mockRows = [
        ['availability_id' => 1, 'starts_at' => '2026-07-01 08:00:00', 'ends_at' => '2026-07-01 12:00:00'],
        ['availability_id' => 2, 'starts_at' => '2026-07-02 08:00:00', 'ends_at' => '2026-07-02 12:00:00'],
    ];
    $db->queryRows[] = $mockRows;
    
    $service = new UmpireAvailabilityService();
    $results = $service->listForUmpire(123);
    
    assert_equals(count($results), 2, 'Should return 2 windows');
    assert_equals($results[0]['availability_id'], 1, 'First window ID matches');
    assert_true(str_contains($db->lastSql[0], 'WHERE umpire_user_id = :umpire_user_id'), 'Query is scoped to umpire');
    assert_equals($db->lastParams[0]['umpire_user_id'], 123, 'Correct umpire ID passed');
});

register_test('UmpireAvailabilityService: Create window validation', function() {
    $db = new UmpireAvailabilityMockDb();
    Database::setInstance($db);
    
    $service = new UmpireAvailabilityService();
    
    // Valid window
    $id = $service->createWindow(123, '2026-07-01 08:00:00', '2026-07-01 12:00:00', 'Testing');
    assert_equals($id, 5000, 'Returns new insert ID');
    assert_equals($db->insertRows[0]['data']['umpire_user_id'], 123, 'Correct user ID in insert');
    assert_equals($db->insertRows[0]['data']['starts_at'], '2026-07-01 08:00:00', 'Start datetime is normalized');
    
    // Invalid: starts_at >= ends_at
    try {
        $service->createWindow(123, '2026-07-01 12:00:00', '2026-07-01 08:00:00');
        assert_true(false, 'Should throw for starts_at > ends_at');
    } catch (InvalidArgumentException $e) {
        assert_true(str_contains($e->getMessage(), 'must be before'), 'Correct error message');
    }
    
    // Invalid: blank datetimes
    try {
        $service->createWindow(123, '', '2026-07-01 12:00:00');
        assert_true(false, 'Should throw for blank starts_at');
    } catch (InvalidArgumentException $e) {
        assert_true(str_contains($e->getMessage(), 'cannot be blank'), 'Correct error message');
    }

    // Invalid: malformed datetime
    try {
        $service->createWindow(123, 'not-a-date', '2026-07-01 12:00:00');
        assert_true(false, 'Should throw for malformed starts_at');
    } catch (InvalidArgumentException $e) {
        assert_true(str_contains($e->getMessage(), 'valid date and time'), 'Correct malformed datetime message');
    }
});

register_test('UmpireAvailabilityService: Update window ownership', function() {
    $db = new UmpireAvailabilityMockDb();
    Database::setInstance($db);
    
    // Mock existence/ownership check
    $db->queryRows[] = [['availability_id' => 10, 'umpire_user_id' => 123]];
    
    $service = new UmpireAvailabilityService();
    $service->updateWindow(10, 123, '2026-07-01 09:00:00', '2026-07-01 13:00:00');
    
    assert_equals(count($db->updateRows), 1, 'Update called once');
    assert_equals($db->updateRows[0]['where'], 'availability_id = :id AND umpire_user_id = :umpire_user_id', 'Update is ownership-scoped');
    
    // Update for wrong user
    $db->queryRows[] = []; // Not found/owned
    try {
        $service->updateWindow(10, 999, '2026-07-01 09:00:00', '2026-07-01 13:00:00');
        assert_true(false, 'Should throw for ownership failure');
    } catch (RuntimeException $e) {
        assert_true(str_contains($e->getMessage(), 'not found or not owned'), 'Correct error message');
    }
});

register_test('UmpireAvailabilityService: Delete window ownership', function() {
    $db = new UmpireAvailabilityMockDb();
    Database::setInstance($db);
    
    // Mock existence/ownership check
    $db->queryRows[] = [['availability_id' => 10, 'umpire_user_id' => 123]];
    
    $service = new UmpireAvailabilityService();
    $service->deleteWindow(10, 123);
    
    assert_equals(count($db->deleteRows), 1, 'Delete called once');
    assert_equals($db->deleteRows[0]['where'], 'availability_id = :id AND umpire_user_id = :umpire_user_id', 'Delete is ownership-scoped');
});

register_test('UmpireAvailabilityService: Pool query (int[] contract)', function() {
    $db = new UmpireAvailabilityMockDb();
    Database::setInstance($db);
    $GLOBALS['_test_settings']['conflict_window_minutes'] = '45';
    
    // Mock pool result
    $db->queryRows[] = [['umpire_user_id' => 1], ['umpire_user_id' => 3], ['umpire_user_id' => 5]];
    
    $service = new UmpireAvailabilityService();
    $ids = $service->getAvailableUmpireIdsForWindow(new DateTime('2026-07-01 10:00:00'), new DateTime('2026-07-01 12:00:00'));
    
    assert_equals($ids, [1, 3, 5], 'Returns int[] array');
    $sql = $db->lastSql[0];
    assert_true(str_contains($sql, 'umpire_availability_windows'), 'Queries availability table');
    assert_true(str_contains($sql, 'game_umpire_assignments'), 'Excludes overlapping assignments');
    assert_true(str_contains($sql, "JOIN roles r ON r.id = u.role_id AND r.name = 'umpire'"), 'Restricts pool to umpire role');
    assert_true(str_contains($sql, 'JOIN schedules s ON s.game_id = gua.game_id'), 'Uses schedules.game_id join');
    assert_true(str_contains($sql, "gua.assignment_status IN ('Draft', 'Published')"), 'Only Draft and Published assignments block pool membership');
    assert_true(!str_contains($sql, "gua.assignment_status != 'Cancelled'"), 'Declined and Open assignments should not block pool membership');
    assert_true(str_contains($sql, "TIMESTAMP(s.game_date, COALESCE(s.game_time, '00:00:00'))"), 'Builds schedule datetime from game_date and game_time');
    assert_true(str_contains($sql, 'INTERVAL 2700 SECOND'), 'Uses configured conflict-window duration');
    assert_true(!str_contains($sql, 'INTERVAL 3 HOUR'), 'Does not hardcode a three-hour overlap window');
    assert_true(!str_contains($sql, 'game_date_time'), 'Does not reference nonexistent game_date_time column');
    assert_true(!str_contains($sql, 'gua.status'), 'Does not reference nonexistent gua.status column');
    unset($GLOBALS['_test_settings']['conflict_window_minutes']);

    try {
        $service->getAvailableUmpireIdsForWindow(new DateTime('2026-07-01 12:00:00'), new DateTime('2026-07-01 12:00:00'));
        assert_true(false, 'Should throw for zero-length requested window');
    } catch (InvalidArgumentException $e) {
        assert_true(str_contains($e->getMessage(), 'must end after it starts'), 'Correct requested window message');
    }
});

register_test('Story 25.1 migration uses compatible FK and rerun-safe index placement', function() {
    $sql = file_get_contents(__DIR__ . '/../../database/migrations/051_create_umpire_availability_windows.sql');
    assert_true(str_contains($sql, '`umpire_user_id` INT NOT NULL'), 'FK column should match signed users.id');
    assert_true(!str_contains($sql, '`umpire_user_id` INT UNSIGNED'), 'FK column should not be unsigned');
    assert_true(str_contains($sql, 'INDEX `idx_umpire_availability_user_window`'), 'User/window index should be declared in table');
    assert_true(!str_contains($sql, 'CREATE INDEX `idx_umpire_availability_user_window`'), 'Should not use standalone CREATE INDEX after IF NOT EXISTS table');
    assert_true(str_contains($sql, "INSERT IGNORE INTO `schema_migrations` (`version`) VALUES ('051')"), 'Migration should use schema_migrations version-only insert');
    assert_true(!str_contains($sql, 'run_at'), 'Migration should not reference nonexistent run_at column');
});

register_test('Story 25.1 portal supports update action and modal controls', function() {
    $php = file_get_contents(__DIR__ . '/../../public/umpires/availability.php');
    assert_true(str_contains($php, "\$action === 'update'"), 'POST handler should support update');
    assert_true(str_contains($php, '$service->updateWindow('), 'Update action should call service updateWindow');
    assert_true(str_contains($php, 'Edit Availability Window'), 'Page should render edit modal');
    assert_true(str_contains($php, 'data-bs-dismiss="modal"'), 'Cancel buttons should dismiss modals');
    assert_true(str_contains($php, "Unsupported availability action"), 'Unsupported POST actions should not silently succeed');
});

// Implementation of service will follow to make these pass
if (file_exists(__DIR__ . '/../../includes/UmpireAvailabilityService.php')) {
    require_once __DIR__ . '/../../includes/UmpireAvailabilityService.php';
} else {
    // Scaffold class if not yet implemented so tests can at least be registered
    class UmpireAvailabilityService {
        public function listForUmpire(int $u) { throw new RuntimeException("Not implemented"); }
        public function createWindow($u, $s, $e, $n=null) { throw new RuntimeException("Not implemented"); }
        public function updateWindow($i, $u, $s, $e, $n=null) { throw new RuntimeException("Not implemented"); }
        public function deleteWindow($i, $u) { throw new RuntimeException("Not implemented"); }
        public function getAvailableUmpireIdsForWindow($s, $e) { throw new RuntimeException("Not implemented"); }
    }
}
