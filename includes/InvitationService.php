<?php
/**
 * District 8 Travel League - Invitation Service
 *
 * Handles coach invitation token lifecycle and admin invitation workflows.
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

class EmailAlreadyRegisteredException extends RuntimeException {}
class InvitationNotPendingException extends RuntimeException {}

class InvitationService {
    private Database $db;
    private object $emailService;

    public function __construct(?Database $db = null, ?object $emailService = null) {
        $this->db = $db ?? Database::getInstance();

        if ($emailService !== null) {
            $this->emailService = $emailService;
        } else {
            if (!class_exists('EmailService')) {
                require_once __DIR__ . '/EmailService.php';
            }
            $this->emailService = new EmailService();
        }
    }

    public function send(string $email, int $adminUserId): void {
        $email = trim(strtolower($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email address is required.');
        }

        if ($this->emailExistsInUsers($email)) {
            throw new EmailAlreadyRegisteredException('This email already belongs to a registered account.');
        }

        // Cancel any existing pending invitation for this email so the new
        // token replaces the old one. Use 'cancelled' if the enum supports
        // it (post-migration 009); otherwise fall back to 'expired'.
        $cancelStatus = $this->statusEnumSupports('cancelled') ? 'cancelled' : 'expired';
        $this->db->query(
            "UPDATE user_invitations
             SET status = :cancel_status
             WHERE email = :email AND status = 'pending'",
            ['email' => $email, 'cancel_status' => $cancelStatus]
        );

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + (14 * 24 * 60 * 60));

        $this->db->query(
            "INSERT INTO user_invitations (email, token, role_id, invited_by, status, expires_at, created_at)
             VALUES (:email, :token, :role_id, :invited_by, 'pending', :expires_at, NOW())",
            [
                'email' => $email,
                'token' => $token,
                'role_id' => $this->defaultUserRoleId(),
                'invited_by' => $adminUserId,
                'expires_at' => $expiresAt,
            ]
        );

        $invitationUrl = $this->buildInvitationUrl($token);
        $sent = $this->sendInvitationEmail($email, $invitationUrl, $expiresAt);

        if (!$sent) {
            throw new RuntimeException('Failed to send invitation email.');
        }

        ActivityLogger::log('registration.invitation_sent', [
            'email' => $email,
            'admin_user_id' => $adminUserId,
        ]);
    }

    public function validate(string $token): array {
        $token = trim($token);
        if ($token === '') {
            throw new ExpiredTokenException('Invitation token is invalid.');
        }

        $row = $this->db->fetchOne(
            "SELECT id, email, status, expires_at
             FROM user_invitations
             WHERE token = :token
             LIMIT 1",
            ['token' => $token]
        );

        if ($row === false) {
            throw new ExpiredTokenException('Invitation token is invalid or expired.');
        }

        $status = (string) ($row['status'] ?? '');
        if ($status !== 'pending') {
            throw new ExpiredTokenException('Invitation token is invalid or expired.');
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            $this->db->query(
                "UPDATE user_invitations
                 SET status = 'expired'
                 WHERE id = :id",
                ['id' => (int) $row['id']]
            );
            throw new ExpiredTokenException('Invitation token is expired.');
        }

        return [
            'email' => (string) $row['email'],
            'invitation_id' => (int) $row['id'],
        ];
    }

    public function markConsumed(int $invitationId): void {
        $this->db->query(
            "UPDATE user_invitations
             SET status = 'completed', completed_at = NOW()
             WHERE id = :id",
            ['id' => $invitationId]
        );
    }

    /**
     * Cancel a pending invitation. Writes status='cancelled' (post-migration 009)
     * so cancelled invitations are distinguishable from naturally-expired ones.
     * Falls back to 'expired' on legacy schemas that haven't yet had migration 009 applied.
     *
     * Only logs the cancellation event when the UPDATE actually transitions a
     * pending row — prevents the audit log from claiming a cancellation that
     * didn't happen (e.g., already-completed or already-cancelled invitations).
     */
    public function cancel(int $invitationId, int $adminUserId): void {
        $cancelStatus = $this->statusEnumSupports('cancelled') ? 'cancelled' : 'expired';
        $stmt = $this->db->query(
            "UPDATE user_invitations
             SET status = :cancel_status, expires_at = NOW()
             WHERE id = :id AND status = 'pending'",
            ['id' => $invitationId, 'cancel_status' => $cancelStatus]
        );

        $rowCount = method_exists($stmt, 'rowCount') ? (int) $stmt->rowCount() : 1;
        if ($rowCount > 0) {
            ActivityLogger::log('registration.invitation_cancelled', [
                'invitation_id' => $invitationId,
                'admin_user_id' => $adminUserId,
            ]);
        }
    }

    /**
     * Resend a pending invitation. Refuses to resend non-pending invitations
     * (already cancelled, completed, or expired) — closes the loophole where
     * a forged POST could resurrect a cancelled invitation.
     */
    public function resend(int $invitationId, int $adminUserId): void {
        $row = $this->db->fetchOne(
            'SELECT email, status FROM user_invitations WHERE id = :id LIMIT 1',
            ['id' => $invitationId]
        );
        if ($row === false) {
            throw new RuntimeException('Invitation not found.');
        }

        $status = (string) ($row['status'] ?? '');
        if ($status !== 'pending') {
            throw new InvitationNotPendingException(
                'Only pending invitations can be resent. Send a new invitation instead.'
            );
        }

        $email = (string) $row['email'];
        $cancelStatus = $this->statusEnumSupports('cancelled') ? 'cancelled' : 'expired';
        $this->db->query(
            "UPDATE user_invitations
             SET status = :cancel_status, expires_at = NOW()
             WHERE id = :id",
            ['id' => $invitationId, 'cancel_status' => $cancelStatus]
        );

        $this->send($email, $adminUserId);
    }

    /**
     * Returns invitations for the admin list. Does NOT include the raw token
     * column — preventing accidental token disclosure if a future template
     * change echoes the array.
     */
    public function getPendingList(): array {
        $rows = $this->db->fetchAll(
            'SELECT id, email, status, expires_at, created_at, completed_at
             FROM user_invitations
             ORDER BY created_at DESC'
        );

        $now = time();
        foreach ($rows as &$row) {
            if ($row['status'] === 'pending' && strtotime((string) $row['expires_at']) < $now) {
                $row['computed_status'] = 'expired';
            } else {
                $row['computed_status'] = $row['status'];
            }
        }
        unset($row);

        return $rows;
    }

    private function emailExistsInUsers(string $email): bool {
        return $this->db->fetchOne(
            'SELECT id FROM users WHERE email = :email LIMIT 1',
            ['email' => $email]
        ) !== false;
    }

    private function defaultUserRoleId(): int {
        $row = $this->db->fetchOne(
            "SELECT id FROM roles WHERE name = 'user' LIMIT 1"
        );
        return (int) ($row['id'] ?? 1);
    }

    /**
     * Check whether the user_invitations.status enum includes the given value.
     * Cached per-process. Used to decide between 'cancelled' (post-migration 009)
     * and 'expired' (legacy schema) for cancellation writes.
     */
    private function statusEnumSupports(string $value): bool {
        static $cache = [];
        if (isset($cache[$value])) {
            return $cache[$value];
        }

        try {
            $row = $this->db->fetchOne(
                "SHOW COLUMNS FROM user_invitations LIKE :col",
                ['col' => 'status']
            );
            $type = is_array($row) ? (string) ($row['Type'] ?? '') : '';
            $cache[$value] = ($type !== '' && stripos($type, "'{$value}'") !== false);
        } catch (Throwable $e) {
            $cache[$value] = false;
        }
        return $cache[$value];
    }

    /**
     * Build a fully-qualified invitation URL. Refuses to fall back to
     * $_SERVER['HTTP_HOST'] (host-header injection vector). When APP_URL is
     * unset, returns a path-only URL — operators will notice broken links
     * and configure APP_URL.
     */
    private function buildInvitationUrl(string $token): string {
        $appUrl = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
        $path = '/coaches/register.php?token=' . urlencode($token);
        if ($appUrl !== '') {
            return $appUrl . $path;
        }

        if (class_exists('Logger')) {
            Logger::warn('APP_URL not configured; invitation URL will not include scheme/host.');
        }
        return $path;
    }

    /**
     * Send invitation email to the supplied address using the explicit-recipient
     * EmailService path. Falls back to legacy triggerNotification only if the
     * newer method is unavailable.
     */
    private function sendInvitationEmail(string $toEmail, string $invitationUrl, string $expiresAt): bool {
        $context = [
            'email' => $toEmail,
            'invitation_link' => $invitationUrl,
            'expires_at' => $expiresAt,
        ];

        if (method_exists($this->emailService, 'triggerNotificationToAddress')) {
            return (bool) $this->emailService->triggerNotificationToAddress(
                'registration_invitation',
                $toEmail,
                $context
            );
        }

        if (method_exists($this->emailService, 'triggerNotification')) {
            Logger::warn('EmailService missing triggerNotificationToAddress; using legacy triggerNotification', [
                'template' => 'registration_invitation',
            ]);
            return (bool) $this->emailService->triggerNotification('registration_invitation', $context);
        }

        throw new RuntimeException('Email service unavailable for invitations.');
    }
}
?>
