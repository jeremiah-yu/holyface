USE holyface_amgs;

CREATE TABLE IF NOT EXISTS policy_documents (
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
  UNIQUE KEY uq_policy_ver (document_code, version),
  KEY idx_policy_current (document_code, is_current)
) ENGINE=InnoDB;

ALTER TABLE consent_logs
  ADD COLUMN IF NOT EXISTS guardian_name VARCHAR(191) NULL AFTER details,
  ADD COLUMN IF NOT EXISTS guardian_confirmed TINYINT(1) NOT NULL DEFAULT 0 AFTER guardian_name,
  ADD COLUMN IF NOT EXISTS device_info VARCHAR(255) NULL AFTER ip_address;
