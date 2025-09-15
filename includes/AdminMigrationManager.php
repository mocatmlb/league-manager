<?php
/**
 * District 8 Travel League - Admin Migration Manager
 * 
 * Manages migration of existing admin accounts to the new user accounts system
 */

// Prevent direct access
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

class AdminMigrationManager {
    private $db;
    private $userAccountManager;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->userAccountManager = new UserAccountManager();
    }
    
    /**
     * Migrate existing admin accounts to new user accounts system
     */
    public function migrateAdminAccounts() {
        try {
            $results = [
                'success' => true,
                'migrated' => 0,
                'skipped' => 0,
                'errors' => []
            ];
            
            // Get administrator role ID
            $adminRole = $this->db->fetchOne("SELECT id FROM roles WHERE name = 'administrator'");
            if (!$adminRole) {
                throw new Exception("Administrator role not found in roles table");
            }
            $adminRoleId = $adminRole['id'];
            
            // Get all active admin users from legacy table
            $adminUsers = $this->db->fetchAll(
                "SELECT * FROM admin_users WHERE is_active = 1 ORDER BY id"
            );
            
            foreach ($adminUsers as $admin) {
                try {
                    // Check if already migrated
                    $existingMigration = $this->db->fetchOne(
                        "SELECT * FROM migration_tracking WHERE user_type = 'admin' AND legacy_identifier = ?",
                        [$admin['username']]
                    );
                    
                    if ($existingMigration && $existingMigration['migration_status'] === 'completed') {
                        $results['skipped']++;
                        continue;
                    }
                    
                    // Check if user already exists in new system
                    $existingUser = $this->db->fetchOne(
                        "SELECT id FROM users WHERE username = ? OR email = ?",
                        [$admin['username'], $admin['email'] ?? '']
                    );
                    
                    if ($existingUser) {
                        // Update migration tracking
                        if ($existingMigration) {
                            $this->db->update(
                                'migration_tracking',
                                [
                                    'new_user_id' => $existingUser['id'],
                                    'migration_status' => 'completed',
                                    'migrated_at' => date('Y-m-d H:i:s'),
                                    'migration_notes' => 'User already existed in new system'
                                ],
                                'id = :id',
                                ['id' => $existingMigration['id']]
                            );
                        } else {
                            $this->db->insert('migration_tracking', [
                                'user_type' => 'admin',
                                'legacy_identifier' => $admin['username'],
                                'new_user_id' => $existingUser['id'],
                                'migration_status' => 'completed',
                                'migrated_at' => date('Y-m-d H:i:s'),
                                'migration_notes' => 'User already existed in new system'
                            ]);
                        }
                        $results['skipped']++;
                        continue;
                    }
                    
                    // Create migration tracking record
                    if (!$existingMigration) {
                        $migrationId = $this->db->insert('migration_tracking', [
                            'user_type' => 'admin',
                            'legacy_identifier' => $admin['username'],
                            'migration_status' => 'in_progress'
                        ]);
                    } else {
                        $migrationId = $existingMigration['id'];
                        $this->db->update(
                            'migration_tracking',
                            ['migration_status' => 'in_progress'],
                            'id = :id',
                            ['id' => $migrationId]
                        );
                    }
                    
                    // Prepare user data for new system
                    $userData = [
                        'username' => $admin['username'],
                        'email' => $admin['email'] ?: $admin['username'] . '@district8league.com',
                        'password_hash' => $admin['password'], // Keep existing hash
                        'first_name' => $admin['first_name'] ?: 'Admin',
                        'last_name' => $admin['last_name'] ?: 'User',
                        'phone' => '(000) 000-0000', // Default phone
                        'role_id' => $adminRoleId,
                        'status' => 'active'
                    ];
                    
                    // Insert directly into users table (bypass createUser validation for migration)
                    $newUserId = $this->db->insert('users', $userData);
                    
                    // Update migration tracking
                    $this->db->update(
                        'migration_tracking',
                        [
                            'new_user_id' => $newUserId,
                            'migration_status' => 'completed',
                            'migrated_at' => date('Y-m-d H:i:s'),
                            'migration_notes' => 'Successfully migrated from admin_users table'
                        ],
                        'id = :id',
                        ['id' => $migrationId]
                    );
                    
                    $results['migrated']++;
                    
                    Logger::info("Admin account migrated", [
                        'legacy_username' => $admin['username'],
                        'new_user_id' => $newUserId
                    ]);
                    
                } catch (Exception $e) {
                    $results['errors'][] = "Failed to migrate admin '{$admin['username']}': " . $e->getMessage();
                    
                    // Update migration tracking with error
                    if (isset($migrationId)) {
                        $this->db->update(
                            'migration_tracking',
                            [
                                'migration_status' => 'failed',
                                'migration_notes' => 'Migration failed: ' . $e->getMessage()
                            ],
                            'id = :id',
                            ['id' => $migrationId]
                        );
                    }
                    
                    Logger::error("Admin migration failed", [
                        'username' => $admin['username'],
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            Logger::error("Admin migration process failed", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'migrated' => 0,
                'skipped' => 0,
                'errors' => []
            ];
        }
    }
    
    /**
     * Get migration status for all admin accounts
     */
    public function getMigrationStatus() {
        $status = [
            'total_admins' => 0,
            'migrated' => 0,
            'pending' => 0,
            'failed' => 0,
            'details' => []
        ];
        
        // Get all admin users
        $adminUsers = $this->db->fetchAll(
            "SELECT * FROM admin_users WHERE is_active = 1 ORDER BY username"
        );
        $status['total_admins'] = count($adminUsers);
        
        foreach ($adminUsers as $admin) {
            $migration = $this->db->fetchOne(
                "SELECT mt.*, u.username as new_username 
                 FROM migration_tracking mt 
                 LEFT JOIN users u ON mt.new_user_id = u.id 
                 WHERE mt.user_type = 'admin' AND mt.legacy_identifier = ?",
                [$admin['username']]
            );
            
            $detail = [
                'legacy_username' => $admin['username'],
                'legacy_email' => $admin['email'],
                'status' => $migration ? $migration['migration_status'] : 'pending',
                'migrated_at' => $migration ? $migration['migrated_at'] : null,
                'new_username' => $migration ? $migration['new_username'] : null,
                'notes' => $migration ? $migration['migration_notes'] : null
            ];
            
            $status['details'][] = $detail;
            
            switch ($detail['status']) {
                case 'completed':
                    $status['migrated']++;
                    break;
                case 'failed':
                    $status['failed']++;
                    break;
                default:
                    $status['pending']++;
                    break;
            }
        }
        
        return $status;
    }
    
    /**
     * Create initial administrator account in new system
     */
    public function createInitialAdmin($username, $email, $password, $firstName, $lastName) {
        try {
            // Get administrator role ID
            $adminRole = $this->db->fetchOne("SELECT id FROM roles WHERE name = 'administrator'");
            if (!$adminRole) {
                throw new Exception("Administrator role not found");
            }
            
            // Check if admin already exists
            $existing = $this->db->fetchOne(
                "SELECT id FROM users WHERE username = ? OR email = ?",
                [$username, $email]
            );
            
            if ($existing) {
                throw new Exception("Admin user already exists");
            }
            
            // Create admin user
            $userData = [
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => '(000) 000-0000',
                'role_id' => $adminRole['id'],
                'status' => 'active'
            ];
            
            $userId = $this->userAccountManager->createUser($userData);
            
            Logger::info("Initial admin account created", [
                'user_id' => $userId,
                'username' => $username
            ]);
            
            return $userId;
            
        } catch (Exception $e) {
            Logger::error("Failed to create initial admin", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Verify migration integrity
     */
    public function verifyMigration() {
        $issues = [];
        
        // Check that all active admin users have migration records
        $adminUsers = $this->db->fetchAll(
            "SELECT username FROM admin_users WHERE is_active = 1"
        );
        
        foreach ($adminUsers as $admin) {
            $migration = $this->db->fetchOne(
                "SELECT * FROM migration_tracking WHERE user_type = 'admin' AND legacy_identifier = ?",
                [$admin['username']]
            );
            
            if (!$migration) {
                $issues[] = "Admin '{$admin['username']}' has no migration record";
            } elseif ($migration['migration_status'] === 'completed' && !$migration['new_user_id']) {
                $issues[] = "Admin '{$admin['username']}' marked as migrated but no new user ID";
            } elseif ($migration['new_user_id']) {
                // Verify new user exists
                $newUser = $this->db->fetchOne(
                    "SELECT * FROM users WHERE id = ?",
                    [$migration['new_user_id']]
                );
                
                if (!$newUser) {
                    $issues[] = "Admin '{$admin['username']}' migration points to non-existent user ID {$migration['new_user_id']}";
                }
            }
        }
        
        return $issues;
    }
    
    /**
     * Rollback admin migration (for testing/emergency)
     */
    public function rollbackMigration($username) {
        try {
            $migration = $this->db->fetchOne(
                "SELECT * FROM migration_tracking WHERE user_type = 'admin' AND legacy_identifier = ?",
                [$username]
            );
            
            if (!$migration) {
                throw new Exception("No migration record found for admin '$username'");
            }
            
            if ($migration['new_user_id']) {
                // Delete the migrated user account
                $this->db->delete('users', 'id = ?', [$migration['new_user_id']]);
            }
            
            // Delete migration record
            $this->db->delete('migration_tracking', 'id = ?', [$migration['id']]);
            
            Logger::info("Admin migration rolled back", ['username' => $username]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error("Failed to rollback admin migration", [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
