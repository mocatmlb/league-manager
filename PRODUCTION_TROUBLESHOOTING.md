# Production Troubleshooting Guide

## Current Issue
```
PHP Fatal error: Failed opening required '../includes/bootstrap.php' in /home2/moc835/public_html/district8travelleague.com/index.php:6
```

## Root Cause
The production server is still using the old `index.php` file with development paths. The deployment process needs to be run to apply the fixes.

## Immediate Fix Options

### Option 1: Quick Manual Fix (2 minutes)
**Via cPanel File Manager:**

1. **Log into cPanel**
2. **Open File Manager**
3. **Navigate to** `/public_html/district8travelleague.com/`
4. **Edit `index.php`** - Change line 6 from:
   ```php
   require_once dirname(__DIR__) . '/includes/bootstrap.php';
   ```
   to:
   ```php
   require_once __DIR__ . '/includes/bootstrap.php';
   ```

5. **Edit `schedule.php`** - Change line 6 from:
   ```php
   require_once dirname(__DIR__) . '/includes/bootstrap.php';
   ```
   to:
   ```php
   require_once __DIR__ . '/includes/bootstrap.php';
   ```

6. **Edit `standings.php`** - Change line 6 from:
   ```php
   require_once dirname(__DIR__) . '/includes/bootstrap.php';
   ```
   to:
   ```php
   require_once __DIR__ . '/includes/bootstrap.php';
   ```

### Option 2: SSH Command Fix (If SSH access available)
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

### Option 3: Re-deploy via Git (Recommended for long-term)
1. **Push changes to main branch**
2. **Trigger cPanel Git deployment**
3. **The updated `.cpanel.yml` will automatically fix the paths**

## Verification Steps

After applying the fix:

1. **Test main pages:**
   - Visit `http://district8travelleague.com/`
   - Visit `http://district8travelleague.com/schedule.php`
   - Visit `http://district8travelleague.com/standings.php`

2. **Test authentication:**
   - Visit `http://district8travelleague.com/coaches/login.php`
   - Visit `http://district8travelleague.com/admin/login.php`

3. **Check for errors:**
   - Look for any PHP errors in the browser
   - Check cPanel error logs

## File Structure Verification

The production structure should be:
```
/home2/moc835/public_html/district8travelleague.com/
├── index.php (with __DIR__ . '/includes/bootstrap.php')
├── includes/
│   ├── bootstrap.php
│   ├── config.php
│   └── ...
├── coaches/
│   ├── login.php (with dirname(__DIR__) . '/includes/bootstrap.php')
│   └── ...
├── admin/
│   ├── login.php (with dirname(dirname(__DIR__)) . '/includes/bootstrap.php')
│   └── ...
└── ...
```

## Common Issues

### Issue 1: "includes" directory missing
**Solution:** Ensure the `includes/` directory was copied during deployment

### Issue 2: Wrong file permissions
**Solution:** Set proper permissions:
```bash
chmod 644 *.php
chmod 755 includes/
chmod 644 includes/*.php
```

### Issue 3: Still getting path errors after fix
**Solution:** Clear any PHP opcache:
```bash
# Via cPanel or SSH
php -r "opcache_reset();"
```

## Prevention for Future Deployments

The `.cpanel.yml` file has been updated to automatically fix these paths during deployment. Future deployments will not have this issue.

## Emergency Rollback

If something goes wrong, you can quickly revert by changing the paths back:
```php
// Change back to:
require_once dirname(__DIR__) . '/includes/bootstrap.php';
```

But this will only work if you also move the `includes/` directory to the parent level.
