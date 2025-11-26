-- Simple single insert test
USE attendance_system;

-- Insert one member
INSERT INTO members (name, phone, congregation_group, baptized, status, date_joined) 
VALUES ('Lord William', '240279748', 'Adult', 'no', 'active', '2025-11-26');

-- Check the result
SELECT * FROM members;

-- Show row count
SELECT COUNT(*) as total_members FROM members;