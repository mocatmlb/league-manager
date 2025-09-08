<?php
/**
 * Fix Production Paths Script - CORRECTED VERSION
 * 
 * This script fixes the include paths in production files to work with the
 * cPanel deployment structure where public/ files are copied to the root.
 */

echo "=== District 8 Travel League - Production Path Fix ===\n\n";

$filesToFix = [
    // Main public files - change dirname(__DIR__) to __DIR__
    'index.php' => [
        [
            'from' => "dirname(__DIR__) . '/includes/bootstrap.php'",
            'to' => "__DIR__ . '/includes/bootstrap.php'"
        ],
        [
            'from' => "dirname(__DIR__) . '/includes/nav.php'",
            'to' => "__DIR__ . '/includes/nav.php'"
        ]
    ],
    'schedule.php' => [
        [
            'from' => "dirname(__DIR__) . '/includes/bootstrap.php'",
            'to' => "__DIR__ . '/includes/bootstrap.php'"
        ],
        [
            'from' => "dirname(__DIR__) . '/includes/nav.php'",
            'to' => "__DIR__ . '/includes/nav.php'"
        ]
    ],
    'standings.php' => [
        [
            'from' => "dirname(__DIR__) . '/includes/bootstrap.php'",
            'to' => "__DIR__ . '/includes/bootstrap.php'"
        ]
    ],
    
    // Admin files - change dirname(dirname(__DIR__)) to dirname(__DIR__)
    'admin/index.php' => [
        [
            'from' => "dirname(dirname(__DIR__)) . '/includes/bootstrap.php'",
            'to' => "dirname(__DIR__) . '/includes/bootstrap.php'"
        ]
    ],
    'admin/login.php' => [
        [
            'from' => "dirname(dirname(__DIR__)) . '/includes/bootstrap.php'",
            'to' => "dirname(__DIR__) . '/includes/bootstrap.php'"
        ]
    ],
    'admin/logout.php' => [
        [
            'from' => "dirname(dirname(__DIR__)) . '/includes/bootstrap.php'",
            'to' => "dirname(__DIR__) . '/includes/bootstrap.php'"
        ]
    ],
    
    // Coaches files - change dirname(dirname(__DIR__)) to dirname(__DIR__)
    'coaches/login.php' => [
        [
            'from' => "dirname(dirname(__DIR__)) . '/includes/bootstrap.php'",
            'to' => "dirname(__DIR__) . '/includes/bootstrap.php'"
        ]
    ],
    'coaches/logout.php' => [
        [
            'from' => "dirname(dirname(__DIR__)) . '/includes/bootstrap.php'",
            'to' => "dirname(__DIR__) . '/includes/bootstrap.php'"
        ]
    ],
    'coaches/score-input.php' => [
        [
            'from' => "dirname(dirname(__DIR__)) . '/includes/bootstrap.php'",
            'to' => "dirname(__DIR__) . '/includes/bootstrap.php'"
        ]
    ],
    'coaches/schedule-change.php' => [
        [
            'from' => "dirname(dirname(__DIR__)) . '/includes/bootstrap.php'",
            'to' => "dirname(__DIR__) . '/includes/bootstrap.php'"
        ]
    ],
    'coaches/dashboard.php' => [
        [
            'from' => "dirname(dirname(__DIR__)) . '/includes/bootstrap.php'",
            'to' => "dirname(__DIR__) . '/includes/bootstrap.php'"
        ]
    ],
    'coaches/contacts.php' => [
        [
            'from' => "dirname(dirname(__DIR__)) . '/includes/bootstrap.php'",
            'to' => "dirname(__DIR__) . '/includes/bootstrap.php'"
        ]
    ]
];

$fixedCount = 0;
$errorCount = 0;

foreach ($filesToFix as $file => $replacements) {
    // CORRECTED: Look in the same directory, not one level up
    $filePath = __DIR__ . '/' . $file;
    
    if (!file_exists($filePath)) {
        echo "❌ File not found: $file\n";
        $errorCount++;
        continue;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    // Perform all replacements for this file
    foreach ($replacements as $replacement) {
        $content = str_replace($replacement['from'], $replacement['to'], $content);
    }
    
    if ($content !== $originalContent) {
        if (file_put_contents($filePath, $content)) {
            echo "✅ Fixed: $file\n";
            $fixedCount++;
        } else {
            echo "❌ Failed to write: $file\n";
            $errorCount++;
        }
    } else {
        echo "ℹ️  No changes needed: $file\n";
    }
}

echo "\n=== Summary ===\n";
echo "Files fixed: $fixedCount\n";
echo "Errors: $errorCount\n";

if ($errorCount === 0) {
    echo "✅ All files processed successfully!\n";
    echo "\nThe production paths have been fixed. The application should now work correctly.\n";
} else {
    echo "❌ Some files had errors. Please check the output above.\n";
}

echo "\n=== Next Steps ===\n";
echo "1. Test the application by visiting the main pages\n";
echo "2. Check error logs if there are still issues\n";
echo "3. Verify all functionality works correctly\n";
?>
