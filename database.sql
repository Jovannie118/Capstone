-- =====================================================================
-- Paracale Scholarship Management System - MySQL schema + seed data
-- Import with:  mysql -u root -p < database.sql
-- or through phpMyAdmin (Import tab).
-- =====================================================================

DROP DATABASE IF EXISTS paracale_sms;
CREATE DATABASE paracale_sms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE paracale_sms;

-- ---------------------------------------------------------------------
-- Admin / staff accounts
-- ---------------------------------------------------------------------
CREATE TABLE admins (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(120) NOT NULL,
  email         VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Demo login:  admin@paracale.edu  /  paracale2026
INSERT INTO admins (full_name, email, password_hash) VALUES
  ('Paracale Scholarship Office', 'admin@paracale.edu',
   '$2y$10$j17sDW.KeHRjD59bUuIU2OFIWQQ7Yud7o99GLK3rk.KBJevD2PdXm');

-- ---------------------------------------------------------------------
-- Applications
-- ---------------------------------------------------------------------
CREATE TABLE applications (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  ref_code     VARCHAR(24) NOT NULL UNIQUE,          -- e.g. SCH-2026-1001
  full_name    VARCHAR(120) NOT NULL,
  email        VARCHAR(160) NOT NULL,
  address      VARCHAR(255) NOT NULL,
  school       VARCHAR(160) NOT NULL,
  status       ENUM('submitted','under_verification','ranked','approved','waitlisted','rejected')
               NOT NULL DEFAULT 'submitted',
  gpa              DECIMAL(3,2) NOT NULL DEFAULT 3.40,
  household_income INT          NOT NULL DEFAULT 24000,
  extracurricular  TINYINT      NOT NULL DEFAULT 6,   -- 0-10
  essay_score      TINYINT      NOT NULL DEFAULT 7,   -- 0-10
  requested        INT          NOT NULL DEFAULT 4000,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (email),
  INDEX (status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Uploaded documents (4 required types per application)
-- ---------------------------------------------------------------------
CREATE TABLE documents (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  doc_type       ENUM('Registration Form','School ID with 3 Signatures',
                      'Barangay Indigency','Certificate of Grade (COG)') NOT NULL,
  file_name      VARCHAR(255) NOT NULL,
  stored_path    VARCHAR(255) NULL,
  status         ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  note           VARCHAR(255) NULL,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  INDEX (application_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Timeline (per-application audit trail)
-- ---------------------------------------------------------------------
CREATE TABLE timeline (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  label          VARCHAR(255) NOT NULL,
  actor          VARCHAR(120) NOT NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Notifications (system-wide activity log)
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(160) NOT NULL,
  body       TEXT NOT NULL,
  kind       ENUM('info','success','warning') NOT NULL DEFAULT 'info',
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Settings (single row: cycle budget)
-- ---------------------------------------------------------------------
CREATE TABLE settings (
  skey  VARCHAR(40) PRIMARY KEY,
  svalue VARCHAR(120) NOT NULL
) ENGINE=InnoDB;

INSERT INTO settings (skey, svalue) VALUES ('budget', '250000');

-- =====================================================================
-- Demo data
-- =====================================================================
INSERT INTO applications
  (ref_code, full_name, email, address, school, status, gpa, household_income, extracurricular, essay_score, requested)
VALUES
  ('SCH-2026-1001','Amara Okafor','amara.okafor@mail.edu','12 Rizal St, Paracale','Paracale National High School','ranked',3.80,14000,8,9,4200),
  ('SCH-2026-1002','Liam Chen','liam.chen@mail.edu','8 Mabini Ave, Paracale','Camarines Norte State College','under_verification',3.20,32000,5,6,3800),
  ('SCH-2026-1003','Sofia Marino','sofia.marino@mail.edu','44 Bonifacio St, Paracale','Paracale National High School','approved',3.95,11000,9,8,5000),
  ('SCH-2026-1004','Noah Bekele','noah.bekele@mail.edu','7 Luna St, Paracale','Mother Francisca Academy','submitted',2.90,41000,4,5,3000),
  ('SCH-2026-1005','Priya Raman','priya.raman@mail.edu','21 Del Pilar St, Paracale','Camarines Norte State College','ranked',3.60,19000,7,7,4500),
  ('SCH-2026-1006','Grace Mwangi','grace.mwangi@mail.edu','3 Aguinaldo St, Paracale','Paracale National High School','rejected',2.60,52000,3,4,2800);

-- Documents: verified for 1001/1003, mixed for the rest
INSERT INTO documents (application_id, doc_type, file_name, status, note) VALUES
  (1,'Registration Form','registration-form.pdf','verified',NULL),
  (1,'School ID with 3 Signatures','school-id.jpg','verified',NULL),
  (1,'Barangay Indigency','barangay-indigency.pdf','verified',NULL),
  (1,'Certificate of Grade (COG)','cog.pdf','verified',NULL),

  (2,'Registration Form','registration-form.pdf','verified',NULL),
  (2,'School ID with 3 Signatures','school-id.jpg','rejected','Scan unreadable - please re-upload.'),
  (2,'Barangay Indigency','barangay-indigency.pdf','pending',NULL),
  (2,'Certificate of Grade (COG)','cog.pdf','pending',NULL),

  (3,'Registration Form','registration-form.pdf','verified',NULL),
  (3,'School ID with 3 Signatures','school-id.jpg','verified',NULL),
  (3,'Barangay Indigency','barangay-indigency.pdf','verified',NULL),
  (3,'Certificate of Grade (COG)','cog.pdf','verified',NULL),

  (4,'Registration Form','registration-form.pdf','pending',NULL),
  (4,'School ID with 3 Signatures','school-id.jpg','pending',NULL),
  (4,'Barangay Indigency','barangay-indigency.pdf','pending',NULL),
  (4,'Certificate of Grade (COG)','cog.pdf','pending',NULL),

  (5,'Registration Form','registration-form.pdf','verified',NULL),
  (5,'School ID with 3 Signatures','school-id.jpg','verified',NULL),
  (5,'Barangay Indigency','barangay-indigency.pdf','verified',NULL),
  (5,'Certificate of Grade (COG)','cog.pdf','verified',NULL),

  (6,'Registration Form','registration-form.pdf','rejected','Incomplete form.'),
  (6,'School ID with 3 Signatures','school-id.jpg','pending',NULL),
  (6,'Barangay Indigency','barangay-indigency.pdf','pending',NULL),
  (6,'Certificate of Grade (COG)','cog.pdf','pending',NULL);

INSERT INTO timeline (application_id, label, actor) VALUES
  (1,'Application submitted','Amara Okafor'),
  (1,'All documents verified','Verification Officer'),
  (2,'Application submitted','Liam Chen'),
  (2,'Document School ID with 3 Signatures marked rejected','Verification Officer'),
  (3,'Application submitted','Sofia Marino'),
  (3,'Approved - award granted','Review Committee'),
  (4,'Application submitted','Noah Bekele'),
  (5,'Application submitted','Priya Raman'),
  (6,'Application submitted','Grace Mwangi'),
  (6,'Rejected - incomplete requirements','Review Committee');

INSERT INTO notifications (title, body, kind) VALUES
  ('Cycle open','The 2026/27 Paracale scholarship cycle accepts applications until 31 August.','info'),
  ('Documents rejected','2 applicants must re-upload unreadable documents.','warning'),
  ('SCH-2026-1003 approved','Sofia Marino has been granted a scholarship award.','success');
