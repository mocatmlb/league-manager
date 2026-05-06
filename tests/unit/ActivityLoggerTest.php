<?php
/**
 * Unit Tests: ActivityLogger
 *
 * Story 1.3 — Implement Cross-Cutting Utility Classes
 * AC4: ActivityLogger::log() inserts into activity_log; handles DB unavailability gracefully
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ActivityLogger.php';

// ---------------------------------------------------------------------------
// Mock Database infrastructure
// ---------------------------------------------------------------------------

/**
 * Captures INSERT calls made by ActivityLogger for assertion.
 */
class ActivityLoggerMockDatabase extends Database {

    public array $capturedSql    = [];
    public array $capturedParams = [];
    public bool  $shouldThrow    = false;

    public function __construct(bool $shouldThrow = false) {
        $this->shouldThrow = $shouldThrow;
    }

    public function query($sql, $params = []) {
        if ($this->shouldThrow) {
            throw new Exception('Simulated DB unavailability');
        }
        $this->capturedSql[]    = $sql;
        $this->capturedParams[] = $params;
        return new ActivityLoggerMockStatement();
    }
}

/**
 * Minimal PDOStatement stand-in so callers that inspect the return value don't crash.
 */
class ActivityLoggerMockStatement {
    public function rowCount(): int { return 1; }
    public function fetch($mode = null) { return false; }
    public function fetchAll($mode = null): array { return []; }
}

// ---------------------------------------------------------------------------
// AC4-P0: successful insert records event and JSON context
// ---------------------------------------------------------------------------

register_test('AC4-P0: ActivityLogger::log - inserts event and JSON context into activity_log', function () {
    $mock = new ActivityLoggerMockDatabase();
    Database::setInstance($mock);

    ActivityLogger::log('auth.login_success', ['user_id' => 1, 'ip' => '127.0.0.1']);

    assert_true(count($mock->capturedSql) === 1, 'log() must execute exactly one INSERT query');

    $sql = $mock->capturedSql[0];
    assert_true(
        stripos($sql, 'INSERT') !== false && stripos($sql, 'activity_log') !== false,
        'log() must insert into activity_log table'
    );

    $params = $mock->capturedParams[0];
    assert_equals($params['event'], 'auth.login_success', 'log() must pass event name as :event parameter');

    $decoded = json_decode($params['context'], true);
    assert_true(is_array($decoded), 'log() must encode context as valid JSON');
    assert_equals($decoded['user_id'], 1, 'log() must include user_id in JSON context');
    assert_equals($decoded['ip'], '127.0.0.1', 'log() must include ip in JSON context');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC4-P1: DB unavailability → no exception thrown (graceful silent failure)
// ---------------------------------------------------------------------------

register_test('AC4-P1: ActivityLogger::log - DB unavailability does not throw exception', function () {
    $mock = new ActivityLoggerMockDatabase(shouldThrow: true);
    Database::setInstance($mock);

    $exceptionThrown = false;
    try {
        ActivityLogger::log('test.event', ['key' => 'value']);
    } catch (Throwable $e) {
        $exceptionThrown = true;
    }

    assert_true(!$exceptionThrown, 'log() must not throw when the database is unavailable');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC4-P2: empty context array produces valid JSON '[]'
// ---------------------------------------------------------------------------

register_test('AC4-P2: ActivityLogger::log - empty context is encoded as valid JSON', function () {
    $mock = new ActivityLoggerMockDatabase();
    Database::setInstance($mock);

    ActivityLogger::log('system.startup');

    assert_true(count($mock->capturedParams) === 1, 'log() must execute the INSERT');
    $params = $mock->capturedParams[0];
    $decoded = json_decode($params['context'], true);
    assert_true(is_array($decoded) && count($decoded) === 0, 'Empty context must encode as JSON array []');

    Database::setInstance(null);
});
