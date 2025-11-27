# Hosting Database Configuration Setup

## Issue
Database connection failed with "Access denied for user 'root'@'localhost'" because the system is using local development credentials on the hosting server.

## Solution
The `config/database.php` file has been updated to automatically detect hosting vs local environment and use appropriate credentials.

## Required Action
You need to update the hosting database credentials in `config/database.php`:

### Step 1: Get Your Hosting Database Credentials
Login to your hosting control panel (cPanel/Plesk/etc.) and find:
- Database Host (usually 'localhost')
- Database Name (created for your attendance system)
- Database Username 
- Database Password

### Step 2: Update database.php
In `config/database.php`, replace these placeholders with your actual hosting credentials:

```php
// HOSTING ENVIRONMENT - Update these with your actual hosting database credentials
$host = 'localhost'; // Or your hosting DB server
$db   = 'your_hosting_database_name';  // Replace with actual DB name
$user = 'your_hosting_username';       // Replace with actual username  
$pass = 'your_hosting_password';       // Replace with actual password
```

### Step 3: Common Hosting Database Info Locations

**cPanel:**
- Go to "MySQL Databases" section
- Database names usually formatted as: `username_dbname`
- Username usually formatted as: `username_dbuser`

**Plesk:**
- Go to "Databases" section
- Check database details

**Other hosts:**
- Check hosting documentation or contact support

### Example Configuration
```php
// Example for cPanel hosting
$host = 'localhost';
$db   = 'johnsmith_attendance_system';
$user = 'johnsmith_attendance_user';
$pass = 'YourSecurePassword123';
```

## Testing
After updating the credentials:
1. Save the file
2. Git push (webhook will auto-deploy)
3. Test your hosted site
4. Should connect successfully

## Security Note
Make sure to use a strong password and don't share your database credentials.