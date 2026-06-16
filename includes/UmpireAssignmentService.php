<?php
if (!defined('D8TL_APP')) { die('Direct access not permitted'); }

if (!class_exists('ActivityLogger')) {
    require_once __DIR__ . '/ActivityLogger.php';
}

class UmpireAssignmentService {

    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getQueueWindowDays(): int {
        return (int) getSetting('unassigned_queue_days', '14');
    }

    public function saveQueueWindowDays(int $days, int $actorUserId): void {
        if ($days < 0) {
            throw new \InvalidArgumentException('Queue window must be a non-negative integer.');
        }
        updateSetting('unassigned_queue_days', (string) $days);
        ActivityLogger::log('umpire.settings_changed', [
            'setting'       => 'unassigned_queue_days',
            'new_value'     => $days,
            'actor_user_id' => $actorUserId,
        ]);
    }

    public function getUnassignedQueue(int $windowDays): array {
        $params = [];
        $dateClause = '';
        if ($windowDays > 0) {
            $dateClause = 'AND s.game_date >= CURDATE()
                  AND s.game_date <= DATE_ADD(CURDATE(), INTERVAL :window_days DAY)';
            $params['window_days'] = $windowDays;
        }

        $sql = "SELECT
                    g.game_id,
                    g.game_number,
                    g.game_status,
                    g.division_id,
                    d.division_name,
                    ht.team_name AS home_team,
                    at.team_name AS away_team,
                    s.game_date,
                    s.game_time,
                    l.location_name,
                    (SELECT COUNT(*) FROM game_umpire_assignments gua
                     WHERE gua.game_id = g.game_id
                       AND gua.slot_index IN (0, 1)
                       AND gua.assignment_status IN ('Draft', 'Published')) AS filled_slots
                FROM games g
                JOIN schedules s ON g.game_id = s.game_id
                LEFT JOIN locations l ON s.location_id = l.location_id
                LEFT JOIN divisions d ON g.division_id = d.division_id
                JOIN teams ht ON g.home_team_id = ht.team_id
                JOIN teams at ON g.away_team_id = at.team_id
                WHERE g.game_status NOT IN ('Completed', 'Cancelled', 'Postponed')
                  AND (
                    SELECT COUNT(*) FROM game_umpire_assignments gua
                    WHERE gua.game_id = g.game_id
                      AND gua.slot_index IN (0, 1)
                      AND gua.assignment_status IN ('Draft', 'Published')
                  ) < 2
                  {$dateClause}
                ORDER BY s.game_date ASC, s.game_time ASC";

        $stmt = $this->db->query($sql, $params);
        return ($stmt && method_exists($stmt, 'fetchAll')) ? $stmt->fetchAll() : [];
    }

    public function getAssignmentBoard(): array {
        $sql = "SELECT
                    g.game_id,
                    g.game_number,
                    g.game_status,
                    g.division_id,
                    d.division_name,
                    ht.team_name AS home_team,
                    at.team_name AS away_team,
                    s.game_date,
                    s.game_time,
                    l.location_name,
                    (SELECT COUNT(*) FROM game_umpire_assignments gua
                     WHERE gua.game_id = g.game_id
                       AND gua.slot_index IN (0, 1)
                       AND gua.assignment_status = 'Draft') AS draft_slots,
                    (SELECT COUNT(*) FROM game_umpire_assignments gua
                     WHERE gua.game_id = g.game_id
                       AND gua.slot_index IN (0, 1)
                       AND gua.assignment_status = 'Published') AS published_slots,
                    (SELECT GROUP_CONCAT(
                       CONCAT(u.first_name, ' ', u.last_name, '|', gua.slot_index, '|', gua.assignment_status)
                       ORDER BY gua.slot_index SEPARATOR ';;'
                     )
                     FROM game_umpire_assignments gua
                     JOIN users u ON u.id = gua.umpire_user_id
                     WHERE gua.game_id = g.game_id
                       AND gua.slot_index IN (0, 1)
                       AND gua.assignment_status IN ('Draft', 'Published')
                    ) AS slot_summary
                FROM games g
                JOIN schedules s ON g.game_id = s.game_id
                LEFT JOIN locations l ON s.location_id = l.location_id
                LEFT JOIN divisions d ON g.division_id = d.division_id
                JOIN teams ht ON g.home_team_id = ht.team_id
                JOIN teams at ON g.away_team_id = at.team_id
                WHERE g.game_status NOT IN ('Cancelled')
                ORDER BY s.game_date ASC, s.game_time ASC";

        $stmt = $this->db->query($sql, []);
        $games = ($stmt && method_exists($stmt, 'fetchAll')) ? $stmt->fetchAll() : [];

        foreach ($games as &$game) {
            $draft     = (int) $game['draft_slots'];
            $published = (int) $game['published_slots'];
            $total     = $draft + $published;
            $game['filled_slots'] = $total;

            if ($total === 0) {
                $game['board_status'] = 'Unassigned';
                $game['status_class'] = 'secondary';
            } elseif ($published === 2) {
                $game['board_status'] = 'Published';
                $game['status_class'] = 'success';
            } elseif ($draft > 0) {
                $game['board_status'] = 'Draft';
                $game['status_class'] = 'warning';
            } else {
                $game['board_status'] = 'Partial';
                $game['status_class'] = 'info';
            }

            $game['slots'] = [0 => null, 1 => null];
            if (!empty($game['slot_summary'])) {
                foreach (explode(';;', $game['slot_summary']) as $slotStr) {
                    $parts = explode('|', $slotStr);
                    if (count($parts) === 3) {
                        $idx = (int) $parts[1];
                        if ($idx === 0 || $idx === 1) {
                            $game['slots'][$idx] = [
                                'name'   => $parts[0],
                                'status' => $parts[2],
                            ];
                        }
                    }
                }
            }
        }
        unset($game);

        return $games;
    }
}
