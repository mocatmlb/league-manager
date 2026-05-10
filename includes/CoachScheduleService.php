<?php
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

class CoachScheduleService {

    private Database $db;

    public function __construct(?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
    }

    public function getTeamSchedule(int $userId): array {
        $teams = TeamScope::getScopedTeams($userId);
        if (empty($teams)) {
            return [];
        }

        $teamIds = array_column($teams, 'team_id');
        $placeholders = implode(',', array_fill(0, count($teamIds), '?'));

        $sql = "SELECT g.game_number, g.game_status,
                       g.home_score, g.away_score,
                       g.home_team_id, g.away_team_id,
                       s.game_date, s.game_time, s.location,
                       ht.team_name AS home_team_name,
                       at.team_name AS away_team_name
                FROM games g
                LEFT JOIN schedules s ON g.game_id = s.game_id
                JOIN teams ht ON g.home_team_id = ht.team_id
                JOIN teams at ON g.away_team_id = at.team_id
                WHERE (g.home_team_id IN ({$placeholders})
                    OR g.away_team_id IN ({$placeholders}))
                ORDER BY s.game_date ASC, s.game_time ASC";

        $params = array_merge(array_values($teamIds), array_values($teamIds));

        return $this->db->fetchAll($sql, $params);
    }
}
