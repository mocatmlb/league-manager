<?php
/**
 * Unit Tests: InvitationService
 *
 * Story 3.3 — Invitation Service & Admin Invitation Management
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ActivityLogger.php';
require_once __DIR__ . '/../../includes/RegistrationService.php';
require_once __DIR__ . '/../../includes/InvitationService.php';

class InvitationMockStatement {
    private int $rowCount;
    public function __construct(int $rowCount = 1) { $this->rowCount = $rowCount; }
    public function rowCount(): int { return $this->rowCount; }
}

class InvitationMockDatabase extends Database {
    public array $users = [];
    public array $invitations = [];
    public array $activityLogEvents = [];
    public int $nextInvitationId = 1;

    public function __construct() {
        // Intentionally bypass Database real connection initialization.
    }

    public function fetchOne($sql, $params = []) {
        $sql = trim($sql);

        if (stripos($sql, 'information_schema.COLUMNS') !== false && stripos($sql, 'COLUMN_TYPE') !== false) {
            if (($params[0] ?? '') === 'user_invitations' && ($params[1] ?? '') === 'status') {
                return ['column_type' => "enum('pending','completed','cancelled','expired')"];
            }
            return false;
        }

        if (stripos($sql, 'SELECT id FROM users WHERE email = :email') !== false) {
            foreach ($this->users as $user) {
                if ($user['email'] === $params['email']) {
                    return ['id' => $user['id']];
                }
            }
            return false;
        }

        if (stripos($sql, "SELECT id FROM roles WHERE name = 'user'") !== false) {
            return ['id' => 1];
        }

        if (stripos($sql, 'FROM user_invitations') !== false && stripos($sql, 'WHERE token = :token') !== false) {
            foreach ($this->invitations as $inv) {
                if (($inv['token'] ?? null) === $params['token']) {
                    return $inv;
                }
            }
            return false;
        }

        if (stripos($sql, 'SELECT email, status FROM user_invitations WHERE id = :id') !== false) {
            foreach ($this->invitations as $inv) {
                if ((int) $inv['id'] === (int) $params['id']) {
                    return ['email' => $inv['email'], 'status' => $inv['status']];
                }
            }
            return false;
        }

        // Legacy mock branch for older lookup signature kept for compatibility.
        if (stripos($sql, 'SELECT email FROM user_invitations WHERE id = :id') !== false) {
            foreach ($this->invitations as $inv) {
                if ((int) $inv['id'] === (int) $params['id']) {
                    return ['email' => $inv['email']];
                }
            }
            return false;
        }

        return false;
    }

    public function fetchAll($sql, $params = []): array {
        if (stripos($sql, 'FROM user_invitations') !== false) {
            return array_values($this->invitations);
        }
        return [];
    }

    public function query($sql, $params = []) {
        $sql = trim($sql);

        if (stripos($sql, 'INSERT INTO activity_log') !== false) {
            $this->activityLogEvents[] = [
                'event' => $params['event'],
                'context' => json_decode($params['context'], true),
            ];
            return new InvitationMockStatement(1);
        }

        if (stripos($sql, 'INSERT INTO user_invitations') !== false) {
            $this->invitations[] = [
                'id' => $this->nextInvitationId++,
                'email' => $params['email'],
                'token' => $params['token'],
                'role_id' => $params['role_id'],
                'invited_by' => $params['invited_by'],
                'status' => 'pending',
                'expires_at' => $params['expires_at'],
                'created_at' => date('Y-m-d H:i:s'),
                'completed_at' => null,
            ];
            return new InvitationMockStatement(1);
        }

        // Cancel-by-email pattern (parameterized cancel_status post-migration-009).
        if (stripos($sql, 'SET status = :cancel_status') !== false && stripos($sql, 'WHERE email = :email') !== false) {
            $newStatus = (string) ($params['cancel_status'] ?? 'expired');
            $rows = 0;
            foreach ($this->invitations as &$inv) {
                if ($inv['email'] === $params['email'] && $inv['status'] === 'pending') {
                    $inv['status'] = $newStatus;
                    $rows++;
                }
            }
            unset($inv);
            return new InvitationMockStatement($rows);
        }

        // Cancel/resend update by id (parameterized cancel_status).
        if (stripos($sql, 'SET status = :cancel_status') !== false && stripos($sql, 'WHERE id = :id') !== false) {
            $newStatus = (string) ($params['cancel_status'] ?? 'expired');
            $rows = 0;
            foreach ($this->invitations as &$inv) {
                if ((int) $inv['id'] === (int) $params['id']) {
                    // Match cancel() guard (status='pending') if present in SQL.
                    if (stripos($sql, "AND status = 'pending'") !== false && $inv['status'] !== 'pending') {
                        continue;
                    }
                    $inv['status'] = $newStatus;
                    if (isset($params['expires_at'])) {
                        $inv['expires_at'] = $params['expires_at'];
                    }
                    $rows++;
                }
            }
            unset($inv);
            return new InvitationMockStatement($rows);
        }

        // Legacy hardcoded-expired branches (pre-cancel_status code path).
        if (stripos($sql, "SET status = 'expired'") !== false && stripos($sql, 'WHERE id = :id') !== false) {
            foreach ($this->invitations as &$inv) {
                if ((int) $inv['id'] === (int) $params['id']) {
                    $inv['status'] = 'expired';
                    if (isset($params['expires_at'])) {
                        $inv['expires_at'] = $params['expires_at'];
                    }
                }
            }
            unset($inv);
            return new InvitationMockStatement(1);
        }

        if (stripos($sql, "SET status = 'completed'") !== false) {
            foreach ($this->invitations as &$inv) {
                if ((int) $inv['id'] === (int) $params['id']) {
                    $inv['status'] = 'completed';
                    $inv['completed_at'] = date('Y-m-d H:i:s');
                }
            }
            unset($inv);
            return new InvitationMockStatement(1);
        }

        return new InvitationMockStatement(0);
    }
}

class InvitationMockEmail {
    public array $calls = [];
    public bool $forceFailure = false;
    public function triggerNotification($templateName, $context = []) {
        $this->calls[] = ['template' => $templateName, 'context' => $context];
        return !$this->forceFailure;
    }
}

register_test('AC1-P0: send creates pending invitation and sends email', function () {
    $db = new InvitationMockDatabase();
    $email = new InvitationMockEmail();
    Database::setInstance($db);
    $service = new InvitationService($db, $email);

    $service->send('newcoach@example.com', 99);

    assert_equals(count($db->invitations), 1, 'send must create one invitation row');
    assert_equals($db->invitations[0]['status'], 'pending', 'invitation must be pending');
    assert_equals($email->calls[0]['template'], 'registration_invitation', 'invitation email must be sent');
    assert_equals($db->activityLogEvents[0]['event'], 'registration.invitation_sent', 'send must log registration.invitation_sent');

    Database::setInstance(null);
});

register_test('AC3-P0: send throws EmailAlreadyRegisteredException for existing account', function () {
    $db = new InvitationMockDatabase();
    $db->users[] = ['id' => 1, 'email' => 'existing@example.com'];
    $email = new InvitationMockEmail();
    Database::setInstance($db);
    $service = new InvitationService($db, $email);

    $thrown = false;
    try {
        $service->send('existing@example.com', 99);
    } catch (EmailAlreadyRegisteredException $e) {
        $thrown = true;
    }
    assert_true($thrown, 'existing user email must throw EmailAlreadyRegisteredException');
    assert_equals(count($db->invitations), 0, 'no invitation should be created');

    Database::setInstance(null);
});

register_test('AC2-P0: send to existing pending email expires old token and creates new invitation', function () {
    $db = new InvitationMockDatabase();
    $db->invitations[] = [
        'id' => 1,
        'email' => 'coach@example.com',
        'token' => 'old-token',
        'role_id' => 1,
        'invited_by' => 50,
        'status' => 'pending',
        'expires_at' => date('Y-m-d H:i:s', time() + 1000),
        'created_at' => date('Y-m-d H:i:s'),
        'completed_at' => null,
    ];
    $db->nextInvitationId = 2;
    $email = new InvitationMockEmail();
    Database::setInstance($db);
    $service = new InvitationService($db, $email);

    $service->send('coach@example.com', 99);

    assert_equals(count($db->invitations), 2, 'second invitation row should be created');
    assert_equals($db->invitations[0]['status'], 'cancelled', 'old pending invitation should be cancelled');
    assert_true($db->invitations[1]['token'] !== 'old-token', 'new invitation must have a replacement token');

    Database::setInstance(null);
});

register_test('AC4-P0: validate returns email for valid token and markConsumed completes invitation', function () {
    $db = new InvitationMockDatabase();
    $db->invitations[] = [
        'id' => 1,
        'email' => 'coach@example.com',
        'token' => 'valid-token',
        'role_id' => 1,
        'invited_by' => 50,
        'status' => 'pending',
        'expires_at' => date('Y-m-d H:i:s', time() + 1000),
        'created_at' => date('Y-m-d H:i:s'),
        'completed_at' => null,
    ];
    $email = new InvitationMockEmail();
    Database::setInstance($db);
    $service = new InvitationService($db, $email);

    $result = $service->validate('valid-token');
    assert_equals($result['email'], 'coach@example.com', 'validate must return invitation email');
    assert_equals($result['invitation_id'], 1, 'validate must return invitation id');

    $service->markConsumed(1);
    assert_equals($db->invitations[0]['status'], 'completed', 'markConsumed must complete invitation');

    Database::setInstance(null);
});

register_test('AC5-P0: validate throws ExpiredTokenException for expired token', function () {
    $db = new InvitationMockDatabase();
    $db->invitations[] = [
        'id' => 1,
        'email' => 'coach@example.com',
        'token' => 'expired-token',
        'role_id' => 1,
        'invited_by' => 50,
        'status' => 'pending',
        'expires_at' => date('Y-m-d H:i:s', time() - 1000),
        'created_at' => date('Y-m-d H:i:s'),
        'completed_at' => null,
    ];
    $email = new InvitationMockEmail();
    Database::setInstance($db);
    $service = new InvitationService($db, $email);

    $thrown = false;
    try {
        $service->validate('expired-token');
    } catch (ExpiredTokenException $e) {
        $thrown = true;
    }
    assert_true($thrown, 'expired invitation token must throw ExpiredTokenException');

    Database::setInstance(null);
});

register_test('AC8-P0: cancel sets invitation as inactive', function () {
    $db = new InvitationMockDatabase();
    $db->invitations[] = [
        'id' => 1,
        'email' => 'coach@example.com',
        'token' => 'pending-token',
        'role_id' => 1,
        'invited_by' => 50,
        'status' => 'pending',
        'expires_at' => date('Y-m-d H:i:s', time() + 1000),
        'created_at' => date('Y-m-d H:i:s'),
        'completed_at' => null,
    ];
    $email = new InvitationMockEmail();
    Database::setInstance($db);
    $service = new InvitationService($db, $email);

    $service->cancel(1, 99);
    assert_equals($db->invitations[0]['status'], 'cancelled', 'cancel should set status to cancelled');

    Database::setInstance(null);
});

register_test('AC7-P0: resend replaces token and re-sends invitation email', function () {
    $db = new InvitationMockDatabase();
    $db->invitations[] = [
        'id' => 1,
        'email' => 'coach@example.com',
        'token' => 'pending-token',
        'role_id' => 1,
        'invited_by' => 50,
        'status' => 'pending',
        'expires_at' => date('Y-m-d H:i:s', time() + 1000),
        'created_at' => date('Y-m-d H:i:s'),
        'completed_at' => null,
    ];
    $db->nextInvitationId = 2;
    $email = new InvitationMockEmail();
    Database::setInstance($db);
    $service = new InvitationService($db, $email);

    $oldToken = $db->invitations[0]['token'];
    $service->resend(1, 99);

    assert_equals(count($db->invitations), 2, 'resend should create a replacement invitation');
    assert_equals($db->invitations[0]['status'], 'cancelled', 'original invitation should be cancelled (deactivated)');
    assert_true($db->invitations[1]['token'] !== $oldToken, 'replacement invitation should have a new token');
    assert_equals($email->calls[count($email->calls) - 1]['template'], 'registration_invitation', 'resend should send invitation email');

    Database::setInstance(null);
});
