-- =====================================================================
-- Paracale SMS - migrate uploaded documents into the database
-- ---------------------------------------------------------------------
-- 1. Run this file once against the existing database
--      mysql -u root -p paracale_sms < migration.sql
--    or through phpMyAdmin (Import tab).
-- 2. Copy documents already stored on disk into the database:
--      php migrate_documents.php
--    This reads every file referenced by documents.stored_path, stores it
--    as a LONGBLOB, verifies the copy, then deletes the old file.
-- ---------------------------------------------------------------------

ALTER TABLE documents
  ADD COLUMN mime_type VARCHAR(100) NULL AFTER file_name,
  ADD COLUMN file_size BIGINT      NULL AFTER mime_type,
  ADD COLUMN file_data LONGBLOB    NULL AFTER file_size,
  ADD COLUMN uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER stored_path;

-- After migrate_documents.php has run successfully, you can optionally
-- drop the now-unused on-disk path column:
--   ALTER TABLE documents DROP COLUMN stored_path;

-- ---------------------------------------------------------------------
-- Structured audit log for admin actions and application decisions.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  admin_id        INT NULL,
  admin_email     VARCHAR(160) NULL,
  action          VARCHAR(60) NOT NULL,
  application_id  INT NULL,
  document_id     INT NULL,
  previous_status VARCHAR(40) NULL,
  new_status      VARCHAR(40) NULL,
  remarks         VARCHAR(500) NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE SET NULL,
  FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
  INDEX (application_id),
  INDEX (document_id),
  INDEX (created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Application window settings (admin-controlled OPEN / CLOSED).
-- ---------------------------------------------------------------------
INSERT IGNORE INTO settings (skey, svalue) VALUES
  ('application_window', 'OPEN'),
  ('application_open_date', 'January 15, 2026'),
  ('application_close_date', 'December 15, 2026');

-- ---------------------------------------------------------------------
-- Section 12 - duplicate application protection.
-- Partial unique keys: the generated columns are NULL when the application
-- is rejected, so each email/phone can have at most ONE active (non-rejected)
-- application. A rejected applicant is free to apply again.
-- ---------------------------------------------------------------------
ALTER TABLE applications
  ADD COLUMN active_email VARCHAR(160)
      AS (IF(status='rejected', NULL, LOWER(email))) STORED,
  ADD COLUMN active_phone VARCHAR(20)
      AS (IF(status='rejected', NULL, phone))        STORED,
  ADD UNIQUE KEY uniq_active_email (active_email),
  ADD UNIQUE KEY uniq_active_phone (active_phone);