<?php
/**
 * District 8 Travel League - Permission Guard
 *
 * Enforces role-based access control at the top of every protected page.
 * Usage: PermissionGuard::requireRole('team_owner');
 */

if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

class PermissionGuard {

    private static array $ROLE_SATISFIES = [
        'user'       => ['coach', 'team_owner', 'team_official', 'administrator'],
        'team_owner' => ['team_owner', 'administrator'],
        'admin'      => ['administrator'],
    ];

    /**
     * Require the current session user to have the specified role.
     *
     * Redirects to the login page and halts execution if the role check fails.
     * Call as the first executable line after bootstrap on every protected page.
     *
     * @param string $role  Required role (e.g. 'team_owner', 'user', 'admin')
     * @param string $loginUrl  Redirect target on failure (defaults to coaches login)
     */
    public static function requireRole(string $role, string $loginUrl = '/public/coaches/login.php'): void {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        $sessionRole = $_SESSION['role'] ?? null;
        $allowed = self::$ROLE_SATISFIES[$role] ?? [$role];

        if (!in_array($sessionRole, $allowed, true)) {
            header('Location: ' . $loginUrl);
            exit;
        }
    }
}
