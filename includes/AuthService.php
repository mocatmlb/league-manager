<?php
/**
 * District 8 Travel League - Auth Service
 *
 * Individual coach account authentication, progressive-backoff brute-force
 * resistance, CAPTCHA gating, remember-me token handling, and session
 * lifecycle helpers.
 */

if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

if (!class_exists('ActivityLogger')) {
    require_once __DIR__ . '/ActivityLogger.php';
}

class AuthService {
    private const REMEMBER_COOKIE = 'd8tl_remember';
    private const REMEMBER_TTL_SECONDS = 2592000; // 30 days
    private const SESSION_TIMEOUT_SECONDS = 3600; // 60 minutes
    private const CAPTCHA_THRESHOLD = 3; // CAPTCHA required after 3+ IP failures in 24h
    private const BACKOFF_WINDOW_SECONDS = 900; // 15 minutes for backoff counting
    private const BACKOFF_MAX_DELAY_USEC = 8_000_000; // cap each delay at 8 seconds

    private static ?string $passwordColumn = null;

    public static function authenticate(string $identifier, string $password, string $ipAddress, bool $rememberMe): bool {
        $identifier = trim($identifier);
        if ($identifier === '' || $password === '') {
            return false;
        }

        $db = Database::getInstance();
        $passwordColumn = self::passwordColumn();

        // Resolve user (lookup by username OR email) — used both for canonical
        // identifier in failure-counting and to skip lockout for unverified
        // accounts whose password is correct.
        $user = $db->fetchOne(
            "SELECT id, username, email, {$passwordColumn} AS password_hash, status,
                    " . (self::usersHasColumn('password_changed_at') ? 'password_changed_at' : "NULL AS password_changed_at") . "
             FROM users
             WHERE username = :identifier OR email = :identifier
             LIMIT 1",
            ['identifier' => $identifier]
        );

        // Canonical identifier for failure counting: prefer the resolved
        // user_id-keyed string ("uid:N") so alternating username vs email
        // doesn't bypass backoff. Fall back to raw identifier for unknown
        // accounts (still counted to slow username enumeration).
        $canonicalIdentifier = $user !== false
            ? 'uid:' . (int) $user['id']
            : 'unk:' . strtolower($identifier);

        // Apply progressive backoff BEFORE password check to slow brute force.
        self::applyBackoffDelay($canonicalIdentifier);

        $passwordOk = $user !== false && password_verify($password, (string) ($user['password_hash'] ?? ''));
        $statusActive = $user !== false && (string) ($user['status'] ?? '') === 'active';

        if ($user === false || !$passwordOk) {
            // Always purge before INSERT (AR-9 compliant).
            self::lazyPurgeLoginAttempts();
            self::recordFailedAttempt($canonicalIdentifier, $ipAddress);
            ActivityLogger::log('auth.login_failure', [
                'identifier' => $identifier,
                'ip' => $ipAddress,
            ]);
            return false;
        }

        if (!$statusActive) {
            // Password was correct but account is unverified/suspended/etc.
            // DO NOT count toward backoff — otherwise an attacker with the
            // correct password locks out a legitimate user the moment they verify.
            $statusValue = (string) ($user['status'] ?? 'unknown');
            ActivityLogger::log('auth.login_failure', [
                'identifier' => $identifier,
                'ip' => $ipAddress,
                'reason' => 'account_status_' . $statusValue,
            ]);
            throw new RuntimeException(self::statusMessage($statusValue));
        }

        // Successful login — clear failure rows for this canonical identifier
        // (true "consecutive failures" semantics) and start the session.
        self::clearFailuresForIdentifier($canonicalIdentifier);
        self::setCoachSession($user);
        if ($rememberMe) {
            self::issueRememberToken((int) $user['id']);
        }

        ActivityLogger::log('auth.login_success', [
            'user_id' => (int) $user['id'],
            'identifier' => $identifier,
            'ip' => $ipAddress,
        ]);
        return true;
    }

    /**
     * Returns count of failed login attempts for this IP within the past 24h.
     * Read-only (does NOT trigger lazy-purge — purge runs only before INSERTs).
     */
    public static function failedAttemptsForIp(string $ipAddress): int {
        $db = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT COUNT(*) AS count
             FROM login_attempts
             WHERE ip_address = :ip
               AND attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)',
            ['ip' => self::resolveClientIp($ipAddress)]
        );
        return (int) ($row['count'] ?? 0);
    }

    public static function captchaRequired(string $ipAddress): bool {
        return self::failedAttemptsForIp($ipAddress) >= self::CAPTCHA_THRESHOLD;
    }

    public static function attemptRememberLogin(): bool {
        if (empty($_COOKIE[self::REMEMBER_COOKIE])) {
            return false;
        }

        $rawToken = (string) $_COOKIE[self::REMEMBER_COOKIE];
        $tokenHash = hash('sha256', $rawToken);
        $db = Database::getInstance();

        $tokenRow = $db->fetchOne(
            'SELECT id, user_id, expires_at
             FROM remember_tokens
             WHERE token_hash = :token_hash
             LIMIT 1',
            ['token_hash' => $tokenHash]
        );

        if ($tokenRow === false || strtotime((string) ($tokenRow['expires_at'] ?? '1970-01-01 00:00:00')) < time()) {
            self::clearRememberCookie();
            return false;
        }

        $passwordColumn = self::passwordColumn();
        $hasPwdChanged = self::usersHasColumn('password_changed_at');
        $user = $db->fetchOne(
            "SELECT id, username, email, status,
                    " . ($hasPwdChanged ? 'password_changed_at' : "NULL AS password_changed_at") . ",
                    {$passwordColumn} AS password_hash
             FROM users
             WHERE id = :id
             LIMIT 1",
            ['id' => (int) $tokenRow['user_id']]
        );

        if ($user === false || (string) ($user['status'] ?? '') !== 'active') {
            self::clearRememberTokenByHash($tokenHash);
            self::clearRememberCookie();
            return false;
        }

        self::setCoachSession($user);
        self::rotateRememberToken((int) $tokenRow['id'], (int) $user['id']);
        return true;
    }

    public static function logout(): void {
        $db = Database::getInstance();

        $userId = isset($_SESSION['coach_user_id']) ? (int) $_SESSION['coach_user_id'] : 0;
        if ($userId > 0) {
            $db->query('DELETE FROM remember_tokens WHERE user_id = :user_id', ['user_id' => $userId]);
            ActivityLogger::log('auth.logout', ['user_id' => $userId]);
        }

        self::clearRememberCookie();
    }

    /**
     * Sliding inactivity-timeout enforcement. Returns false if the session
     * should be terminated (caller must call self::logout() + clear session).
     * Returns true otherwise (and refreshes last_activity).
     *
     * Note: there is NO "hard expires" cap — only sliding inactivity per AC8.
     */
    public static function enforceSessionLifetime(): bool {
        if (empty($_SESSION['user_type']) || $_SESSION['user_type'] !== 'coach') {
            return true;
        }

        $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
        if ($lastActivity > 0 && (time() - $lastActivity) > self::SESSION_TIMEOUT_SECONDS) {
            return false;
        }

        $coachUserId = (int) ($_SESSION['coach_user_id'] ?? 0);
        if ($coachUserId > 0 && self::usersHasColumn('password_changed_at')) {
            $db = Database::getInstance();
            $user = $db->fetchOne(
                'SELECT password_changed_at FROM users WHERE id = :id LIMIT 1',
                ['id' => $coachUserId]
            );

            if ($user !== false) {
                $sessionPwdChanged = (string) ($_SESSION['coach_password_changed_at'] ?? '');
                $dbPwdChanged = (string) ($user['password_changed_at'] ?? '');

                // If the DB has a password_changed_at and either: (a) the session
                // doesn't, or (b) the session timestamp is older — invalidate.
                // This forces sessions issued before this column existed to
                // re-auth the next time the user changes their password.
                if ($dbPwdChanged !== '') {
                    if ($sessionPwdChanged === '' || strtotime($sessionPwdChanged) < strtotime($dbPwdChanged)) {
                        return false;
                    }
                }
            }
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function verifyRecaptcha(?string $responseToken): bool {
        $responseToken = trim((string) $responseToken);
        if ($responseToken === '') {
            return false;
        }

        $secret = '';
        if (defined('RECAPTCHA_SECRET')) {
            $secret = (string) RECAPTCHA_SECRET;
        } elseif (defined('RECAPTCHA_SECRET_KEY')) {
            $secret = (string) RECAPTCHA_SECRET_KEY;
        }

        // AR-8 fail-open is for "Google endpoint unreachable", NOT for missing
        // config. Missing secret means CAPTCHA cannot be verified at all —
        // fail closed and surface a clear error.
        if ($secret === '') {
            if (class_exists('Logger')) {
                Logger::error('CAPTCHA secret not configured — rejecting CAPTCHA verification');
            }
            return false;
        }

        $payload = http_build_query([
            'secret' => $secret,
            'response' => $responseToken,
            'remoteip' => self::resolveClientIp($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 4,
            ],
        ]);

        $result = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
        if ($result === false) {
            // AR-8: fail-open ONLY when Google is unreachable.
            if (class_exists('Logger')) {
                Logger::warn('CAPTCHA verification failed — Google unreachable; failing open per AR-8');
            }
            return true;
        }

        $decoded = json_decode($result, true);
        return !empty($decoded['success']);
    }

    /**
     * Resolves the client IP, optionally honoring X-Forwarded-For only when
     * the immediate REMOTE_ADDR is in the trusted-proxy list.
     *
     * Configure trusted proxies via the TRUSTED_PROXIES constant
     * (comma-separated CIDRs or IPs). If undefined, returns REMOTE_ADDR
     * unchanged so behind-proxy deployments must explicitly opt in.
     */
    public static function resolveClientIp(string $remoteAddr): string {
        $remoteAddr = trim($remoteAddr);
        if ($remoteAddr === '') {
            return '0.0.0.0';
        }

        if (!defined('TRUSTED_PROXIES') || trim((string) TRUSTED_PROXIES) === '') {
            return $remoteAddr;
        }
        if (empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $remoteAddr;
        }

        $trustedList = array_map('trim', explode(',', (string) TRUSTED_PROXIES));
        if (!self::ipMatchesAny($remoteAddr, $trustedList)) {
            return $remoteAddr;
        }

        // X-Forwarded-For is a comma-separated list, leftmost = original client.
        $xff = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        $candidate = trim($xff[0]);
        return filter_var($candidate, FILTER_VALIDATE_IP) ? $candidate : $remoteAddr;
    }

    /**
     * Apply progressive-backoff delay based on this identifier's recent
     * failure count. Replaces the hard-lockout response: brute force is
     * throttled (so the attacker can't get through quickly) but a legitimate
     * user is never locked out by someone else's failed attempts.
     *
     * Delay schedule (consecutive failures within window): 0, 1s, 2s, 4s, 8s,
     * 8s, 8s... (capped at BACKOFF_MAX_DELAY_USEC).
     */
    private static function applyBackoffDelay(string $canonicalIdentifier): void {
        $db = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT COUNT(*) AS count
             FROM login_attempts
             WHERE identifier = :identifier
               AND attempted_at > DATE_SUB(NOW(), INTERVAL ' . (int) self::BACKOFF_WINDOW_SECONDS . ' SECOND)',
            ['identifier' => $canonicalIdentifier]
        );
        $failures = (int) ($row['count'] ?? 0);
        if ($failures <= 0) {
            return;
        }

        // Doubling delay: 2^(failures-1) seconds, capped.
        $delaySeconds = min(2 ** ($failures - 1), self::BACKOFF_MAX_DELAY_USEC / 1_000_000);
        $delayUsec = (int) min((int) ($delaySeconds * 1_000_000), self::BACKOFF_MAX_DELAY_USEC);
        if ($delayUsec > 0) {
            usleep($delayUsec);
        }
    }

    private static function statusMessage(string $status): string {
        switch ($status) {
            case 'unverified':
                return 'Your email address is not yet verified. Please check your inbox for the verification link.';
            case 'suspended':
            case 'locked':
                return 'This account is currently disabled. Please contact league administration.';
            default:
                return 'Invalid username or password';
        }
    }

    private static function recordFailedAttempt(string $identifier, string $ipAddress): void {
        $db = Database::getInstance();
        $db->query(
            'INSERT INTO login_attempts (identifier, ip_address, attempted_at)
             VALUES (:identifier, :ip, NOW())',
            [
                'identifier' => $identifier,
                'ip' => self::resolveClientIp($ipAddress),
            ]
        );
    }

    private static function clearFailuresForIdentifier(string $identifier): void {
        $db = Database::getInstance();
        $db->query(
            'DELETE FROM login_attempts
             WHERE identifier = :identifier
               AND attempted_at > DATE_SUB(NOW(), INTERVAL ' . (int) self::BACKOFF_WINDOW_SECONDS . ' SECOND)',
            ['identifier' => $identifier]
        );
    }

    private static function lazyPurgeLoginAttempts(): void {
        $db = Database::getInstance();
        $db->query(
            'DELETE FROM login_attempts
             WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
             LIMIT 100'
        );
    }

    private static function setCoachSession(array $user): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_regenerate_id(true);
        $_SESSION['user_type'] = 'coach';
        $_SESSION['coach_user_id'] = (int) $user['id'];
        $_SESSION['coach_identifier'] = (string) ($user['username'] ?? $user['email'] ?? 'coach');
        $_SESSION['role'] = 'coach';
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        // NOTE: $_SESSION['expires'] is intentionally NOT set here. We use
        // pure sliding inactivity (last_activity) per Story 3-4 AC8 — no
        // hard absolute cap.
        $_SESSION['coach_password_changed_at'] = (string) ($user['password_changed_at'] ?? '');
    }

    private static function issueRememberToken(int $userId): void {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + self::REMEMBER_TTL_SECONDS);

        $db = Database::getInstance();
        $db->query('DELETE FROM remember_tokens WHERE user_id = :user_id', ['user_id' => $userId]);
        $db->query(
            'INSERT INTO remember_tokens (user_id, token_hash, expires_at, created_at)
             VALUES (:user_id, :token_hash, :expires_at, NOW())',
            [
                'user_id' => $userId,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
            ]
        );

        self::setRememberCookie($rawToken);
    }

    private static function rotateRememberToken(int $rememberRowId, int $userId): void {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + self::REMEMBER_TTL_SECONDS);

        $db = Database::getInstance();
        $db->query(
            'UPDATE remember_tokens
             SET token_hash = :token_hash, expires_at = :expires_at
             WHERE id = :id AND user_id = :user_id',
            [
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
                'id' => $rememberRowId,
                'user_id' => $userId,
            ]
        );

        self::setRememberCookie($rawToken);
    }

    private static function clearRememberTokenByHash(string $tokenHash): void {
        $db = Database::getInstance();
        $db->query('DELETE FROM remember_tokens WHERE token_hash = :token_hash', ['token_hash' => $tokenHash]);
    }

    private static function setRememberCookie(string $rawToken): void {
        // Spec dev notes require Secure unconditionally. Allow an explicit
        // dev-only override via REMEMBER_COOKIE_INSECURE constant.
        $secure = !(defined('REMEMBER_COOKIE_INSECURE') && REMEMBER_COOKIE_INSECURE === true);
        setcookie(
            self::REMEMBER_COOKIE,
            $rawToken,
            [
                'expires' => time() + self::REMEMBER_TTL_SECONDS,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    private static function clearRememberCookie(): void {
        $secure = !(defined('REMEMBER_COOKIE_INSECURE') && REMEMBER_COOKIE_INSECURE === true);
        setcookie(
            self::REMEMBER_COOKIE,
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    private static function passwordColumn(): string {
        if (self::$passwordColumn !== null) {
            return self::$passwordColumn;
        }

        try {
            self::$passwordColumn = self::usersHasColumn('password_hash') ? 'password_hash' : 'password';
        } catch (Throwable $e) {
            // Fall back to legacy column name on schema introspection failure.
            self::$passwordColumn = 'password';
        }
        return self::$passwordColumn;
    }

    private static function usersHasColumn(string $column): bool {
        static $cache = [];
        if (isset($cache[$column])) {
            return $cache[$column];
        }

        try {
            $db = Database::getInstance();
            $row = $db->fetchOne('SHOW COLUMNS FROM users LIKE :column', ['column' => $column]);
            $cache[$column] = ($row !== false);
        } catch (Throwable $e) {
            $cache[$column] = false;
        }
        return $cache[$column];
    }

    private static function ipMatchesAny(string $ip, array $patterns): bool {
        foreach ($patterns as $pattern) {
            if ($pattern === '') continue;
            if (strpos($pattern, '/') === false) {
                if ($pattern === $ip) return true;
                continue;
            }
            // CIDR match (IPv4 only — IPv6 trusted proxies need extra handling)
            [$subnet, $bits] = explode('/', $pattern, 2);
            $bits = (int) $bits;
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
                || filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                continue;
            }
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $mask = -1 << (32 - $bits);
            if (($ipLong & $mask) === ($subnetLong & $mask)) {
                return true;
            }
        }
        return false;
    }
}
?>
