<?php
/**
 * District 8 Travel League - Registration Service
 *
 * Handles self-registration, email verification, password reset, and
 * verification resend flows.
 */

if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

if (!class_exists('ActivityLogger')) {
    require_once __DIR__ . '/ActivityLogger.php';
}
if (!class_exists('Logger')) {
    require_once __DIR__ . '/Logger.php';
}

class DuplicateUsernameException extends RuntimeException {}
class DuplicateEmailException extends RuntimeException {}
class ExpiredTokenException extends RuntimeException {}
class InvalidPasswordException extends RuntimeException {}
class WeakPasswordException extends RuntimeException {}

class RegistrationService {
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

    public function register(array $data): int {
        $this->validateRequired($data, ['username', 'email', 'password', 'first_name', 'last_name', 'phone']);

        // Normalize email so the duplicate check matches the same comparison
        // used in requestPasswordReset() and InvitationService::send().
        $email = trim(strtolower((string) $data['email']));
        $username = trim((string) $data['username']);
        $data['email'] = $email;
        $data['username'] = $username;

        $this->validatePasswordComplexity($data['password']);

        if ($this->usernameExists($username)) {
            throw new DuplicateUsernameException('Username is already in use.');
        }
        if ($this->emailExists($email)) {
            throw new DuplicateEmailException('Email is already in use.');
        }

        $token = $this->generateToken();
        $expiry = $this->tokenExpiry();
        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);

        $insert = [
            'username'            => $username,
            'email'               => $email,
            'first_name'          => $data['first_name'],
            'last_name'           => $data['last_name'],
            'phone'               => $data['phone'],
            'status'              => 'unverified',
            'verification_token'  => $token,
            'verification_expiry' => $expiry,
        ];
        $this->applyPasswordColumn($insert, $passwordHash);
        $this->applyRoleDefaults($insert);

        // Wrap insert + email send in a transaction so that an email-send
        // failure rolls back the user row, preventing username/email squat.
        $connection = $this->db->getConnection();
        $useTransaction = method_exists($connection, 'beginTransaction');

        if ($useTransaction) {
            try {
                $connection->beginTransaction();
            } catch (Throwable $e) {
                // Mock connections may not support real transactions; proceed without.
                $useTransaction = false;
            }
        }

        $userId = 0;
        try {
            try {
                $this->insertUser($insert);
            } catch (PDOException $e) {
                // 23000 = integrity constraint violation (unique key collision
                // racing against an earlier check). Map to typed exceptions.
                if ($e->getCode() === '23000' || ($e->errorInfo[0] ?? '') === '23000') {
                    if ($this->usernameExists($username)) {
                        throw new DuplicateUsernameException('Username is already in use.', 0, $e);
                    }
                    if ($this->emailExists($email)) {
                        throw new DuplicateEmailException('Email is already in use.', 0, $e);
                    }
                }
                throw $e;
            }

            $userId = (int) $connection->lastInsertId();

            if (!$this->sendVerificationEmail($email, $data['first_name'], $token, $userId)) {
                throw new RuntimeException('Failed to send verification email.');
            }

            if ($useTransaction) {
                $connection->commit();
            }
        } catch (Throwable $e) {
            if ($useTransaction) {
                try { $connection->rollBack(); } catch (Throwable $ignored) {}
            }
            throw $e;
        }

        ActivityLogger::log('registration.verification_email_sent', [
            'user_id' => $userId,
            'email'   => $email,
        ]);

        return $userId;
    }

    public function verifyEmail(string $token): int {
        $user = $this->db->fetchOne(
            'SELECT id, email, first_name, verification_expiry
             FROM users
             WHERE verification_token = :token',
            ['token' => $token]
        );

        if ($user === false) {
            throw new RuntimeException('Verification token is invalid or already consumed.');
        }

        if (!empty($user['verification_expiry']) && strtotime((string) $user['verification_expiry']) < time()) {
            throw new ExpiredTokenException('Verification token has expired.');
        }

        $stmt = $this->db->query(
            "UPDATE users
             SET status = 'active',
                 verification_token = NULL,
                 verification_expiry = NULL,
                 updated_at = NOW()
             WHERE id = :id AND verification_token = :token",
            [
                'id'    => (int) $user['id'],
                'token' => $token,
            ]
        );

        if ((int) $stmt->rowCount() !== 1) {
            throw new RuntimeException('Verification token is invalid or already consumed.');
        }

        $userId = (int) $user['id'];
        ActivityLogger::log('registration.account_verified', ['user_id' => $userId]);
        $this->sendAdminNotification($userId, (string) $user['email']);

        return $userId;
    }

    /**
     * Re-issue a verification token and send a new verification email.
     *
     * Identifies the target user by their unverified email (provided by the
     * caller) — NOT by raw user_id from form input. This prevents the
     * verify-email page from leaking user_ids and being abused to trigger
     * verification emails on arbitrary accounts.
     *
     * Only operates on users with status='unverified'. Active/suspended/
     * locked accounts get a silent no-op (returned without error to avoid
     * account enumeration).
     */
    public function resendVerification(string $email): void {
        $email = trim(strtolower($email));
        if ($email === '') {
            return;
        }

        $user = $this->db->fetchOne(
            'SELECT id, email, first_name, status FROM users WHERE email = :email LIMIT 1',
            ['email' => $email]
        );

        // No-op for unknown emails or non-unverified accounts (no enumeration).
        if ($user === false || (string) ($user['status'] ?? '') !== 'unverified') {
            return;
        }

        $userId = (int) $user['id'];
        $token = $this->generateToken();
        $expiry = $this->tokenExpiry();

        $this->db->query(
            'UPDATE users
             SET verification_token = :token,
                 verification_expiry = :expiry,
                 updated_at = NOW()
             WHERE id = :id',
            [
                'token'  => $token,
                'expiry' => $expiry,
                'id'     => $userId,
            ]
        );

        if (!$this->sendVerificationEmail((string) $user['email'], (string) $user['first_name'], $token, $userId)) {
            throw new RuntimeException('Failed to send verification email.');
        }
    }

    public function requestPasswordReset(string $email): void {
        $email = trim(strtolower($email));
        if ($email === '') {
            return;
        }

        $user = $this->db->fetchOne(
            'SELECT id, first_name, email
             FROM users
             WHERE email = :email
             LIMIT 1',
            ['email' => $email]
        );

        // Account-enumeration mitigation: equalize timing between known and
        // unknown emails by always running an artificial delay roughly the
        // same order of magnitude as the known-email path. Combined with
        // the caller (forgot-password.php) showing the same confirmation
        // either way, this materially reduces the timing leak. Not perfect
        // (SMTP latency isn't fully replicated), but practical for shared
        // hosting where we can't run async work.
        if ($user === false) {
            // Burn comparable time to a real email send (~100-300ms).
            usleep(random_int(120_000, 280_000));
            return;
        }

        $token = $this->generateToken();
        $expiry = date('Y-m-d H:i:s', time() + 24 * 60 * 60);

        $this->db->query(
            'UPDATE users
             SET password_reset_token = :token,
                 password_reset_expiry = :expiry,
                 updated_at = NOW()
             WHERE id = :id',
            [
                'token' => $token,
                'expiry' => $expiry,
                'id' => (int) $user['id'],
            ]
        );

        $sent = $this->sendEmailToAddress(
            'auth_password_reset',
            (string) $user['email'],
            [
                'user_id' => (int) $user['id'],
                'email' => (string) $user['email'],
                'first_name' => (string) $user['first_name'],
                'reset_link' => $this->buildAbsoluteUrl('/coaches/reset-password.php?token=' . urlencode($token)),
                'token' => $token,
            ]
        );

        if (!$sent) {
            // AC5: do not expose account-existence via differential errors.
            // Log the failure operationally and return as if it succeeded —
            // caller always shows the same "check your email" confirmation.
            Logger::error('Password reset email send failed (suppressed for non-enumeration)', [
                'user_id' => (int) $user['id'],
                'email' => $email,
            ]);
            return;
        }

        ActivityLogger::log('auth.password_reset_requested', [
            'user_id' => (int) $user['id'],
            'email' => (string) $user['email'],
        ]);
    }

    public function completePasswordReset(string $token, string $newPassword): void {
        $token = trim($token);
        if ($token === '') {
            throw new ExpiredTokenException('Reset token is invalid or expired.');
        }

        $user = $this->db->fetchOne(
            'SELECT id, password_reset_expiry
             FROM users
             WHERE password_reset_token = :token
             LIMIT 1',
            ['token' => $token]
        );

        if ($user === false) {
            throw new ExpiredTokenException('Reset token is invalid or expired.');
        }

        if (!empty($user['password_reset_expiry']) && strtotime((string) $user['password_reset_expiry']) < time()) {
            throw new ExpiredTokenException('Reset token is invalid or expired.');
        }

        try {
            $this->validatePasswordComplexity($newPassword);
        } catch (InvalidPasswordException $e) {
            throw new WeakPasswordException($e->getMessage(), 0, $e);
        }

        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $update = [
            'password_reset_token' => null,
            'password_reset_expiry' => null,
        ];
        if ($this->hasUsersColumn('password_changed_at')) {
            $update['password_changed_at'] = date('Y-m-d H:i:s');
        }
        $this->applyPasswordColumn($update, $passwordHash);

        $this->db->update(
            'users',
            $update,
            'id = :id',
            ['id' => (int) $user['id']]
        );

        // Invalidate all remember-me tokens for this user (forces re-auth on
        // any device that was using "remember me"). Active sessions on other
        // devices are invalidated via the password_changed_at check in
        // AuthService::enforceSessionLifetime() on their next request.
        $this->db->query(
            'DELETE FROM remember_tokens WHERE user_id = :user_id',
            ['user_id' => (int) $user['id']]
        );

        ActivityLogger::log('auth.password_reset_completed', [
            'user_id' => (int) $user['id'],
        ]);
    }

    private function validateRequired(array $data, array $required): void {
        foreach ($required as $field) {
            if (!array_key_exists($field, $data) || trim((string) $data[$field]) === '') {
                throw new InvalidArgumentException('Missing required field: ' . $field);
            }
        }
    }

    /**
     * Validate password against FR-REG-5 complexity requirements.
     * Throws InvalidPasswordException with a SPECIFIC, user-friendly message
     * naming the rule violated (Story 3-2 AC6). When multiple rules fail,
     * the first-failed rule is reported (length checked first).
     */
    private function validatePasswordComplexity(string $password): void {
        if (strlen($password) < 8) {
            throw new InvalidPasswordException('Password must be at least 8 characters.');
        }
        if (!preg_match('/[A-Z]/', $password)) {
            throw new InvalidPasswordException('Password must contain at least one uppercase letter.');
        }
        if (!preg_match('/\d/', $password)) {
            throw new InvalidPasswordException('Password must contain at least one number.');
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            throw new InvalidPasswordException('Password must contain at least one special character.');
        }
    }

    private function usernameExists(string $username): bool {
        return $this->db->fetchOne(
            'SELECT id FROM users WHERE username = :username',
            ['username' => $username]
        ) !== false;
    }

    private function emailExists(string $email): bool {
        return $this->db->fetchOne(
            'SELECT id FROM users WHERE email = :email',
            ['email' => $email]
        ) !== false;
    }

    private function insertUser(array $insert): void {
        $columns = array_keys($insert);
        $columnSql = implode(', ', $columns);
        $placeholderSql = ':' . implode(', :', $columns);

        $this->db->query(
            "INSERT INTO users ({$columnSql}, created_at, updated_at)
             VALUES ({$placeholderSql}, NOW(), NOW())",
            $insert
        );
    }

    private function applyRoleDefaults(array &$insert): void {
        if ($this->hasUsersColumn('role')) {
            $insert['role'] = 'user';
            return;
        }

        if ($this->hasUsersColumn('role_id')) {
            $role = $this->db->fetchOne(
                'SELECT id FROM roles WHERE name = :name',
                ['name' => 'user']
            );

            if ($role === false) {
                throw new RuntimeException("Default role 'user' not found.");
            }

            $insert['role_id'] = (int) $role['id'];
        }
    }

    private function applyPasswordColumn(array &$data, string $passwordHash): void {
        if ($this->hasUsersColumn('password_hash')) {
            $data['password_hash'] = $passwordHash;
            return;
        }
        $data['password'] = $passwordHash;
    }

    private function hasUsersColumn(string $column): bool {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            return false;
        }
        // information_schema works with native prepared statements; SHOW COLUMNS + bound params does not.
        return $this->db->fetchOne(
            'SELECT 1 AS ok FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
             LIMIT 1',
            ['users', $column]
        ) !== false;
    }

    private function sendVerificationEmail(string $email, string $firstName, string $token, int $userId): bool {
        return $this->sendEmailToAddress(
            'registration_verification',
            $email,
            [
                'email'             => $email,
                'first_name'        => $firstName,
                'verification_link' => $this->buildAbsoluteUrl('/coaches/verify-email.php?token=' . urlencode($token)),
                'token'             => $token,
                'user_id'           => $userId,
            ]
        );
    }

    private function sendAdminNotification(int $userId, string $email): void {
        try {
            // Operational notification per AR-12 — failure logged, not surfaced.
            $adminEmail = $this->resolveAdminNotificationAddress();
            $context = ['user_id' => $userId, 'email' => $email];

            if ($adminEmail !== '') {
                $this->sendEmailToAddress('registration_account_verified', $adminEmail, $context);
                return;
            }

            // No admin address configured — fall back to legacy
            // triggerNotification path (which routes via email_recipients
            // table). Logs a warning so operators can see this is the
            // fallback flow.
            Logger::warn('No admin notification address configured; using legacy triggerNotification routing', [
                'user_id' => $userId,
            ]);
            if (method_exists($this->emailService, 'triggerNotification')) {
                $this->emailService->triggerNotification('registration_account_verified', $context);
            }
        } catch (Throwable $e) {
            Logger::error('Operational notification failed after account verification', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send an email to an explicit recipient using the auth-email path.
     * Prefers EmailService::triggerNotificationToAddress (added for Epic 3
     * to support per-user dynamic recipients). Falls back to the legacy
     * triggerNotification (which expects email_recipients-table-driven
     * resolution and will not work for these templates) only if needed —
     * and logs a warning in that case.
     */
    private function sendEmailToAddress(string $template, string $toEmail, array $context): bool {
        if (method_exists($this->emailService, 'triggerNotificationToAddress')) {
            return (bool) $this->emailService->triggerNotificationToAddress($template, $toEmail, $context);
        }

        if (method_exists($this->emailService, 'triggerNotification')) {
            Logger::warn('EmailService missing triggerNotificationToAddress; using legacy triggerNotification', [
                'template' => $template,
            ]);
            return (bool) $this->emailService->triggerNotification($template, $context);
        }

        throw new RuntimeException('Email service does not support notifications.');
    }

    private function resolveAdminNotificationAddress(): string {
        // Prefer a settings-configured address; fall back to APP_ADMIN_EMAIL constant.
        try {
            $row = $this->db->fetchOne(
                "SELECT setting_value FROM settings WHERE setting_key = 'admin_notification_email' LIMIT 1"
            );
            if ($row !== false && trim((string) ($row['setting_value'] ?? '')) !== '') {
                return trim((string) $row['setting_value']);
            }
        } catch (Throwable $e) {
            // settings table read failure — fall through to constant.
        }

        if (defined('APP_ADMIN_EMAIL')) {
            return (string) APP_ADMIN_EMAIL;
        }
        return '';
    }

    /**
     * Build a fully-qualified URL for emails. Prefers APP_URL constant.
     * Refuses to fall back to $_SERVER['HTTP_HOST'] (host-header injection
     * vector) — instead returns a path-only URL that the caller can resolve
     * manually if APP_URL is unset.
     */
    private function buildAbsoluteUrl(string $path): string {
        $appUrl = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
        if ($appUrl !== '') {
            return $appUrl . $path;
        }
        // Defensive: return path-only. Email templates show this verbatim;
        // operators will notice broken links and configure APP_URL.
        return $path;
    }

    private function generateToken(): string {
        return bin2hex(random_bytes(32));
    }

    private function tokenExpiry(): string {
        return date('Y-m-d H:i:s', time() + 48 * 60 * 60);
    }
}
?>
