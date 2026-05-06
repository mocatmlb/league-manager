<?php
/**
 * Unit Tests: RegistrationService
 *
 * Story 3.1 — Registration Service & Email Verification Backend
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ActivityLogger.php';
require_once __DIR__ . '/../../includes/RegistrationService.php';

class RegistrationServiceMockStatement {
    private int $rowCount;
    public function __construct(int $rowCount = 1) {
        $this->rowCount = $rowCount;
    }
    public function rowCount(): int {
        return $this->rowCount;
    }
}

class RegistrationServiceMockConnection {
    private int $lastInsertId = 0;
    public function setLastInsertId(int $id): void {
        $this->lastInsertId = $id;
    }
    public function lastInsertId(): string {
        return (string) $this->lastInsertId;
    }
}

class RegistrationServiceMockDatabase extends Database {
    public array $users = [];
    public array $activityLogEvents = [];
    public array $queryCalls = [];
    public int $nextUserId = 1;
    public bool $hasRoleColumn = true;
    public bool $hasRoleIdColumn = false;
    public int $userRoleId = 1;

    private RegistrationServiceMockConnection $connection;

    public function __construct(array $initialUsers = []) {
        $this->users = $initialUsers;
        if (!empty($initialUsers)) {
            $this->nextUserId = max(array_column($initialUsers, 'id')) + 1;
        }
        $this->connection = new RegistrationServiceMockConnection();
    }

    public function fetchOne($sql, $params = []) {
        $sql = trim($sql);

        if (stripos($sql, 'SHOW COLUMNS FROM users LIKE') !== false) {
            $column = $params['column'] ?? '';
            if ($column === 'role' && $this->hasRoleColumn) {
                return ['Field' => 'role'];
            }
            if ($column === 'role_id' && $this->hasRoleIdColumn) {
                return ['Field' => 'role_id'];
            }
            if ($column === 'password_hash') {
                return ['Field' => 'password_hash'];
            }
            if ($column === 'password_changed_at') {
                return ['Field' => 'password_changed_at'];
            }
            return false;
        }

        if (stripos($sql, 'SELECT id FROM roles WHERE name = :name') !== false) {
            if (($params['name'] ?? null) === 'user') {
                return ['id' => $this->userRoleId];
            }
            return false;
        }

        if (stripos($sql, 'SELECT id FROM users WHERE username = :username') !== false) {
            foreach ($this->users as $user) {
                if ($user['username'] === $params['username']) {
                    return ['id' => $user['id']];
                }
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

        if (stripos($sql, 'FROM users') !== false && stripos($sql, 'WHERE verification_token = :token') !== false) {
            foreach ($this->users as $user) {
                if (($user['verification_token'] ?? null) === $params['token']) {
                    return [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'first_name' => $user['first_name'],
                        'verification_expiry' => $user['verification_expiry'] ?? null,
                    ];
                }
            }
            return false;
        }

        if (stripos($sql, 'SELECT id, email, first_name, status FROM users WHERE id = :id') !== false) {
            foreach ($this->users as $user) {
                if ((int) $user['id'] === (int) $params['id']) {
                    return [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'first_name' => $user['first_name'],
                        'status' => $user['status'],
                    ];
                }
            }
            return false;
        }

        if (stripos($sql, 'SELECT id, email, first_name, status FROM users WHERE email = :email') !== false) {
            foreach ($this->users as $user) {
                if (strcasecmp((string) ($user['email'] ?? ''), (string) ($params['email'] ?? '')) === 0) {
                    return [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'first_name' => $user['first_name'],
                        'status' => $user['status'],
                    ];
                }
            }
            return false;
        }

        if (stripos($sql, 'SELECT id, first_name, email') !== false && stripos($sql, 'WHERE email = :email') !== false) {
            foreach ($this->users as $user) {
                if ($user['email'] === $params['email']) {
                    return [
                        'id' => $user['id'],
                        'first_name' => $user['first_name'],
                        'email' => $user['email'],
                    ];
                }
            }
            return false;
        }

        if (stripos($sql, 'SELECT id, password_reset_expiry') !== false && stripos($sql, 'WHERE password_reset_token = :token') !== false) {
            foreach ($this->users as $user) {
                if (($user['password_reset_token'] ?? null) === $params['token']) {
                    return [
                        'id' => $user['id'],
                        'password_reset_expiry' => $user['password_reset_expiry'] ?? null,
                    ];
                }
            }
            return false;
        }

        return false;
    }

    public function query($sql, $params = []) {
        $this->queryCalls[] = ['sql' => trim($sql), 'params' => $params];
        $sql = trim($sql);

        if (stripos($sql, 'INSERT INTO users') !== false) {
            $id = $this->nextUserId++;
            $row = [
                'id' => $id,
                'username' => $params['username'],
                'email' => $params['email'],
                'password_hash' => $params['password_hash'],
                'first_name' => $params['first_name'],
                'last_name' => $params['last_name'],
                'phone' => $params['phone'],
                'status' => $params['status'],
                'verification_token' => $params['verification_token'],
                'verification_expiry' => $params['verification_expiry'],
            ];
            if (array_key_exists('role', $params)) {
                $row['role'] = $params['role'];
            }
            if (array_key_exists('role_id', $params)) {
                $row['role_id'] = (int) $params['role_id'];
            }
            $this->users[] = $row;
            $this->connection->setLastInsertId($id);
            return new RegistrationServiceMockStatement(1);
        }

        if (stripos($sql, 'INSERT INTO activity_log') !== false) {
            $this->activityLogEvents[] = [
                'event' => $params['event'],
                'context' => json_decode($params['context'], true),
            ];
            return new RegistrationServiceMockStatement(1);
        }

        if (stripos($sql, "SET status = 'active'") !== false) {
            $rowCount = 0;
            foreach ($this->users as &$user) {
                if ((int) $user['id'] === (int) $params['id'] && ($user['verification_token'] ?? null) === $params['token']) {
                    $user['status'] = 'active';
                    $user['verification_token'] = null;
                    $user['verification_expiry'] = null;
                    $rowCount = 1;
                    break;
                }
            }
            unset($user);
            return new RegistrationServiceMockStatement($rowCount);
        }

        if (stripos($sql, 'SET verification_token = :token') !== false) {
            $rowCount = 0;
            foreach ($this->users as &$user) {
                if ((int) $user['id'] === (int) $params['id']) {
                    $user['verification_token'] = $params['token'];
                    $user['verification_expiry'] = $params['expiry'];
                    $rowCount = 1;
                    break;
                }
            }
            unset($user);
            return new RegistrationServiceMockStatement($rowCount);
        }

        if (stripos($sql, 'SET password_reset_token = :token') !== false) {
            $rowCount = 0;
            foreach ($this->users as &$user) {
                if ((int) $user['id'] === (int) $params['id']) {
                    $user['password_reset_token'] = $params['token'];
                    $user['password_reset_expiry'] = $params['expiry'];
                    $rowCount = 1;
                    break;
                }
            }
            unset($user);
            return new RegistrationServiceMockStatement($rowCount);
        }

        if (stripos($sql, 'DELETE FROM remember_tokens') !== false) {
            return new RegistrationServiceMockStatement(1);
        }

        if (stripos($sql, 'UPDATE users SET') !== false && stripos($sql, 'password_reset_token') !== false && stripos($sql, 'WHERE id = :id') !== false) {
            $rowCount = 0;
            foreach ($this->users as &$user) {
                if ((int) $user['id'] === (int) ($params['id'] ?? 0)) {
                    foreach ($params as $key => $value) {
                        if ($key === 'id') {
                            continue;
                        }
                        $user[$key] = $value;
                    }
                    $rowCount = 1;
                    break;
                }
            }
            unset($user);
            return new RegistrationServiceMockStatement($rowCount);
        }

        return new RegistrationServiceMockStatement(0);
    }

    public function getConnection() {
        return $this->connection;
    }
}

class RegistrationServiceMockEmail {
    public array $calls = [];
    public bool $failVerificationEmail = false;
    public bool $failAdminNotification = false;
    public bool $failPasswordResetEmail = false;

    public function triggerNotification($templateName, $context = []) {
        $this->calls[] = [
            'template' => $templateName,
            'context' => $context,
        ];
        if ($templateName === 'registration_verification' && $this->failVerificationEmail) {
            return false;
        }
        if ($templateName === 'registration_account_verified' && $this->failAdminNotification) {
            return false;
        }
        if ($templateName === 'auth_password_reset' && $this->failPasswordResetEmail) {
            return false;
        }
        return true;
    }
}

function base_registration_payload(array $overrides = []): array {
    return array_merge([
        'username' => 'coach1',
        'email' => 'coach1@example.com',
        'password' => 'StrongPass1!',
        'first_name' => 'Casey',
        'last_name' => 'Coach',
        'phone' => '555-111-2222',
    ], $overrides);
}

register_test('AC1-P0: register creates unverified user with default role and sends verification email', function () {
    $db = new RegistrationServiceMockDatabase();
    $email = new RegistrationServiceMockEmail();
    Database::setInstance($db);
    $service = new RegistrationService($db, $email);

    $userId = $service->register(base_registration_payload());

    assert_true($userId > 0, 'register must return a new user id');
    assert_equals(count($db->users), 1, 'register must insert one user row');
    $user = $db->users[0];
    assert_equals($user['status'], 'unverified', 'new user must be unverified');
    assert_equals($user['role'], 'user', 'new user must have role=user');
    assert_true(!empty($user['verification_token']), 'verification token must be stored');
    assert_true(!empty($user['verification_expiry']), 'verification expiry must be stored');
    assert_equals($email->calls[0]['template'], 'registration_verification', 'verification email must be sent');
    assert_equals($db->activityLogEvents[0]['event'], 'registration.verification_email_sent', 'verification email sent event must be logged');

    Database::setInstance(null);
});

register_test('AC1-P1: register stores bcrypt hash and never stores plaintext', function () {
    $db = new RegistrationServiceMockDatabase();
    $email = new RegistrationServiceMockEmail();
    Database::setInstance($db);
    $service = new RegistrationService($db, $email);

    $payload = base_registration_payload(['password' => 'StrongPass1!']);
    $service->register($payload);

    $stored = $db->users[0]['password_hash'];
    assert_true($stored !== $payload['password'], 'stored password must not equal plaintext input');
    assert_true(password_verify($payload['password'], $stored), 'stored password must be a valid bcrypt hash');

    Database::setInstance(null);
});

register_test('AC2-P0: register throws DuplicateUsernameException on username collision', function () {
    $db = new RegistrationServiceMockDatabase([
        [
            'id' => 1,
            'username' => 'coach1',
            'email' => 'someone-else@example.com',
            'first_name' => 'A',
            'status' => 'active',
        ],
    ]);
    $email = new RegistrationServiceMockEmail();
    Database::setInstance($db);
    $service = new RegistrationService($db, $email);

    $thrown = false;
    try {
        $service->register(base_registration_payload());
    } catch (DuplicateUsernameException $e) {
        $thrown = true;
    }

    assert_true($thrown, 'duplicate username must throw DuplicateUsernameException');
    assert_equals(count($db->users), 1, 'duplicate username must not create an extra user row');

    Database::setInstance(null);
});

register_test('AC3-P0: register throws DuplicateEmailException on email collision', function () {
    $db = new RegistrationServiceMockDatabase([
        [
            'id' => 1,
            'username' => 'otheruser',
            'email' => 'coach1@example.com',
            'first_name' => 'A',
            'status' => 'active',
        ],
    ]);
    $email = new RegistrationServiceMockEmail();
    Database::setInstance($db);
    $service = new RegistrationService($db, $email);

    $thrown = false;
    try {
        $service->register(base_registration_payload());
    } catch (DuplicateEmailException $e) {
        $thrown = true;
    }

    assert_true($thrown, 'duplicate email must throw DuplicateEmailException');
    assert_equals(count($db->users), 1, 'duplicate email must not create an extra user row');

    Database::setInstance(null);
});

register_test('AC4-P0: verifyEmail activates account, consumes token, logs event, and sends admin notification', function () {
    $db = new RegistrationServiceMockDatabase([
        [
            'id' => 10,
            'username' => 'coach10',
            'email' => 'coach10@example.com',
            'first_name' => 'Alex',
            'status' => 'unverified',
            'verification_token' => 'valid-token',
            'verification_expiry' => date('Y-m-d H:i:s', time() + 3600),
        ],
    ]);
    $email = new RegistrationServiceMockEmail();
    Database::setInstance($db);
    $service = new RegistrationService($db, $email);

    $userId = $service->verifyEmail('valid-token');

    assert_equals($userId, 10, 'verifyEmail must return verified user id');
    assert_equals($db->users[0]['status'], 'active', 'verifyEmail must activate user status');
    assert_null($db->users[0]['verification_token'], 'verifyEmail must consume token');
    assert_equals($db->activityLogEvents[0]['event'], 'registration.account_verified', 'verifyEmail must log account verified event');
    assert_true(count($email->calls) === 1 && $email->calls[0]['template'] === 'registration_account_verified', 'verifyEmail must send admin operational notification');

    Database::setInstance(null);
});

register_test('AC5-P0: verifyEmail throws ExpiredTokenException for expired token', function () {
    $db = new RegistrationServiceMockDatabase([
        [
            'id' => 11,
            'username' => 'coach11',
            'email' => 'coach11@example.com',
            'first_name' => 'Jamie',
            'status' => 'unverified',
            'verification_token' => 'expired-token',
            'verification_expiry' => date('Y-m-d H:i:s', time() - 60),
        ],
    ]);
    $email = new RegistrationServiceMockEmail();
    Database::setInstance($db);
    $service = new RegistrationService($db, $email);

    $thrown = false;
    try {
        $service->verifyEmail('expired-token');
    } catch (ExpiredTokenException $e) {
        $thrown = true;
    }

    assert_true($thrown, 'expired token must throw ExpiredTokenException');
    assert_equals($db->users[0]['status'], 'unverified', 'expired token must not activate user');

    Database::setInstance(null);
});

register_test('AC4-P1: verifyEmail throws when token was already consumed', function () {
    $db = new RegistrationServiceMockDatabase([
        [
            'id' => 12,
            'username' => 'coach12',
            'email' => 'coach12@example.com',
            'first_name' => 'Robin',
            'status' => 'active',
            'verification_token' => null,
            'verification_expiry' => null,
        ],
    ]);
    $email = new RegistrationServiceMockEmail();
    Database::setInstance($db);
    $service = new RegistrationService($db, $email);

    $thrown = false;
    try {
        $service->verifyEmail('already-used-token');
    } catch (RuntimeException $e) {
        $thrown = true;
    }

    assert_true($thrown, 'consumed/invalid token must throw');
    Database::setInstance(null);
});

register_test('AC6-P0: resendVerification replaces token and sends fresh verification email', function () {
    $db = new RegistrationServiceMockDatabase([
        [
            'id' => 13,
            'username' => 'coach13',
            'email' => 'coach13@example.com',
            'first_name' => 'Taylor',
            'status' => 'unverified',
            'verification_token' => 'old-token',
            'verification_expiry' => date('Y-m-d H:i:s', time() - 60),
        ],
    ]);
    $email = new RegistrationServiceMockEmail();
    Database::setInstance($db);
    $service = new RegistrationService($db, $email);

    $oldToken = $db->users[0]['verification_token'];
    $service->resendVerification('coach13@example.com');

    $newToken = $db->users[0]['verification_token'];
    assert_true($newToken !== $oldToken, 'resendVerification must replace old token');
    assert_true(!empty($db->users[0]['verification_expiry']), 'resendVerification must set a new expiry');
    assert_equals($email->calls[0]['template'], 'registration_verification', 'resendVerification must send verification email');

    Database::setInstance(null);
});

register_test('AC5-P1: requestPasswordReset unknown email does not throw and avoids account enumeration', function () {
    $db = new RegistrationServiceMockDatabase();
    $email = new RegistrationServiceMockEmail();
    Database::setInstance($db);
    $service = new RegistrationService($db, $email);

    $service->requestPasswordReset('missing@example.com');
    assert_true(count($email->calls) === 0, 'unknown email should not trigger reset email');

    Database::setInstance(null);
});

register_test('AC3-P1: completePasswordReset updates password hash and consumes reset token', function () {
    $db = new RegistrationServiceMockDatabase([
        [
            'id' => 21,
            'username' => 'coach21',
            'email' => 'coach21@example.com',
            'first_name' => 'Jamie',
            'status' => 'active',
            'password_hash' => password_hash('OldPass1!', PASSWORD_BCRYPT),
            'password_reset_token' => 'reset-token',
            'password_reset_expiry' => date('Y-m-d H:i:s', time() + 600),
        ],
    ]);
    $email = new RegistrationServiceMockEmail();
    Database::setInstance($db);
    $service = new RegistrationService($db, $email);

    $service->completePasswordReset('reset-token', 'NewPass2!');
    $updated = $db->users[0];
    assert_true(password_verify('NewPass2!', $updated['password_hash']), 'password hash must be updated');
    assert_null($updated['password_reset_token'], 'reset token must be consumed');
    assert_equals($db->activityLogEvents[count($db->activityLogEvents) - 1]['event'], 'auth.password_reset_completed', 'reset completion must be logged');

    Database::setInstance(null);
});

register_test('AC4-P1: completePasswordReset throws ExpiredTokenException for expired token', function () {
    $db = new RegistrationServiceMockDatabase([
        [
            'id' => 22,
            'username' => 'coach22',
            'email' => 'coach22@example.com',
            'first_name' => 'Sky',
            'status' => 'active',
            'password_hash' => password_hash('OldPass1!', PASSWORD_BCRYPT),
            'password_reset_token' => 'expired-reset',
            'password_reset_expiry' => date('Y-m-d H:i:s', time() - 60),
        ],
    ]);
    $email = new RegistrationServiceMockEmail();
    Database::setInstance($db);
    $service = new RegistrationService($db, $email);

    $thrown = false;
    try {
        $service->completePasswordReset('expired-reset', 'NewPass2!');
    } catch (ExpiredTokenException $e) {
        $thrown = true;
    }
    assert_true($thrown, 'expired reset token must throw ExpiredTokenException');

    Database::setInstance(null);
});

register_test('AC4-P2: completePasswordReset throws WeakPasswordException for weak password', function () {
    $db = new RegistrationServiceMockDatabase([
        [
            'id' => 23,
            'username' => 'coach23',
            'email' => 'coach23@example.com',
            'first_name' => 'Riley',
            'status' => 'active',
            'password_hash' => password_hash('OldPass1!', PASSWORD_BCRYPT),
            'password_reset_token' => 'valid-reset',
            'password_reset_expiry' => date('Y-m-d H:i:s', time() + 600),
        ],
    ]);
    $email = new RegistrationServiceMockEmail();
    Database::setInstance($db);
    $service = new RegistrationService($db, $email);

    $thrown = false;
    try {
        $service->completePasswordReset('valid-reset', 'weak');
    } catch (WeakPasswordException $e) {
        $thrown = true;
    }
    assert_true($thrown, 'weak password must throw WeakPasswordException');

    Database::setInstance(null);
});
