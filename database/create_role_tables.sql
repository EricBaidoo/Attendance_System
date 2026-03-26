-- =========================================================
-- [2026-03-23] Role-specific attribute tables
-- =========================================================
-- Normalize away from monolithic member/visitor/new_converts tables.
-- Create semantic role tables that extend canonical people with role-specific metadata.
-- All writes continue through people_sync; reads now use role tables.

-- =========================================================
-- 1. member_roles: Member-specific attributes
-- =========================================================

CREATE TABLE IF NOT EXISTS member_roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  person_id INT NOT NULL UNIQUE,
  gender VARCHAR(20) NULL,
  date_of_birth DATE NULL,
  marital_status VARCHAR(20) NULL,
  location VARCHAR(255) NULL,
  occupation VARCHAR(150) NULL,
  department_id INT NULL,
  congregation_group VARCHAR(50) NOT NULL DEFAULT 'Adult',
  baptized ENUM('yes','no') NOT NULL DEFAULT 'no',
  ministerial_status VARCHAR(100) NULL,
  role_in_church VARCHAR(150) NULL,
  cell_center_id INT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  date_joined DATE NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_member_role_person_id (person_id),
  INDEX idx_member_role_status (status),
  INDEX idx_member_role_department (department_id),
  CONSTRAINT fk_member_role_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE,
  CONSTRAINT fk_member_role_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
  CONSTRAINT fk_member_role_cell_center FOREIGN KEY (cell_center_id) REFERENCES cell_centers(id) ON DELETE SET NULL
);

-- =========================================================
-- 2. visitor_roles: Visitor-specific attributes
-- =========================================================

CREATE TABLE IF NOT EXISTS visitor_roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  person_id INT NOT NULL UNIQUE,
  service_id INT NULL,
  first_time ENUM('yes','no') NOT NULL DEFAULT 'yes',
  how_heard VARCHAR(100) NULL,
  invited_by VARCHAR(200) NULL,
  follow_up_needed ENUM('yes','no') NOT NULL DEFAULT 'no',
  follow_up_completed ENUM('yes','no') NOT NULL DEFAULT 'no',
  follow_up_date DATETIME NULL,
  location VARCHAR(255) NULL,
  became_member ENUM('yes','no') NOT NULL DEFAULT 'no',
  converted_date DATE NULL,
  status ENUM('pending','contacted','converted_to_member','converted_to_new_convert') NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  date DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_visitor_role_person_id (person_id),
  INDEX idx_visitor_role_service (service_id),
  INDEX idx_visitor_role_status (status),
  INDEX idx_visitor_role_date (date),
  CONSTRAINT fk_visitor_role_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE,
  CONSTRAINT fk_visitor_role_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
);

-- =========================================================
-- 3. new_convert_roles: New convert-specific attributes
-- =========================================================

CREATE TABLE IF NOT EXISTS new_convert_roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  person_id INT NOT NULL UNIQUE,
  department_id INT NULL,
  status ENUM('active','converted_to_member','inactive') NOT NULL DEFAULT 'active',
  date_converted DATE NULL,
  member_conversion_date DATE NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_new_convert_role_person_id (person_id),
  INDEX idx_new_convert_role_status (status),
  INDEX idx_new_convert_role_department (department_id),
  CONSTRAINT fk_new_convert_role_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE,
  CONSTRAINT fk_new_convert_role_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- =========================================================
-- 4. Migrate data from legacy tables to role tables
-- =========================================================

-- Migrate members -> member_roles
INSERT INTO member_roles (
  person_id, gender, date_of_birth, marital_status, location, occupation,
  department_id, congregation_group, baptized, ministerial_status, role_in_church,
  cell_center_id, status, date_joined, notes, created_at, updated_at
)
SELECT
  m.person_id, m.gender, m.dob, m.marital_status, m.location, m.occupation,
  m.department_id, COALESCE(m.congregation_group, 'Adult'), COALESCE(m.baptized, 'no'),
  m.ministerial_status, m.role_in_church, m.cell_center_id, m.status, m.date_joined,
  m.notes, m.created_at, NOW()
FROM members m
WHERE m.person_id IS NOT NULL AND m.person_id > 0
  AND NOT EXISTS (SELECT 1 FROM member_roles mr WHERE mr.person_id = m.person_id)
ON DUPLICATE KEY UPDATE
  gender = VALUES(gender),
  date_of_birth = VALUES(date_of_birth),
  marital_status = VALUES(marital_status),
  location = VALUES(location),
  occupation = VALUES(occupation),
  department_id = VALUES(department_id),
  congregation_group = VALUES(congregation_group),
  baptized = VALUES(baptized),
  ministerial_status = VALUES(ministerial_status),
  role_in_church = VALUES(role_in_church),
  cell_center_id = VALUES(cell_center_id),
  status = VALUES(status),
  date_joined = VALUES(date_joined),
  notes = VALUES(notes),
  updated_at = NOW();

-- Migrate visitors -> visitor_roles
INSERT INTO visitor_roles (
  person_id, service_id, first_time, how_heard, invited_by, follow_up_needed,
  follow_up_completed, follow_up_date, location, became_member, converted_date,
  status, notes, date, created_at, updated_at
)
SELECT
  v.person_id, v.service_id, COALESCE(v.first_time, 'yes'), v.how_heard, v.invited_by,
  COALESCE(v.follow_up_needed, 'no'), COALESCE(v.follow_up_completed, 'no'), v.follow_up_date,
  v.location, COALESCE(v.became_member, 'no'), v.converted_date, COALESCE(v.status, 'pending'),
  v.notes, v.date, v.created_at, NOW()
FROM visitors v
WHERE v.person_id IS NOT NULL AND v.person_id > 0
  AND NOT EXISTS (SELECT 1 FROM visitor_roles vr WHERE vr.person_id = v.person_id)
ON DUPLICATE KEY UPDATE
  service_id = VALUES(service_id),
  first_time = VALUES(first_time),
  how_heard = VALUES(how_heard),
  invited_by = VALUES(invited_by),
  follow_up_needed = VALUES(follow_up_needed),
  follow_up_completed = VALUES(follow_up_completed),
  follow_up_date = VALUES(follow_up_date),
  location = VALUES(location),
  became_member = VALUES(became_member),
  converted_date = VALUES(converted_date),
  status = VALUES(status),
  notes = VALUES(notes),
  date = VALUES(date),
  updated_at = NOW();

-- Migrate new_converts -> new_convert_roles
INSERT INTO new_convert_roles (
  person_id, department_id, status, date_converted, member_conversion_date, notes, created_at, updated_at
)
SELECT
  nc.person_id, nc.department_id, COALESCE(nc.status, 'active'), nc.date_converted,
  nc.member_conversion_date, nc.notes, nc.created_at, NOW()
FROM new_converts nc
WHERE nc.person_id IS NOT NULL AND nc.person_id > 0
  AND NOT EXISTS (SELECT 1 FROM new_convert_roles nr WHERE nr.person_id = nc.person_id)
ON DUPLICATE KEY UPDATE
  department_id = VALUES(department_id),
  status = VALUES(status),
  date_converted = VALUES(date_converted),
  member_conversion_date = VALUES(member_conversion_date),
  notes = VALUES(notes),
  updated_at = NOW();

-- =========================================================
-- 5. Create many-to-many for member departments
-- =========================================================
-- Already exists, but ensure it's linked to role tables if needed
-- (keeping for multi-department support)
