-- =============================================================================
-- Remove all E2E dummy data created by e2e_dummy_data_seed.sql
--
-- Strategy:
--   1) Delete program 'E2E_DUMMY' — cascades seasons, divisions, teams, games,
--      schedules, schedule_change_requests, and schedule_history rows tied to
--      those games (per FK ON DELETE CASCADE in schema).
--   2) Delete orphan locations tagged with name prefix 'E2E_TEST ' (not FK'd
--      from programs; safe once schedules referencing them are gone).
--
-- Usage:
--   mysql -u USER -p YOUR_DB < database/e2e_dummy_data_delete.sql
-- =============================================================================

SET NAMES utf8mb4;

-- Anchor row: cascades seasons → divisions, teams, games → schedules,
-- schedule_history, schedule_change_requests (per database/schema.sql FKs).
DELETE FROM programs
WHERE program_code = 'E2E_DUMMY';

-- Locations are not tied to programs; remove fields created for this fixture.
DELETE FROM locations
WHERE location_name LIKE 'E2E_TEST %';

SELECT
  (SELECT COUNT(*) FROM programs WHERE program_code = 'E2E_DUMMY') AS remaining_e2e_programs,
  (SELECT COUNT(*) FROM locations WHERE location_name LIKE 'E2E_TEST %') AS remaining_e2e_locations;
