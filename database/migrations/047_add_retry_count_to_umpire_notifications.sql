-- Migration 047: Add retry_count to umpire_pending_notifications
-- Story 23.5 patch: Support notification retry logic

ALTER TABLE umpire_pending_notifications
ADD COLUMN retry_count INT NOT NULL DEFAULT 0 AFTER trigger_event_ref;

INSERT IGNORE INTO schema_migrations (version) VALUES ('047');
