#!/bin/bash
# ─────────────────────────────────────────────
# Nightly website + MySQL backup
# Upload this to your home directory on the
# hosting server and make it executable:
#   chmod +x ~/backup.sh
# ─────────────────────────────────────────────

# ── Configure these ──────────────────────────
DB_HOST="localhost"
DB_NAME="your_database_name"
DB_USER="your_db_username"
DB_PASS="your_db_password"

SITE_DIR="/home2/moc835/public_html"        # directory to back up
BACKUP_DIR="/home2/moc835/backups"          # where to store backups
KEEP_DAYS=7                         # delete backups older than this
# ─────────────────────────────────────────────

DATE=$(date +"%Y-%m-%d")
LOG="$BACKUP_DIR/backup.log"

mkdir -p "$BACKUP_DIR"

echo "──────────────────────────" >> "$LOG"
echo "Backup started: $(date)" >> "$LOG"

# ── Database dump ─────────────────────────────
DB_FILE="$BACKUP_DIR/db_${DB_NAME}_${DATE}.sql.gz"
mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
  | gzip > "$DB_FILE"

if [ $? -eq 0 ]; then
    echo "DB backup OK: $DB_FILE" >> "$LOG"
else
    echo "DB backup FAILED" >> "$LOG"
fi

# ── Files archive ─────────────────────────────
FILES_FILE="$BACKUP_DIR/files_${DATE}.tar.gz"
tar -czf "$FILES_FILE" -C "$(dirname "$SITE_DIR")" "$(basename "$SITE_DIR")" \
  --exclude="*.log" \
  --exclude=".git"

if [ $? -eq 0 ]; then
    echo "Files backup OK: $FILES_FILE" >> "$LOG"
else
    echo "Files backup FAILED" >> "$LOG"
fi

# ── Prune old backups ─────────────────────────
find "$BACKUP_DIR" -name "db_*.sql.gz"   -mtime +$KEEP_DAYS -delete
find "$BACKUP_DIR" -name "files_*.tar.gz" -mtime +$KEEP_DAYS -delete

echo "Backup finished: $(date)" >> "$LOG"
