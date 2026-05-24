<?php
/**
 * Unit Tests: ImpersonationService
 *
 * Story 13.1 — Admin User Impersonation
 * AC: 3, 4, 7, 9, 10
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ActivityLogger.php';
require_once __DIR__ . '/../../includes/Logger.php';
require_once __DIR__ . '/../../includes/ImpersonationService.php';

// ---------------------------------------------------------------------------
// Mock infrastructure
// ---------------------------------------------------------------------------

class ImpersonationMockDatabase extends Database {
    public array $users   = [];
    public array $queries = [];

    public function __construct() {
        // Bypass real DB connection
    }

    public function fetchOne($sql, $params = []) {
        $this->queries[] = ['sql' => $sql, 'params' => $params];

        // User lookup: SELECT from users
        if (stripos($sql, 'FROM users') !== false) {
            $id = $params['id'] ?? null;
            foreach ($this->users as $u) {
                if ($u['id'] === $id) {
                    return $u;
                }
            }
            return false;
        }

        return false;
    }

    public function query($sql, $params = []) {
        $this->queries[] = ['sql' => $sql, 'params' => $params];
        return new ImpersonationMockStatement();
    }

    public function fetchAll($sql, $params = []) {
        return [];
    }
}

class ImpersonationMockStatement {
    public function rowCount(): int { return 1; }
    public function fetch($mode = null) { return false; }
    public function fetchAll($mode = null): array { return []; }
}

// ---------------------------------------------------------------------------
// Helper: set a fake admin session
// ---------------------------------------------------------------------------
function _setAdminSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    $_SESSION['user_type']       = 'admin';
    $_SESSION['admin_id']        = 7;
    $_SESSION['admin_username']  = 'admin_user';
    $_SESSION['role']            = 'administrator';
    $_SESSION['expires']         = time() + 3600;
    $_SESSION['login_time']      = time() - 60;
}

// ---------------------------------------------------------------------------
// Test: start sets correct coach session keys (AC 3)
// ---------------------------------------------------------------------------

register_test('AC3: startImpersonation sets correct coach session keys', function () {
    _setAdminSession();

    $mock = new ImpersonationMockDatabase();
    $mock->users[] = [
        'id'                  => 42,
        'first_name'          => 'Jane',
        'last_name'           => 'Doe',
        'username'            => 'jdoe',
        'status'              => 'active',
        'password_changed_at' => '2025-01-01 00:00:00',
        'role_name'           => 'team_owner',
    ];
    Database::setInstance($mock);

    ImpersonationService::startImpersonation(42, '127.0.0.1');

    assert_equals($_SESSION['user_type'],        'coach',       'user_type should be coach');
    assert_equals($_SESSION['coach_user_id'],    42,            'coach_user_id should be 42');
    assert_equals($_SESSION['coach_identifier'], 'jdoe',        'coach_identifier should be username');
    assert_true(!empty($_SESSION['login_time']),                'login_time set');
    assert_true(!empty($_SESSION['last_activity']),             'last_activity set');
    assert_true(!empty($_SESSION['impersonating']),             'impersonating flag set');
    assert_equals($_SESSION['impersonated_user_id'], 42,        'impersonated_user_id correct');
    assert_true(strpos($_SESSION['impersonated_user_name'], 'Jane Doe') !== false, 'display name includes full name');
    assert_true(empty($_SESSION['admin_id']),                   'admin_id removed from active impersonated session');
    assert_true(empty($_SESSION['admin_username']),             'admin_username removed from active impersonated session');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// Test: start reads role correctly from DB (AC 4)
// ---------------------------------------------------------------------------

register_test('AC4: startImpersonation sets session role from DB role_name, not hardcoded coach', function () {
    _setAdminSession();

    $mock = new ImpersonationMockDatabase();
    $mock->users[] = [
        'id'                  => 55,
        'first_name'          => 'Bob',
        'last_name'           => 'Smith',
        'username'            => 'bsmith',
        'status'              => 'active',
        'password_changed_at' => null,
        'role_name'           => 'team_owner',
    ];
    Database::setInstance($mock);

    ImpersonationService::startImpersonation(55, '127.0.0.1');

    assert_equals($_SESSION['role'], 'team_owner', 'role must come from DB, not hardcoded coach');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// Test: admin session keys saved under impersonator_ prefix (AC 3)
// ---------------------------------------------------------------------------

register_test('AC3: startImpersonation saves admin keys under impersonator_ prefix', function () {
    _setAdminSession();
    $savedAdminId   = $_SESSION['admin_id'];
    $savedUsername  = $_SESSION['admin_username'];
    $savedExpires   = $_SESSION['expires'];

    $mock = new ImpersonationMockDatabase();
    $mock->users[] = [
        'id'                  => 10,
        'first_name'          => 'Test',
        'last_name'           => 'User',
        'username'            => 'tuser',
        'status'              => 'active',
        'password_changed_at' => null,
        'role_name'           => 'coach',
    ];
    Database::setInstance($mock);

    ImpersonationService::startImpersonation(10, '127.0.0.1');

    assert_equals($_SESSION['impersonator_admin_id'],      $savedAdminId,  'impersonator_admin_id preserved');
    assert_equals($_SESSION['impersonator_admin_username'], $savedUsername, 'impersonator_admin_username preserved');
    assert_equals($_SESSION['impersonator_expires'],        $savedExpires,  'impersonator_expires preserved');
    assert_equals($_SESSION['impersonator_user_type'],      'admin',        'impersonator_user_type preserved');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// Test: stop restores admin keys (AC 7)
// ---------------------------------------------------------------------------

register_test('AC7: stopImpersonation restores admin session keys', function () {
    _setAdminSession();

    $mock = new ImpersonationMockDatabase();
    $mock->users[] = [
        'id'                  => 20,
        'first_name'          => 'Alice',
        'last_name'           => 'Brown',
        'username'            => 'abrown',
        'status'              => 'active',
        'password_changed_at' => null,
        'role_name'           => 'user',
    ];
    Database::setInstance($mock);

    ImpersonationService::startImpersonation(20, '127.0.0.1');

    // Now stop
    ImpersonationService::stopImpersonation('127.0.0.1');

    assert_equals($_SESSION['user_type'],      'admin',         'user_type restored to admin');
    assert_equals($_SESSION['admin_id'],       7,               'admin_id restored');
    assert_equals($_SESSION['admin_username'], 'admin_user',    'admin_username restored');
    assert_equals($_SESSION['role'],           'administrator', 'role restored');
    assert_true(!empty($_SESSION['expires']),                   'expires restored');

    // Impersonation keys cleared
    assert_true(empty($_SESSION['impersonating']),              'impersonating flag cleared');
    assert_true(empty($_SESSION['impersonated_user_id']),       'impersonated_user_id cleared');
    assert_true(empty($_SESSION['impersonated_user_name']),     'impersonated_user_name cleared');
    assert_true(empty($_SESSION['impersonator_admin_id']),      'impersonator_admin_id cleared');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// Test: stop returns correct return URL (AC 7)
// ---------------------------------------------------------------------------

register_test('AC7: stopImpersonation returns the stored return URL', function () {
    _setAdminSession();

    $mock = new ImpersonationMockDatabase();
    $mock->users[] = [
        'id'                  => 33,
        'first_name'          => 'Chris',
        'last_name'           => 'Jones',
        'username'            => 'cjones',
        'status'              => 'active',
        'password_changed_at' => null,
        'role_name'           => 'coach',
    ];
    Database::setInstance($mock);

    ImpersonationService::startImpersonation(33, '127.0.0.1');

    $returnUrl = ImpersonationService::stopImpersonation('127.0.0.1');

    assert_true(strpos($returnUrl, '33') !== false, 'return URL contains target user ID 33');
    assert_true(strpos($returnUrl, 'detail.php') !== false, 'return URL points at detail.php');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// Test: nested impersonation rejected (AC 9)
// ---------------------------------------------------------------------------

register_test('AC9: startImpersonation throws if already impersonating', function () {
    _setAdminSession();

    $mock = new ImpersonationMockDatabase();
    $mock->users[] = [
        'id'                  => 50,
        'first_name'          => 'Dan',
        'last_name'           => 'Lee',
        'username'            => 'dlee',
        'status'              => 'active',
        'password_changed_at' => null,
        'role_name'           => 'coach',
    ];
    Database::setInstance($mock);

    ImpersonationService::startImpersonation(50, '127.0.0.1');

    // Attempt nested impersonation
    $threw = false;
    try {
        ImpersonationService::startImpersonation(50, '127.0.0.1');
    } catch (RuntimeException $e) {
        $threw = true;
    }

    assert_true($threw, 'nested impersonation must throw RuntimeException');

    // Restore so DB can be cleared
    ImpersonationService::stopImpersonation('127.0.0.1');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// Test: inactive user cannot be impersonated (AC 10)
// ---------------------------------------------------------------------------

register_test('AC10: startImpersonation throws for non-active user', function () {
    _setAdminSession();

    $mock = new ImpersonationMockDatabase();
    $mock->users[] = [
        'id'                  => 99,
        'first_name'          => 'Eve',
        'last_name'           => 'Inactive',
        'username'            => 'einactive',
        'status'              => 'disabled',
        'password_changed_at' => null,
        'role_name'           => 'coach',
    ];
    Database::setInstance($mock);

    $threw = false;
    try {
        ImpersonationService::startImpersonation(99, '127.0.0.1');
    } catch (InvalidArgumentException $e) {
        $threw = true;
    }

    assert_true($threw, 'inactive user must throw InvalidArgumentException');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// Test: non-existent user cannot be impersonated (AC 10)
// ---------------------------------------------------------------------------

register_test('AC10: startImpersonation throws for non-existent user', function () {
    _setAdminSession();

    $mock = new ImpersonationMockDatabase();
    // No users added — DB returns false
    Database::setInstance($mock);

    $threw = false;
    try {
        ImpersonationService::startImpersonation(9999, '127.0.0.1');
    } catch (InvalidArgumentException $e) {
        $threw = true;
    }

    assert_true($threw, 'non-existent user must throw InvalidArgumentException');

    Database::setInstance(null);
});

// ---------------------------------------------------------------------------
// Test: administrator targets cannot be impersonated (AC 1)
// ---------------------------------------------------------------------------

register_test('AC1: startImpersonation throws for administrator role target', function () {
    _setAdminSession();

    $mock = new ImpersonationMockDatabase();
    $mock->users[] = [
        'id'                  => 88,
        'first_name'          => 'Admin',
        'last_name'           => 'User',
        'username'            => 'auser',
        'status'              => 'active',
        'password_changed_at' => null,
        'role_name'           => 'administrator',
    ];
    Database::setInstance($mock);

    $threw = false;
    try {
        ImpersonationService::startImpersonation(88, '127.0.0.1');
    } catch (InvalidArgumentException $e) {
        $threw = true;
    }

    assert_true($threw, 'administrator role target must throw InvalidArgumentException');

    Database::setInstance(null);
});
