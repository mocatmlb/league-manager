# District 8 Travel League - Migration Scripts

This directory contains comprehensive migration scripts for importing data from the old FormTools system into the new MVP application.

## 🏗️ Old System Architecture Understanding

The migration scripts are built with proper understanding of the old system:

### Old System Tables:
- **`d8ll_form_3` (Games)**: Contains only game numbers, team IDs, and scores
- **`d8ll_form_2` (Schedules)**: Contains ALL scheduling information including:
  - **First entry per game**: `comment = 'ORIGINAL SCHEDULE'` = True original schedule
  - **Subsequent entries**: Various comments = Actual schedule changes

### New System Tables:
- **`games`**: Game information with team references
- **`schedules`**: Current schedule for each game
- **`schedule_history`**: Complete history of all schedule changes (v1 = Original, v2+ = Changes)
- **`schedule_change_requests`**: Formal change requests with original → requested mapping

## 📁 Migration Scripts

### Core Migration Scripts

#### `migrate_games_schedules_comprehensive.php`
**Primary migration script** - Handles complete migration for multiple seasons.

**Features:**
- ✅ Migrates games from `d8ll_form_3`
- ✅ Properly extracts original schedules from `ORIGINAL SCHEDULE` entries
- ✅ Creates sequential schedule history (v1 = Original, v2+ = Changes)
- ✅ Builds schedule change requests with correct original → requested mapping
- ✅ Supports multiple seasons (2024, 2023, 2022, 2021)
- ✅ Handles team mapping between old and new systems
- ✅ Updates current schedules based on approved changes

**Usage:**
```bash
php migrate_games_schedules_comprehensive.php
```

#### `migrate_2024_games_fixed.php`
**Legacy script** - Original 2024-specific migration (kept for reference).

### Validation & Backup Scripts

#### `validate_migration_comprehensive.php`
Comprehensive validation of migrated data integrity.

**Validates:**
- ✅ Games have valid teams and seasons
- ✅ All games have schedules
- ✅ Schedule history has proper version sequencing
- ✅ Schedule change requests have complete data
- ✅ Data consistency across all tables

**Usage:**
```bash
php validate_migration_comprehensive.php
```

#### `backup_before_migration.php`
Creates complete database backup before migration.

#### `rollback_migration.php`
Restores database from backup if migration fails.

### Orchestration Script

#### `run_comprehensive_migration.sh`
**Recommended approach** - Orchestrates the complete migration process.

**Process:**
1. 🔄 Creates backup
2. 🚀 Runs comprehensive migration
3. ✅ Validates results
4. 📊 Shows summary statistics
5. 🧹 Optional cleanup

**Usage:**
```bash
./run_comprehensive_migration.sh
```

## 🚀 Migration Process

### For New Migrations (Recommended)

1. **Prepare the environment:**
   ```bash
   cd /path/to/mvp-app
   ```

2. **Run comprehensive migration:**
   ```bash
   ./run_comprehensive_migration.sh
   ```

3. **Follow the prompts:**
   - Confirm backup creation ✅
   - Confirm migration execution ✅
   - Review validation results ✅

### For Manual Migration

1. **Create backup:**
   ```bash
   php backup_before_migration.php
   ```

2. **Run migration:**
   ```bash
   php migrate_games_schedules_comprehensive.php
   ```

3. **Validate results:**
   ```bash
   php validate_migration_comprehensive.php
   ```

4. **If issues found, rollback:**
   ```bash
   php rollback_migration.php
   ```

## 📊 Expected Results

After successful migration, you should have:

### Games & Schedules
- ✅ All games migrated with proper team mappings
- ✅ Each game has a current schedule
- ✅ Schedule data matches original FormTools data

### Schedule History
- ✅ **Version 1**: Original schedule from `ORIGINAL SCHEDULE` entries
- ✅ **Version 2+**: Actual schedule changes in chronological order
- ✅ Current version marked with `is_current = 1`
- ✅ No duplicate v1/v2 entries (fixed architecture understanding)

### Schedule Change Requests
- ✅ **Original data**: References true original schedule (v1)
- ✅ **Requested data**: Shows what was requested in each change
- ✅ Proper status tracking (Approved/Pending)
- ✅ Complete audit trail

## 🔧 Configuration

### Seasons to Migrate
Edit `migrate_games_schedules_comprehensive.php`:
```php
$seasons_to_migrate = ['2024', '2023', '2022', '2021']; // Add/remove as needed
```

### Database Connection
Ensure `includes/bootstrap.php` has correct database credentials.

### SQL Dump Location
Update the path in migration scripts:
```php
$sql_dump_file = '/path/to/your/sql/dump.sql';
```

## 🐛 Troubleshooting

### Common Issues

#### "Team mapping failed"
- Ensure teams exist in new system before migration
- Check team name consistency between old and new systems

#### "Season not found"
- Verify seasons exist in new system
- Check season year format (string vs integer)

#### "Validation failures"
- Review validation output for specific issues
- Some warnings may be acceptable (e.g., 2025 test data)

#### "Permission denied on shell script"
```bash
chmod +x run_comprehensive_migration.sh
```

### Database Issues

#### "Connection failed"
- Check database credentials in `includes/bootstrap.php`
- Ensure MySQL server is running
- Verify database exists

#### "SQL dump import failed"
- Check SQL dump file path and permissions
- Ensure sufficient disk space
- Verify MySQL user has import permissions

## 📝 Migration Log

The orchestration script creates detailed logs:
- **Location**: `./migration_YYYYMMDD_HHMMSS.log`
- **Contents**: Complete migration output and any errors
- **Retention**: Keep logs for audit purposes

## 🔄 Re-running Migrations

The scripts are designed to be **idempotent**:
- ✅ Skip existing games/schedules
- ✅ Won't create duplicates
- ✅ Safe to re-run if needed

However, for clean migrations:
1. Restore from backup first
2. Re-run complete migration process

## 🎯 Integration with MVP App

After successful migration:

### Schedule Request History
- ✅ **Manage Games**: Shows proper change counts
- ✅ **History Display**: Chronological schedule changes
- ✅ **Original vs Requested**: Correct data mapping
- ✅ **AJAX Endpoints**: Proper schedule history retrieval

### Data Consistency
- ✅ **Current Schedules**: Match latest approved changes
- ✅ **History Tracking**: Complete audit trail
- ✅ **Change Requests**: Proper original → requested flow

## 📚 Additional Resources

- **Old System Analysis**: See comments in migration scripts for FormTools structure details
- **Database Schema**: Check `docs/` for new system table definitions
- **API Integration**: Schedule history endpoints documented in `public/admin/games/index.php`

---

**Last Updated**: August 31, 2025
**Migration Scripts Version**: 2.0 (Comprehensive)
**Compatibility**: FormTools → MVP App

