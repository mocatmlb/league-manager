<?php
/**
 * Unit Test Runner — District 8 Travel League
 *
 * Discovers and runs all *Test.php files under tests/unit/.
 * Uses a dedicated test database so production data is never touched.
 *
 * Usage (from project root):
 *   php tests/unit/run-unit-tests.php
 *
 * Options:
 *   --file=MigrationRunnerTest.php   Run a single test file
 *   --db=mydb_test                   Override test DB name
 *   --help                           Show this help
 *
 * Environment variables (take priority over defaults):
 *   TEST_DB_HOST    (default: localhost)
 *   TEST_DB_NAME    (default: d8tl_test)
 *   TEST_DB_USER    (default: root)
 *   TEST_DB_PASS    (default: '')
 */

// Bootstrap — must come before any class loading
define('D8TL_APP', true);

// Parse CLI options
$opts = getopt('', ['file:', 'db:', 'help']);

if (isset($opts['help'])) {
    echo <<<HELP
Usage: php tests/unit/run-unit-tests.php [--file=TestFile.php] [--db=db_name]

Options:
  --file    Run a single test file (filename only, not full path)
  --db      Override test database name
  --help    Show this message

Environment variables:
  TEST_DB_HOST / TEST_DB_NAME / TEST_DB_USER / TEST_DB_PASS

HELP;
    exit(0);
}

// Load test database config (overrides production config constants)
require_once __DIR__ . '/../../includes/config.test.php';

// Override DB_NAME from CLI if requested
if (isset($opts['db'])) {
    // Constants can't be redefined — rely on config.test.php honoring TEST_DB_NAME env var
    putenv('TEST_DB_NAME=' . $opts['db']);
}

// Load application database class
require_once __DIR__ . '/../../includes/database.php';

// Load test framework
require_once __DIR__ . '/test-helpers.php';

// Discover test files
$testDir = __DIR__;

if (isset($opts['file'])) {
    $targetFile = $testDir . '/' . basename($opts['file']);
    if (!file_exists($targetFile)) {
        fwrite(STDERR, "ERROR: Test file not found: {$targetFile}\n");
        exit(2);
    }
    $testFiles = [$targetFile];
} else {
    $testFiles = glob($testDir . '/*Test.php');
    sort($testFiles);
}

if (empty($testFiles)) {
    echo "No test files found in {$testDir}/\n";
    exit(0);
}

echo "\nD8TL Unit Test Runner\n";
echo "DB: " . DB_HOST . " / " . DB_NAME . "\n";
echo "Files: " . count($testFiles) . "\n";

foreach ($testFiles as $file) {
    echo "\n=== " . basename($file) . " ===\n";
    require_once $file;
}

$exitCode = run_all_tests();
exit($exitCode);
