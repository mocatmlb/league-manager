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
     * Maps each role to its post-login home URL [production, development].
     * Add one entry here when a new role is introduced — no other file needs changing.
     */
    private static array $ROLE_HOME = [
        'administrator'   => ['/admin/index.php',         '/public/admin/index.php'],
        'umpire_assignor' => ['/admin/umpires/index.php', '/public/admin/umpires/index.php'],
        'umpire'          => ['/umpires/index.php',       '/public/umpires/index.php'],
        'coach'           => ['/coaches/dashboard.php',   '/public/coaches/dashboard.php'],
        'team_owner'      => ['/coaches/dashboard.php',   '/public/coaches/dashboard.php'],
        'team_official'   => ['/coaches/dashboard.php',   '/public/coaches/dashboard.php'],
        'user'            => ['/coaches/dashboard.php',   '/public/coaches/dashboard.php'],
    ];

    /**
     * Return the post-login home URL for a given role.
     * Unknown roles fall back to the 'user' entry.
     */
    public static function getHomeUrl(string $role): string {
        $pair = self::$ROLE_HOME[$role] ?? self::$ROLE_HOME['user'];
        return EnvLoader::isProduction() ? $pair[0] : $pair[1];
    }

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
                $forceChangeUrl = EnvLoader::isProduction() ? '/coaches/force-change-password.php' : '/public/coaches/force-change-password.php';
                header('Location: ' . $forceChangeUrl);
                exit;
            }
        }
    }
}
