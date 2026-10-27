USE holyface_amgs;

-- Teacher desktop: locks, weight profiles, transmutation table, class-level grade items
-- Sources cited in GradeComputationService.php (DepEd Order No. 8, s. 2015).

CREATE TABLE IF NOT EXISTS grading_weight_profiles (
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

CREATE TABLE IF NOT EXISTS transmutation_table (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  initial_min DECIMAL(5,2) NOT NULL,
  initial_max DECIMAL(5,2) NOT NULL,
  transmuted_grade TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS grading_settings (
  setting_key VARCHAR(64) NOT NULL,
  setting_value VARCHAR(128) NOT NULL,
  source_note VARCHAR(255) NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS grading_period_locks (
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

CREATE TABLE IF NOT EXISTS grade_items (
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

ALTER TABLE subjects
  ADD COLUMN IF NOT EXISTS weight_profile_code VARCHAR(64) NULL AFTER grade_level;

ALTER TABLE grade_components
  ADD COLUMN IF NOT EXISTS grade_item_id INT UNSIGNED NULL AFTER id,
  ADD COLUMN IF NOT EXISTS entry_status ENUM('scored','absent','excused') NOT NULL DEFAULT 'scored' AFTER score;

ALTER TABLE final_grades
  ADD COLUMN IF NOT EXISTS sem1 DECIMAL(5,2) NULL AFTER q4,
  ADD COLUMN IF NOT EXISTS sem2 DECIMAL(5,2) NULL AFTER sem1,
  ADD COLUMN IF NOT EXISTS is_submitted TINYINT(1) NOT NULL DEFAULT 0 AFTER action_taken,
  ADD COLUMN IF NOT EXISTS submitted_at DATETIME NULL AFTER is_submitted,
  ADD COLUMN IF NOT EXISTS submitted_by INT UNSIGNED NULL AFTER submitted_at;

INSERT IGNORE INTO grading_weight_profiles (code, name, written_work_pct, performance_task_pct, quarterly_assessment_pct, confirmed, source_note) VALUES
  ('languages_humanities_communication', 'Languages, Humanities & Communication', 25.00, 50.00, 25.00, 0, 'School default pending Registrar confirmation. Compare DepEd DO 8 s.2015 Table 4 (Languages often 30/50/20).'),
  ('mathematics_science_academic', 'Mathematics, Science & most academic subjects', 25.00, 45.00, 30.00, 0, 'School default pending Registrar confirmation. Compare DepEd DO 8 s.2015 Table 4 (Math/Science often 40/40/20).'),
  ('work_immersion_research_performance', 'Work Immersion / Research / Business Enterprise / Exhibit / Performance', 20.00, 60.00, 20.00, 0, 'UNCONFIRMED placeholder. Do not treat as official — Registrar must confirm. DepEd DO 8 s.2015 gives TLE/TVL/MAPEH a performance-heavy mix (often 20/60/20).');

INSERT IGNORE INTO grading_settings (setting_key, setting_value, source_note) VALUES
  ('passing_mark', '75', 'DepEd Order No. 8, s. 2015 — lowest passing transmuted grade is 75.'),
  ('early_warning_threshold', '78', 'School monitoring default (PART D). Registrar may change.'),
  ('drop_points', '8', 'Sharp quarter-to-quarter drop (points) for early warning.'),
  ('inactivity_minutes', '15', 'Desktop auto-logout.');

INSERT IGNORE INTO grading_period_locks (school_year_id, quarter, is_locked) VALUES
  (1, 1, 0), (1, 2, 0), (1, 3, 0), (1, 4, 0);

-- DepEd Order No. 8, s. 2015 Appendix 2 (commonly republished K-12 transmutation bands)
INSERT INTO transmutation_table (initial_min, initial_max, transmuted_grade) VALUES
  (100.00, 100.00, 100),
  (98.40, 99.99, 99),
  (96.80, 98.39, 98),
  (95.20, 96.79, 97),
  (93.60, 95.19, 96),
  (92.00, 93.59, 95),
  (90.40, 91.99, 94),
  (88.80, 90.39, 93),
  (87.20, 88.79, 92),
  (85.60, 87.19, 91),
  (84.00, 85.59, 90),
  (82.40, 83.99, 89),
  (80.80, 82.39, 88),
  (79.20, 80.79, 87),
  (77.60, 79.19, 86),
  (76.00, 77.59, 85),
  (74.40, 75.99, 84),
  (72.80, 74.39, 83),
  (71.20, 72.79, 82),
  (69.60, 71.19, 81),
  (68.00, 69.59, 80),
  (66.40, 67.99, 79),
  (64.80, 66.39, 78),
  (63.20, 64.79, 77),
  (61.60, 63.19, 76),
  (60.00, 61.59, 75),
  (56.00, 59.99, 74),
  (52.00, 55.99, 73),
  (48.00, 51.99, 72),
  (44.00, 47.99, 71),
  (40.00, 43.99, 70),
  (36.00, 39.99, 69),
  (32.00, 35.99, 68),
  (28.00, 31.99, 67),
  (24.00, 27.99, 66),
  (20.00, 23.99, 65),
  (16.00, 19.99, 64),
  (12.00, 15.99, 63),
  (8.00, 11.99, 62),
  (4.00, 7.99, 61),
  (0.00, 3.99, 60);

UPDATE subjects SET weight_profile_code = 'mathematics_science_academic' WHERE subject_code IN ('G11-MATH','G11-STEM-CHEM','G11-ABM-BUS','G12-ICT-PROG');
UPDATE subjects SET weight_profile_code = 'languages_humanities_communication' WHERE subject_code = 'G11-ENG';

UPDATE grade_component_weights gcw
INNER JOIN class_subject_assignments csa ON csa.id = gcw.class_subject_assignment_id
INNER JOIN subjects s ON s.id = csa.subject_id
INNER JOIN grading_weight_profiles p ON p.code = s.weight_profile_code
SET gcw.written_work_pct = p.written_work_pct,
    gcw.performance_task_pct = p.performance_task_pct,
    gcw.quarterly_assessment_pct = p.quarterly_assessment_pct;

-- Seed one WW column for STEM-A General Mathematics Q1 so the grid is not empty
INSERT INTO grade_items (class_subject_assignment_id, quarter, component_type, item_label, max_score, sort_order)
SELECT 1, 1, 'written_work', 'Quiz 1', 50, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM grade_items WHERE class_subject_assignment_id = 1 AND quarter = 1 AND item_label = 'Quiz 1');
INSERT INTO grade_items (class_subject_assignment_id, quarter, component_type, item_label, max_score, sort_order)
SELECT 1, 1, 'performance_task', 'Problem Set', 100, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM grade_items WHERE class_subject_assignment_id = 1 AND quarter = 1 AND item_label = 'Problem Set');
INSERT INTO grade_items (class_subject_assignment_id, quarter, component_type, item_label, max_score, sort_order)
SELECT 1, 1, 'quarterly_assessment', 'Q1 Exam', 50, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM grade_items WHERE class_subject_assignment_id = 1 AND quarter = 1 AND item_label = 'Q1 Exam');

INSERT INTO grade_components (grade_item_id, enrollment_id, class_subject_assignment_id, quarter, component_type, item_label, max_score, score, entry_status, recorded_at)
SELECT gi.id, 1, gi.class_subject_assignment_id, gi.quarter, gi.component_type, gi.item_label, gi.max_score,
       CASE gi.item_label WHEN 'Quiz 1' THEN 36 WHEN 'Problem Set' THEN 72 WHEN 'Q1 Exam' THEN 37 ELSE NULL END,
       'scored', NOW()
FROM grade_items gi
WHERE gi.class_subject_assignment_id = 1 AND gi.quarter = 1
  AND NOT EXISTS (
    SELECT 1 FROM grade_components gc WHERE gc.grade_item_id = gi.id AND gc.enrollment_id = 1
  );
