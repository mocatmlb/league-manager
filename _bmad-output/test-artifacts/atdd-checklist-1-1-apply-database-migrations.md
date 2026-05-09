---
stepsCompleted:
  - step-01-preflight-and-context
  - step-02-generation-mode
  - step-03-test-strategy
  - step-04-generate-tests
  - step-04c-aggregate
  - step-05-validate-and-complete
lastStep: step-05-validate-and-complete
lastSaved: '2026-05-04'
storyId: '1.1'
storyKey: 1-1-apply-database-migrations
storyFile: _bmad-output/implementation-artifacts/1-1-apply-database-migrations.md
atddChecklistPath: _bmad-output/test-artifacts/atdd-checklist-1-1-apply-database-migrations.md
generatedTestFiles:
  - tests/unit/MigrationRunnerTest.php
  - tests/e2e/database-migrations.spec.ts
---

# ATDD Checklist: Story 1.1 — Apply Database Migrations

## TDD Red Phase Status

All tests are currently in **RED PHASE** (skipped). Activate tests task-by-task as each migration is implemented.

| Test File | Tests | TDD Phase |
|-----------|-------|-----------|
| `tests/unit/MigrationRunnerTest.php` | 18 unit/integration tests | 🔴 RED (all skipped) |
| `tests/e2e/database-migrations.spec.ts` | 6 E2E smoke tests | 🔴 RED (all skipped) |
| **Total** | **24 tests** | — |

---

## Stack & Generation Mode

| Setting | Value |
|---------|-------|
| Detected stack | `fullstack` (PHP backend + Playwright) |
| Generation mode | AI generation (backend DDL story — no browser recording) |
| Execution mode | `sequential` |
| Test framework | Playwright (E2E) + custom PHP runner (unit) |

---

## Acceptance Criteria → Test Coverage Map

| AC | Priority | Test(s) | File |
|----|----------|---------|------|
| AC1: migration 000 creates `schema_migrations` with correct columns + version `000` recorded | P0 | `migration 000 creates schema_migrations table`, `schema_migrations has correct columns`, `migration 000 records version "000"` | MigrationRunnerTest.php |
| AC2: migrations 001–007 produce all required tables/columns/indexes | P0 | 12 unit tests covering each migration's schema contract | MigrationRunnerTest.php |
| AC2: all 8 versions (000–007) appear in `schema_migrations` | P0 | `all 8 versions (000-007) are recorded in schema_migrations` | MigrationRunnerTest.php |
| AC2: existing application pages don't break after migrations | P0 | E2E smoke tests (schedule, standings, login pages) | database-migrations.spec.ts |
| AC3: idempotency — re-running migrations produces no error | P0 | `re-running migration 000 SQL produces no error`, `re-running migration 001 SQL produces no error` | MigrationRunnerTest.php |
| AC3: application remains functional after double-run | P2 | `application remains fully functional after migrations applied twice` | database-migrations.spec.ts |

---

## Test Strategy

### Why unit/integration tests rather than E2E for schema verification

This story is SQL DDL only — no new pages or API endpoints are created. The authoritative way to verify migrations is to inspect the database schema directly using `SHOW TABLES`, `DESCRIBE`, `SHOW INDEX`, and `information_schema` queries. The project's existing unit test runner (`php tests/unit/run-unit-tests.php`) with `Database::setInstance()` injection supports this pattern.

E2E tests are included as **application-level smoke tests** to catch regressions in existing pages caused by bad DDL (e.g., column renames, dropped indexes affecting query plans).

### Test level selection

| Level | Rationale |
|-------|-----------|
| Unit/Integration (PHP) | Direct schema inspection — authoritative for migration correctness |
| E2E (Playwright) | Non-regression guard — ensures existing app pages survive the DDL changes |
| API | N/A — no new API endpoints in this story |

### Priority mapping

| Priority | Tests | Rationale |
|----------|-------|-----------|
| P0 | 10 tests | Schema existence / column contracts for tables other code depends on immediately |
| P1 | 7 tests | Index and FK validation, login pages |
| P2 | 1 test | Idempotency regression via E2E (lower risk — unit tests cover idempotency directly) |

---

## Task-by-Task Activation Guide

Activate tests in this order as tasks are completed:

### Task: Create `database/migrations/` directory + migration 000
**Activate in `MigrationRunnerTest.php`:**
- [ ] `AC1-P0: migration 000 creates schema_migrations table` — remove `$skip = true`
- [ ] `AC1-P0: schema_migrations has correct columns` — remove `$skip = true`
- [ ] `AC1-P0: migration 000 records version "000" in schema_migrations` — remove `$skip = true`
- [ ] `AC3-P0: re-running migration 000 SQL produces no error (idempotent)` — remove `$skip = true`

**Run:** `php tests/unit/run-unit-tests.php`

---

### Task: Create migrations 001–007
**Activate per migration in `MigrationRunnerTest.php` as each is created:**
- [ ] Migration 001: `AC2-P0: migration 001 creates league_list table...` + `AC2-P1: idx_league_list_active_order index`
- [ ] Migration 002: `AC2-P0: login_attempts canonical columns` + `AC2-P1: composite indexes`
- [ ] Migration 003: `AC2-P0: teams.status column` + `AC2-P0: all existing teams rows have status=active`
- [ ] Migration 004: `AC2-P0: locations columns` + `AC2-P1: FK fk_locations_submitted_by` + `AC2-P0: all locations rows have status=active`
- [ ] Migration 005: `AC2-P0: remember_tokens token_hash` + `AC2-P1: unique key uq_remember_token` + `AC2-P1: FK with CASCADE`
- [ ] Migration 006: `AC2-P1: version 006 recorded`
- [ ] Migration 007: `AC2-P0: activity_log columns` + `AC2-P1: context nullable JSON`

---

### Task: Verify all migrations applied (final verification)
**Activate in `MigrationRunnerTest.php`:**
- [ ] `AC2-P0: all 8 versions (000-007) are recorded in schema_migrations`
- [ ] `AC3-P1: re-running migration 001 SQL produces no error`

**Activate in `database-migrations.spec.ts`** (remove `test.skip()` from all tests):
- [ ] `[P0] public schedule page still loads after migrations applied`
- [ ] `[P0] public standings page still loads after migrations applied`
- [ ] `[P1] coach login page still loads after migrations applied`
- [ ] `[P1] admin login page still loads after migrations applied`
- [ ] `[P1] teams-related page loads without DB column error after migration 003`
- [ ] `[P2] application remains fully functional after migrations are applied twice`

**Run E2E:** `npx playwright test tests/e2e/database-migrations.spec.ts`

---

## Assumptions & Risks

| Assumption | Risk | Mitigation |
|------------|------|-----------|
| Unit tests run against a test DB, not production | HIGH — running against prod would mutate real data | Use `Database::setInstance()` with a dedicated test DB connection; never run unit tests against prod |
| Existing `user_accounts_schema.sql` may already be applied | MEDIUM — migrations 002/005 have known conflicts with existing tables | Story notes cover this; migrations use `CREATE TABLE IF NOT EXISTS` + conditional `ALTER TABLE` |
| `ADD COLUMN IF NOT EXISTS` syntax supported by MariaDB version in shared hosting | MEDIUM — older MariaDB (<10.2.3) lacks this | Verify MariaDB version in cPanel before applying; fall back to conditional check in migration if needed |
| E2E tests require a running local server and seeded DB | LOW — CI/pre-deploy concern | Run `php -S localhost:8000 -t public/` before E2E; ensure `e2e_dummy_data_seed.sql` is applied |

---

## Next Steps After Green Phase

1. Run `[TA] Test Automation` (`bmad-testarch-automate`) to expand coverage
2. Run `[DS] Dev Story` to implement the migration files
3. Remove `test.skip()` test-by-test as each task is completed
4. After all tests green: run `[CR] Code Review`
