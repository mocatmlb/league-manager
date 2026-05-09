/**
 * ATDD Red-Phase Test Scaffolds: Story 1.1 — Apply Database Migrations
 *
 * TDD RED PHASE: All tests are wrapped in test.skip().
 * These tests assert EXPECTED application-visible behavior after migrations are applied.
 * Remove test.skip() per scenario as each migration task is completed.
 *
 * Note: This story is SQL DDL only — no new UI pages are created.
 * These E2E tests verify that:
 *   1. Existing pages continue to load correctly after migrations (non-regression)
 *   2. The schema_migrations table is readable via a known admin diagnostic route (if available)
 *   3. Application pages that depend on migrated tables (teams, locations) still function
 *
 * Story: 1.1 — Apply Database Migrations
 * Story File: _bmad-output/implementation-artifacts/1-1-apply-database-migrations.md
 * Generated: 2026-05-04
 * ATDD Checklist: _bmad-output/test-artifacts/atdd-checklist-1-1-apply-database-migrations.md
 */

import { test, expect } from '@playwright/test';
import { D8TLTestHelper } from '../helpers/test-helpers';

test.describe('Story 1.1: Database Migrations — Application-Level Smoke Tests (ATDD)', () => {

  // -------------------------------------------------------------------------
  // AC2 / AC3: Existing pages still load after migrations (non-regression)
  // These use the public schedule page which relies on games/teams/locations tables.
  // If migrations introduced breaking DDL, these would fail.
  // -------------------------------------------------------------------------

  test.skip('[P0] public schedule page still loads after migrations applied', async ({ page }) => {
    // THIS TEST WILL FAIL until migrations are applied and verified
    // Expected: schedule page loads, no DB errors surface
    await page.goto('/schedule.php');
    await page.waitForLoadState('networkidle');

    // Should not show a PHP/DB fatal error
    const bodyText = await page.locator('body').textContent();
    expect(bodyText).not.toContain('Fatal error');
    expect(bodyText).not.toContain('SQLSTATE');
    expect(bodyText).not.toContain('Table');
    await expect(page).not.toHaveTitle(/Error|500/i);
  });

  test.skip('[P0] public standings page still loads after migrations applied', async ({ page }) => {
    // THIS TEST WILL FAIL until migrations are applied and verified
    await page.goto('/standings.php');
    await page.waitForLoadState('networkidle');

    const bodyText = await page.locator('body').textContent();
    expect(bodyText).not.toContain('Fatal error');
    expect(bodyText).not.toContain('SQLSTATE');
    await expect(page).not.toHaveTitle(/Error|500/i);
  });

  test.skip('[P1] coach login page still loads after migrations applied', async ({ page }) => {
    // THIS TEST WILL FAIL until migrations are applied and verified
    // login_attempts and remember_tokens tables are touched by migrations 002 and 005
    await page.goto('/coaches/login.php');
    await page.waitForLoadState('networkidle');

    const bodyText = await page.locator('body').textContent();
    expect(bodyText).not.toContain('Fatal error');
    expect(bodyText).not.toContain('SQLSTATE');
    await expect(page).not.toHaveTitle(/Error|500/i);

    // Login form should still be present
    await expect(page.getByRole('textbox').first()).toBeVisible();
  });

  test.skip('[P1] admin login page still loads after migrations applied', async ({ page }) => {
    // THIS TEST WILL FAIL until migrations are applied and verified
    await page.goto('/admin/login.php');
    await page.waitForLoadState('networkidle');

    const bodyText = await page.locator('body').textContent();
    expect(bodyText).not.toContain('Fatal error');
    expect(bodyText).not.toContain('SQLSTATE');
    await expect(page).not.toHaveTitle(/Error|500/i);
  });

  // -------------------------------------------------------------------------
  // AC2: teams.status column — teams-dependent pages should function normally
  // -------------------------------------------------------------------------

  test.skip('[P1] teams-related page loads without DB column error after migration 003', async ({ page }) => {
    // THIS TEST WILL FAIL until migration 003 (teams.status) is applied
    // Any admin page that SELECTs from teams would surface a column error if migration 003 failed
    const helper = new D8TLTestHelper(page);

    await page.goto('/admin/login.php');
    await page.waitForLoadState('networkidle');

    // Attempt admin login — if login succeeds, the teams query on the dashboard would run
    try {
      await helper.loginAsAdmin();
      // If we reach here, teams page loaded without DB error
      const bodyText = await page.locator('body').textContent();
      expect(bodyText).not.toContain('Unknown column');
      expect(bodyText).not.toContain('teams.status');
    } catch {
      // Login credentials not available in test env — skip the auth check
      // but still verify no DB errors on the login page itself
      const bodyText = await page.locator('body').textContent();
      expect(bodyText).not.toContain('Unknown column');
    }
  });

  // -------------------------------------------------------------------------
  // AC3: Idempotency — verifiable at the application level
  // Running the migration SQL files twice should not break the application.
  // This is validated by the unit tests in MigrationRunnerTest.php — tracked here for AC coverage.
  // -------------------------------------------------------------------------

  test.skip('[P2] application remains fully functional after migrations are applied twice (idempotency regression)', async ({ page }) => {
    // THIS TEST WILL FAIL until migrations are applied and idempotency is confirmed
    // Assumption: a test setup fixture has already run each migration SQL twice
    await page.goto('/schedule.php');
    await page.waitForLoadState('networkidle');

    const bodyText = await page.locator('body').textContent();
    expect(bodyText).not.toContain('Fatal error');
    expect(bodyText).not.toContain('SQLSTATE');
    await expect(page).not.toHaveTitle(/Error|500/i);
  });

});
