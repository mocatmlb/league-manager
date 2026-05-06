<?php
/**
 * Unit Tests: LeagueListManager
 *
 * Story 2.1 — LeagueListManager Service
 * AC1: getActiveList returns ordered active entries only
 * AC2: create inserts with is_active=1 and MAX(sort_order)+1
 * AC3: update modifies display_name; returns false for non-existent id
 * AC4: deactivate sets is_active=0; entry excluded from getActiveList
 * AC5: reactivate sets is_active=1 and places at bottom
 * AC6: reorder updates sort_order per position
 * AC7: all tests pass
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/LeagueListManager.php';

// ---------------------------------------------------------------------------
// Mock Database Infrastructure
// ---------------------------------------------------------------------------

/**
 * In-memory mock database for LeagueListManager tests.
 * Supports fetchAll, fetchOne, query (INSERT/UPDATE) with tracked calls.
 */
class LeagueListMockDatabase extends Database {

    /** Simulated league_list table rows */
    public array $rows = [];

    /** Tracks query calls: [['sql' => ..., 'params' => ...], ...] */
    public array $queryCalls = [];

    /** Next auto-increment id */
    public int $nextId = 1;
    public bool $inTransaction = false;

    public function __construct(array $initialRows = []) {
        $this->rows = $initialRows;
        if (!empty($initialRows)) {
            $maxId = max(array_column($initialRows, 'id'));
            $this->nextId = $maxId + 1;
        }
    }

    public function fetchAll($sql, $params = []): array {
        $sql = trim($sql);

        // getActiveList(): WHERE is_active = 1 ORDER BY sort_order ASC
        if (stripos($sql, 'WHERE is_active = 1') !== false && stripos($sql, 'ORDER BY sort_order ASC') !== false) {
            $active = array_filter($this->rows, fn($r) => (int)$r['is_active'] === 1);
            usort($active, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);
            return array_values($active);
        }

        // getAll(): ORDER BY is_active DESC, sort_order ASC
        if (stripos($sql, 'ORDER BY is_active DESC') !== false) {
            $rows = $this->rows;
            usort($rows, function ($a, $b) {
                $activeCmp = $b['is_active'] <=> $a['is_active'];
                if ($activeCmp !== 0) return $activeCmp;
                return $a['sort_order'] <=> $b['sort_order'];
            });
            return array_values($rows);
        }

        return [];
    }

    public function fetchOne($sql, $params = []) {
        $sql = trim($sql);

        // MAX sort_order for all entries (used in create)
        if (stripos($sql, 'COALESCE(MAX(sort_order)') !== false && stripos($sql, 'WHERE is_active') === false) {
            $maxOrder = empty($this->rows) ? 0 : max(array_column($this->rows, 'sort_order'));
            return ['next_order' => $maxOrder + 1];
        }

        // MAX sort_order for active entries only (used in reactivate)
        if (stripos($sql, 'COALESCE(MAX(sort_order)') !== false && stripos($sql, 'WHERE is_active = 1') !== false) {
            $activeOrders = array_column(
                array_filter($this->rows, fn($r) => (int)$r['is_active'] === 1),
                'sort_order'
            );
            $maxOrder = empty($activeOrders) ? 0 : max($activeOrders);
            return ['next_order' => $maxOrder + 1];
        }

        // Existence check by id
        if (stripos($sql, 'SELECT id FROM league_list WHERE id = :id') !== false) {
            $id = (int)($params['id'] ?? 0);
            foreach ($this->rows as $row) {
                if ((int)$row['id'] === $id) {
                    return ['id' => $id];
                }
            }
            return false;
        }

        return false;
    }

    public function query($sql, $params = []) {
        $this->queryCalls[] = ['sql' => $sql, 'params' => $params];
        $sql = trim($sql);

        // INSERT
        if (stripos($sql, 'INSERT INTO league_list') !== false) {
            $newRow = [
                'id'           => $this->nextId,
                'display_name' => $params['display_name'],
                'sort_order'   => (int)$params['sort_order'],
                'is_active'    => 1,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ];
            $this->rows[] = $newRow;
            $this->nextId++;
            return new LeagueListMockStatement(1, $this->nextId - 1);
        }

        // UPDATE display_name
        if (stripos($sql, 'SET display_name') !== false) {
            $id = (int)$params['id'];
            $affected = 0;
            foreach ($this->rows as &$row) {
                if ($row['id'] === $id) {
                    if ($row['display_name'] !== $params['display_name']) {
                        $row['display_name'] = $params['display_name'];
                        $row['updated_at']   = date('Y-m-d H:i:s');
                        $affected = 1;
                    }
                    break;
                }
            }
            unset($row);
            return new LeagueListMockStatement($affected);
        }

        // UPDATE is_active = 0 (deactivate)
        if (stripos($sql, 'SET is_active = 0') !== false) {
            $id = (int)$params['id'];
            $affected = 0;
            foreach ($this->rows as &$row) {
                if ($row['id'] === $id) {
                    if ((int)$row['is_active'] !== 0) {
                        $row['is_active']  = 0;
                        $row['updated_at'] = date('Y-m-d H:i:s');
                        $affected = 1;
                    }
                    break;
                }
            }
            unset($row);
            return new LeagueListMockStatement($affected);
        }

        // UPDATE is_active = 1 (reactivate)
        if (stripos($sql, 'SET is_active = 1') !== false) {
            $id = (int)$params['id'];
            $affected = 0;
            foreach ($this->rows as &$row) {
                if ($row['id'] === $id) {
                    $targetOrder = (int)$params['sort_order'];
                    $changed = ((int)$row['is_active'] !== 1) || ((int)$row['sort_order'] !== $targetOrder);
                    if ($changed) {
                        $row['is_active']  = 1;
                        $row['sort_order'] = $targetOrder;
                        $row['updated_at'] = date('Y-m-d H:i:s');
                        $affected = 1;
                    }
                    break;
                }
            }
            unset($row);
            return new LeagueListMockStatement($affected);
        }

        // UPDATE sort_order (reorder)
        if (stripos($sql, 'SET sort_order') !== false) {
            $id = (int)$params['id'];
            $affected = 0;
            foreach ($this->rows as &$row) {
                if ($row['id'] === $id && (int)$row['is_active'] === 1) {
                    $row['sort_order'] = (int)$params['sort_order'];
                    $row['updated_at'] = date('Y-m-d H:i:s');
                    $affected = 1;
                    break;
                }
            }
            unset($row);
            return new LeagueListMockStatement($affected);
        }

        return new LeagueListMockStatement(0);
    }

    public function getConnection() {
        return new LeagueListMockConnection($this->nextId - 1);
    }

    public function beginTransaction() {
        $this->inTransaction = true;
        return true;
    }

    public function commit() {
        $this->inTransaction = false;
        return true;
    }

    public function rollback() {
        $this->inTransaction = false;
        return true;
    }
}

class LeagueListMockStatement {
    private int $affectedRows;
    private int $lastId;

    public function __construct(int $affectedRows = 1, int $lastId = 0) {
        $this->affectedRows = $affectedRows;
        $this->lastId       = $lastId;
    }

    public function rowCount(): int { return $this->affectedRows; }
    public function fetch($mode = null) { return false; }
    public function fetchAll($mode = null): array { return []; }
}

class LeagueListMockConnection {
    private int $lastInsertId;
    public function __construct(int $lastInsertId) { $this->lastInsertId = $lastInsertId; }
    public function lastInsertId(): string { return (string) $this->lastInsertId; }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeLeague(int $id, string $name, int $sortOrder = 1, int $isActive = 1): array {
    return [
        'id'           => $id,
        'display_name' => $name,
        'sort_order'   => $sortOrder,
        'is_active'    => $isActive,
        'created_at'   => '2026-01-01 00:00:00',
        'updated_at'   => '2026-01-01 00:00:00',
    ];
}

// ---------------------------------------------------------------------------
// AC1: getActiveList returns only active entries ordered by sort_order
// ---------------------------------------------------------------------------

register_test('AC1-P0: getActiveList returns only active entries in sort_order', function () {
    $mock = new LeagueListMockDatabase([
        makeLeague(1, 'Maple', 2, 1),
        makeLeague(2, 'Oak',   1, 1),
        makeLeague(3, 'Pine',  3, 0), // deactivated — must be excluded
    ]);
    Database::setInstance($mock);

    $result = LeagueListManager::getActiveList();

    assert_equals(count($result), 2, 'getActiveList must return 2 active entries');
    assert_equals($result[0]['display_name'], 'Oak',   'First entry must be Oak (sort_order=1)');
    assert_equals($result[1]['display_name'], 'Maple', 'Second entry must be Maple (sort_order=2)');

    Database::setInstance(null);
});

register_test('AC1-P1: getActiveList returns empty array when no active entries', function () {
    $mock = new LeagueListMockDatabase([
        makeLeague(1, 'Maple', 1, 0),
        makeLeague(2, 'Oak',   2, 0),
    ]);
    Database::setInstance($mock);

    $result = LeagueListManager::getActiveList();

    assert_true(is_array($result), 'getActiveList must return array');
    assert_equals(count($result), 0, 'getActiveList must return empty array when all entries are deactivated');

    Database::setInstance(null);
});

register_test('AC1-P2: getActiveList returns empty array on empty table', function () {
    $mock = new LeagueListMockDatabase([]);
    Database::setInstance($mock);

    $result = LeagueListManager::getActiveList();

    assert_true(is_array($result), 'getActiveList must return array');
    assert_equals(count($result), 0, 'getActiveList on empty table must return empty array');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC2: create inserts with is_active=1 and MAX(sort_order)+1
// ---------------------------------------------------------------------------

register_test('AC2-P0: create inserts new entry with is_active=1 and correct sort_order', function () {
    $mock = new LeagueListMockDatabase([
        makeLeague(1, 'Oak', 1, 1),
        makeLeague(2, 'Maple', 2, 1),
    ]);
    Database::setInstance($mock);

    LeagueListManager::create('Birch');

    // Verify the new row was added
    $found = null;
    foreach ($mock->rows as $row) {
        if ($row['display_name'] === 'Birch') {
            $found = $row;
            break;
        }
    }

    assert_true($found !== null, 'create must insert a row with display_name = Birch');
    assert_equals((int)$found['is_active'], 1, 'create must set is_active = 1');
    assert_equals((int)$found['sort_order'], 3, 'create must set sort_order to MAX(sort_order)+1 = 3');

    Database::setInstance(null);
});

register_test('AC2-P1: create returns the new entry id', function () {
    $mock = new LeagueListMockDatabase([]);
    $mock->nextId = 7;
    Database::setInstance($mock);

    $newId = LeagueListManager::create('Elm');

    assert_equals($newId, 7, 'create must return the new entry id');

    Database::setInstance(null);
});

register_test('AC2-P2: create on empty table uses sort_order=1', function () {
    $mock = new LeagueListMockDatabase([]);
    Database::setInstance($mock);

    LeagueListManager::create('First League');

    $found = null;
    foreach ($mock->rows as $row) {
        if ($row['display_name'] === 'First League') {
            $found = $row;
            break;
        }
    }

    assert_true($found !== null, 'create must insert the entry');
    assert_equals((int)$found['sort_order'], 1, 'create on empty table must use sort_order = 1');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC3: update modifies display_name; returns false for non-existent id
// ---------------------------------------------------------------------------

register_test('AC3-P0: update changes display_name and returns true', function () {
    $mock = new LeagueListMockDatabase([
        makeLeague(5, 'Old Name', 1, 1),
    ]);
    Database::setInstance($mock);

    $result = LeagueListManager::update(5, 'New Name');

    assert_true($result === true, 'update must return true when entry exists');

    $updated = null;
    foreach ($mock->rows as $row) {
        if ($row['id'] === 5) {
            $updated = $row;
            break;
        }
    }

    assert_equals($updated['display_name'], 'New Name', 'update must change the display_name');

    Database::setInstance(null);
});

register_test('AC3-P1: update with non-existent id returns false without throwing', function () {
    $mock = new LeagueListMockDatabase([
        makeLeague(1, 'Oak', 1, 1),
    ]);
    Database::setInstance($mock);

    $exceptionThrown = false;
    $result = false;
    try {
        $result = LeagueListManager::update(999, 'Does Not Exist');
    } catch (Throwable $e) {
        $exceptionThrown = true;
    }

    assert_true(!$exceptionThrown, 'update with non-existent id must not throw');
    assert_true($result === false, 'update with non-existent id must return false');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC4: deactivate sets is_active=0; deactivated entry excluded from getActiveList
// ---------------------------------------------------------------------------

register_test('AC4-P0: deactivate sets is_active=0', function () {
    $mock = new LeagueListMockDatabase([
        makeLeague(3, 'Maple', 1, 1),
    ]);
    Database::setInstance($mock);

    $result = LeagueListManager::deactivate(3);

    assert_true($result === true, 'deactivate must return true when entry exists');
    assert_equals((int)$mock->rows[0]['is_active'], 0, 'deactivate must set is_active = 0');

    Database::setInstance(null);
});

register_test('AC4-P1: deactivated entry is excluded from getActiveList', function () {
    $mock = new LeagueListMockDatabase([
        makeLeague(1, 'Oak',   1, 1),
        makeLeague(2, 'Maple', 2, 1),
    ]);
    Database::setInstance($mock);

    LeagueListManager::deactivate(2);
    $active = LeagueListManager::getActiveList();

    assert_equals(count($active), 1, 'After deactivating one entry, only 1 should remain active');
    assert_equals($active[0]['display_name'], 'Oak', 'Remaining active entry must be Oak');

    Database::setInstance(null);
});

register_test('AC4-P2: deactivated entry remains in database', function () {
    $mock = new LeagueListMockDatabase([
        makeLeague(1, 'Oak', 1, 1),
    ]);
    Database::setInstance($mock);

    LeagueListManager::deactivate(1);

    assert_equals(count($mock->rows), 1, 'Row count must remain 1 — soft delete only');
    assert_equals((int)$mock->rows[0]['is_active'], 0, 'Row must still exist with is_active=0');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC5: reactivate sets is_active=1; entry appears at bottom of active list
// ---------------------------------------------------------------------------

register_test('AC5-P0: reactivate sets is_active=1', function () {
    $mock = new LeagueListMockDatabase([
        makeLeague(4, 'Pine', 1, 0),
    ]);
    Database::setInstance($mock);

    $result = LeagueListManager::reactivate(4);

    assert_true($result === true, 'reactivate must return true when entry exists');
    assert_equals((int)$mock->rows[0]['is_active'], 1, 'reactivate must set is_active = 1');

    Database::setInstance(null);
});

register_test('AC5-P1: reactivated entry appears in getActiveList', function () {
    $mock = new LeagueListMockDatabase([
        makeLeague(1, 'Oak',  1, 1),
        makeLeague(2, 'Pine', 2, 0),
    ]);
    Database::setInstance($mock);

    LeagueListManager::reactivate(2);
    $active = LeagueListManager::getActiveList();

    assert_equals(count($active), 2, 'After reactivating, 2 entries should be active');
    assert_equals($active[1]['id'], 2, 'Reactivated entry must appear at the bottom of active list');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC6: reorder updates sort_order per position
// ---------------------------------------------------------------------------

register_test('AC6-P0: reorder updates sort_order for each id in position order', function () {
    $mock = new LeagueListMockDatabase([
        makeLeague(1, 'Oak',   1, 1),
        makeLeague(2, 'Maple', 2, 1),
        makeLeague(3, 'Birch', 3, 1),
    ]);
    Database::setInstance($mock);

    // Reverse the order: Birch first, Maple second, Oak third
    LeagueListManager::reorder([3, 2, 1]);

    $byId = [];
    foreach ($mock->rows as $row) {
        $byId[$row['id']] = $row;
    }

    assert_equals((int)$byId[3]['sort_order'], 1, 'Birch (id=3) must have sort_order=1 after reorder');
    assert_equals((int)$byId[2]['sort_order'], 2, 'Maple (id=2) must have sort_order=2 after reorder');
    assert_equals((int)$byId[1]['sort_order'], 3, 'Oak (id=1) must have sort_order=3 after reorder');

    Database::setInstance(null);
});

register_test('AC6-P1: reorder with single entry uses sort_order=1', function () {
    $mock = new LeagueListMockDatabase([
        makeLeague(5, 'Elm', 2, 1),
    ]);
    Database::setInstance($mock);

    LeagueListManager::reorder([5]);

    assert_equals((int)$mock->rows[0]['sort_order'], 1, 'Single entry reorder must assign sort_order=1');

    Database::setInstance(null);
});

register_test('AC6-P2: getActiveList reflects new order after reorder', function () {
    $mock = new LeagueListMockDatabase([
        makeLeague(1, 'Oak',   1, 1),
        makeLeague(2, 'Maple', 2, 1),
        makeLeague(3, 'Birch', 3, 1),
    ]);
    Database::setInstance($mock);

    LeagueListManager::reorder([3, 1, 2]);
    $active = LeagueListManager::getActiveList();

    assert_equals($active[0]['display_name'], 'Birch', 'Birch must be first after reorder');
    assert_equals($active[1]['display_name'], 'Oak',   'Oak must be second after reorder');
    assert_equals($active[2]['display_name'], 'Maple', 'Maple must be third after reorder');

    Database::setInstance(null);
});

register_test('AC6-P3: reorder rejects duplicate IDs', function () {
    $mock = new LeagueListMockDatabase([
        makeLeague(1, 'Oak',   1, 1),
        makeLeague(2, 'Maple', 2, 1),
    ]);
    Database::setInstance($mock);

    $threw = false;
    try {
        LeagueListManager::reorder([1, 1]);
    } catch (InvalidArgumentException $e) {
        $threw = true;
    }

    assert_true($threw, 'Reorder must reject duplicate IDs');
    Database::setInstance(null);
});

register_test('AC6-P4: reorder rejects missing active IDs', function () {
    $mock = new LeagueListMockDatabase([
        makeLeague(1, 'Oak',   1, 1),
        makeLeague(2, 'Maple', 2, 1),
        makeLeague(3, 'Birch', 3, 0),
    ]);
    Database::setInstance($mock);

    $threw = false;
    try {
        LeagueListManager::reorder([2]);
    } catch (InvalidArgumentException $e) {
        $threw = true;
    }

    assert_true($threw, 'Reorder must require every active ID exactly once');
    Database::setInstance(null);
});
