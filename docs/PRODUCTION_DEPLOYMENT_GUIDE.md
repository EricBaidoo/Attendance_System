# Production Database Update Guide

## Overview
This guide helps you apply all the database changes made during the checkin system enhancement to your online/production database.

## Changes Made
1. **Members Table**: Added `phone2` column for secondary phone numbers
2. **Visitors Table**: Renamed `address` column to `location` for better consistency

## Deployment Options

### Option 1: Direct SQL Execution (Recommended)
1. **Connect to your production database** using your hosting provider's tools:
   - cPanel MySQL/phpMyAdmin
   - Hosting provider's database management interface
   - Command line access (if available)

2. **Execute the SQL script**:
   ```sql
   -- Add phone2 column to members
   ALTER TABLE members ADD COLUMN phone2 VARCHAR(20) DEFAULT NULL AFTER phone1;
   
   -- Rename address to location in visitors table
   ALTER TABLE visitors CHANGE COLUMN address location VARCHAR(255) DEFAULT NULL;
   ```

3. **Verify the changes**:
   ```sql
   DESCRIBE members;
   DESCRIBE visitors;
   ```

### Option 2: Using the SQL File
1. Upload `production_updates.sql` to your server
2. Execute via command line or hosting interface:
   ```bash
   mysql -u username -p database_name < production_updates.sql
   ```

### Option 3: phpMyAdmin/Web Interface
1. Log into your hosting provider's database interface
2. Navigate to the SQL tab
3. Copy and paste the SQL commands from `production_updates.sql`
4. Execute the commands

## Verification Steps
After applying the changes, verify:

1. **Members table has phone2 column**:
   ```sql
   SHOW COLUMNS FROM members LIKE 'phone2';
   ```

2. **Visitors table has location column (not address)**:
   ```sql
   SHOW COLUMNS FROM visitors LIKE 'location';
   ```

3. **Test the checkin system** to ensure it works properly

## Rollback Plan (If Needed)
If something goes wrong, you can rollback:

```sql
-- Remove phone2 from members (if needed)
ALTER TABLE members DROP COLUMN phone2;

-- Change location back to address in visitors (if needed)
ALTER TABLE visitors CHANGE COLUMN location address VARCHAR(255) DEFAULT NULL;
```

## Important Notes
- **Backup your database** before making any changes
- Test in a staging environment first if possible
- The checkin system requires both changes to work properly
- Contact your hosting provider if you need help accessing the database

## Files Updated
- `pages/checkin/checkin.php` - Enhanced checkin system
- Database schema changes as outlined above