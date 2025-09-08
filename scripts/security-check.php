<?php
/**
 * Security Verification Script
 * 
 * This script checks for common security issues and verifies
 * that security measures are properly implemented.
 */

echo "=== District 8 Travel League - Security Check ===\n\n";

$errors = [];
$warnings = [];
$passed = [];

// Check 1: Configuration files exist and have placeholder values
echo "1. Checking configuration files...\n";

$configFiles = [
    'includes/config.php' => 'Development',
    'includes/config.prod.php' => 'Production',
    'includes/config.staging.php' => 'Staging'
];

foreach ($configFiles as $file => $env) {
    if (!file_exists($file)) {
        $errors[] = "Missing configuration file: $file";
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Check for placeholder values (good)
    if (strpos($content, 'REPLACE_WITH_') !== false) {
        $passed[] = "$env config uses placeholder values ✓";
    } else if ($env !== 'Development') {
        $warnings[] = "$env config may contain real credentials";
    }
    
    // Check for old hardcoded values (bad)
    if (strpos($content, 'staging_admin_2024') !== false || 
        strpos($content, 'staging_coaches_2024') !== false) {
        $errors[] = "$env config contains old hardcoded passwords";
    }
    
    // Check for base64 encoded passwords (bad)
    if (strpos($content, 'Q2VycmFjbDQ0JA==') !== false) {
        $errors[] = "$env config contains exposed SMTP password";
    }
}

// Check 2: Backup directory
echo "2. Checking backup directory...\n";

if (is_dir('backups')) {
    $backupFiles = glob('backups/*.sql');
    if (empty($backupFiles)) {
        $passed[] = "Backup directory is clean ✓";
    } else {
        $errors[] = "Found " . count($backupFiles) . " SQL backup files that should be removed";
    }
} else {
    $passed[] = "Backup directory doesn't exist (good) ✓";
}

// Check 3: .gitignore file
echo "3. Checking .gitignore file...\n";

if (file_exists('../.gitignore')) {
    $gitignore = file_get_contents('../.gitignore');
    
    $requiredPatterns = [
        'mvp-app/backups/',
        '*.sql',
        '.env',
        'mvp-app/logs/',
        'mvp-app/uploads/'
    ];
    
    $missing = [];
    foreach ($requiredPatterns as $pattern) {
        if (strpos($gitignore, $pattern) === false) {
            $missing[] = $pattern;
        }
    }
    
    if (empty($missing)) {
        $passed[] = ".gitignore file is comprehensive ✓";
    } else {
        $warnings[] = ".gitignore missing patterns: " . implode(', ', $missing);
    }
} else {
    $errors[] = ".gitignore file is missing";
}

// Check 4: File permissions (if on Unix-like system)
echo "4. Checking file permissions...\n";

if (function_exists('fileperms')) {
    foreach ($configFiles as $file => $env) {
        if (file_exists($file)) {
            $perms = fileperms($file);
            $octal = substr(sprintf('%o', $perms), -4);
            
            if ($octal === '0600' || $octal === '0644') {
                $passed[] = "$file has secure permissions ($octal) ✓";
            } else {
                $warnings[] = "$file permissions ($octal) could be more secure (recommend 600)";
            }
        }
    }
} else {
    $warnings[] = "Cannot check file permissions on this system";
}

// Check 5: PHP syntax
echo "5. Checking PHP syntax...\n";

foreach ($configFiles as $file => $env) {
    if (file_exists($file)) {
        $output = [];
        $return_var = 0;
        exec("php -l $file 2>&1", $output, $return_var);
        
        if ($return_var === 0) {
            $passed[] = "$file syntax is valid ✓";
        } else {
            $errors[] = "$file has syntax errors: " . implode(' ', $output);
        }
    }
}

// Check 6: Security headers in .htaccess
echo "6. Checking security headers...\n";

if (file_exists('.htaccess')) {
    $htaccess = file_get_contents('.htaccess');
    
    $securityHeaders = [
        'X-Content-Type-Options',
        'X-Frame-Options',
        'X-XSS-Protection'
    ];
    
    $foundHeaders = [];
    foreach ($securityHeaders as $header) {
        if (strpos($htaccess, $header) !== false) {
            $foundHeaders[] = $header;
        }
    }
    
    if (!empty($foundHeaders)) {
        $passed[] = "Security headers found: " . implode(', ', $foundHeaders) . " ✓";
    } else {
        $warnings[] = "No security headers found in .htaccess";
    }
} else {
    $warnings[] = ".htaccess file not found";
}

// Display results
echo "\n=== SECURITY CHECK RESULTS ===\n\n";

if (!empty($passed)) {
    echo "✅ PASSED CHECKS:\n";
    foreach ($passed as $check) {
        echo "   $check\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️  WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "   $warning\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "❌ CRITICAL ERRORS:\n";
    foreach ($errors as $error) {
        echo "   $error\n";
    }
    echo "\n";
    echo "🚨 CRITICAL ERRORS MUST BE FIXED BEFORE DEPLOYMENT!\n\n";
    exit(1);
} else {
    echo "🎉 No critical security errors found!\n\n";
    
    if (!empty($warnings)) {
        echo "⚠️  Please review warnings before deployment.\n\n";
        exit(2);
    } else {
        echo "✅ All security checks passed!\n\n";
        exit(0);
    }
}
