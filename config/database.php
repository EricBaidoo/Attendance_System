<?php
// config/database.php

// Detect if we're on hosting server or local development
$is_hosted = !(
    (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) ||
    !isset($_SERVER['HTTP_HOST']) // Command line execution
);

if ($is_hosted) {
    // HOSTING ENVIRONMENT - Your hosting database credentials
    $host = 'localhost';
    $db   = 'u420775839_bmi';
    $user = 'u420775839_bmi';
    $pass = 'Eric0056';
} else {
    // LOCAL DEVELOPMENT ENVIRONMENT
    $host = 'localhost';
    $db   = 'attendance_system';
    $user = 'root';
    $pass = 'root'; // Your XAMPP has root password set
}

$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

require_once __DIR__ . '/../includes/settings_utils.php';
?>