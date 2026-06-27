<?php
/**
 * Unit Tests: Umpire pending notification processor
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';

function seed_umpire_notification_settings(): void {
    if (!isset($GLOBALS['_test_settings']) || !is_array($GLOBALS['_test_settings'])) {
        $GLOBALS['_test_settings'] = [];
    }
    $GLOBALS['_test_settings']['umpire_slot_1_label'] = 'Plate';
    $GLOBALS['_test_settings']['umpire_slot_2_label'] = 'Bases';
    $GLOBALS['_test_settings']['umpire_assignor_name'] = 'Alex Assignor';
    $GLOBALS['_test_settings']['umpire_assignor_email'] = 'assignor@example.test';
}

seed_umpire_notification_settings();

class UmpireNotificationMockDb extends Database {
    public array $queryRows = [];
    public array $fetchOneRows = [];
    public array $lastSql = [];
    public array $lastParams = [];
    public array $updateRows = [];
    public array $settings = [
        'umpire_slot_1_label' => 'Plate',
        'umpire_slot_2_label' => 'Bases',
        'umpire_assignor_name' => 'Alex Assignor',
        'umpire_assignor_email' => 'assignor@example.test',
    ];

    public function __construct() {}

    public function query($sql, $params = []) {
        $this->lastSql[] = $sql;
        $this->lastParams[] = $params;
        $rows = !empty($this->queryRows) ? array_shift($this->queryRows) : [];
        return new UmpireNotificationMockStmt($rows);
    }

    public function fetchOne($sql, $params = []) {
        $this->lastSql[] = $sql;
        $this->lastParams[] = $params;
        if (strpos($sql, 'FROM settings') !== false) {
            $key = $params[0] ?? ($params['setting_key'] ?? '');
            return array_key_exists($key, $this->settings) ? ['setting_value' => $this->settings[$key]] : false;
        }
        if (!empty($this->fetchOneRows)) {
            return array_shift($this->fetchOneRows);
        }
        return false;
    }

    public function update($table, $data, $where, $whereParams = []) {
        $this->updateRows[] = [
            'table' => $table,
            'data' => $data,
            'where' => $where,
            'whereParams' => $whereParams,
        ];
        return new UmpireNotificationMockStmt([]);
    }
}

class UmpireNotificationMockStmt {
    private array $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function fetch($mode = null) { return $this->rows[0] ?? false; }
    public function fetchAll($mode = null): array { return $this->rows; }
}

Database::setInstance(new UmpireNotificationMockDb());
$GLOBALS['d8tl_db_initialized'] = true;
require_once __DIR__ . '/../../includes/process_umpire_notifications.php';

class UmpireNotificationFakeEmailService extends EmailService {
    public array $calls = [];
    public array $failFor = [];

    public function __construct() {}

    public function sendTemplateToAddressWithMetadata($templateName, $toEmail, $context = [], $options = []) {
        $this->calls[] = [
            'template' => $templateName,
            'to' => $toEmail,
            'context' => $context,
            'options' => $options,
        ];
        if (trim($toEmail) === '' || in_array($toEmail, $this->failFor, true)) {
            return ['success' => false, 'queue_id' => null];
        }
        return ['success' => true, 'queue_id' => 7000 + count($this->calls)];
    }
}

function umpire_notification_row(int $notificationId, string $email, int $retryCount = 0, string $notificationType = 'cascade_cancelled'): array {
    return [
        'notification_id' => $notificationId,
        'notification_type' => $notificationType,
        'assignment_id' => 1000 + $notificationId,
        'umpire_user_id' => 2000 + $notificationId,
        'trigger_event_ref' => 'SCR-55',
        'retry_count' => $retryCount,
        'game_id' => 10,
        'slot_index' => $notificationId % 2,
        'assignment_status' => 'Cancelled',
        'umpire_email' => $email,
        'umpire_first_name' => 'Pat',
        'umpire_last_name' => 'Blue',
        'game_number' => 'G010',
        'game_status' => 'Postponed',
        'game_date' => '2026-07-01',
        'game_time' => '18:00:00',
        'location_name' => 'Field 1',
        'division_name' => 'Junior',
        'home_team' => 'Home',
        'away_team' => 'Away',
    ];
}

function umpire_assignor_alert_row(int $notificationId, int $retryCount = 0, string $gameStatus = 'Scheduled'): array {
    $row = umpire_notification_row($notificationId, 'pat@example.test', $retryCount, 'assignor_alert');
    $row['game_status'] = $gameStatus;
    $row['game_date'] = '2026-07-08';
    $row['location_name'] = 'Field 2';
    return $row;
}

function umpire_assignor_audit_row(array $umpireUserIds): array {
    return [
        'context' => json_encode([
            'game_id' => 10,
            'trigger_event_ref' => 'SCR-55',
            'released_umpire_user_ids' => $umpireUserIds,
        ]),
    ];
}

function umpire_released_name_rows(): array {
    return [
        ['id' => 201, 'first_name' => 'Pat', 'last_name' => 'Blue'],
        ['id' => 202, 'first_name' => 'Sam', 'last_name' => 'Green'],
    ];
}

register_test('23.5 processor exits cleanly for empty queue', function () {
    seed_umpire_notification_settings();
    $db = new UmpireNotificationMockDb();
    $db->queryRows = [[]];
    Database::setInstance($db);
    $email = new UmpireNotificationFakeEmailService();

    $result = processUmpirePendingNotifications(25, $email, $db);

    assert_equals($result['processed'], 0, 'Expected no processed rows');
    assert_equals(count($email->calls), 0, 'Expected no email sends');
    assert_equals(count($db->updateRows), 0, 'Expected no row updates');
});

register_test('23.5 processor sends release email with Reply-To metadata and marks sent', function () {
    seed_umpire_notification_settings();
    $db = new UmpireNotificationMockDb();
    $db->queryRows = [[umpire_notification_row(1, 'pat@example.test')]];
    Database::setInstance($db);
    $email = new UmpireNotificationFakeEmailService();

    $result = processUmpirePendingNotifications(25, $email, $db);

    assert_equals($result['sent'], 1, 'Expected one sent row');
    assert_equals($email->calls[0]['template'] ?? null, 'umpire_cascade_cancelled', 'Expected cascade template');
    assert_equals($email->calls[0]['context']['slot_label'] ?? null, 'Bases', 'Expected configured slot label from getSlotLabels');
    assert_equals($email->calls[0]['options']['reply_to_email'] ?? null, 'assignor@example.test', 'Expected Reply-To email');
    assert_true(strpos(strip_tags(implode(' ', $email->calls[0]['context'])), '2026') !== false, 'Expected readable context values');
    assert_true(isset($email->calls[0]['context']['game_status']), 'Expected game status context');
    assert_true(!isset($email->calls[0]['context']['requested_date']), 'Expected no speculative requested date context');
    assert_true(array_key_exists('failure_reason', $db->updateRows[0]['data']), 'Expected failure_reason field on success update');
    assert_equals($db->updateRows[0]['data']['failure_reason'], null, 'Expected failure reason cleared on success');
    assert_true(isset($db->updateRows[0]['data']['sent_at']), 'Expected sent_at update');
});

register_test('23.5 processor records per-row failure and continues later rows', function () {
    seed_umpire_notification_settings();
    $db = new UmpireNotificationMockDb();
    $db->queryRows = [[
        umpire_notification_row(1, 'bad@example.test'),
        umpire_notification_row(2, 'good@example.test'),
    ]];
    Database::setInstance($db);
    $email = new UmpireNotificationFakeEmailService();
    $email->failFor = ['bad@example.test'];

    $result = processUmpirePendingNotifications(25, $email, $db);

    assert_equals($result['processed'], 2, 'Expected both rows processed');
    assert_equals($result['failed'], 1, 'Expected one failed row');
    assert_equals($result['sent'], 1, 'Expected later row still sent');
    assert_true(array_key_exists('failed_at', $db->updateRows[0]['data']), 'Expected failed_at key on failed row');
    assert_equals($db->updateRows[0]['data']['failed_at'], null, 'Expected failed_at null on first retry (not max yet)');
    assert_true(isset($db->updateRows[0]['data']['failure_reason']), 'Expected failure_reason on failed row');
    assert_equals($db->updateRows[0]['data']['retry_count'], 1, 'Expected retry_count incremented to 1');
    assert_true(isset($db->updateRows[1]['data']['sent_at']), 'Expected sent_at on later successful row');
});

register_test('23.5 processor gracefully handles missing umpire email', function () {
    seed_umpire_notification_settings();
    $db = new UmpireNotificationMockDb();
    $db->queryRows = [[umpire_notification_row(1, '')]];
    Database::setInstance($db);
    $email = new UmpireNotificationFakeEmailService();

    $result = processUmpirePendingNotifications(25, $email, $db);

    assert_equals($result['processed'], 1, 'Expected row processed');
    assert_equals($result['failed'], 1, 'Expected send to fail for empty email');
    assert_equals($result['sent'], 0, 'Expected no successful sends');
    assert_equals(count($email->calls), 1, 'Expected send attempt with empty email');
    assert_true(isset($db->updateRows[0]['data']['retry_count']), 'Expected retry_count incremented');
    assert_equals($db->updateRows[0]['data']['retry_count'], 1, 'Expected retry_count = 1 after first failure');
});

register_test('23.5 processor retries failed notifications up to max count', function () {
    seed_umpire_notification_settings();
    $db = new UmpireNotificationMockDb();
    $db->queryRows = [[umpire_notification_row(1, 'retry@example.test', 2)]];
    Database::setInstance($db);
    $email = new UmpireNotificationFakeEmailService();
    $email->failFor = ['retry@example.test'];

    $result = processUmpirePendingNotifications(25, $email, $db);

    assert_equals($result['failed'], 1, 'Expected failure');
    assert_equals($db->updateRows[0]['data']['retry_count'], 3, 'Expected retry_count = 3 after third failure');
    assert_true(isset($db->updateRows[0]['data']['failed_at']), 'Expected failed_at set on max retries');
    assert_true($db->updateRows[0]['data']['failed_at'] !== null, 'Expected failed_at not null on max retries');
});

register_test('23.5 processor clears failed_at on successful retry', function () {
    seed_umpire_notification_settings();
    $db = new UmpireNotificationMockDb();
    $db->queryRows = [[umpire_notification_row(1, 'success@example.test', 1)]];
    Database::setInstance($db);
    $email = new UmpireNotificationFakeEmailService();

    $result = processUmpirePendingNotifications(25, $email, $db);

    assert_equals($result['sent'], 1, 'Expected successful send');
    assert_true(array_key_exists('failed_at', $db->updateRows[0]['data']), 'Expected failed_at in update');
    assert_equals($db->updateRows[0]['data']['failed_at'], null, 'Expected failed_at cleared on success');
    assert_true(isset($db->updateRows[0]['data']['sent_at']), 'Expected sent_at set');
});

register_test('24.6 processor sends assignor SCR alert to assignor email with SCR context', function () {
    seed_umpire_notification_settings();
    $db = new UmpireNotificationMockDb();
    $db->queryRows = [
        [umpire_assignor_alert_row(1)],
        [umpire_assignor_audit_row([201, 202])],
        umpire_released_name_rows(),
        [[]],
    ];
    $db->fetchOneRows = [
        [
            'original_date' => '2026-07-01',
            'original_time' => '18:00:00',
            'original_location' => 'Field 1',
        ],
        [
            'game_date' => '2026-07-08',
            'game_time' => '18:00:00',
            'location' => 'Field 2',
            'location_name' => 'Field 2',
        ],
    ];
    Database::setInstance($db);
    $email = new UmpireNotificationFakeEmailService();

    $result = processUmpirePendingNotifications(25, $email, $db);

    assert_equals($result['sent'], 1, 'Expected assignor alert sent');
    assert_equals($email->calls[0]['template'] ?? null, 'umpire_assignor_scr_alert', 'Expected assignor SCR template');
    assert_equals($email->calls[0]['to'] ?? null, 'assignor@example.test', 'Expected assignor alert sent TO assignor email');
    assert_true(($email->calls[0]['to'] ?? '') !== 'pat@example.test', 'Expected assignor alert not sent to anchor umpire email');
    assert_equals($email->calls[0]['options']['reply_to_email'] ?? null, 'assignor@example.test', 'Expected Reply-To assignor email');
    assert_equals($email->calls[0]['options']['include_configured_recipients'] ?? null, false, 'Expected direct assignor send only');
    assert_equals($email->calls[0]['context']['original_game_date'] ?? null, '07/01/2026', 'Expected original date from SCR request');
    assert_equals($email->calls[0]['context']['original_location'] ?? null, 'Field 1', 'Expected original location from SCR request');
    assert_equals($email->calls[0]['context']['new_game_date'] ?? null, '07/08/2026', 'Expected new date from schedule/history');
    assert_equals($email->calls[0]['context']['released_umpires'] ?? null, 'Pat Blue, Sam Green', 'Expected aggregated released umpire names');
    assert_true(strpos(implode("\n", $db->lastSql), "notification_type = 'assignor_alert'") !== false, 'Expected batch mark for assignor alert rows');
});

register_test('24.6 processor excludes draft-only cascade rows from assignor released names', function () {
    seed_umpire_notification_settings();
    $db = new UmpireNotificationMockDb();
    $db->queryRows = [
        [umpire_assignor_alert_row(1)],
        [umpire_assignor_audit_row([202])],
        [
            ['id' => 202, 'first_name' => 'Sam', 'last_name' => 'Green'],
        ],
        [[]],
    ];
    $db->fetchOneRows = [
        [
            'original_date' => '2026-07-01',
            'original_time' => '18:00:00',
            'original_location' => 'Field 1',
        ],
        [
            'game_date' => '2026-07-08',
            'game_time' => '18:00:00',
            'location' => 'Field 2',
            'location_name' => 'Field 2',
        ],
    ];
    Database::setInstance($db);
    $email = new UmpireNotificationFakeEmailService();

    $result = processUmpirePendingNotifications(25, $email, $db);

    assert_equals($result['sent'], 1, 'Expected assignor alert sent');
    assert_equals($email->calls[0]['context']['released_umpires'] ?? null, 'Sam Green', 'Expected only published umpire names from assignor audit context');
});

register_test('24.6 processor uses postponement fallback text for assignor SCR alert new details', function () {
    seed_umpire_notification_settings();
    $db = new UmpireNotificationMockDb();
    $db->queryRows = [
        [umpire_assignor_alert_row(1, 0, 'Postponed')],
        [umpire_assignor_audit_row([201])],
        [['id' => 201, 'first_name' => 'Pat', 'last_name' => 'Blue']],
        [[]],
    ];
    $db->fetchOneRows = [[
        'original_date' => '2026-07-01',
        'original_time' => '18:00:00',
        'original_location' => 'Field 1',
    ]];
    Database::setInstance($db);
    $email = new UmpireNotificationFakeEmailService();

    $result = processUmpirePendingNotifications(25, $email, $db);

    assert_equals($result['sent'], 1, 'Expected postponement assignor alert sent');
    assert_equals($email->calls[0]['context']['new_game_date'] ?? null, 'Not available in this alert', 'Expected postponement fallback for new date');
    assert_equals($email->calls[0]['context']['new_game_time'] ?? null, 'Not available in this alert', 'Expected postponement fallback for new time');
    assert_equals($email->calls[0]['context']['new_location'] ?? null, 'Not available in this alert', 'Expected postponement fallback for new location');
});

register_test('24.6 processor deduplicates assignor alert rows in one batch', function () {
    seed_umpire_notification_settings();
    $db = new UmpireNotificationMockDb();
    $db->queryRows = [
        [
            umpire_assignor_alert_row(1),
            umpire_assignor_alert_row(2),
        ],
        [umpire_assignor_audit_row([201])],
        [['id' => 201, 'first_name' => 'Pat', 'last_name' => 'Blue']],
        [[]],
        [[]],
    ];
    $db->fetchOneRows = [
        [
            'original_date' => '2026-07-01',
            'original_time' => '18:00:00',
            'original_location' => 'Field 1',
        ],
        [
            'game_date' => '2026-07-08',
            'game_time' => '18:00:00',
            'location' => 'Field 2',
            'location_name' => 'Field 2',
        ],
    ];
    Database::setInstance($db);
    $email = new UmpireNotificationFakeEmailService();

    $result = processUmpirePendingNotifications(25, $email, $db);

    assert_equals($result['processed'], 2, 'Expected both assignor alert rows processed');
    assert_equals($result['sent'], 2, 'Expected duplicate row marked sent without resending');
    assert_equals(count($email->calls), 1, 'Expected only one assignor alert email');
});

register_test('24.6 processor records assignor alert failure and continues later rows', function () {
    seed_umpire_notification_settings();
    $db = new UmpireNotificationMockDb();
    $db->queryRows = [
        [
            umpire_assignor_alert_row(1),
            umpire_notification_row(2, 'good@example.test'),
        ],
        [umpire_assignor_audit_row([201])],
        [['id' => 201, 'first_name' => 'Pat', 'last_name' => 'Blue']],
    ];
    $db->fetchOneRows = [
        [
            'original_date' => '2026-07-01',
            'original_time' => '18:00:00',
            'original_location' => 'Field 1',
        ],
        [
            'game_date' => '2026-07-08',
            'game_time' => '18:00:00',
            'location' => 'Field 2',
            'location_name' => 'Field 2',
        ],
    ];
    Database::setInstance($db);
    $email = new UmpireNotificationFakeEmailService();
    $email->failFor = ['assignor@example.test'];

    $result = processUmpirePendingNotifications(25, $email, $db);

    assert_equals($result['processed'], 2, 'Expected both rows processed');
    assert_equals($result['failed'], 1, 'Expected assignor alert failure');
    assert_equals($result['sent'], 1, 'Expected later cascade row still sent');
    assert_equals($email->calls[0]['template'] ?? null, 'umpire_assignor_scr_alert', 'Expected assignor alert attempted first');
    assert_equals($email->calls[1]['template'] ?? null, 'umpire_cascade_cancelled', 'Expected cascade email after assignor failure');
    assert_equals($db->updateRows[0]['data']['retry_count'] ?? null, 1, 'Expected assignor alert retry count incremented');
    assert_true(isset($db->updateRows[1]['data']['sent_at']), 'Expected later cascade row marked sent');
});

register_test('24.6 processor does not immediately retry duplicate assignor alert after first failure', function () {
    seed_umpire_notification_settings();
    $db = new UmpireNotificationMockDb();
    $db->queryRows = [
        [
            umpire_assignor_alert_row(1),
            umpire_assignor_alert_row(2),
            umpire_notification_row(3, 'good@example.test'),
        ],
        [umpire_assignor_audit_row([201])],
        [['id' => 201, 'first_name' => 'Pat', 'last_name' => 'Blue']],
        [[]],
    ];
    $db->fetchOneRows = [
        [
            'original_date' => '2026-07-01',
            'original_time' => '18:00:00',
            'original_location' => 'Field 1',
        ],
        [
            'game_date' => '2026-07-08',
            'game_time' => '18:00:00',
            'location' => 'Field 2',
            'location_name' => 'Field 2',
        ],
    ];
    Database::setInstance($db);
    $email = new UmpireNotificationFakeEmailService();
    $email->failFor = ['assignor@example.test'];

    $result = processUmpirePendingNotifications(25, $email, $db);

    assert_equals($result['processed'], 3, 'Expected all rows processed');
    assert_equals($result['failed'], 1, 'Expected only first assignor alert failure recorded');
    assert_equals($result['sent'], 2, 'Expected duplicate marked handled and later cascade sent');
    assert_equals(count($email->calls), 2, 'Expected no second assignor alert email attempt');
    assert_equals($email->calls[0]['template'] ?? null, 'umpire_assignor_scr_alert', 'Expected assignor alert attempted once');
    assert_equals($email->calls[1]['template'] ?? null, 'umpire_cascade_cancelled', 'Expected later cascade email still sent');
});

register_test('24.6 processor rejects malformed SCR trigger suffixes', function () {
    assert_equals(umpireParseScrRequestId('SCR-55'), 55, 'Expected valid SCR trigger to parse');
    assert_equals(umpireParseScrRequestId('SCR-55-extra'), null, 'Expected trailing text rejected');
    assert_equals(umpireParseScrRequestId('SCR-0'), null, 'Expected zero request id rejected');
    assert_equals(umpireParseScrRequestId('DIRECT-SCHEDULE-55'), null, 'Expected non-SCR trigger rejected');
});
