<?php
/**
 * Unit Tests: ScoreService
 *
 * Story 5.1 — ScoreService Backend
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ActivityLogger.php';
require_once __DIR__ . '/../../includes/TeamScope.php';
require_once __DIR__ . '/../../includes/GameTimeGate.php';
require_once __DIR__ . '/../../includes/ScoreService.php';

// sendNotification stub — not available outside the full app bootstrap
if (!function_exists('sendNotification')) {
    function sendNotification(string $template, ?int $gameId = null, $changeId = null, array $ctx = []): bool {
        return true;
    }
}

// ---------------------------------------------------------------------------
// Mock infrastructure
// ---------------------------------------------------------------------------

class SSMockStatement {
    public function rowCount(): int { return 1; }
}

class SSMockDatabase extends Database {
    /** @var array[] Teams for TeamScope (include 'owner_user_id' to assign team to a user) */
    public array $teams = [];
    /** @var array[] Game rows (must include game_date, game_time for GameTimeGate) */
    public array $games = [];
    /** @var array[] Captured query calls */
    public array $queryCalls = [];
    /** @var array[] Captured ActivityLogger events */
    public array $activityEvents = [];

    public function __construct() {
        // Bypass real PDO connection
    }

    public function fetchOne($sql, $params = []) {
        // loadGame — game by game_id
        if (stripos($sql, 'FROM games g') !== false && stripos($sql, 'WHERE g.game_id') !== false) {
            $id = (int) ($params['game_id'] ?? -1);
            foreach ($this->games as $g) {
                if ((int) $g['game_id'] === $id) {
                    return $g;
                }
            }
            return false;
        }
        return false;
    }

    public function fetchAll($sql, $params = []): array {
        // TeamScope::getScopedTeams — teams joined with team_owners
        if (stripos($sql, 'team_owners') !== false) {
            $userId = (int) ($params['user_id'] ?? -1);
            return array_values(
                array_filter($this->teams, fn($t) => (int) ($t['owner_user_id'] ?? -1) === $userId)
            );
        }

        // getEligibleGames — games for team IDs, excluding Completed
        if (stripos($sql, 'FROM games g') !== false && stripos($sql, 'game_status') !== false) {
            // Params: array_merge($teamIds, $teamIds) — deduce unique team IDs from first half
            $half    = (int) (count($params) / 2);
            $teamIds = array_map('intval', array_slice($params, 0, $half));

            return array_values(array_filter(
                $this->games,
                fn($g) => $g['game_status'] !== 'Completed'
                    && (in_array((int) $g['home_team_id'], $teamIds, true)
                        || in_array((int) $g['away_team_id'], $teamIds, true))
            ));
        }

        return [];
    }

    public function query($sql, $params = []) {
        $this->queryCalls[] = ['sql' => $sql, 'params' => $params];

        if (stripos($sql, 'INSERT INTO activity_log') !== false) {
            $this->activityEvents[] = [
                'event'   => $params['event'],
                'context' => json_decode($params['context'], true),
            ];
        }

        return new SSMockStatement();
    }
}

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

/** Past game involving team 10 (home) and team 20 (away), not yet scored */
function makePastGame(int $gameId = 1, int $homeTeamId = 10, int $awayTeamId = 20): array {
    return [
        'game_id'      => $gameId,
        'home_team_id' => $homeTeamId,
        'away_team_id' => $awayTeamId,
        'home_score'   => null,
        'away_score'   => null,
        'game_status'  => 'Active',
        'game_date'    => '2000-01-01',  // safely in the past
        'game_time'    => '09:00:00',
    ];
}

/** Future game */
function makeFutureGame(int $gameId = 2, int $homeTeamId = 10, int $awayTeamId = 20): array {
    return [
        'game_id'      => $gameId,
        'home_team_id' => $homeTeamId,
        'away_team_id' => $awayTeamId,
        'home_score'   => null,
        'away_score'   => null,
        'game_status'  => 'Active',
        'game_date'    => '2099-12-31',
        'game_time'    => '23:59:00',
    ];
}

/** Team row with owner assignment */
function makeTeam(int $teamId, int $ownerUserId): array {
    return ['team_id' => $teamId, 'team_name' => "Team {$teamId}", 'owner_user_id' => $ownerUserId];
}

/** Active game row with no schedule (simulates missing schedules row after LEFT JOIN) */
function makeActiveGameMissingSchedule(int $gameId, int $homeTeamId = 10, int $awayTeamId = 20): array {
    return [
        'game_id'      => $gameId,
        'home_team_id' => $homeTeamId,
        'away_team_id' => $awayTeamId,
        'home_score'   => null,
        'away_score'   => null,
        'game_status'  => 'Active',
    ];
}

// ---------------------------------------------------------------------------
// AC1 — submit() saves scores and sets status to Completed
// ---------------------------------------------------------------------------

register_test('AC1: submit saves home_score, away_score, and sets status to Completed', function () {
    $db = new SSMockDatabase();
    $db->teams[] = makeTeam(10, 5);
    $db->games[] = makePastGame(1, 10, 20);
    Database::setInstance($db);
    $service = new ScoreService($db);

    $service->submit(5, 1, 3, 7);

    $updateCall = null;
    foreach ($db->queryCalls as $call) {
        if (stripos($call['sql'], 'UPDATE games') !== false) {
            $updateCall = $call;
            break;
        }
    }

    assert_not_null($updateCall, 'submit must issue an UPDATE games query');
    assert_equals($updateCall['params']['home_score'], 3, 'home_score must be saved');
    assert_equals($updateCall['params']['away_score'], 7, 'away_score must be saved');
    assert_equals($updateCall['params']['status'], 'Completed', 'game_status must be set to Completed');
    assert_equals($updateCall['params']['game_id'], 1, 'correct game_id must be targeted');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC1 — ActivityLogger event score.submitted
// ---------------------------------------------------------------------------

register_test('AC1: submit logs score.submitted event with correct context', function () {
    $db = new SSMockDatabase();
    $db->teams[] = makeTeam(10, 5);
    $db->games[] = makePastGame(1, 10, 20);
    Database::setInstance($db);
    $service = new ScoreService($db);

    $service->submit(5, 1, 4, 2);

    $events = array_column($db->activityEvents, 'event');
    assert_true(in_array('score.submitted', $events, true), 'score.submitted must be logged');

    $logEntry = null;
    foreach ($db->activityEvents as $e) {
        if ($e['event'] === 'score.submitted') {
            $logEntry = $e;
            break;
        }
    }

    assert_equals($logEntry['context']['user_id'], 5, 'context must include user_id');
    assert_equals($logEntry['context']['game_id'], 1, 'context must include game_id');
    assert_equals($logEntry['context']['home_score'], 4, 'context must include home_score');
    assert_equals($logEntry['context']['away_score'], 2, 'context must include away_score');

    Database::setInstance(null);
});

register_test('Review patch: submit throws GameNotEligibleException when schedule date/time missing', function () {
    $db = new SSMockDatabase();
    $db->teams[] = makeTeam(10, 5);
    $db->games[] = makeActiveGameMissingSchedule(1, 10, 20);
    Database::setInstance($db);
    $service = new ScoreService($db);

    $thrown = false;
    try {
        $service->submit(5, 1, 1, 1);
    } catch (GameNotEligibleException $e) {
        $thrown = true;
    }

    assert_true($thrown, 'submit must throw when game_date/game_time are missing');
    $updateCalls = array_filter($db->queryCalls, fn($c) => stripos($c['sql'], 'UPDATE games') !== false);
    assert_equals(count($updateCalls), 0, 'no UPDATE when schedule is missing');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC2 — submit() throws TeamScopeViolationException
// ---------------------------------------------------------------------------

register_test('AC2: submit throws TeamScopeViolationException for game not involving coach team', function () {
    $db = new SSMockDatabase();
    $db->teams[] = makeTeam(99, 5);   // user 5 owns team 99
    $db->games[] = makePastGame(1, 10, 20);  // game involves teams 10 and 20 — not 99
    Database::setInstance($db);
    $service = new ScoreService($db);

    $thrown = false;
    try {
        $service->submit(5, 1, 3, 2);
    } catch (TeamScopeViolationException $e) {
        $thrown = true;
    }

    assert_true($thrown, 'submit must throw TeamScopeViolationException for wrong team');

    $updateCalls = array_filter($db->queryCalls, fn($c) => stripos($c['sql'], 'UPDATE games') !== false);
    assert_equals(count($updateCalls), 0, 'no score must be saved when TeamScopeViolationException is thrown');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC3 — submit() throws GameNotEligibleException
// ---------------------------------------------------------------------------

register_test('AC3: submit throws GameNotEligibleException for a future game', function () {
    $db = new SSMockDatabase();
    $db->teams[] = makeTeam(10, 5);
    $db->games[] = makeFutureGame(2, 10, 20);
    Database::setInstance($db);
    $service = new ScoreService($db);

    $thrown = false;
    try {
        $service->submit(5, 2, 1, 0);
    } catch (GameNotEligibleException $e) {
        $thrown = true;
    }

    assert_true($thrown, 'submit must throw GameNotEligibleException for future game');

    $updateCalls = array_filter($db->queryCalls, fn($c) => stripos($c['sql'], 'UPDATE games') !== false);
    assert_equals(count($updateCalls), 0, 'no score must be saved when GameNotEligibleException is thrown');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC4 — edit() updates scores and logs old and new values
// ---------------------------------------------------------------------------

register_test('AC4: edit updates scores and logs score.edited with old and new values', function () {
    $db = new SSMockDatabase();
    $db->teams[] = makeTeam(10, 5);

    $game              = makePastGame(3, 10, 20);
    $game['home_score'] = 5;
    $game['away_score'] = 3;
    $game['game_status'] = 'Completed';
    $db->games[]        = $game;

    Database::setInstance($db);
    $service = new ScoreService($db);

    $service->edit(5, 3, 6, 4);

    // UPDATE issued
    $updateCall = null;
    foreach ($db->queryCalls as $call) {
        if (stripos($call['sql'], 'UPDATE games') !== false) {
            $updateCall = $call;
            break;
        }
    }
    assert_not_null($updateCall, 'edit must issue an UPDATE games query');
    assert_equals($updateCall['params']['home_score'], 6, 'home_score must be updated');
    assert_equals($updateCall['params']['away_score'], 4, 'away_score must be updated');

    // score.edited event
    $events = array_column($db->activityEvents, 'event');
    assert_true(in_array('score.edited', $events, true), 'score.edited must be logged');

    $logEntry = null;
    foreach ($db->activityEvents as $e) {
        if ($e['event'] === 'score.edited') {
            $logEntry = $e;
            break;
        }
    }
    assert_equals($logEntry['context']['old_home_score'], 5, 'context must include old_home_score');
    assert_equals($logEntry['context']['old_away_score'], 3, 'context must include old_away_score');
    assert_equals($logEntry['context']['home_score'], 6, 'context must include new home_score');
    assert_equals($logEntry['context']['away_score'], 4, 'context must include new away_score');

    Database::setInstance(null);
});

register_test('Review patch: edit throws GameNotEligibleException when game is not Completed', function () {
    $db = new SSMockDatabase();
    $db->teams[] = makeTeam(10, 5);

    $game               = makePastGame(9, 10, 20);
    $game['home_score'] = 2;
    $game['away_score'] = 2;
    // game_status remains Active — must not allow edit path
    $db->games[] = $game;

    Database::setInstance($db);
    $service = new ScoreService($db);

    $thrown = false;
    try {
        $service->edit(5, 9, 3, 3);
    } catch (GameNotEligibleException $e) {
        $thrown = true;
    }

    assert_true($thrown, 'edit must throw when game_status is not Completed');
    $updateCalls = array_filter($db->queryCalls, fn($c) => stripos($c['sql'], 'UPDATE games') !== false);
    assert_equals(count($updateCalls), 0, 'no UPDATE when game is not completed');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC5 — getEligibleGames() returns only past/elapsed unscored games for coach team
// ---------------------------------------------------------------------------

register_test('AC5: getEligibleGames returns only past unscored games for coach team', function () {
    $db = new SSMockDatabase();
    $db->teams[] = makeTeam(10, 5);

    $past    = makePastGame(1, 10, 20);
    $future  = makeFutureGame(2, 10, 20);
    $completed = makePastGame(3, 10, 20);
    $completed['game_status'] = 'Completed';
    $otherTeam = makePastGame(4, 30, 40);  // team 5 doesn't own 30 or 40

    $db->games = [$past, $future, $completed, $otherTeam];
    Database::setInstance($db);
    $service = new ScoreService($db);

    $result = $service->getEligibleGames(5);

    assert_equals(count($result), 1, 'must return only the one past/unscored game for coach team');
    assert_equals((int) $result[0]['game_id'], 1, 'must return the correct eligible game');

    Database::setInstance(null);
});

register_test('Review patch: getEligibleGames excludes games without schedule fields', function () {
    $db = new SSMockDatabase();
    $db->teams[] = makeTeam(10, 5);

    $valid   = makePastGame(1, 10, 20);
    $noSched = makeActiveGameMissingSchedule(11, 10, 20);

    $db->games = [$valid, $noSched];
    Database::setInstance($db);
    $service = new ScoreService($db);

    $result = $service->getEligibleGames(5);

    assert_equals(count($result), 1, 'game without schedule must be excluded');
    assert_equals((int) $result[0]['game_id'], 1, 'only the fully scheduled past game is returned');

    Database::setInstance(null);
});

register_test('AC5: getEligibleGames returns empty array when no eligible games', function () {
    $db = new SSMockDatabase();
    $db->teams[] = makeTeam(10, 5);
    $db->games[] = makeFutureGame(2, 10, 20);  // only future game
    Database::setInstance($db);
    $service = new ScoreService($db);

    $result = $service->getEligibleGames(5);

    assert_equals($result, [], 'must return empty array when no eligible games');

    Database::setInstance(null);
});

register_test('AC5: getEligibleGames returns empty array when coach has no assigned teams', function () {
    $db = new SSMockDatabase();
    // No teams assigned to user 5
    $db->games[] = makePastGame(1, 10, 20);
    Database::setInstance($db);
    $service = new ScoreService($db);

    $result = $service->getEligibleGames(5);

    assert_equals($result, [], 'must return empty array when user has no assigned teams');

    Database::setInstance(null);
});
