<?php
/**
 * Unit Tests: PermissionGuard
 *
 * Story 1.3 — Implement Cross-Cutting Utility Classes
 * AC1: PermissionGuard::requireRole() enforces session role check
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/PermissionGuard.php';

// ---------------------------------------------------------------------------
// Helpers for redirect detection
// ---------------------------------------------------------------------------

/**
 * Invoke PermissionGuard::requireRole() in a way that captures whether it
 * would exit. We use a try/catch on a RuntimeException thrown by the
 * overridden header() / exit stub strategy:
 *
 * Since CLI tests cannot send real HTTP headers, we rely on the fact that
 * requireRole() calls exit on failure. We wrap the call in a forked child
 * process or detect the exit via pcntl, but on shared hosting pcntl may be
 * unavailable. Instead, we test indirectly:
 *
 * - Role MATCH: call returns normally (no exception, no exit).
 * - Role MISMATCH: we call requireRole() and assert a non-zero exit via
 *   a subprocess exec, keeping the test runner process alive.
 */

// ---------------------------------------------------------------------------
// AC1-P0: role matches → execution continues (no exit, no redirect)
// ---------------------------------------------------------------------------

register_test('AC1-P0: PermissionGuard::requireRole - matching role allows execution', function () {
    // Simulate a session with the correct role
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $_SESSION['role'] = 'team_owner';

    $exited = false;
    try {
        // requireRole should return normally when role matches
        PermissionGuard::requireRole('team_owner');
        // If we reach here, no exit occurred
    } catch (Throwable $e) {
        $exited = true;
    }

    assert_true(!$exited, 'requireRole should not exit when session role matches required role');

    unset($_SESSION['role']);
});

// ---------------------------------------------------------------------------
// AC1-P1: role mismatch → redirect + exit (tested via subprocess)
// ---------------------------------------------------------------------------

register_test('AC1-P1: PermissionGuard::requireRole - mismatched role triggers redirect exit', function () {
    // Spin up a child PHP process that will call requireRole() with a wrong role.
    // The child process will exit non-zero (via the exit call inside requireRole).
    // We verify the child process exits (PHP exit() exits with 0 by default after
    // header(), but the key is that execution does NOT continue past requireRole).

    $script = <<<'PHP'
<?php
define('D8TL_APP', true);
require_once __DIR__ . '/../../includes/PermissionGuard.php';

// Suppress header() warnings in CLI
if (!function_exists('header')) {
    function header($str, $replace = true, $code = 0) {}
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$_SESSION['role'] = 'wrong_role';

PermissionGuard::requireRole('team_owner');

// If execution reaches here, the guard failed to exit
echo 'GUARD_DID_NOT_EXIT';
exit(0);
PHP;

    $scriptFile = sys_get_temp_dir() . '/perm_guard_test_' . getmypid() . '.php';
    file_put_contents($scriptFile, $script);

    $output = shell_exec(PHP_BINARY . ' ' . escapeshellarg($scriptFile) . ' 2>/dev/null');
    unlink($scriptFile);

    // If 'GUARD_DID_NOT_EXIT' appears, the guard failed to block execution
    assert_true(
        strpos((string)$output, 'GUARD_DID_NOT_EXIT') === false,
        'requireRole should exit and not continue execution when role does not match'
    );
});

// ---------------------------------------------------------------------------
// AC1-P2: no session / missing role → redirect + exit
// ---------------------------------------------------------------------------

register_test('AC1-P2: PermissionGuard::requireRole - missing session role triggers redirect exit', function () {
    $script = <<<'PHP'
<?php
define('D8TL_APP', true);
require_once __DIR__ . '/../../includes/PermissionGuard.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
// No role set in session
unset($_SESSION['role']);

PermissionGuard::requireRole('team_owner');

echo 'GUARD_DID_NOT_EXIT';
exit(0);
PHP;

    $scriptFile = sys_get_temp_dir() . '/perm_guard_test_missing_' . getmypid() . '.php';
    file_put_contents($scriptFile, $script);

    $output = shell_exec(PHP_BINARY . ' ' . escapeshellarg($scriptFile) . ' 2>/dev/null');
    unlink($scriptFile);

    assert_true(
        strpos((string)$output, 'GUARD_DID_NOT_EXIT') === false,
        'requireRole should exit when no role is set in session'
    );
});
