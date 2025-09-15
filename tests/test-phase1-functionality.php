<?php
/**
 * District 8 Travel League - Phase 1 Functionality Tests
 * 
 * Tests the Phase 1 user accounts system functionality
 */

define('D8TL_APP', true);

// CLI-specific bootstrap to avoid session issues
require_once __DIR__ . '/../includes/env-loader.php';
require_once EnvLoader::getPath('includes/config.php');
require_once __DIR__ . '/../includes/enums.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/Logger.php';
require_once __DIR__ . '/../includes/UserAccountManager.php';
require_once __DIR__ . '/../includes/LegacyAuthManager.php';
require_once __DIR__ . '/../includes/InvitationManager.php';
require_once __DIR__ . '/../includes/AdminMigrationManager.php';

echo "District 8 Travel League - Phase 1 Functionality Tests\n";
echo "=====================================================\n\n";

$testResults = [];
$db = Database::getInstance();

/**
 * Test helper function
 */
function runTest($testName, $testFunction) {
    global $testResults;
    
    echo "Testing: $testName... ";
    
    try {
        $result = $testFunction();
        if ($result === true) {
            echo "✓ PASS\n";
            $testResults[$testName] = 'PASS';
        } else {
            echo "✗ FAIL: $result\n";
            $testResults[$testName] = "FAIL: $result";
        }
    } catch (Exception $e) {
        echo "✗ ERROR: " . $e->getMessage() . "\n";
        $testResults[$testName] = "ERROR: " . $e->getMessage();
    }
}

// Test 1: Database Schema Verification
runTest("Database Schema Verification", function() use ($db) {
    $requiredTables = [
        'users', 'roles', 'permissions', 'role_permissions',
        'user_invitations', 'user_activity_log', 'migration_tracking'
    ];
    
    foreach ($requiredTables as $table) {
        $result = $db->fetchOne("SHOW TABLES LIKE '$table'");
        if (!$result) {
            return "Missing table: $table";
        }
    }
    
    // Check if roles are populated
    $roleCount = $db->fetchOne("SELECT COUNT(*) as count FROM roles");
    if (!$roleCount || $roleCount['count'] < 4) {
        return "Roles not properly populated";
    }
    
    return true;
});

// Test 2: Admin Migration Verification
runTest("Admin Migration Verification", function() use ($db) {
    // Check if admin user was migrated
    $migratedAdmin = $db->fetchOne(
        "SELECT u.*, r.name as role_name FROM users u 
         JOIN roles r ON u.role_id = r.id 
         WHERE u.username = 'admin'"
    );
    
    if (!$migratedAdmin) {
        return "Admin user not found in new system";
    }
    
    if ($migratedAdmin['role_name'] !== 'administrator') {
        return "Admin user does not have administrator role";
    }
    
    if ($migratedAdmin['status'] !== 'active') {
        return "Admin user is not active";
    }
    
    return true;
});

// Test 3: UserAccountManager Functionality
runTest("UserAccountManager - Create User", function() use ($db) {
    $userManager = new UserAccountManager();
    
    // Get user role ID
    $userRole = $db->fetchOne("SELECT id FROM roles WHERE name = 'user'");
    if (!$userRole) {
        return "User role not found";
    }
    
    // Create test user
    $userData = [
        'username' => 'testuser_' . time(),
        'email' => 'test_' . time() . '@example.com',
        'password' => 'TestPassword123',
        'first_name' => 'Test',
        'last_name' => 'User',
        'phone' => '(555) 123-4567',
        'role_id' => $userRole['id'],
        'status' => 'active'
    ];
    
    $userId = $userManager->createUser($userData);
    
    if (!$userId) {
        return "Failed to create user";
    }
    
    // Verify user was created
    $createdUser = $userManager->getUserById($userId);
    if (!$createdUser || $createdUser['username'] !== $userData['username']) {
        return "User not properly created";
    }
    
    // Clean up
    $db->delete('users', 'id = ?', [$userId]);
    
    return true;
});

// Test 4: Authentication System
runTest("Authentication System - Legacy Admin", function() use ($db) {
    $legacyAuth = new LegacyAuthManager();
    
    // Test admin authentication (using existing admin account)
    $admin = $legacyAuth->authenticate('admin', 'admin'); // Default password
    
    if (!$admin) {
        return "Legacy admin authentication failed";
    }
    
    if ($admin['role'] !== 'administrator') {
        return "Admin role not correctly identified";
    }
    
    return true;
});

// Test 5: Invitation System (Database Only)
runTest("Invitation System (Database)", function() use ($db) {
    // Get administrator role and user
    $adminRole = $db->fetchOne("SELECT id FROM roles WHERE name = 'administrator'");
    $adminUser = $db->fetchOne("SELECT id FROM users WHERE username = 'admin'");
    
    if (!$adminRole || !$adminUser) {
        return "Admin role or user not found";
    }
    
    // Create invitation record directly (skip email sending for test)
    $testEmail = 'invitation_test_' . time() . '@example.com';
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+14 days'));
    
    $invitationId = $db->insert('user_invitations', [
        'email' => $testEmail,
        'token' => $token,
        'role_id' => $adminRole['id'],
        'invited_by' => $adminUser['id'],
        'expires_at' => $expiresAt
    ]);
    
    if (!$invitationId) {
        return "Failed to create invitation record";
    }
    
    // Verify invitation was created
    $invitation = $db->fetchOne(
        "SELECT * FROM user_invitations WHERE email = ? AND status = 'pending'",
        [$testEmail]
    );
    
    if (!$invitation) {
        return "Invitation not found in database";
    }
    
    // Test invitation validation
    $invitationManager = new InvitationManager();
    $validation = $invitationManager->validateInvitation($invitation['token']);
    if (!$validation['valid']) {
        return "Invitation validation failed";
    }
    
    // Clean up
    $db->delete('user_invitations', 'id = ?', [$invitation['id']]);
    
    return true;
});

// Test 6: Password Security
runTest("Password Security", function() {
    $userManager = new UserAccountManager();
    
    // Test password strength validation
    $weakPasswords = ['123', 'password', 'abc123'];
    $strongPassword = 'StrongPassword123';
    
    // Use reflection to access private method
    $reflection = new ReflectionClass($userManager);
    $method = $reflection->getMethod('validatePasswordStrength');
    $method->setAccessible(true);
    
    foreach ($weakPasswords as $weakPassword) {
        $errors = $method->invoke($userManager, $weakPassword);
        if (empty($errors)) {
            return "Weak password '$weakPassword' was accepted";
        }
    }
    
    $errors = $method->invoke($userManager, $strongPassword);
    if (!empty($errors)) {
        return "Strong password was rejected: " . implode(', ', $errors);
    }
    
    return true;
});

// Test 7: Permission System
runTest("Permission System", function() use ($db) {
    $userManager = new UserAccountManager();
    
    // Get admin user
    $adminUser = $db->fetchOne(
        "SELECT u.*, r.name as role_name FROM users u 
         JOIN roles r ON u.role_id = r.id 
         WHERE u.username = 'admin'"
    );
    
    if (!$adminUser) {
        return "Admin user not found";
    }
    
    // Test admin permissions
    $adminUser['auth_method'] = 'new_system';
    if (!$userManager->hasPermission($adminUser, 'full_admin_access')) {
        return "Admin does not have full admin access permission";
    }
    
    if (!$userManager->hasPermission($adminUser, 'view_all_users')) {
        return "Admin does not have view all users permission";
    }
    
    return true;
});

// Test 8: Data Integrity
runTest("Data Integrity", function() use ($db) {
    // Check foreign key relationships
    $orphanedUsers = $db->fetchOne(
        "SELECT COUNT(*) as count FROM users u 
         LEFT JOIN roles r ON u.role_id = r.id 
         WHERE r.id IS NULL"
    );
    
    if ($orphanedUsers && $orphanedUsers['count'] > 0) {
        return "Found users with invalid role references";
    }
    
    // Check migration tracking integrity
    $invalidMigrations = $db->fetchOne(
        "SELECT COUNT(*) as count FROM migration_tracking mt 
         LEFT JOIN users u ON mt.new_user_id = u.id 
         WHERE mt.migration_status = 'completed' AND mt.new_user_id IS NOT NULL AND u.id IS NULL"
    );
    
    if ($invalidMigrations && $invalidMigrations['count'] > 0) {
        return "Found migration records pointing to non-existent users";
    }
    
    return true;
});

// Test 9: Legacy System Compatibility
runTest("Legacy System Compatibility", function() use ($db) {
    // Verify legacy tables still exist and are intact
    $legacyTables = ['admin_users', 'settings'];
    
    foreach ($legacyTables as $table) {
        $result = $db->fetchOne("SHOW TABLES LIKE '$table'");
        if (!$result) {
            return "Legacy table '$table' is missing";
        }
    }
    
    // Check admin_users table still has data
    $adminCount = $db->fetchOne("SELECT COUNT(*) as count FROM admin_users WHERE is_active = 1");
    if (!$adminCount || $adminCount['count'] === 0) {
        return "No active admin users in legacy table";
    }
    
    // Check settings table has coaches password
    $coachPassword = $db->fetchOne("SELECT * FROM settings WHERE setting_key = 'coaches_password'");
    if (!$coachPassword) {
        return "Coaches password setting not found";
    }
    
    return true;
});

echo "\n" . str_repeat("=", 50) . "\n";
echo "TEST RESULTS SUMMARY\n";
echo str_repeat("=", 50) . "\n";

$passCount = 0;
$totalCount = count($testResults);

foreach ($testResults as $testName => $result) {
    $status = strpos($result, 'PASS') === 0 ? '✓' : '✗';
    echo sprintf("  %s %-40s %s\n", $status, $testName, $result);
    
    if (strpos($result, 'PASS') === 0) {
        $passCount++;
    }
}

echo str_repeat("-", 50) . "\n";
echo sprintf("Total: %d/%d tests passed (%.1f%%)\n", $passCount, $totalCount, ($passCount / $totalCount) * 100);

if ($passCount === $totalCount) {
    echo "\n🎉 All tests passed! Phase 1 implementation is working correctly.\n";
    exit(0);
} else {
    echo "\n⚠️  Some tests failed. Please review the results above.\n";
    exit(1);
}
