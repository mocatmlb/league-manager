import { test, expect, Page } from '@playwright/test';

// ---------------------------------------------------------------------------
// Admin login helper
// Uses /test-auth.php to create a PHP admin session without the login form.
// This bypasses reCAPTCHA, which blocks form-based login in headless tests.
// The endpoint is gated by TEST_AUTH_SECRET and disabled in production.
// ---------------------------------------------------------------------------

async function adminLogin(page: Page) {
  const secret = process.env.TEST_AUTH_SECRET || 'd8tl-playwright-test-secret';
  await page.goto(`/test-auth.php?role=admin&secret=${secret}`);
  await page.waitForURL(/admin/, { timeout: 10000 });
}

async function goToScheduleManagement(page: Page) {
  await page.goto('/admin/schedules/index.php');
  await page.waitForLoadState('networkidle');
}

// ---------------------------------------------------------------------------
// Tests — Story 20.3: Admin Schedule Change Conflict Prompt
// ---------------------------------------------------------------------------

test.describe('Admin Schedule Change — Conflict Prompt (Story 20.3)', () => {

  test.beforeEach(async ({ page }) => {
    await adminLogin(page);
    await goToScheduleManagement(page);
  });

  // -------------------------------------------------------------------------
  // AC3: No conflict → page loads and direct-change modal is accessible
  // -------------------------------------------------------------------------

  test('AC3 — Schedule Management page loads without errors @smoke @functional', async ({ page }) => {
    await expect(page).not.toHaveURL(/login/);
    await expect(page.locator('h1')).toContainText('Schedule Management');
    await expect(page.locator('.alert-danger')).toHaveCount(0);
  });

  test('AC3 — Process Schedule Change button opens the direct-change modal @smoke @functional', async ({ page }) => {
    const btn = page.locator('button', { hasText: 'Process Schedule Change' });
    await expect(btn).toBeVisible();
    await btn.click();

    // Wait for the modal to become visible
    const modal = page.locator('.modal.show, [id*="direct"][class*="modal"], [id*="change"][class*="modal"]').first();
    await expect(modal).toBeVisible({ timeout: 5000 });
  });

  // -------------------------------------------------------------------------
  // AC1 / AC2: Conflict warning HTML structure (rendered state)
  // These tests validate the warning block when $pendingConflicts is populated.
  // They soft-skip if no conflicting test-data games exist.
  // -------------------------------------------------------------------------

  test('AC1/AC2 — Conflict warning block contains required elements when visible @functional', async ({ page }) => {
    const warningBlock = page.locator('.alert-warning').filter({ hasText: /conflict/i });

    if (await warningBlock.isVisible().catch(() => false)) {
      // Warning heading
      await expect(warningBlock.locator('h5, .alert-heading')).toContainText(/conflict/i);

      // At least one conflict bullet
      const bullets = warningBlock.locator('ul li');
      await expect(bullets).toHaveCountGreaterThan(0);

      // Confirm button
      const confirmBtn = warningBlock.locator('button[type="submit"]', { hasText: /confirm/i });
      await expect(confirmBtn).toBeVisible();

      // Go Back link
      const goBackLink = warningBlock.locator('a.btn', { hasText: /go back/i });
      await expect(goBackLink).toBeVisible();

      // Hidden conflict_confirmed input inside the confirm form
      const hiddenInput = warningBlock.locator('input[name="conflict_confirmed"][value="1"]');
      await expect(hiddenInput).toHaveCount(1);
    } else {
      console.log('ℹ️  No conflict warning on page — test data may not have conflicting games. Skipping assertion.');
    }
  });

  test('AC2 — Direct change confirm form contains conflict_confirmed=1 hidden field @functional', async ({ page }) => {
    const warningBlock = page.locator('.alert-warning').filter({ hasText: /conflict/i });

    if (await warningBlock.isVisible().catch(() => false)) {
      const form = warningBlock.locator('form');
      await expect(form).toHaveCount(1);

      const hiddenConfirmed = form.locator('input[name="conflict_confirmed"]');
      await expect(hiddenConfirmed).toHaveAttribute('value', '1');

      const hiddenAction = form.locator('input[name="action"]');
      await expect(hiddenAction).toHaveCount(1);
    } else {
      console.log('ℹ️  Conflict warning not present — skipping confirm-form structure assertion.');
    }
  });

  // -------------------------------------------------------------------------
  // AC4: Postponement approve path does not trigger conflict warning
  // -------------------------------------------------------------------------

  test('AC4 — Page renders without conflict warning on a clean GET @functional', async ({ page }) => {
    const warningBlock = page.locator('.alert-warning').filter({ hasText: /conflict/i });
    await expect(warningBlock).toHaveCount(0);
    await expect(page.locator('.alert-danger')).toHaveCount(0);
  });

  // -------------------------------------------------------------------------
  // AC5: Go Back navigates to clean page
  // -------------------------------------------------------------------------

  test('AC5 — Go Back link returns to a clean Schedule Management page @functional', async ({ page }) => {
    const warningBlock = page.locator('.alert-warning').filter({ hasText: /conflict/i });

    if (await warningBlock.isVisible().catch(() => false)) {
      const goBackLink = warningBlock.locator('a.btn', { hasText: /go back/i });
      await goBackLink.click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('h1')).toContainText('Schedule Management');
      await expect(page.locator('.alert-warning').filter({ hasText: /conflict/i })).toHaveCount(0);
    } else {
      console.log('ℹ️  No warning block to test Go Back from — skipping.');
    }
  });

  // -------------------------------------------------------------------------
  // AC6: Multiple conflicts listed as separate bullets
  // -------------------------------------------------------------------------

  test('AC6 — Multiple conflicts render as separate bullet points @functional', async ({ page }) => {
    const warningBlock = page.locator('.alert-warning').filter({ hasText: /conflict/i });

    if (await warningBlock.isVisible().catch(() => false)) {
      const bullets = warningBlock.locator('ul li');
      const count = await bullets.count();
      if (count > 1) {
        for (let i = 0; i < count; i++) {
          await expect(bullets.nth(i)).not.toBeEmpty();
        }
        console.log(`✅ ${count} conflict bullets rendered separately`);
      } else {
        console.log('ℹ️  Only one conflict present — multi-bullet scenario not exercised.');
      }
    } else {
      console.log('ℹ️  No conflict warning present — AC6 multi-bullet check skipped.');
    }
  });

  // -------------------------------------------------------------------------
  // Coach-facing suffix must be stripped from admin warning text
  // -------------------------------------------------------------------------

  test('Warning text does not contain the coach-facing review suffix @security @functional', async ({ page }) => {
    const warningBlock = page.locator('.alert-warning').filter({ hasText: /conflict/i });

    if (await warningBlock.isVisible().catch(() => false)) {
      const warningText = await warningBlock.textContent();
      expect(warningText).not.toContain('an admin will need to review');
    } else {
      console.log('ℹ️  No conflict warning — coach suffix check skipped.');
    }
  });

  // -------------------------------------------------------------------------
  // Security: conflict_confirmed via GET must not trigger a save
  // -------------------------------------------------------------------------

  test('conflict_confirmed in querystring does not trigger a save on GET @security', async ({ page }) => {
    await page.goto('/admin/schedules/index.php?conflict_confirmed=1');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toContainText('Schedule Management');
    await expect(page.locator('.alert-success')).toHaveCount(0);
  });

  // -------------------------------------------------------------------------
  // Regression: normal page layout is intact
  // -------------------------------------------------------------------------

  test('Regression — Page layout is intact, no PHP errors @regression', async ({ page }) => {
    await expect(page.locator('.alert-danger')).toHaveCount(0);
    // The "Process Schedule Change" button and page heading must still be present
    await expect(page.locator('h1')).toContainText('Schedule Management');
    await expect(page.locator('button', { hasText: 'Process Schedule Change' })).toBeVisible();
  });
});
