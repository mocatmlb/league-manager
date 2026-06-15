-- =============================================================================
-- E2E / QA dummy league data (seasons, divisions, teams, games, schedules,
-- schedule history, scores, schedule change requests).
--
-- Before re-seeding, run: e2e_dummy_data_delete.sql
--
-- Does not run USE; pass database on CLI, e.g.:
--   mysql -u USER -p YOUR_DB < database/e2e_dummy_data_seed.sql
-- =============================================================================

SET NAMES utf8mb4;
SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------------------------------
-- 1) Program & season (delete script targets program_code = 'E2E_DUMMY')
-- -----------------------------------------------------------------------------
INSERT INTO programs (program_name, program_code, sport_type, age_min, age_max, active_status)
VALUES ('E2E Dummy Baseball', 'E2E_DUMMY', 'Baseball', 10, 12, 'Active');
SET @e2e_program := LAST_INSERT_ID();

INSERT INTO seasons (program_id, season_name, season_year, start_date, end_date, season_status)
VALUES (@e2e_program, 'E2E Spring Showcase 2030', 2030, '2030-04-01', '2030-08-31', 'Active');
SET @e2e_season := LAST_INSERT_ID();

-- -----------------------------------------------------------------------------
-- 2) Divisions
-- -----------------------------------------------------------------------------
INSERT INTO divisions (season_id, division_name, division_code, max_teams)
VALUES (@e2e_season, 'E2E Division Alpha', 'E2E_A', 8);
SET @e2e_div_a := LAST_INSERT_ID();

INSERT INTO divisions (season_id, division_name, division_code, max_teams)
VALUES (@e2e_season, 'E2E Division Bravo', 'E2E_B', 8);
SET @e2e_div_b := LAST_INSERT_ID();

-- -----------------------------------------------------------------------------
-- 3) Locations (delete script uses location_name LIKE 'E2E_TEST %')
-- -----------------------------------------------------------------------------
INSERT INTO locations (location_name, address, city, state, active_status)
VALUES ('E2E_TEST Field North', '100 Test Lane', 'Testville', 'NY', 'Active');
SET @e2e_loc_n := LAST_INSERT_ID();

INSERT INTO locations (location_name, address, city, state, active_status)
VALUES ('E2E_TEST Field South', '200 Test Lane', 'Testville', 'NY', 'Active');
SET @e2e_loc_s := LAST_INSERT_ID();

-- -----------------------------------------------------------------------------
-- 4) Teams (four teams across two divisions)
-- -----------------------------------------------------------------------------
INSERT INTO teams (season_id, division_id, league_name, team_name, manager_first_name, manager_last_name,
                   manager_phone, manager_email, home_field, active_status)
VALUES (@e2e_season, @e2e_div_a, 'E2E Travel', 'E2E River Bats', 'Alex', 'Coachman', '555-0101', 'e2e.bats@example.test', 'E2E_TEST Field North', 'Active');
SET @e2e_team_bats := LAST_INSERT_ID();

INSERT INTO teams (season_id, division_id, league_name, team_name, manager_first_name, manager_last_name,
                   manager_phone, manager_email, home_field, active_status)
VALUES (@e2e_season, @e2e_div_a, 'E2E Travel', 'E2E Lake Lions', 'Blake', 'Mentor', '555-0102', 'e2e.lions@example.test', 'E2E_TEST Field South', 'Active');
SET @e2e_team_lions := LAST_INSERT_ID();

INSERT INTO teams (season_id, division_id, league_name, team_name, manager_first_name, manager_last_name,
                   manager_phone, manager_email, home_field, active_status)
VALUES (@e2e_season, @e2e_div_b, 'E2E Travel', 'E2E Hill Hawks', 'Casey', 'Skipper', '555-0103', 'e2e.hawks@example.test', 'E2E_TEST Field North', 'Active');
SET @e2e_team_hawks := LAST_INSERT_ID();

INSERT INTO teams (season_id, division_id, league_name, team_name, manager_first_name, manager_last_name,
                   manager_phone, manager_email, home_field, active_status)
VALUES (@e2e_season, @e2e_div_b, 'E2E Travel', 'E2E Vale Vipers', 'Dana', 'Leader', '555-0104', 'e2e.vipers@example.test', 'E2E_TEST Field South', 'Active');
SET @e2e_team_vipers := LAST_INSERT_ID();

-- -----------------------------------------------------------------------------
-- 5) Games (unique game_number prefix E2E-)
-- -----------------------------------------------------------------------------
INSERT INTO games (game_number, season_id, division_id, home_team_id, away_team_id, home_score, away_score, game_status,
                   score_submitted_by, score_submitted_at)
VALUES ('E2E-2030-001', @e2e_season, @e2e_div_a, @e2e_team_bats, @e2e_team_lions, NULL, NULL, 'Scheduled', NULL, NULL);
SET @e2e_game_1 := LAST_INSERT_ID();

INSERT INTO games (game_number, season_id, division_id, home_team_id, away_team_id, home_score, away_score, game_status,
                   score_submitted_by, score_submitted_at)
VALUES ('E2E-2030-002', @e2e_season, @e2e_div_b, @e2e_team_hawks, @e2e_team_vipers, 4, 2, 'Completed', 'e2e_seed', '2030-05-05 21:00:00');
SET @e2e_game_2 := LAST_INSERT_ID();

INSERT INTO games (game_number, season_id, division_id, home_team_id, away_team_id, home_score, away_score, game_status,
                   score_submitted_by, score_submitted_at)
VALUES ('E2E-2030-003', @e2e_season, @e2e_div_a, @e2e_team_lions, @e2e_team_bats, 1, 1, 'Scheduled', NULL, NULL);
SET @e2e_game_3 := LAST_INSERT_ID();

-- -----------------------------------------------------------------------------
-- 6) Schedules (live row used by schedule.php joins)
-- -----------------------------------------------------------------------------
INSERT INTO schedules (game_id, game_date, game_time, location_id)
VALUES (@e2e_game_1, '2030-05-10', '18:00:00', @e2e_loc_n);

INSERT INTO schedules (game_id, game_date, game_time, location_id)
VALUES (@e2e_game_2, '2030-05-04', '17:30:00', @e2e_loc_s);

INSERT INTO schedules (game_id, game_date, game_time, location_id)
VALUES (@e2e_game_3, '2030-05-15', '19:00:00', @e2e_loc_n);

-- -----------------------------------------------------------------------------
-- 7) Schedule history (aligns with schedules; supports history UI)
--    Game 2: v1 original (superseded) + v2 after approved change (current)
-- -----------------------------------------------------------------------------
INSERT INTO schedule_history (game_id, version_number, schedule_type, game_date, game_time, location,
                              change_request_id, created_by_type, is_current, notes)
VALUES (@e2e_game_1, 1, 'Original', '2030-05-10', '18:00:00', 'E2E_TEST Field North', NULL, 'System', TRUE,
        'E2E seed: original schedule');

INSERT INTO schedule_history (game_id, version_number, schedule_type, game_date, game_time, location,
                              change_request_id, created_by_type, is_current, notes)
VALUES (@e2e_game_2, 1, 'Original', '2030-05-04', '17:30:00', 'E2E_TEST Field South', NULL, 'System', FALSE,
        'E2E seed: original before approved reschedule');

INSERT INTO schedule_history (game_id, version_number, schedule_type, game_date, game_time, location,
                              change_request_id, created_by_type, is_current, notes)
VALUES (@e2e_game_3, 1, 'Original', '2030-05-15', '19:00:00', 'E2E_TEST Field North', NULL, 'System', TRUE,
        'E2E seed: original schedule');

-- -----------------------------------------------------------------------------
-- 8) Schedule change requests
--    - Pending reschedule for game 1
--    - Approved reschedule for game 2 (then add history v2 + bump schedules)
--    - Denied cancel for game 3
-- -----------------------------------------------------------------------------
INSERT INTO schedule_change_requests (
    game_id, requested_by, request_type,
    original_date, original_time, original_location,
    requested_date, requested_time, requested_location,
    reason, request_status, reviewed_by, reviewed_at, review_notes
) VALUES (
    @e2e_game_1, 'E2E Coach', 'Reschedule',
    '2030-05-10', '18:00:00', 'E2E_TEST Field North',
    '2030-05-12', '18:30:00', 'E2E_TEST Field South',
    'E2E seed: rain forecast — need pending row for QA', 'Pending', NULL, NULL, NULL
);
SET @e2e_scr_pending := LAST_INSERT_ID();

INSERT INTO schedule_change_requests (
    game_id, requested_by, request_type,
    original_date, original_time, original_location,
    requested_date, requested_time, requested_location,
    reason, request_status, reviewed_by, reviewed_at, review_notes
) VALUES (
    @e2e_game_2, 'E2E Coach', 'Reschedule',
    '2030-05-04', '17:30:00', 'E2E_TEST Field South',
    '2030-05-06', '18:00:00', 'E2E_TEST Field North',
    'E2E seed: approved field change', 'Approved', NULL, '2030-05-01 10:00:00', 'E2E seed approval'
);
SET @e2e_scr_approved := LAST_INSERT_ID();

INSERT INTO schedule_change_requests (
    game_id, requested_by, request_type,
    original_date, original_time, original_location,
    requested_date, requested_time, requested_location,
    reason, request_status, reviewed_by, reviewed_at, review_notes
) VALUES (
    @e2e_game_3, 'E2E Coach', 'Cancel',
    '2030-05-15', '19:00:00', 'E2E_TEST Field North',
    NULL, NULL, NULL,
    'E2E seed: cancel denied — keep game on calendar', 'Denied', NULL, '2030-05-02 09:00:00', 'E2E seed denial — play as scheduled'
);
SET @e2e_scr_denied := LAST_INSERT_ID();

-- History v2 for game 2 links to approved request; v1 no longer current
INSERT INTO schedule_history (game_id, version_number, schedule_type, game_date, game_time, location,
                              change_request_id, created_by_type, created_by_id, is_current, notes, created_at)
VALUES (@e2e_game_2, 2, 'Changed', '2030-05-06', '18:00:00', 'E2E_TEST Field North',
        @e2e_scr_approved, 'Admin', NULL, TRUE, 'E2E seed: post-approval schedule', '2030-05-01 10:00:01');

UPDATE schedule_history
SET is_current = FALSE
WHERE game_id = @e2e_game_2 AND version_number = 1;

UPDATE schedules
SET game_date = '2030-05-06', game_time = '18:00:00', location_id = @e2e_loc_n
WHERE game_id = @e2e_game_2;

-- -----------------------------------------------------------------------------
-- Done: summary (optional)
-- -----------------------------------------------------------------------------
SELECT @e2e_program AS e2e_program_id,
       @e2e_season AS e2e_season_id,
       @e2e_game_1 AS e2e_game_1_id,
       @e2e_game_2 AS e2e_game_2_id,
       @e2e_game_3 AS e2e_game_3_id,
       @e2e_scr_pending AS e2e_pending_request_id,
       @e2e_scr_approved AS e2e_approved_request_id,
       @e2e_scr_denied AS e2e_denied_request_id;
