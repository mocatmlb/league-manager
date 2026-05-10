<?php
if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ActivityLogger.php';
require_once __DIR__ . '/../../includes/TeamScope.php';
require_once __DIR__ . '/../../includes/CoachScheduleService.php';

// ---------------------------------------------------------------------------
// Mock infrastructure
// ---------------------------------------------------------------------------

class CSSMockStatement {
    public function rowCount(): int { return 0; }
}

class CSSMockDatabase extends Database {
    public array $teams = [];
    public array $games = [];

    public function __construct() {
        // Bypass real PDO connection
    }

    public function fetchOne($sql, $params = []) {
        return false;
    }

    public function fetchAll($sql, $params = []): array {
        if (stripos($sql, 'team_owners') !== false) {
            $userId = (int) ($params['user_id'] ?? -1);
            return array_values(
                array_filter($this->teams, fn($t) => (int) ($t['owner_user_id'] ?? -1) === $userId)
            );
        }

        if (stripos($sql, 'FROM games g') !== false) {
            $half = (int) (count($params) / 2);
            $teamIds = array_map('intval', array_slice($params, 0, $half));

            return array_values(array_filter(
                $this->games,
                fn($g) => in_array((int) $g['home_team_id'], $teamIds, true)
                        || in_array((int) $g['away_team_id'], $teamIds, true)
            ));
        }

        return [];
    }

    public function query($sql, $params = []) {
        return new CSSMockStatement();
    }
}

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

function cssTeam(int $teamId, int $ownerUserId): array {
    return ['team_id' => $teamId, 'team_name' => "Team {$teamId}", 'owner_user_id' => $ownerUserId];
}

function cssGame(array $overrides = []): array {
    return array_merge([
        'game_number'    => 1,
        'game_status'    => 'Active',
        'home_score'     => null,
        'away_score'     => null,
        'home_team_id'   => 10,
        'away_team_id'   => 20,
        'game_date'      => '2026-05-15',
        'game_time'      => '18:00:00',
        'location'       => 'Field A',
        'home_team_name' => 'Team 10',
        'away_team_name' => 'Team 20',
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

register_test('getTeamSchedule — no assigned teams returns empty array', function () {
    $mock = new CSSMockDatabase();
    $mock->teams = [];
    Database::setInstance($mock);

    $service = new CoachScheduleService($mock);
    $result = $service->getTeamSchedule(999);

    assert_equals($result, [], 'Should return empty array when coach has no teams');
});

register_test('getTeamSchedule — returns home games', function () {
    $mock = new CSSMockDatabase();
    $mock->teams = [cssTeam(10, 1)];
    $mock->games = [
        cssGame(['game_number' => 1, 'home_team_id' => 10, 'away_team_id' => 20]),
        cssGame(['game_number' => 2, 'home_team_id' => 30, 'away_team_id' => 40]),
    ];
    Database::setInstance($mock);

    $service = new CoachScheduleService($mock);
    $result = $service->getTeamSchedule(1);

    assert_equals(count($result), 1, 'Should return 1 home game');
    assert_equals((int) $result[0]['home_team_id'], 10, 'Home team ID should be 10');
});

register_test('getTeamSchedule — returns away games', function () {
    $mock = new CSSMockDatabase();
    $mock->teams = [cssTeam(20, 2)];
    $mock->games = [
        cssGame(['game_number' => 1, 'home_team_id' => 10, 'away_team_id' => 20]),
        cssGame(['game_number' => 2, 'home_team_id' => 30, 'away_team_id' => 40]),
    ];
    Database::setInstance($mock);

    $service = new CoachScheduleService($mock);
    $result = $service->getTeamSchedule(2);

    assert_equals(count($result), 1, 'Should return 1 away game');
    assert_equals((int) $result[0]['away_team_id'], 20, 'Away team ID should be 20');
});

register_test('getTeamSchedule — returns games of ALL statuses', function () {
    $mock = new CSSMockDatabase();
    $mock->teams = [cssTeam(10, 1)];
    $mock->games = [
        cssGame(['game_number' => 1, 'game_status' => 'Active',    'home_team_id' => 10]),
        cssGame(['game_number' => 2, 'game_status' => 'Completed', 'home_team_id' => 10, 'home_score' => 5, 'away_score' => 3]),
        cssGame(['game_number' => 3, 'game_status' => 'Cancelled', 'home_team_id' => 10]),
        cssGame(['game_number' => 4, 'game_status' => 'Postponed', 'home_team_id' => 10]),
    ];
    Database::setInstance($mock);

    $service = new CoachScheduleService($mock);
    $result = $service->getTeamSchedule(1);

    assert_equals(count($result), 4, 'Should return all 4 games regardless of status');

    $statuses = array_column($result, 'game_status');
    assert_true(in_array('Active', $statuses, true), 'Should include Active games');
    assert_true(in_array('Completed', $statuses, true), 'Should include Completed games');
    assert_true(in_array('Cancelled', $statuses, true), 'Should include Cancelled games');
    assert_true(in_array('Postponed', $statuses, true), 'Should include Postponed games');
});

register_test('getTeamSchedule — game row includes all expected fields', function () {
    $mock = new CSSMockDatabase();
    $mock->teams = [cssTeam(10, 1)];
    $mock->games = [
        cssGame([
            'game_number'    => 42,
            'game_date'      => '2026-06-01',
            'game_time'      => '19:30:00',
            'location'       => 'Main Field',
            'home_team_name' => 'Eagles',
            'away_team_name' => 'Hawks',
            'home_score'     => null,
            'away_score'     => null,
            'game_status'    => 'Active',
            'home_team_id'   => 10,
        ]),
    ];
    Database::setInstance($mock);

    $service = new CoachScheduleService($mock);
    $result = $service->getTeamSchedule(1);

    assert_equals(count($result), 1, 'Should return 1 game');
    $game = $result[0];

    assert_equals((int) $game['game_number'], 42, 'game_number');
    assert_equals($game['game_date'], '2026-06-01', 'game_date');
    assert_equals($game['game_time'], '19:30:00', 'game_time');
    assert_equals($game['location'], 'Main Field', 'location');
    assert_equals($game['home_team_name'], 'Eagles', 'home_team_name');
    assert_equals($game['away_team_name'], 'Hawks', 'away_team_name');
    assert_equals($game['game_status'], 'Active', 'game_status');
});

register_test('getTeamSchedule — score fields null when status is Active', function () {
    $mock = new CSSMockDatabase();
    $mock->teams = [cssTeam(10, 1)];
    $mock->games = [
        cssGame(['game_status' => 'Active', 'home_score' => null, 'away_score' => null, 'home_team_id' => 10]),
    ];
    Database::setInstance($mock);

    $service = new CoachScheduleService($mock);
    $result = $service->getTeamSchedule(1);

    assert_null($result[0]['home_score'], 'home_score should be null for Active game');
    assert_null($result[0]['away_score'], 'away_score should be null for Active game');
});

register_test('getTeamSchedule — score fields populated when status is Completed', function () {
    $mock = new CSSMockDatabase();
    $mock->teams = [cssTeam(10, 1)];
    $mock->games = [
        cssGame(['game_status' => 'Completed', 'home_score' => 7, 'away_score' => 3, 'home_team_id' => 10]),
    ];
    Database::setInstance($mock);

    $service = new CoachScheduleService($mock);
    $result = $service->getTeamSchedule(1);

    assert_equals($result[0]['home_score'], 7, 'home_score should be 7');
    assert_equals($result[0]['away_score'], 3, 'away_score should be 3');
});
