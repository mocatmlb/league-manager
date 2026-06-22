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
if (!class_exists('EmailService')) {
    require_once __DIR__ . '/EmailService.php';
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

if (!class_exists('UmpireAssignmentPublishConfirmationRequiredException')) {
    class UmpireAssignmentPublishConfirmationRequiredException extends \RuntimeException {
        private array $payload;

        public function __construct(string $message, array $payload = []) {
            parent::__construct($message, 409);
            $this->payload = array_merge([
                'requires_confirmation' => true,
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

        // Story 23.7: Filter roster by program eligibility
        $programId = $this->resolveGameProgram($gameId);
        if ($programId > 0) {
            $roster = array_filter($roster, function ($umpire) use ($programId) {
                // Eligible if all_programs is true or game program_id is in their list
                return $umpire['all_programs'] || in_array($programId, $umpire['program_ids'], true);
            });
            $roster = array_values($roster); // Re-index
        }

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
        $rosterService = new UmpireRosterService();
        $game = $this->fetchGame($gameId);

        // Story 23.7: Enforce program eligibility
        $programId = $this->resolveGameProgram($gameId);
        if ($programId > 0) {
            $umpireDetails = $rosterService->getUmpire($umpireUserId);
            if ($umpireDetails && !$umpireDetails['all_programs'] && !in_array($programId, $umpireDetails['program_ids'], true)) {
                throw new \InvalidArgumentException('Umpire is not eligible for this game\'s program.');
            }
        }

        $umpire = $this->fetchActiveUmpire($umpireUserId);
        if ($umpire === null) {
            throw new \InvalidArgumentException('Selected umpire is not active or does not have an umpire profile.');
        }

        $rosterService->reconcileUnder18Flag($umpireUserId);
        $migrationMode = $rosterService->isMigrationMode() ? 1 : 0;

        $existing = $this->fetchSlot($gameId, $slotIndex);
        $publishedMutation = $existing && (string) ($existing['assignment_status'] ?? '') === 'Published';
        $reason = trim((string) $overrideReason);
        $hasOverride = $actorIsAdmin && $reason !== '';
        $this->assertUmpireNotAssignedToOtherSlot($gameId, $slotIndex, $umpireUserId);

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

    public function publishGame(
        int $gameId,
        ?int $actorUserId,
        ?int $actorAdminId = null,
        bool $confirmPartial = false
    ): array {
        $this->validatePositiveId($gameId, 'Game');
        [$actorUserId, $actorAdminId] = $this->normalizeActor($actorUserId, $actorAdminId);
        $game = $this->fetchGame($gameId);
        $slots = $this->fetchPublishableSlots($gameId);
        $filledDraftSlots = array_values(array_filter($slots, static function ($slot) {
            return (string) ($slot['assignment_status'] ?? '') === 'Draft'
                && (int) ($slot['umpire_user_id'] ?? 0) > 0;
        }));
        $filledSlots = array_values(array_filter($slots, static function ($slot) {
            return in_array((string) ($slot['assignment_status'] ?? ''), ['Draft', 'Published'], true)
                && (int) ($slot['umpire_user_id'] ?? 0) > 0;
        }));

        if (count($filledDraftSlots) === 0) {
            throw new \InvalidArgumentException('At least one filled Draft slot is required before publishing.');
        }

        $expectedCrewSize = $this->expectedCrewSize($game);
        $filledCrewCount = count($filledSlots);
        $warned = $filledCrewCount < $expectedCrewSize;
        if ($warned && !$confirmPartial) {
            throw new UmpireAssignmentPublishConfirmationRequiredException(
                'This game has fewer filled slots than the expected crew size.',
                [
                    'error' => 'This game has fewer filled slots than the expected crew size.',
                    'warning' => [
                        'filled_slots' => $filledCrewCount,
                        'expected_crew_size' => $expectedCrewSize,
                    ],
                ]
            );
        }

        $assignor = $this->fetchAssignor($actorUserId, $actorAdminId);
        $slotLabels = [
            0 => getSetting('umpire_slot_1_label', 'Umpire 1'),
            1 => getSetting('umpire_slot_2_label', 'Umpire 2'),
        ];
        $scheduledAt = $this->scheduledAt($game);
        $published = 0;
        $emailService = null;

        foreach ($filledDraftSlots as $slot) {
            $slotIndex = (int) $slot['slot_index'];
            $migrationMode = (int) ($slot['migration_mode'] ?? 0) === 1;
            $queueId = null;
            $hash = null;

            if (!$migrationMode) {
                $emailService = $emailService ?: new EmailService();
                $hash = $this->notificationHash($gameId, $slotIndex, (int) $slot['umpire_user_id'], $scheduledAt);
                $email = $emailService->sendTemplateToAddressWithMetadata(
                    'umpire_assignment_published',
                    (string) ($slot['email'] ?? ''),
                    $this->assignmentEmailContext($game, $slot, $slotLabels[$slotIndex] ?? ('Umpire ' . ($slotIndex + 1)), $assignor, $filledCrewCount),
                    [
                        'reply_to_email' => $assignor['email'] ?? '',
                        'reply_to_name' => $assignor['name'] ?? '',
                        'include_configured_recipients' => true,
                    ]
                );
                if (!($email['success'] ?? false) || empty($email['queue_id'])) {
                    throw new \RuntimeException('Assignment email could not be queued.');
                }
                $queueId = (int) $email['queue_id'];
            }

            $this->markSlotPublished($gameId, $slotIndex, $hash, $migrationMode, $actorUserId);
            $published++;

            $context = [
                'game_id' => $gameId,
                'slot_index' => $slotIndex,
                'umpire_user_id' => (int) $slot['umpire_user_id'],
                'email_queue_id' => $queueId,
                'actor_user_id' => $actorUserId,
                'actor_admin_id' => $actorAdminId,
            ];
            ActivityLogger::log('umpire.assigned', $context);
            ActivityLogger::log('umpire.published', $context);
        }

        return [
            'published' => $published,
            'warned' => $warned,
        ];
    }

    /**
     * Cascade-cancel active umpire assignments when game schedule/status changes.
     *
     * Called when a game is cancelled, postponed, or rescheduled. Cancels all Draft/Published
     * assignments with assigned umpires, queues cascade release notifications, and logs audit events.
     *
     * IMPORTANT: This method does NOT manage transactions. Caller must wrap in transaction
     * to ensure atomic cancellation + notification queuing.
     *
     * @param int $gameId Game ID whose assignments should be cancelled
     * @param string $triggerRef Event reference (e.g., "SCR-123", "GAME-CANCELLED-456") for audit trail
     * @param array $options Optional context: ['actor_user_id' => int, 'actor_admin_id' => int, 'source' => string]
     * @return bool True on success, false on error (errors are logged, not thrown)
     */
    public function onScheduleChanged(int $gameId, string $triggerRef, array $options = []): bool {
        try {
            $this->validatePositiveId($gameId, 'Game');
            $triggerRef = trim($triggerRef);
            if ($triggerRef === '') {
                throw new \InvalidArgumentException('Trigger reference is required.');
            }

            $this->fetchCascadeGame($gameId);
            $assignments = $this->fetchCascadeAssignments($gameId);
            if (empty($assignments)) {
                return true;
            }

            $ids = array_map(static function ($row) {
                return (int) $row['assignment_id'];
            }, $assignments);
            $this->cancelCascadeAssignments($ids);

            foreach ($assignments as $assignment) {
                $notificationId = $this->insertPendingCascadeNotification($assignment, $triggerRef);
                ActivityLogger::log('umpire.cascade_cancelled', [
                    'game_id' => $gameId,
                    'assignment_id' => (int) $assignment['assignment_id'],
                    'umpire_user_id' => (int) $assignment['umpire_user_id'],
                    'slot_index' => (int) $assignment['slot_index'],
                    'prior_status' => (string) ($assignment['assignment_status'] ?? ''),
                    'trigger_event_ref' => $triggerRef,
                    'notification_id' => $notificationId,
                    'actor_user_id' => isset($options['actor_user_id']) ? (int) $options['actor_user_id'] : null,
                    'actor_admin_id' => isset($options['actor_admin_id']) ? (int) $options['actor_admin_id'] : null,
                    'source' => isset($options['source']) ? (string) $options['source'] : null,
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            error_log('[UmpireAssignmentService::onScheduleChanged] Cascade cancellation failed game_id=' . $gameId
                . ' trigger_ref=' . $triggerRef . ' error=' . $e->getMessage());
            return false;
        }
    }

    public function getUmpireAssignments(int $umpireUserId): array {
        $sql = "SELECT
                    g.game_id, g.game_number,
                    s.game_date, s.game_time,
                    l.location_name,
                    d.division_name,
                    ht.team_name AS home_team,
                    at.team_name AS away_team,
                    gua.slot_index,
                    gua.assigned_by_user_id,
                    a.first_name AS assignor_first_name,
                    a.last_name AS assignor_last_name,
                    a.email AS assignor_email,
                    a.phone AS assignor_phone,
                    (SELECT COUNT(DISTINCT gua2.slot_index) FROM game_umpire_assignments gua2
                     WHERE gua2.game_id = g.game_id
                       AND gua2.slot_index IN (0, 1)
                       AND gua2.assignment_status = 'Published') AS filled_slots
                FROM game_umpire_assignments gua
                JOIN games g ON gua.game_id = g.game_id
                JOIN schedules s ON s.schedule_id = (
                    SELECT s2.schedule_id
                    FROM schedules s2
                    WHERE s2.game_id = g.game_id
                    ORDER BY s2.modified_date DESC, s2.schedule_id DESC
                    LIMIT 1
                )
                LEFT JOIN locations l ON s.location_id = l.location_id
                LEFT JOIN divisions d ON g.division_id = d.division_id
                JOIN teams ht ON g.home_team_id = ht.team_id
                JOIN teams at ON g.away_team_id = at.team_id
                LEFT JOIN users a ON gua.assigned_by_user_id = a.id
                WHERE gua.umpire_user_id = :uid
                  AND gua.assignment_status = 'Published'
                  AND g.game_status NOT IN ('Cancelled', 'Postponed')
                ORDER BY s.game_date ASC, s.game_time ASC";

        $stmt = $this->db->query($sql, ['uid' => $umpireUserId]);
        $rows = ($stmt && method_exists($stmt, 'fetchAll')) ? $stmt->fetchAll() : [];

        $slotLabels = [
            0 => getSetting('umpire_slot_1_label', 'Umpire 1'),
            1 => getSetting('umpire_slot_2_label', 'Umpire 2'),
        ];

        $results = [];
        foreach ($rows as $row) {
            $slotIndex = (int) ($row['slot_index'] ?? 0);
            $assignorName = trim(($row['assignor_first_name'] ?? '') . ' ' . ($row['assignor_last_name'] ?? ''));
            $phone = (string) ($row['assignor_phone'] ?? '');
            $filledCrew = (int) ($row['filled_slots'] ?? 0);

            $results[] = [
                'game_id' => (int) ($row['game_id'] ?? 0),
                'game_number' => (string) ($row['game_number'] ?? ''),
                'game_date' => (string) ($row['game_date'] ?? ''),
                'game_time' => (string) ($row['game_time'] ?? ''),
                'location_name' => (string) ($row['location_name'] ?? ''),
                'division_name' => (string) ($row['division_name'] ?? ''),
                'home_team' => (string) ($row['home_team'] ?? ''),
                'away_team' => (string) ($row['away_team'] ?? ''),
                'slot_index' => $slotIndex,
                'slot_label' => $slotLabels[$slotIndex] ?? ('Umpire ' . ($slotIndex + 1)),
                'fee_text' => $this->feePerTeamText((string) ($row['division_name'] ?? ''), $filledCrew),
                'assignor_name' => $assignorName ?: 'Contact your assignor',
                'assignor_phone' => $phone ?: '',
                'assignor_phone_tel' => $this->telHref($phone),
                'assignor_email' => (string) ($row['assignor_email'] ?? ''),
            ];
        }

        return $results;
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

    private function fetchCascadeGame(int $gameId): array {
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
        return $row;
    }

    private function fetchCascadeAssignments(int $gameId): array {
        $stmt = $this->db->query(
            "SELECT assignment_id, game_id, umpire_user_id, slot_index,
                    assignment_status, published, migration_mode,
                    last_notified_at, last_notified_hash
             FROM game_umpire_assignments
             WHERE game_id = :game_id
               AND assignment_status IN ('Draft', 'Published')
               AND umpire_user_id IS NOT NULL
             ORDER BY slot_index ASC, assignment_id ASC",
            ['game_id' => $gameId]
        );
        return ($stmt && method_exists($stmt, 'fetchAll')) ? $stmt->fetchAll() : [];
    }

    private function cancelCascadeAssignments(array $assignmentIds): void {
        $assignmentIds = array_values(array_filter(array_map('intval', $assignmentIds), static function ($id) {
            return $id > 0;
        }));
        if (empty($assignmentIds)) {
            return;
        }

        $params = [];
        $placeholders = [];
        foreach ($assignmentIds as $i => $id) {
            $key = 'assignment_id_' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $this->db->query(
            "UPDATE game_umpire_assignments
             SET assignment_status = 'Cancelled',
                 published = 0,
                 modified_at = NOW()
             WHERE assignment_id IN (" . implode(', ', $placeholders) . ")
               AND assignment_status IN ('Draft', 'Published')
               AND umpire_user_id IS NOT NULL",
            $params
        );
    }

    private function insertPendingCascadeNotification(array $assignment, string $triggerRef): int {
        return (int) $this->db->insert('umpire_pending_notifications', [
            'assignment_id' => (int) $assignment['assignment_id'],
            'umpire_user_id' => (int) $assignment['umpire_user_id'],
            'notification_type' => 'cascade_cancelled',
            'trigger_event_ref' => $triggerRef,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function fetchPublishableSlots(int $gameId): array {
        $stmt = $this->db->query(
            "SELECT
                gua.assignment_id, gua.game_id, gua.umpire_user_id, gua.slot_index,
                gua.assignment_status, gua.published, gua.migration_mode,
                u.first_name, u.last_name, u.email, u.phone
             FROM game_umpire_assignments gua
             LEFT JOIN users u ON u.id = gua.umpire_user_id
             WHERE gua.game_id = :game_id
               AND gua.slot_index IN (0, 1)
             ORDER BY gua.slot_index ASC",
            ['game_id' => $gameId]
        );
        return ($stmt && method_exists($stmt, 'fetchAll')) ? $stmt->fetchAll() : [];
    }

    private function fetchAssignor(?int $actorUserId, ?int $actorAdminId): array {
        if ($actorUserId !== null) {
            $row = $this->db->fetchOne(
                "SELECT id, first_name, last_name, email, phone
                 FROM users
                 WHERE id = :id
                 LIMIT 1",
                ['id' => $actorUserId]
            );
            if ($row !== false && $row !== null) {
                return [
                    'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'District 8 Assignor',
                    'email' => (string) ($row['email'] ?? ''),
                    'phone' => (string) ($row['phone'] ?? ''),
                ];
            }
        }

        if ($actorAdminId !== null) {
            $row = $this->db->fetchOne(
                "SELECT id, username, email, phone
                 FROM admin_users
                 WHERE id = :id
                 LIMIT 1",
                ['id' => $actorAdminId]
            );
            if ($row !== false && $row !== null) {
                return [
                    'name' => (string) (($row['username'] ?? '') ?: 'District 8 Assignor'),
                    'email' => (string) ($row['email'] ?? ''),
                    'phone' => (string) ($row['phone'] ?? ''),
                ];
            }
        }

        throw new \InvalidArgumentException('Assignor contact information not found.');
    }

    private function markSlotPublished(int $gameId, int $slotIndex, ?string $hash, bool $migrationMode, ?int $actorUserId = null): void {
        if ($migrationMode) {
            $this->db->query(
                "UPDATE game_umpire_assignments
                 SET assignment_status = 'Published',
                     published = 1,
                     assigned_by_user_id = COALESCE(:actor_user_id, assigned_by_user_id),
                     last_notified_at = NULL,
                     last_notified_hash = NULL,
                     modified_at = NOW()
                 WHERE game_id = :game_id
                   AND slot_index = :slot_index
                   AND assignment_status = 'Draft'",
                [
                    'actor_user_id' => $actorUserId,
                    'game_id' => $gameId,
                    'slot_index' => $slotIndex,
                ]
            );
            return;
        }

        $this->db->query(
            "UPDATE game_umpire_assignments
             SET assignment_status = 'Published',
                 published = 1,
                 assigned_by_user_id = COALESCE(:actor_user_id, assigned_by_user_id),
                 last_notified_at = NOW(),
                 last_notified_hash = :last_notified_hash,
                 modified_at = NOW()
             WHERE game_id = :game_id
               AND slot_index = :slot_index
               AND assignment_status = 'Draft'",
            [
                'actor_user_id' => $actorUserId,
                'last_notified_hash' => $hash,
                'game_id' => $gameId,
                'slot_index' => $slotIndex,
            ]
        );
    }

    private function notificationHash(int $gameId, int $slotIndex, int $umpireUserId, string $scheduledAt): string {
        return hash('sha256', implode('|', [$gameId, $slotIndex, $umpireUserId, $scheduledAt]));
    }

    private function scheduledAt(array $game): string {
        return trim((string) ($game['game_date'] ?? '') . ' ' . (string) (($game['game_time'] ?? '') ?: '00:00:00'));
    }

    private function assignmentEmailContext(array $game, array $slot, string $slotLabel, array $assignor, int $filledCrewCount): array {
        $phone = (string) ($assignor['phone'] ?? '');
        return [
            'game_id' => (int) ($game['game_id'] ?? 0),
            'game_number' => (string) ($game['game_number'] ?? ''),
            'game_date' => !empty($game['game_date']) ? date('m/d/Y', strtotime((string) $game['game_date'])) : 'TBD',
            'game_time' => !empty($game['game_time']) ? date('g:i A', strtotime((string) $game['game_time'])) : 'TBD',
            'location' => (string) (($game['location_name'] ?? '') ?: 'TBD'),
            'division_name' => (string) (($game['division_name'] ?? '') ?: 'TBD'),
            'slot_label' => $slotLabel,
            'fee_per_team' => $this->feePerTeamText((string) ($game['division_name'] ?? ''), $filledCrewCount),
            'assignor_name' => (string) ($assignor['name'] ?? 'District 8 Assignor'),
            'assignor_phone' => $phone !== '' ? $phone : 'Not provided',
            'assignor_phone_tel' => $this->telHref($phone),
            'assignor_email' => (string) ($assignor['email'] ?? ''),
            'home_team' => (string) (($game['home_team'] ?? '') ?: 'TBD'),
            'away_team' => (string) (($game['away_team'] ?? '') ?: 'TBD'),
            'umpire_name' => trim(($slot['first_name'] ?? '') . ' ' . ($slot['last_name'] ?? '')),
        ];
    }

    private function expectedCrewSize(array $game): int {
        return 2;
    }

    private function feePerTeamText(string $divisionName, int $filledCrewCount): string {
        $crew = $filledCrewCount >= 2 ? 2 : 1;
        $division = strtolower($divisionName);
        if (str_contains($division, 'intermediate')) {
            return $crew === 1 ? '$35 per team' : '$50 per team';
        }
        if (str_contains($division, 'junior') || str_contains($division, 'senior')) {
            return $crew === 1 ? '$50 per team' : '$80 per team';
        }
        return 'Confirm fee with assignor';
    }

    private function telHref(string $phone): string {
        $clean = preg_replace('/(?!^\+)\D+/', '', $phone);
        return $clean ? 'tel:' . $clean : '';
    }

    private function assignmentWindow(array $game): array {
        $date = (string) ($game['game_date'] ?? '');
        $time = (string) (($game['game_time'] ?? '') ?: '00:00:00');
        $start = new \DateTime(trim($date . ' ' . $time));
        $end = (clone $start)->modify('+' . UmpireConflictChecker::assignmentWindowSeconds() . ' seconds');
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

    private function assertUmpireNotAssignedToOtherSlot(int $gameId, int $slotIndex, int $umpireUserId): void {
        $row = $this->db->fetchOne(
            "SELECT assignment_id, slot_index
             FROM game_umpire_assignments
             WHERE game_id = :game_id
               AND umpire_user_id = :umpire_user_id
               AND slot_index <> :slot_index
               AND assignment_status IN ('Draft', 'Published')
             LIMIT 1",
            [
                'game_id' => $gameId,
                'umpire_user_id' => $umpireUserId,
                'slot_index' => $slotIndex,
            ]
        );

        if ($row !== false && $row !== null) {
            throw new \InvalidArgumentException('Selected umpire is already assigned to another slot on this game.');
        }
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

    /**
     * Resolve the program_id for a game via its season.
     */
    private function resolveGameProgram(int $gameId): int {
        $row = $this->db->fetchOne(
            "SELECT s.program_id
             FROM games g
             JOIN seasons s ON s.season_id = g.season_id
             WHERE g.game_id = :game_id
             LIMIT 1",
            ['game_id' => $gameId]
        );
        return (int) ($row['program_id'] ?? 0);
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
