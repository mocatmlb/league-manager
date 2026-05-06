<?php
/**
 * ATDD Red-Phase Test Scaffolds: Story 1.1 — Apply Database Migrations
 *
 * TDD RED PHASE: All tests are SKIPPED (marked with $skip = true).
 * These tests assert EXPECTED behavior after migrations are applied.
 * Remove the skip flag per test as each migration is implemented.
 *
 * Story: 1.1 — Apply Database Migrations
 * Story File: _bmad-output/implementation-artifacts/1-1-apply-database-migrations.md
 * Generated: 2026-05-04
 * ATDD Checklist: _bmad-output/test-artifacts/atdd-checklist-1-1-apply-database-migrations.md
 */

// D8TL_APP is defined by run-unit-tests.php; guard against duplicate definition
if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}
require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';

// ---------------------------------------------------------------------------
// Test: AC1 — schema_migrations table created by migration 000
// ---------------------------------------------------------------------------

register_test('AC1-P0: migration 000 creates schema_migrations table', function () {

    $db = Database::getInstance();
    $stmt = $db->query("SHOW TABLES LIKE 'schema_migrations'");
    assert_equals($stmt->rowCount(), 1, "schema_migrations table should exist after migration 000");
});

register_test('AC1-P0: schema_migrations has correct columns', function () {

    $db = Database::getInstance();
    $stmt = $db->query("DESCRIBE schema_migrations");
    $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    assert_true(in_array('version', $columns), "schema_migrations must have 'version' column");
    assert_true(in_array('applied_at', $columns), "schema_migrations must have 'applied_at' column");
});

register_test('AC1-P0: migration 000 records version "000" in schema_migrations', function () {

    $db = Database::getInstance();
    $row = $db->fetchOne("SELECT version FROM schema_migrations WHERE version = '000'");

    assert_not_null($row, "Version '000' should be recorded in schema_migrations after migration 000");
    assert_equals($row['version'], '000', "Recorded version should be '000'");
});

// ---------------------------------------------------------------------------
// Test: AC2 — migrations 001–007 applied in sequence
// ---------------------------------------------------------------------------

register_test('AC2-P0: migration 001 creates league_list table with correct columns', function () {

    $db = Database::getInstance();
    $stmt = $db->query("SHOW TABLES LIKE 'league_list'");
    assert_equals($stmt->rowCount(), 1, "league_list table should exist after migration 001");

    $stmt = $db->query("DESCRIBE league_list");
    $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    foreach (['id', 'display_name', 'sort_order', 'is_active', 'created_at', 'updated_at'] as $col) {
        assert_true(in_array($col, $columns), "league_list must have '$col' column");
    }
});

register_test('AC2-P1: migration 001 creates idx_league_list_active_order index', function () {

    $db = Database::getInstance();
    $stmt = $db->query("SHOW INDEX FROM league_list WHERE Key_name = 'idx_league_list_active_order'");
    assert_true($stmt->rowCount() >= 1, "idx_league_list_active_order index should exist on league_list");
});

register_test('AC2-P0: migration 002 creates login_attempts table with architecture-canonical columns', function () {

    $db = Database::getInstance();
    $stmt = $db->query("DESCRIBE login_attempts");
    $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    assert_true(in_array('identifier', $columns), "login_attempts must have 'identifier' column (architecture-canonical)");
    assert_true(in_array('ip_address', $columns), "login_attempts must have 'ip_address' column");
    assert_true(in_array('attempted_at', $columns), "login_attempts must have 'attempted_at' column");
});

register_test('AC2-P1: migration 002 creates composite indexes on login_attempts', function () {

    $db = Database::getInstance();

    $stmt = $db->query("SHOW INDEX FROM login_attempts WHERE Key_name = 'idx_login_attempts_ip_time'");
    assert_true($stmt->rowCount() >= 1, "idx_login_attempts_ip_time index should exist");

    $stmt = $db->query("SHOW INDEX FROM login_attempts WHERE Key_name = 'idx_login_attempts_identifier_time'");
    assert_true($stmt->rowCount() >= 1, "idx_login_attempts_identifier_time index should exist");
});

register_test('AC2-P0: migration 003 adds status column to teams table', function () {

    $db = Database::getInstance();
    $stmt = $db->query("DESCRIBE teams");
    $columnDefs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $statusCol = array_filter($columnDefs, fn($c) => $c['Field'] === 'status');

    assert_true(count($statusCol) === 1, "teams table must have 'status' column after migration 003");

    $col = array_values($statusCol)[0];
    assert_true(
        str_contains(strtolower($col['Type']), "enum('pending','active','inactive')"),
        "teams.status must be ENUM('pending','active','inactive')"
    );
    assert_equals($col['Default'], 'active', "teams.status default must be 'active'");
});

register_test('AC2-P0: migration 003 sets status=active for all existing teams rows', function () {

    $db = Database::getInstance();
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM teams WHERE status != 'active' OR status IS NULL");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    assert_equals((int)$row['cnt'], 0, "All existing teams rows must have status='active' after migration 003");
});

register_test('AC2-P0: migration 004 adds submitted_by_user_id and status columns to locations', function () {

    $db = Database::getInstance();
    $stmt = $db->query("DESCRIBE locations");
    $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    assert_true(in_array('submitted_by_user_id', $columns), "locations must have 'submitted_by_user_id' column");
    assert_true(in_array('status', $columns), "locations must have 'status' column");
});

register_test('AC2-P1: migration 004 adds FK fk_locations_submitted_by', function () {

    $db = Database::getInstance();
    $stmt = $db->query(
        "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_NAME = 'locations'
           AND CONSTRAINT_NAME = 'fk_locations_submitted_by'
           AND TABLE_SCHEMA = DATABASE()"
    );
    assert_true($stmt->rowCount() >= 1, "FK fk_locations_submitted_by should exist on locations.submitted_by_user_id");
});

register_test('AC2-P0: migration 004 sets status=active for all existing locations rows', function () {

    $db = Database::getInstance();
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM locations WHERE status IS NULL OR status != 'active'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    assert_equals((int)$row['cnt'], 0, "All existing locations rows must have status='active' after migration 004");
});

register_test('AC2-P0: migration 005 creates remember_tokens with token_hash column', function () {

    $db = Database::getInstance();
    $stmt = $db->query("SHOW TABLES LIKE 'remember_tokens'");
    assert_equals($stmt->rowCount(), 1, "remember_tokens table must exist");

    $stmt = $db->query("DESCRIBE remember_tokens");
    $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    assert_true(in_array('token_hash', $columns), "remember_tokens must have 'token_hash' column (SHA-256 design)");
    assert_true(in_array('user_id', $columns), "remember_tokens must have 'user_id' column");
    assert_true(in_array('expires_at', $columns), "remember_tokens must have 'expires_at' column");
});

register_test('AC2-P1: migration 005 adds unique key uq_remember_token on token_hash', function () {

    $db = Database::getInstance();
    $stmt = $db->query("SHOW INDEX FROM remember_tokens WHERE Key_name = 'uq_remember_token' AND Non_unique = 0");
    assert_true($stmt->rowCount() >= 1, "Unique key uq_remember_token should exist on remember_tokens");
});

register_test('AC2-P1: migration 005 adds FK fk_remember_tokens_user with ON DELETE CASCADE', function () {

    $db = Database::getInstance();
    $stmt = $db->query(
        "SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_NAME = 'fk_remember_tokens_user'
           AND CONSTRAINT_SCHEMA = DATABASE()"
    );
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    assert_not_null($row, "FK fk_remember_tokens_user must exist on remember_tokens");
    assert_equals($row['DELETE_RULE'], 'CASCADE', "FK must have ON DELETE CASCADE");
});

register_test('AC2-P1: migration 006 records version "006" (deprecation record) in schema_migrations', function () {

    $db = Database::getInstance();
    $row = $db->fetchOne("SELECT version FROM schema_migrations WHERE version = '006'");

    assert_not_null($row, "Version '006' should be recorded in schema_migrations after migration 006");
});

register_test('AC2-P0: migration 007 creates activity_log table with correct columns', function () {

    $db = Database::getInstance();
    $stmt = $db->query("SHOW TABLES LIKE 'activity_log'");
    assert_equals($stmt->rowCount(), 1, "activity_log table must exist after migration 007");

    $stmt = $db->query("DESCRIBE activity_log");
    $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    // The existing activity_log from schema.sql uses log_id as PK; migration 007 extends it
    // by adding the 'event' and 'context' columns required by ActivityLogger. The table must
    // have a PK (either 'id' or 'log_id'), plus 'event', 'context', and 'created_at'.
    $hasPk = in_array('id', $columns) || in_array('log_id', $columns);
    assert_true($hasPk, "activity_log must have a primary key column ('id' or 'log_id')");
    assert_true(in_array('event', $columns), "activity_log must have 'event' column (added by migration 007)");
    assert_true(in_array('context', $columns), "activity_log must have 'context' column (added by migration 007)");
    assert_true(in_array('created_at', $columns), "activity_log must have 'created_at' column");
});

register_test('AC2-P1: activity_log.context column is nullable JSON type', function () {

    $db = Database::getInstance();
    $stmt = $db->query("DESCRIBE activity_log");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $contextCol = array_values(array_filter($cols, fn($c) => $c['Field'] === 'context'));

    assert_true(count($contextCol) === 1, "activity_log.context column must exist");
    assert_true(
        str_contains(strtolower($contextCol[0]['Type']), 'json'),
        "activity_log.context must be JSON type"
    );
    assert_equals($contextCol[0]['Null'], 'YES', "activity_log.context must be nullable");
});

register_test('AC2-P0: all 8 versions (000-007) are recorded in schema_migrations', function () {

    $db = Database::getInstance();
    $stmt = $db->query("SELECT version FROM schema_migrations ORDER BY version ASC");
    $versions = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'version');

    $expected = ['000', '001', '002', '003', '004', '005', '006', '007'];
    foreach ($expected as $v) {
        assert_true(in_array($v, $versions), "Version '$v' must be recorded in schema_migrations");
    }
});

// ---------------------------------------------------------------------------
// Test: AC3 — idempotency (running migrations a second time produces no error)
// ---------------------------------------------------------------------------

register_test('AC3-P0: re-running migration 000 SQL produces no error (idempotent)', function () {

    $migrationPath = __DIR__ . '/../../database/migrations/000_create_schema_migrations.sql';
    assert_true(file_exists($migrationPath), "Migration file 000 must exist at $migrationPath");

    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $sql = file_get_contents($migrationPath);

    // Split on semicolons to execute statement-by-statement; ignore INSERT IGNORE duplicates
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $error = null;
    foreach ($statements as $statement) {
        if (empty($statement)) {
            continue;
        }
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            // Duplicate key on INSERT IGNORE is acceptable — PDO may still throw depending on mode
            if (!str_contains($e->getMessage(), 'Duplicate entry')) {
                $error = $e->getMessage();
                break;
            }
        }
    }

    assert_null($error, "Re-running migration 000 must not produce an unexpected error: $error");

    // Verify no duplicate schema_migrations entry for version 000
    $row = $db->fetchOne("SELECT COUNT(*) as cnt FROM schema_migrations WHERE version = '000'");
    assert_equals((int)$row['cnt'], 1, "schema_migrations must have exactly 1 row for version '000' after idempotent re-run");
});

register_test('AC3-P1: re-running migration 001 SQL produces no error (IF NOT EXISTS guard)', function () {

    $migrationPath = __DIR__ . '/../../database/migrations/001_add_league_list.sql';
    assert_true(file_exists($migrationPath), "Migration file 001 must exist");

    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $sql = file_get_contents($migrationPath);
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $error = null;
    foreach ($statements as $statement) {
        if (empty($statement)) {
            continue;
        }
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            if (!str_contains($e->getMessage(), 'Duplicate entry')) {
                $error = $e->getMessage();
                break;
            }
        }
    }

    assert_null($error, "Re-running migration 001 must not produce an unexpected error: $error");
});

// ---------------------------------------------------------------------------
// Helper assertions (lightweight, no PHPUnit dependency)
// ---------------------------------------------------------------------------

function assert_equals($actual, $expected, string $message): void {
    if ($actual !== $expected) {
        throw new RuntimeException("ASSERTION FAILED — $message\n  Expected: " . var_export($expected, true) . "\n  Got:      " . var_export($actual, true));
    }
}

function assert_true(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("ASSERTION FAILED — $message");
    }
}

function assert_not_null($value, string $message): void {
    if ($value === null || $value === false) {
        throw new RuntimeException("ASSERTION FAILED — $message (got null/false)");
    }
}

function assert_null($value, string $message): void {
    if ($value !== null) {
        throw new RuntimeException("ASSERTION FAILED — $message");
    }
}
