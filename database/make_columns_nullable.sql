-- Database update to make all columns except name and phone accept NULL
-- Date: November 26, 2025

USE attendance_system;

-- Make all columns except name and phone nullable
ALTER TABLE members 
MODIFY COLUMN email VARCHAR(100) NULL,
MODIFY COLUMN gender ENUM('male', 'female', 'other') NULL,
MODIFY COLUMN dob DATE NULL,
MODIFY COLUMN location VARCHAR(150) NULL,
MODIFY COLUMN occupation VARCHAR(100) NULL,
MODIFY COLUMN department_id INT NULL,
MODIFY COLUMN congregation_group ENUM('Adult', 'Youth', 'Teen', 'Children') NULL DEFAULT 'Adult',
MODIFY COLUMN baptized ENUM('yes', 'no') NULL DEFAULT 'no',
MODIFY COLUMN date_joined DATE NULL DEFAULT (CURRENT_DATE),
MODIFY COLUMN status ENUM('active', 'inactive') NULL DEFAULT 'active';

-- Ensure name and phone are still required (NOT NULL)
ALTER TABLE members 
MODIFY COLUMN name VARCHAR(100) NOT NULL,
MODIFY COLUMN phone VARCHAR(20) NOT NULL;

-- Update any existing records that might have empty strings to NULL
UPDATE members SET 
    email = NULLIF(email, ''),
    gender = NULLIF(gender, ''),
    location = NULLIF(location, ''),
    occupation = NULLIF(occupation, '');

-- Show the updated table structure
DESCRIBE members;