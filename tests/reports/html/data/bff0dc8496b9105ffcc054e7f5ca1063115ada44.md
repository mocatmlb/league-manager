# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: admin-schedule-conflict-prompt.spec.ts >> Admin Schedule Change — Conflict Prompt (Story 20.3) >> conflict_confirmed in querystring does not trigger a save on GET @security
- Location: tests/e2e/admin-schedule-conflict-prompt.spec.ts:172:7

# Error details

```
Error: expect(locator).toContainText(expected) failed

Locator: locator('h1')
Expected substring: "Schedule Management"
Received string:    "Welcome to District 8 Travel League"
Timeout: 15000ms

Call log:
  - Expect "toContainText" with timeout 15000ms
  - waiting for locator('h1')
    19 × locator resolved to <h1 class="display-4">Welcome to District 8 Travel League</h1>
       - unexpected value "Welcome to District 8 Travel League"

```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - navigation [ref=e2]:
    - generic [ref=e3]:
      - link "⚾ District 8 Travel League" [ref=e4] [cursor=pointer]:
        - /url: ./index.php
      - generic [ref=e5]:
        - list [ref=e6]:
          - listitem [ref=e7]:
            - link " Home" [ref=e8] [cursor=pointer]:
              - /url: ./index.php
              - generic [ref=e9]: 
              - text: Home
          - listitem [ref=e10]:
            - link " Schedule" [ref=e11] [cursor=pointer]:
              - /url: ./schedule.php
              - generic [ref=e12]: 
              - text: Schedule
          - listitem [ref=e13]:
            - link " Standings" [ref=e14] [cursor=pointer]:
              - /url: ./standings.php
              - generic [ref=e15]: 
              - text: Standings
        - list [ref=e16]:
          - listitem [ref=e17]:
            - link " Login" [ref=e18] [cursor=pointer]:
              - /url: ./login.php
              - generic [ref=e19]: 
              - text: Login
  - generic [ref=e20]:
    - generic [ref=e23]:
      - heading "Welcome to District 8 Travel League" [level=1] [ref=e24]
      - paragraph [ref=e25]: Your source for schedules, standings, and league information.
    - generic [ref=e26]:
      - generic [ref=e28]:
        - heading "Today's Games" [level=3] [ref=e30]
        - paragraph [ref=e32]: No games scheduled for today.
      - generic [ref=e34]:
        - heading "Next 7 Days" [level=3] [ref=e36]
        - paragraph [ref=e38]: No upcoming games in the next 7 days.
    - generic [ref=e41]:
      - generic [ref=e43]:
        - generic [ref=e44]: 
        - strong [ref=e45]: Weather
      - tabpanel [ref=e47]:
        - generic [ref=e48]:
          - generic [ref=e49]:
            - generic [ref=e50]: 
            - text: Syracuse, NY
          - generic [ref=e51]: Updated 9:40 pm
        - generic [ref=e53]:
          - generic [ref=e54]: 
          - generic [ref=e55]:
            - generic [ref=e56]: 78°F
            - generic [ref=e57]: Overcast
          - generic [ref=e58]:
            - generic [ref=e59]:
              - text: H
              - strong [ref=e60]: 90°
              - text: L
              - strong [ref=e61]: 61°
            - generic [ref=e62]:
              - text: Wind
              - strong [ref=e63]: 3 mph
            - generic [ref=e64]:
              - generic [ref=e66]: 
              - strong [ref=e67]: 44%
        - generic [ref=e68]:
          - generic [ref=e69]: Today — Hourly
          - generic [ref=e70]:
            - generic [ref=e71]:
              - generic [ref=e72]: Now
              - generic [ref=e73]: 
              - generic [ref=e74]: 80°
              - generic [ref=e75]:
                - generic [ref=e76]: 
                - text: 24%
            - generic [ref=e77]:
              - generic [ref=e78]: 10 pm
              - generic [ref=e79]: 
              - generic [ref=e80]: 78°
              - generic [ref=e81]:
                - generic [ref=e82]: 
                - text: 22%
            - generic [ref=e83]:
              - generic [ref=e84]: 11 pm
              - generic [ref=e85]: 
              - generic [ref=e86]: 76°
              - generic [ref=e87]:
                - generic [ref=e88]: 
                - text: 44%
        - generic [ref=e89]:
          - generic [ref=e90]: 7-Day Forecast
          - generic [ref=e91]:
            - generic [ref=e92]: Today
            - generic [ref=e93]: 
            - generic [ref=e94]: Overcast
            - generic [ref=e95]:
              - generic [ref=e96]: 
              - text: 44%
            - generic [ref=e97]:
              - strong [ref=e98]: 90°
              - text: 61°
          - generic [ref=e99]:
            - generic [ref=e100]: Wed Jun 10
            - generic [ref=e101]: 
            - generic [ref=e102]: Rain Showers
            - generic [ref=e103]:
              - generic [ref=e104]: 
              - text: 49%
            - generic [ref=e105]:
              - strong [ref=e106]: 88°
              - text: 71°
          - generic [ref=e107]:
            - generic [ref=e108]: Thu Jun 11
            - generic [ref=e109]: 
            - generic [ref=e110]: Drizzle
            - generic [ref=e111]:
              - generic [ref=e112]: 
              - text: 79%
            - generic [ref=e113]:
              - strong [ref=e114]: 80°
              - text: 68°
          - generic [ref=e115]:
            - generic [ref=e116]: Fri Jun 12
            - generic [ref=e117]: 
            - generic [ref=e118]: Rain Showers
            - generic [ref=e119]:
              - generic [ref=e120]: 
              - text: 89%
            - generic [ref=e121]:
              - strong [ref=e122]: 85°
              - text: 61°
          - generic [ref=e123]:
            - generic [ref=e124]: Sat Jun 13
            - generic [ref=e125]: 
            - generic [ref=e126]: Partly Cloudy
            - generic [ref=e127]:
              - generic [ref=e128]: 
              - text: 26%
            - generic [ref=e129]:
              - strong [ref=e130]: 77°
              - text: 56°
          - generic [ref=e131]:
            - generic [ref=e132]: Sun Jun 14
            - generic [ref=e133]: 
            - generic [ref=e134]: Rain Showers
            - generic [ref=e135]:
              - generic [ref=e136]: 
              - text: 43%
            - generic [ref=e137]:
              - strong [ref=e138]: 70°
              - text: 53°
          - generic [ref=e139]:
            - generic [ref=e140]: Mon Jun 15
            - generic [ref=e141]: 
            - generic [ref=e142]: Light Drizzle
            - generic [ref=e143]:
              - generic [ref=e144]: 
              - text: 43%
            - generic [ref=e145]:
              - strong [ref=e146]: 71°
              - text: 50°
  - contentinfo [ref=e147]:
    - generic [ref=e150]:
      - paragraph [ref=e151]: © 2026 District 8 Travel League. All rights reserved.
      - paragraph [ref=e152]:
        - link "About" [ref=e153] [cursor=pointer]:
          - /url: about.php
        - link "Privacy Policy" [ref=e154] [cursor=pointer]:
          - /url: privacy-policy.php
        - link "Terms & Conditions" [ref=e155] [cursor=pointer]:
          - /url: terms.php
```

# Test source

```ts
  75  |       await expect(goBackLink).toBeVisible();
  76  | 
  77  |       // Hidden conflict_confirmed input inside the confirm form
  78  |       const hiddenInput = warningBlock.locator('input[name="conflict_confirmed"][value="1"]');
  79  |       await expect(hiddenInput).toHaveCount(1);
  80  |     } else {
  81  |       console.log('ℹ️  No conflict warning on page — test data may not have conflicting games. Skipping assertion.');
  82  |     }
  83  |   });
  84  | 
  85  |   test('AC2 — Direct change confirm form contains conflict_confirmed=1 hidden field @functional', async ({ page }) => {
  86  |     const warningBlock = page.locator('.alert-warning').filter({ hasText: /conflict/i });
  87  | 
  88  |     if (await warningBlock.isVisible().catch(() => false)) {
  89  |       const form = warningBlock.locator('form');
  90  |       await expect(form).toHaveCount(1);
  91  | 
  92  |       const hiddenConfirmed = form.locator('input[name="conflict_confirmed"]');
  93  |       await expect(hiddenConfirmed).toHaveAttribute('value', '1');
  94  | 
  95  |       const hiddenAction = form.locator('input[name="action"]');
  96  |       await expect(hiddenAction).toHaveCount(1);
  97  |     } else {
  98  |       console.log('ℹ️  Conflict warning not present — skipping confirm-form structure assertion.');
  99  |     }
  100 |   });
  101 | 
  102 |   // -------------------------------------------------------------------------
  103 |   // AC4: Postponement approve path does not trigger conflict warning
  104 |   // -------------------------------------------------------------------------
  105 | 
  106 |   test('AC4 — Page renders without conflict warning on a clean GET @functional', async ({ page }) => {
  107 |     const warningBlock = page.locator('.alert-warning').filter({ hasText: /conflict/i });
  108 |     await expect(warningBlock).toHaveCount(0);
  109 |     await expect(page.locator('.alert-danger')).toHaveCount(0);
  110 |   });
  111 | 
  112 |   // -------------------------------------------------------------------------
  113 |   // AC5: Go Back navigates to clean page
  114 |   // -------------------------------------------------------------------------
  115 | 
  116 |   test('AC5 — Go Back link returns to a clean Schedule Management page @functional', async ({ page }) => {
  117 |     const warningBlock = page.locator('.alert-warning').filter({ hasText: /conflict/i });
  118 | 
  119 |     if (await warningBlock.isVisible().catch(() => false)) {
  120 |       const goBackLink = warningBlock.locator('a.btn', { hasText: /go back/i });
  121 |       await goBackLink.click();
  122 |       await page.waitForLoadState('networkidle');
  123 |       await expect(page.locator('h1')).toContainText('Schedule Management');
  124 |       await expect(page.locator('.alert-warning').filter({ hasText: /conflict/i })).toHaveCount(0);
  125 |     } else {
  126 |       console.log('ℹ️  No warning block to test Go Back from — skipping.');
  127 |     }
  128 |   });
  129 | 
  130 |   // -------------------------------------------------------------------------
  131 |   // AC6: Multiple conflicts listed as separate bullets
  132 |   // -------------------------------------------------------------------------
  133 | 
  134 |   test('AC6 — Multiple conflicts render as separate bullet points @functional', async ({ page }) => {
  135 |     const warningBlock = page.locator('.alert-warning').filter({ hasText: /conflict/i });
  136 | 
  137 |     if (await warningBlock.isVisible().catch(() => false)) {
  138 |       const bullets = warningBlock.locator('ul li');
  139 |       const count = await bullets.count();
  140 |       if (count > 1) {
  141 |         for (let i = 0; i < count; i++) {
  142 |           await expect(bullets.nth(i)).not.toBeEmpty();
  143 |         }
  144 |         console.log(`✅ ${count} conflict bullets rendered separately`);
  145 |       } else {
  146 |         console.log('ℹ️  Only one conflict present — multi-bullet scenario not exercised.');
  147 |       }
  148 |     } else {
  149 |       console.log('ℹ️  No conflict warning present — AC6 multi-bullet check skipped.');
  150 |     }
  151 |   });
  152 | 
  153 |   // -------------------------------------------------------------------------
  154 |   // Coach-facing suffix must be stripped from admin warning text
  155 |   // -------------------------------------------------------------------------
  156 | 
  157 |   test('Warning text does not contain the coach-facing review suffix @security @functional', async ({ page }) => {
  158 |     const warningBlock = page.locator('.alert-warning').filter({ hasText: /conflict/i });
  159 | 
  160 |     if (await warningBlock.isVisible().catch(() => false)) {
  161 |       const warningText = await warningBlock.textContent();
  162 |       expect(warningText).not.toContain('an admin will need to review');
  163 |     } else {
  164 |       console.log('ℹ️  No conflict warning — coach suffix check skipped.');
  165 |     }
  166 |   });
  167 | 
  168 |   // -------------------------------------------------------------------------
  169 |   // Security: conflict_confirmed via GET must not trigger a save
  170 |   // -------------------------------------------------------------------------
  171 | 
  172 |   test('conflict_confirmed in querystring does not trigger a save on GET @security', async ({ page }) => {
  173 |     await page.goto('/admin/schedules/index.php?conflict_confirmed=1');
  174 |     await page.waitForLoadState('networkidle');
> 175 |     await expect(page.locator('h1')).toContainText('Schedule Management');
      |                                      ^ Error: expect(locator).toContainText(expected) failed
  176 |     await expect(page.locator('.alert-success')).toHaveCount(0);
  177 |   });
  178 | 
  179 |   // -------------------------------------------------------------------------
  180 |   // Regression: normal page layout is intact
  181 |   // -------------------------------------------------------------------------
  182 | 
  183 |   test('Regression — Page layout is intact, no PHP errors @regression', async ({ page }) => {
  184 |     await expect(page.locator('.alert-danger')).toHaveCount(0);
  185 |     // The "Process Schedule Change" button and page heading must still be present
  186 |     await expect(page.locator('h1')).toContainText('Schedule Management');
  187 |     await expect(page.locator('button', { hasText: 'Process Schedule Change' })).toBeVisible();
  188 |   });
  189 | });
  190 | 
```