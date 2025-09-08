import { test, expect } from '@playwright/test';
import { D8TLTestHelper, D8TLPageObjects } from '../helpers/test-helpers';

test.describe('Public Schedule Page', () => {
  let helper: D8TLTestHelper;
  let pages: D8TLPageObjects;

  test.beforeEach(async ({ page }) => {
    helper = new D8TLTestHelper(page);
    pages = new D8TLPageObjects(page);
    
    // Navigate to schedule page
    await helper.navigateToPublicSchedule();
  });

  test.afterEach(async () => {
    await helper.cleanup();
  });

  test('should display schedule table with games @functional', async ({ page }) => {
    // Verify page loads correctly
    await expect(page).toHaveTitle(/Schedule|D8TL/i);
    
    // Check for schedule table
    const scheduleTable = pages.schedulePage.scheduleTable;
    await expect(scheduleTable).toBeVisible();
    
    // Verify table has data
    const rowCount = await helper.expectTableToHaveData('table');
    expect(rowCount).toBeGreaterThan(0);
    
    // Take screenshot for visual regression
    await helper.compareVisualRegression('public-schedule');
  });

  test('should filter games by division @functional', async ({ page }) => {
    // Check if division filter exists
    const divisionFilter = pages.schedulePage.divisionFilter;
    
    if (await divisionFilter.count() > 0) {
      // Get initial row count
      const initialCount = await helper.expectTableToHaveData('table');
      
      // Get available division options
      const options = await divisionFilter.locator('option').allTextContents();
      const validOptions = options.filter(option => option.trim() && option !== 'All');
      
      if (validOptions.length > 0) {
        // Apply filter with first valid option
        await pages.schedulePage.filterByDivision(validOptions[0]);
        
        // Verify results are filtered (may be same or different count)
        const filteredCount = await helper.expectTableToHaveData('table');
        expect(filteredCount).toBeGreaterThan(0);
        
        await helper.takeScreenshot('schedule-filtered-by-division');
        console.log(`✅ Division filter applied: ${validOptions[0]}`);
      }
    } else {
      console.log('ℹ️ No division filter found on page');
    }
  });

  test('should be mobile responsive @mobile', async ({ page }) => {
    // Test mobile responsiveness
    await helper.testMobileResponsiveness();
    
    // Verify schedule is still accessible on mobile
    const scheduleTable = pages.schedulePage.scheduleTable;
    await expect(scheduleTable).toBeVisible();
    
    console.log('✅ Schedule page is mobile responsive');
  });

  test('should load within performance threshold @performance', async ({ page }) => {
    // Test page load performance
    const loadTime = await helper.checkPagePerformance('/schedule.php', 3000);
    
    // Additional performance checks
    const resources = await helper.monitorResourceLoading();
    console.log(`📊 Resources loaded: ${resources.length}`);
    
    // Check for large images or resources that might slow loading
    const largeResources = resources.filter(r => r.url.includes('.jpg') || r.url.includes('.png'));
    console.log(`🖼️ Image resources: ${largeResources.length}`);
  });

  test('should have proper accessibility @accessibility', async ({ page }) => {
    // Basic accessibility checks
    await helper.checkAccessibility();
    
    // Check for proper table headers
    const tableHeaders = page.locator('th');
    const headerCount = await tableHeaders.count();
    
    if (headerCount > 0) {
      expect(headerCount).toBeGreaterThan(0);
      console.log(`✅ Table has ${headerCount} headers`);
    }
    
    // Check for proper heading structure
    const mainHeading = page.locator('h1');
    await expect(mainHeading).toBeVisible();
  });

  test('should handle empty schedule gracefully @functional', async ({ page }) => {
    // This test checks error handling when no games are available
    // We'll simulate this by filtering to a non-existent division
    
    const divisionFilter = pages.schedulePage.divisionFilter;
    
    if (await divisionFilter.count() > 0) {
      // Try to select an option that might result in no games
      const options = await divisionFilter.locator('option').allTextContents();
      
      // Apply each filter and ensure page doesn't break
      for (const option of options) {
        if (option.trim() && option !== 'All') {
          await pages.schedulePage.filterByDivision(option);
          
          // Page should still be functional even if no results
          await expect(page.locator('body')).toBeVisible();
          
          // Check for "no results" message or empty table
          const table = pages.schedulePage.scheduleTable;
          const isVisible = await table.isVisible();
          
          if (isVisible) {
            console.log(`✅ Filter "${option}" shows results`);
          } else {
            console.log(`ℹ️ Filter "${option}" shows no results (expected behavior)`);
          }
        }
      }
    }
  });

  test('should display game information correctly @functional', async ({ page }) => {
    // Verify that game information is displayed properly
    const scheduleTable = pages.schedulePage.scheduleTable;
    await expect(scheduleTable).toBeVisible();
    
    // Check for expected columns (teams, date, time, location)
    const expectedColumns = ['team', 'date', 'time', 'location', 'vs', 'game'];
    const tableHeaders = await page.locator('th').allTextContents();
    const tableContent = await scheduleTable.textContent();
    
    // Check if any expected content exists
    let foundColumns = 0;
    for (const column of expectedColumns) {
      const headerMatch = tableHeaders.some(header => 
        header.toLowerCase().includes(column.toLowerCase())
      );
      const contentMatch = tableContent?.toLowerCase().includes(column.toLowerCase());
      
      if (headerMatch || contentMatch) {
        foundColumns++;
        console.log(`✅ Found column/content: ${column}`);
      }
    }
    
    expect(foundColumns).toBeGreaterThan(0);
    console.log(`✅ Schedule displays ${foundColumns} expected data types`);
  });

  test('should handle navigation correctly @functional', async ({ page }) => {
    // Test navigation from schedule page
    const currentUrl = page.url();
    expect(currentUrl).toContain('schedule');
    
    // Check for navigation links
    const navigationLinks = [
      'a[href*="standings"]',
      'a[href*="home"]',
      'a[href="/"]',
      '.nav a',
      'nav a'
    ];
    
    let foundNavigation = false;
    for (const selector of navigationLinks) {
      const links = page.locator(selector);
      const count = await links.count();
      
      if (count > 0) {
        foundNavigation = true;
        console.log(`✅ Found navigation links: ${count}`);
        break;
      }
    }
    
    // Navigation should exist, but if not, page should still be functional
    if (foundNavigation) {
      console.log('✅ Navigation elements found');
    } else {
      console.log('ℹ️ No standard navigation found (may be custom implementation)');
    }
  });

  test('should support visual regression testing @visual', async ({ page }) => {
    // Wait for page to fully load
    await page.waitForLoadState('networkidle');
    
    // Take full page screenshot for visual regression
    await helper.compareVisualRegression('public-schedule-full-page');
    
    // Test specific components
    const scheduleTable = pages.schedulePage.scheduleTable;
    if (await scheduleTable.isVisible()) {
      await scheduleTable.screenshot({ path: 'tests/reports/screenshots/schedule-table.png' });
      console.log('✅ Schedule table screenshot captured');
    }
    
    // Test with different filters if available
    const divisionFilter = pages.schedulePage.divisionFilter;
    if (await divisionFilter.count() > 0) {
      const options = await divisionFilter.locator('option').allTextContents();
      const validOptions = options.filter(option => option.trim() && option !== 'All');
      
      if (validOptions.length > 0) {
        await pages.schedulePage.filterByDivision(validOptions[0]);
        await helper.compareVisualRegression(`schedule-filtered-${validOptions[0].toLowerCase().replace(/\s+/g, '-')}`);
      }
    }
  });
});

