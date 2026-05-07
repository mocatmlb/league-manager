import { test, expect } from '@playwright/test';

/**
 * Story 4.4 — coach dashboard hero, action grid, auth redirect.
 * Uses `identifier` (not `email`) on coach login.
 */
test.describe('Coach dashboard (4.4 hero)', () => {
  test('guest visiting dashboard is redirected to coach login', async ({ page }) => {
    const response = await page.goto('/coaches/dashboard.php', { waitUntil: 'commit' });
    expect(response?.status()).toBeLessThan(400);
    await page.waitForURL(/coaches\/login\.php/i, { timeout: 15000 });
    expect(page.url()).toMatch(/coaches\/login\.php/);
  });

  test('after login, dashboard shows hero and action grid', async ({ page }) => {
    const user = process.env.TEST_COACH_EMAIL || process.env.TEST_COACH_IDENTIFIER || 'coach@test.local';
    const pass = process.env.TEST_COACH_PASSWORD || 'test123';

    await page.goto('/coaches/login.php');
    await page.waitForLoadState('domcontentloaded');

    await page.locator('input[name="identifier"]').fill(user);
    await page.locator('input[name="password"]').fill(pass);
    await page.locator('button[type="submit"]').click();

    await page.waitForURL(/coaches\/(dashboard|login)\.php/, { timeout: 20000 });

    if (page.url().includes('login.php')) {
      test.skip(true, 'Coach credentials not accepted (configure tests/.env.testing or TEST_COACH_*).');
    }

    await expect(page.locator('.coach-hero')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('.coach-action-grid')).toBeVisible();
    await expect(page.locator('.coach-action-card').first()).toBeVisible();
    await expect(page.locator('nav.navbar-dark')).toBeVisible();
  });
});
