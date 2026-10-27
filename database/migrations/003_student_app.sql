USE holyface_amgs;

ALTER TABLE users
  ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;

ALTER TABLE consent_logs
  ADD COLUMN details JSON NULL AFTER granted;

CREATE TABLE IF NOT EXISTS early_warning_thresholds (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(32) NOT NULL,
  warn_below DECIMAL(5,2) NOT NULL DEFAULT 75.00,
  critical_below DECIMAL(5,2) NOT NULL DEFAULT 72.00,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ewt_code (code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS class_schedules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_section_id INT UNSIGNED NOT NULL,
  subject_id SMALLINT UNSIGNED NOT NULL,
  weekday TINYINT UNSIGNED NOT NULL COMMENT '1=Mon .. 5=Fri',
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  room VARCHAR(32) NULL,
  PRIMARY KEY (id),
  KEY idx_csched_section (class_section_id),
  CONSTRAINT fk_csched_section FOREIGN KEY (class_section_id) REFERENCES class_sections (id) ON DELETE CASCADE,
  CONSTRAINT fk_csched_subject FOREIGN KEY (subject_id) REFERENCES subjects (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS study_plan_tasks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_token CHAR(36) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  day_no TINYINT UNSIGNED NOT NULL,
  task_text VARCHAR(255) NOT NULL,
  is_done TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_spt_user (user_id),
  KEY idx_spt_token (session_token),
  CONSTRAINT fk_spt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS flashcard_decks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  subject_label VARCHAR(128) NOT NULL,
  topic VARCHAR(191) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_fcd_user (user_id),
  CONSTRAINT fk_fcd_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS flashcards (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  deck_id INT UNSIGNED NOT NULL,
  front_text TEXT NOT NULL,
  back_text TEXT NOT NULL,
  box TINYINT UNSIGNED NOT NULL DEFAULT 1,
  next_review DATE NOT NULL,
  PRIMARY KEY (id),
  KEY idx_fc_deck (deck_id),
  CONSTRAINT fk_fc_deck FOREIGN KEY (deck_id) REFERENCES flashcard_decks (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quiz_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  session_token CHAR(36) NOT NULL,
  subject_label VARCHAR(128) NOT NULL,
  difficulty VARCHAR(16) NOT NULL,
  score TINYINT UNSIGNED NOT NULL,
  total TINYINT UNSIGNED NOT NULL,
  missed_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_qa_user (user_id),
  CONSTRAINT fk_qa_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS data_correction_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  field_name VARCHAR(64) NOT NULL,
  current_value VARCHAR(255) NULL,
  requested_value VARCHAR(255) NOT NULL,
  reason TEXT NULL,
  status ENUM('pending','in_review','done','rejected') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dcr_user (user_id),
  CONSTRAINT fk_dcr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ai_reports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  session_token CHAR(36) NOT NULL,
  item_ref VARCHAR(64) NULL,
  comment TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_air_user (user_id),
  CONSTRAINT fk_air_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO early_warning_thresholds (code, warn_below, critical_below) VALUES
  ('quarter_grade', 75.00, 72.00);

INSERT INTO class_schedules (class_section_id, subject_id, weekday, start_time, end_time, room) VALUES
  (1, 1, 1, '07:30:00', '08:30:00', 'R201'),
  (1, 3, 1, '08:30:00', '09:30:00', 'Lab-1'),
  (1, 1, 3, '07:30:00', '08:30:00', 'R201'),
  (1, 3, 4, '10:00:00', '11:00:00', 'Lab-1'),
  (2, 1, 1, '09:30:00', '10:30:00', 'R105'),
  (2, 5, 2, '07:30:00', '08:30:00', 'R105');

-- Sample quarter grades (fictional) so academics screens are not empty
INSERT INTO grades (enrollment_id, class_subject_assignment_id, quarter, written_work_avg, performance_task_avg, quarterly_assessment_score, initial_grade, transmuted_grade, remarks, computed_at) VALUES
  (1, 1, 1, 70.00, 72.00, 74.00, 71.60, 72.00, 'FAILED', NOW()),
  (1, 1, 2, 78.00, 80.00, 76.00, 78.40, 85.00, 'PASSED', NOW()),
  (1, 2, 1, 88.00, 90.00, 86.00, 88.40, 94.00, 'PASSED', NOW()),
  (1, 2, 2, 84.00, 86.00, 82.00, 84.40, 91.00, 'PASSED', NOW()),
  (2, 3, 1, 80.00, 82.00, 78.00, 80.40, 87.00, 'PASSED', NOW());

INSERT INTO final_grades (enrollment_id, class_subject_assignment_id, q1, q2, q3, q4, final_rating, action_taken, computed_at) VALUES
  (1, 1, 72.00, 85.00, NULL, NULL, 78.50, 'PASSED', NOW()),
  (1, 2, 94.00, 91.00, NULL, NULL, 92.50, 'PASSED', NOW()),
  (2, 3, 87.00, NULL, NULL, NULL, 87.00, 'PASSED', NOW());

UPDATE users SET must_change_password = 1 WHERE username = 's005';
