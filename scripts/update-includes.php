<?php
/**
 * Update Include Paths Script
 * 
 * Updates all PHP files to use the new environment-aware path system
 */

echo "=== District 8 Travel League - Update Include Paths ===\n\n";

$updates = [
    // Admin files
    'public/admin/' => [
        'search' => "require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';",
        'replace' => "require_once __DIR__ . '/../../includes/admin_bootstrap.php';",
        'files' => []
    ],
    // Coach files
    'public/coaches/' => [
        'search' => "require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';",
        'replace' => "require_once __DIR__ . '/../../includes/coach_bootstrap.php';",
        'files' => []
    ],
    // Public files
    'public/' => [
        'search' => "require_once dirname(__DIR__) . '/includes/bootstrap.php';",
        'replace' => "// Load environment loader
require_once __DIR__ . '/../includes/env-loader.php';

// Define application constant
define('D8TL_APP', true);

// Load bootstrap using environment-aware path
require_once EnvLoader::getPath('includes/bootstrap.php');",
        'files' => []
    ]
];

// Find all PHP files
function findPhpFiles($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

// Get base directory
$baseDir = dirname(__DIR__);

// Find files for each section
foreach ($updates as $dir => &$config) {
    $fullDir = $baseDir . '/' . $dir;
    if (is_dir($fullDir)) {
        $config['files'] = findPhpFiles($fullDir);
    }
}

$totalFiles = 0;
$updatedFiles = 0;
$errorFiles = 0;

// Process each section
foreach ($updates as $dir => $config) {
    echo "\nProcessing $dir...\n";
    
    foreach ($config['files'] as $file) {
        $totalFiles++;
        $relativePath = str_replace($baseDir . '/', '', $file);
        echo "  Processing: $relativePath... ";
        
        try {
            $content = file_get_contents($file);
            $originalContent = $content;
            
            // Skip files that don't need updating
            if (strpos($content, $config['search']) === false) {
                echo "SKIPPED (no match)\n";
                continue;
            }
            
            // Update the content
            $content = str_replace($config['search'], $config['replace'], $content);
            
            // Remove any duplicate D8TL_APP definitions
            $content = preg_replace('/define\(\'D8TL_APP\',\s*true\);(\s*define\(\'D8TL_APP\',\s*true\);)+/s', "define('D8TL_APP', true);", $content);
            
            if ($content === $originalContent) {
                echo "SKIPPED (no changes)\n";
                continue;
            }
            
            // Write the updated content
            if (file_put_contents($file, $content)) {
                echo "UPDATED\n";
                $updatedFiles++;
            } else {
                echo "ERROR (write failed)\n";
                $errorFiles++;
            }
            
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            $errorFiles++;
        }
    }
}

echo "\n=== Summary ===\n";
echo "Total files processed: $totalFiles\n";
echo "Files updated: $updatedFiles\n";
echo "Errors: $errorFiles\n";

if ($errorFiles === 0) {
    echo "\n✅ All files processed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Test the application to verify all pages load correctly\n";
    echo "2. Check logs for any include path errors\n";
    echo "3. Remove the old fix_production_paths.php script\n";
} else {
    echo "\n❌ Some files had errors. Please check the output above.\n";
}
?>
