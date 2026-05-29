<?php
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

if (!class_exists('ActivityLogger')) {
    require_once __DIR__ . '/ActivityLogger.php';
}

require_once __DIR__ . '/RegistrationService.php';

if (!class_exists('IncorrectCurrentPasswordException')) {
    class IncorrectCurrentPasswordException extends RuntimeException {}
}

class ProfileService {
    private Database $db;

    public function __construct(?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
    }

    public function updateName(int $userId, array $nameData): void {
        $firstName = trim($nameData['first_name'] ?? '');
        $lastName = trim($nameData['last_name'] ?? '');
        $preferredName = trim($nameData['preferred_name'] ?? '');

        if ($firstName === '') {
            throw new InvalidArgumentException('First name is required.');
        }
        if ($lastName === '') {
            throw new InvalidArgumentException('Last name is required.');
        }

        try {
            $this->db->beginTransaction();

            $this->db->query(
                'UPDATE users SET first_name = :first_name, last_name = :last_name, preferred_name = :preferred_name, updated_at = NOW() WHERE id = :user_id',
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'preferred_name' => $preferredName !== '' ? $preferredName : null,
                    'user_id' => $userId,
                ]
            );

            ActivityLogger::log('profile.name_updated', [
                'user_id' => $userId,
                'fields_updated' => ['first_name', 'last_name', 'preferred_name'],
            ]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updatePhone(int $userId, string $phone, string $type, string $role = 'primary'): void {
        $validTypes = ['Home', 'Work', 'Cell'];
        if (!in_array($type, $validTypes, true)) {
            throw new InvalidArgumentException("Invalid phone type: $type. Must be one of: " . implode(', ', $validTypes));
        }

        $validRoles = ['primary', 'secondary'];
        if (!in_array($role, $validRoles, true)) {
            throw new InvalidArgumentException("Invalid phone role: $role. Must be one of: " . implode(', ', $validRoles));
        }

        // Basic phone validation: non-empty and contains at least 7 digits, max 20 chars
        $numericPhone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($numericPhone) < 7 || strlen($phone) > 20) {
            throw new InvalidArgumentException('Invalid phone number format or length.');
        }

        try {
            $this->db->beginTransaction();

            $this->db->query(
                'INSERT INTO user_phones (user_id, phone, type, role, created_at, updated_at)
                 VALUES (:user_id, :phone, :type, :role, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE phone = VALUES(phone), type = VALUES(type), updated_at = NOW()',
                [
                    'user_id' => $userId,
                    'phone' => $phone,
                    'type' => $type,
                    'role' => $role,
                ]
            );

            ActivityLogger::log('profile.phone_updated', [
                'user_id' => $userId,
                'role' => $role,
            ]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateContactInfo(int $userId, string $email, string $phone, bool $smsOptIn): void {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address.');
        }

        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) !== 10) {
            throw new InvalidArgumentException('Phone number must be 10 digits.');
        }
        $formattedPhone = '(' . substr($digits, 0, 3) . ') ' . substr($digits, 3, 3) . '-' . substr($digits, 6, 4);

        try {
            $this->db->beginTransaction();

            // Check email uniqueness inside the transaction so a concurrent duplicate
            // surfaces as our friendly message rather than a raw MySQL constraint error.
            $existing = $this->db->fetchOne(
                'SELECT id FROM users WHERE email = :email AND id != :user_id',
                ['email' => $email, 'user_id' => $userId]
            );
            if ($existing) {
                $this->db->rollBack();
                throw new InvalidArgumentException('Email is already in use.');
            }

            $this->db->query(
                'UPDATE users SET email = :email, phone = :phone, sms_opt_in = :sms_opt_in, updated_at = NOW() WHERE id = :user_id',
                [
                    'email'      => $email,
                    'phone'      => $formattedPhone,
                    'sms_opt_in' => $smsOptIn ? 1 : 0,
                    'user_id'    => $userId,
                ]
            );

            ActivityLogger::log('profile.contact_updated', [
                'user_id' => $userId,
                'fields_updated' => ['email', 'phone', 'sms_opt_in'],
            ]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function removeSecondaryPhone(int $userId): void {
        try {
            $this->db->beginTransaction();

            $this->db->query(
                'DELETE FROM user_phones WHERE user_id = :user_id AND role = :role',
                ['user_id' => $userId, 'role' => 'secondary']
            );

            ActivityLogger::log('profile.phone_removed', [
                'user_id' => $userId,
                'role' => 'secondary',
            ]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): void {
        $row = $this->db->fetchOne(
            'SELECT password_hash FROM users WHERE id = :user_id',
            ['user_id' => $userId]
        );

        if (!$row) {
            throw new InvalidArgumentException('User not found');
        }

        if (!password_verify($currentPassword, $row['password_hash'])) {
            throw new IncorrectCurrentPasswordException('Current password is incorrect.');
        }

        $this->validateNewPasswordComplexity($newPassword);

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);

        try {
            $this->db->beginTransaction();

            $this->db->query(
                'UPDATE users SET password_hash = :hash, password_changed_at = NOW(), updated_at = NOW() WHERE id = :user_id',
                ['hash' => $hash, 'user_id' => $userId]
            );

            ActivityLogger::log('profile.password_changed', [
                'user_id' => $userId,
            ]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function forceSetPassword(int $userId, string $newPassword): void {
        $this->validateNewPasswordComplexity($newPassword);

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->db->query(
            'UPDATE users SET password_hash = :hash, password_changed_at = NOW(), updated_at = NOW() WHERE id = :user_id',
            ['hash' => $hash, 'user_id' => $userId]
        );

        ActivityLogger::log('profile.password_changed', [
            'user_id' => $userId,
            'forced' => true,
        ]);
    }

    private function validateNewPasswordComplexity(string $password): void {
        if (strlen($password) < 8) {
            throw new WeakPasswordException('Password must be at least 8 characters.');
        }
        if (!preg_match('/[A-Z]/', $password)) {
            throw new WeakPasswordException('Password must contain at least one uppercase letter.');
        }
        if (!preg_match('/\d/', $password)) {
            throw new WeakPasswordException('Password must contain at least one number.');
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            throw new WeakPasswordException('Password must contain at least one special character.');
        }
    }
}
