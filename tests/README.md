# D8TL Automated Testing with Playwright + AI

## Overview
This directory contains automated browser testing using Playwright with AI-assisted test generation and execution for the District 8 Travel League application.

## Features
- 🤖 **AI-Powered Test Generation**: Automatically generate comprehensive test suites using Cursor + Claude
- 🌐 **Cross-Browser Testing**: Chrome, Firefox, Safari support
- 📱 **Mobile Responsive Testing**: Test across different device viewports
- 📊 **Visual Regression Testing**: Catch UI changes with screenshot comparisons
- ⚡ **Performance Monitoring**: Ensure page load times meet requirements
- 🔒 **Security Testing**: Validate authentication and authorization
- 📈 **Comprehensive Reporting**: HTML reports with screenshots and videos
- 🔄 **CI/CD Integration**: Ready for automated deployment pipelines

## Directory Structure
```
tests/
├── e2e/                    # End-to-end tests
├── config/                 # Test configuration
├── fixtures/               # Test data fixtures
├── helpers/                # Test utilities and helpers
├── ai-generated/           # AI-generated test cases
├── reports/                # Test reports and screenshots
├── scripts/                # Testing scripts and automation
└── README.md              # This file
```

## Quick Start

### 1. Install Dependencies
```bash
npm install
npm run install:browsers
```

### 2. Set Up Environment
```bash
cp .env.testing.example .env.testing
# Edit .env.testing with your API keys and configuration
```

### 3. Generate AI Tests
```bash
# Set your Anthropic API key
export ANTHROPIC_API_KEY=your_key_here

# Generate tests for entire application
npm run test:ai-generate

# Generate tests for specific page
npm run test:ai-page "/schedule.php" "Public Schedule" "View games" "Filter by division"
```

### 4. Run Tests
```bash
# Run all tests with local server
npm run test:full

# Run specific test categories
npm run test:functional     # Functional tests only
npm run test:mobile        # Mobile responsiveness
npm run test:visual        # Visual regression
npm run test:performance   # Performance tests
npm run test:security      # Security tests

# Interactive testing
npm run test:headed        # See browser during tests
npm run test:debug         # Debug mode
npm run test:ui            # Interactive UI mode
```

### 5. View Reports
```bash
npm run test:report
```

## Test Categories

### Functional Testing (@functional)
- Core functionality verification
- User workflow testing
- Form validation and submission
- Navigation and routing
- Data display and filtering

### Integration Testing (@integration)
- API endpoint testing
- Database integration
- Authentication flows
- Cross-feature interactions
- Email notification testing

### Performance Testing (@performance)
- Page load time verification
- Resource loading checks
- Database query performance
- API response times
- Memory usage monitoring

### Visual Testing (@visual)
- Screenshot comparisons
- UI regression detection
- Cross-browser visual consistency
- Mobile layout validation
- Accessibility compliance

### Security Testing (@security)
- Authentication bypass attempts
- Authorization checks
- Input sanitization validation
- SQL injection prevention
- XSS protection verification

### Mobile Testing (@mobile)
- Responsive design validation
- Touch interaction testing
- Viewport adaptation
- Mobile-specific features
- Performance on mobile devices

## AI Test Generation

The AI test generator uses Claude/Anthropic to create comprehensive test suites:

### Automatic Generation
```bash
# Scan entire application and generate tests
npm run test:ai-generate
```

### Manual Generation
```bash
# Generate tests for specific page
node tests/scripts/ai-test-generator.js page "/coaches/dashboard.php" "Coach Dashboard" "View team info" "Submit scores"

# Generate workflow tests
node tests/scripts/ai-test-generator.js workflow "Score Submission" "Login as coach" "Navigate to scores" "Enter scores" "Submit" "Verify standings updated"
```

### Generated Test Features
- Page Object Model pattern
- Comprehensive assertions
- Error handling and edge cases
- Mobile responsiveness testing
- Performance validation
- Accessibility checks
- Visual regression testing

## Configuration

### Environment Variables (.env.testing)
```bash
# AI Test Generation
ANTHROPIC_API_KEY=your_anthropic_api_key
OPENAI_API_KEY=your_openai_api_key

# Test Environment
TEST_BASE_URL=http://localhost:8080
TEST_ADMIN_EMAIL=admin@test.local
TEST_ADMIN_PASSWORD=test123
TEST_COACH_EMAIL=coach@test.local
TEST_COACH_PASSWORD=test123

# Performance Thresholds
MAX_LOAD_TIME=3000
MAX_API_RESPONSE_TIME=1000

# Visual Testing
VISUAL_THRESHOLD=0.2
ENABLE_VISUAL_TESTS=true
```

### Playwright Configuration
- Multiple browser projects (Chrome, Firefox, Safari)
- Mobile device simulation
- Visual regression testing
- Performance monitoring
- Automatic screenshot and video capture
- HTML reporting with detailed results

## Writing Custom Tests

### Basic Test Structure
```typescript
import { test, expect } from '@playwright/test';
import { D8TLTestHelper, D8TLPageObjects } from '../helpers/test-helpers';

test.describe('Feature Name', () => {
  let helper: D8TLTestHelper;
  let pages: D8TLPageObjects;

  test.beforeEach(async ({ page }) => {
    helper = new D8TLTestHelper(page);
    pages = new D8TLPageObjects(page);
  });

  test('should perform specific action @functional', async ({ page }) => {
    // Test implementation
    await helper.navigateToPublicSchedule();
    await expect(pages.schedulePage.scheduleTable).toBeVisible();
    await helper.compareVisualRegression('schedule-page');
  });
});
```

### Using Test Helpers
```typescript
// Authentication
await helper.loginAsAdmin();
await helper.loginAsCoach();

// Navigation
await helper.navigateToPublicSchedule();
await helper.navigateToPublicStandings();

// Validation
await helper.expectTableToHaveData('table.schedule');
await helper.expectFormError('.error-message', 'Invalid input');

// Performance
await helper.checkPagePerformance('/schedule.php', 3000);

// Mobile
await helper.testMobileResponsiveness();

// Visual
await helper.compareVisualRegression('page-name');
```

## CI/CD Integration

### GitHub Actions Example
```yaml
name: D8TL Testing
on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: '18'
      - run: npm install
      - run: npm run install:browsers
      - run: npm run test:ci
        env:
          ANTHROPIC_API_KEY: ${{ secrets.ANTHROPIC_API_KEY }}
```

### Local CI Script
```bash
# Run comprehensive test suite
npm run test:ci
```

## Troubleshooting

### Common Issues

1. **Server not starting**
   ```bash
   # Check if port 8080 is available
   lsof -i :8080
   # Kill existing processes if needed
   killall php
   ```

2. **Browser installation issues**
   ```bash
   # Reinstall browsers
   npx playwright install --force
   ```

3. **Test timeouts**
   - Increase timeout in playwright.config.ts
   - Check server performance
   - Verify test data setup

4. **Visual test failures**
   - Update baseline screenshots: `npm run test:visual -- --update-snapshots`
   - Adjust threshold in .env.testing

5. **AI generation errors**
   - Verify API key is set correctly
   - Check API rate limits
   - Ensure network connectivity

### Debug Mode
```bash
# Run with debug output
npm run test:debug

# Run specific test with debugging
npx playwright test tests/e2e/schedule.spec.ts --debug
```

## Best Practices

### Test Organization
- Group related tests in describe blocks
- Use descriptive test names
- Tag tests with categories (@functional, @integration, etc.)
- Keep tests independent and atomic

### Performance
- Use page.waitForLoadState('networkidle') for dynamic content
- Implement proper timeouts
- Clean up test data after each test
- Use parallel execution when possible

### Maintenance
- Regularly update baseline screenshots
- Review and update AI-generated tests
- Monitor test execution times
- Keep test data fixtures current

### Security
- Never commit real credentials
- Use test-specific user accounts
- Validate security controls in tests
- Test authentication and authorization thoroughly

## Support and Documentation

- [Playwright Documentation](https://playwright.dev/)
- [Anthropic API Documentation](https://docs.anthropic.com/)
- [D8TL Project Rules](../docs/project-rules.md)
- [MVP Requirements](../docs/MVP/mvp-requirements.md)

## Contributing

1. Follow the project's QA testing rules
2. Generate AI tests for new features
3. Include all mandatory test categories
4. Update documentation for new test patterns
5. Ensure tests pass in CI/CD pipeline

---

For questions or issues, refer to the project documentation or create an issue in the project repository.

