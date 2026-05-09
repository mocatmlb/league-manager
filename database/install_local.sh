#!/usr/bin/env bash
# Local MySQL setup for league-manager (defaults match includes/config.php).
#
# First-time install (database does not exist yet):
#   ./database/install_local.sh
#
# Wipe and recreate (DESTROYS all data in DB_NAME):
#   FORCE_FRESH=1 ./database/install_local.sh
#
# If the database already exists, only extension patches are applied
# (schedule_history, email tables, user accounts, league officials index).
#
# Optional: MYSQL_USER MYSQL_PWD MYSQL_BIN DB_NAME
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
MYSQL_BIN="${MYSQL_BIN:-$(command -v mysql || true)}"
if [[ -z "$MYSQL_BIN" && -x /opt/homebrew/opt/mysql@8.0/bin/mysql ]]; then
  MYSQL_BIN="/opt/homebrew/opt/mysql@8.0/bin/mysql"
fi
if [[ -z "$MYSQL_BIN" ]]; then
  echo "mysql client not found. Install MySQL or set MYSQL_BIN." >&2
  exit 1
fi

MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_ARGS=(-u"$MYSQL_USER")
if [[ -n "${MYSQL_PWD:-}" ]]; then
  MYSQL_ARGS+=(-p"$MYSQL_PWD")
fi

DB_NAME="${DB_NAME:-moc835_d8tl_prod}"

run_sql_file() {
  local file="$1"
  local db="${2:-}"
  echo "==> $(basename "$file")"
  if [[ -n "$db" ]]; then
    "$MYSQL_BIN" "${MYSQL_ARGS[@]}" "$db" < "$file"
  else
    "$MYSQL_BIN" "${MYSQL_ARGS[@]}" < "$file"
  fi
}

db_exists() {
  "$MYSQL_BIN" "${MYSQL_ARGS[@]}" -Nse \
    "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='${DB_NAME}';" 2>/dev/null | tail -1
}

cd "$ROOT_DIR"

if [[ "${FORCE_FRESH:-0}" == "1" ]]; then
  echo "==> FORCE_FRESH: dropping ${DB_NAME}"
  "$MYSQL_BIN" "${MYSQL_ARGS[@]}" -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`;"
fi

EXISTING="$(db_exists || echo 0)"
if [[ "${EXISTING}" == "0" || "${FORCE_FRESH:-0}" == "1" ]]; then
  echo "==> Installing core schema + seed (database/schema.sql)"
  run_sql_file "$ROOT_DIR/database/schema.sql"
else
  echo "==> Database ${DB_NAME} already exists — skipping schema.sql (use FORCE_FRESH=1 to recreate)"
fi

echo "==> Game status enum (ignore error if already aligned)"
set +e
"$MYSQL_BIN" "${MYSQL_ARGS[@]}" "$DB_NAME" < "$ROOT_DIR/database/games_game_status_enum_migration.sql"
set -e

echo "==> Schedule history + views"
run_sql_file "$ROOT_DIR/database/patch_schedule_history.sql" "$DB_NAME"

echo "==> Email notification tables"
run_sql_file "$ROOT_DIR/database/email_system_mvp.sql" "$DB_NAME"

echo "==> User accounts tables + seed roles"
run_sql_file "$ROOT_DIR/database/user_accounts_schema.sql" "$DB_NAME"

echo "==> League officials (adds table; sample rows only if table was empty)"
"$MYSQL_BIN" "${MYSQL_ARGS[@]}" "$DB_NAME" -e \
  "CREATE TABLE IF NOT EXISTS league_officials (
    official_id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(100) NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    display_on_contact_page BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    active_status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_official_active (active_status),
    INDEX idx_official_sort (sort_order),
    INDEX idx_official_display (display_on_contact_page)
  );" || true
if [[ "$( "$MYSQL_BIN" "${MYSQL_ARGS[@]}" "$DB_NAME" -Nse "SELECT COUNT(*) FROM league_officials;" )" == "0" ]]; then
  run_sql_file "$ROOT_DIR/database/league_officials.sql" "$DB_NAME"
else
  echo "    (league_officials already has rows — skipping sample inserts)"
fi

set +e
"$MYSQL_BIN" "${MYSQL_ARGS[@]}" "$DB_NAME" -e \
  "ALTER TABLE schedule_change_requests ADD INDEX idx_game_created (game_id, created_date);" 2>/dev/null
set -e

echo "==> Verify schedule_history"
"$MYSQL_BIN" "${MYSQL_ARGS[@]}" "$DB_NAME" -e "SHOW CREATE TABLE schedule_history\G" 2>/dev/null | head -5 || echo "    (no schedule_history — check patch errors above)"

echo
echo "Done. App defaults (includes/config.php): DB_HOST=localhost DB_NAME=${DB_NAME} DB_USER=root DB_PASS=(empty)"
echo "Admin login: admin / admin  |  Coaches password: coaches"
