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

// ---------------------------------------------------------------------------
// Mock infrastructure
// ---------------------------------------------------------------------------

class RSMockStatement {
    public function rowCount(): int { return 1; }
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

    public function __construct() {
        // Bypass real PDO connection
    }

    public function fetchOne($sql, $params = []) {
        // Game lookup (submit)
        if (stripos($sql, 'FROM games g') !== false && stripos($sql, 'WHERE g.game_id') !== false) {
            $id = (int) ($params['game_id'] ?? -1);
            foreach ($this->games as $g) {
                if ((int) $g['game_id'] === $id) {
                    return $g;
                }
            }
            return false;
        }

        // User lookup (submit — requested_by)
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

        // getEligibleGames
        if (stripos($sql, 'FROM games g') !== false
            && stripos($sql, 'game_status NOT IN') !== false) {
            // Params may have named keys (uid) + indexed team IDs.
            // Extract only the indexed (numeric) values as team IDs.
            $indexed = array_values(array_filter(
                $params,
                fn($k) => is_int($k),
                ARRAY_FILTER_USE_KEY
            ));
            $half    = (int) (count($indexed) / 2);
            $teamIds = array_map('intval', array_slice($indexed, 0, $half));

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

        return new RSMockStatement();
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
                string $status = 'Active', ?string $gameDate = '2099-12-31'): array {
    return [
        'game_id'      => $gameId,
        'home_team_id' => $homeTeamId,
        'away_team_id' => $awayTeamId,
        'game_status'  => $status,
        'game_number'  => $gameId,
        'game_date'    => $gameDate,
        'game_time'    => '10:00:00',
        'location'     => 'Field A',
        'home_team_name' => "Home {$homeTeamId}",
        'away_team_name' => "Away {$awayTeamId}",
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
    ];
}

function rsRequestData(): array {
    return [
        'requested_date'     => '2099-01-15',
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
