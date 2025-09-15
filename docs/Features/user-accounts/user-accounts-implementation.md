# User Accounts and Permissions System - Technical Implementation

## Implementation Summary

- **Authentication System**: PHP-based session authentication with secure cookie handling
- **Database Structure**: MySQL tables for users, roles, permissions, and team relationships
- **Security**: Password hashing using PHP's password_hash() with bcrypt
- **User Interface**: Mobile-responsive forms for registration, login, and management
- **Email Integration**: PHPMailer for notification delivery with HTML templates
- **Technology Compatibility**: PHP 8.0+, MySQL 5.7+, shared hosting compatible

## Phased Implementation Strategy

### Overview

This implementation follows a phased approach that preserves the existing shared authentication system (`admin_users` and `coaches_password`) while gradually introducing the new user accounts system. This ensures zero disruption to current operations while allowing for a smooth transition.

### Phase 1: Foundation & Database Setup (Weeks 1-2)
**Goal**: Establish new user accounts infrastructure without disrupting existing functionality

#### Database Schema Creation
- Create all new user accounts tables (`users`, `roles`, `permissions`, `role_permissions`, `team_owners`, `team_officials`, `role_change_requests`, `user_invitations`, `user_activity_log`)
- Add email templates table for notifications
- **Preserve existing tables**: Keep `admin_users` and `settings` tables completely intact
- Add migration tracking table to monitor data migration progress

#### Core Authentication Classes
- Implement new authentication classes alongside existing system
- Create `UserAccountManager` class for new user operations
- Implement `LegacyAuthManager` wrapper for existing authentication
- Build unified `AuthService` that can handle both authentication methods

#### Initial Admin Account Migration
- Create administrator accounts in new system for current admin users
- Map existing admin credentials to new user accounts
- Set up initial system configuration and permissions
- **Keep existing admin login functional** during transition

### Phase 2: Parallel Authentication System (Weeks 3-4)
**Goal**: Run both authentication systems simultaneously with unified access control

#### Dual Authentication Support
```php
// includes/AuthService.php - Unified authentication service
class AuthService {
    private $legacyAuth;
    private $userAccountManager;
    
    public function authenticate($username, $password) {
        // Try new user accounts system first
        $user = $this->userAccountManager->authenticate($username, $password);
        if ($user) {
            return $this->createSession($user, 'new_system');
        }
        
        // Fall back to legacy system
        $legacyUser = $this->legacyAuth->authenticate($username, $password);
        if ($legacyUser) {
            return $this->createSession($legacyUser, 'legacy_system');
        }
        
        return false;
    }
    
    public function hasPermission($permission) {
        // Check permissions based on current authentication method
        if ($_SESSION['auth_method'] === 'new_system') {
            return $this->userAccountManager->hasPermission($permission);
        } else {
            return $this->legacyAuth->hasPermission($permission);
        }
    }
}
```

#### User Registration Workflow
- Implement invitation-based registration system
- Create registration forms with email verification
- Build admin interface for sending invitations
- **Maintain existing coach access** through legacy system

#### Team Owner Migration
- Identify current team managers from existing data
- Create Team Owner accounts with appropriate team ownership
- Link to existing team records
- **Preserve existing coach functionality** during transition

### Phase 3: Feature Integration (Weeks 5-6)
**Goal**: Integrate new user accounts with existing features while maintaining backward compatibility

#### Permission System Integration
- Implement role-based access control throughout application
- Create permission checks that work with both authentication methods
- Add team-specific access controls
- **Ensure existing features continue working** with legacy authentication

#### User Management Interface
- Build admin dashboard for managing both user types
- Create user listing with legacy/new system indicators
- Implement account migration tools for administrators
- **Maintain existing admin functionality** during transition

#### Email Notification System
- Implement comprehensive email notification system
- Create templates for all user account events
- Set up email logging and delivery tracking
- **Preserve existing email functionality** for legacy users

### Phase 4: Gradual Migration (Weeks 7-8)
**Goal**: Encourage migration to new system while maintaining full backward compatibility

#### Migration Tools
- Create self-service migration tools for coaches
- Build admin tools for bulk account migration
- Implement data mapping and validation
- **Keep legacy system fully functional** for non-migrated users

#### User Communication
- Send migration notifications to existing users
- Provide clear instructions for account creation
- Offer support during transition period
- **Maintain existing user experience** for those who haven't migrated

#### Monitoring and Analytics
- Track migration progress and user adoption
- Monitor system performance with dual authentication
- Identify users who haven't migrated
- **Ensure no disruption** to existing workflows

### Phase 5: Legacy System Deprecation (Weeks 9-10)
**Goal**: Sunset legacy authentication system after successful migration

#### Final Migration Push
- Send final migration notices to remaining users
- Provide extended support for migration process
- Create admin tools for forced migration if needed
- **Maintain data integrity** throughout final migration

#### Legacy System Removal
- Remove legacy authentication code
- Clean up unused database tables
- Update all references to use new system
- **Ensure complete functionality** with new system only

#### System Optimization
- Optimize performance with single authentication system
- Clean up dual authentication code
- Finalize security hardening
- **Verify all features work correctly** with new system

### Implementation Benefits

1. **Zero Disruption**: Existing users can continue using the system without any changes
2. **Gradual Transition**: Users can migrate at their own pace
3. **Data Preservation**: All existing data and relationships are maintained
4. **Rollback Capability**: Can revert to legacy system if issues arise
5. **Testing Safety**: New system can be thoroughly tested before full deployment

### Migration Tracking

```sql
-- Migration tracking table
CREATE TABLE migration_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('admin', 'coach') NOT NULL,
    legacy_identifier VARCHAR(100) NOT NULL,
    new_user_id INT NULL,
    migration_status ENUM('pending', 'in_progress', 'completed', 'failed') DEFAULT 'pending',
    migrated_at DATETIME NULL,
    migration_notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (new_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Success Criteria

- [ ] All existing functionality continues to work with legacy authentication
- [ ] New user accounts system is fully functional
- [ ] Users can migrate seamlessly between systems
- [ ] No data loss during migration process
- [ ] Performance remains acceptable with dual authentication
- [ ] Legacy system can be cleanly removed after migration

## What to Implement First

Based on the phased approach, here's the recommended first steps:

### Immediate Priority 1: Database Schema Setup
1. **Create new user accounts tables** without modifying existing ones
2. **Add migration tracking table** to monitor progress
3. **Set up email templates table** for notifications
4. **Verify existing tables remain untouched**

### Immediate Priority 2: Core Authentication Classes
1. **Create `LegacyAuthManager` class** to wrap existing authentication
2. **Build `UserAccountManager` class** for new user operations
3. **Implement unified `AuthService`** that can handle both systems
4. **Ensure backward compatibility** with existing login flows

### Immediate Priority 3: Admin Account Migration
1. **Create administrator accounts** in new system for current admins
2. **Map existing admin credentials** to new user accounts
3. **Set up initial permissions** and role structure
4. **Test dual authentication** with admin accounts

### Immediate Priority 4: Testing Infrastructure
1. **Create comprehensive test suite** for both authentication methods
2. **Set up staging environment** with dual authentication
3. **Implement monitoring** for migration progress
4. **Verify existing functionality** remains intact

### Key Implementation Principles

1. **Never modify existing authentication code** - only add new code alongside
2. **Always test with existing users first** - ensure no disruption
3. **Maintain data integrity** throughout the process
4. **Provide rollback capability** at every phase
5. **Document all changes** for future reference

This approach ensures that the existing shared authentication system remains fully functional while gradually introducing the new user accounts feature, allowing for a smooth transition without any disruption to current operations.

## Authentication System Implementation

### Session Management

```php
// includes/auth.php - Session initialization with security settings
function initSession() {
    // Set secure session parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    
    // Enable secure flag in production with HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    
    // Custom session name to avoid fingerprinting
    session_name('d8tl_session');
    
    // Start or resume session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['created_at']) || time() - $_SESSION['created_at'] > 3600) {
        session_regenerate_id(true);
        $_SESSION['created_at'] = time();
    }
}

// User authentication verification
function isAuthenticated() {
    // Check if user is logged in
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Role-based permission check
function hasPermission($permission) {
    if (!isAuthenticated()) {
        return false;
    }
    
    // Get user's permissions from the database
    $user_id = $_SESSION['user_id'];
    $db = new Database();
    $stmt = $db->prepare("
        SELECT p.name 
        FROM permissions p
        JOIN role_permissions rp ON p.id = rp.permission_id
        JOIN users u ON u.role_id = rp.role_id
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        $permissions[] = $row['name'];
    }
    
    return in_array($permission, $permissions);
}
```

### Password Security

```php
// includes/auth.php - Password handling functions
function hashPassword($password) {
    // Use PHP's password_hash with bcrypt algorithm
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function validatePasswordStrength($password) {
    $errors = [];
    
    // Minimum 8 characters
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    
    // Must contain at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    }
    
    // Must contain at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    }
    
    // Must contain at least one number
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number.";
    }
    
    return $errors;
}

function recordFailedLoginAttempt($username) {
    $db = new Database();
    
    // Record failed attempt and check for lockout
    $stmt = $db->prepare("
        INSERT INTO login_attempts (username, ip_address, attempted_at)
        VALUES (?, ?, NOW())
    ");
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param("ss", $username, $ip);
    $stmt->execute();
    
    // Check for account lockout (5 failed attempts in 15 minutes)
    $stmt = $db->prepare("
        SELECT COUNT(*) as attempts 
        FROM login_attempts 
        WHERE username = ? 
        AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['attempts'] >= 5; // Return true if account should be locked
}
```

### CSRF Protection

```php
// includes/security.php - CSRF protection functions
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// HTML helper for adding CSRF token to forms
function csrfTokenField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

// Form validation with CSRF check
function validateForm() {
    // Check if CSRF token is valid
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        // Log potential CSRF attack
        error_log("CSRF validation failed: " . $_SERVER['REQUEST_URI']);
        die("Form submission error: Invalid security token. Please try again.");
    }
    
    // Continue with form processing
    return true;
}
```

## Email System Implementation

```php
// includes/EmailService.php - Email notification system
class EmailService {
    private $mailer;
    private $db;
    
    public function __construct() {
        $this->db = new Database();
        $this->mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Configure PHPMailer with settings from config
        $this->mailer->isSMTP();
        $this->mailer->Host = SMTP_HOST;
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = SMTP_USERNAME;
        $this->mailer->Password = SMTP_PASSWORD;
        $this->mailer->SMTPSecure = 'tls';
        $this->mailer->Port = SMTP_PORT;
        $this->mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $this->mailer->isHTML(true);
    }
    
    public function sendEmail($to, $subject, $template, $data) {
        try {
            // Prepare template with data
            $body = $this->prepareTemplate($template, $data);
            
            // Set email details
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            
            // Send email
            $result = $this->mailer->send();
            
            // Log email
            $this->logEmail($to, $subject, $template, $result);
            
            return $result;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function prepareTemplate($template, $data) {
        // Get template content from database
        $stmt = $this->db->prepare("SELECT subject, body FROM email_templates WHERE name = ?");
        $stmt->bind_param("s", $template);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Email template not found: $template");
        }
        
        $row = $result->fetch_assoc();
        $body = $row['body'];
        
        // Replace variables in template with actual data
        foreach ($data as $key => $value) {
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }
        
        return $body;
    }
    
    private function logEmail($to, $subject, $template, $success) {
        $stmt = $this->db->prepare("
            INSERT INTO email_log (recipient, subject, template, status, sent_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $status = $success ? 'sent' : 'failed';
        $stmt->bind_param("ssss", $to, $subject, $template, $status);
        $stmt->execute();
    }
}
```

### Email Template System Database Schema

```sql
-- Email templates table structure
CREATE TABLE email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email log table
CREATE TABLE email_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    template VARCHAR(100) NOT NULL,
    status ENUM('sent', 'failed') NOT NULL,
    sent_at DATETIME NOT NULL,
    INDEX (template),
    INDEX (status),
    INDEX (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Email Usage Example

```php
// Example usage for user invitation
$emailService = new EmailService();
$data = [
    'recipient_name' => 'John Doe',
    'invitation_link' => 'https://district8travelleague.com/register?token=abc123def456',
    'expiration_date' => date('F j, Y', strtotime('+14 days')),
    'admin_name' => 'Admin User'
];
$emailService->sendEmail('john.doe@example.com', 'Invitation to District 8 Travel League', 'user_invitation', $data);
```

## Team Relationship Management Implementation

```php
// includes/TeamRelationshipManager.php - Team relationship management class
class TeamRelationshipManager {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    // Assign a user as Team Owner for a team
    public function assignTeamOwner($user_id, $team_id, $assigned_by) {
        try {
            // Check if user exists and has appropriate role
            if (!$this->validateUserForRole($user_id, 'team_owner')) {
                return ['success' => false, 'message' => 'User does not have Team Owner role'];
            }
            
            // Check if team exists and is available for ownership
            if (!$this->isTeamAvailable($team_id)) {
                return ['success' => false, 'message' => 'Team is not available or does not exist'];
            }
            
            // Add team owner record
            $stmt = $this->db->prepare("
                INSERT INTO team_owners (user_id, team_id, assigned_by, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->bind_param("iii", $user_id, $team_id, $assigned_by);
            $stmt->execute();
            
            // Log the activity
            $this->logTeamAssignment($user_id, $team_id, $assigned_by, 'team_owner');
            
            return ['success' => true, 'message' => 'Team owner assigned successfully'];
        } catch (Exception $e) {
            error_log("Team owner assignment failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    // Assign a user as Team Official for a team
    public function assignTeamOfficial($user_id, $team_id, $assigned_by) {
        try {
            // Check if user exists and has appropriate role
            if (!$this->validateUserForRole($user_id, 'team_official')) {
                return ['success' => false, 'message' => 'User does not have Team Official role'];
            }
            
            // Check if team exists 
            if (!$this->teamExists($team_id)) {
                return ['success' => false, 'message' => 'Team does not exist'];
            }
            
            // Add team official record
            $stmt = $this->db->prepare("
                INSERT INTO team_officials (user_id, team_id, assigned_by, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->bind_param("iii", $user_id, $team_id, $assigned_by);
            $stmt->execute();
            
            // Log the activity
            $this->logTeamAssignment($user_id, $team_id, $assigned_by, 'team_official');
            
            return ['success' => true, 'message' => 'Team official assigned successfully'];
        } catch (Exception $e) {
            error_log("Team official assignment failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    // Request role change (Team Owner or Team Official)
    public function requestRoleChange($user_id, $requested_role, $team_id = null) {
        try {
            // Validate request data
            if (!in_array($requested_role, ['team_owner', 'team_official'])) {
                return ['success' => false, 'message' => 'Invalid role requested'];
            }
            
            // If requesting Team Official role, team_id is required
            if ($requested_role === 'team_official' && $team_id === null) {
                return ['success' => false, 'message' => 'Team ID is required for Team Official role'];
            }
            
            // Check if team exists when team_id is provided
            if ($team_id !== null && !$this->teamExists($team_id)) {
                return ['success' => false, 'message' => 'Team does not exist'];
            }
            
            // Check for existing pending requests
            $stmt = $this->db->prepare("
                SELECT id FROM role_change_requests 
                WHERE user_id = ? 
                AND requested_role = ? 
                AND status = 'pending'
                AND (team_id = ? OR team_id IS NULL)
            ");
            $stmt->bind_param("isi", $user_id, $requested_role, $team_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                return ['success' => false, 'message' => 'A pending request already exists'];
            }
            
            // Create new request
            $stmt = $this->db->prepare("
                INSERT INTO role_change_requests 
                (user_id, requested_role, team_id, status, requested_at)
                VALUES (?, ?, ?, 'pending', NOW())
            ");
            $stmt->bind_param("isi", $user_id, $requested_role, $team_id);
            $stmt->execute();
            $request_id = $stmt->insert_id;
            
            // Notify administrators of new request
            $this->notifyAdminsOfRoleRequest($request_id, $user_id, $requested_role, $team_id);
            
            return [
                'success' => true, 
                'message' => 'Role change request submitted successfully',
                'request_id' => $request_id
            ];
        } catch (Exception $e) {
            error_log("Role change request failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    // Process role change request (approve/reject)
    public function processRoleChangeRequest($request_id, $status, $admin_id, $notes = null) {
        try {
            // Validate status
            if (!in_array($status, ['approved', 'rejected'])) {
                return ['success' => false, 'message' => 'Invalid status'];
            }
            
            // Get request details
            $stmt = $this->db->prepare("
                SELECT user_id, requested_role, team_id 
                FROM role_change_requests 
                WHERE id = ? AND status = 'pending'
            ");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return ['success' => false, 'message' => 'Request not found or already processed'];
            }
            
            $request = $result->fetch_assoc();
            $user_id = $request['user_id'];
            $requested_role = $request['requested_role'];
            $team_id = $request['team_id'];
            
            // Update request status
            $stmt = $this->db->prepare("
                UPDATE role_change_requests
                SET status = ?, processed_by = ?, processed_at = NOW(), notes = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sisi", $status, $admin_id, $notes, $request_id);
            $stmt->execute();
            
            // If approved, update user role and team relationship
            if ($status === 'approved') {
                // Update user role
                $role_id = $this->getRoleId($requested_role);
                $this->updateUserRole($user_id, $role_id);
                
                // Create team relationship if applicable
                if ($requested_role === 'team_owner' && $team_id !== null) {
                    $this->assignTeamOwner($user_id, $team_id, $admin_id);
                } elseif ($requested_role === 'team_official' && $team_id !== null) {
                    $this->assignTeamOfficial($user_id, $team_id, $admin_id);
                }
            }
            
            // Notify user of decision
            $this->notifyUserOfRoleDecision($user_id, $requested_role, $status, $team_id, $notes);
            
            return [
                'success' => true,
                'message' => "Role change request {$status}",
                'request_id' => $request_id
            ];
        } catch (Exception $e) {
            error_log("Process role change failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    // Helper methods
    private function validateUserForRole($user_id, $role) {
        // Implementation for validating user for role
        return true; // Simplified for example
    }
    
    private function isTeamAvailable($team_id) {
        // Check if team exists and doesn't have an owner
        $stmt = $this->db->prepare("
            SELECT t.id 
            FROM teams t
            LEFT JOIN team_owners o ON t.id = o.team_id
            WHERE t.id = ? AND o.user_id IS NULL
        ");
        $stmt->bind_param("i", $team_id);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
    
    private function teamExists($team_id) {
        $stmt = $this->db->prepare("SELECT id FROM teams WHERE id = ?");
        $stmt->bind_param("i", $team_id);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
    
    private function logTeamAssignment($user_id, $team_id, $assigned_by, $role_type) {
        // Log to user_activity_log table
    }
    
    private function notifyAdminsOfRoleRequest($request_id, $user_id, $requested_role, $team_id) {
        // Email notification implementation
    }
    
    private function notifyUserOfRoleDecision($user_id, $requested_role, $status, $team_id, $notes) {
        // Email notification implementation
    }
    
    private function getRoleId($role_name) {
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE name = ?");
        $stmt->bind_param("s", $role_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row ? $row['id'] : null;
    }
    
    private function updateUserRole($user_id, $role_id) {
        $stmt = $this->db->prepare("UPDATE users SET role_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $role_id, $user_id);
        $stmt->execute();
    }
}
```

## Login/Authentication Implementation Example

```php
// public/login.php - Login form processing
<?php
require_once '../includes/bootstrap.php';
require_once '../includes/auth.php';

// Initialize session
initSession();

// Check if user is already logged in
if (isAuthenticated()) {
    // Redirect to appropriate dashboard based on role
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) && $_POST['remember'] === '1';
        
        // Basic validation
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            // Database query
            $db = new Database();
            $stmt = $db->prepare("
                SELECT id, username, password_hash, role_id, status
                FROM users
                WHERE (username = ? OR email = ?) AND status = 'active'
            ");
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Verify password
                if (verifyPassword($password, $user['password_hash'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['created_at'] = time();
                    
                    // Set remember-me cookie if requested
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        $expires = time() + (30 * 24 * 60 * 60); // 30 days
                        
                        // Store token in database
                        $stmt = $db->prepare("
                            INSERT INTO remember_tokens (user_id, token, expires_at)
                            VALUES (?, ?, FROM_UNIXTIME(?))
                        ");
                        $stmt->bind_param("isi", $user['id'], $token, $expires);
                        $stmt->execute();
                        
                        // Set cookie
                        setcookie('remember_token', $token, $expires, '/', '', true, true);
                    }
                    
                    // Log successful login
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $stmt = $db->prepare("
                        INSERT INTO user_activity_log (user_id, ip_address, action, details)
                        VALUES (?, ?, 'login', 'Successful login')
                    ");
                    $stmt->bind_param("is", $user['id'], $ip);
                    $stmt->execute();
                    
                    // Redirect to dashboard
                    header('Location: dashboard.php');
                    exit;
                } else {
                    // Record failed login attempt
                    recordFailedLoginAttempt($username);
                    $error = 'Invalid username or password.';
                }
            } else {
                // Record failed login attempt even if username doesn't exist
                recordFailedLoginAttempt($username);
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - District 8 Travel League</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-form">
            <h1>Login</h1>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?= csrfTokenField() ?>
                
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-check">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    <label for="remember">Remember me</label>
                </div>
                
                <button type="submit" class="btn btn-primary">Login</button>
                
                <p class="mt-3">
                    <a href="forgot-password.php">Forgot your password?</a>
                </p>
            </form>
        </div>
    </div>
    
    <script src="assets/js/validation.js"></script>
</body>
</html>
```

## Future Enhancements

### Two-Factor Authentication

The authentication system can be extended to include two-factor authentication for administrators and other privileged users:

```php
// Two-factor authentication implementation (pseudocode)
function setupTwoFactorAuth($user_id) {
    // Generate secret key
    $secret = generateSecretKey();
    
    // Store in database
    $db = new Database();
    $stmt = $db->prepare("UPDATE users SET two_factor_secret = ? WHERE id = ?");
    $stmt->bind_param("si", $secret, $user_id);
    $stmt->execute();
    
    // Return QR code URL for apps like Google Authenticator
    return generateQRCodeUrl($secret);
}

function verifyTwoFactorCode($user_id, $code) {
    // Fetch user's secret
    $db = new Database();
    $stmt = $db->prepare("SELECT two_factor_secret FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    // Verify the provided code against the secret
    return verifyTOTPCode($user['two_factor_secret'], $code);
}
```

### API Authentication with JWT

For future API access, JWT (JSON Web Tokens) could be implemented:

```php
// JWT authentication implementation (pseudocode)
function generateJWT($user_id, $expiry = 3600) {
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    
    $payload = [
        'sub' => $user_id,
        'iat' => time(),
        'exp' => time() + $expiry
    ];
    $payload = base64_encode(json_encode($payload));
    
    $signature = hash_hmac('sha256', "$header.$payload", JWT_SECRET, true);
    $signature = base64_encode($signature);
    
    return "$header.$payload.$signature";
}

function validateJWT($token) {
    list($header, $payload, $signature) = explode('.', $token);
    
    $valid_signature = hash_hmac('sha256', "$header.$payload", JWT_SECRET, true);
    $valid_signature = base64_encode($valid_signature);
    
    if (!hash_equals($signature, $valid_signature)) {
        return false;
    }
    
    $payload = json_decode(base64_decode($payload), true);
    
    // Check expiration
    if ($payload['exp'] < time()) {
        return false;
    }
    
    return $payload;
}
```
