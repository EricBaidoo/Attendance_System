-- Add phone2 field to members and visitors tables
-- Run this script to add support for second phone number

USE attendance_system;

-- Add phone2 field to members table
ALTER TABLE members 
ADD COLUMN phone2 VARCHAR(20) DEFAULT NULL AFTER phone;

-- Update existing phone field to be more descriptive
ALTER TABLE members 
CHANGE COLUMN phone phone1 VARCHAR(20) NOT NULL;

-- Add phone2 field to visitors table (if it doesn't exist already)
-- First check if visitors table has phone field
DESCRIBE visitors;

-- Add phone fields to visitors table if they don't exist
-- Note: Adjust this based on your current visitors table structure
-- ALTER TABLE visitors ADD COLUMN phone1 VARCHAR(20) DEFAULT NULL;
-- ALTER TABLE visitors ADD COLUMN phone2 VARCHAR(20) DEFAULT NULL;

-- Create index for better search performance
CREATE INDEX idx_members_phone1 ON members(phone1);
CREATE INDEX idx_members_phone2 ON members(phone2);

-- Show updated structure
DESCRIBE members;