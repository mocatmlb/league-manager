<?php
/**
 * Unit Tests: TeamScope
 *
 * Story 1.3 — Implement Cross-Cutting Utility Classes
 * AC2: TeamScope::getScopedTeams() returns correct array of team data
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/TeamScope.php';

// ---------------------------------------------------------------------------
// Mock Database for TeamScope tests
// ---------------------------------------------------------------------------

/**
 * Minimal mock Database that returns pre-configured results for fetchAll().
 * Injected via Database::setInstance() to avoid hitting a real DB.
 */
class TeamScopeMockDatabase extends Database {

    private array $fetchAllResult;

    public function __construct(array $fetchAllResult) {
        // Skip parent constructor (no real DB connection needed)
        $this->fetchAllResult = $fetchAllResult;
    }

    public function fetchAll($sql, $params = []): array {
        return $this->fetchAllResult;
    }
}

// ---------------------------------------------------------------------------
// AC2-P0: user with one team returns single-element array
// ---------------------------------------------------------------------------

register_test('AC2-P0: getScopedTeams - user with one team returns single-element array', function () {
    $fakeTeam = ['id' => 42, 'name' => 'Lightning FC', 'status' => 'active'];
    $mock = new TeamScopeMockDatabase([$fakeTeam]);
    Database::setInstance($mock);

    $result = TeamScope::getScopedTeams(1);

    assert_true(is_array($result), 'getScopedTeams must return an array');
    assert_equals(count($result), 1, 'getScopedTeams must return exactly one team for a user with one assignment');
    assert_equals($result[0]['id'], 42, 'Returned team id must match the mocked team');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC2-P1: user with no teams returns empty array []
// ---------------------------------------------------------------------------

register_test('AC2-P1: getScopedTeams - user with no teams returns empty array', function () {
    $mock = new TeamScopeMockDatabase([]);
    Database::setInstance($mock);

    $result = TeamScope::getScopedTeams(999);

    assert_true(is_array($result), 'getScopedTeams must return an array even when user has no teams');
    assert_equals(count($result), 0, 'getScopedTeams must return empty array when user has no assigned teams');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC2-P2: return value is always an array, never null
// ---------------------------------------------------------------------------

register_test('AC2-P2: getScopedTeams - return type is always array, never null', function () {
    $mock = new TeamScopeMockDatabase([]);
    Database::setInstance($mock);

    $result = TeamScope::getScopedTeams(0);

    assert_true($result !== null, 'getScopedTeams must never return null');
    assert_true(is_array($result), 'getScopedTeams must always return an array');

    Database::setInstance(null);
});
