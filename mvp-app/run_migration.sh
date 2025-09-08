#!/bin/bash

# District 8 Travel League - Migration Execution Script
# This script runs the complete migration process with safety checks

echo "=== District 8 Travel League Data Migration ==="
echo "This will migrate data from the old FormTools system to the MVP app"
echo ""

# Check if PHP is available
if ! command -v php &> /dev/null; then
    echo "ERROR: PHP is not installed or not in PATH"
    exit 1
fi

# Check if we're in the right directory
if [ ! -f "migrate_data.php" ]; then
    echo "ERROR: Migration scripts not found. Please run from mvp-app directory."
    exit 1
fi

# Create backups directory
mkdir -p backups

echo "Step 1: Creating backup..."
php backup_before_migration.php

if [ $? -ne 0 ]; then
    echo "ERROR: Backup failed. Aborting migration."
    exit 1
fi

echo ""
echo "Step 2: Running migration..."
echo "WARNING: This will modify your database!"
echo "A backup has been created in the backups/ directory."
echo ""
read -p "Do you want to continue with the migration? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo "Migration cancelled."
    exit 0
fi

echo ""
echo "Starting migration process..."
php migrate_data.php

if [ $? -ne 0 ]; then
    echo ""
    echo "ERROR: Migration failed!"
    echo "You can rollback using: php rollback_migration.php backups/[backup_file]"
    exit 1
fi

echo ""
echo "Step 3: Validating migration..."
php validate_migration.php

echo ""
echo "=== Migration Process Complete ==="
echo ""
echo "Next steps:"
echo "1. Review the validation report above"
echo "2. Test the MVP application with migrated data"
echo "3. If issues are found, you can rollback using:"
echo "   php rollback_migration.php backups/[backup_file]"
echo ""
echo "Migration files can be found in:"
echo "- Migration logs: Check console output above"
echo "- Backup files: backups/ directory"
echo "- Validation report: Run 'php validate_migration.php' anytime"
echo ""
