<?php
if (!defined('D8TL_APP')) { die('Direct access not permitted'); }

if (!class_exists('ActivityLogger')) {
    require_once __DIR__ . '/ActivityLogger.php';
}
if (!class_exists('UmpireRosterService')) {
    require_once __DIR__ . '/UmpireRosterService.php';
}
if (!class_exists('UmpireConflictChecker')) {
    require_once __DIR__ . '/UmpireConflictChecker.php';
}

if (!class_exists('UmpireAssignmentOverrideRequiredException')) {
    class UmpireAssignmentOverrideRequiredException extends \RuntimeException {
        private array $payload;

        public function __construct(string $message, array $payload = []) {
            parent::__construct($message, 409);
            $this->payload = array_merge([
                'requires_override' => true,
            ], $payload);
        }

        public function getPayload(): array {
            return $this->payload;
        }
    }
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

    public function getGameAssignmentDrawer(int $gameId): array {
        $this->validatePositiveId($gameId, 'Game');
        $game = $this->fetchGame($gameId);

        $slots = [
            0 => $this->openSlot(0),
            1 => $this->openSlot(1),
        ];

        $stmt = $this->db->query(
            "SELECT
                gua.assignment_id, gua.game_id, gua.umpire_user_id, gua.slot_index,
                gua.assignment_status, gua.published, gua.migration_mode,
                u.first_name, u.last_name, u.email, u.phone,
                p.umpire_level, p.is_under_18
             FROM game_umpire_assignments gua
             LEFT JOIN users u ON u.id = gua.umpire_user_id
             LEFT JOIN umpire_profiles p ON p.user_id = gua.umpire_user_id
             WHERE gua.game_id = :game_id
               AND gua.slot_index IN (0, 1)
             ORDER BY gua.slot_index ASC",
            ['game_id' => $gameId]
        );
        $assignmentRows = ($stmt && method_exists($stmt, 'fetchAll')) ? $stmt->fetchAll() : [];
        foreach ($assignmentRows as $row) {
            $idx = (int) ($row['slot_index'] ?? -1);
            if ($idx !== 0 && $idx !== 1) {
                continue;
            }
            $slots[$idx] = $this->formatSlot($idx, $row);
        }

        $rosterService = new UmpireRosterService();
        $roster = $rosterService->getRoster(true);
        $loads = $this->fetchCurrentGameLoads(array_map(static function ($row) {
            return (int) ($row['id'] ?? 0);
        }, $roster));

        foreach ($roster as &$umpire) {
            $id = (int) ($umpire['id'] ?? 0);
            $umpire['current_game_load'] = (int) ($loads[$id] ?? 0);
            $umpire['is_under_18'] = (int) ($umpire['is_under_18'] ?? 0);
        }
        unset($umpire);

        return [
            'game' => $game,
            'slot_labels' => [
                0 => getSetting('umpire_slot_1_label', 'Umpire 1'),
                1 => getSetting('umpire_slot_2_label', 'Umpire 2'),
            ],
            'slots' => $slots,
            'roster' => $roster,
            'migration_mode' => $rosterService->isMigrationMode(),
        ];
    }

    public function saveSlot(
        int $gameId,
        int $slotIndex,
        int $umpireUserId,
        ?int $assignedByUserId,
        bool $actorIsAdmin = false,
        ?string $overrideReason = null,
        ?int $actorAdminId = null
    ): array {
        $this->validatePositiveId($gameId, 'Game');
        $this->validatePositiveId($umpireUserId, 'Umpire');
        $this->validateSlotIndex($slotIndex);
        [$assignedByUserId, $actorAdminId] = $this->normalizeActor($assignedByUserId, $actorAdminId);
        $game = $this->fetchGame($gameId);

        $umpire = $this->fetchActiveUmpire($umpireUserId);
        if ($umpire === null) {
            throw new \InvalidArgumentException('Selected umpire is not active or does not have an umpire profile.');
        }

        $rosterService = new UmpireRosterService();
        $rosterService->reconcileUnder18Flag($umpireUserId);
        $migrationMode = $rosterService->isMigrationMode() ? 1 : 0;

        $existing = $this->fetchSlot($gameId, $slotIndex);
        $publishedMutation = $existing && (string) ($existing['assignment_status'] ?? '') === 'Published';
        $reason = trim((string) $overrideReason);
        $hasOverride = $actorIsAdmin && $reason !== '';

        [$targetStart, $targetEnd] = $this->assignmentWindow($game);
        $conflict = UmpireConflictChecker::check(
            $umpireUserId,
            $targetStart,
            $targetEnd,
            $existing && isset($existing['assignment_id']) ? (int) $existing['assignment_id'] : null
        );

        if (($conflict !== null || $publishedMutation) && !$hasOverride) {
            $message = $conflict !== null
                ? 'This umpire is already assigned to an overlapping game.'
                : 'Published slots require an administrator override reason before they can be changed.';
            throw new UmpireAssignmentOverrideRequiredException($message, [
                'conflict' => $conflict,
            ]);
        }

        $this->db->query(
            "INSERT INTO game_umpire_assignments
                (game_id, umpire_user_id, slot_index, slot_type, assignment_status, published,
                 migration_mode, assigned_by_user_id, assigned_at, created_at, modified_at)
             VALUES
                (:game_id, :umpire_user_id, :slot_index, 'general', 'Draft', 0,
                 :migration_mode, :actor_user_id, NOW(), NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                umpire_user_id = VALUES(umpire_user_id),
                assignment_status = 'Draft',
                published = 0,
                migration_mode = VALUES(migration_mode),
                assigned_by_user_id = VALUES(assigned_by_user_id),
                assigned_at = NOW(),
                last_notified_at = NULL,
                last_notified_hash = NULL,
                modified_at = NOW()",
            [
                'game_id' => $gameId,
                'umpire_user_id' => $umpireUserId,
                'slot_index' => $slotIndex,
                'migration_mode' => $migrationMode,
                'actor_user_id' => $assignedByUserId,
            ]
        );

        if (($conflict !== null || $publishedMutation) && $hasOverride) {
            $this->logOverride($reason, $gameId, $slotIndex, $existing, $umpireUserId, $conflict, $assignedByUserId, $actorAdminId);
        } else {
            ActivityLogger::log($migrationMode === 1 ? 'umpire.migrated' : 'umpire.assigned', [
                'game_id' => $gameId,
                'slot_index' => $slotIndex,
                'umpire_user_id' => $umpireUserId,
                'actor_user_id' => $assignedByUserId,
            ]);
        }

        return [
            'game_id' => $gameId,
            'slot' => [
                'slot_index' => $slotIndex,
                'status' => 'Draft',
                'umpire_user_id' => $umpireUserId,
                'umpire' => $umpire,
                'published' => 0,
                'migration_mode' => $migrationMode,
            ],
        ];
    }

    public function unassignSlot(
        int $gameId,
        int $slotIndex,
        ?int $assignedByUserId,
        bool $actorIsAdmin = false,
        ?string $overrideReason = null,
        ?int $actorAdminId = null
    ): array {
        $this->validatePositiveId($gameId, 'Game');
        $this->validateSlotIndex($slotIndex);
        [$assignedByUserId, $actorAdminId] = $this->normalizeActor($assignedByUserId, $actorAdminId);
        $this->fetchGame($gameId);

        $existing = $this->fetchSlot($gameId, $slotIndex);
        $publishedMutation = $existing && (string) ($existing['assignment_status'] ?? '') === 'Published';
        $reason = trim((string) $overrideReason);
        $hasOverride = $actorIsAdmin && $reason !== '';
        if ($publishedMutation && !$hasOverride) {
            throw new UmpireAssignmentOverrideRequiredException('Published slots require an administrator override reason before they can be changed.');
        }

        if ($existing) {
            $this->db->query(
                "UPDATE game_umpire_assignments
                 SET umpire_user_id = NULL,
                     assignment_status = 'Open',
                     published = 0,
                     assigned_by_user_id = :actor_user_id,
                     assigned_at = NULL,
                     last_notified_at = NULL,
                     last_notified_hash = NULL,
                     modified_at = NOW()
                 WHERE game_id = :game_id
                   AND slot_index = :slot_index",
                [
                    'actor_user_id' => $assignedByUserId,
                    'game_id' => $gameId,
                    'slot_index' => $slotIndex,
                ]
            );

            if ($publishedMutation && $hasOverride) {
                $this->logOverride($reason, $gameId, $slotIndex, $existing, null, null, $assignedByUserId, $actorAdminId);
            } else {
                ActivityLogger::log('umpire.unassigned', [
                    'game_id' => $gameId,
                    'slot_index' => $slotIndex,
                    'umpire_user_id' => ($existing['umpire_user_id'] ?? null) !== null ? (int) $existing['umpire_user_id'] : null,
                    'actor_user_id' => $assignedByUserId,
                ]);
            }
        }

        return [
            'game_id' => $gameId,
            'slot' => $this->openSlot($slotIndex),
        ];
    }

    private function fetchGame(int $gameId): array {
        $row = $this->db->fetchOne(
            "SELECT
                g.game_id, g.game_number, g.game_status, g.division_id,
                d.division_name,
                ht.team_name AS home_team,
                at.team_name AS away_team,
                s.game_date, s.game_time,
                l.location_name
             FROM games g
             JOIN schedules s ON g.game_id = s.game_id
             LEFT JOIN locations l ON s.location_id = l.location_id
             LEFT JOIN divisions d ON g.division_id = d.division_id
             JOIN teams ht ON g.home_team_id = ht.team_id
             JOIN teams at ON g.away_team_id = at.team_id
             WHERE g.game_id = :game_id
             LIMIT 1",
            ['game_id' => $gameId]
        );
        if ($row === false || $row === null) {
            throw new \InvalidArgumentException('Game not found.');
        }
        $status = (string) ($row['game_status'] ?? '');
        if ($status === 'Cancelled' || $status === 'Postponed') {
            throw new \InvalidArgumentException(ucfirst(strtolower($status)) . ' games cannot be assigned.');
        }
        return $row;
    }

    private function assignmentWindow(array $game): array {
        $date = (string) ($game['game_date'] ?? '');
        $time = (string) (($game['game_time'] ?? '') ?: '00:00:00');
        $start = new \DateTime(trim($date . ' ' . $time));
        $end = (clone $start)->modify('+2 hours');
        return [$start, $end];
    }

    private function normalizeActor(?int $assignedByUserId, ?int $actorAdminId): array {
        $assignedByUserId = $assignedByUserId !== null && $assignedByUserId > 0 ? $assignedByUserId : null;
        $actorAdminId = $actorAdminId !== null && $actorAdminId > 0 ? $actorAdminId : null;

        if ($assignedByUserId === null && $actorAdminId === null) {
            throw new \InvalidArgumentException('Authenticated actor not found.');
        }

        return [$assignedByUserId, $actorAdminId];
    }

    private function logOverride(
        string $reason,
        int $gameId,
        int $slotIndex,
        ?array $existing,
        ?int $newUmpireUserId,
        ?array $conflict,
        ?int $assignedByUserId,
        ?int $actorAdminId
    ): void {
        ActivityLogger::log('umpire.override', [
            'reason' => $reason,
            'game_id' => $gameId,
            'target_game_id' => $gameId,
            'slot_index' => $slotIndex,
            'prior_umpire_user_id' => ($existing && isset($existing['umpire_user_id']) && $existing['umpire_user_id'] !== null)
                ? (int) $existing['umpire_user_id']
                : null,
            'new_umpire_user_id' => $newUmpireUserId,
            'conflicting_assignment_id' => $conflict['assignment_id'] ?? null,
            'conflicting_game_id' => $conflict['game_id'] ?? null,
            'actor_user_id' => $assignedByUserId,
            'actor_admin_id' => $actorAdminId,
        ]);
    }

    private function fetchActiveUmpire(int $umpireUserId): ?array {
        $role = $this->db->fetchOne(
            'SELECT id FROM roles WHERE name = :name LIMIT 1',
            ['name' => 'umpire']
        );
        $roleId = (int) ($role['id'] ?? 0);
        if ($roleId < 1) {
            throw new \RuntimeException('umpire role not found in roles table — is migration 041 applied?');
        }

        $row = $this->db->fetchOne(
            "SELECT
                u.id, u.first_name, u.last_name, u.email, u.phone, u.status,
                p.umpire_level, p.is_under_18
             FROM users u
             INNER JOIN umpire_profiles p ON p.user_id = u.id
             WHERE u.id = :uid
               AND u.role_id = :role_id
               AND u.status = 'active'
             LIMIT 1",
            ['uid' => $umpireUserId, 'role_id' => $roleId]
        );

        return $row !== false ? $row : null;
    }

    private function fetchSlot(int $gameId, int $slotIndex): ?array {
        $row = $this->db->fetchOne(
            "SELECT *
             FROM game_umpire_assignments
             WHERE game_id = :game_id
               AND slot_index = :slot_index
             LIMIT 1",
            ['game_id' => $gameId, 'slot_index' => $slotIndex]
        );
        return $row !== false ? $row : null;
    }

    private function fetchCurrentGameLoads(array $umpireIds): array {
        $ids = array_values(array_filter(array_unique(array_map('intval', $umpireIds)), static function ($id) {
            return $id > 0;
        }));
        if (empty($ids)) {
            return [];
        }

        $params = [];
        $placeholders = [];
        foreach ($ids as $i => $id) {
            $key = 'uid_' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $stmt = $this->db->query(
            "SELECT gua.umpire_user_id, COUNT(*) AS current_game_load
             FROM game_umpire_assignments gua
             JOIN games g ON g.game_id = gua.game_id
             WHERE gua.umpire_user_id IN (" . implode(', ', $placeholders) . ")
               AND gua.assignment_status IN ('Draft', 'Published')
               AND g.game_status != 'Cancelled'
             GROUP BY gua.umpire_user_id",
            $params
        );
        $rows = ($stmt && method_exists($stmt, 'fetchAll')) ? $stmt->fetchAll() : [];
        $loads = [];
        foreach ($rows as $row) {
            $loads[(int) ($row['umpire_user_id'] ?? 0)] = (int) ($row['current_game_load'] ?? 0);
        }
        return $loads;
    }

    private function validatePositiveId(int $id, string $label): void {
        if ($id < 1) {
            throw new \InvalidArgumentException($label . ' ID must be positive.');
        }
    }

    private function validateSlotIndex(int $slotIndex): void {
        if ($slotIndex !== 0 && $slotIndex !== 1) {
            throw new \InvalidArgumentException('Slot index must be 0 or 1.');
        }
    }

    private function openSlot(int $slotIndex): array {
        return [
            'slot_index' => $slotIndex,
            'status' => 'Open',
            'umpire_user_id' => null,
            'umpire' => null,
            'published' => 0,
            'migration_mode' => 0,
        ];
    }

    private function formatSlot(int $slotIndex, array $row): array {
        $status = (string) ($row['assignment_status'] ?? 'Open');
        if ($status === 'Open' || empty($row['umpire_user_id'])) {
            return $this->openSlot($slotIndex);
        }

        return [
            'slot_index' => $slotIndex,
            'status' => $status,
            'umpire_user_id' => (int) $row['umpire_user_id'],
            'umpire' => [
                'id' => (int) $row['umpire_user_id'],
                'first_name' => $row['first_name'] ?? '',
                'last_name' => $row['last_name'] ?? '',
                'email' => $row['email'] ?? '',
                'phone' => $row['phone'] ?? '',
                'umpire_level' => $row['umpire_level'] ?? '',
                'is_under_18' => (int) ($row['is_under_18'] ?? 0),
            ],
            'published' => (int) ($row['published'] ?? 0),
            'migration_mode' => (int) ($row['migration_mode'] ?? 0),
        ];
    }
}
