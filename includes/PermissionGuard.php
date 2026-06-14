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
        'user'            => ['coach', 'team_owner', 'team_official', 'administrator'],
        'team_owner'      => ['team_owner', 'administrator'],
        'admin'           => ['administrator'],
        'umpire_assignor' => ['umpire_assignor', 'administrator'],
        'umpire'          => ['umpire'],
    ];

    /**
     * Require the current session user to have the specified role(s).
     *
     * Redirects to the login page and halts execution if the role check fails.
     * Call as the first executable line after bootstrap on every protected page.
     *
     * @param string|array $role  Required role(s) (e.g. 'team_owner', 'user', 'admin' or ['admin', 'umpire_assignor'])
     *                            If an array is passed, user must satisfy ANY of the roles (OR logic)
     * @param string $loginUrl    Redirect target on failure (defaults to coaches login)
     */
    public static function requireRole(string|array $role, string $loginUrl = '/public/login.php'): void {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        $sessionRole = $_SESSION['role'] ?? null;
        $roles = is_array($role) ? $role : [$role];
        $allowed = [];
        foreach ($roles as $r) {
            $allowed = array_merge($allowed, self::$ROLE_SATISFIES[$r] ?? [$r]);
        }

        if (!in_array($sessionRole, $allowed, true)) {
            header('Location: ' . $loginUrl);
            exit;
        }

        if (!empty($_SESSION['force_password_change'])) {
            $currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
            if ($currentScript !== 'force-change-password.php') {
                $basePath = dirname($loginUrl);
                header('Location: ' . $basePath . '/force-change-password.php');
                exit;
            }
        }
    }
}
