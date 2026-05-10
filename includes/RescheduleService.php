<?php
/**
 * District 8 Travel League - Reschedule Service
 *
 * Handles team-scoped reschedule request creation, status tracking, and cancellation.
 * All scoping rules are enforced server-side via TeamScope.
 *
 * Usage: $service = new RescheduleService(Database::getInstance());
 */

if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

if (!class_exists('RequestNotCancellableException')) {
    class RequestNotCancellableException extends RuntimeException {}
}

// TeamScopeViolationException is declared in ScoreService.php; guard against redeclaration.
if (!class_exists('TeamScopeViolationException')) {
    class TeamScopeViolationException extends RuntimeException {}
}

class RescheduleService {

    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * Submit a reschedule request for a game owned by the coach's team.
     *
     * @param array $requestData  Required keys: requested_date, requested_time,
     *                            requested_location, reason
     * @return int  New request ID
     * @throws TeamScopeViolationException  Game does not involve coach's team
     */
    public function submit(int $userId, int $gameId, array $requestData): int {
        if ($userId <= 0 || $gameId <= 0) {
            throw new TeamScopeViolationException('Invalid user or game');
        }
        $teams   = TeamScope::getScopedTeams($userId);
        $teamIds = array_map('intval', array_column($teams, 'team_id'));

        $game = $this->db->fetchOne(
            'SELECT g.*, s.game_date, s.game_time, s.location
             FROM games g
             LEFT JOIN schedules s ON g.game_id = s.game_id
             WHERE g.game_id = :game_id',
            ['game_id' => $gameId]
        );

        if (empty($game)) {
            throw new TeamScopeViolationException('Game not found');
        }

        $homeId = (int) $game['home_team_id'];
        $awayId = (int) $game['away_team_id'];
        if (!in_array($homeId, $teamIds, true) && !in_array($awayId, $teamIds, true)) {
            throw new TeamScopeViolationException('Game does not involve a team owned by user');
        }

        if (in_array($game['game_status'], ['Completed', 'Cancelled'], true)) {
            throw new TeamScopeViolationException('Game is not eligible for reschedule');
        }

        if (empty($game['game_date'])) {
            throw new TeamScopeViolationException('Game has no scheduled date');
        }

        // Fetch user info for legacy email template compatibility (requested_by column).
        $user = $this->db->fetchOne(
            'SELECT first_name, last_name, phone FROM users WHERE id = :id',
            ['id' => $userId]
        );
        $requestedBy = '';
        if (!empty($user)) {
            $requestedBy = trim($user['first_name'] . ' ' . $user['last_name'])
                . ' (' . ($user['phone'] ?? '') . ')';
        }

        $requestedDate     = $requestData['requested_date'] ?? '';
        $requestedTime     = $requestData['requested_time'] ?? '';
        $requestedLocation = $requestData['requested_location'] ?? '';
        $reason            = $requestData['reason'] ?? '';

        if ($requestedDate === '' || $requestedTime === '' || $requestedLocation === '' || $reason === '') {
            throw new InvalidArgumentException('All request fields (date, time, location, reason) are required');
        }

        $existing = $this->db->fetchOne(
            'SELECT 1 FROM schedule_change_requests
             WHERE game_id = :game_id AND submitted_by_user_id = :uid AND request_status = :status
             LIMIT 1',
            ['game_id' => $gameId, 'uid' => $userId, 'status' => 'Pending']
        );
        if (!empty($existing)) {
            throw new RequestNotCancellableException('A pending request already exists for this game');
        }

        $this->db->beginTransaction();
        try {
            $requestId = (int) $this->db->insert('schedule_change_requests', [
                'game_id'              => $gameId,
                'submitted_by_user_id' => $userId,
                'requested_by'         => $requestedBy,
                'request_type'         => 'Reschedule',
                'original_date'        => $game['game_date'] ?? null,
                'original_time'        => $game['game_time'] ?? null,
                'original_location'    => $game['location'] ?? null,
                'requested_date'       => $requestedDate,
                'requested_time'       => $requestedTime,
                'requested_location'   => $requestedLocation,
                'reason'               => $reason,
                'request_status'       => 'Pending',
            ]);

            ActivityLogger::log('reschedule.request_submitted', [
                'user_id'    => $userId,
                'game_id'    => $gameId,
                'request_id' => $requestId,
            ]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        // Notification is fire-and-forget — runs after commit so a failure never orphans the row.
        try {
            if (function_exists('sendNotification')) {
                sendNotification('onScheduleChangeRequest', $gameId, $requestId);
            }
        } catch (Throwable $e) {
            error_log('[RescheduleService] Notification failed — game_id=' . $gameId
                . ' request_id=' . $requestId . ' error=' . $e->getMessage());
        }

        return $requestId;
    }

    /**
     * Cancel a pending reschedule request.
     * Maps to 'Denied' since the ENUM has no 'Cancelled' value.
     *
     * @throws RequestNotCancellableException  Not found, wrong user, or not Pending
     */
    public function cancel(int $requestId, int $userId): void {
        $row = $this->db->fetchOne(
            'SELECT * FROM schedule_change_requests WHERE request_id = :rid',
            ['rid' => $requestId]
        );

        if (empty($row)) {
            throw new RequestNotCancellableException('Request not found');
        }

        if ((int) $row['submitted_by_user_id'] !== $userId) {
            throw new RequestNotCancellableException(
                'Request does not belong to this user'
            );
        }

        if ($row['request_status'] !== 'Pending') {
            throw new RequestNotCancellableException(
                'Only Pending requests can be cancelled'
            );
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->query(
                "UPDATE schedule_change_requests SET request_status = 'Denied' WHERE request_id = :rid AND request_status = 'Pending'",
                ['rid' => $requestId]
            );

            // rowCount = 0 means a concurrent cancel already applied — both cannot succeed.
            if ($stmt->rowCount() === 0) {
                throw new RequestNotCancellableException('Request was already processed by a concurrent action');
            }

            ActivityLogger::log('reschedule.request_cancelled', [
                'user_id'    => $userId,
                'request_id' => $requestId,
            ]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Return games eligible for reschedule requests for the coach's assigned teams.
     * Excludes Completed, Cancelled, games with no schedule row, and games with existing Pending requests.
     *
     * @return array
     */
    public function getEligibleGames(int $userId): array {
        $teams = TeamScope::getScopedTeams($userId);
        if (empty($teams)) {
            return [];
        }

        $teamIds      = array_map('intval', array_column($teams, 'team_id'));
        $placeholders = implode(',', array_fill(0, count($teamIds), '?'));

        $games = $this->db->fetchAll(
            "SELECT g.*, s.game_date, s.game_time, s.location,
                    ht.team_name AS home_team_name, at.team_name AS away_team_name
             FROM games g
             LEFT JOIN schedules s ON g.game_id = s.game_id
             JOIN teams ht ON g.home_team_id = ht.team_id
             JOIN teams at ON g.away_team_id = at.team_id
             WHERE (g.home_team_id IN ({$placeholders}) OR g.away_team_id IN ({$placeholders}))
               AND g.game_status NOT IN ('Completed', 'Cancelled')
               AND NOT EXISTS (
                 SELECT 1 FROM schedule_change_requests scr
                 WHERE scr.game_id = g.game_id
                   AND scr.submitted_by_user_id = :uid
                   AND scr.request_status = 'Pending'
               )",
            array_merge(
                ['uid' => $userId],
                array_values($teamIds),
                array_values($teamIds)
            )
        );

        // Exclude rows where the schedules JOIN returned no game_date (NULL).
        return array_values(array_filter(
            $games,
            fn($g) => isset($g['game_date']) && $g['game_date'] !== null && $g['game_date'] !== ''
        ));
    }

    /**
     * Return all reschedule requests submitted by the given coach, newest first.
     *
     * @return array
     */
    public function getCoachRequests(int $userId): array {
        return $this->db->fetchAll(
            'SELECT scr.*,
                    g.game_number,
                    ht.team_name AS home_team_name,
                    at.team_name AS away_team_name,
                    s.game_date
             FROM schedule_change_requests scr
             JOIN games g ON scr.game_id = g.game_id
             JOIN teams ht ON g.home_team_id = ht.team_id
             JOIN teams at ON g.away_team_id = at.team_id
             LEFT JOIN schedules s ON g.game_id = s.game_id
             WHERE scr.submitted_by_user_id = :uid
             ORDER BY scr.created_date DESC',
            ['uid' => $userId]
        );
    }
}
