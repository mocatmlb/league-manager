<?php
/**
 * District 8 Travel League — User Management Service
 *
 * Story 4.3: assignTeam() + removeTeam()
 * Story 8.1: getList(), update(), setRole(), disable(), enable(), delete(), resetPassword()
 */

if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

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
    // Public API — Story 8.1: Full CRUD
    // -----------------------------------------------------------------------

    public function getList(array $filters, int $page = 1, int $perPage = 25): array {
        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $where[] = "(u.first_name LIKE :search1 OR u.last_name LIKE :search2 OR u.username LIKE :search3 OR u.email LIKE :search4)";
            $params['search1'] = $term;
            $params['search2'] = $term;
            $params['search3'] = $term;
            $params['search4'] = $term;
        }
        if (!empty($filters['role'])) {
            $where[] = "r.name = :role_filter";
            $params['role_filter'] = $filters['role'];
        }
        if (!empty($filters['status'])) {
            $where[] = "u.status = :status_filter";
            $params['status_filter'] = $filters['status'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $perPage = max(1, min($perPage, 100));
        // Patch 3: clamp page to prevent integer overflow before multiplication
        $page    = min(max(1, $page), 1_000_000);
        $offset  = (int) (((float) $page - 1) * $perPage);

        $countSql = "SELECT COUNT(*) AS cnt FROM users u LEFT JOIN roles r ON u.role_id = r.id {$whereClause}";
        $countRow = $this->db->fetchOne($countSql, $params);
        $totalCount = (int) ($countRow['cnt'] ?? 0);

        $limitInt = (int) $perPage;
        $offsetInt = (int) $offset;
        $dataSql = "SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.preferred_name,
                           u.status, u.created_at, r.name AS role_name
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.id
                    {$whereClause}
                    ORDER BY u.last_name ASC, u.first_name ASC
                    LIMIT {$limitInt} OFFSET {$offsetInt}";
        // Patch 5: guard against query() returning a non-object on failure
        $stmt  = $this->db->query($dataSql, $params);
        $users = ($stmt && method_exists($stmt, 'fetchAll')) ? $stmt->fetchAll() : [];

        return ['users' => $users, 'total_count' => $totalCount];
    }

    public function update(int $userId, array $data): void {
        // Patch 2: validate before touching the DB
        if (array_key_exists('email', $data)) {
            $email = trim((string) ($data['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Invalid email address.');
            }
            $conflict = $this->db->fetchOne(
                'SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1',
                ['email' => $email, 'id' => $userId]
            );
            if ($conflict !== false) {
                throw new InvalidArgumentException('That email address is already in use by another account.');
            }
        }
        if (array_key_exists('username', $data)) {
            $username = trim((string) ($data['username'] ?? ''));
            if ($username === '') {
                throw new InvalidArgumentException('Username cannot be empty.');
            }
            $conflict = $this->db->fetchOne(
                'SELECT id FROM users WHERE username = :username AND id <> :id LIMIT 1',
                ['username' => $username, 'id' => $userId]
            );
            if ($conflict !== false) {
                throw new InvalidArgumentException('That username is already taken.');
            }
        }
        foreach (['first_name', 'last_name'] as $required) {
            if (array_key_exists($required, $data) && trim((string) ($data[$required] ?? '')) === '') {
                throw new InvalidArgumentException(ucwords(str_replace('_', ' ', $required)) . ' cannot be empty.');
            }
        }

        $allowed = ['first_name', 'last_name', 'preferred_name', 'email', 'username'];
        $sets = [];
        $params = ['id' => $userId];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }
        if (empty($sets)) {
            return;
        }
        $sets[] = "updated_at = NOW()";
        $sql = "UPDATE users SET " . implode(', ', $sets) . " WHERE id = :id";
        $this->db->query($sql, $params);

        ActivityLogger::log('admin.user_edited', [
            'user_id' => $userId,
            'fields'  => array_keys(array_intersect_key($data, array_flip($allowed))),
        ]);
    }

    public function setRole(int $userId, string $role, int $adminUserId): void {
        $validRoles = ['user', 'team_owner', 'administrator'];
        if (!in_array($role, $validRoles, true)) {
            throw new InvalidArgumentException("Invalid role: {$role}");
        }

        $user = $this->db->fetchOne(
            'SELECT role_id FROM users WHERE id = :id LIMIT 1',
            ['id' => $userId]
        );
        if ($user === false) {
            throw new RuntimeException("User not found: {$userId}");
        }
        $oldRoleName = $this->resolveRoleName((int) ($user['role_id'] ?? 0));

        $this->setUserRole($userId, $role);

        ActivityLogger::log('admin.user_role_changed', [
            'user_id'       => $userId,
            'old_role'      => $oldRoleName,
            'new_role'      => $role,
            'admin_user_id' => $adminUserId,
        ]);
    }

    public function disable(int $userId, int $adminUserId): void {
        $this->db->query(
            "UPDATE users SET status = 'disabled', session_invalidated_at = NOW(), updated_at = NOW() WHERE id = :id",
            ['id' => $userId]
        );

        ActivityLogger::log('admin.user_disabled', [
            'user_id'       => $userId,
            'admin_user_id' => $adminUserId,
        ]);
    }

    public function enable(int $userId, int $adminUserId): void {
        $this->db->query(
            "UPDATE users SET status = 'active', updated_at = NOW() WHERE id = :id",
            ['id' => $userId]
        );

        ActivityLogger::log('admin.user_enabled', [
            'user_id'       => $userId,
            'admin_user_id' => $adminUserId,
        ]);
    }

    public function forceVerify(int $userId, int $adminUserId): void {
        $stmt = $this->db->query(
            "UPDATE users
             SET status = 'active',
                 verification_token = NULL,
                 verification_expiry = NULL,
                 updated_at = NOW()
             WHERE id = :id AND status = 'unverified'",
            ['id' => $userId]
        );

        if ((int) $stmt->rowCount() !== 1) {
            throw new RuntimeException('User not found or account is not in unverified state.');
        }

        ActivityLogger::log('registration.account_verified', [
            'user_id'       => $userId,
            'admin_user_id' => $adminUserId,
        ]);
    }

    public function delete(int $userId, int $adminUserId): void {
        $this->db->beginTransaction();
        try {
            $this->db->query(
                'DELETE FROM team_owners WHERE user_id = :user_id',
                ['user_id' => $userId]
            );
            $this->db->query(
                'DELETE FROM users WHERE id = :id',
                ['id' => $userId]
            );
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        ActivityLogger::log('admin.user_deleted', [
            'user_id'       => $userId,
            'admin_user_id' => $adminUserId,
        ]);
    }

    public function resetPassword(int $userId, int $adminUserId): string {
        $tempPassword = $this->generateTempPassword();
        $hash = password_hash($tempPassword, PASSWORD_BCRYPT);

        $this->db->query(
            "UPDATE users SET password_hash = :hash, force_password_change = 1, updated_at = NOW() WHERE id = :id",
            ['hash' => $hash, 'id' => $userId]
        );

        ActivityLogger::log('admin.user_password_reset', [
            'user_id'       => $userId,
            'admin_user_id' => $adminUserId,
        ]);

        return $tempPassword;
    }

    // -----------------------------------------------------------------------
    // Public API — Story 4.3: Team Assignment
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

        // Fetch user data before mutating
        $user = $this->db->fetchOne(
            'SELECT id, first_name, last_name, email, phone, role_id FROM users WHERE id = :id LIMIT 1',
            ['id' => $userId]
        );
        if ($user === false) {
            throw new RuntimeException('User not found.');
        }

        // Insert team_owners row.
        // assigned_by is NULL because admin IDs come from admin_users, not users,
        // and cannot satisfy the team_owners.assigned_by FK -> users(id).
        $this->db->query(
            'INSERT INTO team_owners (user_id, team_id, assigned_by, created_at)
             VALUES (:user_id, :team_id, NULL, NOW())',
            ['user_id' => $userId, 'team_id' => $teamId]
        );

        // Backfill teams.manager_* so the denormalised columns stay consistent
        // with the canonical user data.
        $this->db->query(
            'UPDATE teams
                SET manager_first_name = :first_name,
                    manager_last_name  = :last_name,
                    manager_email      = :email,
                    manager_phone      = :phone
              WHERE team_id = :team_id',
            [
                'first_name' => $user['first_name'],
                'last_name'  => $user['last_name'] ?? '',
                'email'      => $user['email'],
                'phone'      => $user['phone'] ?? null,
                'team_id'    => $teamId,
            ]
        );

        // Elevate role to team_owner whenever not already team_owner
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

    private function generateTempPassword(int $length = 12): string {
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits = '23456789';
        $special = '!@#$%';
        $all = $lower . $upper . $digits . $special;
        $maxAll = strlen($all) - 1;

        // Guarantee at least one from each category
        $password = '';
        $password .= $upper[random_int(0, strlen($upper) - 1)];
        $password .= $digits[random_int(0, strlen($digits) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        for ($i = 3; $i < $length; $i++) {
            $password .= $all[random_int(0, $maxAll)];
        }

        // Shuffle to avoid predictable positions
        $chars = str_split($password);
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}
