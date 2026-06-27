-- Migration: 051_create_umpire_availability_windows
-- Description: Adds table for umpires to manage their availability windows.
-- Story: 25.1

CREATE TABLE IF NOT EXISTS `umpire_availability_windows` (
    `availability_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `umpire_user_id` INT NOT NULL,
    `starts_at` DATETIME NOT NULL,
    `ends_at` DATETIME NOT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_umpire_availability_user` FOREIGN KEY (`umpire_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_umpire_availability_user_window` (`umpire_user_id`, `starts_at`, `ends_at`),
    INDEX `idx_umpire_availability_window_lookup` (`starts_at`, `ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Record migration
INSERT INTO `schema_migrations` (`version`) VALUES ('051') ON DUPLICATE KEY UPDATE `run_at` = CURRENT_TIMESTAMP;
