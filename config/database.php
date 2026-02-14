<?php
// config/database.php

// Improved detection: Check if the server is running locally
$local_ips = ['127.0.0.1', '::1'];
$is_local = in_array($_SERVER['REMOTE_ADDR'] ?? '', $local_ips) || ($_SERVER['SERVER_NAME'] ?? '') === 'localhost';

if (!$is_local) {
    // HOSTING ENVIRONMENT - Your online database credentials
    $host = 'localhost';
    $db   = 'u145148023_attendance';
    $user = 'u145148023_Bmi_admin';
    $pass = 'Bmi@2025_#';
} else {
    // LOCAL DEVELOPMENT ENVIRONMENT - Your local XAMPP/WAMP credentials
    $host = 'localhost';
    $db   = 'attendance_system';
    $user = 'root';
    $pass = ''; // Default XAMPP password is empty
}

$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Success! The connection is established.
} catch (\PDOException $e) {
    // If it fails, it will tell us exactly why
    die("Database Connection Error: " . $e->getMessage());
}
