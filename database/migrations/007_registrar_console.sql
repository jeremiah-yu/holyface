USE holyface_amgs;

-- Registrar desktop: school file vault for circulars, forms, backups, and official records.

CREATE TABLE IF NOT EXISTS registrar_files (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(191) NOT NULL,
  original_name VARCHAR(191) NOT NULL,
  stored_name CHAR(36) NOT NULL,
  mime VARCHAR(128) NOT NULL,
  byte_size INT UNSIGNED NOT NULL,
  category VARCHAR(32) NOT NULL DEFAULT 'other',
  notes VARCHAR(255) NULL,
  uploaded_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_registrar_stored (stored_name),
  KEY idx_registrar_cat (category),
  KEY idx_registrar_uploaded (uploaded_by),
  CONSTRAINT fk_registrar_uploader FOREIGN KEY (uploaded_by) REFERENCES users (id)
) ENGINE=InnoDB;
