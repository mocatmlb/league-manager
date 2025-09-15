<?php
require_once __DIR__ . '/test-helpers.php';

// Define app constant to satisfy includes
if (!defined('D8TL_APP')) define('D8TL_APP', true);

// Load minimal required files
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/enums.php';
require_once __DIR__ . '/../../includes/Logger.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/TestDatabase.php';

// Provide a minimal Logger if not present
if (!class_exists('Logger')) {
    class Logger {
        public static function info($msg, $ctx = []) {}
        public static function warn($msg, $ctx = []) {}
    }
}

function withTestDb($callback) {
    $testDb = new TestDatabase();
    Database::setInstance($testDb);
    return $callback($testDb);
}

// Tests

test('CSRF token generation and verification', function() {
    resetSession();
    Auth::startSession();
    $token = Auth::generateCSRFToken();
    assertNotEmpty($token);
    assertTrue(Auth::verifyCSRFToken($token));
    assertFalse(Auth::verifyCSRFToken($token . 'x'));
});


test('isAdmin returns false when not logged in', function() {
    resetSession();
    assertFalse(Auth::isAdmin());
});


test('authenticateAdmin succeeds with username', function() {
    resetSession();
    withTestDb(function(TestDatabase $db) {
        $password = 'Secret123!';
        $db->admins[] = [
            'id' => 1,
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => password_hash($password, PASSWORD_BCRYPT)
        ];
        $ok = Auth::authenticateAdmin('admin', $password);
        assertTrue($ok, 'Expected authentication to succeed with username');
        assertTrue(Auth::isAdmin(), 'Expected to be admin after login');
        $user = Auth::getCurrentUser();
        assertEquals('admin', $user['username']);
    });
});


test('authenticateAdmin succeeds with email', function() {
    resetSession();
    withTestDb(function(TestDatabase $db) {
        $password = 'Secret123!';
        $db->admins[] = [
            'id' => 2,
            'username' => 'root',
            'email' => 'root@example.com',
            'password' => password_hash($password, PASSWORD_BCRYPT)
        ];
        $ok = Auth::authenticateAdmin('root@example.com', $password);
        assertTrue($ok, 'Expected authentication to succeed with email');
        assertTrue(Auth::isAdmin(), 'Expected to be admin after login');
        $user = Auth::getCurrentUser();
        assertEquals('root', $user['username']);
    });
});


test('authenticateAdmin fails with wrong password', function() {
    resetSession();
    withTestDb(function(TestDatabase $db) {
        $password = 'Secret123!';
        $db->admins[] = [
            'id' => 3,
            'username' => 'alice',
            'email' => 'alice@example.com',
            'password' => password_hash($password, PASSWORD_BCRYPT)
        ];
        $ok = Auth::authenticateAdmin('alice', 'WrongPass');
        assertFalse($ok, 'Expected authentication to fail with wrong password');
        assertFalse(Auth::isAdmin(), 'Should not be admin after failed login');
    });
});
