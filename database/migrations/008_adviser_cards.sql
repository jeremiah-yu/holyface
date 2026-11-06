USE holyface_amgs;

ALTER TABLE official_report_cards
  ADD COLUMN released_at DATETIME NULL AFTER issued_at,
  ADD COLUMN released_by INT UNSIGNED NULL AFTER released_at,
  ADD COLUMN generated_by INT UNSIGNED NULL AFTER released_by,
  ADD COLUMN honor_label VARCHAR(64) NULL AFTER remarks,
  ADD COLUMN section_rank SMALLINT UNSIGNED NULL AFTER honor_label;
