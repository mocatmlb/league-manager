# District 8 Travel League - User Stories for Testing

## Overview

This document contains comprehensive user stories derived from the MVP requirements, technical specifications, and current codebase implementation. These user stories are designed to enable comprehensive testing of all system functionality across the three-tier authentication system (Public, Coach, Admin).

## User Story Format

Each user story follows the format:
- **As a** [user type]
- **I want** [functionality]
- **So that** [business value]
- **Acceptance Criteria:** [detailed testable criteria]

---

## Public User Stories (No Authentication Required)

### PU-001: View Public Home Page
**As a** public visitor  
**I want** to view the league home page with current information  
**So that** I can quickly see today's games, upcoming games, and access league documents  

**Acceptance Criteria:**
- [ ] Home page loads without requiring authentication
- [ ] Page displays welcome message with league name
- [ ] Today's games are displayed in table format (if any games today)
- [ ] Next 7 days of games are displayed in table format
- [ ] Links to public documents uploaded by admin are visible
- [ ] Page is mobile responsive
- [ ] Navigation menu includes links to Schedule and Standings
- [ ] Page loads within 3 seconds

### PU-002: View Game Schedules
**As a** public visitor  
**I want** to view game schedules in table and calendar formats  
**So that** I can see when and where games are being played  

**Acceptance Criteria:**
- [ ] Schedule page loads without authentication
- [ ] Games are displayed in table format by default
- [ ] Table includes columns: Game Number, Date, Time, Away Team, Home Team, Location, Score
- [ ] All table columns are sortable (ascending/descending)
- [ ] All table columns are filterable
- [ ] Option to switch to calendar view is available
- [ ] Calendar view shows games on appropriate dates
- [ ] Calendar view allows month-to-month navigation
- [ ] Completed games show final scores
- [ ] Page is mobile responsive with appropriate table handling
- [ ] Filter options include Program, Season, Division
- [ ] Global search functionality works across all game data

### PU-003: View Team Standings
**As a** public visitor  
**I want** to view team standings organized by division  
**So that** I can see how teams are performing in the league  

**Acceptance Criteria:**
- [ ] Standings page loads without authentication
- [ ] Each division is displayed in its own table
- [ ] Division headers clearly identify each division
- [ ] Teams are automatically sorted by current standings position
- [ ] Standings table includes: Place, Team, Won, Lost, Tied, Games Back, RS (Runs Scored), RA (Runs Against)
- [ ] Games Back is calculated correctly: (Leader Wins - Team Wins + Team Losses - Leader Losses) / 2
- [ ] Standings update automatically when scores are entered
- [ ] Tie-breaking rules are applied for teams with identical records
- [ ] Page is mobile responsive
- [ ] Win percentage is calculated and used for sorting

### PU-004: Access Public Documents
**As a** public visitor  
**I want** to access league documents and rules  
**So that** I can download important league information  

**Acceptance Criteria:**
- [ ] Document links are visible on home page
- [ ] Only documents marked as public are displayed
- [ ] Documents are listed with title and description
- [ ] Document download links work correctly
- [ ] File types are restricted to safe formats (PDF, DOC, DOCX, TXT)
- [ ] Documents are sorted by upload date (newest first)
- [ ] Broken or missing files are handled gracefully

---

## Coach User Stories (Password Protected Access)

### CO-001: Coach Authentication
**As a** coach  
**I want** to log in with the shared coaches password  
**So that** I can access coach-protected features  

**Acceptance Criteria:**
- [ ] Coach login page is accessible at /coaches/login.php
- [ ] Login form includes password field and CSRF token
- [ ] Correct password grants access to coach dashboard
- [ ] Incorrect password shows appropriate error message
- [ ] Session lasts for 1 hour as configured
- [ ] Session applies to all coach-protected pages
- [ ] Already logged-in coaches are redirected to dashboard
- [ ] Logout functionality terminates session properly
- [ ] Failed login attempts are logged for security
- [ ] Login success is logged for audit trail

### CO-002: Access Coach Dashboard
**As a** authenticated coach  
**I want** to access a dashboard with available coach tools  
**So that** I can quickly navigate to the features I need  

**Acceptance Criteria:**
- [ ] Dashboard is accessible only after coach authentication
- [ ] Dashboard displays welcome message and instructions
- [ ] Three main tool cards are displayed: Schedule Change Request, Score Input, Contact Directory
- [ ] Each tool card has appropriate icon, title, description, and action button
- [ ] Navigation links work correctly
- [ ] Important information section is displayed
- [ ] Page is mobile responsive
- [ ] Session timeout is handled gracefully

### CO-003: Submit Schedule Change Request
**As a** authenticated coach  
**I want** to submit requests to change game schedules  
**So that** I can request modifications when needed  

**Acceptance Criteria:**
- [ ] Schedule change form is accessible only to authenticated coaches
- [ ] Form includes fields for: Game selection, Request type (Reschedule/Cancel/Location Change), Reason
- [ ] For reschedule requests: New date, time, and location fields are available
- [ ] Game dropdown shows only games involving coach's teams
- [ ] Form includes CSRF protection
- [ ] Submitted requests are saved with "Pending" status
- [ ] Original schedule remains unchanged until admin approval
- [ ] Confirmation message is displayed after successful submission
- [ ] Form validation prevents invalid submissions
- [ ] Email notification is sent to administrators (if configured)

### CO-004: Submit Game Scores
**As a** authenticated coach  
**I want** to submit game scores with immediate recording  
**So that** scores are recorded and standings are updated automatically  

**Acceptance Criteria:**
- [ ] Score input form is accessible only to authenticated coaches
- [ ] Form includes game selection dropdown and score fields for both teams
- [ ] Game dropdown shows only completed games without scores
- [ ] Score fields accept only valid numeric values
- [ ] Form includes CSRF protection
- [ ] Scores are recorded immediately upon submission
- [ ] Standings are recalculated automatically when scores are entered
- [ ] Game status is updated to "Completed"
- [ ] Submission timestamp and submitter are recorded
- [ ] Confirmation message shows submitted scores
- [ ] Updated standings are immediately visible on public pages
- [ ] Email notification is sent (if configured)

### CO-005: Access Contact Directory
**As a** authenticated coach  
**I want** to view league contact information  
**So that** I can communicate with other team managers and league officials  

**Acceptance Criteria:**
- [ ] Contact directory is accessible only to authenticated coaches
- [ ] Directory displays team manager contact information
- [ ] Information includes: Team name, Manager name, Phone number, Email address
- [ ] Contact information is organized by division or alphabetically
- [ ] Search functionality allows finding specific teams or managers
- [ ] Contact information is current and accurate
- [ ] Page is mobile responsive for easy access on phones
- [ ] Email addresses are clickable (mailto links)
- [ ] Phone numbers are clickable on mobile devices

---

## Admin User Stories (Full Administrative Access)

### AD-001: Admin Authentication
**As a** system administrator  
**I want** to log in with my admin credentials  
**So that** I can access the administrative console  

**Acceptance Criteria:**
- [ ] Admin login page is accessible at /admin/login.php
- [ ] Login form includes username, password, and CSRF token
- [ ] Valid credentials grant access to admin dashboard
- [ ] Invalid credentials show appropriate error message
- [ ] Default admin password must be changed on first login
- [ ] Password changes require email verification
- [ ] Session timeout is configurable (default 2 hours)
- [ ] Failed login attempts are logged and may trigger lockout
- [ ] Successful logins are logged with timestamp and IP
- [ ] Session regeneration occurs on login for security

### AD-002: Access Admin Dashboard
**As a** authenticated administrator  
**I want** to view a comprehensive dashboard with system metrics  
**So that** I can monitor league operations and access management tools  

**Acceptance Criteria:**
- [ ] Dashboard is accessible only after admin authentication
- [ ] Welcome message displays current admin username
- [ ] Current active season is displayed prominently
- [ ] Key metrics are displayed: Total games, Completed games, Pending schedule changes, Active teams
- [ ] Quick action buttons provide access to all management areas
- [ ] Recent activity log is displayed (last 10 activities)
- [ ] Dashboard loads quickly with optimized database queries
- [ ] All metric calculations are accurate
- [ ] Navigation to management sections works correctly

### AD-003: Manage Programs
**As a** system administrator  
**I want** to create, edit, and manage sports programs  
**So that** I can organize different sports and age groups  

**Acceptance Criteria:**
- [ ] Programs management page shows list of all programs
- [ ] Create new program form includes: Name, Code, Sport type, Age range, Game format
- [ ] Program codes must be unique
- [ ] Edit functionality allows modification of all program fields
- [ ] Delete functionality includes confirmation and cascade handling
- [ ] Active/Inactive status can be toggled
- [ ] Programs are sorted by name or creation date
- [ ] Search and filter functionality works correctly
- [ ] Form validation prevents invalid data entry
- [ ] Changes are logged in activity log

### AD-004: Manage Seasons
**As a** system administrator  
**I want** to create and manage seasons within programs  
**So that** I can organize time-bound league periods  

**Acceptance Criteria:**
- [ ] Seasons management page shows seasons organized by program
- [ ] Create season form includes: Program selection, Season name, Year, Date ranges, Status
- [ ] Registration start/end dates can be configured
- [ ] Season status can be changed: Planning → Registration → Active → Completed → Archived
- [ ] Only one season per program can be "Active" at a time
- [ ] Division management is accessible from season details
- [ ] Season deletion includes confirmation and cascade handling
- [ ] Date validation ensures logical date ranges
- [ ] Current season indicator is clearly visible

### AD-005: Manage Teams
**As a** system administrator  
**I want** to manage team registrations and information  
**So that** I can maintain accurate team and manager data  

**Acceptance Criteria:**
- [ ] Teams management page shows teams organized by season/division
- [ ] Create team form includes: Season, Division, League name, Team name, Manager details
- [ ] Manager information includes: Name, Phone, Email, Home field preferences
- [ ] Team assignment to divisions can be modified
- [ ] Active/Inactive status can be managed
- [ ] Registration fees and late fees can be tracked
- [ ] Search functionality works across team and manager information
- [ ] Contact information is validated (email format, phone format)
- [ ] Team deletion includes confirmation and handles game references

### AD-006: Manage Games
**As a** system administrator  
**I want** to create and manage individual games  
**So that** I can set up matchups between teams  

**Acceptance Criteria:**
- [ ] Games management page shows games with filtering options
- [ ] Create game form includes: Season, Division, Home team, Away team, Game number
- [ ] Game numbers must be unique within the system
- [ ] Team selection is limited to teams in the same division
- [ ] Game status can be managed: Active, Completed, Cancelled
- [ ] Score entry and modification is available
- [ ] Game deletion includes confirmation and handles schedule references
- [ ] Bulk game creation tools are available
- [ ] Game history and modifications are tracked

### AD-007: Manage Schedules
**As a** system administrator  
**I want** to assign dates, times, and locations to games  
**So that** I can create the league schedule  

**Acceptance Criteria:**
- [ ] Schedule management page shows games with schedule information
- [ ] Schedule assignment includes: Date, Time, Location selection
- [ ] Location dropdown is populated from locations table
- [ ] Conflict detection prevents double-booking of fields
- [ ] Schedule changes are versioned and tracked
- [ ] Bulk scheduling tools are available
- [ ] Schedule export functionality is provided
- [ ] Schedule change requests are displayed and manageable
- [ ] Approval/denial of schedule changes updates schedules immediately

### AD-008: Approve Schedule Change Requests
**As a** system administrator  
**I want** to review and approve/deny schedule change requests  
**So that** I can maintain control over schedule modifications  

**Acceptance Criteria:**
- [ ] Schedule change requests are listed with all relevant details
- [ ] Request details include: Game info, Original schedule, Requested changes, Reason
- [ ] Approval immediately updates the game schedule
- [ ] Denial maintains the original schedule
- [ ] Review notes can be added to requests
- [ ] Email notifications are sent to requesters (if configured)
- [ ] Request history is maintained for audit purposes
- [ ] Bulk approval/denial functionality is available
- [ ] Conflict checking is performed before approval

### AD-009: Manage Locations
**As a** system administrator  
**I want** to manage field locations and their information  
**So that** I can maintain accurate venue data for scheduling  

**Acceptance Criteria:**
- [ ] Locations management page shows all field locations
- [ ] Create location form includes: Name, Address, GPS coordinates, Notes
- [ ] Location information is used in schedule assignment dropdowns
- [ ] Active/Inactive status can be managed
- [ ] Location deletion checks for existing schedule references
- [ ] Address validation and GPS coordinate formatting
- [ ] Search functionality works across location data
- [ ] Location usage statistics are available

### AD-010: Manage System Settings
**As a** system administrator  
**I want** to configure system-wide settings and preferences  
**So that** I can customize the system behavior  

**Acceptance Criteria:**
- [ ] Settings page is organized into logical sections
- [ ] General settings include: League name, Current season, Contact information
- [ ] Authentication settings include: Coaches password, Session timeouts
- [ ] Email settings include: SMTP configuration, From address, Templates
- [ ] Settings validation prevents invalid configurations
- [ ] Password changes are hashed before storage
- [ ] Email configuration testing is available
- [ ] Settings changes are logged in activity log
- [ ] Backup and restore functionality for settings

### AD-011: Manage Email Templates
**As a** system administrator  
**I want** to customize email notification templates  
**So that** I can control the content and format of automated emails  

**Acceptance Criteria:**
- [ ] Email templates page shows all available templates
- [ ] Template editor includes subject and body fields
- [ ] Variable substitution is supported (e.g., {game_number}, {team_name})
- [ ] Template preview functionality is available
- [ ] Test email sending is supported
- [ ] Template activation/deactivation is available
- [ ] Template history and versioning is maintained
- [ ] HTML and plain text formats are supported
- [ ] Template validation prevents syntax errors

### AD-012: Upload and Manage Documents
**As a** system administrator  
**I want** to upload and manage public documents  
**So that** I can provide league information to the public  

**Acceptance Criteria:**
- [ ] Document upload form supports multiple file types (PDF, DOC, DOCX, TXT)
- [ ] File size limits are enforced (5MB default)
- [ ] Document metadata includes: Title, Description, Public/Private status
- [ ] Uploaded files are stored securely with renamed filenames
- [ ] Document listing shows all uploaded files with management options
- [ ] Public/Private status can be toggled
- [ ] Document deletion includes file system cleanup
- [ ] File type validation prevents dangerous uploads
- [ ] Upload progress indication for large files

### AD-013: View Activity Logs
**As a** system administrator  
**I want** to view system activity and audit logs  
**So that** I can monitor system usage and troubleshoot issues  

**Acceptance Criteria:**
- [ ] Activity log page shows recent system activities
- [ ] Log entries include: User, Action, Timestamp, IP address, Details
- [ ] Filtering options include: Date range, User type, Action type
- [ ] Search functionality works across log details
- [ ] Log entries are paginated for performance
- [ ] Export functionality is available for log data
- [ ] Log retention policies are configurable
- [ ] Security events are highlighted appropriately

### AD-014: Manage Admin Users
**As a** system administrator  
**I want** to manage other administrator accounts  
**So that** I can control access to the administrative console  

**Acceptance Criteria:**
- [ ] Admin users page shows all administrator accounts
- [ ] Create admin form includes: Username, Password, Email, Name
- [ ] Username uniqueness is enforced
- [ ] Password requirements are enforced and validated
- [ ] Admin account activation/deactivation is available
- [ ] Password reset functionality is provided
- [ ] Admin account deletion includes confirmation
- [ ] Last login information is displayed
- [ ] Admin activity can be viewed per user

---

## Integration User Stories (Cross-Feature Functionality)

### IN-001: Email Notification System
**As a** system user  
**I want** to receive automated email notifications for relevant events  
**So that** I stay informed about important league activities  

**Acceptance Criteria:**
- [ ] Schedule change request notifications are sent to administrators
- [ ] Schedule change approval/denial notifications are sent to requesters
- [ ] Game score submission notifications are sent to configured recipients
- [ ] Email templates are used for consistent formatting
- [ ] Variable substitution works correctly in all templates
- [ ] Email delivery failures are logged and handled gracefully
- [ ] SMTP configuration is validated before sending
- [ ] Email queue system handles high volumes

### IN-002: Data Integrity and Relationships
**As a** system user  
**I want** data relationships to be maintained correctly  
**So that** the system remains consistent and reliable  

**Acceptance Criteria:**
- [ ] Foreign key relationships are enforced in the database
- [ ] Cascade deletions work correctly (e.g., deleting season removes games)
- [ ] Referential integrity is maintained across all operations
- [ ] Orphaned records are prevented or cleaned up
- [ ] Data validation prevents inconsistent states
- [ ] Transaction rollback works for failed operations

### IN-003: Security and Access Control
**As a** system user  
**I want** the system to be secure and protect sensitive information  
**So that** league data and personal information is safe  

**Acceptance Criteria:**
- [ ] CSRF protection is implemented on all forms
- [ ] SQL injection prevention through prepared statements
- [ ] XSS protection through input sanitization and output encoding
- [ ] Session security with proper cookie settings
- [ ] Password hashing using secure algorithms
- [ ] Access control prevents unauthorized access to protected areas
- [ ] File upload security prevents malicious files
- [ ] Error messages don't reveal sensitive information

### IN-004: Performance and Scalability
**As a** system user  
**I want** the system to perform well under normal usage  
**So that** I can use the system efficiently  

**Acceptance Criteria:**
- [ ] Page load times are under 3 seconds on shared hosting
- [ ] Database queries are optimized with appropriate indexes
- [ ] Large datasets are paginated appropriately
- [ ] Caching is implemented where beneficial
- [ ] Resource usage stays within shared hosting limits
- [ ] System handles concurrent users appropriately

### IN-005: Mobile Responsiveness
**As a** system user on mobile devices  
**I want** all pages to work well on smartphones and tablets  
**So that** I can access the system from any device  

**Acceptance Criteria:**
- [ ] All pages are mobile responsive using Bootstrap framework
- [ ] Tables adapt appropriately on small screens (stacking or horizontal scroll)
- [ ] Forms are usable on touch devices
- [ ] Navigation menus work on mobile devices
- [ ] Text is readable without zooming
- [ ] Touch targets are appropriately sized
- [ ] Performance is acceptable on mobile connections

---

## Testing Categories

### Functional Testing
- All user stories should be tested for basic functionality
- Form submissions and data processing
- Authentication and authorization
- CRUD operations for all entities
- Email notification delivery
- File upload and download

### Security Testing
- Authentication bypass attempts
- CSRF token validation
- SQL injection prevention
- XSS prevention
- File upload security
- Session management security
- Access control enforcement

### Performance Testing
- Page load time measurement
- Database query performance
- Concurrent user handling
- Large dataset handling
- File upload performance
- Email delivery performance

### Usability Testing
- Navigation and user flow
- Form usability and validation
- Error message clarity
- Mobile device usability
- Accessibility compliance
- User experience consistency

### Integration Testing
- Database relationship integrity
- Email system integration
- Cross-feature functionality
- Data consistency across operations
- Session management across pages
- File system integration

### Regression Testing
- Existing functionality preservation
- Data migration accuracy
- Configuration setting persistence
- User session continuity
- Email template functionality
- Document access continuity

---

## Test Data Requirements

### Sample Data Sets
- Multiple programs (Baseball, Softball, T-Ball)
- Multiple seasons (Active, Completed, Planning)
- Multiple divisions per season
- Teams with complete manager information
- Games with various statuses
- Schedules with different dates and locations
- Schedule change requests in various states
- Admin users with different access levels
- Email templates for all notification types
- Public and private documents
- Activity log entries for audit testing

### Edge Cases
- Empty datasets (no games, teams, etc.)
- Maximum data volumes (large numbers of games, teams)
- Invalid data inputs and boundary conditions
- Concurrent access scenarios
- Network failure scenarios
- Database connection failures
- Email delivery failures

---

## Acceptance Testing Checklist

### Pre-Deployment Testing
- [ ] All user stories pass acceptance criteria
- [ ] Security testing completed with no critical issues
- [ ] Performance testing meets requirements
- [ ] Mobile responsiveness verified on multiple devices
- [ ] Email system tested with real SMTP configuration
- [ ] Database backup and restore procedures tested
- [ ] Error handling and logging verified
- [ ] Documentation updated and accurate

### Post-Deployment Verification
- [ ] All public pages accessible without authentication
- [ ] Coach authentication and features working
- [ ] Admin console fully functional
- [ ] Email notifications delivering correctly
- [ ] Database operations performing within limits
- [ ] File uploads and downloads working
- [ ] Mobile access verified
- [ ] SSL certificate active and valid
- [ ] Monitoring and logging operational

This comprehensive set of user stories provides the foundation for thorough testing of the District 8 Travel League Management System across all user types and functionality areas.
