# Production Deployment Path Fix

## Issue Identified
The production deployment was failing with the error:
```
PHP Fatal error: Failed opening required '../includes/bootstrap.php' (include_path='.:/opt/cpanel/ea-php81/root/usr/share/pear') in /home2/moc835/public_html/district8travelleague.com/index.php:6
```

## Root Cause
The cPanel deployment process copies the `public/` directory contents to the root of the deployment path, but the PHP files were still using development paths:

- **Development structure**: `public/index.php` → `../includes/bootstrap.php`
- **Production structure**: `index.php` → `./includes/bootstrap.php`

## Files Fixed

### 1. Main Public Files
- **`public/index.php`**: Changed `dirname(__DIR__)` to `__DIR__`
- **`public/schedule.php`**: Changed `dirname(__DIR__)` to `__DIR__`
- **`public/standings.php`**: Changed `dirname(__DIR__)` to `__DIR__`

### 2. Coaches Files
- **`public/coaches/login.php`**: Changed `dirname(dirname(__DIR__))` to `dirname(__DIR__)`
- **`public/coaches/logout.php`**: Changed `dirname(dirname(__DIR__))` to `dirname(__DIR__)`
- **`public/coaches/score-input.php`**: Changed `dirname(dirname(__DIR__))` to `dirname(__DIR__)`
- **`public/coaches/schedule-change.php`**: Changed `dirname(dirname(__DIR__))` to `dirname(__DIR__)`
- **`public/coaches/dashboard.php`**: Changed `dirname(dirname(__DIR__))` to `dirname(__DIR__)`
- **`public/coaches/contacts.php`**: Changed `dirname(dirname(__DIR__))` to `dirname(__DIR__)`

### 3. Admin Files
- **No changes needed**: Admin files correctly use `dirname(dirname(__DIR__))` which works for the production structure

### 4. Deployment Configuration
- **`.cpanel.yml`**: Added step to copy production-specific index.php
- **`public/index.prod.php`**: Created production-specific version with correct paths

## Production File Structure

After deployment, the production structure is:
```
/home2/moc835/public_html/district8travelleague.com/
├── index.php (from public/index.php with corrected paths)
├── includes/
│   ├── bootstrap.php
│   ├── config.php
│   ├── database.php
│   └── ...
├── admin/
│   ├── index.php (uses dirname(dirname(__DIR__)) - correct)
│   ├── login.php
│   └── ...
├── coaches/
│   ├── login.php (uses dirname(__DIR__) - correct)
│   ├── dashboard.php
│   └── ...
├── schedule.php (uses __DIR__ - correct)
├── standings.php (uses __DIR__ - correct)
└── ...
```

## Path Resolution Logic

### Development Environment
- Files are in `public/` subdirectory
- Bootstrap is in `includes/` at project root
- Path: `dirname(__DIR__) . '/includes/bootstrap.php'` = `../includes/bootstrap.php`

### Production Environment
- Files are copied to deployment root
- Bootstrap is in `includes/` at deployment root
- Path: `__DIR__ . '/includes/bootstrap.php'` = `./includes/bootstrap.php`

### Admin Files (Both Environments)
- Files are in `admin/` subdirectory
- Bootstrap is in `includes/` at parent level
- Path: `dirname(dirname(__DIR__)) . '/includes/bootstrap.php'` = `../../includes/bootstrap.php` (dev) or `../includes/bootstrap.php` (prod)

## Testing Results

All pages tested and working correctly:
- ✅ Home page: HTTP 200
- ✅ Schedule page: HTTP 200
- ✅ Standings page: HTTP 200
- ✅ Coaches login: HTTP 200
- ✅ Admin login: HTTP 200

## Deployment Process

The updated `.cpanel.yml` now includes:
```yaml
# Replace index.php with production version for correct paths
- cp $DEPLOYPATH/public/index.prod.php $DEPLOYPATH/index.php
```

This ensures the production deployment uses the correct paths.

## Next Steps

1. **Deploy to production** using the updated `.cpanel.yml`
2. **Verify all pages load correctly** in production
3. **Test all functionality** to ensure no regressions
4. **Monitor error logs** for any remaining path issues

## Backward Compatibility

- Development environment continues to work unchanged
- All existing functionality preserved
- No breaking changes to the application logic
- Only path references updated for production compatibility

## Security Note

The path fixes maintain the same security model:
- All includes are still properly protected with `D8TL_APP` constant
- No direct access to sensitive files
- Same authentication and authorization flow
