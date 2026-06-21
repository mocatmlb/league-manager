<?php
/**
 * Unit Tests: EmailService
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';

if (!defined('EMAIL_DEV_LOG_ONLY')) {
    define('EMAIL_DEV_LOG_ONLY', true);
}

class EmailServiceMockDb extends Database {
    public array $fetchOneRows = [];
    public array $fetchAllRows = [];
    public array $insertRows = [];
    public array $updateRows = [];
    public array $lastSql = [];
    public array $lastParams = [];
    public int $nextInsertId = 9100;

    public function __construct() {}

    public function fetchOne($sql, $params = []) {
        $this->lastSql[] = $sql;
        $this->lastParams[] = $params;
        if (!empty($this->fetchOneRows)) {
            return array_shift($this->fetchOneRows);
        }
        return false;
    }

    public function fetchAll($sql, $params = []) {
        $this->lastSql[] = $sql;
        $this->lastParams[] = $params;
        if (!empty($this->fetchAllRows)) {
            return array_shift($this->fetchAllRows);
        }
        return [];
    }

    public function insert($table, $data) {
        $this->insertRows[] = ['table' => $table, 'data' => $data];
        return $this->nextInsertId++;
    }

    public function update($table, $data, $where, $whereParams = []) {
        $this->updateRows[] = [
            'table' => $table,
            'data' => $data,
            'where' => $where,
            'whereParams' => $whereParams,
        ];
        return true;
    }
}

require_once __DIR__ . '/../../includes/EmailService.php';

register_test('23.4 EmailService queues direct-address template with queue id and per-message Reply-To metadata', function () {
    $mock = new EmailServiceMockDb();
    $mock->fetchOneRows = [
        [
            'template_name' => 'umpire_assignment_published',
            'subject_template' => 'D8 Assignment: {game_date} {game_time} — {slot_label}',
            'body_template' => '<p>{game_number}</p><p>{assignor_phone_tel}</p>',
            'is_active' => 1,
        ],
    ];
    Database::setInstance($mock);

    $svc = new EmailService();
    $result = $svc->sendTemplateToAddressWithMetadata(
        'umpire_assignment_published',
        'umpire@example.test',
        [
            'game_id' => 10,
            'game_number' => 'G010',
            'game_date' => '07/01/2026',
            'game_time' => '6:00 PM',
            'slot_label' => 'Plate',
            'assignor_phone_tel' => 'tel:5552223333',
        ],
        [
            'reply_to_email' => 'assignor@example.test',
            'reply_to_name' => 'Alex Assignor',
        ]
    );

    assert_true($result['success'] ?? false, 'Expected direct template send to succeed in dev-log mode');
    assert_equals($result['queue_id'] ?? null, 9100, 'Expected queue id returned');
    assert_equals($mock->insertRows[0]['table'] ?? null, 'email_queue', 'Expected email_queue insert');
    $queued = $mock->insertRows[0]['data'];
    assert_equals($queued['reply_to_email'] ?? null, 'assignor@example.test', 'Expected queued Reply-To email');
    assert_equals($queued['reply_to_name'] ?? null, 'Alex Assignor', 'Expected queued Reply-To name');
    assert_true(strpos($queued['subject'] ?? '', 'D8 Assignment: 07/01/2026 6:00 PM') !== false, 'Expected processed subject');
    assert_equals($mock->updateRows[0]['data']['status'] ?? null, 'Sent', 'Expected EMAIL_DEV_LOG_ONLY to mark queue Sent');
});

register_test('EmailService resolves assigned umpire recipient source for game-based notifications', function () {
    $mock = new EmailServiceMockDb();
    $mock->fetchOneRows = [
        [
            'template_name' => 'umpire_assignment_published',
            'subject_template' => 'Assignment {game_number}',
            'body_template' => '<p>{game_number}</p>',
            'is_active' => 1,
        ],
    ];
    $mock->fetchAllRows = [
        [
            [
                'template_name' => 'umpire_assignment_published',
                'recipient_type' => 'Team_Based',
                'recipient_source' => 'Assigned_Umpires',
                'email_address' => null,
                'is_active' => 1,
            ],
        ],
        [
            ['email' => 'plate@example.test'],
            ['email' => 'base@example.test'],
        ],
    ];
    Database::setInstance($mock);

    $svc = new EmailService();
    $sent = $svc->triggerNotification('umpire_assignment_published', [
        'game_id' => 10,
        'game_number' => 'G010',
    ]);

    assert_true($sent, 'Expected assigned umpire notification to queue successfully');
    assert_equals($mock->insertRows[0]['table'] ?? null, 'email_queue', 'Expected email_queue insert');
    $to = json_decode($mock->insertRows[0]['data']['to_addresses'] ?? '[]', true);
    assert_equals($to, ['plate@example.test', 'base@example.test'], 'Expected assigned umpire emails as recipients');
    $sqlLog = implode("\n", $mock->lastSql);
    assert_true(strpos($sqlLog, 'game_umpire_assignments') !== false, 'Expected assigned umpire lookup query');
    assert_true(strpos($sqlLog, 'gua.assignment_status IN') !== false, 'Expected assigned slot status filter');
});

register_test('EmailService resolves all active league contact recipients', function () {
    $mock = new EmailServiceMockDb();
    $mock->fetchOneRows = [
        [
            'template_name' => 'league_notice',
            'subject_template' => 'League Notice',
            'body_template' => '<p>Notice</p>',
            'is_active' => 1,
        ],
    ];
    $mock->fetchAllRows = [
        [
            [
                'template_name' => 'league_notice',
                'recipient_type' => 'Static_To',
                'recipient_source' => 'League_Contacts',
                'email_address' => null,
                'league_official_id' => null,
                'is_active' => 1,
            ],
        ],
        [
            ['email' => 'director@example.test'],
            ['email' => 'assignor@example.test'],
        ],
    ];
    Database::setInstance($mock);

    $svc = new EmailService();
    $sent = $svc->triggerNotification('league_notice', []);

    assert_true($sent, 'Expected league contact notification to queue successfully');
    $to = json_decode($mock->insertRows[0]['data']['to_addresses'] ?? '[]', true);
    assert_equals($to, ['director@example.test', 'assignor@example.test'], 'Expected active league contact emails as recipients');
    $sqlLog = implode("\n", $mock->lastSql);
    assert_true(strpos($sqlLog, 'FROM league_officials') !== false, 'Expected league official lookup query');
    assert_true(strpos($sqlLog, "active_status = 'Active'") !== false, 'Expected active contact filter');
});

register_test('EmailService resolves one selected league contact recipient', function () {
    $mock = new EmailServiceMockDb();
    $mock->fetchOneRows = [
        [
            'template_name' => 'league_notice',
            'subject_template' => 'League Notice',
            'body_template' => '<p>Notice</p>',
            'is_active' => 1,
        ],
        ['email' => 'assignor@example.test'],
    ];
    $mock->fetchAllRows = [
        [
            [
                'template_name' => 'league_notice',
                'recipient_type' => 'Static_CC',
                'recipient_source' => 'League_Contact',
                'email_address' => null,
                'league_official_id' => 12,
                'is_active' => 1,
            ],
        ],
    ];
    Database::setInstance($mock);

    $svc = new EmailService();
    $sent = $svc->triggerNotification('league_notice', []);

    assert_true($sent, 'Expected selected league contact notification to queue successfully');
    $cc = json_decode($mock->insertRows[0]['data']['cc_addresses'] ?? '[]', true);
    assert_equals($cc, ['assignor@example.test'], 'Expected selected league contact as CC recipient');
    $paramsLog = json_encode($mock->lastParams);
    assert_true(strpos($paramsLog, '12') !== false, 'Expected selected official id in query params');
});

register_test('Email recipients settings section exposes dynamic league contact selector', function () {
    $source = file_get_contents(__DIR__ . '/../../public/admin/settings/sections/email-recipients.php');
    assert_true(strpos($source, 'FROM league_officials') !== false, 'Expected active league contacts query');
    assert_true(strpos($source, 'League_Contacts') !== false, 'Expected all league contacts source option');
    assert_true(strpos($source, 'League_Contact') !== false, 'Expected selected league contact source option');
    assert_true(strpos($source, 'league_official_id') !== false, 'Expected selected contact id field');
    assert_true(strpos($source, 'editLeagueOfficialId') !== false, 'Expected edit modal contact selector');
});
