-- Test Insert to diagnose the issue
USE attendance_system;

-- First, let's check the table structure
DESCRIBE members;

-- Try inserting just one record to test
INSERT INTO members (name, phone, congregation_group, baptized, status, date_joined) VALUES
('Test Member', '123456789', 'Adult', 'no', 'active', CURDATE());

-- Check if it worked
SELECT * FROM members WHERE name = 'Test Member';

-- Show any errors or warnings
SHOW WARNINGS;