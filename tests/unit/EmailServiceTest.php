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
    public array $insertRows = [];
    public array $updateRows = [];
    public int $nextInsertId = 9100;

    public function __construct() {}

    public function fetchOne($sql, $params = []) {
        if (!empty($this->fetchOneRows)) {
            return array_shift($this->fetchOneRows);
        }
        return false;
    }

    public function fetchAll($sql, $params = []) {
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
