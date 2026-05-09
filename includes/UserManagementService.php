<?php
/**
 * District 8 Travel League — User Management Service
 *
 * Story 4.3 initial version: assignTeam() + removeTeam() only.
 * Story 8.1 will add getList(), update(), setRole(), disable(), enable(),
 * delete(), and resetPassword() — do NOT add those here.
 */

if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

// TeamAlreadyClaimedException is defined in TeamRegistrationService — load it
// before we need it so callers can catch the exception without requiring TRS.
if (!class_exists('TeamRegistrationService')) {
    require_once __DIR__ . '/TeamRegistrationService.php';
}
if (!class_exists('ActivityLogger')) {
    require_once __DIR__ . '/ActivityLogger.php';
}
if (!class_exists('Logger')) {
    require_once __DIR__ . '/Logger.php';
}

class UserManagementService {

    private Database $db;
    private object $emailService;

    public function __construct(?Database $db = null, ?object $emailService = null) {
        $this->db = $db ?? Database::getInstance();
        if ($emailService !== null) {
            $this->emailService = $emailService;
            return;
        }
        if (!class_exists('EmailService')) {
            require_once __DIR__ . '/EmailService.php';
        }
        $this->emailService = new EmailService();
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Assign a team to a user and elevate their role to team_owner if needed.
     *
     * App-layer 1:1 enforcement — team_owners has no UNIQUE(user_id) DB constraint,
     * so we query before inserting.
     *
     * @throws TeamAlreadyClaimedException if the user already owns a team
     */
    public function assignTeam(int $userId, int $teamId, int $adminUserId): void {
        // Guard: enforce 1:1 user-to-team at app layer
        $existing = $this->db->fetchOne(
            'SELECT team_id FROM team_owners WHERE user_id = :user_id LIMIT 1',
            ['user_id' => $userId]
        );
        if ($existing !== false) {
            throw new TeamAlreadyClaimedException(
                'This coach already has a team assigned. Multiple team assignments are not supported in this version.'
            );
        }

        // Insert team_owners row (assigned_by is NOT NULL)
        $this->db->query(
            'INSERT INTO team_owners (user_id, team_id, assigned_by, created_at)
             VALUES (:user_id, :team_id, :assigned_by, NOW())',
            ['user_id' => $userId, 'team_id' => $teamId, 'assigned_by' => $adminUserId]
        );

        // Elevate role to team_owner whenever not already team_owner (Story 4.3 review: option 1)
        $user = $this->db->fetchOne(
            'SELECT id, first_name, email, role_id FROM users WHERE id = :id LIMIT 1',
            ['id' => $userId]
        );
        if ($user !== false) {
            $currentRoleName = $this->resolveRoleName((int) ($user['role_id'] ?? 0));
            if ($currentRoleName !== 'team_owner') {
                $this->setUserRole($userId, 'team_owner');
            }

            // Operational email — failure logged, never surfaced to caller (AR-12)
            try {
                $this->emailService->triggerNotificationToAddress(
                    'team_assignment_notification',
                    (string) $user['email'],
                    [
                        'user_id'    => $userId,
                        'team_id'    => $teamId,
                        'first_name' => $user['first_name'],
                    ]
                );
            } catch (Throwable $e) {
                error_log('[UserManagementService] assignTeam email failed: ' . $e->getMessage());
            }
        }

        ActivityLogger::log('team.owner_assigned', [
            'user_id'       => $userId,
            'team_id'       => $teamId,
            'admin_user_id' => $adminUserId,
        ]);
    }

    /**
     * Remove a team assignment.  If the user has no remaining teams after
     * removal, their role is reverted to 'user'.
     */
    public function removeTeam(int $userId, int $teamId, int $adminUserId): void {
        $stmt = $this->db->query(
            'DELETE FROM team_owners WHERE user_id = :user_id AND team_id = :team_id',
            ['user_id' => $userId, 'team_id' => $teamId]
        );
        if ($stmt->rowCount() === 0) {
            return;
        }

        // Revert role to 'user' when no remaining team assignments exist
        $remaining = $this->db->fetchOne(
            'SELECT COUNT(*) AS cnt FROM team_owners WHERE user_id = :user_id',
            ['user_id' => $userId]
        );
        if ($remaining !== false && (int) $remaining['cnt'] === 0) {
            $this->setUserRole($userId, 'user');
        }

        // Operational email — failure logged, never surfaced to caller (AR-12)
        $user = $this->db->fetchOne(
            'SELECT first_name, email FROM users WHERE id = :id LIMIT 1',
            ['id' => $userId]
        );
        if ($user !== false) {
            try {
                $this->emailService->triggerNotificationToAddress(
                    'team_removal_notification',
                    (string) $user['email'],
                    [
                        'user_id'    => $userId,
                        'team_id'    => $teamId,
                        'first_name' => $user['first_name'],
                    ]
                );
            } catch (Throwable $e) {
                error_log('[UserManagementService] removeTeam email failed: ' . $e->getMessage());
            }
        }

        ActivityLogger::log('team.owner_removed', [
            'user_id'       => $userId,
            'team_id'       => $teamId,
            'admin_user_id' => $adminUserId,
        ]);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Resolve the name of a role from its ID.
     * Returns empty string when the ID is 0 or not found.
     */
    private function resolveRoleName(int $roleId): string {
        if ($roleId === 0) {
            return '';
        }
        $row = $this->db->fetchOne(
            'SELECT name FROM roles WHERE id = :id LIMIT 1',
            ['id' => $roleId]
        );
        return $row !== false ? (string) $row['name'] : '';
    }

    /**
     * Set a user's role, handling both role_id (FK) and legacy role (varchar)
     * column layouts gracefully.
     */
    private function setUserRole(int $userId, string $roleName): void {
        if ($this->hasUsersColumn('role_id')) {
            $roleId = $this->getRoleId($roleName);
            if ($roleId !== null) {
                $this->db->query(
                    'UPDATE users SET role_id = :role_id, updated_at = NOW() WHERE id = :id',
                    ['role_id' => $roleId, 'id' => $userId]
                );
            }
        } elseif ($this->hasUsersColumn('role')) {
            $this->db->query(
                'UPDATE users SET role = :role, updated_at = NOW() WHERE id = :id',
                ['role' => $roleName, 'id' => $userId]
            );
        }
    }

    /** Look up a role's integer ID by name.  Returns null when not found. */
    private function getRoleId(string $roleName): ?int {
        $row = $this->db->fetchOne(
            'SELECT id FROM roles WHERE name = :name LIMIT 1',
            ['name' => $roleName]
        );
        return $row !== false ? (int) $row['id'] : null;
    }

    /**
     * Runtime check for a column on the users table.
     * Prevents hard failures when running against schemas that differ between
     * environments.
     */
    private function hasUsersColumn(string $column): bool {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            return false;
        }
        return $this->db->fetchOne(
            'SELECT 1 AS ok FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = ?
               AND COLUMN_NAME  = ?
             LIMIT 1',
            ['users', $column]
        ) !== false;
    }
}
