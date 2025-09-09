<?php
/**
 * District 8 Travel League - PHP Backwards Compatibility Functions
 * 
 * Provides backwards compatibility for PHP functions that may not be available
 * in older PHP versions (7.4+)
 */

// Prevent direct access
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

/**
 * Polyfill for str_contains() function (PHP 8.0+)
 */
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle !== '' && strpos($haystack, $needle) !== false;
    }
}

/**
 * Polyfill for str_starts_with() function (PHP 8.0+)
 */
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

/**
 * Polyfill for str_ends_with() function (PHP 8.0+)
 */
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle !== '' && substr($haystack, -strlen($needle)) === $needle;
    }
}

/**
 * Polyfill for array_key_first() function (PHP 7.3+)
 */
if (!function_exists('array_key_first')) {
    function array_key_first(array $array) {
        foreach ($array as $key => $unused) {
            return $key;
        }
        return null;
    }
}

/**
 * Polyfill for array_key_last() function (PHP 7.3+)
 */
if (!function_exists('array_key_last')) {
    function array_key_last(array $array) {
        if (!empty($array)) {
            return key(array_slice($array, -1, 1, true));
        }
        return null;
    }
}

/**
 * Polyfill for array_is_list() function (PHP 8.1+)
 */
if (!function_exists('array_is_list')) {
    function array_is_list(array $array): bool {
        if (empty($array)) {
            return true;
        }
        
        $keys = array_keys($array);
        return $keys === range(0, count($keys) - 1);
    }
}

/**
 * Backwards compatible match() expression replacement
 * Usage: match_expression($value, $cases, $default)
 * where $cases is an array of 'value' => 'result' pairs
 */
function match_expression($value, array $cases, $default = null) {
    if (array_key_exists($value, $cases)) {
        return $cases[$value];
    }
    return $default;
}

/**
 * Check if current PHP version supports a specific feature
 */
function php_version_supports($feature) {
    switch ($feature) {
        case 'match':
            return version_compare(PHP_VERSION, '8.0.0', '>=');
        case 'union_types':
            return version_compare(PHP_VERSION, '8.0.0', '>=');
        case 'named_arguments':
            return version_compare(PHP_VERSION, '8.0.0', '>=');
        case 'attributes':
            return version_compare(PHP_VERSION, '8.0.0', '>=');
        case 'enums':
            return version_compare(PHP_VERSION, '8.1.0', '>=');
        case 'readonly_properties':
            return version_compare(PHP_VERSION, '8.1.0', '>=');
        case 'fibers':
            return version_compare(PHP_VERSION, '8.1.0', '>=');
        default:
            return false;
    }
}

/**
 * Get PHP version info for debugging
 */
function get_php_compatibility_info() {
    return [
        'version' => PHP_VERSION,
        'version_id' => PHP_VERSION_ID,
        'major' => PHP_MAJOR_VERSION,
        'minor' => PHP_MINOR_VERSION,
        'release' => PHP_RELEASE_VERSION,
        'supports_match' => php_version_supports('match'),
        'supports_union_types' => php_version_supports('union_types'),
        'supports_named_arguments' => php_version_supports('named_arguments'),
        'supports_attributes' => php_version_supports('attributes'),
        'supports_enums' => php_version_supports('enums'),
        'supports_readonly' => php_version_supports('readonly_properties'),
        'functions' => [
            'str_contains' => function_exists('str_contains'),
            'str_starts_with' => function_exists('str_starts_with'),
            'str_ends_with' => function_exists('str_ends_with'),
            'array_key_first' => function_exists('array_key_first'),
            'array_key_last' => function_exists('array_key_last'),
            'array_is_list' => function_exists('array_is_list'),
            'random_bytes' => function_exists('random_bytes'),
            'password_hash' => function_exists('password_hash'),
            'password_verify' => function_exists('password_verify')
        ]
    ];
}
