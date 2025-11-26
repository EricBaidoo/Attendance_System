<?php
/**
 * Database Configuration for Hosting Environment
 * 
 * INSTRUCTIONS FOR HOSTING SETUP:
 * 1. Create a database in your hosting control panel (cPanel/Plesk/etc)
 * 2. Note down the database name, username, password, and host
 * 3. Update the values below with your hosting database details
 * 4. Upload this file to your hosting server
 */

// =============================================================================
// HOSTING DATABASE CONFIGURATION
// =============================================================================
// Update these values with your hosting database details

$host = 'localhost';                    // Usually 'localhost' for shared hosting
$dbname = 'your_database_name';         // Database name from hosting control panel
$username = 'your_db_username';         // Database username from hosting
$password = 'your_db_password';         // Database password from hosting

// =============================================================================
// PDO CONNECTION SETUP
// =============================================================================

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
    
} catch (PDOException $e) {
    // Log error and show user-friendly message
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please check your configuration.");
}

// =============================================================================
// HOSTING ENVIRONMENT NOTES:
// =============================================================================
/*
COMMON HOSTING DATABASE SETUPS:

1. SHARED HOSTING (cPanel):
   - Host: localhost
   - Database name: username_dbname
   - Username: username_dbuser
   - Password: your_password

2. CLOUD HOSTING:
   - Host: database-server.host.com
   - Database name: attendance_system
   - Username: db_user
   - Password: secure_password

3. VPS/DEDICATED:
   - Host: localhost or IP address
   - Database name: attendance_system
   - Username: root or custom user
   - Password: your_secure_password

STEPS TO DEPLOY:
1. Upload all project files to your hosting
2. Import the complete_hosting_setup.sql file into your database
3. Update this config file with correct database details
4. Test the login page: yourdomain.com/login.php
5. Default login: admin / admin123 (change immediately!)
*/
?>