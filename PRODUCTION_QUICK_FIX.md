# Production Quick Fix - Path Issues

## Current Issue
The production server is showing this error:
```
PHP Fatal error: Failed opening required '../includes/bootstrap.php' in /home2/moc835/public_html/district8travelleague.com/index.php:6
```

## Root Cause
The cPanel deployment copies `public/` files to the root, but the paths still reference the development structure.

## Quick Fix Options

### Option 1: Manual File Edit (Fastest)
1. Log into cPanel File Manager
2. Navigate to `/public_html/district8travelleague.com/`
3. Edit `index.php` and change line 6 from:
   ```php
   require_once dirname(__DIR__) . '/includes/bootstrap.php';
   ```
   to:
   ```php
   require_once __DIR__ . '/includes/bootstrap.php';
   ```

4. Edit `schedule.php` and change line 6 from:
   ```php
   require_once dirname(__DIR__) . '/includes/bootstrap.php';
   ```
   to:
   ```php
   require_once __DIR__ . '/includes/bootstrap.php';
   ```

5. Edit `standings.php` and change line 6 from:
   ```php
   require_once dirname(__DIR__) . '/includes/bootstrap.php';
   ```
   to:
   ```php
   require_once __DIR__ . '/includes/bootstrap.php';
   ```

6. For coaches files, edit each file in the `coaches/` directory and change:
   ```php
   require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
   ```
   to:
   ```php
   require_once dirname(__DIR__) . '/includes/bootstrap.php';
   ```

### Option 2: SSH Command (If SSH access available)
```bash
cd /home2/moc835/public_html/district8travelleague.com

# Fix main files
sed -i "s|dirname(__DIR__) . '/includes/bootstrap\.php'|__DIR__ . '/includes/bootstrap.php'|g" index.php
sed -i "s|dirname(__DIR__) . '/includes/bootstrap\.php'|__DIR__ . '/includes/bootstrap.php'|g" schedule.php
sed -i "s|dirname(__DIR__) . '/includes/bootstrap\.php'|__DIR__ . '/includes/bootstrap.php'|g" standings.php

# Fix coaches files
sed -i "s|dirname(dirname(__DIR__)) . '/includes/bootstrap\.php'|dirname(__DIR__) . '/includes/bootstrap.php'|g" coaches/login.php
sed -i "s|dirname(dirname(__DIR__)) . '/includes/bootstrap\.php'|dirname(__DIR__) . '/includes/bootstrap.php'|g" coaches/logout.php
sed -i "s|dirname(dirname(__DIR__)) . '/includes/bootstrap\.php'|dirname(__DIR__) . '/includes/bootstrap.php'|g" coaches/score-input.php
sed -i "s|dirname(dirname(__DIR__)) . '/includes/bootstrap\.php'|dirname(__DIR__) . '/includes/bootstrap.php'|g" coaches/schedule-change.php
sed -i "s|dirname(dirname(__DIR__)) . '/includes/bootstrap\.php'|dirname(__DIR__) . '/includes/bootstrap.php'|g" coaches/dashboard.php
sed -i "s|dirname(dirname(__DIR__)) . '/includes/bootstrap\.php'|dirname(__DIR__) . '/includes/bootstrap.php'|g" coaches/contacts.php
```

### Option 3: Upload Fixed Files
1. Download the current files from production
2. Apply the fixes locally
3. Upload the fixed files back to production

## Files That Need Fixing

### Main Public Files (change `dirname(__DIR__)` to `__DIR__`):
- `index.php` (line 6)
- `schedule.php` (line 6)
- `standings.php` (line 6)

### Coaches Files (change `dirname(dirname(__DIR__))` to `dirname(__DIR__)`):
- `coaches/login.php` (line 6)
- `coaches/logout.php` (line 6)
- `coaches/score-input.php` (line 6)
- `coaches/schedule-change.php` (line 6)
- `coaches/dashboard.php` (line 6)
- `coaches/contacts.php` (line 6)

### Admin Files (NO CHANGES NEEDED):
- Admin files use `dirname(dirname(__DIR__))` which is correct for production

## Verification
After applying the fixes:
1. Visit `https://district8travelleague.com/` - should load without errors
2. Visit `https://district8travelleague.com/schedule.php` - should load without errors
3. Visit `https://district8travelleague.com/standings.php` - should load without errors
4. Visit `https://district8travelleague.com/coaches/login.php` - should load without errors

## Future Deployments
The `.cpanel.yml` file has been updated to automatically apply these fixes during future deployments, so this manual fix only needs to be done once.
