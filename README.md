# District 8 Travel League Management System

## Overview

The District 8 Travel League Management System is a PHP-based web application designed to replace the legacy system with an improved, secure, and user-friendly platform for managing youth baseball leagues. Built specifically for shared hosting environments, the system provides comprehensive functionality for team management, game scheduling, score tracking, and league administration.

## Current Status: MVP Implementation

The MVP (Minimum Viable Product) is currently in development and represents a direct replacement of the current system functionality. The MVP is built with **PHP 8.0+ and MySQL** for maximum compatibility with shared hosting environments like A Small Orange.

### Key Features ✅
- **Public Website**: View schedules, standings, and league information
- **Three-Tier Authentication**: Public access, coach password protection, and admin console
- **Administrative Dashboard**: Comprehensive management interface for teams, games, and schedules
- **Schedule Change Management**: Submit and approve schedule change requests
- **Score Management**: Input scores and automatically calculate standings
- **Email Notification System**: Automated notifications with configurable templates
- **Git-Based Deployment**: Automated deployment with staging environment for testing

## Technology Stack

- **Frontend**: PHP 8.0+ with HTML/CSS and vanilla JavaScript
- **Backend**: PHP 8.0+ with MVC-style architecture and MySQLi
- **Database**: MariaDB 5.5+ (MySQL-compatible) with prepared statements
- **Authentication**: PHP session-based with role-based access control
- **Hosting**: A Small Orange Shared Web Hosting with cPanel
- **Email**: PHPMailer 6.8+ for SMTP email functionality
- **Deployment**: Git-based automated deployment with .cpanel.yml
- **Version Control**: Git with GitHub and cPanel Git Version Control integration

## Quick Start

### Local Installation

#### Prerequisites
- **PHP 8.0+** (XAMPP, WAMP, MAMP, or native installation)
- **MySQL/MariaDB 5.5+** (included with XAMPP/WAMP/MAMP)
- **Apache Web Server** (included with XAMPP/WAMP/MAMP)
- **Composer** (PHP dependency manager)
- **Git** for version control

#### Setup Steps
```bash
# Clone repository
git clone https://github.com/your-org/league-manager.git
cd league-manager

# Install PHP dependencies
composer install

# Copy and configure environment
cp includes/config.example.php includes/config.php
# Edit config.php with your local database settings

# Create database and import schema
mysql -u root -p -e "CREATE DATABASE d8tl_local;"
mysql -u root -p d8tl_local < database/schema.sql

# Start local development server
# Option 1: Use your local Apache (point to public directory)
# Option 2: Use PHP built-in server
php -S localhost:8000 -t public/
```

#### Access the Application
- **Main Application**: http://localhost:8000 (or your Apache virtual host)
- **Admin Console**: http://localhost:8000/admin/
- **Coach Portal**: http://localhost:8000/coaches/

#### Default Login Credentials (Local Development)
- **Admin Password**: `admin` (change in production)
- **Coach Password**: `coaches` (change in production)

### Remote Installation (A Small Orange Git Deployment)

The system uses **automated Git-based deployment** with A Small Orange shared hosting for production and staging environments.

#### Prerequisites
- **A Small Orange Shared Hosting** with cPanel access
- **PHP 8.0+** (configurable via cPanel PHP Selector)
- **MariaDB/MySQL 5.5+** (included with hosting)
- **Git Version Control** (available in cPanel)
- **SMTP Email** (included with hosting or external service)

#### Quick Deployment
1. **Set up cPanel Git repositories** for production and staging
2. **Create databases** via cPanel MySQL Databases
3. **Configure environment files** with production credentials
4. **Import database schema** via phpMyAdmin
5. **Deploy via cPanel Git Version Control**

For detailed step-by-step remote installation instructions, see the **[Git Deployment Setup Guide](./GIT_DEPLOYMENT_SETUP.md)**.

#### Environment Configuration
- **Production**: `district8travelleague.com` (main branch)
- **Staging**: `staging.district8travelleague.com` (staging branch)
- **Development**: Local environment (develop branch)

⚠️ **CRITICAL SECURITY NOTE**: All placeholder values in configuration files MUST be replaced with actual credentials before deployment. Never commit real credentials to version control.

## Directory Structure

```
league-manager/
├── .cpanel.yml                 # Deployment configuration
├── composer.json               # PHP dependencies
├── public/                     # Web-accessible files
│   ├── index.php              # Main entry point
│   ├── schedule.php           # Public schedule
│   ├── standings.php          # Public standings
│   ├── admin/                 # Admin console
│   ├── coaches/               # Coach portal
│   └── assets/                # CSS, JS, images
├── includes/                   # PHP includes and configuration
│   ├── config.php             # Main configuration
│   ├── config.prod.php        # Production configuration
│   ├── config.staging.php     # Staging configuration
│   ├── database.php           # Database connection class
│   └── [other includes]
├── database/                   # Database schema and migrations
├── scripts/                    # Utility and maintenance scripts
├── templates/                  # Email templates
├── uploads/                    # File uploads directory
├── logs/                       # Application logs
├── backups/                    # Database backups
├── vendor/                     # Composer dependencies
├── docs/                       # Documentation
├── tests/                      # Test files
└── [migration scripts]        # Data migration utilities
```

## Documentation

### 📋 Complete Documentation
- **[System Requirements](./docs/requirements.md)** - Complete MVP specifications and feature requirements
- **[Technical Overview](./docs/tech.md)** - Comprehensive technical documentation with PHP/MySQL stack details
- **[Git Deployment Setup Guide](./GIT_DEPLOYMENT_SETUP.md)** - Complete setup guide for Git-based deployment

### 🚀 Quick Reference
- **[Deployment Strategy Summary](./DEPLOYMENT_STRATEGY_SUMMARY.md)** - Overview of deployment architecture
- **[Security Documentation](./SECURITY.md)** - Security best practices and guidelines

## System Architecture

The system uses a hierarchical structure:
- **Programs**: Top-level sports programs (Baseball, Softball)
- **Seasons**: Time-bound periods within programs
- **Divisions**: Organizational groupings within seasons
- **Teams**: Individual teams participating in divisions
- **Games**: Individual game records with unique numbering
- **Locations**: Centralized baseball field management
- **Schedules**: Versioned scheduling system with approval workflow

## Authentication System

### Three-Tier Access (MVP)
- **Public Access**: View schedules, standings, and league information (no login required)
- **Coach Access**: Password-protected access for schedule changes and score submission
- **Admin Console**: Full administrative access with comprehensive management capabilities

### Future Enhancement (Post-MVP)
- **Six Primary Roles**: Public User, Team Official, Team Manager, Umpire, Umpire Assignor, Administrator
- **Multi-Role Support**: Users can hold multiple roles simultaneously
- **Individual User Accounts**: Personal accounts with role-based permissions

## Support & Contributing

### Getting Help
- **Documentation**: Check the `/docs` folder for detailed requirements and technical specifications
- **Issues**: Report bugs and feature requests via GitHub Issues
- **Local Setup**: See local installation section above for development troubleshooting

### Development Guidelines
- **Code Style**: Follow established coding standards and linting rules
- **Testing**: Write comprehensive tests for all new features
- **Documentation**: Update documentation for any changes
- **Security**: Follow security best practices and conduct code reviews

## Project Status

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

### 📅 Future Phases
- **v2.1.0**: Enhanced multi-role authentication and individual user accounts
- **v2.2.0**: Advanced features and comprehensive league management
- **v2.3.0**: Umpire management system and advanced officiating features

## License

This project is proprietary software developed for the District 8 Travel League. All rights reserved.

---

For detailed technical requirements, implementation specifications, and feature documentation, see the [System Requirements](./docs/requirements.md) and [Technical Overview](./docs/tech.md) documents.