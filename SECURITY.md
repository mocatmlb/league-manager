# Security Guide - District 8 Travel League

## Overview

This document outlines the security measures implemented in the D8TL application and provides guidance for secure deployment and maintenance.

## Critical Security Requirements

### 1. Configuration Security

#### Before Any Deployment

**MANDATORY**: Replace all placeholder values in configuration files:

- `mvp-app/includes/config.prod.php` - Production environment
- `mvp-app/includes/config.staging.php` - Staging environment

#### Placeholder Values That MUST Be Changed

```php
// Database Configuration
define('DB_NAME', 'REPLACE_WITH_ACTUAL_DATABASE_NAME');
define('DB_USER', 'REPLACE_WITH_ACTUAL_DB_USERNAME');
define('DB_PASS', 'REPLACE_WITH_ACTUAL_DB_PASSWORD');

// Email Configuration
define('SMTP_USERNAME', 'REPLACE_WITH_ACTUAL_EMAIL_ADDRESS');
define('SMTP_PASSWORD', 'REPLACE_WITH_ACTUAL_EMAIL_PASSWORD');

// Application Passwords
define('DEFAULT_ADMIN_PASSWORD', 'REPLACE_WITH_STRONG_ADMIN_PASSWORD_MIN_12_CHARS');
define('DEFAULT_COACHES_PASSWORD', 'REPLACE_WITH_STRONG_COACHES_PASSWORD_MIN_12_CHARS');
```

### 2. Password Requirements

#### Strong Password Policy

All passwords MUST meet these requirements:
- **Minimum 12 characters**
- **Mixed case letters** (A-Z, a-z)
- **Numbers** (0-9)
- **Special characters** (!@#$%^&*)
- **Unique per environment** (different for staging vs production)

#### Examples of Strong Passwords
```
✅ Good: MySecureP@ssw0rd2024!
✅ Good: D8TL#Admin$2024*Secure
❌ Bad: admin123
❌ Bad: password
❌ Bad: district8
```

### 3. File Security

#### Protected Files

The `.gitignore` file prevents these sensitive files from being committed:

```
# Database backups (may contain sensitive data)
mvp-app/backups/
*.sql

# Environment files with real credentials
.env
.env.*

# Configuration files with real credentials
mvp-app/includes/config.local.php
mvp-app/includes/config.production.php

# Logs (may contain sensitive information)
mvp-app/logs/
*.log

# User uploads
mvp-app/uploads/
```

#### File Permissions

Set secure permissions on configuration files:

```bash
# Make configuration files readable only by owner
chmod 600 mvp-app/includes/config.*.php

# Ensure web server can read but not write
chown www-data:www-data mvp-app/includes/config.*.php
```

## Security Features Implemented

### 1. Authentication & Authorization

- **Role-based access control** (Public, Coach, Admin)
- **Session-based authentication** with configurable timeouts
- **Password hashing** using PHP's `password_hash()` function
- **Secure session configuration** with HTTP-only cookies

### 2. Input Protection

- **Prepared SQL statements** prevent SQL injection
- **CSRF tokens** on all state-changing forms
- **Input validation** and sanitization
- **File upload restrictions** (type and size limits)

### 3. Session Security

```php
// Secure session configuration
ini_set('session.cookie_httponly', 1);  // Prevent XSS access to cookies
ini_set('session.cookie_secure', 1);    // HTTPS only (production)
ini_set('session.use_strict_mode', 1);  // Prevent session fixation
ini_set('session.cookie_samesite', 'Strict'); // CSRF protection
```

### 4. Error Handling

- **Production**: Errors logged, not displayed to users
- **Development**: Full error reporting for debugging
- **Staging**: Balanced approach for testing

## Deployment Security Checklist

### Pre-Deployment

- [ ] All placeholder credentials replaced with real values
- [ ] Strong passwords generated and documented securely
- [ ] Configuration files have correct permissions
- [ ] Database backups removed from repository
- [ ] `.gitignore` file in place and comprehensive

### Post-Deployment

- [ ] Default passwords changed immediately
- [ ] SMTP configuration tested
- [ ] Database connection verified
- [ ] Admin access confirmed
- [ ] Coach access confirmed
- [ ] SSL/HTTPS enabled and working
- [ ] Security headers configured

### Ongoing Maintenance

- [ ] Regular password rotation (quarterly)
- [ ] Security updates applied promptly
- [ ] Log files monitored for suspicious activity
- [ ] Database backups stored securely (not in repository)
- [ ] Access logs reviewed regularly

## Security Incident Response

### If Credentials Are Compromised

1. **Immediate Actions**:
   - Change all affected passwords immediately
   - Revoke any API keys or tokens
   - Check logs for unauthorized access
   - Notify relevant stakeholders

2. **Investigation**:
   - Determine scope of compromise
   - Identify how credentials were exposed
   - Document timeline of events

3. **Recovery**:
   - Implement additional security measures
   - Update security procedures
   - Monitor for ongoing threats

### If Repository Contains Sensitive Data

1. **Remove sensitive data** from repository immediately
2. **Use `git filter-branch`** to remove from history if needed
3. **Change all exposed credentials**
4. **Update `.gitignore`** to prevent future exposure
5. **Audit entire repository** for other sensitive data

## Security Tools and Monitoring

### Recommended Tools

- **GitGuardian**: Automated secret scanning
- **TruffleHog**: Git repository secret detection
- **Gitleaks**: Detect hardcoded secrets
- **OWASP ZAP**: Web application security testing

### Log Monitoring

Monitor these log files for security events:
- `mvp-app/logs/php_errors.log` - PHP errors and warnings
- Web server access logs - Unusual access patterns
- Database logs - Failed login attempts, unusual queries

## Contact Information

For security-related questions or to report vulnerabilities:
- **Project Maintainer**: [Contact Information]
- **Security Issues**: Create a private issue or contact directly

## Security Updates

This document should be reviewed and updated:
- After any security-related changes
- Quarterly as part of security review
- When new threats or vulnerabilities are identified

---

**Last Updated**: January 2025
**Version**: 1.0
**Review Date**: April 2025
