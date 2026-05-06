<?php
/**
 * Unit Test Framework Helpers — District 8 Travel League
 *
 * Lightweight test runner (no PHPUnit required).
 * Provides register_test() / run_all_tests() used by all unit test files.
 *
 * Usage:
 *   require_once __DIR__ . '/test-helpers.php';
 *   register_test('my test name', function () { ... });
 *   // run_all_tests() is called automatically by run-unit-tests.php
 */

$_d8tl_tests   = [];
$_d8tl_results = ['passed' => 0, 'failed' => 0, 'skipped' => 0, 'errors' => []];

/**
 * Register a named test callback.
 */
function register_test(string $name, callable $fn): void {
    global $_d8tl_tests;
    $_d8tl_tests[] = ['name' => $name, 'fn' => $fn];
}

/**
 * Execute all registered tests and print a summary.
 * Returns exit code: 0 = all pass/skip, 1 = any failure.
 */
function run_all_tests(): int {
    global $_d8tl_tests, $_d8tl_results;

    $total = count($_d8tl_tests);
    echo "\nRunning {$total} test(s)...\n";
    echo str_repeat('-', 60) . "\n";

    foreach ($_d8tl_tests as $test) {
        echo "  [{$test['name']}]\n";
        try {
            ob_start();
            ($test['fn'])();
            $output = ob_get_clean();

            if (str_contains($output, 'SKIP')) {
                echo "    ⬜ SKIPPED\n";
                $_d8tl_results['skipped']++;
            } else {
                echo "    ✅ PASSED\n";
                $_d8tl_results['passed']++;
            }
            if (trim($output) !== '') {
                echo "    " . str_replace("\n", "\n    ", trim($output)) . "\n";
            }
        } catch (Throwable $e) {
            ob_end_clean();
            echo "    ❌ FAILED: " . $e->getMessage() . "\n";
            $_d8tl_results['failed']++;
            $_d8tl_results['errors'][] = "[{$test['name']}] " . $e->getMessage();
        }
    }

    echo str_repeat('-', 60) . "\n";
    echo sprintf(
        "Results: %d passed, %d failed, %d skipped (total: %d)\n",
        $_d8tl_results['passed'],
        $_d8tl_results['failed'],
        $_d8tl_results['skipped'],
        $total
    );

    if (!empty($_d8tl_results['errors'])) {
        echo "\nFailed tests:\n";
        foreach ($_d8tl_results['errors'] as $err) {
            echo "  • {$err}\n";
        }
    }

    echo "\n";
    return $_d8tl_results['failed'] > 0 ? 1 : 0;
}

// ---------------------------------------------------------------------------
// Assertion helpers (shared across all test files)
// ---------------------------------------------------------------------------

if (!function_exists('assert_equals')) {
    function assert_equals($actual, $expected, string $message): void {
        if ($actual !== $expected) {
            throw new RuntimeException(
                "ASSERTION FAILED — $message\n  Expected: " . var_export($expected, true) .
                "\n  Got:      " . var_export($actual, true)
            );
        }
    }
}

if (!function_exists('assert_true')) {
    function assert_true(bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException("ASSERTION FAILED — $message");
        }
    }
}

if (!function_exists('assert_not_null')) {
    function assert_not_null($value, string $message): void {
        if ($value === null || $value === false) {
            throw new RuntimeException("ASSERTION FAILED — $message (got null/false)");
        }
    }
}

if (!function_exists('assert_null')) {
    function assert_null($value, string $message): void {
        if ($value !== null) {
            throw new RuntimeException("ASSERTION FAILED — $message");
        }
    }
}
