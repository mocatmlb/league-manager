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
    
    // Mock pool result
    $db->queryRows[] = [['umpire_user_id' => 1], ['umpire_user_id' => 3], ['umpire_user_id' => 5]];
    
    $service = new UmpireAvailabilityService();
    $ids = $service->getAvailableUmpireIdsForWindow(new DateTime('2026-07-01 10:00:00'), new DateTime('2026-07-01 12:00:00'));
    
    assert_equals($ids, [1, 3, 5], 'Returns int[] array');
    assert_true(str_contains($db->lastSql[0], 'umpire_availability_windows'), 'Queries availability table');
    assert_true(str_contains($db->lastSql[0], 'game_umpire_assignments'), 'Excludes overlapping assignments');
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
