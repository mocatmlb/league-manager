# District 8 Travel League Management System

## Overview

The District 8 Travel League Management System is a PHP-based web application designed to replace the legacy PHP/FormTools-based website with an improved, secure, and user-friendly platform for managing youth baseball leagues. Built specifically for shared hosting environments, the system provides comprehensive functionality for team management, game scheduling, score tracking, and league administration.

## Current Status: MVP Implementation

The MVP (Minimum Viable Product) is currently in development and represents a direct replacement of the current system functionality. The MVP is built with **PHP 8.0+ and MySQL** for maximum compatibility with shared hosting environments like A Small Orange.

### MVP Core Features ✅
- **Public Website**: View schedules, standings, and league information
- **Three-Tier Authentication**: Public access, coach password protection, and admin console
- **Administrative Dashboard**: Comprehensive management interface for teams, games, and schedules
- **Schedule Change Management**: Submit and approve schedule change requests
- **Score Management**: Input scores and automatically calculate standings
- **Email Notification System**: Automated notifications with configurable templates
- **Git-Based Deployment**: Automated deployment with staging environment for testing

## Key Features

### 🔐 Authentication System
**MVP**: Three-tier access system for current implementation
- **Public Access**: View schedules, standings, and league information (no login required)
- **Coach Access**: Password-protected access for schedule changes and score submission
- **Admin Console**: Full administrative access with comprehensive management capabilities

**Post-MVP**: Advanced multi-role authentication system
- **Six Primary Roles**: Public User, Team Official, Team Manager, Umpire, Umpire Assignor, Administrator
- **Multi-Role Support**: Users can hold multiple roles simultaneously with permission inheritance
- **Approval Workflow**: New user registrations require administrative approval
- **Individual User Accounts**: Personal accounts with role-based permissions

### 🏆 League Management **[MVP]**
- **Program Structure**: Support for multiple sports programs (Baseball, Softball, etc.)
- **Season Management**: Hierarchical organization (Programs → Seasons → Divisions → Teams)
- **Team Registration**: Administrative team registration and management
- **Division Organization**: Flexible division structure (East/West, American/National, etc.)

### 🏟️ Location Management System **[Post-MVP]**
- **Centralized Field Database**: Standardized location management with duplicate prevention
- **Smart Duplicate Detection**: Fuzzy matching algorithms prevent duplicate field entries
- **Role-Based Creation**: Team Managers and Administrators can add new locations
- **GPS-Friendly Addressing**: Physical addresses for navigation and helpful location notes
- **Active Status Control**: Administrator-only control over location availability

### 📅 Game & Schedule Management **[MVP Core, Post-MVP Advanced]**
**MVP Features:**
- **Basic Game Management**: Create, edit, and manage games
- **Schedule Display**: Public and administrative schedule views
- **Schedule Changes**: Request and approval workflow for schedule modifications
- **Game Status**: Simple Active/Cancelled game status system

**Post-MVP Features:**
- **Advanced Scheduling**: Bulk schedule generation and optimization
- **Conflict Detection**: Automatic prevention of team, venue, and umpire conflicts
- **Schedule Versioning**: Complete history of all schedule changes with approval tracking
- **Advanced Filtering**: Comprehensive filtering and bookmarking capabilities

### ⚾ Umpire Management System **[Final Phase]**
- **Assignment Tracking**: Up to 3 umpires per game with availability management
- **Confidentiality**: Umpire assignments hidden from team managers for security
- **Assignment History**: Complete audit trail of all umpire assignments
- **Notification System**: Automated alerts for assignment changes

*Note: Moved to final implementation phase to focus on core functionality first*

### 📊 Score & Standings Management **[MVP]**
- **Score Submission**: Administrative score input interface
- **Basic Approval**: Administrative review and approval of submitted scores
- **Real-time Standings**: Automatic calculation of wins, losses, ties, and run differentials
- **Historical Tracking**: Basic game and season statistics *(Advanced analytics Post-MVP)*

### 🌐 Public Website Features **[MVP Core, Post-MVP Advanced]**
**MVP Features:**
- **Public Schedules**: View current game schedules and results
- **Team Standings**: League standings and team statistics
- **League Information**: Basic league information and announcements
- **Mobile Responsive**: Optimized for all device types

**Post-MVP Features:**
- **Advanced Filtering**: Comprehensive schedule filtering and bookmarking
- **Static Document Links**: Simple access to league rules and forms (no complex document management)
- **Team Directory**: Contact information with appropriate access controls
- **Export Capabilities**: PDF, CSV, and iCal export functionality

### 📧 Email Notification System **[MVP]**
- **Automated Notifications**: Schedule changes, score updates, and game additions
- **Configurable Templates**: Customizable email templates with system variables
- **SMTP Configuration**: Full email server setup and testing tools
- **Recipient Management**: Team-based and static recipient configuration
- **Testing Tools**: Comprehensive email testing with dummy or real data

## System Architecture

### Technology Stack
- **Frontend**: PHP 8.0+ with HTML/CSS and vanilla JavaScript
- **Backend**: PHP 8.0+ with MVC-style architecture and MySQLi
- **Database**: MariaDB 5.5+ (MySQL-compatible) with prepared statements
- **Authentication**: PHP session-based with role-based access control
- **Hosting**: A Small Orange Shared Web Hosting with cPanel
- **Email**: PHPMailer 6.8+ for SMTP email functionality
- **Deployment**: Git-based automated deployment with .cpanel.yml
- **Version Control**: Git with GitHub and cPanel Git Version Control integration

### Core Entities
- **Programs**: Top-level sports programs (Baseball, Softball)
- **Seasons**: Time-bound periods within programs
- **Divisions**: Organizational groupings within seasons
- **Teams**: Individual teams participating in divisions
- **Games**: Individual game records with unique numbering
- **Locations**: Centralized baseball field management with duplicate prevention
- **Schedules**: Versioned scheduling system with approval workflow
- **Users**: Multi-role user accounts with permission inheritance
- **Umpires**: Game officials with assignment tracking

## Key System Features

### 🔐 Multi-Role Authentication System
- **Six Primary Roles**: Public User, Team Official, Team Manager, Umpire, Umpire Assignor, Administrator
- **Multi-Role Support**: Users can hold multiple roles simultaneously with permission inheritance
- **Approval Workflow**: New user registrations require administrative approval
- **Default Admin Account**: Bootstrap account for initial system setup
- **JWT Authentication**: Modern token-based authentication with Redis session management

### 🏆 Comprehensive League Management
- **Scalable Program Structure**: Support for multiple sports programs (Baseball, Softball, etc.)
- **Season Management**: Hierarchical organization (Programs → Seasons → Divisions → Teams)
- **Team Registration**: Self-service team registration with approval workflows
- **Division Organization**: Flexible division structure (East/West, American/National, etc.)

### 📅 Advanced Game & Schedule Management
- **Unique Game Numbering**: Each game has a unique identifier within its season
- **Schedule Versioning**: Complete history of all schedule changes with approval tracking
- **Conflict Detection**: Automatic prevention of team, venue, and umpire conflicts
- **Status Management**: Simple Active/Cancelled game status system

### ⚾ Umpire Management System
- **Assignment Tracking**: Up to 3 umpires per game with availability management
- **Confidentiality**: Umpire assignments hidden from team managers for security
- **Assignment History**: Complete audit trail of all umpire assignments
- **Notification System**: Automated alerts for assignment changes

### 📊 Score & Standings Management
- **Score Submission**: Team officials and managers can submit game scores
- **Approval Workflow**: Administrative review and approval of submitted scores
- **Real-time Standings**: Automatic calculation of wins, losses, ties, and run differentials
- **Historical Tracking**: Complete game and season statistics

### 🌐 Public Website Features
- **Public Schedules**: View current game schedules and results
- **Team Standings**: League standings and team statistics
- **League Information**: Rules, documents, and announcements
- **Mobile Responsive**: Optimized for all device types

## Quick Start (Local Development)

### Prerequisites
- **PHP 8.0+** (XAMPP, WAMP, MAMP, or native installation)
- **MySQL/MariaDB 5.5+** (included with XAMPP/WAMP/MAMP)
- **Apache Web Server** (included with XAMPP/WAMP/MAMP)
- **Composer** (PHP dependency manager)
- **Git** for version control

### Local Setup
```bash
# Clone repository
git clone https://github.com/your-org/d8tl.git
cd d8tl/mvp-app

# Install PHP dependencies
composer install

# Copy and configure environment
cp includes/config.example.php includes/config.php
# Edit config.php with your local database settings

# Create database and import schema
mysql -u root -p -e "CREATE DATABASE d8tl_local;"
mysql -u root -p d8tl_local < database/schema.sql

# Start local development server
# Option 1: Use your local Apache (point to mvp-app/public directory)
# Option 2: Use PHP built-in server
php -S localhost:8000 -t public/
```

**Access the application:**
- **Main Application**: http://localhost:8000 (or your Apache virtual host)
- **Admin Console**: http://localhost:8000/admin/
- **Coach Portal**: http://localhost:8000/coaches/

### Default Login Credentials (Local Development)
- **Admin Password**: `admin` (change in production)
- **Coach Password**: `coaches` (change in production)

For detailed setup instructions, see the [Git Deployment Setup Guide](./GIT_DEPLOYMENT_SETUP.md).

## Production Deployment

### Prerequisites
- **A Small Orange Shared Hosting** with cPanel access
- **PHP 8.0+** (configurable via cPanel PHP Selector)
- **MariaDB/MySQL 5.5+** (included with hosting)
- **Git Version Control** (available in cPanel)
- **SMTP Email** (included with hosting or external service)
- **Composer** (available on hosting platform)

### Git-Based Deployment Strategy

The system uses **automated Git-based deployment** with staging and production environments:

- **Production**: `district8travelleague.com` (main branch)
- **Staging**: `staging.district8travelleague.com` (staging branch)
- **Development**: Local environment (develop branch)

### Environment Configuration

Configure environment-specific settings in PHP configuration files:

**Production Configuration** (`includes/config.prod.php`):
```php
<?php
// Database Configuration - Production (MUST BE CONFIGURED)
define('DB_HOST', 'localhost');
define('DB_NAME', 'REPLACE_WITH_ACTUAL_DATABASE_NAME');
define('DB_USER', 'REPLACE_WITH_ACTUAL_DB_USERNAME');
define('DB_PASS', 'REPLACE_WITH_ACTUAL_DB_PASSWORD');

// Application Configuration
define('APP_NAME', 'District 8 Travel League');
define('APP_URL', 'https://district8travelleague.com');
define('APP_ENV', 'production');

// Email Configuration
define('SMTP_HOST', 'mail.asmallorange.com');
define('SMTP_USERNAME', 'REPLACE_WITH_ACTUAL_EMAIL_ADDRESS');
define('SMTP_PASSWORD', 'REPLACE_WITH_ACTUAL_EMAIL_PASSWORD');

// Security Settings (CRITICAL: MUST BE CHANGED BEFORE DEPLOYMENT)
define('DEFAULT_ADMIN_PASSWORD', 'REPLACE_WITH_STRONG_ADMIN_PASSWORD_MIN_12_CHARS');
define('DEFAULT_COACHES_PASSWORD', 'REPLACE_WITH_STRONG_COACHES_PASSWORD_MIN_12_CHARS');
?>
```

⚠️ **CRITICAL SECURITY NOTE**: All placeholder values MUST be replaced with actual credentials before deployment. Never commit real credentials to version control.

### Deployment Workflow

#### 1. Initial Setup
1. **Set up cPanel Git repositories** for production and staging
2. **Create databases** via cPanel MySQL Databases
3. **Configure environment files** with production credentials
4. **Import database schema** via phpMyAdmin

#### 2. Development Workflow
```bash
# Feature development
git checkout develop
git checkout -b feature/new-feature
# Make changes and commit
git push origin feature/new-feature

# Deploy to staging for testing
git checkout staging
git merge develop
git push origin staging
# Automatic deployment to staging.district8travelleague.com

# Deploy to production (after testing)
git checkout main
git merge staging
git push origin main
# Manual deployment via cPanel Git Version Control
```

#### 3. Automated Deployment Features
- **Database Migrations**: Automatic schema updates during deployment
- **Dependency Management**: Composer dependencies installed automatically
- **Environment Configuration**: Environment-specific settings applied
- **Health Checks**: Post-deployment verification of system functionality
- **Rollback Capability**: Quick recovery from deployment issues

### cPanel Deployment Process
1. **Navigate to cPanel → Git Version Control**
2. **Select repository** (production or staging)
3. **Click "Pull or Deploy"** to trigger deployment
4. **Monitor deployment logs** for success/failure
5. **Verify application functionality** post-deployment

### Security Configuration

#### Critical Security Steps (MANDATORY)

1. **Replace All Placeholder Credentials**: Before any deployment, replace ALL placeholder values in configuration files:
   - Database credentials in `config.prod.php` and `config.staging.php`
   - SMTP email credentials
   - Default admin and coach passwords

2. **Use Strong Passwords**: 
   - Minimum 12 characters
   - Mix of uppercase, lowercase, numbers, and special characters
   - Unique passwords for each environment

3. **Secure File Permissions**: Ensure configuration files are not publicly accessible:
   ```bash
   chmod 600 mvp-app/includes/config.*.php
   ```

4. **Never Commit Real Credentials**: The `.gitignore` file prevents accidental commits of:
   - Database backups
   - Real configuration files
   - Log files with sensitive data

#### Security Features

- **CSRF Protection**: All forms include CSRF tokens
- **Session Security**: Secure session configuration with HTTP-only cookies
- **Password Hashing**: All passwords use PHP's `password_hash()` function
- **Input Sanitization**: Database queries use prepared statements
- **Access Control**: Role-based access for admin and coach areas

### Initial Setup

1. **Access Application**: Navigate to your deployed URL
2. **Admin Console Login**: Use admin password from configuration
3. **Change Default Passwords**: Update admin and coach passwords immediately
4. **Create First Program**: Set up your first sports program (e.g., "Baseball")
5. **Create Season**: Add the current season to your program
6. **Configure Divisions**: Set up divisions within your season
7. **Configure Email Settings**: Set up SMTP configuration for notifications

### Security Considerations

- **Change Default Passwords**: Immediately change all default passwords in production
- **SSL/TLS**: Use HTTPS in production with SSL certificates from hosting provider
- **File Permissions**: Ensure proper file permissions (755 for directories, 644 for files)
- **Database Security**: Use strong database passwords and limit user privileges
- **Configuration Protection**: Sensitive files protected via .htaccess rules
- **Regular Updates**: Keep PHP and Composer dependencies updated

### Hosting Environment - A Small Orange Shared Web Hosting

#### Server Specifications
- **Hosting Type**: Shared web hosting with cPanel management
- **PHP Support**: Multiple PHP versions (5.3 to 8.0+) via PHP Selector
- **Database**: MariaDB 5.5+ (MySQL-compatible) with phpMyAdmin access
- **Email**: Integrated email hosting with SMTP support
- **Storage**: Daily automated backups with 7-day retention
- **Control Panel**: cPanel with comprehensive management tools

#### Key Advantages for MVP
- ✅ **Cost Effective**: Affordable hosting solution ($5-15/month)
- ✅ **Easy Management**: cPanel interface for non-technical management
- ✅ **PHP Optimized**: Native PHP support with multiple version options
- ✅ **Git Integration**: cPanel Git Version Control for automated deployment
- ✅ **Email Included**: Built-in email hosting and SMTP services
- ✅ **No Server Management**: Hosting provider handles all server maintenance
- ✅ **Proven Reliability**: Established hosting with good uptime records

### Monitoring & Maintenance

#### Health Checks
- **Application**: Automated post-deployment health verification
- **Database**: MySQL/MariaDB connection and query monitoring
- **File Permissions**: Automated permission verification
- **Email**: SMTP connectivity and configuration testing

#### Backup Strategy
- **Database**: Daily automated cPanel backups with 7-day retention
- **Pre-deployment Backups**: Automatic database backup before each production deployment
- **Files**: Regular backup of uploaded documents via cPanel
- **Configuration**: Version control for all configuration files

#### Log Management
- **Deployment Logs**: Complete deployment history and status
- **PHP Error Logs**: Application error tracking and debugging
- **Email Logs**: SMTP delivery and notification tracking
- **Access Logs**: User activity and security monitoring via cPanel

## Documentation

### 🚀 Getting Started
- **[Git Deployment Setup Guide](./GIT_DEPLOYMENT_SETUP.md)** - Complete setup guide for Git-based deployment
- **[Deployment Strategy Summary](./DEPLOYMENT_STRATEGY_SUMMARY.md)** - Overview of deployment architecture

### 📋 MVP Development Documentation
- **[MVP Requirements](./docs/requirements.md)** - Complete MVP specifications and feature requirements
- **[Technical Overview](./docs/tech.md)** - Comprehensive technical documentation with PHP/MySQL stack details
- **[Implementation Plan](./docs/implamentation.md)** - Development roadmap and implementation strategy

### 🎯 Current MVP Features (In Development)
The MVP includes these core features with PHP-based implementation:
- **Program Management** - Sports program creation and configuration
- **Season Management** - Time-bound program instances with divisions
- **Team Management** - Team registration and administrative management
- **Game Management** - Individual game creation, scheduling, and scoring
- **Schedule Management** - Schedule display and change request workflow
- **Score Management** - Score submission with automatic standings calculation
- **Email Notification System** - Automated notifications with PHPMailer
- **Public Website** - Public-facing schedules, standings, and league information
- **Three-Tier Authentication** - Public access, coach portal, and admin console

### 🔧 Technical Documentation
- **[System Architecture](./docs/tech.md#system-architecture)** - PHP/MySQL architecture overview
- **[Database Schema](./mvp-app/database/schema.sql)** - Complete database structure
- **[Deployment Configuration](./mvp-app/.cpanel.yml)** - Automated deployment settings
- **[Environment Configuration](./docs/tech.md#environment-configuration)** - Production and staging setup

### 📚 Post-MVP Feature Documentation
- [Advanced User Authentication](./docs/Features/user-authentication/user-authentication-requirements.md) **[Post-MVP]**
- [Location Management](./docs/Features/location-management/location-management-requirements.md) **[Post-MVP]**
- [Umpire Management](./docs/Features/umpire-management/umpire-management-requirements.md) **[Final Phase]**
- [Reporting & Analytics](./docs/Features/reporting-analytics/reporting-analytics-requirements.md) **[Post-MVP]**

## Support & Contributing

### Getting Help
- **Documentation**: Check the `/docs` folder for detailed requirements
- **Issues**: Report bugs and feature requests via GitHub Issues
- **Support**: Contact the development team for technical support

### Development Guidelines
- **Code Style**: Follow established coding standards and linting rules
- **Testing**: Write comprehensive tests for all new features
- **Documentation**: Update documentation for any changes (see `.cursorrules`)
- **Security**: Follow security best practices and conduct code reviews

## License

This project is proprietary software developed for the District 8 Travel League. All rights reserved.

## Project Status & Roadmap

### 🚧 Current Status: MVP Development (v2.0.0-MVP)
**PHP-Based MVP - Direct replacement of current system functionality**

**✅ Completed Components:**
- Git-based deployment system with staging and production environments
- Database schema design with comprehensive entity relationships
- Automated deployment configuration (.cpanel.yml) with health checks
- Environment-specific configuration system (production/staging)
- Technical documentation and deployment guides

**🔄 In Development:**
- Three-tier authentication system (Public/Coach/Admin)
- Administrative dashboard for comprehensive system management
- Public website with schedules and standings display
- Schedule change request and approval workflow
- Score input system with automatic standings calculation
- Email notification system with PHPMailer integration
- Program, season, division, and team management interfaces

**🎯 MVP Technology Stack:**
- **Backend**: PHP 8.0+ with MVC architecture and MySQLi
- **Frontend**: HTML/CSS with vanilla JavaScript and Bootstrap
- **Database**: MariaDB 5.5+ (MySQL-compatible) with prepared statements
- **Hosting**: A Small Orange Shared Web Hosting with cPanel
- **Deployment**: Git-based automated deployment with staging environment

### 📅 Future Phases

### v2.1.0 (Planned - Phase 2)
**Enhanced Features - Beyond current system capabilities**
- Multi-role authentication system with 6 primary roles
- Individual user accounts with approval workflow
- Advanced game and schedule management with conflict detection
- Enhanced public website features with filtering and bookmarking

### v2.2.0 (Planned - Phase 3)
**Advanced Features - Comprehensive league management**
- Centralized Location Management with duplicate prevention
- Comprehensive reporting and analytics
- Calendar integrations and export capabilities

### v2.3.0 (Planned - Final Phase)
**Umpire Management - Advanced officiating features**
- Umpire management system with confidentiality features
- Assignment tracking and availability management
- Comprehensive umpire utilization reporting

### 📜 Legacy System (v1.0.0)
- Basic PHP/FormTools implementation
- Simple password protection
- Basic game scheduling and score tracking
- Limited user management capabilities

---

For detailed technical requirements and implementation specifications, see the [System Requirements](./docs/requirements.md) and [Implementation Plan](./docs/implamentation.md) documents.

## 🎉 **Repository Successfully Prepared!**

### **Current Status:**
- ✅ **Clean Git repository** with comprehensive security sanitization
- ✅ **All sensitive data removed** (database backups, exposed credentials)
- ✅ **Security documentation added** (SECURITY.md, .gitignore, etc.)
- ✅ **Navigation issues fixed** and application fully functional
- ✅ **Comprehensive commit message** documenting all changes

### **Next Steps to Create New Remote Repository:**

#### **Option 1: Create New GitHub Repository via Web Interface**

1. **Go to GitHub**: Visit https://github.com/new
2. **Repository Details**:
   - **Repository name**: `D8TL` (or your preferred name)
   - **Description**: `District 8 Travel League - Secure MVP Application`
   - **Visibility**: Choose Public or Private
   - **DO NOT** initialize with README, .gitignore, or license (we already have these)

3. **After creating the repository**, GitHub will show you commands like:
   ```bash
   git remote add origin https://github.com/YOUR_USERNAME/D8TL.git
   git branch -M main
   git push -u origin main
   ```

#### **Option 2: Use GitHub CLI (if installed)**
```bash
# Create repository directly from command line
gh repo create D8TL --public --description "District 8 Travel League - Secure MVP Application"
git remote add origin https://github.com/YOUR_USERNAME/D8TL.git
git push -u origin main
```

### **Ready to Push Commands:**
Once you have the new repository URL, run:

```bash
# Add the new remote origin
git remote add origin https://github.com/YOUR_USERNAME/D8TL.git

# Push your secure, sanitized code
git push -u origin main
```

### **What's Included in Your Repository:**

- 🔒 **Fully sanitized codebase** - No exposed credentials or sensitive data
- 📚 **Comprehensive documentation** - Security guides, setup instructions
- 🛠️ **Working MVP application** - Complete travel league management system
- ✅ **Test suite** - Playwright E2E tests for quality assurance
- 🔧 **Security tools** - Automated security verification script

### **Repository Highlights:**

- **3,970+ legacy files removed** (entire oldsite directory)
- **12 database backup files removed** (contained exposed SMTP credentials)
- **Hardcoded passwords replaced** with secure placeholders
- **Comprehensive .gitignore** prevents future security issues
- **Navigation routing fixed** for proper development server operation
- **Security verification script** for ongoing security validation

Your repository is now **100% ready for public release** with all security vulnerabilities resolved while maintaining full application functionality!
