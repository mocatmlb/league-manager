<?php
/**
 * Unit Tests: UmpireConflictChecker
 *
 * Story 23.3 - double-book detection.
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/UmpireConflictChecker.php';

class UmpireConflictCheckerMockDb extends Database {
    public array $lastSql = [];
    public array $lastParams = [];
    public array $rows = [];

    public function __construct() {}

    public function query($sql, $params = []) {
        $this->lastSql[] = $sql;
        $this->lastParams[] = $params;
        return new UmpireConflictCheckerMockStmt($this->rows);
    }
}

class UmpireConflictCheckerMockStmt {
    private array $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function fetch($mode = null) {
        return $this->rows[0] ?? false;
    }
}

register_test('23.3 conflict checker returns Draft/Published overlap payload', function () {
    $mock = new UmpireConflictCheckerMockDb();
    $mock->rows = [[
        'assignment_id' => 55,
        'game_id' => 99,
        'game_number' => 'G099',
        'game_date' => '2026-07-01',
        'game_time' => '18:00:00',
        'home_team' => 'Home',
        'away_team' => 'Away',
        'location_name' => 'Field 1',
        'assignment_status' => 'Draft',
    ]];
    Database::setInstance($mock);

    $result = UmpireConflictChecker::check(
        101,
        new DateTime('2026-07-01 18:30:00'),
        new DateTime('2026-07-01 20:30:00'),
        null
    );

    assert_equals($result['assignment_id'], 55, 'Expected assignment id in conflict payload');
    assert_equals($result['game_id'], 99, 'Expected game id in conflict payload');
    assert_equals($result['location_name'], 'Field 1', 'Expected location in payload');

    $sql = $mock->lastSql[0] ?? '';
    assert_true(strpos($sql, "gua.assignment_status IN ('Draft', 'Published')") !== false, 'Expected only Draft/Published conflicts');
    assert_true(strpos($sql, "g.game_status NOT IN ('Cancelled', 'Postponed')") !== false, 'Expected cancelled/postponed games ignored');
    assert_true(strpos($sql, 'gua.assignment_id <> :exclude_assignment_id') !== false, 'Expected exclude assignment predicate');
    assert_true(strpos($sql, 'DATE_ADD') !== false, 'Expected default two-hour window in SQL');
});

register_test('23.3 conflict checker returns null for adjacent/non-overlap', function () {
    $mock = new UmpireConflictCheckerMockDb();
    $mock->rows = [];
    Database::setInstance($mock);

    $result = UmpireConflictChecker::check(
        101,
        new DateTime('2026-07-01 20:00:00'),
        new DateTime('2026-07-01 22:00:00'),
        55
    );

    assert_null($result, 'Expected no conflict when query returns no overlapping assignment');
    $params = $mock->lastParams[0] ?? [];
    assert_equals($params['umpire_user_id'] ?? null, 101, 'Expected umpire id param');
    assert_equals($params['exclude_assignment_id'] ?? null, 55, 'Expected excluded assignment id param');
    assert_equals($params['target_start'] ?? null, '2026-07-01 20:00:00', 'Expected start param');
    assert_equals($params['target_end'] ?? null, '2026-07-01 22:00:00', 'Expected end param');
});

register_test('23.3 conflict checker rejects invalid umpire id', function () {
    $mock = new UmpireConflictCheckerMockDb();
    Database::setInstance($mock);

    $threw = false;
    try {
        UmpireConflictChecker::check(0, new DateTime('2026-07-01 18:00:00'), new DateTime('2026-07-01 20:00:00'));
    } catch (InvalidArgumentException $e) {
        $threw = true;
    }

    assert_true($threw, 'Expected invalid umpire id to throw');
});
