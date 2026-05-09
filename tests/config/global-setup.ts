import type { FullConfig } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

async function globalSetup(_config: FullConfig) {
  const dirs = [
    path.join(process.cwd(), 'tests', 'reports', 'html'),
    path.join(process.cwd(), 'tests', 'reports', 'screenshots'),
    path.join(process.cwd(), 'tests', 'reports', 'artifacts'),
  ];
  for (const d of dirs) {
    fs.mkdirSync(d, { recursive: true });
  }
}

export default globalSetup;
