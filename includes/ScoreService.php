<?php
/**
 * District 8 Travel League - Score Service
 *
 * Enforces team-scoping, time-gating, and standings updates for score submission.
 * Standings are computed dynamically from the games table — no separate update step needed.
 *
 * Usage: $service = new ScoreService(Database::getInstance());
 */

if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

class TeamScopeViolationException extends RuntimeException {}
class GameNotEligibleException extends RuntimeException {}
class ScoreConflictException extends RuntimeException {}

class ScoreService {

    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * Submit a score for an eligible game owned by the coach's team.
     *
     * @throws TeamScopeViolationException  Game does not involve coach's team
     * @throws GameNotEligibleException     Missing schedule, not yet started, or ineligible
     * @throws InvalidArgumentException     Score out of 0–99 range
     */
    public function submit(int $userId, int $gameId, int $homeScore, int $awayScore): void {
        $this->validateScores($homeScore, $awayScore);

        $teams   = TeamScope::getScopedTeams($userId);
        $teamIds = array_map('intval', array_column($teams, 'team_id'));

        $game = $this->loadGame($gameId);
        $this->enforceTeamScopeWithIds($teamIds, $game);
        $this->enforceTimeGate($game);

        $this->db->query(
            'UPDATE games
             SET home_score = :home_score, away_score = :away_score,
                 game_status = :status, modified_date = NOW()
             WHERE game_id = :game_id',
            [
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'status'     => 'Completed',
                'game_id'    => $gameId,
            ]
        );

        try {
            if (function_exists('sendNotification')) {
                sendNotification('onGameScoreUpdate', $gameId);
            }
        } catch (Throwable $e) {
            error_log('[ScoreService] Admin notification failed — game_id=' . $gameId . ' error=' . $e->getMessage());
        }

        ActivityLogger::log('score.submitted', [
            'user_id'    => $userId,
            'game_id'    => $gameId,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ]);
    }

    /**
     * Edit the score of an already-completed game.
     * Applies the same team scope and time gate enforcement as submit().
     * Uses modified_date as an optimistic lock — throws ScoreConflictException
     * if a concurrent request already updated the row.
     *
     * @throws TeamScopeViolationException
     * @throws GameNotEligibleException  Not completed, missing schedule, or not yet eligible
     * @throws ScoreConflictException    Concurrent edit detected
     * @throws InvalidArgumentException
     */
    public function edit(int $userId, int $gameId, int $homeScore, int $awayScore): void {
        $this->validateScores($homeScore, $awayScore);

        $teams   = TeamScope::getScopedTeams($userId);
        $teamIds = array_map('intval', array_column($teams, 'team_id'));

        $game = $this->loadGame($gameId);
        $this->enforceTeamScopeWithIds($teamIds, $game);
        $this->enforceCompletedForEdit($game);
        $this->enforceTimeGate($game);

        $expectedModifiedDate = $game['modified_date'] ?? null;
        $oldHomeScore         = isset($game['home_score']) ? (int) $game['home_score'] : null;
        $oldAwayScore         = isset($game['away_score']) ? (int) $game['away_score'] : null;

        $stmt = $this->db->query(
            'UPDATE games
             SET home_score = :home_score, away_score = :away_score,
                 modified_date = NOW(6)
             WHERE game_id = :game_id
               AND modified_date = :expected_modified_date',
            [
                'home_score'             => $homeScore,
                'away_score'             => $awayScore,
                'game_id'                => $gameId,
                'expected_modified_date' => $expectedModifiedDate,
            ]
        );

        if ($stmt->rowCount() === 0) {
            throw new ScoreConflictException(
                "Game {$gameId} was modified by a concurrent request. Please reload and try again."
            );
        }

        ActivityLogger::log('score.edited', [
            'user_id'        => $userId,
            'game_id'        => $gameId,
            'old_home_score' => $oldHomeScore,
            'old_away_score' => $oldAwayScore,
            'home_score'     => $homeScore,
            'away_score'     => $awayScore,
        ]);
    }

    /**
     * Return past/elapsed unscored games involving the coach's assigned teams.
     *
     * @return array  Each element is a games row with game_date, game_time from schedules.
     */
    public function getEligibleGames(int $userId): array {
        $teams = TeamScope::getScopedTeams($userId);
        if (empty($teams)) {
            return [];
        }

        $teamIds      = array_column($teams, 'team_id');
        $placeholders = implode(',', array_fill(0, count($teamIds), '?'));

        $games = $this->db->fetchAll(
            "SELECT g.*, s.game_date, s.game_time, s.location,
                    ht.team_name AS home_team_name, at.team_name AS away_team_name
             FROM games g
             LEFT JOIN schedules s ON g.game_id = s.game_id
             JOIN teams ht ON g.home_team_id = ht.team_id
             JOIN teams at ON g.away_team_id = at.team_id
             WHERE (g.home_team_id IN ({$placeholders}) OR g.away_team_id IN ({$placeholders}))
               AND g.game_status != 'Completed'",
            array_merge(array_values($teamIds), array_values($teamIds))
        );

        return array_values(array_filter(
            $games,
            fn($g) => $this->hasScheduleForTimeGate($g) && GameTimeGate::isEligible($g)
        ));
    }

    /**
     * Return completed games involving the coach's assigned teams (for edit flow).
     * Most-recently-modified first; capped at 20 rows.
     *
     * @return array  Each element is a games row with game_date, game_time, team names.
     */
    public function getCompletedGames(int $userId): array {
        $teams = TeamScope::getScopedTeams($userId);
        if (empty($teams)) {
            return [];
        }

        $teamIds      = array_column($teams, 'team_id');
        $placeholders = implode(',', array_fill(0, count($teamIds), '?'));

        return $this->db->fetchAll(
            "SELECT g.*, s.game_date, s.game_time,
                    ht.team_name AS home_team_name, at.team_name AS away_team_name
             FROM games g
             LEFT JOIN schedules s ON g.game_id = s.game_id
             JOIN teams ht ON g.home_team_id = ht.team_id
             JOIN teams at ON g.away_team_id = at.team_id
             WHERE (g.home_team_id IN ({$placeholders}) OR g.away_team_id IN ({$placeholders}))
               AND g.game_status = 'Completed'
             ORDER BY g.modified_date DESC
             LIMIT 20",
            array_merge(array_values($teamIds), array_values($teamIds))
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Load a game row with LEFT JOIN schedules.
     * Note: game_date and game_time are NULL when no schedules row exists.
     * Call hasScheduleForTimeGate() before using these columns.
     *
     * @throws GameNotEligibleException  Game not found
     */
    private function loadGame(int $gameId): array {
        $game = $this->db->fetchOne(
            'SELECT g.*, s.game_date, s.game_time
             FROM games g
             LEFT JOIN schedules s ON g.game_id = s.game_id
             WHERE g.game_id = :game_id',
            ['game_id' => $gameId]
        );

        if ($game === false || empty($game)) {
            throw new GameNotEligibleException("Game not found: {$gameId}");
        }

        return $game;
    }

    /**
     * @param int[] $teamIds  Pre-fetched scoped team IDs (integers)
     */
    private function enforceTeamScopeWithIds(array $teamIds, array $game): void {
        $homeId = (int) $game['home_team_id'];
        $awayId = (int) $game['away_team_id'];

        if (!in_array($homeId, $teamIds, true) && !in_array($awayId, $teamIds, true)) {
            throw new TeamScopeViolationException(
                "Game {$game['game_id']} does not involve a team owned by user"
            );
        }
    }

    private function enforceCompletedForEdit(array $game): void {
        if (($game['game_status'] ?? '') !== 'Completed') {
            throw new GameNotEligibleException(
                "Game {$game['game_id']} cannot be edited until it is marked completed"
            );
        }
    }

    /**
     * Schedule columns must be present so GameTimeGate does not mis-evaluate null dates.
     */
    private function hasScheduleForTimeGate(array $game): bool {
        if (!isset($game['game_date']) || $game['game_date'] === '' || $game['game_date'] === null) {
            return false;
        }
        if (!isset($game['game_time']) || $game['game_time'] === '' || $game['game_time'] === null) {
            return false;
        }

        return true;
    }

    private function enforceTimeGate(array $game): void {
        if (!$this->hasScheduleForTimeGate($game)) {
            throw new GameNotEligibleException(
                "Game {$game['game_id']} has no schedule date/time for score submission"
            );
        }
        if (!GameTimeGate::isEligible($game)) {
            throw new GameNotEligibleException(
                "Game {$game['game_id']} is not yet eligible for score submission"
            );
        }
    }

    private function validateScores(int $homeScore, int $awayScore): void {
        if ($homeScore < 0 || $homeScore > 99 || $awayScore < 0 || $awayScore > 99) {
            throw new InvalidArgumentException('Scores must be between 0 and 99 inclusive');
        }
    }
}
