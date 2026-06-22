-- Story 23.7: Umpire Program Eligibility Filtering
-- Add persistence for umpire-to-program eligibility mapping.

CREATE TABLE IF NOT EXISTS umpire_program_eligibility (
  umpire_user_id INT NOT NULL,
  program_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (umpire_user_id, program_id),
  FOREIGN KEY (umpire_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE,
  INDEX idx_umpire_program_eligibility_program (program_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
