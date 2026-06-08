<?php
/**
 * District 8 Travel League - Conflict Detection Service
 *
 * Detects team and location scheduling conflicts across displayed games.
 * Uses two bulk queries to avoid N+1 database hits.
 *
 * Usage: $svc = new ConflictDetectionService(Database::getInstance());
 */

if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

class ConflictDetectionService {

    private Database $db;
    private int $conflictWindow;

    public function __construct(Database $db, ?int $conflictWindow = null) {
        $this->db = $db;
        $this->conflictWindow = $conflictWindow ?? (defined('CONFLICT_WINDOW_SECONDS') ? CONFLICT_WINDOW_SECONDS : 10800);
    }

    /**
     * Detect team and location conflicts for the given games array.
     *
     * @param array $games  Rows from the games management SELECT; each row must have:
     *                      game_id, game_date, game_time, location_id,
     *                      home_team_id, away_team_id, game_status
     * @return array<int, array{type: string, message: string}[]>  Keyed by game_id
     */
    public function getGameConflicts(array $games): array {
        if (empty($games)) {
            return [];
        }

        $activeGames = array_filter($games, fn($g) => !in_array($g['game_status'], ['Cancelled', 'Postponed']));
        if (empty($activeGames)) {
            return [];
        }

        $gameIds = array_map('intval', array_column($activeGames, 'game_id'));

        // Performance guard: don't run bulk queries for huge sets (e.g. 500+ games)
        if (count($gameIds) > 500) {
            return [];
        }

        $conflicts = [];
        foreach ($gameIds as $id) {
            $conflicts[$id] = [];
        }

        $this->detectTeamConflicts($gameIds, $conflicts);
        $this->detectLocationConflicts($gameIds, $conflicts);

        return $conflicts;
    }

    /**
     * Stub for SCR-time conflict checking — implemented in Story 20-2.
     */
    public function checkScrConflicts(
        string $proposedDate,
        string $proposedTime,
        string $proposedLocation,
        int $homeTeamId,
        int $awayTeamId
    ): array {
        return [];
    }

    // -------------------------------------------------------------------------

    private function detectTeamConflicts(array $gameIds, array &$conflicts): void {
        $placeholders = implode(',', array_fill(0, count($gameIds), '?'));

        $sql = "
            SELECT
                g1.game_id            AS source_game_id,
                g2.game_id            AS conflict_game_id,
                s2.game_date          AS conflict_date,
                s2.game_time          AS conflict_time,
                t.team_name           AS conflict_team_name
            FROM games g1
            JOIN schedules s1 ON s1.game_id = g1.game_id
            JOIN games g2
                ON  g2.game_id != g1.game_id
                AND g2.game_status NOT IN ('Cancelled','Postponed')
                AND (   g2.home_team_id IN (g1.home_team_id, g1.away_team_id)
                     OR g2.away_team_id IN (g1.home_team_id, g1.away_team_id))
            JOIN schedules s2
                ON  s2.game_id   = g2.game_id
                AND s2.game_date = s1.game_date
            JOIN teams t
                ON  t.team_id IN (g1.home_team_id, g1.away_team_id)
                AND t.team_id IN (g2.home_team_id, g2.away_team_id)
            WHERE g1.game_id IN ($placeholders)
              AND g1.game_status NOT IN ('Cancelled','Postponed')
              AND (
                    s1.game_time IS NULL
                 OR s2.game_time IS NULL
                 OR ABS(TIME_TO_SEC(s1.game_time) - TIME_TO_SEC(s2.game_time)) <= " . $this->conflictWindow . "
              )
        ";

        $rows = $this->db->fetchAll($sql, $gameIds);

        foreach ($rows as $row) {
            $sourceId = (int) $row['source_game_id'];
            if (!array_key_exists($sourceId, $conflicts)) {
                continue;
            }
            $timeLabel = $row['conflict_time'] ? date('g:i A', strtotime($row['conflict_time'])) : '(Time TBD)';
            $dateLabel = date('M j, Y', strtotime($row['conflict_date']));
            $conflicts[$sourceId][] = [
                'type'    => 'team',
                'message' => "Team {$row['conflict_team_name']} also has a game on {$dateLabel} {$timeLabel}",
            ];
        }
    }

    private function detectLocationConflicts(array $gameIds, array &$conflicts): void {
        $placeholders = implode(',', array_fill(0, count($gameIds), '?'));

        $sql = "
            SELECT
                g1.game_id            AS source_game_id,
                g2.game_id            AS conflict_game_id,
                s2.game_date          AS conflict_date,
                s2.game_time          AS conflict_time,
                l.location_name       AS location_name
            FROM games g1
            JOIN schedules s1 ON s1.game_id = g1.game_id
            JOIN games g2
                ON  g2.game_id != g1.game_id
                AND g2.game_status NOT IN ('Cancelled','Postponed')
            JOIN schedules s2
                ON  s2.game_id       = g2.game_id
                AND s2.game_date     = s1.game_date
                AND s2.location_id   = s1.location_id
            JOIN locations l ON l.location_id = s1.location_id
            WHERE g1.game_id IN ($placeholders)
              AND g1.game_status NOT IN ('Cancelled','Postponed')
              AND s1.location_id IS NOT NULL
              AND (
                    s1.game_time IS NULL
                 OR s2.game_time IS NULL
                 OR ABS(TIME_TO_SEC(s1.game_time) - TIME_TO_SEC(s2.game_time)) <= " . $this->conflictWindow . "
              )
        ";

        $rows = $this->db->fetchAll($sql, $gameIds);

        foreach ($rows as $row) {
            $sourceId = (int) $row['source_game_id'];
            if (!array_key_exists($sourceId, $conflicts)) {
                continue;
            }
            $timeLabel = $row['conflict_time'] ? date('g:i A', strtotime($row['conflict_time'])) : '(Time TBD)';
            $dateLabel = date('M j, Y', strtotime($row['conflict_date']));
            $conflicts[$sourceId][] = [
                'type'    => 'location',
                'message' => "{$row['location_name']} also has a game on {$dateLabel} {$timeLabel}",
            ];
        }
    }

    /**
     * Returns null if either time is null (caller treats null as "always conflict").
     * Otherwise returns the absolute difference in seconds.
     */
    private function timeDiffSeconds(?string $a, ?string $b): ?int {
        if ($a === null || $b === null) {
            return null;
        }
        return abs(strtotime($a) - strtotime($b));
    }
}
