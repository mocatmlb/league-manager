<?php
// Minimal assertion library and test registration

$GLOBALS['__tests'] = [];

function test($name, $fn) {
    $GLOBALS['__tests'][$name] = $fn;
}

function assertTrue($cond, $msg = 'Expected condition to be true') {
    if (!$cond) throw new Exception($msg);
}

function assertFalse($cond, $msg = 'Expected condition to be false') {
    if ($cond) throw new Exception($msg);
}

function assertEquals($expected, $actual, $msg = null) {
    if ($expected != $actual) {
        $display = $msg ?: 'Values are not equal';
        throw new Exception($display . "\nExpected: " . var_export($expected, true) . "\nActual:   " . var_export($actual, true));
    }
}

function assertNotEmpty($value, $msg = 'Expected value to be not empty') {
    if (empty($value)) throw new Exception($msg);
}

function resetSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
    $_SESSION = [];
}
