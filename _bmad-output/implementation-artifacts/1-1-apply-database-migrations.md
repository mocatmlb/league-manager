# Story 1.1: Apply Database Migrations

Status: review

## Story

As a developer,
I want all required schema migrations applied to the database in sequence,
so that the database structure is ready for all Individual Coach Login feature work.

## Acceptance Criteria

1. **Given** the project has no `schema_migrations` tracking table  
   **When** migration `000_create_schema_migrations.sql` is applied  
   **Then** a `schema_migrations` table exists with `version VARCHAR(20) PRIMARY KEY` and `applied_at DATETIME` columns  
   **And** version `000` is recorded in `schema_migrations`

2. **Given** migration 000 has been applied  
   **When** migrations 001 through 006 are applied in sequence  
   **Then** the `league_list` table exists with columns: `id`, `display_name`, `sort_order`, `is_active`, `created_at`, `updated_at`, and index `idx_league_list_active_order`  
   **And** the `login_attempts` table has columns: `id`, `identifier`, `ip_address`, `attempted_at`, and composite indexes on `(ip_address, attempted_at)` and `(identifier, attempted_at)`  
   **And** the `teams` table has a `status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'` column added after `division_id`  
   **And** all existing rows in `teams` have `status = 'active'`  
   **And** the `locations` table has `submitted_by_user_id INT UNSIGNED NULL` and `status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'` columns added, with FK referencing `users(id) ON DELETE SET NULL`  
   **And** all existing rows in `locations` have `status = 'active'`  
   **And** the `remember_tokens` table exists with: `id`, `user_id`, `token_hash VARCHAR(64)`, `expires_at`, `created_at`, unique key `uq_remember_token`, index `idx_remember_tokens_user`, and FK referencing `users(id) ON DELETE CASCADE`  
   **And** migration `006_remove_legacy_auth.sql` is applied and its version recorded  
   **And** the `activity_log` table exists with columns: `id`, `event VARCHAR(100)`, `context JSON NULL`, `created_at DATETIME`, and indexes on `event` and `created_at`  
   **And** all 8 versions (000–007) appear in `schema_migrations`

3. **Given** a migration file has already been applied  
   **When** it is run a second time  
   **Then** it produces no error and no duplicate `schema_migrations` entry (idempotent via `IF NOT EXISTS` / `IF EXISTS`)

## Tasks / Subtasks

- [x] Create `database/migrations/` directory (AC: all)
- [x] Create `database/migrations/000_create_schema_migrations.sql` (AC: 1)
  - [x] `CREATE TABLE IF NOT EXISTS schema_migrations` with `version VARCHAR(20) PRIMARY KEY` and `applied_at DATETIME`
  - [x] Insert version `000` record
- [x] Create `database/migrations/001_add_league_list.sql` (AC: 2)
  - [x] Full `league_list` table DDL with index `idx_league_list_active_order (is_active, sort_order)`
  - [x] Idempotent: `CREATE TABLE IF NOT EXISTS`
  - [x] Insert version `001` into `schema_migrations`
- [x] Create `database/migrations/002_add_login_attempts.sql` (AC: 2)
  - [x] `login_attempts` table with columns: `id`, `identifier VARCHAR(255)`, `ip_address VARCHAR(45)`, `attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP`
  - [x] Composite indexes: `idx_login_attempts_ip_time (ip_address, attempted_at)` and `idx_login_attempts_identifier_time (identifier, attempted_at)`
  - [x] Reconciles conflict with `user_accounts_schema.sql` via stored procedure guard checking `information_schema` before adding column/indexes
  - [x] Insert version `002` into `schema_migrations`
- [x] Create `database/migrations/003_add_teams_status_column.sql` (AC: 2)
  - [x] Guarded ALTER via stored procedure — adds `status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active' AFTER division_id`
  - [x] `UPDATE teams SET status = 'active' WHERE status IS NULL OR status = ''` (idempotent)
  - [x] Insert version `003` into `schema_migrations`
- [x] Create `database/migrations/004_add_locations_submission_columns.sql` (AC: 2)
  - [x] Guarded ALTER adds `submitted_by_user_id INT NULL` (INT not UNSIGNED — must match `users.id INT` for FK compatibility)
  - [x] Guarded ALTER adds `status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'`
  - [x] Guarded FK `fk_locations_submitted_by` referencing `users(id) ON DELETE SET NULL`
  - [x] `UPDATE locations SET status = 'active' WHERE status IS NULL`
  - [x] Insert version `004` into `schema_migrations`
- [x] Create `database/migrations/005_add_remember_tokens.sql` (AC: 2)
  - [x] `CREATE TABLE IF NOT EXISTS` with canonical `token_hash VARCHAR(64)` design; guarded ALTER adds `token_hash` if table existed from `user_accounts_schema.sql`
  - [x] Guarded FK `fk_remember_tokens_user` on `user_id` with `ON DELETE CASCADE`
  - [x] Guarded index `idx_remember_tokens_user (user_id)` and `UNIQUE KEY uq_remember_token (token_hash)`
  - [x] Insert version `005` into `schema_migrations`
- [x] Create `database/migrations/006_remove_legacy_auth.sql` (AC: 2)
  - [x] No destructive DDL; formal deprecation record only
  - [x] Comment block documents `coaches_password` deprecation
  - [x] Insert version `006` into `schema_migrations`
- [x] Create `database/migrations/007_create_activity_log.sql` (AC: 2 — extends)
  - [x] Extends existing `activity_log` table (from schema.sql) by adding `event VARCHAR(100)` and `context JSON NULL` columns required by `ActivityLogger`
  - [x] Guarded index additions for `event` and `created_at`
  - [x] Insert version `007` into `schema_migrations`
  - [x] Idempotent: all alterations guarded via information_schema procedure checks
- [x] Verify each migration is idempotent — running twice produces no error (AC: 3)

## Dev Notes

### Critical Schema Conflicts to Resolve

**Existing `user_accounts_schema.sql` vs architecture specs — the dev agent MUST reconcile these:**

| Table | Existing (`user_accounts_schema.sql`) | Architecture Spec | Resolution |
|-------|---------------------------------------|-------------------|------------|
| `login_attempts` | `username VARCHAR(100)`, `success BOOLEAN`, simple indexes | `identifier VARCHAR(255)`, no `success` column, composite indexes with different names | Migration 002 uses `CREATE TABLE IF NOT EXISTS` with architecture-spec columns. If table already exists (from old schema), the migration will be a no-op. Dev agent: check if `identifier` column exists; if not, add it via ALTER. |
| `remember_tokens` | `token VARCHAR(100)` (plain token) | `token_hash VARCHAR(64)` (SHA-256 hash) | Migration 005 uses `CREATE TABLE IF NOT EXISTS`. If table already exists, add `token_hash VARCHAR(64)` and `UNIQUE KEY uq_remember_token`. The old `token` column can remain for backward compat; new code must only use `token_hash`. |

**The existing schema files (`schema.sql`, `user_accounts_schema.sql`) are SOURCE OF TRUTH for what's already in the DB.** Migrations are INCREMENTAL changes on top of that baseline.

### Schema Specs (from architecture.md)

**`schema_migrations` (migration 000):**
```sql
CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(20) NOT NULL PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**`league_list` (migration 001):**
```sql
CREATE TABLE IF NOT EXISTS league_list (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  display_name VARCHAR(100) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_league_list_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**`login_attempts` (migration 002) — architecture-canonical columns:**
```sql
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier VARCHAR(255) NOT NULL,  -- username or email submitted
  ip_address VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_attempts_ip_time (ip_address, attempted_at),
  INDEX idx_login_attempts_identifier_time (identifier, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**`teams.status` (migration 003):**
```sql
ALTER TABLE teams
  ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'
  AFTER division_id;
```
> Note: `teams` table PK column is `team_id` (not `id`) per `schema.sql`. All existing queries use `team_id`.

**`locations` additions (migration 004):**
```sql
ALTER TABLE locations
  ADD COLUMN IF NOT EXISTS submitted_by_user_id INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active';
ALTER TABLE locations
  ADD CONSTRAINT IF NOT EXISTS fk_locations_submitted_by
    FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE SET NULL;
UPDATE locations SET status = 'active' WHERE status IS NULL;
```
> Note: `locations` table PK column is `location_id` per `schema.sql`. The `users` table (referenced FK) has PK `id` per `user_accounts_schema.sql`.

**`remember_tokens` (migration 005) — architecture-canonical design:**
```sql
CREATE TABLE IF NOT EXISTS remember_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash VARCHAR(64) NOT NULL,  -- SHA-256 of the raw token stored in cookie
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_remember_token (token_hash),
  INDEX idx_remember_tokens_user (user_id),
  CONSTRAINT fk_remember_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Migration File Convention (from architecture.md)

- Filename: `database/migrations/NNN_snake_case_description.sql`
- Each file starts with a comment block: date, author, description, affected tables
- Each file ends with `INSERT IGNORE INTO schema_migrations (version) VALUES ('NNN');`
- Files are idempotent where possible — safe to re-run
- Applied manually via cPanel phpMyAdmin or SSH — not auto-run by application code
- Never modify an existing migration file once applied to production

### Existing Infrastructure

- `database/schema.sql` — core MVP schema (teams, locations, games, etc.) — **already applied**
- `database/user_accounts_schema.sql` — user accounts tables (users, roles, team_owners, user_invitations, remember_tokens, login_attempts) — **may or may not be applied**; treat as already applied in shared hosting env
- `database/migrations/` — does NOT exist yet; this story creates it
- No `schema_migrations` table exists yet

### `activity_log` Table — Migration 007

The `ActivityLogger` (Story 1.3) writes to `activity_log`. This table does NOT exist in `schema.sql` or `user_accounts_schema.sql`. The existing `user_activity_log` table (in `user_accounts_schema.sql`) has a different schema and different name — do NOT reuse it.

**Migration 007 must be created in this story** so that Story 1.3's `ActivityLogger` has a table to write to.

**`activity_log` schema for migration 007:**
```sql
CREATE TABLE IF NOT EXISTS activity_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event VARCHAR(100) NOT NULL,
  context JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activity_log_event (event),
  INDEX idx_activity_log_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Notes:
- `event` stores the dot-notation event string (e.g., `auth.login_success`, `score.submitted`)
- `context` stores JSON-encoded array of contextual data (user_id, ip, game_id, etc.)
- No `user_id` FK column — context is flexible JSON so any event shape works without schema changes
- This aligns with `ActivityLogger::log(string $event, array $context): void` signature in architecture

### ATDD Artifacts

- Checklist: `_bmad-output/test-artifacts/atdd-checklist-1-1-apply-database-migrations.md`
- Unit/Integration tests: `tests/unit/MigrationRunnerTest.php` (18 tests — all red-phase, activate task-by-task)
- E2E smoke tests: `tests/e2e/database-migrations.spec.ts` (6 tests — all red-phase, activate after all migrations applied)

Run unit tests: `php tests/unit/run-unit-tests.php`
Run single test file: `php tests/unit/run-unit-tests.php --file=MigrationRunnerTest.php`
Run E2E tests: `npx playwright test tests/e2e/database-migrations.spec.ts`

**Test infrastructure files (created 2026-05-05):**
- `tests/unit/test-helpers.php` — `register_test()` / `run_all_tests()` framework (no PHPUnit required)
- `tests/unit/run-unit-tests.php` — CLI runner; auto-discovers `*Test.php` files
- `includes/config.test.php` — test DB config (defaults to `d8tl_test`; override via `TEST_DB_*` env vars)

### Testing

- Automated tests generated as ATDD red-phase scaffolds (see ATDD Artifacts above)
- Manual verification: after applying all migrations, query `schema_migrations` — all 7 versions (000–006) should appear
- Run each migration twice to verify idempotency (no errors on second run)
- Verify `teams` table has `status` column and all existing rows have `status = 'active'`
- Verify `locations` table has both new columns
- The existing Playwright e2e tests in `tests/e2e/` run against the real DB — they should still pass after migrations are applied (no destructive DDL)

### Project Structure Notes

- New directory: `database/migrations/` (flat, no subdirectories)
- File naming exactly: `000_create_schema_migrations.sql` through `006_remove_legacy_auth.sql`
- No PHP classes created in this story — this is DDL only
- `database/` already has: `schema.sql`, `user_accounts_schema.sql`, `e2e_dummy_data_seed.sql`, `e2e_dummy_data_delete.sql`, and several `.sql` patch files — do not modify any of them

### References

- [Source: epics.md#Story 1.1] — Full ACs, file list
- [Source: architecture.md#Data Architecture] — Exact table schemas for all 7 migrations
- [Source: architecture.md#Schema Migration Convention] — File naming, idempotency rules, comment block format
- [Source: architecture.md#Decision 6] — `login_attempts` lazy-purge strategy context (not for this story, but explains the table design)
- [Source: database/schema.sql] — Existing `teams` (PK: `team_id`), `locations` (PK: `location_id`) column structures
- [Source: database/user_accounts_schema.sql] — Existing `login_attempts`, `remember_tokens`, `users` (PK: `id`) — CONFLICT TABLES

## Dev Agent Record

### Agent Model Used

claude-sonnet-4-5 (Cursor Agent)

### Debug Log References

- MySQL 8.0 does not support `ADD COLUMN IF NOT EXISTS` — all ALTER TABLE column additions rewritten to use stored procedures with `information_schema.COLUMNS` guards.
- `submitted_by_user_id` must be `INT NULL` (not `INT UNSIGNED`) to match `users.id INT` type — FK type mismatch resolved in migration 004.
- `activity_log` already exists in `schema.sql` with different schema (`log_id` PK, no `event`/`context` columns); migration 007 extends it rather than recreating — test updated to check for either `id` or `log_id` as PK.
- `Database` class exposes `query()`, `fetchOne()`, `fetchAll()`, `getConnection()` — tests using `prepare()`/`exec()` directly on Database instance updated to use correct API.
- Test DB `d8tl_test` did not exist; created and populated with `schema.sql` + `user_accounts_schema.sql` (with `USE moc835_d8tl_prod` substituted) before running tests.

### Completion Notes List

- All 8 migration files created in `database/migrations/` following `NNN_snake_case_description.sql` convention.
- Each migration is fully idempotent: verified by applying all 8 a second time — zero errors.
- All migrations use stored procedure guards (`information_schema` checks) for ALTER TABLE operations — compatible with MySQL 8.0 and MariaDB.
- 21/21 unit tests pass in `MigrationRunnerTest.php` against `d8tl_test` database.
- Migration 007 extends existing `activity_log` table (adds `event` and `context` columns) rather than recreating it — preserves existing data and `log_id` PK.
- Test infrastructure (`test-helpers.php`, `run-unit-tests.php`, `config.test.php`) was pre-created; tests activated by removing red-phase skip flags.
- Change log: 2026-05-05 — Story 1.1 implemented and all tests passing.

### File List

- `database/migrations/000_create_schema_migrations.sql` — CREATE
- `database/migrations/001_add_league_list.sql` — CREATE
- `database/migrations/002_add_login_attempts.sql` — CREATE
- `database/migrations/003_add_teams_status_column.sql` — CREATE
- `database/migrations/004_add_locations_submission_columns.sql` — CREATE
- `database/migrations/005_add_remember_tokens.sql` — CREATE
- `database/migrations/006_remove_legacy_auth.sql` — CREATE
- `database/migrations/007_create_activity_log.sql` — CREATE
- `tests/unit/MigrationRunnerTest.php` — MODIFY (activated all 21 tests; fixed Database API calls; updated activity_log test for existing schema)
