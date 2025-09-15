<?php
// Simple test runner for PHP unit-style tests without external framework

$baseDir = __DIR__;

require_once __DIR__ . '/test-helpers.php';

$testFiles = [
    __DIR__ . '/AuthTest.php',
];

$total = 0;
$failed = 0;

foreach ($testFiles as $file) {
    require $file;
}

foreach ($GLOBALS['__tests'] as $name => $fn) {
    $total++;
    try {
        $fn();
        echo "."; // passed
    } catch (Throwable $e) {
        $failed++;
        echo "F\nTest failed: {$name}\n";
        echo $e->getMessage() . "\n";
    }
}

echo "\n\nTests: {$total}, Failures: {$failed}\n";
if ($failed > 0) {
    exit(1);
}
