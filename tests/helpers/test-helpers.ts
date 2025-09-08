import { Page, Locator, expect, BrowserContext } from '@playwright/test';
import * as fs from 'fs-extra';
import * as path from 'path';

/**
 * Comprehensive test helper class for D8TL application testing
 * Provides utilities for authentication, navigation, validation, performance, and more
 */
export class D8TLTestHelper {
  constructor(private page: Page) {}

  // ==================== AUTHENTICATION HELPERS ====================

  /**
   * Login as admin user
   */
  async loginAsAdmin(
    email: string = process.env.TEST_ADMIN_EMAIL || 'admin@test.local',
    password: string = process.env.TEST_ADMIN_PASSWORD || 'test123'
  ) {
    console.log(`🔐 Logging in as admin: ${email}`);
    
    await this.page.goto('/admin/login.php');
    await this.page.waitForLoadState('networkidle');
    
    // Fill login form
    await this.fillFormField('input[name="email"], input[type="email"]', email);
    await this.fillFormField('input[name="password"], input[type="password"]', password);
    
    // Submit form
    await this.page.click('button[type="submit"], input[type="submit"]');
    
    // Wait for redirect to admin dashboard
    try {
      await this.page.waitForURL('**/admin/**', { timeout: 10000 });
      console.log('✅ Admin login successful');
    } catch (error) {
      // Check for error messages
      const errorElement = this.page.locator('.error, .alert-danger, .login-error');
      if (await errorElement.isVisible()) {
        const errorText = await errorElement.textContent();
        throw new Error(`Admin login failed: ${errorText}`);
      }
      throw new Error('Admin login failed: No redirect to dashboard');
    }
    
    // Take authentication screenshot
    await this.takeScreenshot('admin-login-success');
  }

  /**
   * Login as coach user
   */
  async loginAsCoach(
    email: string = process.env.TEST_COACH_EMAIL || 'coach@test.local',
    password: string = process.env.TEST_COACH_PASSWORD || 'test123'
  ) {
    console.log(`🔐 Logging in as coach: ${email}`);
    
    await this.page.goto('/coaches/login.php');
    await this.page.waitForLoadState('networkidle');
    
    // Fill login form
    await this.fillFormField('input[name="email"], input[type="email"]', email);
    await this.fillFormField('input[name="password"], input[type="password"]', password);
    
    // Submit form
    await this.page.click('button[type="submit"], input[type="submit"]');
    
    // Wait for redirect to coach dashboard
    try {
      await this.page.waitForURL('**/coaches/dashboard.php', { timeout: 10000 });
      console.log('✅ Coach login successful');
    } catch (error) {
      // Check for error messages
      const errorElement = this.page.locator('.error, .alert-danger, .login-error');
      if (await errorElement.isVisible()) {
        const errorText = await errorElement.textContent();
        throw new Error(`Coach login failed: ${errorText}`);
      }
      throw new Error('Coach login failed: No redirect to dashboard');
    }
    
    // Take authentication screenshot
    await this.takeScreenshot('coach-login-success');
  }

  /**
   * Logout from current session
   */
  async logout() {
    try {
      // Try multiple logout patterns
      const logoutSelectors = [
        'a[href*="logout"]',
        'button[onclick*="logout"]',
        '.logout-link',
        '#logout'
      ];
      
      for (const selector of logoutSelectors) {
        const element = this.page.locator(selector);
        if (await element.isVisible()) {
          await element.click();
          await this.page.waitForLoadState('networkidle');
          console.log('✅ Logout successful');
          return;
        }
      }
      
      // Fallback: navigate to logout URL
      await this.page.goto('/logout.php');
      await this.page.waitForLoadState('networkidle');
      
    } catch (error) {
      console.warn('⚠️ Logout may have failed:', error.message);
    }
  }

  // ==================== NAVIGATION HELPERS ====================

  /**
   * Navigate to public schedule page
   */
  async navigateToPublicSchedule() {
    await this.page.goto('/schedule.php');
    await this.page.waitForLoadState('networkidle');
    await this.takeScreenshot('public-schedule-loaded');
  }

  /**
   * Navigate to public standings page
   */
  async navigateToPublicStandings() {
    await this.page.goto('/standings.php');
    await this.page.waitForLoadState('networkidle');
    await this.takeScreenshot('public-standings-loaded');
  }

  /**
   * Navigate to coach dashboard
   */
  async navigateToCoachDashboard() {
    await this.page.goto('/coaches/dashboard.php');
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Navigate to admin dashboard
   */
  async navigateToAdminDashboard() {
    await this.page.goto('/admin/');
    await this.page.waitForLoadState('networkidle');
  }

  // ==================== FORM HELPERS ====================

  /**
   * Fill a form field with proper validation
   */
  async fillFormField(selector: string, value: string) {
    const field = this.page.locator(selector);
    await expect(field).toBeVisible({ timeout: 10000 });
    await field.clear();
    await field.fill(value);
    
    // Verify the value was set
    const actualValue = await field.inputValue();
    expect(actualValue).toBe(value);
  }

  /**
   * Select dropdown option
   */
  async selectDropdownOption(selector: string, value: string) {
    const dropdown = this.page.locator(selector);
    await expect(dropdown).toBeVisible();
    await dropdown.selectOption(value);
  }

  /**
   * Submit form and wait for response
   */
  async submitForm(formSelector: string = 'form') {
    const form = this.page.locator(formSelector);
    await expect(form).toBeVisible();
    
    // Submit form
    await form.locator('button[type="submit"], input[type="submit"]').click();
    await this.page.waitForLoadState('networkidle');
  }

  // ==================== VALIDATION HELPERS ====================

  /**
   * Expect table to have data
   */
  async expectTableToHaveData(tableSelector: string): Promise<number> {
    const table = this.page.locator(tableSelector);
    await expect(table).toBeVisible({ timeout: 10000 });
    
    const rows = table.locator('tbody tr, tr').filter({ hasNot: this.page.locator('th') });
    const count = await rows.count();
    
    expect(count).toBeGreaterThan(0);
    console.log(`✅ Table has ${count} data rows`);
    
    return count;
  }

  /**
   * Expect form error message
   */
  async expectFormError(errorSelector: string, expectedMessage?: string) {
    const errorElement = this.page.locator(errorSelector);
    await expect(errorElement).toBeVisible({ timeout: 5000 });
    
    if (expectedMessage) {
      await expect(errorElement).toContainText(expectedMessage);
    }
    
    console.log('✅ Form error displayed as expected');
  }

  /**
   * Expect success message
   */
  async expectSuccessMessage(messageSelector: string = '.success, .alert-success, .message-success') {
    const successElement = this.page.locator(messageSelector);
    await expect(successElement).toBeVisible({ timeout: 10000 });
    
    const message = await successElement.textContent();
    console.log(`✅ Success message: ${message}`);
  }

  // ==================== PERFORMANCE HELPERS ====================

  /**
   * Measure page load time
   */
  async measurePageLoadTime(url: string): Promise<number> {
    const startTime = Date.now();
    await this.page.goto(url);
    await this.page.waitForLoadState('networkidle');
    const loadTime = Date.now() - startTime;
    
    console.log(`📊 Page ${url} loaded in ${loadTime}ms`);
    return loadTime;
  }

  /**
   * Check page performance against threshold
   */
  async checkPagePerformance(url: string, maxLoadTime: number = 3000) {
    const loadTime = await this.measurePageLoadTime(url);
    
    expect(loadTime).toBeLessThan(maxLoadTime);
    console.log(`✅ Page ${url} performance OK: ${loadTime}ms < ${maxLoadTime}ms`);
    
    return loadTime;
  }

  /**
   * Monitor resource loading
   */
  async monitorResourceLoading() {
    const resources: { url: string; status: number; size: number }[] = [];
    
    this.page.on('response', (response) => {
      resources.push({
        url: response.url(),
        status: response.status(),
        size: 0 // Size would need additional API calls
      });
    });
    
    return resources;
  }

  // ==================== VISUAL TESTING HELPERS ====================

  /**
   * Take screenshot for debugging or visual regression
   */
  async takeScreenshot(name: string, options?: { fullPage?: boolean }) {
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const screenshotPath = path.join('tests/reports/screenshots', `${name}-${timestamp}.png`);
    
    await fs.ensureDir(path.dirname(screenshotPath));
    
    await this.page.screenshot({
      path: screenshotPath,
      fullPage: options?.fullPage ?? false,
    });
    
    console.log(`📸 Screenshot saved: ${screenshotPath}`);
    return screenshotPath;
  }

  /**
   * Compare visual regression
   */
  async compareVisualRegression(name: string, options?: { threshold?: number }) {
    const threshold = options?.threshold ?? parseFloat(process.env.VISUAL_THRESHOLD || '0.2');
    
    // Take screenshot for visual comparison
    await expect(this.page).toHaveScreenshot(`${name}.png`, {
      threshold,
      mode: 'strict'
    });
    
    console.log(`✅ Visual regression test passed: ${name}`);
  }

  // ==================== MOBILE TESTING HELPERS ====================

  /**
   * Test mobile responsiveness across different viewports
   */
  async testMobileResponsiveness() {
    const viewports = [
      { width: 375, height: 667, name: 'iPhone SE' },
      { width: 414, height: 896, name: 'iPhone XR' },
      { width: 360, height: 640, name: 'Galaxy S5' },
      { width: 768, height: 1024, name: 'iPad' },
    ];

    for (const viewport of viewports) {
      console.log(`📱 Testing viewport: ${viewport.name} (${viewport.width}x${viewport.height})`);
      
      await this.page.setViewportSize({ width: viewport.width, height: viewport.height });
      await this.page.waitForLoadState('networkidle');
      
      // Take screenshot for each viewport
      await this.takeScreenshot(`mobile-${viewport.name.toLowerCase().replace(/\s+/g, '-')}`);
      
      // Check that content is still accessible
      const body = this.page.locator('body');
      await expect(body).toBeVisible();
      
      // Check for mobile-specific elements or behaviors
      const mobileMenu = this.page.locator('.mobile-menu, .hamburger, .nav-toggle');
      if (await mobileMenu.isVisible()) {
        console.log(`✅ Mobile navigation detected on ${viewport.name}`);
      }
    }
    
    // Reset to desktop viewport
    await this.page.setViewportSize({ width: 1280, height: 720 });
  }

  // ==================== ACCESSIBILITY HELPERS ====================

  /**
   * Check basic accessibility requirements
   */
  async checkAccessibility() {
    console.log('♿ Running accessibility checks...');
    
    // Check for main heading
    const h1 = this.page.locator('h1');
    await expect(h1).toBeVisible();
    console.log('✅ Page has main heading (h1)');
    
    // Check for proper form labels
    const inputs = this.page.locator('input[type="text"], input[type="email"], input[type="password"], textarea, select');
    const inputCount = await inputs.count();
    
    for (let i = 0; i < inputCount; i++) {
      const input = inputs.nth(i);
      const id = await input.getAttribute('id');
      const ariaLabel = await input.getAttribute('aria-label');
      const placeholder = await input.getAttribute('placeholder');
      
      if (id) {
        const label = this.page.locator(`label[for="${id}"]`);
        if (await label.count() > 0) {
          console.log(`✅ Input has proper label: ${id}`);
          continue;
        }
      }
      
      if (ariaLabel || placeholder) {
        console.log(`✅ Input has aria-label or placeholder`);
        continue;
      }
      
      console.warn(`⚠️ Input may be missing proper labeling`);
    }
    
    // Check for alt text on images
    const images = this.page.locator('img');
    const imageCount = await images.count();
    
    for (let i = 0; i < imageCount; i++) {
      const img = images.nth(i);
      const alt = await img.getAttribute('alt');
      const role = await img.getAttribute('role');
      
      if (!alt && role !== 'presentation') {
        console.warn(`⚠️ Image missing alt text`);
      } else {
        console.log(`✅ Image has proper alt text or role`);
      }
    }
  }

  // ==================== DATA VALIDATION HELPERS ====================

  /**
   * Verify database data (placeholder for actual DB integration)
   */
  async verifyDatabaseData(query: string, expectedResult: any) {
    // This would connect to test database to verify data
    // Implementation depends on your database setup
    console.log(`🗄️ Verifying database query: ${query}`);
    console.log(`Expected result: ${JSON.stringify(expectedResult)}`);
    
    // TODO: Implement actual database verification
    // For now, this is a placeholder that logs the intent
  }

  // ==================== API TESTING HELPERS ====================

  /**
   * Test API endpoint
   */
  async testAPIEndpoint(
    endpoint: string, 
    method: 'GET' | 'POST' | 'PUT' | 'DELETE' = 'GET', 
    data?: any,
    expectedStatus: number = 200
  ) {
    console.log(`🌐 Testing API: ${method} ${endpoint}`);
    
    const response = await this.page.request[method.toLowerCase() as 'get' | 'post' | 'put' | 'delete'](
      endpoint,
      data ? { data } : undefined
    );
    
    expect(response.status()).toBe(expectedStatus);
    console.log(`✅ API response status: ${response.status()}`);
    
    return response;
  }

  // ==================== EMAIL TESTING HELPERS ====================

  /**
   * Wait for email notification (placeholder for email testing integration)
   */
  async waitForEmailNotification(recipient: string, subject: string, timeout: number = 10000) {
    // This would integrate with email testing service like MailHog, Mailtrap, etc.
    console.log(`📧 Waiting for email to ${recipient} with subject: ${subject}`);
    
    // For now, we'll just wait and log
    await this.page.waitForTimeout(Math.min(timeout, 5000));
    console.log(`✅ Email notification wait completed`);
  }

  // ==================== SECURITY TESTING HELPERS ====================

  /**
   * Test for SQL injection vulnerabilities
   */
  async testSQLInjection(inputSelector: string) {
    const maliciousInputs = [
      "'; DROP TABLE users; --",
      "' OR '1'='1",
      "admin'--",
      "' UNION SELECT * FROM users --"
    ];
    
    for (const maliciousInput of maliciousInputs) {
      console.log(`🔒 Testing SQL injection with: ${maliciousInput}`);
      
      await this.fillFormField(inputSelector, maliciousInput);
      await this.submitForm();
      
      // Check that the page doesn't reveal database errors
      const errorIndicators = [
        'mysql error',
        'sql syntax',
        'database error',
        'warning: mysql',
        'fatal error'
      ];
      
      const pageContent = await this.page.textContent('body');
      const lowerContent = pageContent?.toLowerCase() || '';
      
      for (const indicator of errorIndicators) {
        if (lowerContent.includes(indicator)) {
          throw new Error(`Potential SQL injection vulnerability detected: ${indicator}`);
        }
      }
    }
    
    console.log('✅ SQL injection tests passed');
  }

  /**
   * Test XSS protection
   */
  async testXSSProtection(inputSelector: string) {
    const xssPayloads = [
      '<script>alert("XSS")</script>',
      '<img src="x" onerror="alert(1)">',
      'javascript:alert("XSS")',
      '<svg onload="alert(1)">'
    ];
    
    for (const payload of xssPayloads) {
      console.log(`🔒 Testing XSS protection with payload`);
      
      await this.fillFormField(inputSelector, payload);
      await this.submitForm();
      
      // Check that scripts are not executed
      const alerts = this.page.locator('text=XSS');
      expect(await alerts.count()).toBe(0);
    }
    
    console.log('✅ XSS protection tests passed');
  }

  // ==================== CLEANUP HELPERS ====================

  /**
   * Cleanup test environment
   */
  async cleanup() {
    try {
      // Clear any test data, logout, etc.
      await this.logout();
      
      // Clear local storage and cookies
      await this.page.evaluate(() => {
        localStorage.clear();
        sessionStorage.clear();
      });
      
      // Clear cookies
      const context = this.page.context();
      await context.clearCookies();
      
      console.log('✅ Test cleanup completed');
      
    } catch (error) {
      console.warn('⚠️ Cleanup may have failed:', error.message);
    }
  }

  // ==================== UTILITY HELPERS ====================

  /**
   * Wait for element with custom timeout
   */
  async waitForElement(selector: string, timeout: number = 10000) {
    const element = this.page.locator(selector);
    await expect(element).toBeVisible({ timeout });
    return element;
  }

  /**
   * Get text content safely
   */
  async getTextContent(selector: string): Promise<string> {
    const element = this.page.locator(selector);
    await expect(element).toBeVisible();
    return (await element.textContent()) || '';
  }

  /**
   * Check if element exists without throwing
   */
  async elementExists(selector: string): Promise<boolean> {
    try {
      const element = this.page.locator(selector);
      return await element.count() > 0;
    } catch {
      return false;
    }
  }
}

/**
 * Page Object Model classes for D8TL application
 */
export class D8TLPageObjects {
  constructor(private page: Page) {}

  // Public pages
  get homepage() {
    return new HomePage(this.page);
  }

  get schedulePage() {
    return new SchedulePage(this.page);
  }

  get standingsPage() {
    return new StandingsPage(this.page);
  }

  // Coach pages
  get coachLogin() {
    return new CoachLoginPage(this.page);
  }

  get coachDashboard() {
    return new CoachDashboardPage(this.page);
  }

  get scheduleChangePage() {
    return new ScheduleChangePage(this.page);
  }

  get scoreInputPage() {
    return new ScoreInputPage(this.page);
  }

  // Admin pages
  get adminLogin() {
    return new AdminLoginPage(this.page);
  }

  get adminDashboard() {
    return new AdminDashboardPage(this.page);
  }

  get teamManagement() {
    return new TeamManagementPage(this.page);
  }

  get gameManagement() {
    return new GameManagementPage(this.page);
  }
}

// ==================== PAGE OBJECT CLASSES ====================

class HomePage {
  constructor(private page: Page) {}

  get scheduleLink() {
    return this.page.locator('a[href*="schedule"], .schedule-link');
  }

  get standingsLink() {
    return this.page.locator('a[href*="standings"], .standings-link');
  }

  get coachesLink() {
    return this.page.locator('a[href*="coaches"], .coaches-link');
  }

  async navigateToSchedule() {
    await this.scheduleLink.click();
  }

  async navigateToStandings() {
    await this.standingsLink.click();
  }

  async navigateToCoaches() {
    await this.coachesLink.click();
  }
}

class SchedulePage {
  constructor(private page: Page) {}

  get scheduleTable() {
    return this.page.locator('table.schedule, .schedule-table, table');
  }

  get divisionFilter() {
    return this.page.locator('select[name="division"], .division-filter');
  }

  get programFilter() {
    return this.page.locator('select[name="program"], .program-filter');
  }

  async filterByDivision(division: string) {
    if (await this.divisionFilter.count() > 0) {
      await this.divisionFilter.selectOption(division);
      await this.page.waitForLoadState('networkidle');
    }
  }

  async filterByProgram(program: string) {
    if (await this.programFilter.count() > 0) {
      await this.programFilter.selectOption(program);
      await this.page.waitForLoadState('networkidle');
    }
  }
}

class StandingsPage {
  constructor(private page: Page) {}

  get standingsTable() {
    return this.page.locator('table.standings, .standings-table, table');
  }

  async getTeamPosition(teamName: string): Promise<number> {
    const rows = this.standingsTable.locator('tbody tr, tr');
    const count = await rows.count();
    
    for (let i = 0; i < count; i++) {
      const row = rows.nth(i);
      const text = await row.textContent();
      if (text?.includes(teamName)) {
        return i + 1;
      }
    }
    
    throw new Error(`Team ${teamName} not found in standings`);
  }
}

class CoachLoginPage {
  constructor(private page: Page) {}

  get emailInput() {
    return this.page.locator('input[name="email"], input[type="email"]');
  }

  get passwordInput() {
    return this.page.locator('input[name="password"], input[type="password"]');
  }

  get loginButton() {
    return this.page.locator('button[type="submit"], input[type="submit"]');
  }

  async login(email: string, password: string) {
    await this.emailInput.fill(email);
    await this.passwordInput.fill(password);
    await this.loginButton.click();
  }
}

class CoachDashboardPage {
  constructor(private page: Page) {}

  get scheduleChangeLink() {
    return this.page.locator('a[href*="schedule-change"]');
  }

  get scoreInputLink() {
    return this.page.locator('a[href*="score-input"]');
  }

  get teamInfo() {
    return this.page.locator('.team-info, .team-details');
  }

  async navigateToScheduleChange() {
    await this.scheduleChangeLink.click();
  }

  async navigateToScoreInput() {
    await this.scoreInputLink.click();
  }
}

class ScheduleChangePage {
  constructor(private page: Page) {}

  get gameSelect() {
    return this.page.locator('select[name="game"], .game-select');
  }

  get newDateInput() {
    return this.page.locator('input[name="new_date"], input[type="date"]');
  }

  get newTimeInput() {
    return this.page.locator('input[name="new_time"], input[type="time"]');
  }

  get reasonTextarea() {
    return this.page.locator('textarea[name="reason"], .reason-input');
  }

  get submitButton() {
    return this.page.locator('button[type="submit"], input[type="submit"]');
  }

  async submitScheduleChange(gameId: string, newDate: string, newTime: string, reason: string) {
    await this.gameSelect.selectOption(gameId);
    await this.newDateInput.fill(newDate);
    await this.newTimeInput.fill(newTime);
    await this.reasonTextarea.fill(reason);
    await this.submitButton.click();
  }
}

class ScoreInputPage {
  constructor(private page: Page) {}

  get gameSelect() {
    return this.page.locator('select[name="game"], .game-select');
  }

  get homeScoreInput() {
    return this.page.locator('input[name="home_score"], .home-score');
  }

  get awayScoreInput() {
    return this.page.locator('input[name="away_score"], .away-score');
  }

  get submitButton() {
    return this.page.locator('button[type="submit"], input[type="submit"]');
  }

  async submitScore(gameId: string, homeScore: number, awayScore: number) {
    await this.gameSelect.selectOption(gameId);
    await this.homeScoreInput.fill(homeScore.toString());
    await this.awayScoreInput.fill(awayScore.toString());
    await this.submitButton.click();
  }
}

class AdminLoginPage {
  constructor(private page: Page) {}

  get emailInput() {
    return this.page.locator('input[name="email"], input[type="email"]');
  }

  get passwordInput() {
    return this.page.locator('input[name="password"], input[type="password"]');
  }

  get loginButton() {
    return this.page.locator('button[type="submit"], input[type="submit"]');
  }

  async login(email: string, password: string) {
    await this.emailInput.fill(email);
    await this.passwordInput.fill(password);
    await this.loginButton.click();
  }
}

class AdminDashboardPage {
  constructor(private page: Page) {}

  get teamsLink() {
    return this.page.locator('a[href*="teams"]');
  }

  get gamesLink() {
    return this.page.locator('a[href*="games"]');
  }

  get seasonsLink() {
    return this.page.locator('a[href*="seasons"]');
  }

  get schedulesLink() {
    return this.page.locator('a[href*="schedules"]');
  }

  async navigateToTeams() {
    await this.teamsLink.click();
  }

  async navigateToGames() {
    await this.gamesLink.click();
  }

  async navigateToSchedules() {
    await this.schedulesLink.click();
  }
}

class TeamManagementPage {
  constructor(private page: Page) {}

  get addTeamButton() {
    return this.page.locator('button:has-text("Add Team"), .add-team-btn');
  }

  get teamNameInput() {
    return this.page.locator('input[name="team_name"], .team-name-input');
  }

  get divisionSelect() {
    return this.page.locator('select[name="division"], .division-select');
  }

  get saveButton() {
    return this.page.locator('button[type="submit"], .save-btn');
  }

  async addTeam(teamName: string, division: string) {
    await this.addTeamButton.click();
    await this.teamNameInput.fill(teamName);
    await this.divisionSelect.selectOption(division);
    await this.saveButton.click();
  }
}

class GameManagementPage {
  constructor(private page: Page) {}

  get addGameButton() {
    return this.page.locator('button:has-text("Add Game"), .add-game-btn');
  }

  get homeTeamSelect() {
    return this.page.locator('select[name="home_team"], .home-team-select');
  }

  get awayTeamSelect() {
    return this.page.locator('select[name="away_team"], .away-team-select');
  }

  get gameDateInput() {
    return this.page.locator('input[name="game_date"], input[type="date"]');
  }

  get gameTimeInput() {
    return this.page.locator('input[name="game_time"], input[type="time"]');
  }

  get saveButton() {
    return this.page.locator('button[type="submit"], .save-btn');
  }

  async addGame(homeTeam: string, awayTeam: string, date: string, time: string) {
    await this.addGameButton.click();
    await this.homeTeamSelect.selectOption(homeTeam);
    await this.awayTeamSelect.selectOption(awayTeam);
    await this.gameDateInput.fill(date);
    await this.gameTimeInput.fill(time);
    await this.saveButton.click();
  }
}

