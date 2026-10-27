-- Holy Face AMGS — MySQL/MariaDB schema
-- Charset: utf8mb4 for Filipino diacritics and emoji in announcements

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS holyface_amgs
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE holyface_amgs;

-- ---------------------------------------------------------------------------
-- Roles & users
-- ---------------------------------------------------------------------------
CREATE TABLE roles (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(32) NOT NULL,
  name VARCHAR(64) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id TINYINT UNSIGNED NOT NULL,
  email VARCHAR(191) NOT NULL,
  username VARCHAR(64) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(191) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_username (username),
  KEY idx_users_role (role_id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id)
) ENGINE=InnoDB;

CREATE TABLE refresh_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_refresh_user (user_id),
  KEY idx_refresh_hash (token_hash),
  CONSTRAINT fk_refresh_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Academic structure
-- ---------------------------------------------------------------------------
CREATE TABLE strands (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(16) NOT NULL,
  name VARCHAR(128) NOT NULL,
  description TEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_strands_code (code)
) ENGINE=InnoDB;

CREATE TABLE school_years (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  label VARCHAR(32) NOT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  starts_on DATE NULL,
  ends_on DATE NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_school_years_label (label)
) ENGINE=InnoDB;

CREATE TABLE teachers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  employee_no VARCHAR(32) NOT NULL,
  department VARCHAR(128) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_teachers_user (user_id),
  UNIQUE KEY uq_teachers_employee (employee_no),
  CONSTRAINT fk_teachers_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE students (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  lrn VARCHAR(12) NOT NULL,
  strand_id TINYINT UNSIGNED NOT NULL,
  grade_level TINYINT UNSIGNED NOT NULL COMMENT '11 or 12',
  section_code VARCHAR(16) NOT NULL,
  school_year_id SMALLINT UNSIGNED NOT NULL,
  photo_path VARCHAR(255) NULL,
  guardian_name VARCHAR(191) NULL,
  guardian_contact VARCHAR(64) NULL,
  date_of_birth DATE NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_students_user (user_id),
  UNIQUE KEY uq_students_lrn (lrn),
  KEY idx_students_strand (strand_id),
  KEY idx_students_sy (school_year_id),
  CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_students_strand FOREIGN KEY (strand_id) REFERENCES strands (id),
  CONSTRAINT fk_students_sy FOREIGN KEY (school_year_id) REFERENCES school_years (id)
) ENGINE=InnoDB;

CREATE TABLE subject_categories (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(32) NOT NULL,
  name VARCHAR(128) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subject_cat_code (code)
) ENGINE=InnoDB;

CREATE TABLE subjects (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_code VARCHAR(32) NOT NULL,
  title VARCHAR(191) NOT NULL,
  category_id TINYINT UNSIGNED NOT NULL,
  strand_id TINYINT UNSIGNED NULL COMMENT 'NULL = core/all strands',
  grade_level TINYINT UNSIGNED NOT NULL,
  weight_profile_code VARCHAR(64) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subjects_code (subject_code),
  KEY idx_subjects_cat (category_id),
  CONSTRAINT fk_subjects_cat FOREIGN KEY (category_id) REFERENCES subject_categories (id),
  CONSTRAINT fk_subjects_strand FOREIGN KEY (strand_id) REFERENCES strands (id)
) ENGINE=InnoDB;

CREATE TABLE class_sections (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_year_id SMALLINT UNSIGNED NOT NULL,
  strand_id TINYINT UNSIGNED NOT NULL,
  grade_level TINYINT UNSIGNED NOT NULL,
  section_code VARCHAR(16) NOT NULL,
  adviser_teacher_id INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_section (school_year_id, strand_id, grade_level, section_code),
  KEY idx_section_adviser (adviser_teacher_id),
  CONSTRAINT fk_section_sy FOREIGN KEY (school_year_id) REFERENCES school_years (id),
  CONSTRAINT fk_section_strand FOREIGN KEY (strand_id) REFERENCES strands (id),
  CONSTRAINT fk_section_adviser FOREIGN KEY (adviser_teacher_id) REFERENCES teachers (id)
) ENGINE=InnoDB;

CREATE TABLE class_subject_assignments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_section_id INT UNSIGNED NOT NULL,
  subject_id SMALLINT UNSIGNED NOT NULL,
  teacher_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_csa (class_section_id, subject_id),
  KEY idx_csa_teacher (teacher_id),
  CONSTRAINT fk_csa_section FOREIGN KEY (class_section_id) REFERENCES class_sections (id) ON DELETE CASCADE,
  CONSTRAINT fk_csa_subject FOREIGN KEY (subject_id) REFERENCES subjects (id),
  CONSTRAINT fk_csa_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id)
) ENGINE=InnoDB;

CREATE TABLE enrollments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id INT UNSIGNED NOT NULL,
  class_section_id INT UNSIGNED NOT NULL,
  status ENUM('enrolled','dropped','completed') NOT NULL DEFAULT 'enrolled',
  enrolled_at DATE NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_enrollment (student_id, class_section_id),
  KEY idx_enroll_section (class_section_id),
  CONSTRAINT fk_enroll_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_enroll_section FOREIGN KEY (class_section_id) REFERENCES class_sections (id)
) ENGINE=InnoDB;

-- DepEd-style component weights (percentages must sum to 100 per subject assignment)
CREATE TABLE grade_component_weights (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_subject_assignment_id INT UNSIGNED NOT NULL,
  written_work_pct DECIMAL(5,2) NOT NULL DEFAULT 40.00,
  performance_task_pct DECIMAL(5,2) NOT NULL DEFAULT 40.00,
  quarterly_assessment_pct DECIMAL(5,2) NOT NULL DEFAULT 20.00,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gcw_csa (class_subject_assignment_id),
  CONSTRAINT fk_gcw_csa FOREIGN KEY (class_subject_assignment_id) REFERENCES class_subject_assignments (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE grade_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_subject_assignment_id INT UNSIGNED NOT NULL,
  quarter TINYINT UNSIGNED NOT NULL,
  component_type ENUM('written_work','performance_task','quarterly_assessment') NOT NULL,
  item_label VARCHAR(128) NOT NULL,
  max_score DECIMAL(6,2) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gi_csa (class_subject_assignment_id, quarter),
  CONSTRAINT fk_gi_csa FOREIGN KEY (class_subject_assignment_id) REFERENCES class_subject_assignments (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE grade_components (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  grade_item_id INT UNSIGNED NULL,
  enrollment_id INT UNSIGNED NOT NULL,
  class_subject_assignment_id INT UNSIGNED NOT NULL,
  quarter TINYINT UNSIGNED NOT NULL COMMENT '1-4',
  component_type ENUM('written_work','performance_task','quarterly_assessment') NOT NULL,
  item_label VARCHAR(128) NOT NULL,
  max_score DECIMAL(6,2) NOT NULL,
  score DECIMAL(6,2) NULL,
  entry_status ENUM('scored','absent','excused') NOT NULL DEFAULT 'scored',
  recorded_by INT UNSIGNED NULL,
  recorded_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_gc_enrollment (enrollment_id),
  KEY idx_gc_csa (class_subject_assignment_id),
  CONSTRAINT fk_gc_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments (id) ON DELETE CASCADE,
  CONSTRAINT fk_gc_csa FOREIGN KEY (class_subject_assignment_id) REFERENCES class_subject_assignments (id),
  CONSTRAINT fk_gc_teacher FOREIGN KEY (recorded_by) REFERENCES users (id),
  CONSTRAINT fk_gc_item FOREIGN KEY (grade_item_id) REFERENCES grade_items (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE grades (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  enrollment_id INT UNSIGNED NOT NULL,
  class_subject_assignment_id INT UNSIGNED NOT NULL,
  quarter TINYINT UNSIGNED NOT NULL,
  written_work_avg DECIMAL(5,2) NULL,
  performance_task_avg DECIMAL(5,2) NULL,
  quarterly_assessment_score DECIMAL(5,2) NULL,
  initial_grade DECIMAL(5,2) NULL,
  transmuted_grade DECIMAL(5,2) NULL,
  remarks VARCHAR(32) NULL,
  computed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_grade_row (enrollment_id, class_subject_assignment_id, quarter),
  CONSTRAINT fk_grades_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments (id) ON DELETE CASCADE,
  CONSTRAINT fk_grades_csa FOREIGN KEY (class_subject_assignment_id) REFERENCES class_subject_assignments (id)
) ENGINE=InnoDB;

CREATE TABLE final_grades (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  enrollment_id INT UNSIGNED NOT NULL,
  class_subject_assignment_id INT UNSIGNED NOT NULL,
  q1 DECIMAL(5,2) NULL,
  q2 DECIMAL(5,2) NULL,
  q3 DECIMAL(5,2) NULL,
  q4 DECIMAL(5,2) NULL,
  sem1 DECIMAL(5,2) NULL,
  sem2 DECIMAL(5,2) NULL,
  final_rating DECIMAL(5,2) NULL,
  action_taken VARCHAR(32) NULL,
  is_submitted TINYINT(1) NOT NULL DEFAULT 0,
  submitted_at DATETIME NULL,
  submitted_by INT UNSIGNED NULL,
  computed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_final_grade (enrollment_id, class_subject_assignment_id),
  CONSTRAINT fk_final_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments (id) ON DELETE CASCADE,
  CONSTRAINT fk_final_csa FOREIGN KEY (class_subject_assignment_id) REFERENCES class_subject_assignments (id)
) ENGINE=InnoDB;

CREATE TABLE official_report_cards (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  enrollment_id INT UNSIGNED NOT NULL,
  school_year_id SMALLINT UNSIGNED NOT NULL,
  card_number VARCHAR(40) NOT NULL,
  general_average DECIMAL(5,2) NOT NULL,
  remarks VARCHAR(32) NOT NULL,
  payload_json LONGTEXT NOT NULL,
  issued_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orc_enrollment (enrollment_id),
  UNIQUE KEY uq_orc_number (card_number),
  KEY idx_orc_sy (school_year_id),
  CONSTRAINT fk_orc_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments (id) ON DELETE CASCADE,
  CONSTRAINT fk_orc_sy FOREIGN KEY (school_year_id) REFERENCES school_years (id)
) ENGINE=InnoDB;

CREATE TABLE grade_audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_user_id INT UNSIGNED NOT NULL,
  enrollment_id INT UNSIGNED NOT NULL,
  class_subject_assignment_id INT UNSIGNED NOT NULL,
  quarter TINYINT UNSIGNED NOT NULL,
  field_name VARCHAR(64) NOT NULL,
  old_value VARCHAR(64) NULL,
  new_value VARCHAR(64) NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_actor (actor_user_id),
  KEY idx_audit_enrollment (enrollment_id),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users (id),
  CONSTRAINT fk_audit_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments (id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Content & privacy
-- ---------------------------------------------------------------------------
CREATE TABLE announcements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(191) NOT NULL,
  body TEXT NOT NULL,
  audience ENUM('all','students','teachers','public') NOT NULL DEFAULT 'all',
  published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  created_by INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ann_audience (audience),
  CONSTRAINT fk_ann_author FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB;

CREATE TABLE study_materials (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_id SMALLINT UNSIGNED NOT NULL,
  title VARCHAR(191) NOT NULL,
  content TEXT NOT NULL,
  tags VARCHAR(255) NULL,
  uploaded_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sm_subject (subject_id),
  CONSTRAINT fk_sm_subject FOREIGN KEY (subject_id) REFERENCES subjects (id),
  CONSTRAINT fk_sm_uploader FOREIGN KEY (uploaded_by) REFERENCES users (id)
) ENGINE=InnoDB;

-- AI sessions store NO PII — only anonymous token + material id
CREATE TABLE ai_study_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_token CHAR(36) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  study_material_id INT UNSIGNED NULL,
  tool_type ENUM('study_plan','flashcards','quiz') NOT NULL,
  request_summary VARCHAR(255) NOT NULL COMMENT 'Non-PII description only',
  response_payload JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ai_user (user_id),
  KEY idx_ai_token (session_token),
  CONSTRAINT fk_ai_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_ai_material FOREIGN KEY (study_material_id) REFERENCES study_materials (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE policy_documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_code VARCHAR(32) NOT NULL,
  version VARCHAR(16) NOT NULL,
  title VARCHAR(191) NOT NULL,
  last_updated DATE NOT NULL,
  body_json JSON NOT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  published_by INT UNSIGNED NULL,
  published_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_policy_ver (document_code, version)
) ENGINE=InnoDB;

CREATE TABLE consent_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  consent_type VARCHAR(64) NOT NULL,
  version VARCHAR(16) NOT NULL,
  granted TINYINT(1) NOT NULL,
  details JSON NULL,
  guardian_name VARCHAR(191) NULL,
  guardian_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  ip_address VARCHAR(45) NULL,
  device_info VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_consent_user (user_id),
  CONSTRAINT fk_consent_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_user_id INT UNSIGNED NULL,
  action VARCHAR(64) NOT NULL,
  entity_type VARCHAR(64) NULL,
  entity_id VARCHAR(64) NULL,
  metadata JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_action (action),
  KEY idx_audit_actor_general (actor_user_id),
  CONSTRAINT fk_audit_general_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE csrf_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  token_hash CHAR(64) NOT NULL,
  session_key VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_csrf_session (session_key)
) ENGINE=InnoDB;

CREATE TABLE login_rate_limits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_address VARCHAR(45) NOT NULL,
  username_attempt VARCHAR(64) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rate_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE early_warning_thresholds (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(32) NOT NULL,
  warn_below DECIMAL(5,2) NOT NULL DEFAULT 75.00,
  critical_below DECIMAL(5,2) NOT NULL DEFAULT 72.00,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ewt_code (code)
) ENGINE=InnoDB;

CREATE TABLE class_schedules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_section_id INT UNSIGNED NOT NULL,
  subject_id SMALLINT UNSIGNED NOT NULL,
  weekday TINYINT UNSIGNED NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  room VARCHAR(32) NULL,
  PRIMARY KEY (id),
  KEY idx_csched_section (class_section_id),
  CONSTRAINT fk_csched_section FOREIGN KEY (class_section_id) REFERENCES class_sections (id) ON DELETE CASCADE,
  CONSTRAINT fk_csched_subject FOREIGN KEY (subject_id) REFERENCES subjects (id)
) ENGINE=InnoDB;

CREATE TABLE study_plan_tasks (
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

CREATE TABLE flashcard_decks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  subject_label VARCHAR(128) NOT NULL,
  topic VARCHAR(191) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_fcd_user (user_id),
  CONSTRAINT fk_fcd_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE flashcards (
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

CREATE TABLE quiz_attempts (
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

CREATE TABLE data_correction_requests (
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

CREATE TABLE ai_reports (
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

CREATE TABLE grading_weight_profiles (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(191) NOT NULL,
  written_work_pct DECIMAL(5,2) NOT NULL,
  performance_task_pct DECIMAL(5,2) NOT NULL,
  quarterly_assessment_pct DECIMAL(5,2) NOT NULL,
  confirmed TINYINT(1) NOT NULL DEFAULT 0,
  source_note VARCHAR(255) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gwp_code (code)
) ENGINE=InnoDB;

CREATE TABLE transmutation_table (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  initial_min DECIMAL(5,2) NOT NULL,
  initial_max DECIMAL(5,2) NOT NULL,
  transmuted_grade TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE grading_settings (
  setting_key VARCHAR(64) NOT NULL,
  setting_value VARCHAR(128) NOT NULL,
  source_note VARCHAR(255) NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB;

CREATE TABLE grading_period_locks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  school_year_id SMALLINT UNSIGNED NOT NULL,
  quarter TINYINT UNSIGNED NOT NULL,
  is_locked TINYINT(1) NOT NULL DEFAULT 0,
  locked_by INT UNSIGNED NULL,
  locked_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gpl (school_year_id, quarter),
  CONSTRAINT fk_gpl_sy FOREIGN KEY (school_year_id) REFERENCES school_years (id)
) ENGINE=InnoDB;

CREATE TABLE contact_inquiries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(191) NOT NULL,
  email VARCHAR(191) NOT NULL,
  role VARCHAR(32) NOT NULL,
  subject VARCHAR(191) NOT NULL,
  message TEXT NOT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contact_created (created_at)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
