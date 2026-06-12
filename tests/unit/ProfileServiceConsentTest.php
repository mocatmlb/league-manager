<?php
/**
 * Unit Tests: ProfileService — SMS consent & ToS acceptance timestamps
 *
 * SMS Consent & ToS Compliance spec — updateContactInfo() consent audit:
 *   - opting in sets sms_consent_at
 *   - staying opted in preserves the original sms_consent_at
 *   - opting out clears sms_consent_at
 *   - terms_accepted_at is stamped only when the caller affirms acceptance
 *   - missing user row aborts the save
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ActivityLogger.php';
require_once __DIR__ . '/../../includes/RegistrationService.php';
require_once __DIR__ . '/../../includes/ProfileService.php';

// ---------------------------------------------------------------------------
// Mock infrastructure
// ---------------------------------------------------------------------------

class PSConsentMockStatement {
    public function rowCount(): int { return 1; }
}

class PSConsentMockDatabase extends Database {
    public array $queryCalls = [];
    /** Row returned for the in-transaction consent-state read; false simulates a missing user. */
    public $consentRow = ['sms_opt_in' => 0, 'sms_consent_at' => null, 'terms_accepted_at' => null];

    public function __construct() {
        // Bypass real PDO connection
    }

    public function fetchOne($sql, $params = []) {
        if (stripos($sql, 'SELECT sms_opt_in, sms_consent_at, terms_accepted_at FROM users') !== false) {
            return $this->consentRow;
        }
        // Email-uniqueness check: no duplicate.
        return false;
    }

    public function query($sql, $params = []) {
        $this->queryCalls[] = ['sql' => $sql, 'params' => $params];
        return new PSConsentMockStatement();
    }

    public function beginTransaction() { return true; }
    public function commit() { return true; }
    public function rollback() { return true; }
}

function ps_consent_update_call(PSConsentMockDatabase $db): array {
    $calls = array_filter(
        $db->queryCalls,
        fn($c) => stripos($c['sql'], 'UPDATE users SET email') !== false
    );
    assert_true(count($calls) > 0, 'Expected UPDATE users SET email ... query');
    return array_values($calls)[0];
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

register_test('updateContactInfo: opting in sets sms_consent_at to now', function () {
    $db = new PSConsentMockDatabase();
    $db->consentRow = ['sms_opt_in' => 0, 'sms_consent_at' => null, 'terms_accepted_at' => null];
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $before = date('Y-m-d H:i:s');
    $svc->updateContactInfo(1, 'coach@example.com', '5551234567', true, true);
    $after = date('Y-m-d H:i:s');

    $call = ps_consent_update_call($db);
    assert_equals($call['params']['sms_opt_in'], 1, 'sms_opt_in param');
    assert_not_null($call['params']['sms_consent_at'], 'sms_consent_at should be set on opt-in');
    assert_true(
        $call['params']['sms_consent_at'] >= $before && $call['params']['sms_consent_at'] <= $after,
        'sms_consent_at should be the current datetime'
    );
});

register_test('updateContactInfo: staying opted in preserves existing sms_consent_at', function () {
    $db = new PSConsentMockDatabase();
    $db->consentRow = ['sms_opt_in' => 1, 'sms_consent_at' => '2025-01-15 10:30:00', 'terms_accepted_at' => null];
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $svc->updateContactInfo(2, 'coach@example.com', '5551234567', true, true);

    $call = ps_consent_update_call($db);
    assert_equals($call['params']['sms_opt_in'], 1, 'sms_opt_in param');
    assert_equals($call['params']['sms_consent_at'], '2025-01-15 10:30:00', 'existing sms_consent_at must be preserved when already opted in');
});

register_test('updateContactInfo: opting out clears sms_consent_at', function () {
    $db = new PSConsentMockDatabase();
    $db->consentRow = ['sms_opt_in' => 1, 'sms_consent_at' => '2025-01-15 10:30:00', 'terms_accepted_at' => null];
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $svc->updateContactInfo(3, 'coach@example.com', '5551234567', false, true);

    $call = ps_consent_update_call($db);
    assert_equals($call['params']['sms_opt_in'], 0, 'sms_opt_in param');
    assert_null($call['params']['sms_consent_at'], 'sms_consent_at should be NULL after opt-out');
});

register_test('updateContactInfo: terms_accepted_at stamped when caller affirms acceptance', function () {
    $db = new PSConsentMockDatabase();
    $db->consentRow = ['sms_opt_in' => 0, 'sms_consent_at' => null, 'terms_accepted_at' => null];
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $before = date('Y-m-d H:i:s');
    $svc->updateContactInfo(4, 'coach@example.com', '5551234567', false, true);
    $after = date('Y-m-d H:i:s');

    $call = ps_consent_update_call($db);
    assert_not_null($call['params']['terms_accepted_at'], 'terms_accepted_at should be set');
    assert_true(
        $call['params']['terms_accepted_at'] >= $before && $call['params']['terms_accepted_at'] <= $after,
        'terms_accepted_at should be the current datetime'
    );
});

register_test('updateContactInfo: terms_accepted_at preserved when caller does not affirm', function () {
    $db = new PSConsentMockDatabase();
    $db->consentRow = ['sms_opt_in' => 0, 'sms_consent_at' => null, 'terms_accepted_at' => '2025-03-01 08:00:00'];
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $svc->updateContactInfo(5, 'coach@example.com', '5551234567', false);

    $call = ps_consent_update_call($db);
    assert_equals($call['params']['terms_accepted_at'], '2025-03-01 08:00:00', 'terms_accepted_at must not be fabricated without caller affirmation');
});

register_test('updateContactInfo: missing user row aborts the save', function () {
    $db = new PSConsentMockDatabase();
    $db->consentRow = false; // simulate deleted user
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $thrown = false;
    try {
        $svc->updateContactInfo(99, 'coach@example.com', '5551234567', true, true);
    } catch (RuntimeException $e) {
        $thrown = true;
        assert_equals($e->getMessage(), 'User account not found.', 'missing-user error message');
    }
    assert_true($thrown, 'Expected RuntimeException for missing user row');

    $updateCalls = array_filter($db->queryCalls, fn($c) => stripos($c['sql'], 'UPDATE users') !== false);
    assert_equals(count($updateCalls), 0, 'No UPDATE should run when the user row is missing');
});

register_test('updateContactInfo: invalid phone blocks save (no UPDATE issued)', function () {
    $db = new PSConsentMockDatabase();
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $thrown = false;
    try {
        $svc->updateContactInfo(6, 'coach@example.com', '12345', true, true);
    } catch (InvalidArgumentException $e) {
        $thrown = true;
        assert_equals($e->getMessage(), 'Phone number must be 10 digits.', 'phone error message');
    }
    assert_true($thrown, 'Expected InvalidArgumentException for invalid phone');

    $updateCalls = array_filter($db->queryCalls, fn($c) => stripos($c['sql'], 'UPDATE users') !== false);
    assert_equals(count($updateCalls), 0, 'No UPDATE should run when phone validation fails');
});
