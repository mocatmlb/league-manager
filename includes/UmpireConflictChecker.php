<?php
if (!defined('D8TL_APP')) { die('Direct access not permitted'); }

final class UmpireConflictChecker {
    private const DEFAULT_ASSIGNMENT_WINDOW_HOURS = 2;

    public static function check(
        int $umpireUserId,
        \DateTime $start,
        \DateTime $end,
        ?int $excludeAssignmentId = null
    ): ?array {
        if ($umpireUserId < 1) {
            throw new \InvalidArgumentException('Umpire ID must be positive.');
        }
        if ($end <= $start) {
            throw new \InvalidArgumentException('Assignment end must be after start.');
        }

        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT
                gua.assignment_id,
                gua.game_id,
                gua.assignment_status,
                g.game_number,
                s.game_date,
                s.game_time,
                ht.team_name AS home_team,
                at.team_name AS away_team,
                l.location_name
             FROM game_umpire_assignments gua
             JOIN games g ON g.game_id = gua.game_id
             JOIN schedules s ON s.game_id = g.game_id
             JOIN teams ht ON ht.team_id = g.home_team_id
             JOIN teams at ON at.team_id = g.away_team_id
             LEFT JOIN locations l ON l.location_id = s.location_id
             WHERE gua.umpire_user_id = :umpire_user_id
               AND gua.assignment_status IN ('Draft', 'Published')
               AND g.game_status NOT IN ('Cancelled', 'Postponed')
               AND (:exclude_assignment_id IS NULL OR gua.assignment_id <> :exclude_assignment_id)
               AND TIMESTAMP(s.game_date, COALESCE(s.game_time, '00:00:00')) < :target_end
               AND DATE_ADD(
                    TIMESTAMP(s.game_date, COALESCE(s.game_time, '00:00:00')),
                    INTERVAL " . self::DEFAULT_ASSIGNMENT_WINDOW_HOURS . " HOUR
               ) > :target_start
             ORDER BY s.game_date ASC, s.game_time ASC, gua.assignment_id ASC
             LIMIT 1",
            [
                'umpire_user_id' => $umpireUserId,
                'exclude_assignment_id' => $excludeAssignmentId !== null && $excludeAssignmentId > 0 ? $excludeAssignmentId : null,
                'target_start' => $start->format('Y-m-d H:i:s'),
                'target_end' => $end->format('Y-m-d H:i:s'),
            ]
        );

        if (!$stmt || !method_exists($stmt, 'fetch')) {
            return null;
        }

        $row = $stmt->fetch();
        if ($row === false || $row === null) {
            return null;
        }

        return [
            'assignment_id' => (int) ($row['assignment_id'] ?? 0),
            'game_id' => (int) ($row['game_id'] ?? 0),
            'game_number' => $row['game_number'] ?? null,
            'game_date' => $row['game_date'] ?? null,
            'game_time' => $row['game_time'] ?? null,
            'home_team' => $row['home_team'] ?? null,
            'away_team' => $row['away_team'] ?? null,
            'location_name' => $row['location_name'] ?? null,
            'assignment_status' => $row['assignment_status'] ?? null,
        ];
    }
}
