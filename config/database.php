<?php
// config/database.php

// Detect if we're on hosting server or local development
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

$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>