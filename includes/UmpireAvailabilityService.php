<?php
/**
 * District 8 Travel League - Umpire Availability Service
 *
 * Manages availability windows for umpires.
 */

if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

if (!class_exists('UmpireConflictChecker')) {
    require_once __DIR__ . '/UmpireConflictChecker.php';
}
if (!class_exists('ActivityLogger')) {
    require_once __DIR__ . '/ActivityLogger.php';
}

final class UmpireAvailabilityService {

    /**
     * List all availability windows for a specific umpire, ordered chronologically.
     */
    public function listForUmpire(int $umpireUserId): array {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT availability_id, starts_at, ends_at, notes, created_at, updated_at
             FROM umpire_availability_windows
             WHERE umpire_user_id = :umpire_user_id
             ORDER BY starts_at ASC",
            ['umpire_user_id' => $umpireUserId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Create a new availability window.
     *
     * @throws InvalidArgumentException if validation fails.
     */
    public function createWindow(int $umpireUserId, string $startsAt, string $endsAt, ?string $notes = null): int {
        [$startsAt, $endsAt] = $this->validateWindow($startsAt, $endsAt);

        $db = Database::getInstance();
        $id = $db->insert('umpire_availability_windows', [
            'umpire_user_id' => $umpireUserId,
            'starts_at'      => $startsAt,
            'ends_at'        => $endsAt,
            'notes'          => $notes
        ]);

        ActivityLogger::log('umpire.availability.created', [
            'availability_id' => $id,
            'umpire_user_id'   => $umpireUserId
        ]);

        return (int)$id;
    }

    /**
     * Create one availability window per selected local date.
     *
     * @param string[] $dates YYYY-MM-DD local calendar dates.
     * @return array{created: int[], skipped: string[], errors: string[]}
     *
     * @throws InvalidArgumentException if batch-level validation fails.
     */
    public function createWindowsForDates(int $umpireUserId, array $dates, ?string $startTime, ?string $endTime, ?string $notes = null): array {
        if (count($dates) > 62) {
            throw new InvalidArgumentException('Batch cannot exceed 62 dates.');
        }

        $dates = array_values(array_unique(array_map(static function ($date) {
            return trim((string)$date);
        }, $dates)));

        if (empty($dates)) {
            throw new InvalidArgumentException('At least one availability date is required.');
        }

        $isAllDay = ($startTime === null || trim($startTime) === '') && ($endTime === null || trim($endTime) === '');
        if (!$isAllDay) {
            $startTime = $this->validateLocalTime((string)$startTime, 'Start time');
            $endTime = $this->validateLocalTime((string)$endTime, 'End time');

            if ($startTime >= $endTime) {
                throw new InvalidArgumentException('Start time must be before end time.');
            }
        }

        $created = [];
        $skipped = [];
        $errors = [];

        foreach ($dates as $date) {
            try {
                $date = $this->validateLocalDate($date);
                if ($isAllDay) {
                    $startsAt = $date . ' 00:00:00';
                    $endsAt = DateTimeImmutable::createFromFormat('!Y-m-d', $date)
                        ->modify('+1 day')
                        ->format('Y-m-d') . ' 00:00:00';
                } else {
                    $startsAt = $date . ' ' . $startTime . ':00';
                    $endsAt = $date . ' ' . $endTime . ':00';
                }

                if ($this->windowExists($umpireUserId, $startsAt, $endsAt)) {
                    $skipped[] = $date;
                    continue;
                }

                $created[] = $this->createWindow($umpireUserId, $startsAt, $endsAt, $notes);
            } catch (InvalidArgumentException $e) {
                $errors[] = $date;
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }

    /**
     * Update an existing availability window, enforcing ownership.
     *
     * @throws InvalidArgumentException if validation fails.
     * @throws RuntimeException if window not found or not owned by the user.
     */
    public function updateWindow(int $availabilityId, int $umpireUserId, string $startsAt, string $endsAt, ?string $notes = null): void {
        $this->validateOwnership($availabilityId, $umpireUserId);
        [$startsAt, $endsAt] = $this->validateWindow($startsAt, $endsAt);

        $db = Database::getInstance();
        $db->update(
            'umpire_availability_windows',
            [
                'starts_at' => $startsAt,
                'ends_at'   => $endsAt,
                'notes'     => $notes
            ],
            'availability_id = :id AND umpire_user_id = :umpire_user_id',
            ['id' => $availabilityId, 'umpire_user_id' => $umpireUserId]
        );

        ActivityLogger::log('umpire.availability.updated', [
            'availability_id' => $availabilityId,
            'umpire_user_id'   => $umpireUserId
        ]);
    }

    /**
     * Delete an availability window, enforcing ownership.
     */
    public function deleteWindow(int $availabilityId, int $umpireUserId): void {
        $this->validateOwnership($availabilityId, $umpireUserId);

        $db = Database::getInstance();
        $db->delete(
            'umpire_availability_windows',
            'availability_id = :id AND umpire_user_id = :umpire_user_id',
            ['id' => $availabilityId, 'umpire_user_id' => $umpireUserId]
        );

        ActivityLogger::log('umpire.availability.deleted', [
            'availability_id' => $availabilityId,
            'umpire_user_id'   => $umpireUserId
        ]);
    }

    /**
     * Returns an array of umpire_user_id integers for active umpires with at least one
     * covering availability window and no Draft/Published overlapping game assignments.
     * 
     * @return int[]
     */
    public function getAvailableUmpireIdsForWindow(DateTimeInterface $startsAt, DateTimeInterface $endsAt): array {
        if ($startsAt >= $endsAt) {
            throw new InvalidArgumentException('Requested availability window must end after it starts.');
        }

        $db = Database::getInstance();
        
        $startStr = $startsAt->format('Y-m-d H:i:s');
        $endStr   = $endsAt->format('Y-m-d H:i:s');

        // Logic:
        // 1. Join users and umpire_profiles to ensure they are active umpires.
        // 2. Filter by having at least one window covering the requested range (starts_at <= requested_start AND ends_at >= requested_end).
        // 3. Exclude anyone with a Draft/Published assignment overlapping the requested range.
        $windowSeconds = UmpireConflictChecker::assignmentWindowSeconds();
        $sql = "
            SELECT DISTINCT u.id as umpire_user_id
            FROM users u
            JOIN roles r ON r.id = u.role_id AND r.name = 'umpire'
            JOIN umpire_profiles up ON u.id = up.user_id
            JOIN umpire_availability_windows aw ON u.id = aw.umpire_user_id
            WHERE u.status = 'active'
              AND aw.starts_at <= :requested_start
              AND aw.ends_at >= :requested_end
              AND NOT EXISTS (
                  SELECT 1 
                  FROM game_umpire_assignments gua
                  JOIN games g ON g.game_id = gua.game_id
                  JOIN schedules s ON s.game_id = gua.game_id
                  WHERE gua.umpire_user_id = u.id
                    AND gua.assignment_status IN ('Draft', 'Published')
                    AND g.game_status NOT IN ('Cancelled', 'Postponed')
                    AND TIMESTAMP(s.game_date, COALESCE(s.game_time, '00:00:00')) < :overlap_end
                    AND DATE_ADD(
                        TIMESTAMP(s.game_date, COALESCE(s.game_time, '00:00:00')),
                        INTERVAL " . $windowSeconds . " SECOND
                    ) > :overlap_start
              )
        ";
        
        $stmt = $db->query($sql, [
            'requested_start' => $startStr,
            'requested_end'   => $endStr,
            'overlap_start'   => $startStr,
            'overlap_end'     => $endStr
        ]);

        $ids = [];
        foreach ($stmt->fetchAll() as $row) {
            $ids[] = (int)$row['umpire_user_id'];
        }
        return $ids;
    }

    private function validateWindow(string $startsAt, string $endsAt): array {
        if (empty($startsAt) || empty($endsAt)) {
            throw new InvalidArgumentException("Start and end times cannot be blank.");
        }
        $start = $this->parseLocalDateTime($startsAt, 'Start time');
        $end = $this->parseLocalDateTime($endsAt, 'End time');
        if ($start >= $end) {
            throw new InvalidArgumentException("Start time must be before end time.");
        }

        return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    }

    private function parseLocalDateTime(string $value, string $label): DateTimeImmutable {
        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i'];
        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!' . $format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($parsed instanceof DateTimeImmutable && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $parsed;
            }
        }

        throw new InvalidArgumentException($label . ' must be a valid date and time.');
    }

    private function validateLocalDate(string $value): string {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$parsed instanceof DateTimeImmutable || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('Date must be a valid YYYY-MM-DD calendar date.');
        }

        if ($parsed->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Date must be a valid YYYY-MM-DD calendar date.');
        }

        return $value;
    }

    private function validateLocalTime(string $value, string $label): string {
        $value = trim($value);
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
            throw new InvalidArgumentException($label . ' must be a valid HH:MM time.');
        }

        return $value;
    }

    private function windowExists(int $umpireUserId, string $startsAt, string $endsAt): bool {
        $db = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT availability_id
             FROM umpire_availability_windows
             WHERE umpire_user_id = :umpire_user_id
               AND starts_at = :starts_at
               AND ends_at = :ends_at
             LIMIT 1",
            [
                'umpire_user_id' => $umpireUserId,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt
            ]
        );

        return (bool)$row;
    }

    private function validateOwnership(int $availabilityId, int $umpireUserId): void {
        $db = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT availability_id FROM umpire_availability_windows WHERE availability_id = :id AND umpire_user_id = :umpire_user_id",
            ['id' => $availabilityId, 'umpire_user_id' => $umpireUserId]
        );
        if (!$row) {
            throw new RuntimeException("Availability window not found or not owned by user.");
        }
    }
}
