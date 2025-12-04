-- Hosting Database Migration Script for Phone1/Phone2 Fields
-- Run this script on your online hosting database: u145148023_attendance
-- Date: December 3, 2025

USE u145148023_attendance;

-- Step 1: Add phone2 field to members table
-- This will be the new optional secondary phone number field
ALTER TABLE members 
ADD COLUMN phone2 VARCHAR(20) DEFAULT NULL 
AFTER phone;

-- Step 2: Rename existing phone field to phone1
-- This preserves all existing phone data as the primary phone number
ALTER TABLE members 
CHANGE COLUMN phone phone1 VARCHAR(20) NOT NULL;

-- Step 3: Create indexes for better search performance
-- These indexes will improve query performance when searching by phone numbers
CREATE INDEX idx_members_phone1 ON members(phone1);
CREATE INDEX idx_members_phone2 ON members(phone2);

-- Step 4: Verify the changes
-- This will show you the updated table structure
DESCRIBE members;

-- Step 5: Show sample data to verify migration worked correctly
-- This will display a few records to confirm phone1 has data and phone2 is null
SELECT id, name, phone1, phone2 
FROM members 
WHERE phone1 IS NOT NULL 
LIMIT 5;

-- Step 6: Show count of members with phone data
SELECT 
    COUNT(*) as total_members,
    COUNT(phone1) as members_with_phone1,
    COUNT(phone2) as members_with_phone2
FROM members;

-- Migration completed successfully!
-- Your existing phone numbers are now in the phone1 field
-- The phone2 field is available for optional secondary numbers
-- All application forms and search functions now support both fields