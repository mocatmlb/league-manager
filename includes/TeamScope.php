<?php
/**
 * District 8 Travel League - Team Scope Utility
 *
 * Provides team-scoped queries for authenticated coaches.
 * This is the only authoritative source of a user's assigned teams.
 *
 * Usage: $teams = TeamScope::getScopedTeams($_SESSION['user_id']);
 */

if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

class TeamScope {

    /**
     * Return all teams assigned to the given user.
     *
     * Joins team_owners with teams to return full team data.
     * Always returns an array — never null, never false.
     * Returns an empty array [] when the user has no assigned teams.
     *
     * @param int $userId
     * @return array  Array of team rows; empty array if none assigned
     */
    public static function getScopedTeams(int $userId): array {
        $db = Database::getInstance();

        $sql = 'SELECT t.*
                FROM teams t
                INNER JOIN team_owners o ON o.team_id = t.team_id
                WHERE o.user_id = :user_id';

        $result = $db->fetchAll($sql, ['user_id' => $userId]);

        return is_array($result) ? $result : [];
    }
}
