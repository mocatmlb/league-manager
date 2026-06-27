<?php
if (!defined('D8TL_APP')) { die('Direct access not permitted'); }

final class UmpireConflictChecker {
    private const DEFAULT_ASSIGNMENT_WINDOW_MINUTES = 180;
    private const TRAVEL_WARNING_GAP_MINUTES = 45;

    public static function assignmentWindowSeconds(): int {
        $minutes = self::configuredWindowMinutes();
        return $minutes * 60;
    }

    public static function check(
        int $umpireUserId,
        \DateTime $start,
        \DateTime $end,
        ?int $excludeAssignmentId = null,
        ?int $targetLocationId = null,
        ?string $targetLocationName = null
    ): ?array {
        if ($umpireUserId < 1) {
            throw new \InvalidArgumentException('Umpire ID must be positive.');
        }
        if ($end <= $start) {
            throw new \InvalidArgumentException('Assignment end must be after start.');
        }

        $db = Database::getInstance();
        $windowSeconds = self::assignmentWindowSeconds();
        $stmt = $db->query(
            "SELECT
                gua.assignment_id,
                gua.game_id,
                gua.assignment_status,
                g.game_number,
                s.game_date,
                s.game_time,
                s.location_id,
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
               AND (:exclude_assignment_id_is_null IS NULL OR gua.assignment_id <> :exclude_assignment_id_value)
               AND TIMESTAMP(s.game_date, COALESCE(s.game_time, '00:00:00')) < :target_end
               AND DATE_ADD(
                    TIMESTAMP(s.game_date, COALESCE(s.game_time, '00:00:00')),
                    INTERVAL " . $windowSeconds . " SECOND
               ) > :target_start
             ORDER BY s.game_date ASC, s.game_time ASC, gua.assignment_id ASC",
            [
                'umpire_user_id' => $umpireUserId,
                'exclude_assignment_id_is_null' => $excludeAssignmentId !== null && $excludeAssignmentId > 0 ? $excludeAssignmentId : null,
                'exclude_assignment_id_value' => $excludeAssignmentId !== null && $excludeAssignmentId > 0 ? $excludeAssignmentId : 0,
                'target_start' => $start->format('Y-m-d H:i:s'),
                'target_end' => $end->format('Y-m-d H:i:s'),
            ]
        );

        if (!$stmt || !method_exists($stmt, 'fetch')) {
            return null;
        }

        while (($row = $stmt->fetch()) !== false) {
            if ($row === false || $row === null) {
                continue;
            }

            $otherDate = (string) ($row['game_date'] ?? '');
            $otherTime = (string) (($row['game_time'] ?? '') ?: '00:00:00');
            $otherStart = new \DateTime(trim($otherDate . ' ' . $otherTime));
            $otherEnd = (clone $otherStart)->modify('+' . $windowSeconds . ' seconds');

            if (self::isProximityOnlyExemption(
                $start,
                $end,
                $otherStart,
                $otherEnd,
                $targetLocationId,
                $targetLocationName,
                isset($row['location_id']) ? (int) $row['location_id'] : null,
                isset($row['location_name']) ? (string) $row['location_name'] : null
            )) {
                continue;
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

        return null;
    }

    private static function isProximityOnlyExemption(
        \DateTime $targetStart,
        \DateTime $targetEnd,
        \DateTime $otherStart,
        \DateTime $otherEnd,
        ?int $targetLocationId,
        ?string $targetLocationName,
        ?int $otherLocationId,
        ?string $otherLocationName
    ): bool {
        if ($otherStart < $targetEnd && $otherEnd > $targetStart) {
            return false;
        }

        $gapMinutes = abs($targetStart->getTimestamp() - $otherStart->getTimestamp()) / 60;
        if ($gapMinutes >= self::TRAVEL_WARNING_GAP_MINUTES) {
            return false;
        }

        return self::locationsDiffer(
            $targetLocationId,
            $targetLocationName,
            $otherLocationId,
            $otherLocationName
        );
    }

    private static function locationsDiffer(
        ?int $targetLocationId,
        ?string $targetLocationName,
        ?int $otherLocationId,
        ?string $otherLocationName
    ): bool {
        if ($targetLocationId !== null && $otherLocationId !== null
            && (int) $targetLocationId > 0 && (int) $otherLocationId > 0) {
            return (int) $targetLocationId !== (int) $otherLocationId;
        }

        $targetName = trim((string) $targetLocationName);
        $otherName = trim((string) $otherLocationName);
        if ($targetName === '' || $otherName === '') {
            return false;
        }

        return strcasecmp($targetName, $otherName) !== 0;
    }

    private static function configuredWindowMinutes(): int {
        $raw = function_exists('getSetting')
            ? getSetting('conflict_window_minutes', (string) self::DEFAULT_ASSIGNMENT_WINDOW_MINUTES)
            : (string) self::DEFAULT_ASSIGNMENT_WINDOW_MINUTES;
        $minutes = (int) $raw;
        if ($minutes < 1) {
            return self::DEFAULT_ASSIGNMENT_WINDOW_MINUTES;
        }
        return min($minutes, 1440);
    }
}
