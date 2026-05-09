import { defineConfig, devices } from '@playwright/test';
import * as path from 'path';
import * as dotenv from 'dotenv';

dotenv.config({ path: path.join(__dirname, 'tests', '.env.testing') });

const goldenOnly = process.env.PLAYWRIGHT_GOLDEN === '1';
const allBrowsers = process.env.PLAYWRIGHT_ALL_BROWSERS === '1';

export default defineConfig({
  testDir: path.join(__dirname, 'tests', 'e2e'),
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: [
    ['list'],
    ['html', { outputFolder: 'tests/reports/html', open: 'never' }],
    ['json', { outputFile: 'tests/reports/results.json' }],
    ['junit', { outputFile: 'tests/reports/results.xml' }],
  ],
  use: {
    baseURL: process.env.TEST_BASE_URL || 'http://localhost:8080',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 30000,
    navigationTimeout: 30000,
    ignoreHTTPSErrors: true,
  },
  projects: goldenOnly
    ? [{ name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 720 } } }]
    : allBrowsers
      ? [
          { name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 720 } } },
          { name: 'firefox', use: { ...devices['Desktop Firefox'], viewport: { width: 1280, height: 720 } } },
          { name: 'webkit', use: { ...devices['Desktop Safari'], viewport: { width: 1280, height: 720 } } },
        ]
      : [{ name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 720 } } }],
  timeout: 90000,
  expect: { timeout: 15000 },
  webServer: {
    command: 'php -S localhost:8080 -t public/',
    url: 'http://localhost:8080',
    reuseExistingServer: !process.env.CI,
    timeout: 120000,
    stdout: 'pipe',
    stderr: 'pipe',
  },
  outputDir: 'tests/reports/artifacts',
  globalSetup: path.join(__dirname, 'tests', 'config', 'global-setup.ts'),
  globalTeardown: path.join(__dirname, 'tests', 'config', 'global-teardown.ts'),
});
