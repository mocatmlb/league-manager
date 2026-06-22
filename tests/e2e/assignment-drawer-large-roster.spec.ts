import { expect, test } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const drawerScript = fs.readFileSync(
  path.join(__dirname, '../../public/admin/umpires/assignment-drawer.js'),
  'utf8'
);

type Umpire = {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  umpire_level: string;
  is_under_18: number;
  current_game_load: number;
};

function roster(size = 130): Umpire[] {
  return Array.from({ length: size }, (_, index) => {
    const id = 101 + index;
    return {
      id,
      first_name: `Umpire${id}`,
      last_name: index % 2 === 0 ? 'Blue' : 'Black',
      email: `umpire${id}@example.test`,
      phone: `555-01${String(index).padStart(2, '0')}`,
      umpire_level: index % 2 === 0 ? 'Blue Shirt' : 'Black Shirt',
      is_under_18: index % 10 === 0 ? 1 : 0,
      current_game_load: index % 4,
    };
  });
}

async function mountDrawer(page) {
  const umpires = roster();

  await page.setContent(`
    <!doctype html>
    <html>
      <body>
        <button data-assignment-drawer-trigger data-game-id="55">Open Drawer</button>
        <div id="assignmentDrawer" data-csrf-token="test-token" data-page-mode="board" data-can-override="1">
          <h5 id="assignmentDrawerTitle"></h5>
          <div id="assignmentDrawerBody" aria-live="polite"></div>
        </div>
      </body>
    </html>
  `);

  await page.evaluate((seedRoster) => {
    const slots = {
      0: {
        slot_index: 0,
        status: 'Draft',
        umpire_user_id: 101,
        umpire: seedRoster[0],
        published: 0,
        migration_mode: 0,
      },
      1: {
        slot_index: 1,
        status: 'Open',
        umpire_user_id: null,
        umpire: null,
        published: 0,
        migration_mode: 0,
      },
    };

    (window as any).__drawerData = {
      game: {
        game_id: 55,
        game_number: 'G055',
        away_team: 'Away',
        home_team: 'Home',
        game_date: '2026-07-01',
        game_time: '18:00',
        location_name: 'Field 1',
        division_name: 'Majors',
      },
      slot_labels: { 0: 'Plate', 1: 'Bases' },
      slots,
      roster: seedRoster,
      migration_mode: false,
    };

    (window as any).bootstrap = {
      Offcanvas: {
        getOrCreateInstance: () => ({ show: () => undefined }),
      },
    };

    (window as any).fetch = async (url: string, options?: RequestInit) => {
      const data = (window as any).__drawerData;
      const urlText = String(url);

      if (urlText.includes('save-slot.php')) {
        const form = options?.body as FormData;
        const slotIndex = Number(form.get('slot_index'));
        const umpireId = Number(form.get('umpire_user_id'));
        const umpire = data.roster.find((row: Umpire) => row.id === umpireId);
        data.slots[slotIndex] = {
          slot_index: slotIndex,
          status: 'Draft',
          umpire_user_id: umpireId,
          umpire,
          published: 0,
          migration_mode: 0,
        };
      }

      if (urlText.includes('unassign-slot.php')) {
        const form = options?.body as FormData;
        const slotIndex = Number(form.get('slot_index'));
        data.slots[slotIndex] = {
          slot_index: slotIndex,
          status: 'Open',
          umpire_user_id: null,
          umpire: null,
          published: 0,
          migration_mode: 0,
        };
      }

      return {
        ok: true,
        json: async () => ({ success: true, data }),
      };
    };
  }, umpires);

  await page.addScriptTag({ content: drawerScript });
  await page.getByRole('button', { name: 'Open Drawer' }).click();
  await expect(page.getByText('Game Details')).toBeVisible();
}

test.describe('assignment drawer large-roster picker', () => {
  test('defaults to slot overview without repeated full roster lists', async ({ page }) => {
    await mountDrawer(page);

    await expect(page.locator('select')).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Change' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Choose' })).toBeVisible();
    await expect(page.locator('[data-umpire-result-id]')).toHaveCount(0);
    await expect(page.getByText('Umpire230')).toHaveCount(0);
  });

  test('searches filters and excludes the opposite slot umpire', async ({ page }) => {
    await mountDrawer(page);

    await page.getByRole('button', { name: 'Choose' }).click();
    await expect(page.locator('[data-umpire-result-id="101"]')).toHaveCount(0);
    await expect(page.getByText('129 available of 129 umpires')).toBeVisible();

    await page.getByLabel('Search umpires').fill('umpire130');
    await expect(page.locator('[data-umpire-result-id="130"]')).toBeVisible();
    await expect(page.locator('[data-umpire-result-id]')).toHaveCount(1);

    await page.getByRole('button', { name: 'Black Shirt' }).click();
    await expect(page.locator('[data-umpire-result-id="130"]')).toBeVisible();

    await page.getByRole('button', { name: 'Back' }).click();
    await expect(page.getByRole('button', { name: 'Choose' })).toBeFocused();
  });

  test('selection and unassign refresh available choices', async ({ page }) => {
    await mountDrawer(page);

    await page.getByRole('button', { name: 'Choose' }).click();
    await page.locator('[data-umpire-result-id="102"]').getByRole('button', { name: 'Select' }).click();

    await expect(page.getByText('Umpire102 Black')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Choose' })).toHaveCount(0);

    await page.getByRole('button', { name: 'Change' }).first().click();
    await expect(page.locator('[data-umpire-result-id="102"]')).toHaveCount(0);

    await page.getByRole('button', { name: 'Back' }).click();
    await page.getByRole('button', { name: 'Unassign' }).nth(1).click();
    await page.getByRole('button', { name: 'Change' }).first().click();
    await expect(page.locator('[data-umpire-result-id="102"]')).toBeVisible();
  });

  test('mobile-width picker controls remain reachable by keyboard', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await mountDrawer(page);

    await page.keyboard.press('Tab');
    await page.keyboard.press('Enter');
    await expect(page.getByLabel('Search umpires')).toBeFocused();

    await page.getByLabel('Search umpires').fill('umpire120');
    await expect(page.locator('[data-umpire-result-id="120"]')).toBeVisible();

    const visibleControls = [
      page.getByRole('button', { name: 'Back' }),
      page.getByRole('button', { name: 'All' }),
      page.getByRole('button', { name: 'Low load' }),
      page.locator('[data-umpire-result-id="120"]').getByRole('button', { name: 'Select' }),
    ];

    for (const control of visibleControls) {
      await expect(control).toBeVisible();
      const box = await control.boundingBox();
      expect(box?.x ?? -1).toBeGreaterThanOrEqual(0);
      expect((box?.x ?? 0) + (box?.width ?? 0)).toBeLessThanOrEqual(390);
    }
  });
});
