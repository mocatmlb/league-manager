<?php
/**
 * Test Database Configuration — District 8 Travel League
 *
 * Overrides production DB constants with test-database values.
 * NEVER points to the production database.
 *
 * Load order in test runner:
 *   1. This file (defines DB_* constants from env vars or test defaults)
 *   2. includes/database.php (uses the already-defined constants)
 *
 * Set environment variables to customise without touching this file:
 *   TEST_DB_HOST  TEST_DB_NAME  TEST_DB_USER  TEST_DB_PASS
 */

if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

// Safety guard — refuse to load if production config already defined DB_NAME
// (prevents accidentally pointing at prod when this file is loaded late)
if (defined('DB_NAME') && DB_NAME !== 'd8tl_test') {
    die("config.test.php loaded after production config. Aborting to protect production database.\n");
}

define('DB_HOST',    getenv('TEST_DB_HOST') ?: 'localhost');
define('DB_NAME',    getenv('TEST_DB_NAME') ?: 'd8tl_test');
define('DB_USER',    getenv('TEST_DB_USER') ?: 'root');
define('DB_PASS',    getenv('TEST_DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Application constants required by included classes
if (!defined('APP_NAME'))    define('APP_NAME',    'D8TL Test');
if (!defined('APP_VERSION')) define('APP_VERSION', 'test');
if (!defined('APP_ENV'))     define('APP_ENV',     'test');

// Reduce noise during test runs
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('America/New_York');
