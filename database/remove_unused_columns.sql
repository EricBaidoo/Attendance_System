-- Remove unnecessary columns from members table
-- Date: November 26, 2025

USE attendance_system;

-- Remove the columns that are not being used
ALTER TABLE members
DROP COLUMN emergency_contact,
DROP COLUMN emergency_phone,
DROP COLUMN baptism_date,
DROP COLUMN member_type,
DROP COLUMN profile_photo,
DROP COLUMN notes,
DROP COLUMN address;-- Show the updated table structure
DESCRIBE members;