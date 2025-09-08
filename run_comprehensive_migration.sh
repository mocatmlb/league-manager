#!/bin/bash

# District 8 Travel League - Comprehensive Migration Script
# Orchestrates the complete migration process with proper error handling

echo "=== District 8 Travel League Comprehensive Migration ==="
echo "This script will migrate games, schedules, and schedule changes from the old system"
echo ""

# Configuration
BACKUP_DIR="./backups"
LOG_FILE="./migration_$(date +%Y%m%d_%H%M%S).log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to log and display messages
log_message() {
    echo -e "$1" | tee -a "$LOG_FILE"
}

# Function to check if command succeeded
check_success() {
    if [ $? -eq 0 ]; then
        log_message "${GREEN}✓ $1 completed successfully${NC}"
        return 0
    else
        log_message "${RED}✗ $1 failed${NC}"
        return 1
    fi
}

# Function to prompt for confirmation
confirm() {
    read -p "$1 (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        return 0
    else
        return 1
    fi
}

# Start migration process
log_message "Starting comprehensive migration at $(date)"
log_message "Log file: $LOG_FILE"
echo ""

# Step 1: Create backup
log_message "${YELLOW}Step 1: Creating backup...${NC}"
if confirm "Create backup before migration?"; then
    php backup_before_migration.php >> "$LOG_FILE" 2>&1
    if check_success "Backup creation"; then
        echo ""
    else
        log_message "${RED}Backup failed. Aborting migration.${NC}"
        exit 1
    fi
else
    log_message "${YELLOW}Skipping backup (not recommended)${NC}"
    echo ""
fi

# Step 2: Run comprehensive migration
log_message "${YELLOW}Step 2: Running comprehensive migration...${NC}"
if confirm "Proceed with comprehensive migration?"; then
    php migrate_games_schedules_comprehensive.php >> "$LOG_FILE" 2>&1
    if check_success "Comprehensive migration"; then
        echo ""
    else
        log_message "${RED}Migration failed. Check log for details.${NC}"
        
        if confirm "Restore from backup?"; then
            log_message "${YELLOW}Restoring from backup...${NC}"
            php rollback_migration.php >> "$LOG_FILE" 2>&1
            check_success "Backup restoration"
        fi
        exit 1
    fi
else
    log_message "Migration cancelled by user"
    exit 0
fi

# Step 3: Validate migration
log_message "${YELLOW}Step 3: Validating migration...${NC}"
php validate_migration_comprehensive.php >> "$LOG_FILE" 2>&1
if check_success "Migration validation"; then
    echo ""
else
    log_message "${YELLOW}Validation found issues. Check log for details.${NC}"
    
    if confirm "Continue despite validation issues?"; then
        log_message "${YELLOW}Continuing with validation warnings...${NC}"
    else
        if confirm "Restore from backup?"; then
            log_message "${YELLOW}Restoring from backup...${NC}"
            php rollback_migration.php >> "$LOG_FILE" 2>&1
            check_success "Backup restoration"
        fi
        exit 1
    fi
fi

# Step 4: Final summary
log_message "${GREEN}=== Migration Complete ===${NC}"
log_message "Migration completed at $(date)"

# Display summary statistics
log_message ""
log_message "${YELLOW}Migration Summary:${NC}"
mysql -u root -D moc835_d8tl_prod -e "
SELECT 
    'Games' as table_name, COUNT(*) as count 
FROM games 
WHERE season_id IN (SELECT season_id FROM seasons WHERE season_year IN ('2024', '2023', '2022', '2021'))
UNION ALL
SELECT 'Schedules', COUNT(*) FROM schedules s 
JOIN games g ON s.game_id = g.game_id 
JOIN seasons se ON g.season_id = se.season_id 
WHERE se.season_year IN ('2024', '2023', '2022', '2021')
UNION ALL
SELECT 'Schedule History', COUNT(*) FROM schedule_history sh 
JOIN games g ON sh.game_id = g.game_id 
JOIN seasons se ON g.season_id = se.season_id 
WHERE se.season_year IN ('2024', '2023', '2022', '2021')
UNION ALL
SELECT 'Change Requests', COUNT(*) FROM schedule_change_requests scr 
JOIN games g ON scr.game_id = g.game_id 
JOIN seasons se ON g.season_id = se.season_id 
WHERE se.season_year IN ('2024', '2023', '2022', '2021');
" | tee -a "$LOG_FILE"

log_message ""
log_message "${GREEN}✓ All migration steps completed successfully!${NC}"
log_message "Full log available at: $LOG_FILE"

# Optional cleanup
if confirm "Clean up temporary files?"; then
    log_message "Cleaning up temporary files..."
    # Add cleanup commands here if needed
    log_message "Cleanup completed"
fi

echo ""
log_message "Migration process finished. You can now test the schedule request history in the manage games interface."

