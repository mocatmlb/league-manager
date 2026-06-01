<?php
/**
 * Unit Tests: RescheduleService
 *
 * Story 6.1 — RescheduleService Backend
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ActivityLogger.php';
require_once __DIR__ . '/../../includes/TeamScope.php';
// ScoreService declares TeamScopeViolationException; load it so class_exists guard works.
require_once __DIR__ . '/../../includes/ScoreService.php';
require_once __DIR__ . '/../../includes/RescheduleService.php';

// sendNotification stub
if (!function_exists('sendNotification')) {
    function sendNotification(string $template, ?int $gameId = null, $changeId = null, array $ctx = []): bool {
        return true;
    }
}

// getSetting stub — test cases override via $GLOBALS['_test_settings']
if (!function_exists('getSetting')) {
    function getSetting(string $key, string $default = ''): string {
        return (string) ($GLOBALS['_test_settings'][$key] ?? $default);
    }
}

// ---------------------------------------------------------------------------
// Mock infrastructure
// ---------------------------------------------------------------------------

class RSMockStatement {
    public int $rows;
    public function __construct(int $rows = 1) { $this->rows = $rows; }
    public function rowCount(): int { return $this->rows; }
}

class RSMockDatabase extends Database {
    /** @var array[]  Teams (include 'owner_user_id' and 'team_id') */
    public array $teams = [];
    /** @var array[]  Game rows */
    public array $games = [];
    /** @var array[]  Request rows (schedule_change_requests) */
    public array $requests = [];
    /** @var array[]  User rows (for requested_by fetch) */
    public array $users = [];
    /** @var array[]  Captured query() calls */
    public array $queryCalls = [];
    /** @var array[]  Captured insert() calls */
    public array $insertCalls = [];
    /** @var array[]  Captured ActivityLogger events */
    public array $activityEvents = [];
    /** @var int  Next auto-increment ID returned by insert() */
    public int $nextInsertId = 42;
    /** @var int  rowCount returned by the next UPDATE query (1 = success, 0 = conflict) */
    public int $nextUpdateRowCount = 1;
    /** @var bool  Whether beginTransaction was called */
    public bool $transactionStarted = false;
    /** @var bool  Whether commit was called */
    public bool $committed = false;
    /** @var bool  Whether rollback was called */
    public bool $rolledBack = false;

    public function __construct() {
        // Bypass real PDO connection
    }

    public function beginTransaction(): bool { $this->transactionStarted = true; return true; }
    public function commit(): bool           { $this->committed = true; return true; }
    public function rollback(): bool         { $this->rolledBack = true; return true; }

    public function fetchOne($sql, $params = []) {
        // Game lookup (submit + submitPostponement)
        if (stripos($sql, 'FROM games g') !== false && stripos($sql, 'game_id') !== false) {
            $id = (int) ($params['game_id'] ?? $params[0] ?? -1);
            foreach ($this->games as $g) {
                if ((int) $g['game_id'] === $id) {
                    return $g;
                }
            }
            return false;
        }

        // MAX(version_number) for schedule_history (submitPostponement)
        if (stripos($sql, 'MAX(version_number)') !== false) {
            return ['max_ver' => 1];
        }

        // User lookup (submit + submitPostponement — requested_by)
        if (stripos($sql, 'FROM users') !== false && stripos($sql, 'WHERE id') !== false) {
            $id = (int) ($params['id'] ?? -1);
            foreach ($this->users as $u) {
                if ((int) ($u['id'] ?? -1) === $id) {
                    return $u;
                }
            }
            return false;
        }

        // Request lookup (cancel)
        if (stripos($sql, 'FROM schedule_change_requests') !== false
            && stripos($sql, 'WHERE request_id') !== false) {
            $rid = (int) ($params['rid'] ?? -1);
            foreach ($this->requests as $r) {
                if ((int) $r['request_id'] === $rid) {
                    return $r;
                }
            }
            return false;
        }

        return false;
    }

    public function fetchAll($sql, $params = []): array {
        // TeamScope::getScopedTeams
        if (stripos($sql, 'team_owners') !== false) {
            $userId = (int) ($params['user_id'] ?? -1);
            return array_values(
                array_filter($this->teams, fn($t) => (int) ($t['owner_user_id'] ?? -1) === $userId)
            );
        }

        // getEligiblePostponementGames (must check before getEligibleGames — same base pattern)
        if (stripos($sql, 'FROM games g') !== false
            && stripos($sql, 'game_status NOT IN') !== false
            && stripos($sql, "request_type = 'Postponement'") !== false) {
            $teamIds = [];
            foreach ($params as $k => $v) {
                if (is_string($k) && preg_match('/^[ha]\d+$/', $k)) {
                    $teamIds[] = (int) $v;
                }
            }
            $teamIds = array_unique($teamIds);
            $uid = (int) ($params['uid'] ?? -1);

            // Pending postponement SCRs from this user exclude the game
            $pendingPostponementGameIds = [];
            foreach ($this->requests as $r) {
                if ($r['request_status'] === 'Pending'
                    && ($r['request_type'] ?? '') === 'Postponement'
                    && (int) ($r['submitted_by_user_id'] ?? -1) === $uid) {
                    $pendingPostponementGameIds[] = (int) $r['game_id'];
                }
            }

            return array_values(array_filter(
                $this->games,
                fn($g) => $g['game_status'] !== 'Completed'
                    && $g['game_status'] !== 'Cancelled'
                    && $g['game_status'] !== 'Postponed'
                    && ($g['home_score'] ?? null) === null
                    && ($g['away_score'] ?? null) === null
                    && !in_array((int) $g['game_id'], $pendingPostponementGameIds, true)
                    && (in_array((int) $g['home_team_id'], $teamIds, true)
                        || in_array((int) $g['away_team_id'], $teamIds, true))
            ));
        }

        // getEligibleGames
        if (stripos($sql, 'FROM games g') !== false
            && stripos($sql, 'game_status NOT IN') !== false) {
            // Extract team IDs from named params: keys starting with 'h' or 'a' followed by digits.
            $teamIds = [];
            foreach ($params as $k => $v) {
                if (is_string($k) && preg_match('/^[ha]\d+$/', $k)) {
                    $teamIds[] = (int) $v;
                }
            }
            $teamIds = array_unique($teamIds);

            // Exclude games that have a Pending request in the mock data
            $pendingGameIds = [];
            foreach ($this->requests as $r) {
                if ($r['request_status'] === 'Pending') {
                    $pendingGameIds[] = (int) $r['game_id'];
                }
            }

            return array_values(array_filter(
                $this->games,
                fn($g) => $g['game_status'] !== 'Completed'
                    && $g['game_status'] !== 'Cancelled'
                    && !in_array((int) $g['game_id'], $pendingGameIds, true)
                    && (in_array((int) $g['home_team_id'], $teamIds, true)
                        || in_array((int) $g['away_team_id'], $teamIds, true))
            ));
        }

        // getCoachRequests
        if (stripos($sql, 'FROM schedule_change_requests') !== false
            && stripos($sql, 'submitted_by_user_id') !== false) {
            $uid = (int) ($params['uid'] ?? -1);
            return array_values(
                array_filter($this->requests, fn($r) => (int) ($r['submitted_by_user_id'] ?? -1) === $uid)
            );
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

        $rows = stripos($sql, 'UPDATE schedule_change_requests') !== false
            ? $this->nextUpdateRowCount
            : 1;
        return new RSMockStatement($rows);
    }

    public function insert($table, $data) {
        $this->insertCalls[] = ['table' => $table, 'data' => $data];

        // Capture activity_log inserts the same way query() does
        if ($table === 'activity_log') {
            $this->activityEvents[] = [
                'event'   => $data['event'] ?? '',
                'context' => json_decode($data['context'] ?? '{}', true),
            ];
        }

        return $this->nextInsertId;
    }
}

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

function rsGame(int $gameId, int $homeTeamId = 10, int $awayTeamId = 20,
                string $status = 'Active', ?string $gameDate = '2099-12-31',
                ?string $rescheduleCutoff = null): array {
    return [
        'game_id'               => $gameId,
        'home_team_id'          => $homeTeamId,
        'away_team_id'          => $awayTeamId,
        'game_status'           => $status,
        'game_number'           => $gameId,
        'game_date'             => $gameDate,
        'game_time'             => '10:00:00',
        'location'              => 'Field A',
        'home_team_name'        => "Home {$homeTeamId}",
        'away_team_name'        => "Away {$awayTeamId}",
        'reschedule_cutoff_date' => $rescheduleCutoff,
    ];
}

function rsTeam(int $teamId, int $ownerUserId): array {
    return ['team_id' => $teamId, 'team_name' => "Team {$teamId}", 'owner_user_id' => $ownerUserId];
}

function rsUser(int $id): array {
    return ['id' => $id, 'first_name' => 'Jane', 'last_name' => 'Coach', 'phone' => '555-1234'];
}

function rsRequest(int $requestId, int $submittedByUserId, string $status = 'Pending'): array {
    return [
        'request_id'           => $requestId,
        'game_id'              => 1,
        'submitted_by_user_id' => $submittedByUserId,
        'request_status'       => $status,
        'request_type'         => 'Reschedule',
    ];
}

function rsPostponementRequest(int $requestId, int $gameId, int $submittedByUserId, string $status = 'Pending'): array {
    return [
        'request_id'           => $requestId,
        'game_id'              => $gameId,
        'submitted_by_user_id' => $submittedByUserId,
        'request_status'       => $status,
        'request_type'         => 'Postponement',
    ];
}

function rsScoredGame(int $gameId, int $homeTeamId = 10, int $awayTeamId = 20): array {
    $g = rsGame($gameId, $homeTeamId, $awayTeamId);
    $g['home_score'] = 3;
    $g['away_score'] = 2;
    return $g;
}

function rsRequestData(string $requestedDate = '2099-01-15'): array {
    return [
        'requested_date'     => $requestedDate,
        'requested_time'     => '14:00:00',
        'requested_location' => 'Field B',
        'reason'             => 'Field unavailable',
    ];
}

// ---------------------------------------------------------------------------
// AC1: submit() creates a pending request and returns the new ID
// ---------------------------------------------------------------------------

register_test('AC1: submit inserts Pending request and returns new request ID', function () {
    $db = new RSMockDatabase();
    $db->teams[]  = rsTeam(10, 5);
    $db->games[]  = rsGame(1, 10, 20);
    $db->users[]  = rsUser(5);
    $db->nextInsertId = 42;
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $id = $service->submit(5, 1, rsRequestData());

    assert_equals($id, 42, 'submit must return the new request ID');

    $insertCall = null;
    foreach ($db->insertCalls as $c) {
        if ($c['table'] === 'schedule_change_requests') {
            $insertCall = $c;
            break;
        }
    }
    assert_not_null($insertCall, 'submit must INSERT into schedule_change_requests');
    assert_equals($insertCall['data']['request_status'], 'Pending', 'request_status must be Pending');
    assert_equals($insertCall['data']['request_type'], 'Reschedule', 'request_type must be Reschedule');
    assert_equals($insertCall['data']['submitted_by_user_id'], 5, 'submitted_by_user_id must be set');
    assert_equals($insertCall['data']['game_id'], 1, 'game_id must be set');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC1: ActivityLogger event reschedule.request_submitted
// ---------------------------------------------------------------------------

register_test('AC1: submit logs reschedule.request_submitted with user_id, game_id, request_id', function () {
    $db = new RSMockDatabase();
    $db->teams[]  = rsTeam(10, 5);
    $db->games[]  = rsGame(1, 10, 20);
    $db->users[]  = rsUser(5);
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $service->submit(5, 1, rsRequestData());

    $events = array_column($db->activityEvents, 'event');
    assert_true(in_array('reschedule.request_submitted', $events, true),
        'reschedule.request_submitted must be logged');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC2: submit() throws TeamScopeViolationException for wrong team
// ---------------------------------------------------------------------------

register_test('AC2: submit throws TeamScopeViolationException for game not involving coach team', function () {
    $db = new RSMockDatabase();
    $db->teams[]  = rsTeam(99, 5); // user 5 owns team 99; game involves 10 and 20
    $db->games[]  = rsGame(1, 10, 20);
    $db->users[]  = rsUser(5);
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $thrown = false;
    try {
        $service->submit(5, 1, rsRequestData());
    } catch (TeamScopeViolationException $e) {
        $thrown = true;
    }

    assert_true($thrown, 'submit must throw TeamScopeViolationException for wrong team');

    $insertCalls = array_filter($db->insertCalls, fn($c) => $c['table'] === 'schedule_change_requests');
    assert_equals(count($insertCalls), 0, 'no row must be inserted on scope violation');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC3: cancel() updates status to Denied for valid request
// ---------------------------------------------------------------------------

register_test('AC3: cancel sets request_status to Denied for valid Pending request', function () {
    $db = new RSMockDatabase();
    $db->requests[] = rsRequest(7, 5, 'Pending');
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $service->cancel(7, 5);

    $updateCall = null;
    foreach ($db->queryCalls as $call) {
        if (stripos($call['sql'], "request_status = 'Denied'") !== false) {
            $updateCall = $call;
            break;
        }
    }
    assert_not_null($updateCall, 'cancel must issue an UPDATE setting status to Denied');
    assert_equals((int) $updateCall['params']['rid'], 7, 'correct request_id must be targeted');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC3: ActivityLogger event reschedule.request_cancelled
// ---------------------------------------------------------------------------

register_test('AC3: cancel logs reschedule.request_cancelled', function () {
    $db = new RSMockDatabase();
    $db->requests[] = rsRequest(7, 5, 'Pending');
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $service->cancel(7, 5);

    $events = array_column($db->activityEvents, 'event');
    assert_true(in_array('reschedule.request_cancelled', $events, true),
        'reschedule.request_cancelled must be logged');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC4: cancel() throws when request is not Pending
// ---------------------------------------------------------------------------

register_test('AC4: cancel throws RequestNotCancellableException when status is Approved', function () {
    $db = new RSMockDatabase();
    $db->requests[] = rsRequest(8, 5, 'Approved');
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $thrown = false;
    try {
        $service->cancel(8, 5);
    } catch (RequestNotCancellableException $e) {
        $thrown = true;
    }

    assert_true($thrown, 'cancel must throw for Approved request');

    Database::setInstance(null);
});

register_test('AC4: cancel throws RequestNotCancellableException when status is Denied', function () {
    $db = new RSMockDatabase();
    $db->requests[] = rsRequest(9, 5, 'Denied');
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $thrown = false;
    try {
        $service->cancel(9, 5);
    } catch (RequestNotCancellableException $e) {
        $thrown = true;
    }

    assert_true($thrown, 'cancel must throw for already-Denied request');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC5: cancel() throws when request belongs to a different user
// ---------------------------------------------------------------------------

register_test('AC5: cancel throws RequestNotCancellableException when user does not own request', function () {
    $db = new RSMockDatabase();
    $db->requests[] = rsRequest(10, 99, 'Pending'); // owned by user 99
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $thrown = false;
    try {
        $service->cancel(10, 5); // user 5 tries to cancel
    } catch (RequestNotCancellableException $e) {
        $thrown = true;
    }

    assert_true($thrown, 'cancel must throw when request belongs to a different user');

    $updateCalls = array_filter($db->queryCalls,
        fn($c) => stripos($c['sql'], "request_status = 'Denied'") !== false);
    assert_equals(count($updateCalls), 0, 'no UPDATE must occur on ownership violation');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC6: getEligibleGames() returns team-scoped, non-terminal, scheduled games
// ---------------------------------------------------------------------------

register_test('AC6: getEligibleGames returns only Active games for coach team', function () {
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);

    $active    = rsGame(1, 10, 20, 'Active');
    $completed = rsGame(2, 10, 20, 'Completed');
    $cancelled = rsGame(3, 10, 20, 'Cancelled');
    $otherTeam = rsGame(4, 30, 40, 'Active');
    $db->games = [$active, $completed, $cancelled, $otherTeam];

    Database::setInstance($db);
    $service = new RescheduleService($db);

    $result = $service->getEligibleGames(5);

    assert_equals(count($result), 1, 'must return only Active game for coach team');
    assert_equals((int) $result[0]['game_id'], 1, 'must return the correct game');

    Database::setInstance(null);
});

register_test('AC6: getEligibleGames excludes games with NULL game_date', function () {
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);

    $withDate    = rsGame(1, 10, 20, 'Active', '2099-12-31');
    $withoutDate = rsGame(2, 10, 20, 'Active', null);
    $db->games   = [$withDate, $withoutDate];

    Database::setInstance($db);
    $service = new RescheduleService($db);

    $result = $service->getEligibleGames(5);

    assert_equals(count($result), 1, 'game with NULL game_date must be excluded');
    assert_equals((int) $result[0]['game_id'], 1, 'only the scheduled game is returned');

    Database::setInstance(null);
});

register_test('AC6: getEligibleGames returns empty array when coach has no teams', function () {
    $db = new RSMockDatabase();
    // No teams for user 5
    $db->games[] = rsGame(1, 10, 20);
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $result = $service->getEligibleGames(5);

    assert_equals($result, [], 'must return [] when user has no assigned teams');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC7: getCoachRequests() returns all requests for the coach
// ---------------------------------------------------------------------------

register_test('AC7: getCoachRequests returns all requests submitted by the coach', function () {
    $db = new RSMockDatabase();
    $db->requests[] = rsRequest(1, 5, 'Pending');
    $db->requests[] = rsRequest(2, 5, 'Approved');
    $db->requests[] = rsRequest(3, 99, 'Pending'); // different user
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $result = $service->getCoachRequests(5);

    assert_equals(count($result), 2, 'must return only requests submitted by user 5');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC2 (Story 10.1) — submit() wraps insert + log in a transaction
// ---------------------------------------------------------------------------

register_test('AC2-10: submit wraps insert and ActivityLogger in a transaction', function () {
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);
    $db->games[] = rsGame(1, 10, 20);
    $db->users[] = rsUser(5);
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $service->submit(5, 1, rsRequestData());

    assert_true($db->transactionStarted, 'submit must call beginTransaction');
    assert_true($db->committed, 'submit must commit on success');
    assert_true(!$db->rolledBack, 'submit must not rollback on success');

    Database::setInstance(null);
});

register_test('AC2-10: submit rolls back transaction when insert fails (DB constraint)', function () {
    // Simulate a DB failure during the schedule_change_requests INSERT.
    // Note: ActivityLogger::log() swallows its own exceptions by design, so failures
    // inside it cannot propagate. The transaction guard covers INSERT-level failures.
    $db = new class extends RSMockDatabase {
        public function insert($table, $data) {
            if ($table === 'schedule_change_requests') {
                throw new RuntimeException('Duplicate key constraint violation');
            }
            return parent::insert($table, $data);
        }
    };
    $db->teams[] = rsTeam(10, 5);
    $db->games[] = rsGame(1, 10, 20);
    $db->users[] = rsUser(5);
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $thrown = false;
    try {
        $service->submit(5, 1, rsRequestData());
    } catch (RuntimeException $e) {
        $thrown = true;
    }

    assert_true($thrown, 'submit must re-throw when a DB failure occurs inside the transaction');
    assert_true($db->rolledBack, 'submit must rollback when a DB failure occurs inside the transaction');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// AC3 (Story 10.1) — cancel() resolves race condition via rowCount check
// ---------------------------------------------------------------------------

register_test('AC3-10: cancel throws RequestNotCancellableException when rowCount is 0 (concurrent cancel)', function () {
    $db = new RSMockDatabase();
    $db->requests[] = rsRequest(7, 5, 'Pending');
    $db->nextUpdateRowCount = 0; // simulate concurrent cancel already applied
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $thrown = false;
    try {
        $service->cancel(7, 5);
    } catch (RequestNotCancellableException $e) {
        $thrown = true;
    }

    assert_true($thrown, 'cancel must throw when rowCount is 0 (concurrent cancel race)');

    $logEvents = array_column($db->activityEvents, 'event');
    assert_true(
        !in_array('reschedule.request_cancelled', $logEvents, true),
        'reschedule.request_cancelled must NOT be logged when conflict is detected'
    );

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// Submission window enforcement tests
// ---------------------------------------------------------------------------

register_test('Window: all settings 0/blank — submit succeeds (no restriction)', function () {
    $GLOBALS['_test_settings'] = [
        'reschedule_pre_game_hours'  => '0',
        'reschedule_post_game_hours' => '0',
        'timezone'                   => 'UTC',
    ];
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);
    $db->games[] = rsGame(1, 10, 20, 'Active', '2099-12-31');
    $db->users[] = rsUser(5);
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $id = $service->submit(5, 1, rsRequestData('2099-12-30'));
    assert_equals($id, 42, 'submit must return the new request ID');

    Database::setInstance(null);
    unset($GLOBALS['_test_settings']);
});

register_test('Window: pre-game blackout hit — submit throws SubmissionWindowException', function () {
    // Game is 12 hours from now; blackout = 24 hours.
    $gameAt = (new DateTime('now', new DateTimeZone('UTC')))->modify('+12 hours');
    $GLOBALS['_test_settings'] = [
        'reschedule_pre_game_hours'  => '24',
        'reschedule_post_game_hours' => '0',
        'timezone'                   => 'UTC',
    ];
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);
    $game = rsGame(1, 10, 20, 'Active', $gameAt->format('Y-m-d'));
    $game['game_time'] = $gameAt->format('H:i:s');
    $db->games[] = $game;
    $db->users[] = rsUser(5);
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $thrown = false;
    try {
        $service->submit(5, 1, rsRequestData('2099-01-01'));
    } catch (SubmissionWindowException $e) {
        $thrown = true;
    }
    assert_true($thrown, 'submit must throw SubmissionWindowException when inside pre-game blackout');

    Database::setInstance(null);
    unset($GLOBALS['_test_settings']);
});

register_test('Window: post-game blackout hit — submit throws SubmissionWindowException', function () {
    // Game started 3 hours ago; post-game window = 6 hours → now is inside [gameAt, gameAt+6h].
    $gameAt = (new DateTime('now', new DateTimeZone('UTC')))->modify('-3 hours');
    $GLOBALS['_test_settings'] = [
        'reschedule_pre_game_hours'  => '0',
        'reschedule_post_game_hours' => '6',
        'timezone'                   => 'UTC',
    ];
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);
    $game = rsGame(1, 10, 20, 'Active', $gameAt->format('Y-m-d'));
    $game['game_time'] = $gameAt->format('H:i:s');
    $db->games[] = $game;
    $db->users[] = rsUser(5);
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $thrown = false;
    try {
        $service->submit(5, 1, rsRequestData('2099-01-01'));
    } catch (SubmissionWindowException $e) {
        $thrown = true;
    }
    assert_true($thrown, 'submit must throw SubmissionWindowException when inside post-game blackout window');

    Database::setInstance(null);
    unset($GLOBALS['_test_settings']);
});

register_test('Window: past game outside post-game window — submit succeeds', function () {
    // Game was 28 days ago; post-game window = 2 hours → now is well outside the window.
    $gameAt = (new DateTime('now', new DateTimeZone('UTC')))->modify('-28 days');
    $GLOBALS['_test_settings'] = [
        'reschedule_pre_game_hours'  => '24',
        'reschedule_post_game_hours' => '2',
        'timezone'                   => 'UTC',
    ];
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);
    $game = rsGame(1, 10, 20, 'Active', $gameAt->format('Y-m-d'));
    $game['game_time'] = $gameAt->format('H:i:s');
    $db->games[] = $game;
    $db->users[] = rsUser(5);
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $id = $service->submit(5, 1, rsRequestData('2099-01-01'));
    assert_equals($id, 42, 'submit must succeed for a past game that is outside the blackout window');

    Database::setInstance(null);
    unset($GLOBALS['_test_settings']);
});

register_test('Window: season cutoff hit — submit throws SubmissionWindowException', function () {
    $GLOBALS['_test_settings'] = [
        'reschedule_pre_game_hours'  => '0',
        'reschedule_post_game_hours' => '0',
        'timezone'                   => 'UTC',
    ];
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);
    // Game is far in the future but season cutoff is 2025-01-01
    $db->games[] = rsGame(1, 10, 20, 'Active', '2099-12-31', '2025-01-01');
    $db->users[] = rsUser(5);
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $thrown = false;
    try {
        // Requested date is beyond the cutoff
        $service->submit(5, 1, rsRequestData('2025-06-01'));
    } catch (SubmissionWindowException $e) {
        $thrown = true;
    }
    assert_true($thrown, 'submit must throw SubmissionWindowException when requested date exceeds season cutoff');

    Database::setInstance(null);
    unset($GLOBALS['_test_settings']);
});

register_test('Window: season cutoff NULL — no cutoff enforced', function () {
    $GLOBALS['_test_settings'] = [
        'reschedule_pre_game_hours'  => '0',
        'reschedule_post_game_hours' => '0',
        'timezone'                   => 'UTC',
    ];
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);
    // reschedule_cutoff_date is null
    $db->games[] = rsGame(1, 10, 20, 'Active', '2099-12-31', null);
    $db->users[] = rsUser(5);
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $id = $service->submit(5, 1, rsRequestData('2099-11-01'));
    assert_equals($id, 42, 'submit must succeed when season has no cutoff date');

    Database::setInstance(null);
    unset($GLOBALS['_test_settings']);
});

register_test('Window: getEligibleGames excludes game inside pre-game blackout', function () {
    $gameAt = (new DateTime('now', new DateTimeZone('UTC')))->modify('+2 hours');
    $GLOBALS['_test_settings'] = [
        'reschedule_pre_game_hours'  => '24',
        'reschedule_post_game_hours' => '0',
        'timezone'                   => 'UTC',
    ];
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);
    $game = rsGame(1, 10, 20, 'Active', $gameAt->format('Y-m-d'));
    $game['game_time'] = $gameAt->format('H:i:s');
    $db->games[] = $game;
    // Add a second game far in future to confirm it's still returned
    $db->games[] = rsGame(2, 10, 20, 'Active', '2099-12-31');
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $result = $service->getEligibleGames(5);
    $ids = array_column($result, 'game_id');
    assert_true(!in_array(1, $ids, true), 'game inside pre-game blackout must be excluded');
    assert_true(in_array(2, $ids, true), 'game outside blackout must remain eligible');

    Database::setInstance(null);
    unset($GLOBALS['_test_settings']);
});

// ---------------------------------------------------------------------------
// Story 18-1: getEligiblePostponementGames tests
// ---------------------------------------------------------------------------

register_test('Postpone: getEligiblePostponementGames excludes Completed, Cancelled, Postponed', function () {
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);
    $db->games[] = rsGame(1, 10, 20, 'Active');
    $db->games[] = rsGame(2, 10, 20, 'Completed');
    $db->games[] = rsGame(3, 10, 20, 'Cancelled');
    $db->games[] = rsGame(4, 10, 20, 'Postponed');
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $result = $service->getEligiblePostponementGames(5);
    $ids = array_column($result, 'game_id');

    assert_true(in_array(1, $ids, true), 'Active game must be eligible');
    assert_true(!in_array(2, $ids, true), 'Completed game must be excluded');
    assert_true(!in_array(3, $ids, true), 'Cancelled game must be excluded');
    assert_true(!in_array(4, $ids, true), 'Already-Postponed game must be excluded');

    Database::setInstance(null);
});

register_test('Postpone: getEligiblePostponementGames excludes scored games', function () {
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);
    $db->games[] = rsGame(1, 10, 20, 'Active');
    $db->games[] = rsScoredGame(2, 10, 20);
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $result = $service->getEligiblePostponementGames(5);
    $ids = array_column($result, 'game_id');

    assert_true(in_array(1, $ids, true), 'Unscored game must be eligible');
    assert_true(!in_array(2, $ids, true), 'Scored game must be excluded');

    Database::setInstance(null);
});

register_test('Postpone: getEligiblePostponementGames excludes game with Pending postponement from same user', function () {
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);
    $db->games[] = rsGame(1, 10, 20, 'Active');
    $db->games[] = rsGame(2, 10, 20, 'Active');
    // Game 1 has a pending postponement from user 5
    $db->requests[] = rsPostponementRequest(99, 1, 5, 'Pending');
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $result = $service->getEligiblePostponementGames(5);
    $ids = array_column($result, 'game_id');

    assert_true(!in_array(1, $ids, true), 'Game with pending postponement from same user must be excluded');
    assert_true(in_array(2, $ids, true), 'Game without pending postponement must remain eligible');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// Story 18-1: submitPostponement tests
// ---------------------------------------------------------------------------

register_test('Postpone: submitPostponement auto-approve updates game_status to Postponed', function () {
    $GLOBALS['_test_settings'] = ['postponement_auto_approve' => '1'];
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);
    $db->games[] = rsGame(1, 10, 20, 'Active');
    $db->users[] = rsUser(5);
    $db->nextInsertId = 77;
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $service->submitPostponement(5, 1, 'Field flooded');

    $gameUpdate = null;
    foreach ($db->queryCalls as $c) {
        if (stripos($c['sql'], 'UPDATE games') !== false && stripos($c['sql'], 'game_status') !== false) {
            $gameUpdate = $c;
            break;
        }
    }
    assert_not_null($gameUpdate, 'submitPostponement auto-approve must UPDATE games table');

    Database::setInstance(null);
    unset($GLOBALS['_test_settings']);
});

register_test('Postpone: submitPostponement auto-approve creates Approved SCR with Postponement type', function () {
    $GLOBALS['_test_settings'] = ['postponement_auto_approve' => '1'];
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);
    $db->games[] = rsGame(1, 10, 20, 'Active');
    $db->users[] = rsUser(5);
    $db->nextInsertId = 77;
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $id = $service->submitPostponement(5, 1, 'Field flooded');

    assert_equals($id, 77, 'submitPostponement must return the new request ID');

    $scrInsert = null;
    foreach ($db->insertCalls as $c) {
        if ($c['table'] === 'schedule_change_requests') {
            $scrInsert = $c;
            break;
        }
    }
    assert_not_null($scrInsert, 'submitPostponement must INSERT into schedule_change_requests');
    assert_equals($scrInsert['data']['request_type'], 'Postponement', 'request_type must be Postponement');
    assert_equals($scrInsert['data']['request_status'], 'Approved', 'auto-approve path must set Approved');

    Database::setInstance(null);
    unset($GLOBALS['_test_settings']);
});

register_test('Postpone: submitPostponement pending does NOT update game_status', function () {
    $GLOBALS['_test_settings'] = ['postponement_auto_approve' => '0'];
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);
    $db->games[] = rsGame(1, 10, 20, 'Active');
    $db->users[] = rsUser(5);
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $service->submitPostponement(5, 1, 'Rain forecast');

    $gameUpdate = null;
    foreach ($db->queryCalls as $c) {
        if (stripos($c['sql'], 'UPDATE games') !== false && stripos($c['sql'], 'game_status') !== false) {
            $gameUpdate = $c;
            break;
        }
    }
    assert_true($gameUpdate === null, 'pending path must NOT update games table');

    Database::setInstance(null);
    unset($GLOBALS['_test_settings']);
});

register_test('Postpone: submitPostponement pending creates Pending SCR', function () {
    $GLOBALS['_test_settings'] = ['postponement_auto_approve' => '0'];
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);
    $db->games[] = rsGame(1, 10, 20, 'Active');
    $db->users[] = rsUser(5);
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $service->submitPostponement(5, 1, 'Rain forecast');

    $scrInsert = null;
    foreach ($db->insertCalls as $c) {
        if ($c['table'] === 'schedule_change_requests') {
            $scrInsert = $c;
            break;
        }
    }
    assert_not_null($scrInsert, 'submitPostponement pending must INSERT into schedule_change_requests');
    assert_equals($scrInsert['data']['request_type'], 'Postponement', 'request_type must be Postponement');
    assert_equals($scrInsert['data']['request_status'], 'Pending', 'pending path must set Pending');

    Database::setInstance(null);
    unset($GLOBALS['_test_settings']);
});

register_test('Postpone: submitPostponement throws TeamScopeViolationException on wrong team', function () {
    $GLOBALS['_test_settings'] = ['postponement_auto_approve' => '1'];
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);  // user 5 owns team 10
    $db->games[] = rsGame(1, 20, 30, 'Active');  // game involves teams 20 and 30, not 10
    $db->users[] = rsUser(5);
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $thrown = false;
    try {
        $service->submitPostponement(5, 1, 'Rain forecast');
    } catch (TeamScopeViolationException $e) {
        $thrown = true;
    }
    assert_true($thrown, 'submitPostponement must throw TeamScopeViolationException for wrong team');

    Database::setInstance(null);
    unset($GLOBALS['_test_settings']);
});

register_test('Postpone: submitPostponement throws InvalidArgumentException on blank reason', function () {
    $GLOBALS['_test_settings'] = ['postponement_auto_approve' => '1'];
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);
    $db->games[] = rsGame(1, 10, 20, 'Active');
    $db->users[] = rsUser(5);
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $thrown = false;
    try {
        $service->submitPostponement(5, 1, '   ');
    } catch (InvalidArgumentException $e) {
        $thrown = true;
    }
    assert_true($thrown, 'submitPostponement must throw InvalidArgumentException for blank reason');

    Database::setInstance(null);
    unset($GLOBALS['_test_settings']);
});

register_test('Postpone: submitPostponement throws on Pending Change game status', function () {
    $GLOBALS['_test_settings'] = ['postponement_auto_approve' => '1'];
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);
    $db->games[] = rsGame(1, 10, 20, 'Pending Change');
    $db->users[] = rsUser(5);
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $thrown = false;
    try {
        $service->submitPostponement(5, 1, 'Field flooded');
    } catch (TeamScopeViolationException $e) {
        $thrown = true;
    }
    assert_true($thrown, 'submitPostponement must reject Pending Change games');

    Database::setInstance(null);
    unset($GLOBALS['_test_settings']);
});

register_test('Postpone: submitPostponement throws on duplicate Pending postponement', function () {
    $GLOBALS['_test_settings'] = ['postponement_auto_approve' => '1'];
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);
    $db->games[] = rsGame(1, 10, 20, 'Active');
    $db->users[] = rsUser(5);
    $db->requests[] = rsPostponementRequest(99, 1, 5, 'Pending');
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $thrown = false;
    try {
        $service->submitPostponement(5, 1, 'Field flooded');
    } catch (TeamScopeViolationException $e) {
        $thrown = true;
    }
    assert_true($thrown, 'submitPostponement must reject duplicate Pending postponement');

    Database::setInstance(null);
    unset($GLOBALS['_test_settings']);
});

register_test('Postpone: submitPostponement throws on partially-scored game', function () {
    $GLOBALS['_test_settings'] = ['postponement_auto_approve' => '1'];
    $db = new RSMockDatabase();
    $db->teams[] = rsTeam(10, 5);
    $g = rsGame(1, 10, 20, 'Active');
    $g['home_score'] = 3;
    $g['away_score'] = null;
    $db->games[] = $g;
    $db->users[] = rsUser(5);
    Database::setInstance($db);
    $service = new RescheduleService($db);

    $thrown = false;
    try {
        $service->submitPostponement(5, 1, 'Field flooded');
    } catch (TeamScopeViolationException $e) {
        $thrown = true;
    }
    assert_true($thrown, 'submitPostponement must reject game with one score set');

    Database::setInstance(null);
    unset($GLOBALS['_test_settings']);
});
