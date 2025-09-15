<?php
/**
 * District 8 Travel League - User Account Manager
 * 
 * Manages the new user accounts system with individual user accounts,
 * role-based permissions, and invitation-based registration.
 */

// Prevent direct access
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

class UserAccountManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Authenticate user with new user accounts system
     */
    public function authenticate($username, $password) {
        // Find user by username or email
        $user = $this->db->fetchOne(
            "SELECT u.*, r.name as role_name 
             FROM users u 
             JOIN roles r ON u.role_id = r.id 
             WHERE (u.username = ? OR u.email = ?) AND u.status = 'active'", 
            [$username, $username]
        );
        
        if (!$user) {
            $this->recordFailedLoginAttempt($username);
            return false;
        }
        
        // Check if account is locked due to failed attempts
        if ($this->isAccountLocked($username)) {
            Logger::warn("Login attempt on locked account", ['username' => $username]);
            return false;
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $this->recordFailedLoginAttempt($username);
            return false;
        }
        
        // Update last login
        $this->db->update(
            'users',
            [
                'last_login_at' => date('Y-m-d H:i:s'),
                'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? null
            ],
            'id = :user_id',
            ['user_id' => $user['id']]
        );
        
        // Record successful login
        $this->recordSuccessfulLogin($user['id']);
        
        Logger::info("User login successful", [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'phone' => $user['phone'],
            'role_id' => $user['role_id'],
            'role_name' => $user['role_name'],
            'type' => 'user_account',
            'auth_method' => 'new_system'
        ];
    }
    
    /**
     * Create new user account
     */
    public function createUser($userData) {
        try {
            // Validate required fields
            $required = ['username', 'email', 'password', 'first_name', 'last_name', 'phone', 'role_id'];
            foreach ($required as $field) {
                if (empty($userData[$field])) {
                    throw new Exception("Required field missing: $field");
                }
            }
            
            // Check for duplicate username/email
            $existing = $this->db->fetchOne(
                "SELECT id FROM users WHERE username = ? OR email = ?",
                [$userData['username'], $userData['email']]
            );
            
            if ($existing) {
                throw new Exception("Username or email already exists");
            }
            
            // Validate password strength
            $passwordErrors = $this->validatePasswordStrength($userData['password']);
            if (!empty($passwordErrors)) {
                throw new Exception("Password validation failed: " . implode(', ', $passwordErrors));
            }
            
            // Hash password
            $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            // Prepare user data
            $insertData = [
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password_hash' => $passwordHash,
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'phone' => $userData['phone'],
                'role_id' => $userData['role_id'],
                'status' => $userData['status'] ?? 'unverified'
            ];
            
            // Generate verification token if account needs verification
            if ($insertData['status'] === 'unverified') {
                $insertData['verification_token'] = bin2hex(random_bytes(32));
                $insertData['verification_expiry'] = date('Y-m-d H:i:s', strtotime('+7 days'));
            }
            
            $userId = $this->db->insert('users', $insertData);
            
            // Log user creation
            $this->logActivity($userId, 'user_created', 'users', $userId, [
                'username' => $userData['username'],
                'role_id' => $userData['role_id']
            ]);
            
            Logger::info("User account created", [
                'user_id' => $userId,
                'username' => $userData['username'],
                'role_id' => $userData['role_id']
            ]);
            
            return $userId;
            
        } catch (Exception $e) {
            Logger::error("User creation failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($userId) {
        return $this->db->fetchOne(
            "SELECT u.*, r.name as role_name 
             FROM users u 
             JOIN roles r ON u.role_id = r.id 
             WHERE u.id = ?",
            [$userId]
        );
    }
    
    /**
     * Get user by username or email
     */
    public function getUserByUsernameOrEmail($identifier) {
        return $this->db->fetchOne(
            "SELECT u.*, r.name as role_name 
             FROM users u 
             JOIN roles r ON u.role_id = r.id 
             WHERE u.username = ? OR u.email = ?",
            [$identifier, $identifier]
        );
    }
    
    /**
     * Update user information
     */
    public function updateUser($userId, $userData) {
        try {
            $allowedFields = ['username', 'email', 'first_name', 'last_name', 'phone', 'role_id', 'status'];
            $updateData = [];
            
            foreach ($allowedFields as $field) {
                if (isset($userData[$field])) {
                    $updateData[$field] = $userData[$field];
                }
            }
            
            if (empty($updateData)) {
                throw new Exception("No valid fields to update");
            }
            
            // Check for duplicate username/email if being updated
            if (isset($updateData['username']) || isset($updateData['email'])) {
                $existing = $this->db->fetchOne(
                    "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?",
                    [$updateData['username'] ?? '', $updateData['email'] ?? '', $userId]
                );
                
                if ($existing) {
                    throw new Exception("Username or email already exists");
                }
            }
            
            $this->db->update('users', $updateData, 'id = :user_id', ['user_id' => $userId]);
            
            // Log user update
            $this->logActivity($userId, 'user_updated', 'users', $userId, $updateData);
            
            Logger::info("User account updated", ['user_id' => $userId, 'fields' => array_keys($updateData)]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error("User update failed", ['user_id' => $userId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Change user password
     */
    public function changePassword($userId, $newPassword) {
        try {
            // Validate password strength
            $passwordErrors = $this->validatePasswordStrength($newPassword);
            if (!empty($passwordErrors)) {
                throw new Exception("Password validation failed: " . implode(', ', $passwordErrors));
            }
            
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $this->db->update(
                'users',
                ['password_hash' => $passwordHash],
                'id = :user_id',
                ['user_id' => $userId]
            );
            
            // Log password change
            $this->logActivity($userId, 'password_changed', 'users', $userId);
            
            Logger::info("User password changed", ['user_id' => $userId]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error("Password change failed", ['user_id' => $userId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Check if user has specific permission
     */
    public function hasPermission($user, $permission) {
        if (!$user || $user['auth_method'] !== 'new_system') {
            return false;
        }
        
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) as count 
             FROM role_permissions rp 
             JOIN permissions p ON rp.permission_id = p.id 
             WHERE rp.role_id = ? AND p.name = ?",
            [$user['role_id'], $permission]
        );
        
        return $result && $result['count'] > 0;
    }
    
    /**
     * Get all permissions for a user
     */
    public function getUserPermissions($userId) {
        $user = $this->getUserById($userId);
        if (!$user) {
            return [];
        }
        
        $permissions = $this->db->fetchAll(
            "SELECT p.name, p.description 
             FROM permissions p 
             JOIN role_permissions rp ON p.id = rp.permission_id 
             WHERE rp.role_id = ?",
            [$user['role_id']]
        );
        
        return array_column($permissions, 'name');
    }
    
    /**
     * Validate password strength
     */
    private function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        return $errors;
    }
    
    /**
     * Record failed login attempt
     */
    private function recordFailedLoginAttempt($username) {
        $this->db->insert('login_attempts', [
            'username' => $username,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'success' => false
        ]);
    }
    
    /**
     * Record successful login
     */
    private function recordSuccessfulLogin($userId) {
        $user = $this->getUserById($userId);
        if ($user) {
            $this->db->insert('login_attempts', [
                'username' => $user['username'],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'success' => true
            ]);
        }
    }
    
    /**
     * Check if account is locked due to failed attempts
     */
    private function isAccountLocked($username) {
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) as attempts 
             FROM login_attempts 
             WHERE username = ? 
             AND success = FALSE 
             AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
            [$username]
        );
        
        return $result && $result['attempts'] >= 5;
    }
    
    /**
     * Log user activity
     */
    private function logActivity($userId, $action, $entityType = null, $entityId = null, $details = null) {
        $this->db->insert('user_activity_log', [
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details ? json_encode($details) : null
        ]);
    }
    
    /**
     * Get all users with pagination
     */
    public function getAllUsers($limit = 50, $offset = 0, $filters = []) {
        $where = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "u.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['role_id'])) {
            $where[] = "u.role_id = ?";
            $params[] = $filters['role_id'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT u.*, r.name as role_name 
                FROM users u 
                JOIN roles r ON u.role_id = r.id 
                $whereClause 
                ORDER BY u.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get total user count
     */
    public function getUserCount($filters = []) {
        $where = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['role_id'])) {
            $where[] = "role_id = ?";
            $params[] = $filters['role_id'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM users $whereClause", $params);
        return $result ? $result['count'] : 0;
    }
}
