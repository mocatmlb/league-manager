<?php
/**
 * District 8 Travel League - Umpire Pending Notification Processor
 *
 * CLI-safe cron drain for umpire cascade release notifications.
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

function processUmpirePendingNotifications(int $limit = UMPIRE_NOTIFICATION_DEFAULT_BATCH_SIZE, ?EmailService $emailService = null, ?Database $db = null): array {
    $db = $db ?: Database::getInstance();
    $emailService = $emailService ?: new EmailService();
    $limit = max(1, min(UMPIRE_NOTIFICATION_MAX_BATCH_SIZE, $limit));

    $stmt = $db->query(
        "SELECT
            upn.notification_id,
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
         WHERE upn.notification_type = 'cascade_cancelled'
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
    foreach ($rows as $row) {
        $result['processed']++;
        try {
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

            if (!($emailResult['success'] ?? false)) {
                throw new RuntimeException('Email send returned unsuccessful status.');
            }

            $db->update('umpire_pending_notifications', [
                'sent_at' => date('Y-m-d H:i:s'),
                'failure_reason' => null,
                'failed_at' => null,
            ], 'notification_id = :notification_id', [
                'notification_id' => (int) $row['notification_id'],
            ]);
            $result['sent']++;
        } catch (Throwable $e) {
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
            $result['failed']++;
            error_log('[process_umpire_notifications] notification_id=' . (int) $row['notification_id']
                . ' failed (retry ' . $newRetryCount . '/' . UMPIRE_NOTIFICATION_MAX_RETRY_COUNT . '): ' . $e->getMessage());
        }
    }

    if (class_exists('Logger')) {
        Logger::info('Umpire notification processor completed', $result);
    }
    return $result;
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
