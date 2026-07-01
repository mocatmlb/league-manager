<?php
/**
 * Unit Tests: UmpireAvailabilityService
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ActivityLogger.php';

if (!function_exists('getSetting')) {
    function getSetting(string $key, string $default = '') {
        return $GLOBALS['_test_settings'][$key] ?? $default;
    }
}

// Mock Database for testing
class UmpireAvailabilityMockDb extends Database {
    public array $lastSql      = [];
    public array $lastParams   = [];
    public array $queryRows    = [];
    public array $insertRows   = [];
    public array $updateRows   = [];
    public array $deleteRows   = [];
    public int $nextInsertId   = 5000;
    public array $activityLogRows = [];

    public function __construct() {}

    public function query($sql, $params = []) {
        $this->lastSql[]    = $sql;
        $this->lastParams[] = $params;
        if (str_contains($sql, 'INSERT INTO activity_log')) {
            $this->activityLogRows[] = $params;
        }
        $rows = !empty($this->queryRows) ? array_shift($this->queryRows) : [];
        return new UmpireAvailabilityMockStmt($rows);
    }

    public function fetchOne($sql, $params = []) {
        $this->lastSql[] = $sql;
        $this->lastParams[] = $params;
        $rows = !empty($this->queryRows) ? array_shift($this->queryRows) : [];
        return $rows[0] ?? false;
    }

    public function insert($table, $data) {
        $this->insertRows[] = ['table' => $table, 'data' => $data];
        return $this->nextInsertId++;
    }

    public function update($table, $data, $where, $whereParams = []) {
        $this->updateRows[] = [
            'table' => $table,
            'data' => $data,
            'where' => $where,
            'whereParams' => $whereParams,
        ];
        return new UmpireAvailabilityMockStmt([]);
    }

    public function delete($table, $where, $params = []) {
        $this->deleteRows[] = ['table' => $table, 'where' => $where, 'params' => $params];
        return true;
    }
}

class UmpireAvailabilityMockStmt {
    private array $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function fetchAll() { return $this->rows; }
    public function fetch() { return array_shift($this->rows); }
    public function rowCount() { return count($this->rows); }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

register_test('UmpireAvailabilityService: List windows for umpire', function() {
    $db = new UmpireAvailabilityMockDb();
    Database::setInstance($db);

    $mockRows = [
        ['availability_id' => 1, 'starts_at' => '2026-07-01 08:00:00', 'ends_at' => '2026-07-01 12:00:00'],
        ['availability_id' => 2, 'starts_at' => '2026-07-02 08:00:00', 'ends_at' => '2026-07-02 12:00:00'],
    ];
    $db->queryRows[] = $mockRows;
    
    $service = new UmpireAvailabilityService();
    $results = $service->listForUmpire(123);
    
    assert_equals(count($results), 2, 'Should return 2 windows');
    assert_equals($results[0]['availability_id'], 1, 'First window ID matches');
    assert_true(str_contains($db->lastSql[0], 'WHERE umpire_user_id = :umpire_user_id'), 'Query is scoped to umpire');
    assert_equals($db->lastParams[0]['umpire_user_id'], 123, 'Correct umpire ID passed');
});

register_test('UmpireAvailabilityService: Create window validation', function() {
    $db = new UmpireAvailabilityMockDb();
    Database::setInstance($db);
    
    $service = new UmpireAvailabilityService();
    
    // Valid window
    $id = $service->createWindow(123, '2026-07-01 08:00:00', '2026-07-01 12:00:00', 'Testing');
    assert_equals($id, 5000, 'Returns new insert ID');
    assert_equals($db->insertRows[0]['data']['umpire_user_id'], 123, 'Correct user ID in insert');
    assert_equals($db->insertRows[0]['data']['starts_at'], '2026-07-01 08:00:00', 'Start datetime is normalized');
    
    // Invalid: starts_at >= ends_at
    try {
        $service->createWindow(123, '2026-07-01 12:00:00', '2026-07-01 08:00:00');
        assert_true(false, 'Should throw for starts_at > ends_at');
    } catch (InvalidArgumentException $e) {
        assert_true(str_contains($e->getMessage(), 'must be before'), 'Correct error message');
    }
    
    // Invalid: blank datetimes
    try {
        $service->createWindow(123, '', '2026-07-01 12:00:00');
        assert_true(false, 'Should throw for blank starts_at');
    } catch (InvalidArgumentException $e) {
        assert_true(str_contains($e->getMessage(), 'cannot be blank'), 'Correct error message');
    }

    // Invalid: malformed datetime
    try {
        $service->createWindow(123, 'not-a-date', '2026-07-01 12:00:00');
        assert_true(false, 'Should throw for malformed starts_at');
    } catch (InvalidArgumentException $e) {
        assert_true(str_contains($e->getMessage(), 'valid date and time'), 'Correct malformed datetime message');
    }
});

register_test('Story 25.6 audit payload records actor and source for manual admin create', function() {
    $db = new UmpireAvailabilityMockDb();
    Database::setInstance($db);

    $service = new UmpireAvailabilityService();
    $id = $service->createWindow(123, '2026-07-01T08:00', '2026-07-01T12:00', 'Call confirmation', 42, 'admin_manual');

    assert_equals($id, 5000, 'Returns new insert ID');
    assert_equals($db->insertRows[0]['data']['starts_at'], '2026-07-01 08:00:00', 'Accepts datetime-local format');
    assert_equals(count($db->activityLogRows), 1, 'Expected activity log insert');
    $context = json_decode($db->activityLogRows[0]['context'], true);
    assert_equals($context['availability_id'], 5000, 'Audit context includes availability id');
    assert_equals($context['umpire_user_id'], 123, 'Audit context includes target umpire id');
    assert_equals($context['actor_user_id'], 42, 'Audit context includes acting admin id');
    assert_equals($context['source'], 'admin_manual', 'Audit context includes source');
    assert_true(!array_key_exists('notes', $context), 'Audit context must not log notes text');
});

register_test('UmpireAvailabilityService: Update window ownership', function() {
    $db = new UmpireAvailabilityMockDb();
    Database::setInstance($db);
    
    // Mock existence/ownership check
    $db->queryRows[] = [['availability_id' => 10, 'umpire_user_id' => 123]];
    
    $service = new UmpireAvailabilityService();
    $service->updateWindow(10, 123, '2026-07-01 09:00:00', '2026-07-01 13:00:00');
    
    assert_equals(count($db->updateRows), 1, 'Update called once');
    assert_equals($db->updateRows[0]['where'], 'availability_id = :id AND umpire_user_id = :umpire_user_id', 'Update is ownership-scoped');
    
    // Update for wrong user
    $db->queryRows[] = []; // Not found/owned
    try {
        $service->updateWindow(10, 999, '2026-07-01 09:00:00', '2026-07-01 13:00:00');
        assert_true(false, 'Should throw for ownership failure');
    } catch (RuntimeException $e) {
        assert_true(str_contains($e->getMessage(), 'not found or not owned'), 'Correct error message');
    }
});

register_test('Story 25.6 audit payload records actor and source for manual admin update and delete', function() {
    $db = new UmpireAvailabilityMockDb();
    Database::setInstance($db);

    $service = new UmpireAvailabilityService();
    $db->queryRows[] = [['availability_id' => 10, 'umpire_user_id' => 123]];
    $service->updateWindow(10, 123, '2026-07-01T09:00', '2026-07-01T13:00', 'Updated by phone', 42, 'admin_manual');

    $db->queryRows[] = [['availability_id' => 10, 'umpire_user_id' => 123]];
    $service->deleteWindow(10, 123, 42, 'admin_manual');

    assert_equals(count($db->activityLogRows), 2, 'Expected update and delete audit logs');
    $updateContext = json_decode($db->activityLogRows[0]['context'], true);
    $deleteContext = json_decode($db->activityLogRows[1]['context'], true);
    assert_equals($updateContext['actor_user_id'], 42, 'Update audit includes actor');
    assert_equals($updateContext['source'], 'admin_manual', 'Update audit includes source');
    assert_equals($deleteContext['actor_user_id'], 42, 'Delete audit includes actor');
    assert_equals($deleteContext['source'], 'admin_manual', 'Delete audit includes source');
    assert_true(!array_key_exists('notes', $updateContext), 'Update audit must not log notes text');
});

register_test('UmpireAvailabilityService: Delete window ownership', function() {
    $db = new UmpireAvailabilityMockDb();
    Database::setInstance($db);
    
    // Mock existence/ownership check
    $db->queryRows[] = [['availability_id' => 10, 'umpire_user_id' => 123]];
    
    $service = new UmpireAvailabilityService();
    $service->deleteWindow(10, 123);
    
    assert_equals(count($db->deleteRows), 1, 'Delete called once');
    assert_equals($db->deleteRows[0]['where'], 'availability_id = :id AND umpire_user_id = :umpire_user_id', 'Delete is ownership-scoped');
});

register_test('UmpireAvailabilityService: Pool query (int[] contract)', function() {
    $db = new UmpireAvailabilityMockDb();
    Database::setInstance($db);
    $GLOBALS['_test_settings']['conflict_window_minutes'] = '45';
    
    // Mock pool result
    $db->queryRows[] = [['umpire_user_id' => 1], ['umpire_user_id' => 3], ['umpire_user_id' => 5]];
    
    $service = new UmpireAvailabilityService();
    $ids = $service->getAvailableUmpireIdsForWindow(new DateTime('2026-07-01 10:00:00'), new DateTime('2026-07-01 12:00:00'));
    
    assert_equals($ids, [1, 3, 5], 'Returns int[] array');
    $sql = $db->lastSql[0];
    assert_true(str_contains($sql, 'umpire_availability_windows'), 'Queries availability table');
    assert_true(str_contains($sql, 'game_umpire_assignments'), 'Excludes overlapping assignments');
    assert_true(str_contains($sql, "JOIN roles r ON r.id = u.role_id AND r.name = 'umpire'"), 'Restricts pool to umpire role');
    assert_true(str_contains($sql, 'JOIN schedules s ON s.game_id = gua.game_id'), 'Uses schedules.game_id join');
    assert_true(str_contains($sql, "gua.assignment_status IN ('Draft', 'Published')"), 'Only Draft and Published assignments block pool membership');
    assert_true(!str_contains($sql, "gua.assignment_status != 'Cancelled'"), 'Declined and Open assignments should not block pool membership');
    assert_true(str_contains($sql, "TIMESTAMP(s.game_date, COALESCE(s.game_time, '00:00:00'))"), 'Builds schedule datetime from game_date and game_time');
    assert_true(str_contains($sql, 'INTERVAL 2700 SECOND'), 'Uses configured conflict-window duration');
    assert_true(!str_contains($sql, 'INTERVAL 3 HOUR'), 'Does not hardcode a three-hour overlap window');
    assert_true(!str_contains($sql, 'game_date_time'), 'Does not reference nonexistent game_date_time column');
    assert_true(!str_contains($sql, 'gua.status'), 'Does not reference nonexistent gua.status column');
    unset($GLOBALS['_test_settings']['conflict_window_minutes']);

    try {
        $service->getAvailableUmpireIdsForWindow(new DateTime('2026-07-01 12:00:00'), new DateTime('2026-07-01 12:00:00'));
        assert_true(false, 'Should throw for zero-length requested window');
    } catch (InvalidArgumentException $e) {
        assert_true(str_contains($e->getMessage(), 'must end after it starts'), 'Correct requested window message');
    }
});

register_test('Story 25.3 availability pool display query contract', function() {
    $db = new UmpireAvailabilityMockDb();
    Database::setInstance($db);
    $GLOBALS['_test_settings']['conflict_window_minutes'] = '45';

    $db->queryRows[] = [[
        'user_id' => 7,
        'first_name' => 'Ava',
        'last_name' => 'Blue',
        'email' => 'ava@example.test',
        'phone' => '555-0107',
        'umpire_level' => 'Blue Shirt',
        'is_under_18' => 1,
        'current_game_load' => 2,
    ]];

    $service = new UmpireAvailabilityService();
    $rows = $service->getAvailabilityPoolForWindow(new DateTime('2026-07-01 10:00:00'), new DateTime('2026-07-01 12:00:00'));

    assert_equals(count($rows), 1, 'Expected one display row');
    assert_equals($rows[0]['user_id'], 7, 'Display row keeps user id');
    assert_equals($rows[0]['first_name'], 'Ava', 'Display row includes first name');
    assert_equals($rows[0]['phone'], '555-0107', 'Display row includes phone');
    assert_equals($rows[0]['umpire_level'], 'Blue Shirt', 'Display row includes umpire level');
    assert_equals($rows[0]['is_under_18'], 1, 'Display row normalizes under-18 flag');
    assert_equals($rows[0]['current_game_load'], 2, 'Display row includes current D8 assignment load');

    $sql = $db->lastSql[0];
    assert_true(str_contains($sql, 'umpire_availability_windows'), 'Display query uses availability table');
    assert_true(str_contains($sql, "JOIN roles r ON r.id = u.role_id AND r.name = 'umpire'"), 'Display query restricts to umpire role');
    assert_true(str_contains($sql, "gua.assignment_status IN ('Draft', 'Published')"), 'Display query blocks Draft and Published assignments');
    assert_true(str_contains($sql, "g.game_status NOT IN ('Cancelled', 'Postponed')"), 'Display query ignores Cancelled/Postponed games');
    assert_true(str_contains($sql, "TIMESTAMP(s.game_date, COALESCE(s.game_time, '00:00:00'))"), 'Display query builds schedule datetime from live schema');
    assert_true(str_contains($sql, 'COUNT(load_gua.assignment_id) AS current_game_load'), 'Display query calculates load with one aggregate join');
    assert_true(str_contains($sql, 'INTERVAL 2700 SECOND'), 'Display query uses configured assignment window duration');
    assert_true(!str_contains($sql, 'game_date_time'), 'Display query must not reference nonexistent game_date_time column');
    assert_true(!str_contains($sql, 'gua.status'), 'Display query must not reference nonexistent gua.status column');
    assert_true(!str_contains($sql, 'notes'), 'Display query must not expose availability notes');
    assert_equals($db->lastParams[0]['requested_start'], '2026-07-01 10:00:00', 'Display query start parameter is normalized');
    assert_equals($db->lastParams[0]['requested_end'], '2026-07-01 12:00:00', 'Display query end parameter is normalized');

    // All-day normalization contract
    [$s, $e] = $service->normalizeAllDayFromDate('2026-07-01');
    assert_equals($s, '2026-07-01 00:00:00', 'All-day starts at midnight');
    assert_equals($e, '2026-07-02 00:00:00', 'All-day ends next day midnight');

    [$s, $e] = $service->normalizeAllDayFromDate('2026-07-01T10:00');
    assert_equals($s, '2026-07-01 00:00:00', 'All-day from datetime-local normalized to date midnight');

    unset($GLOBALS['_test_settings']['conflict_window_minutes']);

    try {
        $service->getAvailabilityPoolForWindow(new DateTime('2026-07-01 12:00:00'), new DateTime('2026-07-01 12:00:00'));
        assert_true(false, 'Should throw for zero-length requested display window');
    } catch (InvalidArgumentException $e) {
        assert_true(str_contains($e->getMessage(), 'must end after it starts'), 'Correct display-window validation message');
    }
});

register_test('Story 25.1 migration uses compatible FK and rerun-safe index placement', function() {
    $sql = file_get_contents(__DIR__ . '/../../database/migrations/051_create_umpire_availability_windows.sql');
    assert_true(str_contains($sql, '`umpire_user_id` INT NOT NULL'), 'FK column should match signed users.id');
    assert_true(!str_contains($sql, '`umpire_user_id` INT UNSIGNED'), 'FK column should not be unsigned');
    assert_true(str_contains($sql, 'INDEX `idx_umpire_availability_user_window`'), 'User/window index should be declared in table');
    assert_true(!str_contains($sql, 'CREATE INDEX `idx_umpire_availability_user_window`'), 'Should not use standalone CREATE INDEX after IF NOT EXISTS table');
    assert_true(str_contains($sql, "INSERT IGNORE INTO `schema_migrations` (`version`) VALUES ('051')"), 'Migration should use schema_migrations version-only insert');
    assert_true(!str_contains($sql, 'run_at'), 'Migration should not reference nonexistent run_at column');
});

register_test('Story 25.1 portal supports update action and modal controls', function() {
    $php = file_get_contents(__DIR__ . '/../../public/umpires/availability.php');
    assert_true(str_contains($php, "\$action === 'update'"), 'POST handler should support update');
    assert_true(str_contains($php, '$service->updateWindow('), 'Update action should call service updateWindow');
    assert_true(str_contains($php, 'Edit Availability Window'), 'Page should render edit modal');
    assert_true(str_contains($php, 'data-bs-dismiss="modal"'), 'Cancel buttons should dismiss modals');
    assert_true(str_contains($php, "Unsupported availability action"), 'Unsupported POST actions should not silently succeed');
});

register_test('Availability edit modals support all-day updates', function() {
    $portal = file_get_contents(__DIR__ . '/../../public/umpires/availability.php');
    assert_true(str_contains($portal, "name=\"is_all_day\""), 'Portal edit modal should expose all-day toggle');
    assert_true(str_contains($portal, 'normalizeAllDayFromDate'), 'Portal update handler should use service for all-day edits');
    assert_true(str_contains($portal, "(\$_POST['is_all_day'] ?? '0') === '1'"), 'Portal update handler should read all-day POST flag');
    assert_true(str_contains($portal, 'availability-edit-all-day-toggle'), 'Portal edit JS should bind all-day toggle');
    assert_true(str_contains($portal, 'endInput.disabled = toggle.checked'), 'Portal edit JS should disable end time for all-day edits');

    $admin = file_get_contents(__DIR__ . '/../../public/admin/umpires/availability-management.php');
    assert_true(str_contains($admin, "name=\"is_all_day\""), 'Admin edit modal should expose all-day toggle');
    assert_true(str_contains($admin, 'normalizeAllDayFromDate'), 'Admin update handler should use service for all-day edits');
    assert_true(str_contains($admin, "(\$_POST['is_all_day'] ?? '0') === '1'"), 'Admin update handler should read all-day POST flag');
    assert_true(str_contains($admin, 'availability-edit-all-day-toggle'), 'Admin edit JS should bind all-day toggle');
    assert_true(str_contains($admin, 'endInput.disabled = toggle.checked'), 'Admin edit JS should disable end time for all-day edits');
});

register_test('Story 25.7 createWindowsForDates creates all-day windows from dates', function() {
    $db = new UmpireAvailabilityMockDb();
    Database::setInstance($db);
    $db->queryRows[] = [];

    $service = new UmpireAvailabilityService();
    $result = $service->createWindowsForDates(123, ['2026-07-10'], null, null);

    assert_equals($result['created'], [5000], 'Expected created availability id');
    assert_equals($result['skipped'], [], 'Expected no skipped dates');
    assert_equals($db->insertRows[0]['data']['starts_at'], '2026-07-10 00:00:00', 'Expected all-day start at local midnight');
    assert_equals($db->insertRows[0]['data']['ends_at'], '2026-07-11 00:00:00', 'Expected all-day end at next local midnight');
});

register_test('Story 25.7 createWindowsForDates creates partial-day windows from dates and HH:MM times', function() {
    $db = new UmpireAvailabilityMockDb();
    Database::setInstance($db);
    $db->queryRows[] = [];

    $service = new UmpireAvailabilityService();
    $result = $service->createWindowsForDates(123, ['2026-07-10'], '09:00', '17:00');

    assert_equals($result['created'], [5000], 'Expected created availability id');
    assert_equals($db->insertRows[0]['data']['starts_at'], '2026-07-10 09:00:00', 'Expected partial-day start datetime');
    assert_equals($db->insertRows[0]['data']['ends_at'], '2026-07-10 17:00:00', 'Expected partial-day end datetime');
});

register_test('Story 25.6 admin batch availability records actor and source', function() {
    $db = new UmpireAvailabilityMockDb();
    Database::setInstance($db);
    $db->queryRows[] = [];

    $service = new UmpireAvailabilityService();
    $result = $service->createWindowsForDates(123, ['2026-07-10'], null, null, 'Call confirmation', 42, 'admin_manual');

    assert_equals($result['created'], [5000], 'Expected created availability id');
    assert_equals(count($db->activityLogRows), 1, 'Expected activity log insert');
    $context = json_decode($db->activityLogRows[0]['context'], true);
    assert_equals($context['umpire_user_id'], 123, 'Batch audit context includes target umpire id');
    assert_equals($context['actor_user_id'], 42, 'Batch audit context includes acting admin id');
    assert_equals($context['source'], 'admin_manual', 'Batch audit context includes admin source');
    assert_true(!array_key_exists('notes', $context), 'Batch audit context must not log notes text');
});

register_test('Story 25.7 createWindowsForDates skips duplicate all-day date without inserting', function() {
    $db = new UmpireAvailabilityMockDb();
    Database::setInstance($db);
    $db->queryRows[] = [['availability_id' => 99]];

    $service = new UmpireAvailabilityService();
    $result = $service->createWindowsForDates(123, ['2026-07-10'], null, null);

    assert_equals($result['created'], [], 'Expected no created ids');
    assert_equals($result['skipped'], ['2026-07-10'], 'Expected duplicate date to be skipped');
    assert_equals(count($db->insertRows), 0, 'Expected no insert for duplicate all-day window');
});

register_test('Story 25.7 createWindowsForDates enforces batch cap', function() {
    $db = new UmpireAvailabilityMockDb();
    Database::setInstance($db);
    $dates = [];
    for ($i = 1; $i <= 63; $i++) {
        $dates[] = sprintf('2026-07-%02d', (($i - 1) % 28) + 1);
    }

    try {
        (new UmpireAvailabilityService())->createWindowsForDates(123, $dates, null, null);
        assert_true(false, 'Expected batch cap exception');
    } catch (InvalidArgumentException $e) {
        assert_equals($e->getMessage(), 'Batch cannot exceed 62 dates.', 'Expected batch cap error message');
    }
});

register_test('Story 25.7 createWindowsForDates rejects reversed partial-day times', function() {
    $db = new UmpireAvailabilityMockDb();
    Database::setInstance($db);

    try {
        (new UmpireAvailabilityService())->createWindowsForDates(123, ['2026-07-10'], '17:00', '09:00');
        assert_true(false, 'Expected reversed time exception');
    } catch (InvalidArgumentException $e) {
        assert_true(str_contains($e->getMessage(), 'Start time must be before end time.'), 'Expected reversed time validation message');
    }
});

register_test('Story 25.7 availability page exposes calendar and batch-create contract', function() {
    $php = file_get_contents(__DIR__ . '/../../public/umpires/availability.php');
    assert_true(str_contains($php, 'availabilityCalendarEl'), 'Page should expose calendar container');
    assert_true(str_contains($php, 'availabilityAllDayToggle'), 'Page should expose all-day toggle control');
    assert_true(str_contains($php, "\$action === 'batch_create'"), 'POST handler should support batch_create action');
    assert_true(str_contains($php, 'Auth::generateCSRFToken()'), 'Page should generate CSRF token');
    assert_true(str_contains($php, "\$action === 'update'"), 'Regression: update action should remain present');
    assert_true(str_contains($php, "\$action === 'delete'"), 'Regression: delete action should remain present');
    assert_true(str_contains($php, 'fullcalendar@5.11.3/main.min.css'), 'Page should load FullCalendar v5 CSS');
    assert_true(str_contains($php, 'fullcalendar@5.11.3/main.min.js'), 'Page should load FullCalendar v5 JS');
    assert_true(str_contains($php, 'dateClick'), 'Page should use dateClick for non-contiguous selected dates');
    assert_true(str_contains($php, 'selectable: false'), 'Page should avoid range selection for multi-date toggles');
});

register_test('Story 25.6 manual availability management page and navigation contract', function() {
    $projectRoot = __DIR__ . '/../..';
    $pagePath = $projectRoot . '/public/admin/umpires/availability-management.php';
    assert_true(file_exists($pagePath), 'Manual availability management page should exist');

    $php = file_get_contents($pagePath);
    assert_true(str_contains($php, "PermissionGuard::requireRole('umpire_assignor', '/login.php')"), 'Page uses assignor role guard');
    assert_true(str_contains($php, 'Auth::getCurrentUser()'), 'Page uses current authenticated admin/assignor');
    assert_true(!str_contains($php, 'coach_user_id'), 'Admin page must not use umpire portal session key');
    assert_true(str_contains($php, "\$_GET['umpire_user_id']"), 'Page accepts target umpire via GET');
    assert_true(str_contains($php, 'getUmpire($targetUmpireId)'), 'Page validates target umpire through roster service');
    assert_true(str_contains($php, "\$targetUmpire['status'] !== 'active'"), 'Page rejects non-active umpire targets');
    assert_true(str_contains($php, 'Auth::verifyCSRFToken'), 'POST mutations verify CSRF');
    assert_true(str_contains($php, "\$action === 'batch_create'"), 'Page supports calendar batch_create mutation');
    assert_true(str_contains($php, 'createWindowsForDates('), 'Page uses service batch helper for calendar selections');
    assert_true(str_contains($php, 'createWindow('), 'Page supports create mutation');
    assert_true(str_contains($php, 'updateWindow('), 'Page supports update mutation');
    assert_true(str_contains($php, 'deleteWindow('), 'Page supports delete mutation');
    assert_true(str_contains($php, "'admin_manual'"), 'Page tags admin-originated service calls');
    assert_true(str_contains($php, 'availabilityCalendarEl'), 'Page exposes calendar container');
    assert_true(str_contains($php, 'availabilityAllDayToggle'), 'Page exposes all-day toggle control');
    assert_true(str_contains($php, 'fullcalendar@5.11.3/main.min.css'), 'Page loads FullCalendar v5 CSS');
    assert_true(str_contains($php, 'fullcalendar@5.11.3/main.min.js'), 'Page loads FullCalendar v5 JS');
    assert_true(str_contains($php, 'dateClick'), 'Page uses dateClick for non-contiguous selected dates');
    assert_true(str_contains($php, 'selectable: false'), 'Page avoids range selection for multi-date toggles');
    assert_true(str_contains($php, 'availability-management.php?umpire_user_id='), 'Successful mutations redirect back to selected umpire');
    assert_true(str_contains($php, 'htmlspecialchars'), 'Page escapes rendered values');

    $nav = file_get_contents($projectRoot . '/includes/nav.php');
    assert_equals(substr_count($nav, 'availability-management.php'), 2, 'Nav should expose Manage Availability in admin and assignor menus');
    assert_true(str_contains($nav, "isActiveNav('availability-management', 'umpires')"), 'Nav active state includes availability-management');

    $roster = file_get_contents($projectRoot . '/public/admin/umpires/roster.php');
    assert_true(str_contains($roster, "availability-management.php?umpire_user_id="), 'Roster exposes per-umpire availability action');
    assert_true(str_contains($roster, "\$umpire['status'] === 'active'"), 'Roster gates availability action to active umpires');

    assert_true(file_exists($projectRoot . '/public/admin/umpires/availability.php'), 'Story 25.3 availability query route should exist');
    $queryPage = file_get_contents($projectRoot . '/public/admin/umpires/availability.php');
    assert_true(str_contains($queryPage, 'getAvailabilityPoolForWindow('), 'Query page should use display pool service method');
    assert_true(str_contains($queryPage, "PermissionGuard::requireRole('umpire_assignor', '/login.php')") || str_contains($queryPage, "PermissionGuard::requireRole(['admin', 'umpire_assignor'], '/login.php')"), 'Query page should be assignor/admin guarded');
    assert_true(str_contains($queryPage, "\$_GET['date']"), 'Query page should read date from GET');
    assert_true(str_contains($queryPage, "\$_GET['time']"), 'Query page should read time from GET');
    assert_true(str_contains($queryPage, 'UmpireConflictChecker::assignmentWindowSeconds()'), 'Query page should use assignment window duration');
    assert_true(str_contains($queryPage, 'minute duration'), 'Query page should show window duration');
    assert_true(str_contains($queryPage, 'No umpires are available'), 'Query page should render empty state');
    assert_true(str_contains($queryPage, 'htmlspecialchars'), 'Query page should escape rendered values');
    assert_true(!str_contains($queryPage, 'Auth::verifyCSRFToken'), 'Read-only query page should not use CSRF mutation flow');

    assert_equals(substr_count($nav, 'availability-management.php'), 2, 'Nav should still expose Manage Availability in admin and assignor menus');
    assert_equals(substr_count($nav, 'admin/umpires/availability.php'), 2, 'Nav should expose Availability Query in admin and assignor menus');
    assert_true(str_contains($nav, "isActiveNav('availability', 'umpires')"), 'Nav active state includes availability query');
    assert_true(str_contains($nav, 'Availability Query'), 'Nav labels the standalone query route');
});

// Implementation of service will follow to make these pass
if (file_exists(__DIR__ . '/../../includes/UmpireAvailabilityService.php')) {
    require_once __DIR__ . '/../../includes/UmpireAvailabilityService.php';
} else {
    // Scaffold class if not yet implemented so tests can at least be registered
    class UmpireAvailabilityService {
        public function listForUmpire(int $u) { throw new RuntimeException("Not implemented"); }
        public function createWindow($u, $s, $e, $n=null) { throw new RuntimeException("Not implemented"); }
        public function updateWindow($i, $u, $s, $e, $n=null) { throw new RuntimeException("Not implemented"); }
        public function deleteWindow($i, $u) { throw new RuntimeException("Not implemented"); }
        public function getAvailableUmpireIdsForWindow($s, $e) { throw new RuntimeException("Not implemented"); }
    }
}
