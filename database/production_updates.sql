-- Production Database Update Script
-- Date: December 4, 2025
-- Description: Database changes for checkin system enhancement and phone field consistency

-- ==========================================
-- SECTION 1: INSPECTION QUERIES (Run first to check current structure)
-- ==========================================

-- Check current members table structure
DESCRIBE members;

-- Check current visitors table structure  
DESCRIBE visitors;

-- Note: Check the DESCRIBE results above to see if phone2 and location columns already exist
-- If they exist, you can skip Section 2

-- ==========================================
-- SECTION 2: DATABASE UPDATES (Run after reviewing inspection results)
-- ==========================================

-- Add phone2 column to members table 
-- EXPECTED ERROR: If you get "#1060 - Duplicate column name 'phone2'" - this means the column already exists (GOOD!)
ALTER TABLE members 
ADD COLUMN phone2 VARCHAR(20) DEFAULT NULL;

-- Add location column to visitors table
-- EXPECTED ERROR: If you get "#1060 - Duplicate column name 'location'" - this means the column already exists (GOOD!)
ALTER TABLE visitors 
ADD COLUMN location VARCHAR(255) DEFAULT NULL;

-- ==========================================
-- SECTION 3: VERIFICATION (Run after updates to confirm changes)
-- ==========================================

-- Verify members table structure
DESCRIBE members;

-- Verify visitors table structure
DESCRIBE visitors;

-- Check data counts
SELECT 
    'Members' as table_name,
    COUNT(*) as total_records,
    SUM(CASE WHEN phone IS NOT NULL THEN 1 ELSE 0 END) as with_phone,
    SUM(CASE WHEN phone2 IS NOT NULL THEN 1 ELSE 0 END) as with_phone2
FROM members
UNION ALL
SELECT 
    'Visitors' as table_name,
    COUNT(*) as total_records,
    SUM(CASE WHEN phone IS NOT NULL THEN 1 ELSE 0 END) as with_phone,
    SUM(CASE WHEN location IS NOT NULL THEN 1 ELSE 0 END) as with_location
FROM visitors;

-- End of script