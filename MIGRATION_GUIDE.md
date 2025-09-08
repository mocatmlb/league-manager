# District 8 Travel League - Data Migration Guide

This guide walks you through migrating data from the old FormTools system to the new MVP application.

## Overview

The migration process transfers the following data:
- **Programs** (Junior, Senior, Majors Baseball)
- **Seasons** (2025 season data)
- **Divisions** (American, National, Central Leagues)
- **Locations** (Field locations from schedules)
- **Teams** (Team registrations and manager info)
- **Games & Schedules** (Game matchups, scores, and scheduling)

## Prerequisites

1. **Database Access**: Ensure you have access to both:
   - Old FormTools database (`moc835_ftoo886`)
   - New MVP database (`moc835_d8tl_prod`)

2. **PHP Environment**: Scripts require PHP with MySQLi extension

3. **Backup Space**: Ensure sufficient disk space for database backups

## Migration Steps

### Step 1: Create Backup

**CRITICAL**: Always create a backup before migration!

```bash
cd /path/to/mvp-app
php backup_before_migration.php
```

This creates a timestamped backup in the `backups/` directory.

### Step 2: Review Configuration

Edit the database configuration in `migrate_data.php`:

```php
$old_db_config = [
    'host' => 'localhost',
    'username' => 'REPLACE_WITH_OLD_DB_USERNAME',
    'password' => 'REPLACE_WITH_OLD_DB_PASSWORD',
    'database' => 'REPLACE_WITH_OLD_DATABASE_NAME'
];

$current_season = 2025; // Set the season to migrate
```

**SECURITY WARNING**: Never commit real database credentials to version control. Use environment variables or secure configuration files for production deployments.

### Step 3: Run Migration

Execute the migration script:

```bash
php migrate_data.php
```

The script will:
1. Connect to both databases
2. Migrate programs, seasons, and divisions
3. Import locations from old schedules
4. Transfer team registrations
5. Import games and schedules
6. Display progress and results

### Step 4: Validate Migration

Run the validation script to verify data integrity:

```bash
php validate_migration.php
```

This checks:
- Record counts for each data type
- Data integrity (no orphaned records)
- Sample data verification
- Migration completeness

## Data Mapping

### Programs
| Old System | New System |
|------------|------------|
| program_id = '1' | Junior Baseball (JR) |
| program_id = '2' | Senior Baseball (SR) |
| program_id = '3' | Majors Baseball (MAJ) |

### Teams
| Old Field | New Field | Notes |
|-----------|-----------|-------|
| league_name | league_name | Primary team identifier |
| team_name | team_name | Display name (if provided) |
| manager_* | manager_* | Manager contact info |
| home_field | home_field | Primary field |
| home_field_5070 | home_field_5070 | 50/70 field |
| avail_* | avail_* | Availability preferences |

### Games
| Old System | New System | Notes |
|------------|------------|-------|
| d8ll_form_3 | games table | Game results |
| d8ll_form_2 | schedules table | Game scheduling |
| game_no | game_number | Unique game identifier |

## Troubleshooting

### Common Issues

1. **Connection Errors**
   - Verify database credentials
   - Check network connectivity
   - Ensure databases exist

2. **Missing Teams in Games**
   - Some games may reference teams not in current season
   - Check team name matching (case-sensitive)
   - Review team registration data

3. **Location Mapping**
   - Locations are auto-created from schedule data
   - Manual cleanup may be needed for similar names
   - Check location standardization

### Rollback Procedure

If migration fails or data is incorrect:

```bash
# List available backups
ls -la backups/

# Rollback to specific backup
php rollback_migration.php backups/backup_2025-01-27_14-30-15.sql
```

**Warning**: Rollback completely restores the database to backup state!

## Post-Migration Tasks

### 1. Data Cleanup
- Review and standardize location names
- Verify team information accuracy
- Check division assignments

### 2. System Configuration
- Update current season settings
- Configure email templates
- Set up admin users

### 3. Testing
- Test schedule display
- Verify score submission
- Check team management functions

## Migration Statistics

After successful migration, you should see approximately:

- **Programs**: 3 (Junior, Senior, Majors)
- **Seasons**: 3 (one per program for current year)
- **Divisions**: 6-9 (2-3 per program)
- **Teams**: 50-100+ (varies by season)
- **Games**: 200-500+ (varies by season)
- **Locations**: 20-50+ (unique field locations)

## Data Validation Checklist

- [ ] All programs created correctly
- [ ] Seasons exist for current year
- [ ] Divisions properly assigned
- [ ] Teams have valid manager information
- [ ] Games link to correct teams
- [ ] Schedules have dates and locations
- [ ] No orphaned records
- [ ] Score data preserved where available

## Support

If you encounter issues:

1. Check the validation report for specific problems
2. Review migration logs for error messages
3. Verify source data in old system
4. Consider partial re-migration of specific data types

## File Descriptions

- `migrate_data.php` - Main migration script
- `backup_before_migration.php` - Creates database backup
- `rollback_migration.php` - Restores from backup
- `validate_migration.php` - Validates migrated data
- `MIGRATION_GUIDE.md` - This documentation

## Security Notes

- **CRITICAL**: Replace all placeholder credentials with actual values before running migration
- Migration scripts should use environment variables for credentials in production
- Remove or secure scripts after migration
- Backup files contain sensitive data - automatically created with secure permissions (600)
- Backup directories created with restricted access (750)
- Never commit real database credentials to version control

## Performance Considerations

- Migration may take several minutes for large datasets
- Consider running during low-traffic periods
- Monitor disk space during backup creation
- Large result sets may require memory optimization

---

**Remember**: Always test migration on a copy of production data first!
