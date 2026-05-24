<?php
/**
 * District 8 Travel League — ImpersonationService
 *
 * Provides admin-initiated session impersonation of coach/user accounts.
 * Story 13.1
 */

if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

class ImpersonationService {
    /**
     * Build an environment-aware absolute path.
     */
    private static function buildPath(string $relativePath): string {
        $normalized = ltrim($relativePath, '/');

        if (class_exists('EnvLoader') && method_exists('EnvLoader', 'isProduction') && EnvLoader::isProduction()) {
            return '/' . $normalized;
        }

        return '/public/' . $normalized;
    }

    /**
     * Start impersonating a user account.
     *
     * Saves the admin session under impersonator_* keys, regenerates the session
     * ID, and writes a full coach-style session for the target user. The session
     * role is read from the DB (not hardcoded) so team_owner permission checks pass.
     *
     * @throws InvalidArgumentException if target user is not active or doesn't exist
     * @throws RuntimeException         if already impersonating
     */
    public static function startImpersonation(int $targetUserId, string $adminIp): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['impersonating'])) {
            Logger::warn('Impersonation start blocked: already impersonating', [
                'admin_id' => $_SESSION['impersonator_admin_id'] ?? 0,
                'ip' => $adminIp,
            ]);
            throw new RuntimeException('Already impersonating a user. Stop the current session first.');
        }

        $db = Database::getInstance();
        $target = $db->fetchOne(
            'SELECT u.id, u.first_name, u.last_name, u.username, u.status,
                    u.password_changed_at, r.name AS role_name
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id
             LIMIT 1',
            ['id' => $targetUserId]
        );

        if ($target === false || ($target['status'] ?? '') !== 'active') {
            throw new InvalidArgumentException('User not found or account is not active.');
        }
        $targetRole = strtolower(trim((string) ($target['role_name'] ?? '')));
        if ($targetRole === 'administrator' || $targetRole === 'admin') {
            throw new InvalidArgumentException('Administrator accounts cannot be impersonated.');
        }

        // Save admin session keys
        $savedAdminId       = $_SESSION['admin_id']       ?? null;
        $savedAdminUsername = $_SESSION['admin_username']  ?? null;
        $savedRole          = $_SESSION['role']            ?? null;
        $savedUserType      = $_SESSION['user_type']       ?? null;
        $savedExpires       = $_SESSION['expires']         ?? null;
        $savedLoginTime     = $_SESSION['login_time']      ?? null;

        session_regenerate_id(true);

        // Remove admin-only keys from the active impersonated session.
        unset(
            $_SESSION['admin_id'],
            $_SESSION['admin_username'],
            $_SESSION['expires']
        );

        // Write coach-style session matching AuthService::setCoachSession keys
        $roleName = $target['role_name'] ?? 'user';
        $_SESSION['user_type']                = 'coach';
        $_SESSION['coach_user_id']            = (int) $target['id'];
        $_SESSION['coach_identifier']         = (string) ($target['username'] ?? $target['email'] ?? 'coach');
        $_SESSION['role']                     = $roleName;
        $_SESSION['login_time']               = time();
        $_SESSION['last_activity']            = time();
        $_SESSION['coach_password_changed_at'] = (string) ($target['password_changed_at'] ?? '');

        // Impersonation state keys
        $displayName = trim($target['first_name'] . ' ' . $target['last_name']) . ' (' . $target['username'] . ')';
        $_SESSION['impersonating']             = true;
        $_SESSION['impersonated_user_id']      = $targetUserId;
        $_SESSION['impersonated_user_name']    = $displayName;
        $_SESSION['impersonated_full_name']    = trim((string) ($target['first_name'] ?? '') . ' ' . (string) ($target['last_name'] ?? ''));
        $_SESSION['impersonated_username']     = (string) ($target['username'] ?? '');
        $_SESSION['impersonation_return_url']  = self::buildPath('admin/users/detail.php?id=' . $targetUserId);

        // Save admin keys under impersonator_ prefix
        $_SESSION['impersonator_admin_id']       = $savedAdminId;
        $_SESSION['impersonator_admin_username']  = $savedAdminUsername;
        $_SESSION['impersonator_role']            = $savedRole;
        $_SESSION['impersonator_user_type']       = $savedUserType;
        $_SESSION['impersonator_expires']         = $savedExpires;
        $_SESSION['impersonator_login_time']      = $savedLoginTime;

        ActivityLogger::log('admin.impersonation_start', [
            'admin_id'       => $savedAdminId,
            'admin_username' => $savedAdminUsername,
            'target_user_id' => $targetUserId,
            'target_username'=> $target['username'],
            'ip'             => $adminIp,
        ]);
    }

    /**
     * Stop the current impersonation session.
     *
     * Restores the admin's original session and clears all impersonation keys.
     *
     * @return string The return URL to redirect to after stopping
     */
    public static function stopImpersonation(string $adminIp): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $returnUrl      = (string) ($_SESSION['impersonation_return_url'] ?? self::buildPath('admin/users/index.php'));
        $adminId        = $_SESSION['impersonator_admin_id']      ?? null;
        $adminUsername  = $_SESSION['impersonator_admin_username'] ?? null;
        $targetUserId   = $_SESSION['impersonated_user_id']        ?? null;

        session_regenerate_id(true);

        // Restore admin session
        $_SESSION['user_type']       = $_SESSION['impersonator_user_type']  ?? 'admin';
        $_SESSION['admin_id']        = $_SESSION['impersonator_admin_id']   ?? null;
        $_SESSION['admin_username']  = $_SESSION['impersonator_admin_username'] ?? null;
        $_SESSION['role']            = $_SESSION['impersonator_role']       ?? 'administrator';
        $_SESSION['expires']         = $_SESSION['impersonator_expires']    ?? (time() + 3600);
        $_SESSION['login_time']      = $_SESSION['impersonator_login_time'] ?? time();

        // Clear coach-session keys
        unset(
            $_SESSION['coach_user_id'],
            $_SESSION['coach_identifier'],
            $_SESSION['coach_password_changed_at'],
            $_SESSION['last_activity']
        );

        // Clear impersonation state keys
        unset(
            $_SESSION['impersonating'],
            $_SESSION['impersonated_user_id'],
            $_SESSION['impersonated_user_name'],
            $_SESSION['impersonated_full_name'],
            $_SESSION['impersonated_username'],
            $_SESSION['impersonation_return_url'],
            $_SESSION['impersonator_admin_id'],
            $_SESSION['impersonator_admin_username'],
            $_SESSION['impersonator_role'],
            $_SESSION['impersonator_user_type'],
            $_SESSION['impersonator_expires'],
            $_SESSION['impersonator_login_time']
        );

        ActivityLogger::log('admin.impersonation_stop', [
            'admin_id'       => $adminId,
            'admin_username' => $adminUsername,
            'target_user_id' => $targetUserId,
            'ip'             => $adminIp,
        ]);

        return $returnUrl;
    }
}
