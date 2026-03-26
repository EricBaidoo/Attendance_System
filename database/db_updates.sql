-- Bridge Ministries International - Attendance System
-- Cumulative Database Update Script
--
-- Purpose:
--   Keep all schema/data update SQL in one place.
--   Run this file on local/online environments whenever needed.
--
-- Usage:
--   1) Backup database first.
--   2) Run the full file (idempotent blocks are used where possible).
--   3) For each new DB change, APPEND a new dated section at the bottom.


-- =========================================================
-- [2026-03-12] Members enhancements + multi-department + cell centers
-- =========================================================

-- 1) Add marital_status to members (if missing)
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE members ADD COLUMN marital_status VARCHAR(20) NULL DEFAULT NULL',
    'SELECT "members.marital_status already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'members'
    AND COLUMN_NAME = 'marital_status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =========================================================
-- [2026-03-23] Communication campaigns status enum expansion
-- =========================================================
-- Adds in-progress status for long-running SMS batch workflows.
-- Safe/idempotent: only modifies enum if 'sending' is not already present.

SET @campaign_status_type = (
  SELECT COLUMN_TYPE
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'communication_campaigns'
    AND COLUMN_NAME = 'status'
  LIMIT 1
);

SET @sql = (
  SELECT IF(
    @campaign_status_type IS NULL,
    'SELECT "communication_campaigns.status column not found"',
    IF(
      @campaign_status_type LIKE '%''sending''%',
      'SELECT "communication_campaigns.status already supports sending"',
      "ALTER TABLE communication_campaigns MODIFY COLUMN status ENUM('draft','scheduled','sending','sent','cancelled') NOT NULL DEFAULT 'draft'"
    )
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =========================================================
-- [2026-03-19] Communication module base schema
-- =========================================================
-- Supports announcements, campaigns, and delivery logs.

CREATE TABLE IF NOT EXISTS communication_announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  audience VARCHAR(120) NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  publish_date DATETIME NULL,
  expires_at DATETIME NULL,
  created_by_user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_comm_ann_status (status),
  INDEX idx_comm_ann_publish_date (publish_date),
  INDEX idx_comm_ann_created_by (created_by_user_id)
);

SET @has_fk_comm_ann_user = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'communication_announcements'
    AND CONSTRAINT_NAME = 'fk_comm_ann_created_by'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql = (
  SELECT IF(
    @has_fk_comm_ann_user > 0,
    'SELECT "fk_comm_ann_created_by already exists"',
    'ALTER TABLE communication_announcements ADD CONSTRAINT fk_comm_ann_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS communication_campaigns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  channel ENUM('sms','email','whatsapp','announcement','other') NOT NULL DEFAULT 'sms',
  audience VARCHAR(120) NULL,
  content TEXT NOT NULL,
  status ENUM('draft','scheduled','sent','cancelled') NOT NULL DEFAULT 'draft',
  scheduled_at DATETIME NULL,
  sent_at DATETIME NULL,
  total_recipients INT NOT NULL DEFAULT 0,
  delivered_count INT NOT NULL DEFAULT 0,
  failed_count INT NOT NULL DEFAULT 0,
  created_by_user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_comm_campaign_channel (channel),
  INDEX idx_comm_campaign_status (status),
  INDEX idx_comm_campaign_scheduled (scheduled_at),
  INDEX idx_comm_campaign_created_by (created_by_user_id)
);

SET @has_fk_comm_campaign_user = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'communication_campaigns'
    AND CONSTRAINT_NAME = 'fk_comm_campaign_created_by'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql = (
  SELECT IF(
    @has_fk_comm_campaign_user > 0,
    'SELECT "fk_comm_campaign_created_by already exists"',
    'ALTER TABLE communication_campaigns ADD CONSTRAINT fk_comm_campaign_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS communication_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  campaign_id INT NULL,
  channel ENUM('sms','email','whatsapp','announcement','other') NOT NULL,
  recipient VARCHAR(190) NULL,
  status ENUM('queued','sent','delivered','failed') NOT NULL DEFAULT 'queued',
  message_preview VARCHAR(255) NULL,
  error_detail VARCHAR(255) NULL,
  sent_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_comm_log_campaign (campaign_id),
  INDEX idx_comm_log_channel (channel),
  INDEX idx_comm_log_status (status),
  INDEX idx_comm_log_sent_at (sent_at)
);

SET @has_fk_comm_logs_campaign = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'communication_logs'
    AND CONSTRAINT_NAME = 'fk_comm_logs_campaign'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql = (
  SELECT IF(
    @has_fk_comm_logs_campaign > 0,
    'SELECT "fk_comm_logs_campaign already exists"',
    'ALTER TABLE communication_logs ADD CONSTRAINT fk_comm_logs_campaign FOREIGN KEY (campaign_id) REFERENCES communication_campaigns(id) ON DELETE SET NULL'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Create member_departments (many-to-many)
CREATE TABLE IF NOT EXISTS member_departments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  department_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_member_dept (member_id, department_id),
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- 3) Backfill existing single department data into member_departments
INSERT IGNORE INTO member_departments (member_id, department_id)
SELECT id, department_id
FROM members
WHERE department_id IS NOT NULL;

-- 4) Create cell_centers table
CREATE TABLE IF NOT EXISTS cell_centers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5) Seed default cell centers
INSERT INTO cell_centers (name)
SELECT 'Cell Center 1' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cell_centers WHERE name = 'Cell Center 1');

INSERT INTO cell_centers (name)
SELECT 'Cell Center 2' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cell_centers WHERE name = 'Cell Center 2');

INSERT INTO cell_centers (name)
SELECT 'Cell Center 3' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cell_centers WHERE name = 'Cell Center 3');

-- 6) Add role_in_church to members (if missing)
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE members ADD COLUMN role_in_church VARCHAR(150) NULL DEFAULT NULL',
    'SELECT "members.role_in_church already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'members'
    AND COLUMN_NAME = 'role_in_church'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 7) Add cell_center_id to members (if missing)
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE members ADD COLUMN cell_center_id INT NULL DEFAULT NULL',
    'SELECT "members.cell_center_id already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'members'
    AND COLUMN_NAME = 'cell_center_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 8) Add FK members.cell_center_id -> cell_centers.id (if missing)
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE members ADD CONSTRAINT fk_member_cell_center FOREIGN KEY (cell_center_id) REFERENCES cell_centers(id) ON DELETE SET NULL',
    'SELECT "fk_member_cell_center already exists"'
  )
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'members'
    AND CONSTRAINT_NAME = 'fk_member_cell_center'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Note:
--   Primary/Highest-priority department uses existing members.department_id.
--   All selected departments are stored in member_departments.


-- =========================================================
-- [2026-03-14] Visitor/Convert status normalization cleanup
-- =========================================================
-- Goal:
--   Normalize legacy status values so workflow logic is consistent across:
--   visitors, new_converts, members conversion flows and reporting filters.
--
-- Safe properties:
--   - Data-only updates (no schema changes)
--   - Transaction wrapped
--   - Idempotent (re-running keeps same final state)

-- Preview current distribution before updates
SELECT 'visitors_status_before' AS label, COALESCE(status, '(NULL)') AS status_value, COUNT(*) AS total
FROM visitors
GROUP BY COALESCE(status, '(NULL)')
ORDER BY total DESC;

SELECT 'new_converts_status_before' AS label, COALESCE(status, '(NULL)') AS status_value, COUNT(*) AS total
FROM new_converts
GROUP BY COALESCE(status, '(NULL)')
ORDER BY total DESC;

START TRANSACTION;

-- 1) Standardize legacy visitor status 'converted' -> 'converted_to_member'
UPDATE visitors
SET status = 'converted_to_member'
WHERE status = 'converted';

-- 2) If visitor is marked as became_member, status should be converted_to_member
UPDATE visitors
SET status = 'converted_to_member'
WHERE became_member = 'yes'
  AND COALESCE(status, '') <> 'converted_to_member';

-- 3) Ensure converted_to_member visitors have became_member = 'yes'
UPDATE visitors
SET became_member = 'yes'
WHERE status = 'converted_to_member'
  AND COALESCE(became_member, 'no') <> 'yes';

-- 4) Backfill converted_date for member-converted visitors when empty
UPDATE visitors
SET converted_date = CURDATE()
WHERE status = 'converted_to_member'
  AND converted_date IS NULL;

-- 5) Normalize new_converts status based on member conversion date
UPDATE new_converts
SET status = 'converted_to_member'
WHERE member_conversion_date IS NOT NULL
  AND COALESCE(status, '') <> 'converted_to_member';

UPDATE new_converts
SET status = 'active'
WHERE (status IS NULL OR status = '')
  AND member_conversion_date IS NULL;

-- 6) If a new_convert is promoted to member and linked visitor exists,
--    mirror normalized member conversion state into visitors
UPDATE visitors v
JOIN new_converts nc ON nc.visitor_id = v.id
SET v.status = 'converted_to_member',
    v.became_member = 'yes',
    v.converted_date = COALESCE(v.converted_date, nc.member_conversion_date, CURDATE())
WHERE nc.status = 'converted_to_member';

COMMIT;

-- Verification after updates
SELECT 'visitors_status_after' AS label, COALESCE(status, '(NULL)') AS status_value, COUNT(*) AS total
FROM visitors
GROUP BY COALESCE(status, '(NULL)')
ORDER BY total DESC;

SELECT 'new_converts_status_after' AS label, COALESCE(status, '(NULL)') AS status_value, COUNT(*) AS total
FROM new_converts
GROUP BY COALESCE(status, '(NULL)')
ORDER BY total DESC;


-- =========================================================
-- [2026-03-15] Lifecycle schema alignment (notes + conversion fields)
-- =========================================================
-- Ensures conversion workflow pages work on older databases.

-- visitors.notes
-- NOTE: run this script on the target database (do not hardcode USE <db_name>).

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE visitors ADD COLUMN notes TEXT NULL',
    'SELECT "visitors.notes already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'visitors'
    AND COLUMN_NAME = 'notes'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- visitors.converted_date
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE visitors ADD COLUMN converted_date DATE NULL',
    'SELECT "visitors.converted_date already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'visitors'
    AND COLUMN_NAME = 'converted_date'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- visitors.became_member
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    "ALTER TABLE visitors ADD COLUMN became_member ENUM('yes','no') NOT NULL DEFAULT 'no'",
    'SELECT "visitors.became_member already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'visitors'
    AND COLUMN_NAME = 'became_member'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- new_converts.notes
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE new_converts ADD COLUMN notes TEXT NULL',
    'SELECT "new_converts.notes already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'new_converts'
    AND COLUMN_NAME = 'notes'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- new_converts.member_conversion_date
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE new_converts ADD COLUMN member_conversion_date DATE NULL',
    'SELECT "new_converts.member_conversion_date already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'new_converts'
    AND COLUMN_NAME = 'member_conversion_date'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- members.notes
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE members ADD COLUMN notes TEXT NULL',
    'SELECT "members.notes already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'members'
    AND COLUMN_NAME = 'notes'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- visitors.created_at
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE visitors ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP',
    'SELECT "visitors.created_at already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'visitors'
    AND COLUMN_NAME = 'created_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- new_converts.created_at
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE new_converts ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP',
    'SELECT "new_converts.created_at already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'new_converts'
    AND COLUMN_NAME = 'created_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- members.created_at
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE members ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP',
    'SELECT "members.created_at already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'members'
    AND COLUMN_NAME = 'created_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =========================================================
-- [2026-03-15] Status-column compatibility hardening
-- =========================================================
-- Prevent enum truncation warnings when workflow status values evolve.

-- visitors.status -> VARCHAR(50) (if currently enum)
SET @is_enum = (
  SELECT CASE
    WHEN LOWER(DATA_TYPE) = 'enum' THEN 1
    ELSE 0
  END
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'visitors'
    AND COLUMN_NAME = 'status'
  LIMIT 1
);

SET @sql = IF(
  IFNULL(@is_enum, 0) = 1,
  'ALTER TABLE visitors MODIFY COLUMN status VARCHAR(50) NULL',
  'SELECT "visitors.status already compatible"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- new_converts.status -> VARCHAR(50) (if currently enum)
SET @is_enum = (
  SELECT CASE
    WHEN LOWER(DATA_TYPE) = 'enum' THEN 1
    ELSE 0
  END
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'new_converts'
    AND COLUMN_NAME = 'status'
  LIMIT 1
);

SET @sql = IF(
  IFNULL(@is_enum, 0) = 1,
  'ALTER TABLE new_converts MODIFY COLUMN status VARCHAR(50) NULL',
  'SELECT "new_converts.status already compatible"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =========================================================
-- [2026-03-15] Unified people foundation (modular monolith)
-- =========================================================
-- Goal:
--   Introduce a single source-of-truth `people` table while keeping
--   existing visitors/new_converts/members pages operational.
--
-- Strategy:
--   1) Create core people + lifecycle tables
--   2) Add nullable person_id links to legacy tables
--   3) Seed people from members/new_converts/visitors (dedupe by email/phone)
--   4) Backfill person_id on legacy records
--   5) Seed lifecycle events for journey analytics

-- 1) Core table: people
CREATE TABLE IF NOT EXISTS people (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NULL,
  phone VARCHAR(30) NULL,
  alt_phone VARCHAR(30) NULL,
  gender VARCHAR(20) NULL,
  date_of_birth DATE NULL,
  location VARCHAR(255) NULL,
  occupation VARCHAR(150) NULL,
  current_stage VARCHAR(50) NOT NULL DEFAULT 'visitor',
  first_seen_at DATETIME NULL,
  last_seen_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_people_name (full_name),
  INDEX idx_people_email (email),
  INDEX idx_people_phone (phone),
  INDEX idx_people_stage (current_stage)
);

-- 2) Lifecycle timeline table
CREATE TABLE IF NOT EXISTS person_lifecycle_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  person_id INT NOT NULL,
  event_type VARCHAR(50) NOT NULL,
  event_date DATETIME NOT NULL,
  source_table VARCHAR(50) NULL,
  source_id INT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lifecycle_person (person_id),
  INDEX idx_lifecycle_type (event_type),
  INDEX idx_lifecycle_date (event_date),
  CONSTRAINT fk_lifecycle_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE
);

-- 3) Add person_id columns to legacy tables if missing
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE visitors ADD COLUMN person_id INT NULL',
    'SELECT "visitors.person_id already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'visitors'
    AND COLUMN_NAME = 'person_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE new_converts ADD COLUMN person_id INT NULL',
    'SELECT "new_converts.person_id already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'new_converts'
    AND COLUMN_NAME = 'person_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE members ADD COLUMN person_id INT NULL',
    'SELECT "members.person_id already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'members'
    AND COLUMN_NAME = 'person_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Add indexes for faster joins (idempotent)
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE visitors ADD INDEX idx_visitors_person_id (person_id)',
    'SELECT "idx_visitors_person_id already exists"'
  )
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'visitors'
    AND INDEX_NAME = 'idx_visitors_person_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE new_converts ADD INDEX idx_new_converts_person_id (person_id)',
    'SELECT "idx_new_converts_person_id already exists"'
  )
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'new_converts'
    AND INDEX_NAME = 'idx_new_converts_person_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE members ADD INDEX idx_members_person_id (person_id)',
    'SELECT "idx_members_person_id already exists"'
  )
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'members'
    AND INDEX_NAME = 'idx_members_person_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) Seed people from members first (highest maturity stage)
INSERT INTO people (
  full_name, email, phone, alt_phone, gender, date_of_birth,
  location, occupation, current_stage, first_seen_at, last_seen_at
)
SELECT
  m.name,
  NULLIF(TRIM(m.email), ''),
  NULLIF(TRIM(m.phone), ''),
  NULLIF(TRIM(m.phone2), ''),
  NULLIF(TRIM(m.gender), ''),
  m.dob,
  NULLIF(TRIM(m.location), ''),
  NULLIF(TRIM(m.occupation), ''),
  'member',
  COALESCE(m.date_joined, m.created_at, NOW()),
  COALESCE(m.created_at, NOW())
FROM members m
WHERE COALESCE(TRIM(m.name), '') <> ''
  AND NOT EXISTS (
    SELECT 1
    FROM people p
    WHERE (
      NULLIF(TRIM(m.email), '') IS NOT NULL
      AND LOWER(TRIM(p.email)) = LOWER(TRIM(m.email))
    )
    OR (
      NULLIF(TRIM(m.phone), '') IS NOT NULL
      AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(m.phone), '[^0-9]', '')
    )
  );

-- 6) Seed people from new_converts if not already present
INSERT INTO people (
  full_name, email, phone, current_stage, first_seen_at, last_seen_at
)
SELECT
  nc.name,
  NULLIF(TRIM(nc.email), ''),
  NULLIF(TRIM(nc.phone), ''),
  CASE
    WHEN COALESCE(nc.status, '') = 'converted_to_member' THEN 'member'
    ELSE 'new_convert'
  END,
  COALESCE(nc.date_converted, nc.created_at, NOW()),
  COALESCE(nc.created_at, NOW())
FROM new_converts nc
WHERE COALESCE(TRIM(nc.name), '') <> ''
  AND NOT EXISTS (
    SELECT 1
    FROM people p
    WHERE (
      NULLIF(TRIM(nc.email), '') IS NOT NULL
      AND LOWER(TRIM(p.email)) = LOWER(TRIM(nc.email))
    )
    OR (
      NULLIF(TRIM(nc.phone), '') IS NOT NULL
      AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(nc.phone), '[^0-9]', '')
    )
  );

-- 7) Seed people from visitors if not already present
INSERT INTO people (
  full_name, email, phone, current_stage, first_seen_at, last_seen_at
)
SELECT
  v.name,
  NULLIF(TRIM(v.email), ''),
  NULLIF(TRIM(v.phone), ''),
  CASE
    WHEN COALESCE(v.status, '') IN ('converted_to_member', 'converted') OR COALESCE(v.became_member, 'no') = 'yes' THEN 'member'
    WHEN COALESCE(v.status, '') = 'converted_to_convert' THEN 'new_convert'
    ELSE 'visitor'
  END,
  COALESCE(v.created_at, v.date, NOW()),
  COALESCE(v.created_at, v.date, NOW())
FROM visitors v
WHERE COALESCE(TRIM(v.name), '') <> ''
  AND NOT EXISTS (
    SELECT 1
    FROM people p
    WHERE (
      NULLIF(TRIM(v.email), '') IS NOT NULL
      AND LOWER(TRIM(p.email)) = LOWER(TRIM(v.email))
    )
    OR (
      NULLIF(TRIM(v.phone), '') IS NOT NULL
      AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(v.phone), '[^0-9]', '')
    )
  );

-- 8) Backfill members.person_id
UPDATE members m
SET m.person_id = (
  SELECT p.id
  FROM people p
  WHERE (
      NULLIF(TRIM(m.email), '') IS NOT NULL
      AND LOWER(TRIM(p.email)) = LOWER(TRIM(m.email))
    )
    OR (
      NULLIF(TRIM(m.phone), '') IS NOT NULL
      AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(m.phone), '[^0-9]', '')
    )
    OR (
      COALESCE(TRIM(m.name), '') <> ''
      AND LOWER(TRIM(p.full_name)) = LOWER(TRIM(m.name))
    )
  ORDER BY CASE
      WHEN NULLIF(TRIM(m.email), '') IS NOT NULL AND LOWER(TRIM(p.email)) = LOWER(TRIM(m.email)) THEN 1
      WHEN NULLIF(TRIM(m.phone), '') IS NOT NULL AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(m.phone), '[^0-9]', '') THEN 2
      ELSE 3
    END,
    p.id
  LIMIT 1
)
WHERE m.person_id IS NULL;

-- 9) Backfill new_converts.person_id
UPDATE new_converts nc
SET nc.person_id = (
  SELECT p.id
  FROM people p
  WHERE (
      NULLIF(TRIM(nc.email), '') IS NOT NULL
      AND LOWER(TRIM(p.email)) = LOWER(TRIM(nc.email))
    )
    OR (
      NULLIF(TRIM(nc.phone), '') IS NOT NULL
      AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(nc.phone), '[^0-9]', '')
    )
    OR (
      COALESCE(TRIM(nc.name), '') <> ''
      AND LOWER(TRIM(p.full_name)) = LOWER(TRIM(nc.name))
    )
  ORDER BY CASE
      WHEN NULLIF(TRIM(nc.email), '') IS NOT NULL AND LOWER(TRIM(p.email)) = LOWER(TRIM(nc.email)) THEN 1
      WHEN NULLIF(TRIM(nc.phone), '') IS NOT NULL AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(nc.phone), '[^0-9]', '') THEN 2
      ELSE 3
    END,
    p.id
  LIMIT 1
)
WHERE nc.person_id IS NULL;

-- 10) Backfill visitors.person_id
UPDATE visitors v
SET v.person_id = (
  SELECT p.id
  FROM people p
  WHERE (
      NULLIF(TRIM(v.email), '') IS NOT NULL
      AND LOWER(TRIM(p.email)) = LOWER(TRIM(v.email))
    )
    OR (
      NULLIF(TRIM(v.phone), '') IS NOT NULL
      AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(v.phone), '[^0-9]', '')
    )
    OR (
      COALESCE(TRIM(v.name), '') <> ''
      AND LOWER(TRIM(p.full_name)) = LOWER(TRIM(v.name))
    )
  ORDER BY CASE
      WHEN NULLIF(TRIM(v.email), '') IS NOT NULL AND LOWER(TRIM(p.email)) = LOWER(TRIM(v.email)) THEN 1
      WHEN NULLIF(TRIM(v.phone), '') IS NOT NULL AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(v.phone), '[^0-9]', '') THEN 2
      ELSE 3
    END,
    p.id
  LIMIT 1
)
WHERE v.person_id IS NULL;

-- 11) Stage upgrade from legacy data (never downgrade stages)
UPDATE people p
JOIN members m ON m.person_id = p.id
SET p.current_stage = 'member'
WHERE p.current_stage <> 'member';

UPDATE people p
JOIN new_converts nc ON nc.person_id = p.id
SET p.current_stage = CASE
    WHEN p.current_stage = 'member' THEN 'member'
    WHEN COALESCE(nc.status, '') = 'converted_to_member' THEN 'member'
    ELSE 'new_convert'
  END
WHERE p.current_stage IN ('visitor', 'new_convert', 'member');

UPDATE people p
JOIN visitors v ON v.person_id = p.id
SET p.current_stage = CASE
    WHEN p.current_stage = 'member' THEN 'member'
    WHEN COALESCE(v.status, '') IN ('converted_to_member', 'converted') OR COALESCE(v.became_member, 'no') = 'yes' THEN 'member'
    WHEN p.current_stage = 'new_convert' OR COALESCE(v.status, '') = 'converted_to_convert' THEN 'new_convert'
    ELSE 'visitor'
  END
WHERE p.current_stage IN ('visitor', 'new_convert', 'member');

-- 12) Seed lifecycle events (idempotent via source tuple)
INSERT INTO person_lifecycle_events (person_id, event_type, event_date, source_table, source_id, notes)
SELECT
  v.person_id,
  'visitor_checked_in',
  COALESCE(v.created_at, v.date, NOW()),
  'visitors',
  v.id,
  'Imported from visitors table'
FROM visitors v
WHERE v.person_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM person_lifecycle_events e
    WHERE e.source_table = 'visitors'
      AND e.source_id = v.id
      AND e.event_type = 'visitor_checked_in'
  );

INSERT INTO person_lifecycle_events (person_id, event_type, event_date, source_table, source_id, notes)
SELECT
  nc.person_id,
  'became_new_convert',
  COALESCE(nc.date_converted, nc.created_at, NOW()),
  'new_converts',
  nc.id,
  'Imported from new_converts table'
FROM new_converts nc
WHERE nc.person_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM person_lifecycle_events e
    WHERE e.source_table = 'new_converts'
      AND e.source_id = nc.id
      AND e.event_type = 'became_new_convert'
  );

INSERT INTO person_lifecycle_events (person_id, event_type, event_date, source_table, source_id, notes)
SELECT
  m.person_id,
  'became_member',
  COALESCE(m.date_joined, m.created_at, NOW()),
  'members',
  m.id,
  'Imported from members table'
FROM members m
WHERE m.person_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM person_lifecycle_events e
    WHERE e.source_table = 'members'
      AND e.source_id = m.id
      AND e.event_type = 'became_member'
  );

-- 13) Optional foreign keys for person_id links (only if absent)
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE visitors ADD CONSTRAINT fk_visitors_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE SET NULL',
    'SELECT "fk_visitors_person already exists"'
  )
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'visitors'
    AND CONSTRAINT_NAME = 'fk_visitors_person'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE new_converts ADD CONSTRAINT fk_new_converts_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE SET NULL',
    'SELECT "fk_new_converts_person already exists"'
  )
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'new_converts'
    AND CONSTRAINT_NAME = 'fk_new_converts_person'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE members ADD CONSTRAINT fk_members_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE SET NULL',
    'SELECT "fk_members_person already exists"'
  )
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'members'
    AND CONSTRAINT_NAME = 'fk_members_person'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =========================================================
-- [2026-03-15] Collation normalization for people tables
-- =========================================================
-- Fixes ERROR 1267 (Illegal mix of collations) that occurs when
-- people/person_lifecycle_events are created with utf8mb4_unicode_ci
-- while legacy tables use utf8mb4_0900_ai_ci.
-- Idempotent: ALTER TABLE CONVERT TO CHARACTER SET is safe to re-run.

ALTER TABLE people
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

ALTER TABLE person_lifecycle_events
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

-- Also normalize new_converts which may have been created with unicode_ci
-- on some MySQL versions / hosting environments.
ALTER TABLE new_converts
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

-- Re-run all three backfills in case collation mismatch caused them to
-- produce zero matches above (safe: WHERE person_id IS NULL = no-op if done).

-- Re-backfill members.person_id
UPDATE members m
SET m.person_id = (
  SELECT p.id
  FROM people p
  WHERE (
      NULLIF(TRIM(m.email), '') IS NOT NULL
      AND LOWER(TRIM(p.email)) = LOWER(TRIM(m.email))
    )
    OR (
      NULLIF(TRIM(m.phone), '') IS NOT NULL
      AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(m.phone), '[^0-9]', '')
    )
    OR (
      COALESCE(TRIM(m.name), '') <> ''
      AND LOWER(TRIM(p.full_name)) = LOWER(TRIM(m.name))
    )
  ORDER BY CASE
      WHEN NULLIF(TRIM(m.email), '') IS NOT NULL AND LOWER(TRIM(p.email)) = LOWER(TRIM(m.email)) THEN 1
      WHEN NULLIF(TRIM(m.phone), '') IS NOT NULL AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(m.phone), '[^0-9]', '') THEN 2
      ELSE 3
    END, p.id
  LIMIT 1
)
WHERE m.person_id IS NULL;

-- Re-backfill new_converts.person_id
UPDATE new_converts nc
SET nc.person_id = (
  SELECT p.id
  FROM people p
  WHERE (
      NULLIF(TRIM(nc.email), '') IS NOT NULL
      AND LOWER(TRIM(p.email)) = LOWER(TRIM(nc.email))
    )
    OR (
      NULLIF(TRIM(nc.phone), '') IS NOT NULL
      AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(nc.phone), '[^0-9]', '')
    )
    OR (
      COALESCE(TRIM(nc.name), '') <> ''
      AND LOWER(TRIM(p.full_name)) = LOWER(TRIM(nc.name))
    )
  ORDER BY CASE
      WHEN NULLIF(TRIM(nc.email), '') IS NOT NULL AND LOWER(TRIM(p.email)) = LOWER(TRIM(nc.email)) THEN 1
      WHEN NULLIF(TRIM(nc.phone), '') IS NOT NULL AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(nc.phone), '[^0-9]', '') THEN 2
      ELSE 3
    END, p.id
  LIMIT 1
)
WHERE nc.person_id IS NULL;

-- Re-backfill visitors.person_id
UPDATE visitors v
SET v.person_id = (
  SELECT p.id
  FROM people p
  WHERE (
      NULLIF(TRIM(v.email), '') IS NOT NULL
      AND LOWER(TRIM(p.email)) = LOWER(TRIM(v.email))
    )
    OR (
      NULLIF(TRIM(v.phone), '') IS NOT NULL
      AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(v.phone), '[^0-9]', '')
    )
    OR (
      COALESCE(TRIM(v.name), '') <> ''
      AND LOWER(TRIM(p.full_name)) = LOWER(TRIM(v.name))
    )
  ORDER BY CASE
      WHEN NULLIF(TRIM(v.email), '') IS NOT NULL AND LOWER(TRIM(p.email)) = LOWER(TRIM(v.email)) THEN 1
      WHEN NULLIF(TRIM(v.phone), '') IS NOT NULL AND REGEXP_REPLACE(TRIM(p.phone), '[^0-9]', '') = REGEXP_REPLACE(TRIM(v.phone), '[^0-9]', '') THEN 2
      ELSE 3
    END, p.id
  LIMIT 1
)
WHERE v.person_id IS NULL;

-- Re-seed any missing lifecycle events after collation fix
INSERT INTO person_lifecycle_events (person_id, event_type, event_date, source_table, source_id, notes)
SELECT v.person_id, 'visitor_checked_in', COALESCE(v.created_at, v.date, NOW()), 'visitors', v.id, 'Imported from visitors table'
FROM visitors v
WHERE v.person_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM person_lifecycle_events e
    WHERE e.source_table = 'visitors' AND e.source_id = v.id AND e.event_type = 'visitor_checked_in'
  );

INSERT INTO person_lifecycle_events (person_id, event_type, event_date, source_table, source_id, notes)
SELECT nc.person_id, 'became_new_convert', COALESCE(nc.date_converted, nc.created_at, NOW()), 'new_converts', nc.id, 'Imported from new_converts table'
FROM new_converts nc
WHERE nc.person_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM person_lifecycle_events e
    WHERE e.source_table = 'new_converts' AND e.source_id = nc.id AND e.event_type = 'became_new_convert'
  );

INSERT INTO person_lifecycle_events (person_id, event_type, event_date, source_table, source_id, notes)
SELECT m.person_id, 'became_member', COALESCE(m.date_joined, m.created_at, NOW()), 'members', m.id, 'Imported from members table'
FROM members m
WHERE m.person_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM person_lifecycle_events e
    WHERE e.source_table = 'members' AND e.source_id = m.id AND e.event_type = 'became_member'
  );


-- =========================================================
-- [2026-03-23] Enforce unique identity keys on people
-- =========================================================
-- Goal:
--   Enforce uniqueness for phone/email in the canonical people table
--   using normalized keys. Unique indexes are added only when existing
--   duplicates are fully resolved.

-- Add identity key columns if missing
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE people ADD COLUMN email_key VARCHAR(150) NULL',
    'SELECT "people.email_key already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'people'
    AND COLUMN_NAME = 'email_key'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE people ADD COLUMN phone_key VARCHAR(30) NULL',
    'SELECT "people.phone_key already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'people'
    AND COLUMN_NAME = 'phone_key'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill normalized keys
UPDATE people
SET email_key = CASE
      WHEN NULLIF(TRIM(email), '') IS NULL THEN NULL
      ELSE LOWER(TRIM(email))
    END,
    phone_key = CASE
      WHEN NULLIF(TRIM(phone), '') IS NULL THEN NULL
      ELSE REGEXP_REPLACE(TRIM(phone), '[^0-9]', '')
    END;

-- Count duplicate key groups
SET @email_dup_groups = (
  SELECT COUNT(*)
  FROM (
    SELECT email_key
    FROM people
    WHERE email_key IS NOT NULL AND email_key <> ''
    GROUP BY email_key
    HAVING COUNT(*) > 1
  ) e
);

SET @phone_dup_groups = (
  SELECT COUNT(*)
  FROM (
    SELECT phone_key
    FROM people
    WHERE phone_key IS NOT NULL AND phone_key <> ''
    GROUP BY phone_key
    HAVING COUNT(*) > 1
  ) p
);

-- Add unique index for email_key only if no duplicates remain
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'people' AND INDEX_NAME = 'uq_people_email_key') = 0,
    IF(@email_dup_groups = 0,
      'ALTER TABLE people ADD UNIQUE INDEX uq_people_email_key (email_key)',
      'SELECT "Skip uq_people_email_key: resolve duplicate email keys first"'
    ),
    'SELECT "uq_people_email_key already exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add unique index for phone_key only if no duplicates remain
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'people' AND INDEX_NAME = 'uq_people_phone_key') = 0,
    IF(@phone_dup_groups = 0,
      'ALTER TABLE people ADD UNIQUE INDEX uq_people_phone_key (phone_key)',
      'SELECT "Skip uq_people_phone_key: resolve duplicate phone keys first"'
    ),
    'SELECT "uq_people_phone_key already exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =========================================================
-- [2026-03-15] Visitor status consistency fix
-- =========================================================
-- A visitor who has already been converted to a new convert
-- must not remain in 'pending' status. Fix any such rows by
-- aligning their status to the highest stage their person_id
-- has reached.

-- Visitors whose person is linked to a new_convert (but not yet a member)
-- should be 'converted_to_convert'
UPDATE visitors v
SET v.status = 'converted_to_convert'
WHERE v.status = 'pending'
  AND v.person_id IS NOT NULL
  AND EXISTS (
    SELECT 1 FROM new_converts nc WHERE nc.person_id = v.person_id
  )
  AND NOT EXISTS (
    SELECT 1 FROM members m WHERE m.person_id = v.person_id
  );

-- Visitors whose person is linked to a member should be 'converted_to_member'
UPDATE visitors v
SET v.status = 'converted_to_member',
    v.became_member = 'yes'
WHERE v.status IN ('pending', 'converted_to_convert')
  AND v.person_id IS NOT NULL
  AND EXISTS (
    SELECT 1 FROM members m WHERE m.person_id = v.person_id
  );


-- =========================================================
-- [2026-03-18] Finance module base schema
-- =========================================================
-- Creates a unified transactions table for income and expenses.

CREATE TABLE IF NOT EXISTS finance_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transaction_date DATE NOT NULL,
  type ENUM('income','expense') NOT NULL,
  category VARCHAR(100) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_method VARCHAR(50) NULL,
  reference_no VARCHAR(100) NULL,
  description TEXT NULL,
  recorded_by_user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_finance_type_date (type, transaction_date),
  INDEX idx_finance_date (transaction_date),
  INDEX idx_finance_category (category),
  INDEX idx_finance_recorded_by (recorded_by_user_id)
);

-- Add FK only if users table exists and FK is not present already.
SET @has_users_table = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
);

SET @has_fk_finance_user = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'finance_transactions'
    AND CONSTRAINT_NAME = 'fk_finance_recorded_by_user'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql = (
  SELECT IF(
    @has_users_table = 0,
    'SELECT "users table not found; skip finance FK"',
    IF(
      @has_fk_finance_user > 0,
      'SELECT "fk_finance_recorded_by_user already exists"',
      'ALTER TABLE finance_transactions ADD CONSTRAINT fk_finance_recorded_by_user FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE SET NULL'
    )
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =========================================================
-- [2026-03-18] Tithers and tithe books tracking
-- =========================================================
CREATE TABLE IF NOT EXISTS tithe_books (
  id INT AUTO_INCREMENT PRIMARY KEY,
  book_number VARCHAR(50) NOT NULL,
  issued_date DATE NULL,
  status ENUM('available','assigned','retired') NOT NULL DEFAULT 'available',
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tithe_books_book_number (book_number),
  INDEX idx_tithe_books_status (status)
);

CREATE TABLE IF NOT EXISTS tithers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NULL,
  full_name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(120) NULL,
  tithe_book_id INT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  start_date DATE NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tithers_member (member_id),
  INDEX idx_tithers_book (tithe_book_id),
  INDEX idx_tithers_status (status)
);

SET @has_fk_tithers_member = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tithers'
    AND CONSTRAINT_NAME = 'fk_tithers_member'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql = (
  SELECT IF(
    @has_fk_tithers_member > 0,
    'SELECT "fk_tithers_member already exists"',
    'ALTER TABLE tithers ADD CONSTRAINT fk_tithers_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_fk_tithers_book = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tithers'
    AND CONSTRAINT_NAME = 'fk_tithers_book'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql = (
  SELECT IF(
    @has_fk_tithers_book > 0,
    'SELECT "fk_tithers_book already exists"',
    'ALTER TABLE tithers ADD CONSTRAINT fk_tithers_book FOREIGN KEY (tithe_book_id) REFERENCES tithe_books(id) ON DELETE SET NULL'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_uq_tithers_book = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tithers'
    AND INDEX_NAME = 'uq_tithers_tithe_book_id'
);

SET @has_duplicate_tither_book = (
  SELECT COUNT(*)
  FROM (
    SELECT tithe_book_id
    FROM tithers
    WHERE tithe_book_id IS NOT NULL
    GROUP BY tithe_book_id
    HAVING COUNT(*) > 1
  ) dup
);

SET @sql = (
  SELECT IF(
    @has_uq_tithers_book > 0,
    'SELECT "uq_tithers_tithe_book_id already exists"',
    IF(
      @has_duplicate_tither_book > 0,
      'SELECT "Cannot add uq_tithers_tithe_book_id yet: duplicate tithe_book_id values exist in tithers"',
      'ALTER TABLE tithers ADD UNIQUE KEY uq_tithers_tithe_book_id (tithe_book_id)'
    )
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE finance_transactions ADD COLUMN tither_id INT NULL AFTER recorded_by_user_id',
    'SELECT "finance_transactions.tither_id already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'finance_transactions'
    AND COLUMN_NAME = 'tither_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE finance_transactions ADD COLUMN tithe_book_id INT NULL AFTER tither_id',
    'SELECT "finance_transactions.tithe_book_id already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'finance_transactions'
    AND COLUMN_NAME = 'tithe_book_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE finance_transactions ADD INDEX idx_finance_tither (tither_id)',
    'SELECT "idx_finance_tither already exists"'
  )
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'finance_transactions'
    AND INDEX_NAME = 'idx_finance_tither'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE finance_transactions ADD INDEX idx_finance_tithe_book (tithe_book_id)',
    'SELECT "idx_finance_tithe_book already exists"'
  )
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'finance_transactions'
    AND INDEX_NAME = 'idx_finance_tithe_book'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_fk_finance_tither = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'finance_transactions'
    AND CONSTRAINT_NAME = 'fk_finance_tither'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql = (
  SELECT IF(
    @has_fk_finance_tither > 0,
    'SELECT "fk_finance_tither already exists"',
    'ALTER TABLE finance_transactions ADD CONSTRAINT fk_finance_tither FOREIGN KEY (tither_id) REFERENCES tithers(id) ON DELETE SET NULL'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_fk_finance_tithe_book = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'finance_transactions'
    AND CONSTRAINT_NAME = 'fk_finance_tithe_book'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql = (
  SELECT IF(
    @has_fk_finance_tithe_book > 0,
    'SELECT "fk_finance_tithe_book already exists"',
    'ALTER TABLE finance_transactions ADD CONSTRAINT fk_finance_tithe_book FOREIGN KEY (tithe_book_id) REFERENCES tithe_books(id) ON DELETE SET NULL'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =========================================================
-- [2026-03-19] Tithe month tracking for SMS receipts
-- =========================================================
-- Adds the paid_for_month field used to record which month a tithe payment covers.

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE finance_transactions ADD COLUMN paid_for_month DATE NULL AFTER tithe_book_id',
    'SELECT "finance_transactions.paid_for_month already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'finance_transactions'
    AND COLUMN_NAME = 'paid_for_month'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE finance_transactions ADD INDEX idx_finance_paid_for_month (paid_for_month)',
    'SELECT "idx_finance_paid_for_month already exists"'
  )
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'finance_transactions'
    AND INDEX_NAME = 'idx_finance_paid_for_month'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE finance_transactions ADD COLUMN sms_provider_code VARCHAR(20) NULL AFTER paid_for_month',
    'SELECT "finance_transactions.sms_provider_code already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'finance_transactions'
    AND COLUMN_NAME = 'sms_provider_code'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE finance_transactions ADD COLUMN sms_campaign_id VARCHAR(100) NULL AFTER sms_provider_code',
    'SELECT "finance_transactions.sms_campaign_id already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'finance_transactions'
    AND COLUMN_NAME = 'sms_campaign_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE finance_transactions ADD COLUMN sms_status VARCHAR(20) NULL AFTER sms_campaign_id',
    'SELECT "finance_transactions.sms_status already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'finance_transactions'
    AND COLUMN_NAME = 'sms_status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE finance_transactions ADD COLUMN sms_error VARCHAR(255) NULL AFTER sms_status',
    'SELECT "finance_transactions.sms_error already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'finance_transactions'
    AND COLUMN_NAME = 'sms_error'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE finance_transactions ADD COLUMN sms_sent_at DATETIME NULL AFTER sms_error',
    'SELECT "finance_transactions.sms_sent_at already exists"'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'finance_transactions'
    AND COLUMN_NAME = 'sms_sent_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =========================================================
-- [2026-03-18] Users role enum expansion for module access
-- =========================================================
-- Fixes SQLSTATE[01000]: 1265 Data truncated for users.role when saving
-- role values such as data_staff/accountant/communication_team/general_admin.

SET @users_role_type = (
  SELECT COLUMN_TYPE
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'role'
  LIMIT 1
);

SET @sql = (
  SELECT IF(
    @users_role_type IS NULL,
    'SELECT "users.role column not found"',
    IF(
      @users_role_type LIKE '%general_admin%'
      AND @users_role_type LIKE '%data_staff%'
      AND @users_role_type LIKE '%accountant%'
      AND @users_role_type LIKE '%communication_team%',
      'SELECT "users.role enum already supports module roles"',
      "ALTER TABLE users MODIFY COLUMN role ENUM('admin','staff','general_admin','data_staff','accountant','communication_team') NOT NULL DEFAULT 'data_staff'"
    )
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
