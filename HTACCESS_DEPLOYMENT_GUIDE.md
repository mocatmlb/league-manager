# .htaccess Environment Variables Deployment Guide

## Overview

This approach stores sensitive configuration (database credentials, passwords, etc.) in the `.htaccess` file using `SetEnv` directives. This is secure because:

✅ `.htaccess` files are not web-accessible by default  
✅ Environment variables are loaded directly by Apache/PHP  
✅ No credentials are stored in PHP code  
✅ The repository only contains placeholder values  

## Deployment Strategy

### What's Safe to Commit

✅ **Development .htaccess** - Contains placeholder values only  
✅ **Production .htaccess template** - Contains placeholder values for reference  
✅ **PHP configuration files** - Load from environment variables  
✅ **EnvLoader class** - Reads environment variables safely  

### What Should NOT Be Committed

❌ **Production .htaccess with real credentials** - Keep this only on the server  
❌ **Any file with actual passwords or API keys**  

## Setup Instructions

### Step 1: Deploy Application Code

Deploy your application code normally via Git. The `.htaccess` file in the repository contains placeholder values, which is safe.

### Step 2: Configure Production Environment Variables

On your production server, edit the `.htaccess` file to replace placeholder values:

**Location**: `/home2/moc835/public_html/district8travelleague.com/.htaccess`

Replace these lines with your actual credentials:

```apache
# Database Configuration
SetEnv DB_HOST localhost
SetEnv DB_NAME moc835_d8tl_prod
SetEnv DB_USER your_actual_database_username
SetEnv DB_PASS your_actual_database_password
SetEnv DB_CHARSET utf8mb4

# Email Configuration
SetEnv SMTP_USERNAME your_email@district8travelleague.com
SetEnv SMTP_PASSWORD your_actual_email_password

# Security - Use Strong Passwords!
SetEnv DEFAULT_ADMIN_PASSWORD your_strong_admin_password_min_12_chars
SetEnv DEFAULT_COACHES_PASSWORD your_strong_coaches_password_min_12_chars
```

### Step 3: Test the Configuration

1. **Visit your site**: `http://district8travelleague.com/`
2. **Check for database connection errors**
3. **Test admin login**: `http://district8travelleague.com/admin/login.php`
4. **Test coaches login**: `http://district8travelleague.com/coaches/login.php`

### Step 4: Verify Environment Variables Are Loading

Create a temporary test file to verify environment variables:

```php
<?php
// TEMPORARY TEST FILE - DELETE AFTER VERIFICATION
echo "DB_USER: " . $_SERVER['DB_USER'] . "\n";
echo "DB_HOST: " . $_SERVER['DB_HOST'] . "\n";
echo "SMTP_HOST: " . $_SERVER['SMTP_HOST'] . "\n";
// DO NOT DISPLAY PASSWORDS IN PRODUCTION!
?>
```

**Important**: Delete this test file immediately after verification!

## How It Works

### 1. Apache SetEnv Directive

The `.htaccess` file uses `SetEnv` to create environment variables:

```apache
SetEnv DB_USER your_username
SetEnv DB_PASS your_password
```

### 2. PHP Environment Access

PHP can access these via `$_SERVER` superglobal:

```php
$username = $_SERVER['DB_USER'];
$password = $_SERVER['DB_PASS'];
```

### 3. EnvLoader Class

The `EnvLoader` class provides a clean interface:

```php
$username = EnvLoader::get('DB_USER', 'default_value');
$port = EnvLoader::getInt('SMTP_PORT', 587);
```

## Security Benefits

### ✅ Advantages

1. **Not Web Accessible** - `.htaccess` files are protected by Apache
2. **Server-Side Only** - Environment variables exist only in server memory
3. **No Code Exposure** - Credentials never appear in PHP source code
4. **Git Safe** - Repository only contains placeholder values
5. **Easy Management** - All configuration in one file

### ⚠️ Important Security Notes

1. **File Permissions** - Ensure `.htaccess` has proper permissions (644)
2. **Backup Security** - Don't include `.htaccess` in public backups
3. **Access Control** - Limit who can edit the production `.htaccess`
4. **Strong Passwords** - Use complex passwords for all accounts

## Deployment Process

### Initial Deployment

1. **Push code to repository** (with placeholder values)
2. **Deploy via cPanel Git** 
3. **Edit production `.htaccess`** with actual credentials
4. **Test application functionality**

### Subsequent Deployments

1. **Push code changes to repository**
2. **Deploy via cPanel Git**
3. **Your `.htaccess` credentials are preserved** (Git won't overwrite them)
4. **Test that everything still works**

## Troubleshooting

### Issue: Environment Variables Not Loading

**Symptoms**: Database connection errors, placeholder values appearing

**Solutions**:
1. Check `.htaccess` file exists and has correct syntax
2. Verify Apache `mod_env` is enabled
3. Check file permissions on `.htaccess` (should be 644)
4. Test with a simple `phpinfo()` page to see if variables appear

### Issue: Apache Configuration Error

**Symptoms**: 500 Internal Server Error

**Solutions**:
1. Check Apache error logs
2. Verify `.htaccess` syntax is correct
3. Ensure `SetEnv` is allowed in `.htaccess` files
4. Check for typos in variable names

### Issue: Variables Not Accessible in PHP

**Symptoms**: `$_SERVER['DB_USER']` returns empty

**Solutions**:
1. Try `$_ENV['DB_USER']` or `getenv('DB_USER')`
2. Check if variables are being set correctly
3. Verify Apache configuration allows environment variables

## File Structure After Deployment

```
/home2/moc835/public_html/district8travelleague.com/
├── .htaccess (CONTAINS YOUR REAL CREDENTIALS - SECURE)
├── .htaccess.production (template only - safe to commit)
├── includes/
│   ├── config.prod.php (loads from environment variables)
│   └── env-loader.php (environment variable helper)
└── ... (rest of application)
```

## Emergency Rollback

If something goes wrong:

1. **Restore previous `.htaccess`** from backup
2. **Or temporarily use direct credentials** in `config.prod.php`:
   ```php
   define('DB_USER', 'your_actual_username');
   define('DB_PASS', 'your_actual_password');
   ```
3. **Check error logs** for specific issues

## Best Practices

1. **Always backup** your production `.htaccess` before changes
2. **Use strong passwords** for all credentials
3. **Test thoroughly** after deployment
4. **Monitor logs** for any configuration issues
5. **Keep credentials secure** - never share or commit them

This approach provides excellent security while maintaining deployment simplicity!
