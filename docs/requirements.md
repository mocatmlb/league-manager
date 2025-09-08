# District 8 Travel League - MVP Requirements

## MVP Definition

The MVP (Minimum Viable Product) represents a direct replacement of the current PHP/FormTools-based system, providing essential features needed to operate the league immediately upon deployment. The MVP focuses on core operational needs using a **PHP-based solution optimized for shared hosting environments**.

### Key MVP Principles
- **Shared Hosting Compatible**: Designed to run on standard shared hosting with cPanel
- **PHP-Native**: Built with PHP 8.0+ and MySQL for maximum compatibility
- **Direct Replacement**: Maintains all current functionality while improving user experience
- **Simple Deployment**: Easy to deploy and maintain on existing hosting infrastructure
- **Cost Effective**: No need for VPS or specialized hosting requirements

## MVP Core Features Overview

The MVP includes the following essential features with a three-tier user access system:

### Public Features (No Authentication)
1. **Public Website** - Schedule display, standings, and home page with current/upcoming games
2. **Document Access** - Links to league documents uploaded by administrators

### Coach Features (Password Protected)
3. **Schedule Change Requests** - Submit requests to modify game schedules (requires admin approval)
4. **Score Submission** - Submit game scores with immediate recording and standings update
5. **Contact Directory** - Access to league contact information

### Administrative Features (Admin Console)
6. **Complete System Management** - Full CRUD operations for all entities
7. **Dashboard with Metrics** - Key statistics and quick access to management functions
8. **Email Notification System** - Automated notifications with configurable templates
9. **Document Management** - Upload and manage public documents

## Feature 1: Program Management

### Overview
Program Management handles the creation and administration of sports programs (Junior Baseball, Senior Baseball, 50-70 Baseball). Programs serve as the top-level organizational structure in the system hierarchy.

### MVP Requirements
- **Program Creation**: Create and configure sports programs with basic information
- **Program Settings**: Name, sport type, age requirements, game format defaults
- **Program Status**: Active/Inactive status management
- **Program Listing**: Display and search active programs

### Core Data
- Program name and code (e.g., "Junior Baseball", "JR")
- Sport type (Baseball, Softball, T-Ball)
- Age requirements (min/max age)
- Default season type and game format
- Active status and creation dates

### User Interface
- Admin interface for program creation and editing
- Program listing with search and filter capabilities
- Status indicators and quick actions

## Feature 2: Season Management

### Overview
Season Management handles time-bound instances of programs with specific dates and configurations. Seasons serve as the parent level in the Program-Season-Team hierarchy.

### MVP Requirements
- **Season Creation**: Create seasons for specific programs with date ranges
- **Season Configuration**: Set registration periods, game schedules, division structure
- **Season Status**: Manage season lifecycle (Planning → Registration → Active → Completed)
- **Division Management**: Create and manage divisions within seasons

### Core Data
- Season name and program association
- Start/end dates and registration periods
- Season status and configuration
- Division structure and team assignments

### User Interface
- Season creation and editing forms
- Season dashboard with status overview
- Division management interface

## Feature 3: Team Management

### Overview
Team Management handles team registration, administration, and contact management. Teams represent the actual participating teams with their managers and organizational details.

### MVP Requirements
- **Team Registration**: Register teams for specific seasons
- **Manager Information**: Contact details and availability preferences
- **Division Assignment**: Assign teams to appropriate divisions
- **Team Directory**: Secure access to team and manager contact information

### Core Data
- Team information (league name, team name, division)
- Manager contact details (name, phone, email)
- Home field information and availability preferences
- Registration status and dates

### User Interface
- Team registration forms for managers
- Administrative team management dashboard
- Searchable team directory with contact information

## Feature 4: Game Management

### Overview
Game Management creates and administers individual games with team assignments and scoring. Games represent specific matchups between teams within a season.

### MVP Requirements
- **Game Creation**: Create games with unique game numbers and team assignments
- **Game Status**: Manage game status (Active, Completed, Cancelled)
- **Score Tracking**: Record game scores and update standings
- **Game Modification**: Edit game details with audit trail

### Core Data
- Game number (unique identifier)
- Team assignments (home/away teams)
- Game status and completion information
- Score information and submission details
- Audit trail of all game modifications

### User Interface
- Game creation and editing forms
- Game listing with status indicators
- Score input interface

## Feature 5: Schedule Management

### Overview
Schedule Management assigns date, time, and location to existing games. This feature handles schedule changes through a strict approval workflow system where changes only take effect after administrative approval.

### MVP Requirements
- **Schedule Assignment**: Assign date, time, and location to games
- **Schedule Change Workflow**: Request and approval system for schedule modifications
- **Approval-Based Updates**: Schedule changes only take effect AFTER admin approval
- **Conflict Detection**: Prevent scheduling conflicts
- **Change Tracking**: Complete audit trail of schedule changes

### Schedule Change Workflow
1. **Coach Submits Request**: Coach submits schedule change request through password-protected form
2. **Pending Status**: Change request remains in "Pending" status
3. **Original Schedule Remains**: Current schedule continues to display unchanged
4. **Admin Review**: Administrator reviews request in admin console
5. **Approval Decision**: Admin approves or denies the request
6. **Immediate Update**: Upon approval, schedule updates immediately and automatically
7. **Notification**: Email notifications sent to affected parties

### Core Data
- Schedule assignments (date, time, location)
- Schedule change requests with approval status (Pending, Approved, Denied)
- Change history and audit trail
- Conflict detection and resolution
- Approval timestamps and administrator information

### User Interface
- **Coach Interface**: Schedule change request forms (password-protected)
- **Admin Interface**: Schedule change approval/denial interface
- **Public Interface**: Always shows current approved schedule
- **Audit Interface**: Complete history of all schedule changes and approvals

## Feature 6: Score Management

### Overview
Score Management handles game score submission with immediate recording and automatic standings calculation. Unlike schedule changes, score submissions are processed immediately without requiring administrative approval.

### MVP Requirements
- **Immediate Score Recording**: Scores are recorded immediately upon submission
- **Automatic Standings Update**: Standings calculate and update immediately when scores are entered
- **Score Validation**: Basic validation and confirmation during input
- **Administrative Override**: Admins can modify scores if corrections are needed

### Score Input Workflow
1. **Coach Submits Score**: Coach enters score through password-protected form
2. **Validation**: System validates score format and game eligibility
3. **Immediate Recording**: Score is immediately saved to database
4. **Automatic Calculation**: Standings are immediately recalculated for affected teams
5. **Public Display**: Updated scores and standings immediately visible on public pages
6. **Notification**: Email notifications sent to affected parties (optional)
7. **Admin Override**: Administrators can modify scores later if corrections needed

### Core Data
- Game scores (home/away team scores) with immediate recording
- Score submission details and timestamps
- Real-time calculated standings and statistics
- Score modification history and audit trail
- Automatic win/loss/tie calculations

### User Interface
- **Coach Interface**: Score input forms with immediate validation and confirmation
- **Public Interface**: Real-time display of updated scores and standings
- **Admin Interface**: Score modification and correction capabilities
- **Standings Display**: Automatically updated division standings tables

## Feature 7: Email Notification System

### Overview
The Email Notification System provides automated email notifications for key league events, replacing manual communication processes with configurable automated notifications.

### MVP Requirements
- **Notification Triggers**: Automated emails for schedule changes, score updates, and game additions
- **Email Templates**: Configurable email templates with system variables
- **Recipient Management**: Team-based and static recipient configuration
- **SMTP Configuration**: Email server setup and testing tools

### Notification Types
- **Schedule Change Notifications**: Request, approval, denial, cancellation
- **Score Update Notifications**: Game score submissions and updates
- **Game Management Notifications**: New game additions

### Core Data
- Email templates with subject and body content
- Recipient configuration (team-based and static)
- Email queue and delivery tracking
- SMTP configuration and testing results

### User Interface
- Email template editor with variable support
- SMTP configuration interface with testing tools
- Email monitoring dashboard
- Test email functionality

## Feature 8: Public Website

### Overview
The Public Website provides public access to schedules, standings, and league information without requiring authentication.

### MVP Requirements
- **Public Schedule Display**: View current game schedules and results with multiple display options
- **Public Standings**: View team standings and league statistics organized by division
- **League Information**: Access to rules, documents, and announcements
- **Mobile Responsive**: Optimized viewing on all devices

### Schedule Display UI Requirements

#### Table Format (Default View)
- **Display**: Games shown in table format by default
- **Sortable Columns**: All columns can be sorted ascending/descending
- **Filterable Columns**: All columns can be filtered with search/dropdown options
- **Column Structure**:
  - **Game Number**: Unique game identifier
  - **Date**: Game date (sortable by date)
  - **Time**: Game time
  - **Away Team**: Visiting team name
  - **Home Team**: Home team name  
  - **Location**: Field/venue name
  - **Score**: Final score (if game completed)

#### Calendar Format (Alternative View)
- **View Toggle**: Option to switch from table to calendar display
- **Calendar Layout**: Monthly calendar view with games displayed on appropriate dates
- **Game Details**: Click/hover to see game details (teams, time, location, score)
- **Navigation**: Month-to-month navigation controls

#### Additional Features
- **Mobile Responsive**: Table adapts to mobile screens (possibly stacked or horizontal scroll)
- **Export Options**: Print-friendly view
- **Search Functionality**: Global search across all game data

### Standings Display UI Requirements

#### Division-Based Tables
- **Separate Tables**: Each division displayed in its own table
- **Division Headers**: Clear division names/titles above each table
- **Automatic Sorting**: Teams sorted by current standings position

#### Standings Table Columns
- **Place**: Current position in division (1st, 2nd, 3rd, etc.)
- **Team**: Team name
- **Won**: Number of wins
- **Lost**: Number of losses  
- **Tied**: Number of tied games
- **Games Back**: Games behind first place (calculated automatically)
- **RS**: Runs Scored (total runs scored by team)
- **RA**: Runs Against (total runs allowed by team)

#### Standings Calculations
- **Win Percentage**: Automatic calculation for sorting
- **Games Back**: Calculated as (Leader Wins - Team Wins + Team Losses - Leader Losses) / 2
- **Tie-Breaking**: Handle tied records with appropriate tie-breaking rules
- **Real-Time Updates**: Standings update automatically when scores are entered

### Core Data
- Public schedule information with filtering/sorting capabilities
- Team standings and statistics organized by division
- League documents and announcements
- Mobile-optimized display formats

### User Interface
- **Schedule Page**: Table view (default) with calendar view option
- **Standings Page**: Division-based tables with calculated statistics
- **Home Page**: Current day's games + next 7 days + document links
- **Document Access**: Download links for league documents
- **Mobile-Responsive Design**: Optimized for all device types

## User Access Control System

### Overview
The user access control system is implemented in two phases to provide both immediate functionality and future scalability:

**Phase 1 (MVP)**: Three-tier access system with shared passwords and admin accounts
**Phase 2 (Post-MVP)**: Traditional user accounts with role-based permissions and invitation system

### Phase 1: Three-Tier Access System (Current MVP)

The MVP uses a three-tier user access system with different permission levels and authentication methods for different user types.

#### User Types and Access Levels

##### 1. Public Users (No Authentication Required)
**Access**: Anyone can view these pages without any password or login

**Available Pages:**
- **Schedule Display**: View current game schedules and results (table/calendar format)
- **Standings Display**: View team standings organized by division
- **Home Page**: Includes the following elements:
  - Links to documents uploaded by admin users
  - Current day's games display (today's games in table format)
  - Next 7 days of games (tomorrow through 7 days out in table format)
  - Welcome message and league announcements

##### 2. Coach Access (Password Protected Access)
**Access**: Public pages PLUS password-protected coach pages using a shared "coaches password"

**Authentication Method:**
- Single shared "coaches password" (configurable by admin)
- Password entry grants 1-hour session cookie for all coach pages
- Session applies to all coach-protected pages once authenticated

**Coach-Protected Pages:**
- **Schedule Change Request Form**: Submit requests to modify game schedules (requires admin approval before schedule updates)
- **Score Input Form**: Submit game scores with immediate recording and automatic standings update
- **League Contact Information List**: Access team manager contact details

##### 3. Administrator (Admin Console Access)
**Access**: Full administrative console with complete system management

**Authentication Method:**
- Admin username and password system
- Initial password: 'admin' (must be changed on first login)
- Password changes require email verification to admin email address
- Secure admin session management

**Admin Console Structure:**

##### Dashboard
**Key Metrics** (by current active program season):
- Game statistics: played vs unplayed games
- Days remaining in current season
- Number of schedule changes awaiting approval
- Recent activity summary

**Quick Access Links:**
- Manage Games
- Manage Schedule  
- Manage Programs
- Manage Seasons
- Manage Teams
- Manage Locations
- Upload Documents

##### Administrative Management Pages

**Manage Games**: 
- CRUD operations for games
- Input or change game scores
- Game status management (Active, Completed, Cancelled)

**Manage Schedule**:
- CRUD operations for game schedules
- **Approve or deny requested schedule changes** (changes only take effect after approval)
- Schedule conflict detection and resolution
- View pending schedule change requests with details

**Manage Programs**:
- CRUD operations for sports programs
- Program configuration and settings

**Manage Seasons**:
- CRUD operations for seasons
- Season lifecycle management
- Division setup and management

**Manage Teams**:
- CRUD operations for teams
- Assign teams to seasons and divisions
- Manager contact information management:
  - Manager name
  - Phone number
  - Email address

**Manage Locations**:
- CRUD operations for field locations
- Location information used for schedule requests
- GPS coordinates and directions

**Upload Documents**:
- Upload documents for display on public home page
- Document management and organization
- File type restrictions and security

### Phase 2: Traditional User Accounts with Role-Based Permissions (Post-MVP)

Phase 2 introduces a comprehensive user account system with individual user registration, role-based permissions, and advanced user management capabilities.

#### User Account Registration System

##### Invitation-Based Registration
- **Admin-Initiated Invitations**: Administrators send invitations through the admin dashboard
- **Invitation Process**:
  1. Administrator enters email address in admin dashboard
  2. System generates unique invitation URL with secure token
  3. Invitation email sent to specified address with registration link
  4. Invitee clicks link to access registration page

##### Registration Workflow
- **Registration Form Fields**:
  - First Name (required)
  - Last Name (required)
  - Email Address (pre-populated from invitation, editable)
  - Cell Phone (required)
  - User ID/Username (required, unique)
  - Password (required)
  - Confirm Password (required)

- **Account Verification Process**:
  1. User completes registration form
  2. Account created in "unverified" status
  3. Verification email automatically sent to user's email
  4. User clicks verification link to activate account
  5. Account status changes to "active"

##### Invitation Management
- **Invitation Expiration**:
  - Default expiration: 14 days
  - Configurable in application Settings
  - Admin option for non-expiring invitations
  
- **Invitation Tracking**:
  - **Pending**: Invitation sent, registration not completed
  - **Completed**: User registered and verified account
  - **Expired**: Invitation expired before completion

#### User Roles and Permissions

##### Role Hierarchy
All registered users are assigned one of the following roles:

**1. User (Default Role)**
- Automatically assigned to new registered users
- **Permissions**:
  - All public features (schedules, standings, league rules)
  - Access to team registration feature
  - View all league and coach contact information
  - Enhanced user experience while logged in

**2. Team Owner**
- Assigned by administrator when approving team registration
- Can be team owner of multiple teams
- **Permissions**:
  - All User permissions PLUS:
  - Submit schedule change requests for owned teams
  - Input scores for games involving owned teams
  - Access team-specific management features

**3. Administrator**
- **Permissions**:
  - Full system access and management
  - All user management capabilities
  - System configuration and settings
  - All Team Owner and User permissions

#### User Account Management (Admin Features)

##### Account Administration
- **Edit User Information**: Modify all user account details
- **Password Management**:
  - Reset passwords to admin-specified values
  - Send password reset emails to users
- **Account Status Control**:
  - Disable user accounts (prevents login)
  - Re-enable disabled accounts
- **Role Management**: Change assigned user roles
- **Team Ownership**: Assign/modify team ownership for users with Coach+ roles

##### Direct Account Creation
- **Bypass Invitation System**: Admins can create accounts directly
- **Skip Verification**: Admin-created accounts are automatically verified
- **Immediate Access**: Users can login immediately with admin-provided credentials

#### User Activity Auditing

##### Login Tracking
- **Login Events**: Track all user login attempts (successful and failed)
- **Session Information**: Record login time, IP address, user agent
- **Login History**: Accessible from user account management interface

##### Activity Logging
- **User Actions**: Log all significant user actions and changes
- **Audit Trail**: Complete history of user activities
- **Administrative Access**: View user audit logs from account management

#### Team Registration Feature

##### Registration Process
- **User Access**: Available to all registered users with "User" role or higher
- **Program Selection**: Choose from available programs
- **Season Selection**: Choose from active seasons with open registration
- **Registration Periods**: Only show seasons where:
  - Current date ≥ registration start date
  - Current date ≤ registration end date

##### Team Registration Workflow
1. User accesses team registration form
2. Selects program and eligible season
3. Completes team registration information
4. Submits registration for admin approval
5. Admin reviews and approves/denies registration
6. Upon approval, user is assigned "Team Owner" role for that team

#### Enhanced Email Notification System

##### New Notification Types

**onUserInvitation**
- **Trigger**: Administrator sends user invitation
- **Recipients**: Invited user (email address provided by admin)
- **Content**: Welcome message, registration link, expiration information

**onUserRegistration**
- **Trigger**: User completes registration form
- **Recipients**: Configurable list of administrators/staff
- **Content**: New user details, account status, verification status

**onTeamRegistrationSubmit**
- **Trigger**: User submits team registration form
- **Recipients**: Configurable list of administrators/staff
- **Content**: Team registration details, user information, approval required

##### Email Configuration
- **Recipient Management**: Configure who receives each notification type
- **Template Customization**: Customize email templates for each notification
- **Variable Support**: Use system variables in email templates

#### Database Schema Additions

##### User Accounts Table
- User ID, username, email, password hash
- Personal information (first name, last name, phone)
- Account status, role, creation date, last login
- Email verification status and tokens

##### Invitations Table
- Invitation ID, email address, invitation token
- Expiration date, status, sent date
- Inviting administrator, registration completion date

##### User Activity Log Table
- Activity ID, user ID, action type, timestamp
- IP address, user agent, additional details
- Related entity IDs (game, team, etc.)

##### Team Ownership Table
- User ID, team ID, assignment date
- Status (active, inactive), assigned by administrator

#### Security Enhancements

##### Authentication Security
- **Password Requirements**: Configurable password complexity rules
- **Account Lockout**: Temporary lockout after failed login attempts
- **Session Security**: Secure session management with timeout
- **Two-Factor Authentication**: Optional 2FA for enhanced security (future enhancement)

##### Data Protection
- **Personal Information**: Secure handling of user personal data
- **COPPA Compliance**: Age verification and parental consent handling
- **Data Retention**: Configurable data retention policies
- **Privacy Controls**: User privacy settings and data export options

## Technical Architecture

### Technology Stack
- **Frontend**: Modern PHP with HTML5, CSS3, and JavaScript
- **Backend**: PHP 8.0+ with MySQL database
- **Database**: MySQL 5.7+ (shared hosting compatible)
- **Authentication**: PHP session-based with secure password hashing
- **Hosting**: Shared hosting compatible (cPanel/standard web hosting)
- **Framework**: Custom PHP or lightweight framework (Laravel/CodeIgniter optional)

### Hosting Requirements
- **PHP Version**: 8.0+ (most shared hosts support this)
- **MySQL**: 5.7+ or MariaDB equivalent
- **Web Server**: Apache with .htaccess support or Nginx
- **SSL/TLS**: HTTPS support (Let's Encrypt or hosting provider SSL)
- **Email**: SMTP support for email notifications
- **Storage**: Standard file system for document uploads
- **No Special Requirements**: No Node.js, Redis, or VPS-specific features needed

### Database Design
- Hierarchical structure: Programs → Seasons → Teams
- Supporting entities: Games, Schedules, Divisions, Locations
- Audit trails and modification tracking
- Referential integrity and proper indexing
- MySQL-compatible schema with standard data types

### Security Requirements
- HTTPS encryption for all communications
- PHP input validation and prepared statements for SQL injection prevention
- Secure PHP session management with proper configuration
- Password hashing using PHP's password_hash() function
- Data protection and COPPA compliance
- CSRF protection for forms

### PHP Implementation Details

#### Application Structure
```
/public_html/
├── index.php                 # Public home page
├── schedule.php              # Public schedule display
├── standings.php             # Public standings display
├── coaches/                  # Coach password-protected pages
│   ├── login.php            # Coach password entry
│   ├── schedule-change.php  # Schedule change request form
│   ├── score-input.php      # Score submission form
│   └── contacts.php         # League contact information
├── admin/                    # Administrative console
│   ├── index.php            # Admin dashboard
│   ├── login.php            # Admin authentication
│   ├── games/               # Game management CRUD
│   ├── schedules/           # Schedule management CRUD
│   ├── programs/            # Program management CRUD
│   ├── seasons/             # Season management CRUD
│   ├── teams/               # Team management CRUD
│   ├── locations/           # Location management CRUD
│   ├── documents/           # Document upload management
│   └── settings/            # System configuration
├── includes/                # Shared PHP includes
│   ├── config.php           # Database and app configuration
│   ├── database.php         # Database connection class
│   ├── auth.php             # Three-tier authentication functions
│   ├── email.php            # Email notification class
│   └── functions.php        # Utility functions
├── assets/                  # CSS, JavaScript, images
│   ├── css/
│   ├── js/
│   └── images/
├── uploads/                 # Document uploads by admin
│   └── documents/           # Public documents
└── .htaccess                # URL rewriting and security
```

#### Database Connection
- PDO-based database abstraction for security
- Connection pooling appropriate for shared hosting
- Prepared statements for all database queries
- Error handling and logging

#### Session Management
- **Public Users**: No session required
- **Coach Users**: 1-hour session cookie after password authentication
- **Admin Users**: Secure admin session with timeout and regeneration
- CSRF token implementation for all forms
- Secure cookie settings with httpOnly and secure flags

#### Email System
- PHPMailer or similar library for SMTP
- Queue-based email sending for shared hosting limits
- Template system for notification emails
- SMTP configuration interface

#### UI Implementation
- **JavaScript/jQuery**: For table sorting, filtering, and calendar view toggle
- **CSS Framework**: Bootstrap or similar for responsive design
- **DataTables**: For advanced table functionality (sorting, filtering, pagination)
- **Calendar Library**: FullCalendar.js or similar for calendar view
- **Mobile Optimization**: Responsive tables that stack or scroll on mobile devices

## Implementation Phases

### Phase 1: Core Infrastructure
- MySQL database setup and schema creation
- PHP application structure and configuration
- Basic authentication system with session management
- Administrative login and basic security framework

### Phase 2: Data Management
- Program, Season, and Team management interfaces
- Game creation and basic scheduling functionality
- Administrative CRUD interfaces for core entities
- Database connection and data access layer

### Phase 3: Schedule and Score Management
- Schedule change workflow implementation
- Score input forms and standings calculation
- Public website pages for schedules and standings
- Basic email notification system

### Phase 4: Email System and Polish
- Complete email notification system with templates
- SMTP configuration interface and testing tools
- User interface polish and responsive design
- Final testing and shared hosting deployment

## Success Criteria

### Functional Success
- ✅ All current system functionality preserved and enhanced
- ✅ Public access to schedules and standings
- ✅ Password-protected administrative access
- ✅ Schedule change request and approval system
- ✅ Score input and standings calculation
- ✅ Team and game management
- ✅ Email notification system with configurable templates

### Performance Success
- Page load times under 3 seconds on shared hosting
- Mobile-responsive design for all interfaces
- Support for 100+ concurrent users (shared hosting limitations)
- 99.5% uptime during baseball season (shared hosting SLA)
- Efficient database queries optimized for shared MySQL

### User Success
- Intuitive interfaces requiring minimal training
- Reduced manual communication through automated notifications
- Improved reliability and data accuracy
- Successful completion of full baseball season

## MVP Exclusions (Post-MVP Features)

The following features are documented as Phase 2 (Post-MVP) features:
- **Phase 2 User Account System**: Individual user accounts, role-based permissions, invitation system, user registration, account management, and activity auditing
- **Team Registration Feature**: User-initiated team registration with admin approval workflow
- **Enhanced Email Notifications**: User invitation, registration, and team registration notifications

Additional features excluded from MVP:
- Advanced umpire management and assignment system
- Comprehensive reporting and analytics
- Advanced document management system
- Advanced public website features (filtering, bookmarking)
- Payment processing integration
- Two-factor authentication and advanced security features

## Next Steps

This MVP requirements document serves as the foundation for iterative development. Each feature can be refined and expanded based on feedback and specific implementation needs. Technical implementation details will be developed in subsequent phases.

---

*This document consolidates all MVP features into a single, editable requirements specification. Individual feature folders contain detailed technical documentation and implementation guides.*
