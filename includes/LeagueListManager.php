<?php
/**
 * District 8 Travel League - League List Manager
 *
 * CRUD service for the league_list table.
 * Provides the active league dropdown for coach registration and admin management.
 *
 * Usage: LeagueListManager::getActiveList(), ::create(), ::update(), etc.
 */

if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

class LeagueListManager {

    private const STATUS_UPDATED = 'updated';
    private const STATUS_NO_CHANGE = 'no_change';
    private const STATUS_NOT_FOUND = 'not_found';

    /**
     * Return all active league entries ordered by sort_order ascending.
     *
     * @return array<int, array{id: int, display_name: string, sort_order: int, is_active: int}>
     */
    public static function getActiveList(): array {
        $db = Database::getInstance();
        return $db->fetchAll(
            'SELECT id, display_name, sort_order, is_active, created_at, updated_at
             FROM league_list
             WHERE is_active = 1
             ORDER BY sort_order ASC'
        );
    }

    /**
     * Return all entries (active + deactivated) for admin display.
     * Active entries first (by sort_order), then deactivated entries.
     *
     * @return array<int, array>
     */
    public static function getAll(): array {
        $db = Database::getInstance();
        return $db->fetchAll(
            'SELECT id, display_name, sort_order, is_active, created_at, updated_at
             FROM league_list
             ORDER BY is_active DESC, sort_order ASC, id ASC'
        );
    }

    /**
     * Create a new active league entry at the bottom of the list.
     *
     * @param string $displayName Short league name shown in registration dropdown
     * @return int The new entry's id
     */
    public static function create(string $displayName): int {
        $db = Database::getInstance();

        // Determine next sort_order
        $row = $db->fetchOne(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order FROM league_list'
        );
        $nextOrder = (int) ($row['next_order'] ?? 1);

        $db->query(
            'INSERT INTO league_list (display_name, sort_order, is_active, created_at, updated_at)
             VALUES (:display_name, :sort_order, 1, NOW(), NOW())',
            [
                'display_name' => $displayName,
                'sort_order'   => $nextOrder,
            ]
        );

        return (int) $db->getConnection()->lastInsertId();
    }

    /**
     * Update the display name of an existing entry.
     *
     * @param int    $id          Entry id
     * @param string $displayName New display name
     * @return bool true on success, false if entry not found
     */
    public static function update(int $id, string $displayName): bool {
        $db = Database::getInstance();
        $status = self::runStateChange(
            'UPDATE league_list SET display_name = :display_name, updated_at = NOW() WHERE id = :id',
            [
                'display_name' => $displayName,
                'id'           => $id,
            ],
            $id
        );
        return $status !== self::STATUS_NOT_FOUND;
    }

    /**
     * Deactivate a league entry (soft delete). Entry remains for historical reference.
     *
     * @param int $id Entry id
     * @return bool true on success, false if entry not found
     */
    public static function deactivate(int $id): bool {
        $db = Database::getInstance();
        $status = self::runStateChange(
            'UPDATE league_list SET is_active = 0, updated_at = NOW() WHERE id = :id',
            ['id' => $id],
            $id
        );
        return $status !== self::STATUS_NOT_FOUND;
    }

    /**
     * Reactivate a deactivated league entry. The entry is placed at the bottom of the active list.
     *
     * @param int $id Entry id
     * @return bool true on success, false if entry not found
     */
    public static function reactivate(int $id): bool {
        $db = Database::getInstance();

        // Place reactivated entry at the bottom
        $row = $db->fetchOne(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order FROM league_list WHERE is_active = 1'
        );
        $nextOrder = (int) ($row['next_order'] ?? 1);

        $status = self::runStateChange(
            'UPDATE league_list SET is_active = 1, sort_order = :sort_order, updated_at = NOW() WHERE id = :id',
            [
                'sort_order' => $nextOrder,
                'id'         => $id,
            ],
            $id
        );
        return $status !== self::STATUS_NOT_FOUND;
    }

    /**
     * Reorder active entries by updating sort_order to match the given ID sequence.
     *
     * @param array<int> $orderedIds Entry IDs in desired display order (1-indexed positions assigned)
     */
    public static function reorder(array $orderedIds): void {
        $db = Database::getInstance();
        $orderedIds = array_values(array_map('intval', $orderedIds));
        $orderedIds = array_values(array_filter($orderedIds, fn($id) => $id > 0));

        if (empty($orderedIds)) {
            throw new InvalidArgumentException('Reorder payload is empty.');
        }

        $activeRows = $db->fetchAll(
            'SELECT id FROM league_list WHERE is_active = 1 ORDER BY sort_order ASC'
        );
        $activeIds = array_map(fn($row) => (int) $row['id'], $activeRows);
        sort($activeIds);

        $submittedIds = $orderedIds;
        sort($submittedIds);

        if (count(array_unique($orderedIds)) !== count($orderedIds)) {
            throw new InvalidArgumentException('Duplicate league IDs are not allowed in reorder payload.');
        }
        if ($submittedIds !== $activeIds) {
            throw new InvalidArgumentException('Reorder payload must include each active league ID exactly once.');
        }

        $db->beginTransaction();
        try {
            $position = 1;
            foreach ($orderedIds as $id) {
                $stmt = $db->query(
                    'UPDATE league_list SET sort_order = :sort_order, updated_at = NOW() WHERE id = :id AND is_active = 1',
                    [
                        'sort_order' => $position,
                        'id'         => $id,
                    ]
                );

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Failed to update reorder state for league ID ' . $id);
                }
                $position++;
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    private static function runStateChange(string $sql, array $params, int $id): string {
        $db = Database::getInstance();
        $stmt = $db->query($sql, $params);
        if ($stmt->rowCount() > 0) {
            return self::STATUS_UPDATED;
        }

        return self::entryExists($id) ? self::STATUS_NO_CHANGE : self::STATUS_NOT_FOUND;
    }

    private static function entryExists(int $id): bool {
        $db = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT id FROM league_list WHERE id = :id',
            ['id' => $id]
        );
        return $row !== false;
    }
}
