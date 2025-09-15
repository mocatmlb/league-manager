# User Accounts and Permissions System

## Overview

The User Accounts and Permissions System replaces the current three-tier access system (Public/Coach/Admin) with a comprehensive user management system featuring individual user accounts, role-based permissions, and an invitation-based registration workflow. This system enhances security, improves user management, and provides fine-grained access control to league resources.

## Business Requirements

### Core Requirements

1. **Individual User Accounts**: Replace shared password system with personal user accounts
2. **Role-Based Access Control**: Define specific roles with customizable permissions
3. **Invitation-Based Registration**: Admin-controlled user registration process
4. **Team Ownership**: Associate users with specific teams they manage
5. **User Management Interface**: Administrative tools for user management
6. **Audit Trail**: Track user activities and access events
7. **Account Recovery**: Self-service password reset functionality
8. **Profile Management**: Allow users to update their personal information

### User Experience Goals

1. **Simplified Access**: Single login for all permitted functionality
2. **Personalized Experience**: Tailored dashboard based on user role and team ownership
3. **Consistent Authentication**: Maintain sessions across all system areas
4. **Mobile Compatibility**: Responsive design for all authentication interfaces
5. **Progressive Enhancement**: Feature visibility based on user permissions

## Functional Requirements

### User Registration System

#### Invitation-Based Registration

1. **Admin-Initiated Invitations**:
   - Administrators send invitations through the admin dashboard
   - System validates email format before sending invitation
   - Duplicate invitations to the same email are prevented

2. **Invitation Process**:
   - Administrator enters email address and selects intended role 
   - System generates unique invitation URL with secure token (expiring after 14 days)
   - Invitation email sent with registration link
   - Admin can view pending invitations and resend if needed

3. **Registration Form**:
   - First Name (required)
   - Last Name (required)
   - Email Address (pre-populated from invitation, editable)
   - Cell Phone (required)
   - Username (required, unique)
   - Password (required, with complexity requirements)
   - Confirm Password (required)
   - Captcha to prevent BOTS

4. **Account Verification**:
   - User completes registration form
   - Account created with "unverified" status
   - Verification email sent automatically
   - Account activated after verification link is clicked

#### Direct Account Creation (Admin Only)

1. **Bypass Invitation Process**:
   - Administrators can create accounts directly
   - All required fields must be provided
   - Temporary password generated automatically
   - Account created with "verified" status

2. **Notification**:
   - Welcome email sent to new user with login instructions
   - Temporary password included with forced change on first login

### Role and Permission System

#### Role Hierarchy

1. **User (Default Role)**:
   - An approved system user who has not been assigned another role
   - Can change their user profile (name, email, phone, reset password)
   - Can request Team Owner role
   - Can request Team Official role for another team registered to an active season
   - Access to view public features (schedules, standings, league information)
   - Access to view leauge contact information

2. **Team Owner**:
   - Inherits all User permissions
   - Can access team registration form to register a team as a Team Owner for an *active* season
   - Can request Team Official role for another team registered to an *active* season
   - Can submit schedule change requests for any game where their team is the home or visitor team
   - Can input or edit game scores for any game where their team is the home or visitor team
   - Can requst (to administrators) an invitation be sent to external users to be a Team Official for a team owned by the Team Owner - (requires admin approval)

3. **Team Official**:
   - Inherits all User permissions
   - Can submit schedule change requests for any game where their assigned team is the home or visitor team
   - Can input or edit game scores for any game where their assigned team is the home or visitor team

4. **Administrator**:
   - Has full system access with CRUD abilities on all features
   - Has CRUD abilities on all user accounts
   - Can manually create new accounts and bypass new user account setup flow
   - Can send invitations to users to register for an account
   - Can approve/deny role change requests
   - Can manage system settings and configuration

#### Permission Framework

1. **Resource-Based Permissions**:
   - Create (e.g., create_team, create_game)
   - Read (e.g., view_schedule, view_team_contacts)
   - Update (e.g., update_game, update_score)
   - Delete (e.g., delete_team, delete_document)

2. **Role-Permission Mapping**:
   - Roles contain collections of permissions
   - Permissions are hierarchical (higher roles include lower role permissions)
   - Special permissions for sensitive operations

3. **Team-Specific Permissions**:
   - Team ownership grants additional permissions for specific teams
   - Multiple team ownership supported

### User Management Features

#### Account Administration (Admin Only)

1. **User Listing**:
   - Searchable, filterable list of all users
   - Status indicators (active, disabled, unverified)
   - Quick action buttons (edit, disable, delete)

2. **Edit User Information**:
   - Modify all user account details
   - Change assigned role
   - Update contact information

3. **Password Management**:
   - Reset passwords to temporary values
   - Send password reset emails
   - Force password change on next login

4. **Account Status Control**:
   - Disable user accounts (prevents login)
   - Re-enable disabled accounts
   - Delete accounts (with safety confirmations)

5. **Team Relationship Management**:
   - Assign users as Team Owners or Team Officials for specific teams
   - Remove team associations
   - View and manage team relationships
   - Review and approve/reject role change requests
   - View history of team assignments

#### Self-Service User Features

1. **Profile Management**:
   - View and update personal information
   - Change password
   - Update contact details
   - Set communication preferences

2. **Password Recovery**:
   - Forgotten password workflow
   - Email-based recovery with secure tokens
   - Password reset forms with validation

3. **Team Information and Role Requests**:
   - View teams owned or assigned to the user
   - Access team-specific features based on role
   - Submit requests to become Team Owner for a new team
   - Submit requests to become Team Official for an existing team
   - View status of pending role change requests

### User Activity Auditing

1. **Login Tracking**:
   - Record all login attempts (successful and failed)
   - Track login time, IP address, user agent
   - Detect suspicious login patterns

2. **Activity Logging**:
   - Log all significant user actions
   - Record CRUD operations on key entities
   - Track permission-sensitive actions
   - Document data access patterns

3. **Administrative Reports**:
   - Login history reports
   - User activity summary
   - Failed login attempts
   - Session duration analytics

## Technical Requirements

### Database Schema

#### Users Table
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    role_id INT NOT NULL,
    status ENUM('unverified', 'active', 'disabled') NOT NULL DEFAULT 'unverified',
    verification_token VARCHAR(100) NULL,
    verification_expiry DATETIME NULL,
    password_reset_token VARCHAR(100) NULL,
    password_reset_expiry DATETIME NULL,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Roles Table
```sql
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Permissions Table
```sql
CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Role Permissions Table
```sql
CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Team Relationships Tables

```sql
-- For Team Owners
CREATE TABLE team_owners (
    user_id INT NOT NULL,
    team_id INT NOT NULL,
    assigned_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, team_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- For Team Officials
CREATE TABLE team_officials (
    user_id INT NOT NULL,
    team_id INT NOT NULL,
    assigned_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, team_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- For Role Change Requests
CREATE TABLE role_change_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    requested_role VARCHAR(50) NOT NULL,
    team_id INT NULL, -- NULL if requesting Team Owner, NOT NULL if requesting Team Official
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    processed_by INT NULL,
    notes TEXT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### User Invitations Table
```sql
CREATE TABLE user_invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    role_id INT NOT NULL,
    invited_by INT NOT NULL,
    status ENUM('pending', 'completed', 'expired') NOT NULL DEFAULT 'pending',
    expires_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (invited_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### User Activity Log Table
```sql
CREATE TABLE user_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    details TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Authentication System

#### Session Management
- PHP session-based authentication with secure configuration
- Session timeout based on activity and absolute time limits
- Secure cookie handling with HTTPOnly and Secure flags
- Session regeneration on privilege level changes
- Remember-me functionality with secure tokens

#### Password Security
- Password hashing using PHP's password_hash() with bcrypt
- Configurable password complexity requirements
- Password age and history tracking
- Forced password change for temporary passwords
- Account lockout after failed login attempts

#### CSRF Protection
- Token-based CSRF protection for all forms
- Per-session token generation
- Validation on all state-changing operations

### Email Notifications

#### Notification Types

1. **onUserInvitation**
   - Trigger: Administrator sends user invitation
   - Recipients: Invited user email address
   - Content: Welcome message, registration link, expiration information

2. **onUserRegistration**
   - Trigger: User completes registration form
   - Recipients: Administrators
   - Content: New user details, account status, verification status

3. **onPasswordReset**
   - Trigger: User requests password reset
   - Recipients: User's email address
   - Content: Password reset instructions and secure link

4. **onAccountStatusChange**
   - Trigger: Admin changes user account status
   - Recipients: Affected user
   - Content: Notification of account status change and reason
   
5. **onRoleChangeRequest**
   - Trigger: User submits a request to become Team Owner or Team Official
   - Recipients: Administrators
   - Content: Request details, user information, team information (if applicable)

6. **onRoleChangeProcessed**
   - Trigger: Administrator approves or rejects a role change request
   - Recipients: User who made the request
   - Content: Decision outcome, next steps (if approved), reason (if rejected)

7. **onTeamOfficialInvitation**
   - Trigger: Team Owner invites a user to be a Team Official
   - Recipients: Invited user
   - Content: Invitation details, team information, acceptance/rejection links

### Security Measures

1. **Input Validation**
   - Server-side validation of all form inputs
   - Sanitization of user-provided data
   - Protection against XSS and injection attacks

2. **Access Control**
   - Permission checks before all sensitive operations
   - Team-specific access controls
   - IP-based suspicious activity detection

3. **Data Protection**
   - Encryption of sensitive data
   - Secure handling of personally identifiable information
   - Compliance with data protection regulations

4. **Audit Logging**
   - Comprehensive logging of security events
   - Failed login attempt tracking
   - Privilege escalation monitoring

## User Interface Requirements

### Authentication Screens

1. **Login Page**
   - Username/email and password fields
   - Remember me option
   - Forgotten password link
   - Error messages for failed attempts
   - Redirect to appropriate dashboard based on role

2. **Registration Form**
   - Fields for all required user information
   - Password strength meter
   - Clear validation messages
   - Terms of service acceptance

3. **Password Reset**
   - Email input for password reset request
   - Secure token validation
   - New password entry with confirmation
   - Success confirmation

### User Management Interfaces

1. **User Listing (Admin)**
   - Sortable, filterable table of users
   - Status indicators and quick filters
   - Pagination for large user bases
   - Quick action buttons

2. **User Detail/Edit (Admin)**
   - Complete user information form
   - Role selection dropdown
   - Team ownership management
   - Account status controls

3. **Invitation Management (Admin)**
   - Form to send new invitations
   - List of pending invitations
   - Resend/cancel invitation options
   - Expiration tracking

4. **User Profile (Self-service)**
   - Personal information display and edit
   - Password change form
   - Communication preferences
   - Team associations

### Dashboard Integration

1. **User-specific Dashboard**
   - Personalized welcome message
   - Quick access to owned teams
   - Role-appropriate action buttons
   - Recent activity summary

2. **Team-specific Views**
   - Filtered views based on team ownership
   - Team management actions
   - Schedule and score entry for owned teams

## Migration Strategy

### Data Migration Plan

1. **Administrator Migration**
   - Create administrator accounts for current admin users
   - Preserve admin credentials where possible
   - Set up initial system configuration

2. **Coach Migration**
   - Identify current team managers from existing data
   - Create Team Manager accounts with appropriate team ownership
   - Link to existing team records

3. **Data Mapping**
   - Map existing coach contact information to new user accounts
   - Preserve historical data attribution
   - Link historical activities to new user accounts where possible

### Transition Strategy

1. **Parallel Operation Period**
   - Run both authentication systems simultaneously during transition
   - Allow login via either method for a limited time
   - Encourage migration to new accounts

2. **User Communication**
   - Announce new system to all users
   - Provide clear instructions for account creation
   - Offer support during transition period

3. **Cutover Timeline**
   - Phase 1: Administrator accounts (Week 1)
   - Phase 2: Team Manager accounts (Weeks 2-3)
   - Phase 3: Deprecation of old system (Week 4)
   - Phase 4: Complete cutover (Week 5)

## Implementation Phases

### Phase 1: Core Authentication System

1. **Database Setup**
   - Create user and role tables
   - Set up permission structure
   - Establish team ownership relationships

2. **Authentication Logic**
   - Implement login/logout functionality
   - Develop session management
   - Create password security measures

3. **Basic User Management**
   - Administrator account creation
   - User CRUD operations
   - Initial role assignment

### Phase 2: User Registration Workflow

1. **Invitation System**
   - Develop invitation creation and management
   - Implement email notification for invitations
   - Create secure token generation and validation

2. **Registration Forms**
   - Build user registration interface
   - Implement validation logic
   - Create email verification workflow

3. **Self-service Features**
   - Password reset functionality
   - Profile management
   - Account settings

### Phase 3: Team Ownership and Permissions

1. **Team Association**
   - Implement team ownership assignment
   - Create team-specific permission checks
   - Develop team management interfaces

2. **Role-based Access Control**
   - Fine-tune permission assignments
   - Implement permission checks throughout application
   - Create role management interface

3. **User Experience Integration**
   - Personalized dashboards
   - Role-appropriate navigation
   - Team-filtered views

### Phase 4: Audit and Reporting

1. **Activity Logging**
   - Implement comprehensive activity tracking
   - Create login history recording
   - Develop security event monitoring

2. **Administrative Reports**
   - User activity reports
   - Security audit reports
   - Team ownership reports

3. **System Optimization**
   - Performance tuning
   - Security hardening
   - UX refinement

## Testing Requirements

### Functional Testing

1. **Authentication Testing**
   - Verify login with valid credentials
   - Test login failure with invalid credentials
   - Confirm session timeout functionality
   - Validate remember-me feature
   - Test password reset workflow

2. **User Management Testing**
   - Create, read, update, delete user accounts
   - Test role assignment and permission inheritance
   - Verify team ownership assignment
   - Test account status changes

3. **Registration Flow Testing**
   - Create and send invitations
   - Complete registration process
   - Verify email verification workflow
   - Test invitation expiration

### Security Testing

1. **Access Control Testing**
   - Verify permission restrictions work correctly
   - Test team-specific access controls
   - Confirm role-based feature visibility
   - Validate administrative function security

2. **Input Validation Testing**
   - Test form validation for all user inputs
   - Attempt XSS and injection attacks
   - Verify CSRF protection works

3. **Password Security Testing**
   - Test password complexity requirements
   - Verify account lockout after failed attempts
   - Confirm secure password reset process

### Integration Testing

1. **System Integration**
   - Test authentication across all application areas
   - Verify permission checks throughout system
   - Validate session consistency across pages

2. **Email System Integration**
   - Test all email notifications
   - Verify email delivery and formatting
   - Confirm link functionality in emails

3. **Database Integration**
   - Test database integrity with user operations
   - Verify foreign key relationships
   - Validate data consistency across tables

## Acceptance Criteria

### Core Functionality

1. **Authentication**
   - Users can securely login with username/email and password
   - Sessions maintain appropriate timeout periods
   - Logout functionality works correctly

2. **User Management**
   - Administrators can create, edit, and disable user accounts
   - Users can be assigned different roles with appropriate permissions
   - Team ownership can be assigned and managed

3. **Self-service**
   - Users can update their profile information
   - Password reset functionality works end-to-end
   - Users can view their team associations

### Security Standards

1. **Authentication Security**
   - Passwords are securely hashed and stored
   - Account lockout protects against brute force
   - Session management prevents hijacking

2. **Authorization Controls**
   - Permission checks prevent unauthorized access
   - Role-based access controls work correctly
   - Team ownership limits access appropriately

3. **Audit Capability**
   - User actions are properly logged
   - Security events are recorded
   - Activity can be reviewed by administrators

### User Experience Goals

1. **Usability**
   - Login process is intuitive and efficient
   - User management interfaces are clear and functional
   - Self-service features are easily accessible

2. **Performance**
   - Authentication processes complete in under 2 seconds
   - User management operations respond within 3 seconds
   - System maintains performance with 100+ concurrent users

3. **Integration**
   - Authentication system works seamlessly with all application features
   - Permissions correctly control feature access
   - Team ownership properly filters relevant data

## Dependencies and Risks

### Technical Dependencies

1. **PHP Requirements**
   - PHP 8.0+ with session support
   - Password hashing functions
   - PDO or MySQLi for database operations

2. **Database Requirements**
   - MySQL 5.7+ or MariaDB equivalent
   - Support for foreign key constraints
   - Transaction support for critical operations

3. **Email System**
   - Functional SMTP configuration
   - PHPMailer library integration
   - Template system for email content

### Implementation Risks

1. **Data Migration Risks**
   - Loss of historical data attribution
   - Incomplete team ownership mapping
   - Disruption during transition period

2. **Security Risks**
   - Potential session management vulnerabilities
   - Password storage security concerns
   - Permission bypass possibilities

3. **User Adoption Risks**
   - Resistance to new authentication system
   - Confusion during transition period
   - Increased support requests during rollout

## Documentation Requirements

1. **User Documentation**
   - Account creation and login guides
   - Password management instructions
   - Role and permission explanations
   - Team ownership overview

2. **Administrator Documentation**
   - User management procedures
   - Invitation system instructions
   - Role and permission management
   - Audit log interpretation

3. **Technical Documentation**
   - Authentication system architecture
   - Database schema documentation
   - Security implementation details
   - API documentation for authentication endpoints