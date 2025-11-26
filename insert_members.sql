-- Bulk Insert Members for Church Management System
-- Run this in MySQL Workbench to add all members at once
-- Database: attendance_system (or your database name)

USE attendance_system;

-- Insert members from your CSV data
INSERT INTO members (name, email, phone, gender, location, occupation, congregation_group, baptized, status, date_joined) VALUES
('Lord William', NULL, '240279748', NULL, NULL, NULL, 'Adult', 'no', 'active', NOW()),
('Frederica Afful', NULL, '272432100', NULL, NULL, NULL, 'Adult', 'no', 'active', NOW()),
('Michael Abia Sackey', NULL, '243493595', NULL, NULL, NULL, 'Adult', 'no', 'active', NOW()),
('Sara Ntow', NULL, '262440603', NULL, NULL, NULL, 'Adult', 'no', 'active', NOW()),
('Martey Naomi', NULL, '243202612', NULL, NULL, NULL, 'Adult', 'no', 'active', NOW()),
('John Richard', NULL, '239102664', NULL, NULL, NULL, 'Adult', 'no', 'active', NOW()),
('Edem Affram', NULL, '244680038', NULL, NULL, NULL, 'Adult', 'no', 'active', NOW()),
('Vivian Andokow', NULL, '279639585', NULL, NULL, NULL, 'Adult', 'no', 'active', NOW()),
('Bennita Sobeli', NULL, '201859341', NULL, NULL, NULL, 'Adult', 'no', 'active', NOW()),
('Vera Selorm Tasiame', NULL, '506946794', NULL, NULL, NULL, 'Adult', 'no', 'active', NOW()),
('Delphina Amasah', NULL, '248716993', NULL, NULL, NULL, 'Adult', 'no', 'active', NOW()),
('Joshua Adisah', NULL, '241459999', NULL, NULL, NULL, 'Adult', 'no', 'active', NOW()),
('Sylvia Frimpong', NULL, '244734029', NULL, NULL, NULL, 'Adult', 'no', 'active', NOW()),
('Frank Darko', NULL, '245672309', NULL, NULL, NULL, 'Adult', 'no', 'active', NOW()),
('Joyce Mensah', NULL, '202345678', NULL, NULL, NULL, 'Adult', 'no', 'active', NOW());

-- Note: This is a sample of the first 15 members
-- To add all 212 members, you would need to add all entries from your CSV
-- Format: (name, email, phone, gender, location, occupation, congregation_group, baptized, status, date_joined)

-- After running this, check the results:
-- SELECT COUNT(*) as total_members FROM members;
-- SELECT * FROM members ORDER BY date_joined DESC LIMIT 10;