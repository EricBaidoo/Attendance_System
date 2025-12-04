<?php
/**
 * Secure Database Configuration
 * This file reads database credentials from environment variables or a secure config file
 */

// Try to load from environment variables first (recommended for production)
$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
$db   = $_ENV['DB_NAME'] ?? getenv('DB_NAME');
$user = $_ENV['DB_USER'] ?? getenv('DB_USER');
$pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS');

// If environment variables are not set, load from secure config file
if (!$host || !$db || !$user || !$pass) {
    $config_file = __DIR__ . '/database_config.json';
    
    if (file_exists($config_file)) {
        $config = json_decode(file_get_contents($config_file), true);
        
        // Detect environment
        $is_hosted = !($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);
        $environment = $is_hosted ? 'production' : 'development';
        
        $host = $config[$environment]['host'] ?? 'localhost';
        $db   = $config[$environment]['database'] ?? '';
        $user = $config[$environment]['username'] ?? '';
        $pass = $config[$environment]['password'] ?? '';
    } else {
        // Fallback to original method (for backward compatibility)
        $is_hosted = !($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);

        if ($is_hosted) {
            // HOSTING ENVIRONMENT - Your hosting database credentials
            $host = 'localhost';
            $db   = 'u145148023_attendance';
            $user = 'u145148023_Bmi_admin';
            $pass = 'Bmi@2025_#';
        } else {
            // LOCAL DEVELOPMENT ENVIRONMENT
            $host = 'localhost';
            $db   = 'attendance_system';
            $user = 'root';
            $pass = 'root';
        }
    }
}

$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => true,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Set timezone
    $pdo->exec("SET time_zone = '+00:00'");
    
} catch (PDOException $e) {
    // In production, log the error and show a generic message
    error_log("Database connection failed: " . $e->getMessage());
    
    // Don't expose database details in production
    if (ini_get('display_errors')) {
        throw new PDOException($e->getMessage(), (int)$e->getCode());
    } else {
        die('Database connection failed. Please contact the administrator.');
    }
}
?>