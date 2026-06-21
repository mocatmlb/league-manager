<?php
/**
 * Integration Tests: Story 23.7 - Umpire Program Eligibility Filtering
 * Uses real test database to verify service interactions.
 */

if (!defined('D8TL_APP')) {
    define('D8TL_APP', true);
}

require_once __DIR__ . '/test-helpers.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/ActivityLogger.php';
require_once __DIR__ . '/../../includes/UmpireRosterService.php';
require_once __DIR__ . '/../../includes/UmpireAssignmentService.php';

// Force database reset to use test config
Database::setInstance(null);
$db = Database::getInstance();
echo "[DEBUG_LOG] Integration DB: " . DB_NAME . "\n";
$tables = $db->query("SHOW TABLES")->fetchAll();
echo "[DEBUG_LOG] Table count: " . count($tables) . "\n";
foreach ($tables as $t) {
    echo "[DEBUG_LOG] Table: " . reset($t) . "\n";
}

// Ensure we are on the test database
if (strpos(DB_NAME, 'test') === false) {
    die("Integration tests MUST run on a test database. Current DB: " . DB_NAME);
}

function cleanup_eligibility_test_data() {
    $db = Database::getInstance();
    $db->query("CREATE TABLE IF NOT EXISTS umpire_program_eligibility (
        umpire_user_id INT NOT NULL,
        program_id INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (umpire_user_id, program_id)
    )");
    $db->query("CREATE TABLE IF NOT EXISTS umpire_profiles (
        user_id INT PRIMARY KEY,
        umpire_level VARCHAR(50),
        is_under_18 TINYINT(1) DEFAULT 0,
        date_of_birth DATE,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $db->query("DELETE FROM umpire_program_eligibility");
    $db->query("DELETE FROM umpire_profiles WHERE user_id > 1000");
    $db->query("DELETE FROM users WHERE id > 1000");
    $db->query("DELETE FROM programs WHERE program_id > 1000");
    $db->query("DELETE FROM seasons WHERE season_id > 1000");
    $db->query("DELETE FROM games WHERE game_id > 1000");
    $db->query("DELETE FROM teams WHERE team_id > 1000");

    // Ensure 'umpire' role exists in test DB
    $db->query("INSERT IGNORE INTO roles (id, name) VALUES (2, 'umpire')");
}

register_test('23.7 CRUD: Persist and Retrieve Eligibility', function () {
    cleanup_eligibility_test_data();
    $db = Database::getInstance();
    $rosterSvc = new UmpireRosterService();

    // Setup: 2 programs, 1 umpire
    $db->query("INSERT INTO programs (program_id, program_name, program_code, active_status, sport_type) VALUES (1001, 'Prog A', 'A', 'Active', 'Baseball')");
    $db->query("INSERT INTO programs (program_id, program_name, program_code, active_status, sport_type) VALUES (1002, 'Prog B', 'B', 'Active', 'Baseball')");
    
    $umpireId = 1001;
    $db->query("INSERT INTO users (id, username, password_hash, first_name, last_name, email, phone, role_id, status) VALUES ({$umpireId}, 'ump@test.com', 'hash', 'Test', 'Umpire', 'ump@test.com', '555-0101', 2, 'active')");
    $db->query("INSERT INTO umpire_profiles (user_id, umpire_level) VALUES ({$umpireId}, 'Blue Shirt')");

    // 1. Set specific eligibility
    $rosterSvc->updateProfile($umpireId, [
        'all_programs' => false,
        'program_ids' => [1001]
    ], 1);

    $umpire = $rosterSvc->getUmpire($umpireId);
    assert_equals($umpire['all_programs'], false);
    assert_equals($umpire['program_ids'], [1001]);

    // 2. Set all programs
    $rosterSvc->updateProfile($umpireId, [
        'all_programs' => true,
        'program_ids' => [1001, 1002] // Should be ignored/cleared
    ], 1);

    $umpire = $rosterSvc->getUmpire($umpireId);
    assert_equals($umpire['all_programs'], true);
    assert_true(empty($umpire['program_ids']));
});

register_test('23.7 Assignment Filtering: Drawer and saveSlot', function () {
    cleanup_eligibility_test_data();
    $db = Database::getInstance();
    $rosterSvc = new UmpireRosterService();
    $assignSvc = new UmpireAssignmentService();

    // Setup: Program 1001, Season, Game
    $db->query("INSERT INTO programs (program_id, program_name, program_code, active_status, sport_type) VALUES (1001, 'Prog A', 'A', 'Active', 'Baseball')");
    $db->query("INSERT INTO seasons (season_id, program_id, season_name, season_year) VALUES (1001, 1001, 'Test Season', 2026)");
    
    // Games requires teams
    $db->query("INSERT INTO teams (team_id, team_name, season_id, league_name, manager_first_name, manager_last_name) VALUES (1001, 'Home', 1001, 'D8', 'H', 'M'), (1002, 'Away', 1001, 'D8', 'A', 'M')");
    $db->query("INSERT INTO games (game_id, game_number, season_id, home_team_id, away_team_id) VALUES (1001, 'G1001', 1001, 1001, 1002)");

    // Umpires: 1001 (Eligible), 1002 (Ineligible)
    $db->query("INSERT INTO users (id, username, password_hash, first_name, last_name, email, phone, role_id, status) VALUES (1001, 'e@test.com', 'hash', 'Eligible', 'Ump', 'e@test.com', '555-1001', 2, 'active')");
    $db->query("INSERT INTO umpire_profiles (user_id, umpire_level) VALUES (1001, 'Blue Shirt')");
    
    $db->query("INSERT INTO users (id, username, password_hash, first_name, last_name, email, phone, role_id, status) VALUES (1002, 'i@test.com', 'hash', 'Ineligible', 'Ump', 'i@test.com', '555-1002', 2, 'active')");
    $db->query("INSERT INTO umpire_profiles (user_id, umpire_level) VALUES (1002, 'Blue Shirt')");

    // Set 1002 to be eligible for 1002 (not created yet) - effectively ineligible for 1001
    $db->query("INSERT INTO programs (program_id, program_name, program_code, active_status, sport_type) VALUES (1002, 'Prog B', 'B', 'Active', 'Baseball')");
    $db->query("INSERT INTO umpire_program_eligibility (umpire_user_id, program_id) VALUES (1002, 1002)");

    // 1. Verify Drawer Filtering
    $drawer = $assignSvc->getGameAssignmentDrawer(1001);
    $rosterIds = array_column($drawer['roster'], 'id');
    assert_true(in_array(1001, $rosterIds), "Eligible umpire 1001 should be in drawer");
    assert_true(!in_array(1002, $rosterIds), "Ineligible umpire 1002 should NOT be in drawer");

    // 2. Verify saveSlot Enforcement
    $threw = false;
    try {
        $assignSvc->saveSlot(1001, 0, 1002, 1);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
        assert_equals($e->getMessage(), 'Umpire is not eligible for this game\'s program.');
    }
    assert_true($threw, "saveSlot should reject ineligible umpire");

    // Should work for 1001
    $assignSvc->saveSlot(1001, 0, 1001, 1);
    $slot = $db->fetchOne("SELECT umpire_user_id FROM game_umpire_assignments WHERE game_id=1001 AND slot_index=0");
    assert_equals($slot['umpire_user_id'], 1001);
});

register_test('23.7 Sync: Backfill Eligibility', function () {
    cleanup_eligibility_test_data();
    $db = Database::getInstance();
    $rosterSvc = new UmpireRosterService();

    $db->query("INSERT INTO programs (program_id, program_name, program_code, active_status, sport_type) VALUES (1001, 'Prog A', 'A', 'Active', 'Baseball')");
    $db->query("INSERT INTO programs (program_id, program_name, program_code, active_status, sport_type) VALUES (1002, 'Prog B', 'B', 'Active', 'Baseball')");
    
    // Umpire 1001: Selected Programs (only 1001)
    $db->query("INSERT INTO users (id, username, password_hash, first_name, last_name, email, phone, role_id, status) VALUES (1001, 's@test.com', 'hash', 'Selected', 'Ump', 's@test.com', '555-2001', 2, 'active')");
    $db->query("INSERT INTO umpire_profiles (user_id, umpire_level) VALUES (1001, 'Blue Shirt')");
    $db->query("INSERT INTO umpire_program_eligibility (umpire_user_id, program_id) VALUES (1001, 1001)");

    // Umpire 1002: All Programs (no rows)
    $db->query("INSERT INTO users (id, username, password_hash, first_name, last_name, email, phone, role_id, status) VALUES (1002, 'a@test.com', 'hash', 'All', 'Ump', 'a@test.com', '555-2002', 2, 'active')");
    $db->query("INSERT INTO umpire_profiles (user_id, umpire_level) VALUES (1002, 'Blue Shirt')");

    // Sync Program 1002
    $count = $rosterSvc->syncProgramEligibility([1002], 1);
    assert_equals($count, 1, "Should backfill 1 row for Umpire 1001");

    $umpire1 = $rosterSvc->getUmpire(1001);
    assert_equals(count($umpire1['program_ids']), 2);
    assert_true(in_array(1002, $umpire1['program_ids']));

    $umpire2 = $rosterSvc->getUmpire(1002);
    assert_equals($umpire2['all_programs'], true, "All-programs umpire should remain in all-programs mode");
});
