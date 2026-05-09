<?php
/**
 * Unit Tests: ProfileService
 *
 * Story 7.1 — ProfileService Backend
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

class PSMockStatement {
    public function rowCount(): int { return 1; }
}

class PSMockDatabase extends Database {
    public array $queryCalls = [];
    public array $activityEvents = [];
    public ?array $userRow = null;

    public function __construct() {
        // Bypass real PDO connection
    }

    public function fetchOne($sql, $params = []) {
        if (stripos($sql, 'SELECT password_hash FROM users') !== false) {
            return $this->userRow;
        }
        return false;
    }

    public function query($sql, $params = []) {
        $this->queryCalls[] = ['sql' => $sql, 'params' => $params];

        if (stripos($sql, 'INSERT INTO activity_log') !== false) {
            $this->activityEvents[] = [
                'event'   => $params['event'],
                'context' => json_decode($params['context'], true),
            ];
        }

        return new PSMockStatement();
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

// --- updateName ---

register_test('updateName stores first, last, preferred in correct UPDATE SQL', function () {
    $db = new PSMockDatabase();
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $svc->updateName(1, ['first_name' => 'John', 'last_name' => 'Doe', 'preferred_name' => 'JD']);

    $updateCalls = array_filter($db->queryCalls, fn($c) => stripos($c['sql'], 'UPDATE users SET') !== false);
    assert_true(count($updateCalls) > 0, 'Expected UPDATE users query');

    $call = array_values($updateCalls)[0];
    assert_equals($call['params']['first_name'], 'John', 'first_name param');
    assert_equals($call['params']['last_name'], 'Doe', 'last_name param');
    assert_equals($call['params']['preferred_name'], 'JD', 'preferred_name param');
    assert_equals($call['params']['user_id'], 1, 'user_id param');
});

register_test('updateName logs profile.name_updated with fields_updated array (not values)', function () {
    $db = new PSMockDatabase();
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $svc->updateName(5, ['first_name' => 'Jane', 'last_name' => 'Smith', 'preferred_name' => '']);

    $events = array_column($db->activityEvents, 'event');
    assert_true(in_array('profile.name_updated', $events, true), 'Expected profile.name_updated event');

    $evt = $db->activityEvents[array_search('profile.name_updated', $events)];
    assert_equals($evt['context']['user_id'], 5, 'event user_id');
    assert_equals($evt['context']['fields_updated'], ['first_name', 'last_name', 'preferred_name'], 'fields_updated should list field names only');
    assert_true(!isset($evt['context']['first_name']), 'Should not log actual name values');
});

// --- updatePhone ---

register_test('updatePhone inserts new row (INSERT ... ON DUPLICATE KEY UPDATE) for primary', function () {
    $db = new PSMockDatabase();
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $svc->updatePhone(1, '555-1234', 'Cell', 'primary');

    $insertCalls = array_filter($db->queryCalls, fn($c) => stripos($c['sql'], 'INSERT INTO user_phones') !== false);
    assert_true(count($insertCalls) > 0, 'Expected INSERT INTO user_phones query');

    $call = array_values($insertCalls)[0];
    assert_equals($call['params']['phone'], '555-1234', 'phone param');
    assert_equals($call['params']['type'], 'Cell', 'type param');
    assert_equals($call['params']['role'], 'primary', 'role param');
});

register_test('updatePhone updates existing row for secondary role', function () {
    $db = new PSMockDatabase();
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $svc->updatePhone(2, '555-5678', 'Home', 'secondary');

    $insertCalls = array_filter($db->queryCalls, fn($c) => stripos($c['sql'], 'INSERT INTO user_phones') !== false);
    assert_true(count($insertCalls) > 0, 'Expected INSERT INTO user_phones query');

    $call = array_values($insertCalls)[0];
    assert_equals($call['params']['role'], 'secondary', 'role param should be secondary');
    assert_true(stripos($call['sql'], 'ON DUPLICATE KEY UPDATE') !== false, 'Should use ON DUPLICATE KEY UPDATE');
});

register_test('updatePhone throws InvalidArgumentException for invalid type', function () {
    $db = new PSMockDatabase();
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $thrown = false;
    try {
        $svc->updatePhone(1, '555-1234', 'Fax', 'primary');
    } catch (InvalidArgumentException $e) {
        $thrown = true;
    }
    assert_true($thrown, 'Expected InvalidArgumentException for invalid phone type');
});

// --- removeSecondaryPhone ---

register_test('removeSecondaryPhone issues DELETE WHERE role = secondary', function () {
    $db = new PSMockDatabase();
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $svc->removeSecondaryPhone(3);

    $deleteCalls = array_filter($db->queryCalls, fn($c) => stripos($c['sql'], 'DELETE FROM user_phones') !== false);
    assert_true(count($deleteCalls) > 0, 'Expected DELETE FROM user_phones query');

    $call = array_values($deleteCalls)[0];
    assert_equals($call['params']['user_id'], 3, 'user_id param');
    assert_equals($call['params']['role'], 'secondary', 'role param should be secondary');
});

register_test('removeSecondaryPhone logs profile.phone_removed', function () {
    $db = new PSMockDatabase();
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $svc->removeSecondaryPhone(4);

    $events = array_column($db->activityEvents, 'event');
    assert_true(in_array('profile.phone_removed', $events, true), 'Expected profile.phone_removed event');

    $evt = $db->activityEvents[array_search('profile.phone_removed', $events)];
    assert_equals($evt['context']['role'], 'secondary', 'role in context should be secondary');
});

// --- changePassword ---

register_test('changePassword with correct current password and valid new password calls UPDATE users', function () {
    $db = new PSMockDatabase();
    $db->userRow = ['password_hash' => password_hash('OldPass1!', PASSWORD_BCRYPT)];
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $svc->changePassword(1, 'OldPass1!', 'NewPass2@');

    $updateCalls = array_filter($db->queryCalls, fn($c) =>
        stripos($c['sql'], 'UPDATE users SET password_hash') !== false
    );
    assert_true(count($updateCalls) > 0, 'Expected UPDATE users SET password_hash query');
});

register_test('changePassword with correct current password logs profile.password_changed', function () {
    $db = new PSMockDatabase();
    $db->userRow = ['password_hash' => password_hash('OldPass1!', PASSWORD_BCRYPT)];
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $svc->changePassword(7, 'OldPass1!', 'NewPass2@');

    $events = array_column($db->activityEvents, 'event');
    assert_true(in_array('profile.password_changed', $events, true), 'Expected profile.password_changed event');

    $evt = $db->activityEvents[array_search('profile.password_changed', $events)];
    assert_equals($evt['context']['user_id'], 7, 'event user_id');
    assert_true(!isset($evt['context']['password']), 'Should never log password value');
});

register_test('changePassword with correct current password updates password_changed_at', function () {
    $db = new PSMockDatabase();
    $db->userRow = ['password_hash' => password_hash('OldPass1!', PASSWORD_BCRYPT)];
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $svc->changePassword(1, 'OldPass1!', 'NewPass2@');

    $updateCalls = array_filter($db->queryCalls, fn($c) =>
        stripos($c['sql'], 'UPDATE users SET password_hash') !== false
    );
    $call = array_values($updateCalls)[0];
    assert_true(stripos($call['sql'], 'password_changed_at') !== false, 'UPDATE should include password_changed_at');
});

register_test('changePassword throws IncorrectCurrentPasswordException for wrong current password', function () {
    $db = new PSMockDatabase();
    $db->userRow = ['password_hash' => password_hash('RealPass1!', PASSWORD_BCRYPT)];
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $thrown = false;
    try {
        $svc->changePassword(1, 'WrongPass1!', 'NewPass2@');
    } catch (IncorrectCurrentPasswordException $e) {
        $thrown = true;
    }
    assert_true($thrown, 'Expected IncorrectCurrentPasswordException');
});

register_test('changePassword throws WeakPasswordException for too short password', function () {
    $db = new PSMockDatabase();
    $db->userRow = ['password_hash' => password_hash('OldPass1!', PASSWORD_BCRYPT)];
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $thrown = false;
    try {
        $svc->changePassword(1, 'OldPass1!', 'Sh1!');
    } catch (WeakPasswordException $e) {
        $thrown = true;
    }
    assert_true($thrown, 'Expected WeakPasswordException for too short password');
});

register_test('changePassword throws WeakPasswordException for no uppercase', function () {
    $db = new PSMockDatabase();
    $db->userRow = ['password_hash' => password_hash('OldPass1!', PASSWORD_BCRYPT)];
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $thrown = false;
    try {
        $svc->changePassword(1, 'OldPass1!', 'lowercase1!');
    } catch (WeakPasswordException $e) {
        $thrown = true;
    }
    assert_true($thrown, 'Expected WeakPasswordException for no uppercase');
});

register_test('changePassword throws WeakPasswordException for no number', function () {
    $db = new PSMockDatabase();
    $db->userRow = ['password_hash' => password_hash('OldPass1!', PASSWORD_BCRYPT)];
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $thrown = false;
    try {
        $svc->changePassword(1, 'OldPass1!', 'NoNumbers!');
    } catch (WeakPasswordException $e) {
        $thrown = true;
    }
    assert_true($thrown, 'Expected WeakPasswordException for no number');
});

register_test('changePassword throws WeakPasswordException for no special char', function () {
    $db = new PSMockDatabase();
    $db->userRow = ['password_hash' => password_hash('OldPass1!', PASSWORD_BCRYPT)];
    Database::setInstance($db);
    $svc = new ProfileService($db);

    $thrown = false;
    try {
        $svc->changePassword(1, 'OldPass1!', 'NoSpecial1A');
    } catch (WeakPasswordException $e) {
        $thrown = true;
    }
    assert_true($thrown, 'Expected WeakPasswordException for no special character');
});
