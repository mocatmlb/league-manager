-- Migration: Add location_id to schedule_history for better auditability
-- Story 14.2 AC 5 requirement

ALTER TABLE schedule_history ADD COLUMN location_id INT NULL AFTER location;
