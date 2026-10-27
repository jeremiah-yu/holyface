USE holyface_amgs;

CREATE TABLE IF NOT EXISTS official_report_cards (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
