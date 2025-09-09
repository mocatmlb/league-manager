<?php
// Simple bootstrap diagnostic
echo "<h1>Bootstrap Path Test</h1>";
echo "<p>Current directory: " . __DIR__ . "</p>";

// Test bootstrap paths
$paths = [
    'admin_bootstrap (prod)' => __DIR__ . '/includes/admin_bootstrap.php',
    'admin_bootstrap (dev)' => __DIR__ . '/../includes/admin_bootstrap.php', 
    'coach_bootstrap (prod)' => __DIR__ . '/includes/coach_bootstrap.php',
    'coach_bootstrap (dev)' => __DIR__ . '/../includes/coach_bootstrap.php'
];

foreach ($paths as $name => $path) {
    echo "<p>$name: $path - " . (file_exists($path) ? 'EXISTS' : 'NOT FOUND') . "</p>";
}

// Test the actual path resolution logic
echo "<h2>Path Resolution Test:</h2>";
$adminBootstrapPath = file_exists(__DIR__ . '/includes/admin_bootstrap.php') 
    ? __DIR__ . '/includes/admin_bootstrap.php'
    : __DIR__ . '/../includes/admin_bootstrap.php';
echo "<p>Selected admin bootstrap path: $adminBootstrapPath</p>";
echo "<p>Path exists: " . (file_exists($adminBootstrapPath) ? 'YES' : 'NO') . "</p>";

if (file_exists($adminBootstrapPath)) {
    echo "<h3>Testing Bootstrap Load:</h3>";
    try {
        require_once $adminBootstrapPath;
        echo "<p>Bootstrap loaded successfully!</p>";
    } catch (Exception $e) {
        echo "<p>Bootstrap load failed: " . $e->getMessage() . "</p>";
    }
}
?>
