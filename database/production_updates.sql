-- Production Database Update Script
-- Date: December 4, 2025
-- Description: All database changes made during checkin system enhancement

-- 1. Add phone2 column to members table (if not exists)
ALTER TABLE members 
ADD COLUMN IF NOT EXISTS phone2 VARCHAR(20) DEFAULT NULL AFTER phone1;

-- Alternative for MySQL versions that don't support IF NOT EXISTS:
-- ALTER TABLE members ADD COLUMN phone2 VARCHAR(20) DEFAULT NULL AFTER phone1;

-- 2. Change address column to location in visitors table
-- First check if address column exists, then rename it
ALTER TABLE visitors 
CHANGE COLUMN address location VARCHAR(255) DEFAULT NULL;

-- Alternative: If the column doesn't exist, add it
-- ALTER TABLE visitors ADD COLUMN location VARCHAR(255) DEFAULT NULL AFTER phone;

-- 3. Verify the changes
SELECT 'Members table structure:' as info;
DESCRIBE members;

SELECT 'Visitors table structure:' as info;
DESCRIBE visitors;

-- 4. Check for any data integrity issues
SELECT 'Members with phone2 data:' as info;
SELECT COUNT(*) as count FROM members WHERE phone2 IS NOT NULL;

SELECT 'Visitors with location data:' as info;
SELECT COUNT(*) as count FROM visitors WHERE location IS NOT NULL;

-- End of script