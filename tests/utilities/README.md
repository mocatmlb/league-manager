# Test Utilities

This directory contains reusable utility scripts for testing and validation.

## Available Utilities

### `validate-migration.php`
Validates that data migration was completed successfully.
- Checks data integrity across all tables
- Verifies relationships between entities
- Reports any missing or inconsistent data

**Usage:**
```bash
php tests/utilities/validate-migration.php
```

### `validate-migration-comprehensive.php`
Comprehensive migration validation with detailed reporting.
- Performs all basic validation checks
- Provides detailed statistics and counts
- Identifies specific data integrity issues
- Generates summary reports

**Usage:**
```bash
php tests/utilities/validate-migration-comprehensive.php
```

### `test-email-system.php`
Tests the email system configuration and functionality.
- Validates SMTP configuration
- Sends test emails
- Verifies email delivery
- Updates configuration status

**Usage:**
1. Edit the script and set your email address in `$yourEmail`
2. Run: `php tests/utilities/test-email-system.php`

## Utility Scripts in `/scripts/`

The following utility scripts are available in the main `scripts/` directory:

### `scripts/backup-database.php`
Creates automated database backups before deployments.
- Generates timestamped backup files
- Maintains backup rotation (keeps last 10)
- Includes all tables, triggers, and routines

### `scripts/security-check.php`
Performs security verification checks.
- Validates configuration files
- Checks file permissions
- Verifies security headers
- Identifies potential security issues

### `scripts/deployment-monitor.php`
Monitors deployment status and health.
- Checks application availability
- Monitors database connectivity
- Validates critical functionality

### `scripts/clear-cache.php`
Clears application cache and temporary files.
- Removes cached data
- Cleans temporary files
- Resets application state

## Database Utilities

### `backup_before_migration.php`
Creates comprehensive database backup before migrations.
- Full database dump with structure and data
- Includes metadata and timestamps
- Safe for restoration

### `rollback_migration.php`
Restores database from backup files.
- Interactive confirmation required
- Restores complete database state
- Validates backup file integrity

**Usage:**
```bash
php rollback_migration.php backups/backup_2025-01-27_14-30-15.sql
```

## Best Practices

1. **Always backup before running utilities** that modify data
2. **Test in staging environment** before production use
3. **Review output carefully** for any warnings or errors
4. **Keep utilities updated** as the application evolves
5. **Document any new utilities** added to this directory

## Security Notes

- These utilities may contain sensitive operations
- Ensure proper access controls are in place
- Never expose utility scripts through web interface
- Use command-line execution only
- Validate all inputs and parameters
