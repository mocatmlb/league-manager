CREATE TABLE IF NOT EXISTS umpire_profiles (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  user_id          INT NOT NULL,
  umpire_level     ENUM('Blue Shirt','Black Shirt') NOT NULL DEFAULT 'Blue Shirt',
  is_under_18      TINYINT(1) NOT NULL DEFAULT 0,
  date_of_birth    DATE NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_umpire_profiles_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (version) VALUES ('042');
