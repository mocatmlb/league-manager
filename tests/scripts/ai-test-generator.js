/**
 * AI-Powered Test Generation Script for D8TL
 * Integrates with Cursor/Claude to automatically generate comprehensive Playwright tests
 * 
 * Usage:
 *   node ai-test-generator.js scan                                    # Scan entire app
 *   node ai-test-generator.js page <url> <name> [stories...]         # Generate page test
 *   node ai-test-generator.js workflow <name> <step1> <step2> ...    # Generate workflow test
 */

const fs = require('fs-extra');
const path = require('path');
const { Anthropic } = require('@anthropic-ai/sdk');
require('dotenv').config({ path: '.env.testing' });

class AITestGenerator {
  constructor() {
    this.anthropic = new Anthropic({
      apiKey: process.env.ANTHROPIC_API_KEY,
    });
    this.baseUrl = process.env.TEST_BASE_URL || 'http://localhost:8080';
    this.outputDir = path.join(__dirname, '../ai-generated');
    this.e2eDir = path.join(__dirname, '../e2e');
  }

  async generateTestsForPage(pageUrl, pageName, userStories = []) {
    console.log(`🤖 Generating AI test for: ${pageName}`);
    
    const prompt = `
You are an expert QA engineer creating comprehensive Playwright tests for a District 8 Travel League (D8TL) web application.

Page: ${pageName}
URL: ${pageUrl}
User Stories: ${userStories.length > 0 ? userStories.join('\n- ') : 'Standard page functionality'}

Based on the D8TL project QA requirements, generate a complete Playwright test suite that includes:

1. **Functional Testing (@functional)**
   - Core functionality verification
   - User workflow testing
   - Edge case handling
   - Input validation testing
   - Form submission and validation

2. **UI/UX Testing**
   - Element visibility and accessibility
   - Form validation and error messages
   - Mobile responsiveness
   - Cross-browser compatibility
   - Navigation testing

3. **Integration Testing (@integration)**
   - API endpoint testing (if applicable)
   - Database integration verification
   - Authentication and authorization
   - Cross-feature interactions

4. **Performance Testing (@performance)**
   - Page load time verification (max 3000ms)
   - Resource loading checks
   - Memory usage monitoring

5. **Visual Testing (@visual)**
   - Screenshot comparisons for regression testing
   - Layout validation across viewports

6. **Security Testing (@security)**
   - Authentication bypass attempts
   - Input sanitization validation
   - Authorization checks

Generate TypeScript Playwright test code with:
- Proper test structure using describe/test blocks
- Page Object Model pattern usage
- Comprehensive assertions with expect()
- Error handling and edge cases
- Screenshot capture for critical paths
- Mobile testing scenarios
- Accessibility checks using basic ARIA validation
- Performance assertions
- Proper test tags (@functional, @integration, @performance, @visual, @security)
- beforeEach and afterEach hooks for setup/cleanup
- Use of D8TLTestHelper and D8TLPageObjects from '../helpers/test-helpers'

The test should be production-ready, follow Playwright best practices, and align with D8TL QA requirements.

Return ONLY the TypeScript test code, properly formatted and ready to run.
`;

    try {
      const response = await this.anthropic.messages.create({
        model: 'claude-3-sonnet-20241022',
        max_tokens: 4000,
        messages: [{
          role: 'user',
          content: prompt
        }]
      });

      const testCode = response.content[0].text;
      
      // Clean up the response to ensure it's valid TypeScript
      const cleanedCode = this.cleanGeneratedCode(testCode);
      
      // Save generated test
      const fileName = `${pageName.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '')}.ai-generated.spec.ts`;
      const filePath = path.join(this.outputDir, fileName);
      
      await fs.ensureDir(this.outputDir);
      await fs.writeFile(filePath, cleanedCode);
      
      console.log(`✅ Generated test: ${fileName}`);
      console.log(`📁 Location: ${filePath}`);
      
      return filePath;
      
    } catch (error) {
      console.error(`❌ Error generating test for ${pageName}:`, error.message);
      
      if (error.message.includes('API key')) {
        console.error('💡 Make sure to set ANTHROPIC_API_KEY in your .env.testing file');
      }
      
      throw error;
    }
  }

  async generateWorkflowTests(workflowName, steps) {
    console.log(`🤖 Generating AI workflow test: ${workflowName}`);
    
    const prompt = `
Generate a comprehensive end-to-end workflow test for the District 8 Travel League application.

Workflow: ${workflowName}
Steps: ${steps.join('\n')}

Create a Playwright test that:
1. Tests the complete user journey from start to finish
2. Includes proper authentication if required using D8TLTestHelper
3. Validates data persistence across pages
4. Handles error scenarios gracefully
5. Takes screenshots at critical steps
6. Includes performance assertions (max 3000ms page loads)
7. Tests mobile compatibility
8. Uses Page Object Model pattern
9. Includes proper test tags (@workflow, @integration, @functional)
10. Follows D8TL QA testing requirements for comprehensive coverage

The test should:
- Use describe/test blocks for organization
- Include beforeEach/afterEach hooks
- Use D8TLTestHelper and D8TLPageObjects from '../helpers/test-helpers'
- Have comprehensive error handling
- Include visual regression testing at key steps
- Validate business logic and data flow
- Test authentication and authorization where applicable

Return ONLY the TypeScript Playwright test code, properly formatted and ready to run.
`;

    try {
      const response = await this.anthropic.messages.create({
        model: 'claude-3-sonnet-20241022',
        max_tokens: 4000,
        messages: [{
          role: 'user',
          content: prompt
        }]
      });

      const testCode = response.content[0].text;
      const cleanedCode = this.cleanGeneratedCode(testCode);
      
      const fileName = `${workflowName.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '')}.workflow.spec.ts`;
      const filePath = path.join(this.outputDir, fileName);
      
      await fs.ensureDir(this.outputDir);
      await fs.writeFile(filePath, cleanedCode);
      
      console.log(`✅ Generated workflow test: ${fileName}`);
      console.log(`📁 Location: ${filePath}`);
      
      return filePath;
      
    } catch (error) {
      console.error(`❌ Error generating workflow test for ${workflowName}:`, error.message);
      throw error;
    }
  }

  cleanGeneratedCode(code) {
    // Remove markdown code blocks if present
    let cleaned = code.replace(/```typescript\n?/g, '').replace(/```\n?/g, '');
    
    // Ensure proper imports are at the top
    if (!cleaned.includes("import { test, expect } from '@playwright/test'")) {
      cleaned = "import { test, expect } from '@playwright/test';\n" + cleaned;
    }
    
    if (!cleaned.includes("import { D8TLTestHelper, D8TLPageObjects } from '../helpers/test-helpers'")) {
      cleaned = cleaned.replace(
        "import { test, expect } from '@playwright/test';",
        "import { test, expect } from '@playwright/test';\nimport { D8TLTestHelper, D8TLPageObjects } from '../helpers/test-helpers';"
      );
    }
    
    // Ensure proper formatting
    cleaned = cleaned.trim();
    
    return cleaned;
  }

  async scanApplicationAndGenerateTests() {
    console.log('🔍 Scanning D8TL application for comprehensive test generation...');
    console.log(`🎯 Base URL: ${this.baseUrl}`);
    
    // Define key pages and workflows to test based on D8TL MVP requirements
    const pagesToTest = [
      {
        url: '/',
        name: 'Public Homepage',
        stories: [
          'View current schedules and standings',
          'Navigate to coaches section',
          'Access public information',
          'Mobile responsive navigation'
        ]
      },
      {
        url: '/schedule.php',
        name: 'Public Schedule',
        stories: [
          'View game schedules by division',
          'Filter games by program and division',
          'Search for specific teams or games',
          'View game details and locations',
          'Mobile schedule viewing'
        ]
      },
      {
        url: '/standings.php',
        name: 'Public Standings',
        stories: [
          'View team standings by division',
          'Sort teams by wins, losses, percentage',
          'View division standings',
          'Mobile standings display'
        ]
      },
      {
        url: '/coaches/login.php',
        name: 'Coach Login',
        stories: [
          'Login with valid coach credentials',
          'Handle invalid login attempts',
          'Password validation and security',
          'Redirect to coach dashboard after login',
          'Mobile login experience'
        ]
      },
      {
        url: '/coaches/dashboard.php',
        name: 'Coach Dashboard',
        stories: [
          'View team information and roster',
          'Access schedule change requests',
          'Navigate to score input',
          'View team schedule and standings',
          'Mobile dashboard functionality'
        ]
      },
      {
        url: '/coaches/schedule-change.php',
        name: 'Schedule Change Request',
        stories: [
          'Submit new schedule change request',
          'View pending change requests',
          'Cancel existing requests',
          'Validate request form inputs',
          'Mobile schedule change interface'
        ]
      },
      {
        url: '/coaches/score-input.php',
        name: 'Score Input',
        stories: [
          'Input game scores for completed games',
          'Validate score input format',
          'Submit scores and update standings',
          'Handle score input errors',
          'Mobile score input'
        ]
      },
      {
        url: '/admin/login.php',
        name: 'Admin Login',
        stories: [
          'Admin authentication with secure credentials',
          'Handle authentication failures',
          'Redirect to admin dashboard',
          'Security validation and session management'
        ]
      },
      {
        url: '/admin/',
        name: 'Admin Dashboard',
        stories: [
          'Access all administrative functions',
          'Navigate to team management',
          'Access game and schedule management',
          'View system logs and reports',
          'Mobile admin interface'
        ]
      },
      {
        url: '/admin/teams/',
        name: 'Team Management',
        stories: [
          'Create and edit teams',
          'Assign teams to divisions',
          'Manage team rosters',
          'Validate team data',
          'Bulk team operations'
        ]
      },
      {
        url: '/admin/games/',
        name: 'Game Management',
        stories: [
          'Create and schedule games',
          'Assign teams to games',
          'Update game details and locations',
          'Cancel and reschedule games',
          'Bulk game operations'
        ]
      },
      {
        url: '/admin/schedules/',
        name: 'Schedule Management',
        stories: [
          'Review schedule change requests',
          'Approve or deny change requests',
          'Manage game schedules',
          'Generate schedule reports'
        ]
      }
    ];

    const workflowsToTest = [
      {
        name: 'Complete Coach Schedule Change Workflow',
        steps: [
          'Login as coach with valid credentials',
          'Navigate to schedule change request page',
          'Select game to reschedule',
          'Fill out change request form with valid data',
          'Submit change request successfully',
          'Verify request appears in pending status',
          'Login as admin to review request',
          'Admin approves the schedule change',
          'Verify schedule is updated in public view',
          'Verify coach receives confirmation'
        ]
      },
      {
        name: 'Score Input and Standings Update Workflow',
        steps: [
          'Login as coach for team with completed game',
          'Navigate to score input page',
          'Select completed game from list',
          'Enter valid home and away team scores',
          'Submit scores with proper validation',
          'Verify scores are saved correctly',
          'Check that standings are automatically updated',
          'Verify updated standings appear in public view',
          'Test mobile score input process'
        ]
      },
      {
        name: 'Complete Admin Game Management Workflow',
        steps: [
          'Login as admin with full permissions',
          'Navigate to game management section',
          'Create new game with team assignments',
          'Set game date, time, and location',
          'Verify game appears in schedule',
          'Update game details and location',
          'Test game cancellation process',
          'Verify changes reflect in public schedule',
          'Test bulk game operations'
        ]
      },
      {
        name: 'End-to-End Team Registration and Game Flow',
        steps: [
          'Admin creates new team in system',
          'Assign team to appropriate division',
          'Create games for the new team',
          'Coach logs in and views team schedule',
          'Coach submits scores for completed games',
          'Verify standings update correctly',
          'Admin reviews and manages team data',
          'Test complete season workflow'
        ]
      },
      {
        name: 'Public User Information Access Workflow',
        steps: [
          'Visit public homepage without authentication',
          'Navigate to public schedule page',
          'Filter schedule by different divisions',
          'View team standings and rankings',
          'Test mobile responsive navigation',
          'Verify all public data displays correctly',
          'Test search and filter functionality',
          'Validate performance on mobile devices'
        ]
      }
    ];

    let generatedCount = 0;
    let failedCount = 0;

    // Generate tests for each page
    console.log(`\n📄 Generating tests for ${pagesToTest.length} pages...`);
    for (const page of pagesToTest) {
      try {
        await this.generateTestsForPage(page.url, page.name, page.stories);
        generatedCount++;
      } catch (error) {
        console.error(`❌ Failed to generate test for ${page.name}:`, error.message);
        failedCount++;
      }
    }

    // Generate workflow tests
    console.log(`\n🔄 Generating ${workflowsToTest.length} workflow tests...`);
    for (const workflow of workflowsToTest) {
      try {
        await this.generateWorkflowTests(workflow.name, workflow.steps);
        generatedCount++;
      } catch (error) {
        console.error(`❌ Failed to generate workflow test for ${workflow.name}:`, error.message);
        failedCount++;
      }
    }

    // Generate summary report
    console.log('\n📊 Test Generation Summary:');
    console.log(`✅ Successfully generated: ${generatedCount} tests`);
    console.log(`❌ Failed to generate: ${failedCount} tests`);
    console.log(`📁 Tests saved to: ${this.outputDir}`);
    
    if (generatedCount > 0) {
      console.log('\n🎉 Test generation complete!');
      console.log('\n📋 Next steps:');
      console.log('1. Review generated tests in tests/ai-generated/');
      console.log('2. Move relevant tests to tests/e2e/ for execution');
      console.log('3. Run tests with: npm run test');
      console.log('4. View reports with: npm run test:report');
    }

    if (failedCount > 0) {
      console.log('\n⚠️  Some tests failed to generate. Check your API key and network connection.');
    }

    return { generated: generatedCount, failed: failedCount };
  }

  async validateEnvironment() {
    console.log('🔍 Validating test environment...');
    
    // Check API key
    if (!process.env.ANTHROPIC_API_KEY) {
      throw new Error('ANTHROPIC_API_KEY not found in environment. Please set it in .env.testing');
    }
    
    // Check output directories
    await fs.ensureDir(this.outputDir);
    await fs.ensureDir(this.e2eDir);
    await fs.ensureDir(path.join(__dirname, '../reports'));
    
    console.log('✅ Environment validation complete');
  }
}

// CLI interface
if (require.main === module) {
  const generator = new AITestGenerator();
  
  const action = process.argv[2];
  
  async function main() {
    try {
      await generator.validateEnvironment();
      
      if (action === 'scan') {
        await generator.scanApplicationAndGenerateTests();
      } else if (action === 'page') {
        const url = process.argv[3];
        const name = process.argv[4];
        const stories = process.argv.slice(5);
        
        if (!url || !name) {
          console.error('Usage: node ai-test-generator.js page <url> <name> [stories...]');
          console.error('Example: node ai-test-generator.js page "/schedule.php" "Public Schedule" "View games" "Filter by division"');
          process.exit(1);
        }
        
        await generator.generateTestsForPage(url, name, stories);
      } else if (action === 'workflow') {
        const name = process.argv[3];
        const steps = process.argv.slice(4);
        
        if (!name || steps.length === 0) {
          console.error('Usage: node ai-test-generator.js workflow <name> <step1> <step2> ...');
          console.error('Example: node ai-test-generator.js workflow "Login Flow" "Navigate to login" "Enter credentials" "Submit form"');
          process.exit(1);
        }
        
        await generator.generateWorkflowTests(name, steps);
      } else {
        console.log('🤖 D8TL AI Test Generator');
        console.log('\nAvailable commands:');
        console.log('  scan                                    - Scan entire application and generate comprehensive tests');
        console.log('  page <url> <name> [stories...]         - Generate test for specific page');
        console.log('  workflow <name> <step1> <step2> ...    - Generate workflow test');
        console.log('\nExamples:');
        console.log('  node ai-test-generator.js scan');
        console.log('  node ai-test-generator.js page "/schedule.php" "Public Schedule" "View games" "Filter by division"');
        console.log('  node ai-test-generator.js workflow "Login Flow" "Navigate to login" "Enter credentials" "Submit form"');
        console.log('\nMake sure to set ANTHROPIC_API_KEY in your .env.testing file before running.');
      }
    } catch (error) {
      console.error('❌ Error:', error.message);
      process.exit(1);
    }
  }
  
  main();
}

module.exports = AITestGenerator;

