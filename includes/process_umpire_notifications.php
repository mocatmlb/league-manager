<?php
/**
 * District 8 Travel League - Umpire Pending Notification Processor
 *
 * CLI-safe cron drain for umpire cascade release and assignor SCR alert notifications.
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

if (!class_exists('Database')) {
    require_once __DIR__ . '/bootstrap.php';
} else {
    if (!function_exists('getSetting')) {
        require_once __DIR__ . '/functions.php';
    }
    if (!class_exists('Logger')) {
        require_once __DIR__ . '/Logger.php';
    }
}
require_once __DIR__ . '/EmailService.php';
require_once __DIR__ . '/UmpireAssignmentService.php';

// Notification processing constants
define('UMPIRE_NOTIFICATION_DEFAULT_BATCH_SIZE', 25);
define('UMPIRE_NOTIFICATION_MAX_BATCH_SIZE', 100);
define('UMPIRE_NOTIFICATION_MAX_RETRY_COUNT', 3);
define('UMPIRE_ASSIGNOR_SCR_UNAVAILABLE_TEXT', 'Not available in this alert');

function processUmpirePendingNotifications(int $limit = UMPIRE_NOTIFICATION_DEFAULT_BATCH_SIZE, ?EmailService $emailService = null, ?Database $db = null): array {
    $db = $db ?: Database::getInstance();
    $emailService = $emailService ?: new EmailService();
    $limit = max(1, min(UMPIRE_NOTIFICATION_MAX_BATCH_SIZE, $limit));

    $stmt = $db->query(
        "SELECT
            upn.notification_id,
            upn.notification_type,
            upn.assignment_id,
            upn.umpire_user_id,
            upn.trigger_event_ref,
            upn.retry_count,
            gua.game_id,
            gua.slot_index,
            gua.assignment_status,
            u.email AS umpire_email,
            u.first_name AS umpire_first_name,
            u.last_name AS umpire_last_name,
            g.game_number,
            g.game_status,
            s.game_date,
            s.game_time,
            l.location_name,
            d.division_name,
            ht.team_name AS home_team,
            at.team_name AS away_team
         FROM umpire_pending_notifications upn
         JOIN game_umpire_assignments gua ON gua.assignment_id = upn.assignment_id
         JOIN users u ON u.id = upn.umpire_user_id
         JOIN games g ON g.game_id = gua.game_id
         JOIN schedules s ON s.game_id = g.game_id
         LEFT JOIN locations l ON l.location_id = s.location_id
         LEFT JOIN divisions d ON d.division_id = g.division_id
         JOIN teams ht ON ht.team_id = g.home_team_id
         JOIN teams at ON at.team_id = g.away_team_id
         WHERE upn.notification_type IN ('cascade_cancelled', 'assignor_alert')
           AND upn.sent_at IS NULL
           AND (upn.failed_at IS NULL OR upn.retry_count < " . UMPIRE_NOTIFICATION_MAX_RETRY_COUNT . ")
         ORDER BY upn.created_at ASC, upn.notification_id ASC
         LIMIT " . (int) $limit,
        []
    );
    $rows = ($stmt && method_exists($stmt, 'fetchAll')) ? $stmt->fetchAll() : [];

    $result = [
        'processed' => 0,
        'sent' => 0,
        'failed' => 0,
    ];

    if (empty($rows)) {
        if (class_exists('Logger')) {
            Logger::info('Umpire notification processor completed: No pending notifications');
        }
        return $result;
    }

    $replyTo = umpireNotificationReplyTo();
    $assignorAlertBatchKeys = [];

    foreach ($rows as $row) {
        $result['processed']++;
        $notificationType = (string) ($row['notification_type'] ?? 'cascade_cancelled');

        if ($notificationType === 'assignor_alert') {
            $batchKey = (int) ($row['game_id'] ?? 0) . '|' . (string) ($row['trigger_event_ref'] ?? '');
            if (isset($assignorAlertBatchKeys[$batchKey])) {
                try {
                    markAssignorAlertRowsSent($db, (int) $row['game_id'], (string) $row['trigger_event_ref']);
                    $result['sent']++;
                } catch (Throwable $e) {
                    markUmpireNotificationFailed($db, $row, $e);
                    $result['failed']++;
                }
                continue;
            }
        }

        try {
            if ($notificationType === 'assignor_alert') {
                $assignorEmail = (string) ($replyTo['email'] ?? '');
                if ($assignorEmail === '') {
                    throw new RuntimeException('Assignor email is not configured.');
                }

                $emailResult = $emailService->sendTemplateToAddressWithMetadata(
                    'umpire_assignor_scr_alert',
                    $assignorEmail,
                    umpireAssignorScrAlertEmailContext($row, $db, $replyTo),
                    [
                        'reply_to_email' => $replyTo['email'],
                        'reply_to_name' => $replyTo['name'],
                        'include_configured_recipients' => false,
                    ]
                );
            } else {
                $emailResult = $emailService->sendTemplateToAddressWithMetadata(
                    'umpire_cascade_cancelled',
                    (string) ($row['umpire_email'] ?? ''),
                    umpireCascadeEmailContext($row, $replyTo),
                    [
                        'reply_to_email' => $replyTo['email'],
                        'reply_to_name' => $replyTo['name'],
                        'include_configured_recipients' => false,
                    ]
                );
            }

            if (!($emailResult['success'] ?? false)) {
                throw new RuntimeException('Email send returned unsuccessful status.');
            }

            if ($notificationType === 'assignor_alert') {
                markAssignorAlertRowsSent($db, (int) $row['game_id'], (string) $row['trigger_event_ref']);
                $assignorAlertBatchKeys[(int) ($row['game_id'] ?? 0) . '|' . (string) ($row['trigger_event_ref'] ?? '')] = true;
            } else {
                $db->update('umpire_pending_notifications', [
                    'sent_at' => date('Y-m-d H:i:s'),
                    'failure_reason' => null,
                    'failed_at' => null,
                ], 'notification_id = :notification_id', [
                    'notification_id' => (int) $row['notification_id'],
                ]);
            }
            $result['sent']++;
        } catch (Throwable $e) {
            markUmpireNotificationFailed($db, $row, $e);
            $result['failed']++;
            if ($notificationType === 'assignor_alert') {
                $assignorAlertBatchKeys[(int) ($row['game_id'] ?? 0) . '|' . (string) ($row['trigger_event_ref'] ?? '')] = true;
            }
        }
    }

    if (class_exists('Logger')) {
        Logger::info('Umpire notification processor completed', $result);
    }
    return $result;
}

function markUmpireNotificationFailed(Database $db, array $row, Throwable $e): void {
    $retryCount = (int) ($row['retry_count'] ?? 0);
    $newRetryCount = $retryCount + 1;
    $isMaxRetries = $newRetryCount >= UMPIRE_NOTIFICATION_MAX_RETRY_COUNT;

    $db->update('umpire_pending_notifications', [
        'failed_at' => $isMaxRetries ? date('Y-m-d H:i:s') : null,
        'failure_reason' => substr($e->getMessage(), 0, 500),
        'retry_count' => $newRetryCount,
    ], 'notification_id = :notification_id', [
        'notification_id' => (int) $row['notification_id'],
    ]);
    error_log('[process_umpire_notifications] notification_id=' . (int) $row['notification_id']
        . ' failed (retry ' . $newRetryCount . '/' . UMPIRE_NOTIFICATION_MAX_RETRY_COUNT . '): ' . $e->getMessage());
}

function markAssignorAlertRowsSent(Database $db, int $gameId, string $triggerRef): void {
    $db->query(
        "UPDATE umpire_pending_notifications upn
         JOIN game_umpire_assignments gua ON gua.assignment_id = upn.assignment_id
         SET upn.sent_at = :sent_at,
             upn.failure_reason = NULL,
             upn.failed_at = NULL
         WHERE gua.game_id = :game_id
           AND upn.trigger_event_ref = :trigger_event_ref
           AND upn.notification_type = 'assignor_alert'
           AND upn.sent_at IS NULL",
        [
            'sent_at' => date('Y-m-d H:i:s'),
            'game_id' => $gameId,
            'trigger_event_ref' => $triggerRef,
        ]
    );
}

function umpireParseScrRequestId(string $triggerRef): ?int {
    if (preg_match('/^SCR-([1-9][0-9]*)$/', $triggerRef, $matches) !== 1) {
        return null;
    }
    return (int) $matches[1];
}

function umpireFormatScheduleDate(?string $date): string {
    if ($date === null || trim($date) === '') {
        return 'TBD';
    }
    $timestamp = strtotime($date);
    return $timestamp !== false ? date('m/d/Y', $timestamp) : 'TBD';
}

function umpireFormatScheduleTime(?string $time): string {
    if ($time === null || trim($time) === '') {
        return 'TBD';
    }
    $timestamp = strtotime($time);
    return $timestamp !== false ? date('g:i A', $timestamp) : 'TBD';
}

function umpireFetchReleasedUmpireNames(Database $db, int $gameId, string $triggerRef): string {
    $publishedUmpireIds = umpireFetchPublishedReleasedUmpireIdsFromAudit($db, $gameId, $triggerRef);
    if (!empty($publishedUmpireIds)) {
        $publishedNames = umpireFetchUmpireNamesByIds($db, $publishedUmpireIds);
        if (!empty($publishedNames)) {
            return implode(', ', $publishedNames);
        }
    }

    $stmt = $db->query(
        "SELECT DISTINCT u.first_name, u.last_name, gua.slot_index
         FROM umpire_pending_notifications upn
         JOIN game_umpire_assignments gua ON gua.assignment_id = upn.assignment_id
         JOIN users u ON u.id = upn.umpire_user_id
         WHERE gua.game_id = :game_id
           AND upn.trigger_event_ref = :trigger_event_ref
           AND upn.notification_type = 'cascade_cancelled'
         ORDER BY gua.slot_index ASC, u.last_name ASC, u.first_name ASC",
        [
            'game_id' => $gameId,
            'trigger_event_ref' => $triggerRef,
        ]
    );
    $rows = ($stmt && method_exists($stmt, 'fetchAll')) ? $stmt->fetchAll() : [];
    $names = [];
    foreach ($rows as $releasedRow) {
        $name = trim((string) ($releasedRow['first_name'] ?? '') . ' ' . (string) ($releasedRow['last_name'] ?? ''));
        if ($name !== '') {
            $names[] = $name;
        }
    }
    return !empty($names) ? implode(', ', $names) : 'None listed';
}

function umpireFetchPublishedReleasedUmpireIdsFromAudit(Database $db, int $gameId, string $triggerRef): array {
    $stmt = $db->query(
        "SELECT context
         FROM activity_log
         WHERE event = :event
           AND context LIKE :game_context
           AND context LIKE :trigger_context
         ORDER BY created_at DESC
         LIMIT 1",
        [
            'event' => 'umpire.assignor_scr_alert_queued',
            'game_context' => '%\"game_id\":' . $gameId . '%',
            'trigger_context' => '%\"trigger_event_ref\":\"' . $triggerRef . '\"%',
        ]
    );
    $rows = ($stmt && method_exists($stmt, 'fetchAll')) ? $stmt->fetchAll() : [];
    $context = $rows[0]['context'] ?? null;
    if (!is_string($context) || trim($context) === '') {
        return [];
    }

    $decoded = json_decode($context, true);
    if (!is_array($decoded) || !isset($decoded['released_umpire_user_ids']) || !is_array($decoded['released_umpire_user_ids'])) {
        return [];
    }

    $ids = [];
    foreach ($decoded['released_umpire_user_ids'] as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    return array_values(array_unique($ids));
}

function umpireFetchUmpireNamesByIds(Database $db, array $umpireUserIds): array {
    $umpireUserIds = array_values(array_filter(array_map('intval', $umpireUserIds), static function ($id) {
        return $id > 0;
    }));
    if (empty($umpireUserIds)) {
        return [];
    }

    $placeholders = [];
    $params = [];
    foreach ($umpireUserIds as $idx => $id) {
        $key = 'umpire_user_id_' . $idx;
        $placeholders[] = ':' . $key;
        $params[$key] = $id;
    }

    $stmt = $db->query(
        "SELECT id, first_name, last_name
         FROM users
         WHERE id IN (" . implode(', ', $placeholders) . ")",
        $params
    );
    $rows = ($stmt && method_exists($stmt, 'fetchAll')) ? $stmt->fetchAll() : [];
    $namesById = [];
    foreach ($rows as $row) {
        $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
        if ($name !== '') {
            $namesById[(int) ($row['id'] ?? 0)] = $name;
        }
    }

    $names = [];
    foreach ($umpireUserIds as $id) {
        if (isset($namesById[$id])) {
            $names[] = $namesById[$id];
        }
    }
    return $names;
}

function umpireAssignorScrAlertEmailContext(array $row, Database $db, array $replyTo): array {
    $triggerRef = (string) ($row['trigger_event_ref'] ?? '');
    $requestId = umpireParseScrRequestId($triggerRef);
    $gameStatus = (string) ($row['game_status'] ?? '');
    $isPostponed = strcasecmp($gameStatus, 'Postponed') === 0;

    $originalDate = 'TBD';
    $originalTime = 'TBD';
    $originalLocation = 'TBD';
    if ($requestId !== null) {
        $request = $db->fetchOne(
            "SELECT original_date, original_time, original_location
             FROM schedule_change_requests
             WHERE request_id = :request_id
             LIMIT 1",
            ['request_id' => $requestId]
        );
        if ($request !== false && $request !== null) {
            $originalDate = umpireFormatScheduleDate($request['original_date'] ?? null);
            $originalTime = umpireFormatScheduleTime($request['original_time'] ?? null);
            $originalLocation = trim((string) ($request['original_location'] ?? '')) ?: 'TBD';
        }
    }

    $newDate = UMPIRE_ASSIGNOR_SCR_UNAVAILABLE_TEXT;
    $newTime = UMPIRE_ASSIGNOR_SCR_UNAVAILABLE_TEXT;
    $newLocation = UMPIRE_ASSIGNOR_SCR_UNAVAILABLE_TEXT;
    if (!$isPostponed) {
        $newDate = umpireFormatScheduleDate($row['game_date'] ?? null);
        $newTime = umpireFormatScheduleTime($row['game_time'] ?? null);
        $newLocation = trim((string) ($row['location_name'] ?? '')) ?: 'TBD';

        if ($requestId !== null) {
            $history = $db->fetchOne(
                "SELECT sh.game_date, sh.game_time, sh.location, l.location_name
                 FROM schedule_history sh
                 LEFT JOIN locations l ON l.location_id = sh.location_id
                 WHERE sh.change_request_id = :request_id
                   AND sh.is_current = 1
                 LIMIT 1",
                ['request_id' => $requestId]
            );
            if ($history !== false && $history !== null) {
                $newDate = umpireFormatScheduleDate($history['game_date'] ?? null);
                $newTime = umpireFormatScheduleTime($history['game_time'] ?? null);
                $locationName = trim((string) ($history['location_name'] ?? ''));
                if ($locationName === '') {
                    $locationName = trim((string) ($history['location'] ?? ''));
                }
                $newLocation = $locationName !== '' ? $locationName : 'TBD';
            }
        }
    }

    return [
        'game_id' => (int) ($row['game_id'] ?? 0),
        'game_number' => (string) ($row['game_number'] ?? ''),
        'original_game_date' => $originalDate,
        'original_game_time' => $originalTime,
        'original_location' => $originalLocation,
        'new_game_date' => $newDate,
        'new_game_time' => $newTime,
        'new_location' => $newLocation,
        'division_name' => (string) (($row['division_name'] ?? '') ?: 'TBD'),
        'home_team' => (string) (($row['home_team'] ?? '') ?: 'TBD'),
        'away_team' => (string) (($row['away_team'] ?? '') ?: 'TBD'),
        'released_umpires' => umpireFetchReleasedUmpireNames($db, (int) ($row['game_id'] ?? 0), $triggerRef),
        'trigger_event_ref' => $triggerRef,
        'assignor_name' => (string) ($replyTo['name'] ?? 'District 8 Travel League'),
        'assignor_email' => (string) ($replyTo['email'] ?? ''),
    ];
}

function umpireCascadeEmailContext(array $row, array $replyTo): array {
    $slotLabels = (new UmpireAssignmentService())->getSlotLabels();
    $slotIndex = (int) ($row['slot_index'] ?? 0);
    $umpireName = trim((string) ($row['umpire_first_name'] ?? '') . ' ' . (string) ($row['umpire_last_name'] ?? ''));

    return [
        'game_id' => (int) ($row['game_id'] ?? 0),
        'game_number' => (string) ($row['game_number'] ?? ''),
        'game_date' => !empty($row['game_date']) ? date('m/d/Y', strtotime((string) $row['game_date'])) : 'TBD',
        'game_time' => !empty($row['game_time']) ? date('g:i A', strtotime((string) $row['game_time'])) : 'TBD',
        'location' => (string) (($row['location_name'] ?? '') ?: 'TBD'),
        'division_name' => (string) (($row['division_name'] ?? '') ?: 'TBD'),
        'home_team' => (string) (($row['home_team'] ?? '') ?: 'TBD'),
        'away_team' => (string) (($row['away_team'] ?? '') ?: 'TBD'),
        'slot_label' => $slotLabels[$slotIndex] ?? ('Umpire ' . ($slotIndex + 1)),
        'game_status' => (string) (($row['game_status'] ?? '') ?: 'Changed'),
        'trigger_event_ref' => (string) ($row['trigger_event_ref'] ?? ''),
        'assignor_name' => $replyTo['name'],
        'assignor_email' => $replyTo['email'],
        'umpire_name' => $umpireName !== '' ? $umpireName : 'Umpire',
    ];
}

function umpireNotificationReplyTo(): array {
    $name = trim((string) getSetting('umpire_assignor_name', ''));
    $email = trim((string) getSetting('umpire_assignor_email', ''));

    if ($email === '') {
        $email = defined('SMTP_FROM_EMAIL') ? (string) SMTP_FROM_EMAIL : '';
    }
    if ($name === '') {
        $name = defined('SMTP_FROM_NAME') ? (string) SMTP_FROM_NAME : 'District 8 Travel League';
    }

    return [
        'name' => $name !== '' ? $name : 'District 8 Travel League',
        'email' => $email,
    ];
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $result = processUmpirePendingNotifications(25);
        if ($result['processed'] === 0) {
            echo "No pending umpire notifications to process.\n";
        } else {
            echo "Processed {$result['processed']} umpire notifications: {$result['sent']} sent, {$result['failed']} failed.\n";
        }
        exit(0);
    } catch (Throwable $e) {
        error_log('[process_umpire_notifications] fatal: ' . $e->getMessage());
        echo "Error processing umpire notifications: " . $e->getMessage() . "\n";
        exit(1);
    }
}
