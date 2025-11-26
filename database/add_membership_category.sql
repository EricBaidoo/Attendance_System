-- Add congregation_group column to members table
USE church_attendance;

-- Add the new column
ALTER TABLE members ADD COLUMN congregation_group ENUM('Adult', 'Youth', 'Teen', 'Children') DEFAULT 'Adult' AFTER member_type;

-- Update existing records to have default values
UPDATE members SET congregation_group = 'Adult' WHERE congregation_group IS NULL;

-- Add some sample data for testing
UPDATE members SET congregation_group = 'Youth' WHERE id = 1;
UPDATE members SET congregation_group = 'Teen' WHERE id = 2;
UPDATE members SET congregation_group = 'Children' WHERE id = 3;