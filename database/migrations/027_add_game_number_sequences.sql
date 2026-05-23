-- Migration 027: Add game_number_sequences table for auto-generated game numbers
-- Date: 2026-05-23
-- Description: Creates a sequences table to atomically generate game numbers in
--              YYYYNNNN format (four-digit year + four-digit zero-padded counter).
--              Seeds from existing game_number values so no collision with historic data.
--
-- Affected tables: game_number_sequences (CREATE)
-- Idempotent: Yes (CREATE TABLE IF NOT EXISTS, INSERT IGNORE for seeding)
-- Compatibility: MySQL 8.0 / InnoDB

CREATE TABLE IF NOT EXISTS game_number_sequences (
    seq_year   SMALLINT UNSIGNED NOT NULL,
    last_seq   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (seq_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed from existing game_number values matching YYYY#### format so that
-- the first auto-generated number for any given year never collides with
-- manually-entered historic data.
INSERT IGNORE INTO game_number_sequences (seq_year, last_seq)
SELECT
    CAST(LEFT(game_number, 4) AS UNSIGNED)                    AS seq_year,
    MAX(CAST(RIGHT(game_number, 4) AS UNSIGNED))              AS last_seq
FROM games
WHERE game_number REGEXP '^[0-9]{8}$'
GROUP BY LEFT(game_number, 4);

INSERT IGNORE INTO schema_migrations (version) VALUES ('027');
