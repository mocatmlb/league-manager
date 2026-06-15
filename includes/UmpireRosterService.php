<?php
if (!defined('D8TL_APP')) { die('Direct access not permitted'); }

if (!class_exists('ActivityLogger')) {
    require_once __DIR__ . '/ActivityLogger.php';
}
if (!class_exists('Logger')) {
    require_once __DIR__ . '/Logger.php';
}

if (!class_exists('DuplicateEmailException')) {
    class DuplicateEmailException extends \RuntimeException {}
}

class UmpireRosterService {

    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create a new umpire user account + umpire_profiles row in one transaction.
     * Returns ['user_id' => int, 'temp_password' => string].
     * Throws DuplicateEmailException, InvalidArgumentException.
     */
    public function createUmpire(array $data, int $actorUserId): array {
        // Validate required fields
        foreach (['first_name', 'last_name', 'email', 'phone', 'umpire_level'] as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $label = ucwords(str_replace('_', ' ', $field));
                throw new \InvalidArgumentException("{$label} is required.");
            }
        }

        $email     = trim(strtolower((string) $data['email']));
        $username  = $email;
        $firstName = trim((string) $data['first_name']);
        $lastName  = trim((string) $data['last_name']);
        $phone     = trim((string) $data['phone']);
        $level     = trim((string) $data['umpire_level']);
        $isUnder18 = !empty($data['is_under_18']) ? 1 : 0;
        $dob       = !empty($data['date_of_birth']) ? trim((string) $data['date_of_birth']) : null;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address.');
        }

        if (!in_array($level, ['Blue Shirt', 'Black Shirt'], true)) {
            throw new \InvalidArgumentException('Invalid umpire level. Must be Blue Shirt or Black Shirt.');
        }

        $this->validateUnder18Dob((bool) $isUnder18, $dob);

        // Duplicate email check
        $existing = $this->db->fetchOne(
            'SELECT id FROM users WHERE email = :e LIMIT 1',
            ['e' => $email]
        );
        if ($existing !== false) {
            throw new DuplicateEmailException('Email address is already in use.');
        }

        // Resolve umpire role_id
        $umpireRoleId = (int) ($this->db->fetchOne(
            'SELECT id FROM roles WHERE name = :name LIMIT 1',
            ['name' => 'umpire']
        )['id'] ?? 0);
        if ($umpireRoleId < 1) {
            throw new \RuntimeException('umpire role not found — is migration 041 applied?');
        }

        // Generate temp password
        $tempPassword = $this->generateTempPassword();
        $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);

        $newId = 0;
        $inTransaction = false;
        try {
            $this->db->beginTransaction();
            $inTransaction = true;

            // 1. INSERT users
            $this->db->query(
                "INSERT INTO users
                    (username, email, password_hash, first_name, last_name, preferred_name,
                     phone, role_id, status, verification_token, verification_expiry,
                     force_password_change, created_at, updated_at)
                 VALUES
                    (:username, :email, :password_hash, :first_name, :last_name, NULL,
                     :phone, :role_id, 'active', NULL, NULL,
                     1, NOW(), NOW())",
                [
                    'username'      => $username,
                    'email'         => $email,
                    'password_hash' => $passwordHash,
                    'first_name'    => $firstName,
                    'last_name'     => $lastName,
                    'phone'         => $phone,
                    'role_id'       => $umpireRoleId,
                ]
            );
            $newId = (int) $this->db->getConnection()->lastInsertId();

            // 2. Dual-write phone to user_phones
            $this->db->query(
                "INSERT INTO user_phones (user_id, phone, type, role, created_at, updated_at)
                 VALUES (:uid, :phone, 'Cell', 'primary', NOW(), NOW())",
                ['uid' => $newId, 'phone' => $phone]
            );

            // 3. INSERT umpire_profiles
            $this->db->query(
                "INSERT INTO umpire_profiles (user_id, umpire_level, is_under_18, date_of_birth, created_at, updated_at)
                 VALUES (:uid, :level, :under18, :dob, NOW(), NOW())",
                ['uid' => $newId, 'level' => $level, 'under18' => $isUnder18, 'dob' => $isUnder18 ? $dob : null]
            );

            $this->db->commit();
            $inTransaction = false;
        } catch (\Throwable $e) {
            if ($inTransaction) {
                try { $this->db->rollback(); } catch (\Throwable $ignored) {}
            }
            throw $e;
        }

        ActivityLogger::log('umpire.account_created', [
            'user_id'       => $newId,
            'actor_user_id' => $actorUserId,
            'level'         => $level,
            'is_under_18'   => $isUnder18,
        ]);

        if (!$this->isMigrationMode()) {
            $this->sendWelcomeEmail($email, $firstName, $tempPassword);
        }

        return ['user_id' => $newId, 'temp_password' => $tempPassword];
    }

    /**
     * Return all umpires joined with umpire_profiles.
     * $activeOnly = true hides disabled accounts.
     */
    public function getRoster(bool $activeOnly = true): array {
        $activeClause = $activeOnly ? "AND u.status = 'active'" : '';
        $sql = "SELECT
                    u.id, u.first_name, u.last_name, u.email, u.phone, u.status,
                    p.umpire_level, p.is_under_18, p.date_of_birth
                FROM users u
                INNER JOIN umpire_profiles p ON p.user_id = u.id
                WHERE u.role_id = (SELECT id FROM roles WHERE name = 'umpire' LIMIT 1)
                  {$activeClause}
                ORDER BY u.last_name ASC, u.first_name ASC";
        $stmt = $this->db->query($sql, []);
        return ($stmt && method_exists($stmt, 'fetchAll')) ? $stmt->fetchAll() : [];
    }

    /**
     * Return single umpire row + profile, or null if not found / not an umpire.
     */
    public function getUmpire(int $userId): ?array {
        $row = $this->db->fetchOne(
            "SELECT
                u.id, u.first_name, u.last_name, u.email, u.phone, u.status,
                p.umpire_level, p.is_under_18, p.date_of_birth
             FROM users u
             INNER JOIN umpire_profiles p ON p.user_id = u.id
             WHERE u.id = :uid
               AND u.role_id = (SELECT id FROM roles WHERE name = 'umpire' LIMIT 1)
             LIMIT 1",
            ['uid' => $userId]
        );
        return $row !== false ? $row : null;
    }

    /**
     * Update umpire_profiles fields (level, is_under_18, date_of_birth).
     * Enforces: under18=true requires DOB; under18=false clears DOB.
     */
    public function updateProfile(int $userId, array $data, int $actorUserId): void {
        $level     = trim((string) ($data['umpire_level'] ?? 'Blue Shirt'));
        $isUnder18 = !empty($data['is_under_18']) ? 1 : 0;
        $dob       = !empty($data['date_of_birth']) ? trim((string) $data['date_of_birth']) : null;

        if (!in_array($level, ['Blue Shirt', 'Black Shirt'], true)) {
            throw new \InvalidArgumentException('Invalid umpire level.');
        }

        $this->validateUnder18Dob((bool) $isUnder18, $dob);

        // If adult, clear DOB unconditionally
        $dobToStore = $isUnder18 ? $dob : null;

        $this->db->query(
            "INSERT INTO umpire_profiles (user_id, umpire_level, is_under_18, date_of_birth, created_at, updated_at)
             VALUES (:uid, :level, :under18, :dob, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                 umpire_level  = VALUES(umpire_level),
                 is_under_18   = VALUES(is_under_18),
                 date_of_birth = VALUES(date_of_birth),
                 updated_at    = NOW()",
            ['uid' => $userId, 'level' => $level, 'under18' => $isUnder18, 'dob' => $dobToStore]
        );

        ActivityLogger::log('umpire.profile_updated', [
            'user_id'       => $userId,
            'actor_user_id' => $actorUserId,
        ]);
    }

    /** Set users.status = 'disabled' and session_invalidated_at = NOW(). */
    public function deactivate(int $userId, int $actorUserId): void {
        $this->db->query(
            "UPDATE users SET status = 'disabled', session_invalidated_at = NOW(), updated_at = NOW() WHERE id = :id",
            ['id' => $userId]
        );
        ActivityLogger::log('umpire.deactivated', [
            'user_id'       => $userId,
            'actor_user_id' => $actorUserId,
        ]);
    }

    /** Set users.status = 'active'. */
    public function activate(int $userId, int $actorUserId): void {
        $this->db->query(
            "UPDATE users SET status = 'active', updated_at = NOW() WHERE id = :id",
            ['id' => $userId]
        );
        ActivityLogger::log('umpire.activated', [
            'user_id'       => $userId,
            'actor_user_id' => $actorUserId,
        ]);
    }

    /** Migration mode session toggle — session key: 'umpire_migration_mode'. */
    public function isMigrationMode(): bool      { return !empty($_SESSION['umpire_migration_mode']); }
    public function enableMigrationMode(): void  { $_SESSION['umpire_migration_mode'] = true; }
    public function disableMigrationMode(): void { unset($_SESSION['umpire_migration_mode']); }

    /**
     * Cross-service dependency — called by UmpireAssignmentService::saveSlot() (Story 23.2).
     * Auto-clears is_under_18/date_of_birth if umpire has turned 18.
     * Must never throw — any exception is swallowed and logged.
     */
    public function reconcileUnder18Flag(int $userId): void {
        try {
            $profile = $this->db->fetchOne(
                'SELECT is_under_18, date_of_birth FROM umpire_profiles WHERE user_id = :uid LIMIT 1',
                ['uid' => $userId]
            );
            if (!$profile || !$profile['is_under_18'] || empty($profile['date_of_birth'])) {
                return;
            }
            $dob = new \DateTime($profile['date_of_birth']);
            $now = new \DateTime();
            if ($now->diff($dob)->y >= 18) {
                $this->db->query(
                    "UPDATE umpire_profiles
                     SET is_under_18 = 0, date_of_birth = NULL, updated_at = NOW()
                     WHERE user_id = :uid",
                    ['uid' => $userId]
                );
                ActivityLogger::log('umpire.under18_auto_cleared', [
                    'umpire_user_id' => $userId,
                ]);
            }
        } catch (\Throwable $e) {
            Logger::error('[UmpireRosterService] reconcileUnder18Flag failed silently', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function sendWelcomeEmail(string $toEmail, string $firstName, string $tempPassword): void {
        try {
            if (!class_exists('EmailService')) {
                require_once __DIR__ . '/EmailService.php';
            }
            $emailSvc = new EmailService();
            $loginUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/login.php';
            $emailSvc->triggerNotificationToAddress('umpire_account_welcome', $toEmail, [
                'first_name'    => $firstName,
                'email'         => $toEmail,
                'temp_password' => $tempPassword,
                'login_url'     => $loginUrl,
            ]);
        } catch (\Throwable $e) {
            Logger::error('[UmpireRosterService] sendWelcomeEmail failed', [
                'to'    => $toEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Throws InvalidArgumentException if $isUnder18=true and $dob is empty or not a valid date. */
    private function validateUnder18Dob(bool $isUnder18, ?string $dob): void {
        if (!$isUnder18) {
            return;
        }
        if (empty($dob)) {
            throw new \InvalidArgumentException('Date of birth is required for under-18 umpires.');
        }
        $parsed = \DateTime::createFromFormat('Y-m-d', $dob);
        if (!$parsed || $parsed->format('Y-m-d') !== $dob) {
            throw new \InvalidArgumentException('Date of birth must be a valid date in YYYY-MM-DD format.');
        }
        // Sanity checks: DOB must be in the past and within reasonable age range (5-120 years)
        $now = new \DateTime();
        if ($parsed >= $now) {
            throw new \InvalidArgumentException('Date of birth cannot be in the future.');
        }
        $age = (int) $now->diff($parsed)->y;
        if ($age < 5 || $age > 120) {
            throw new \InvalidArgumentException('Date of birth must represent an age between 5 and 120 years.');
        }
    }

    private function generateTempPassword(int $length = 12): string {
        $lower   = 'abcdefghijkmnopqrstuvwxyz';
        $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits  = '23456789';
        $special = '!@#$%';
        $all     = $lower . $upper . $digits . $special;
        $maxAll  = strlen($all) - 1;

        $password  = $upper[random_int(0, strlen($upper) - 1)];
        $password .= $digits[random_int(0, strlen($digits) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        for ($i = 3; $i < $length; $i++) {
            $password .= $all[random_int(0, $maxAll)];
        }

        $chars = str_split($password);
        shuffle($chars);
        return implode('', $chars);
    }
}
