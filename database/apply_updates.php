<?php
/**
 * Database Update Automation Script
 * 
 * This script reads and executes the cumulative SQL patches in database/db_updates.sql.
 * It is designed to be safe to run multiple times (idempotent).
 * 
 * Usage:
 * 1. Upload this file and database/db_updates.sql to your server.
 * 2. Run it via CLI: php database/apply_updates.php
 * 3. Or visit it in the browser: yourdomain.com/database/apply_updates.php
 */

set_time_limit(0);
header('Content-Type: text/plain');

try {
    // 1. Setup connection
    require_once __DIR__ . '/../config/database.php';
    
    echo "--- Bridge Ministries BMI Database Update Tool ---\n";
    echo "Connected to: " . $db . " on " . $host . "\n\n";

    // 2. Read SQL file
    $sql_file = __DIR__ . '/db_updates.sql';
    if (!file_exists($sql_file)) {
        throw new Exception("Error: db_updates.sql not found at " . $sql_file);
    }

    $sql_content = file_get_contents($sql_file);
    if ($sql_content === false) {
        throw new Exception("Error: Could not read db_updates.sql");
    }

    // 3. Simple SQL splitter (splitting by ';' while respecting common patterns)
    // Note: This is an approximation. For complex procedures, multi_query is better.
    // However, db_updates.sql uses PREPARE/EXECUTE blocks which are fine when split by ';'.
    
    $queries = preg_split("/;+(?=(?:[^']*'[^']*')*[^']*$)/", $sql_content);
    
    $total = count($queries);
    $executed = 0;
    $errors = 0;

    echo "Found " . $total . " SQL statements to process.\n";
    echo "Processing...\n\n";

    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) {
            continue;
        }

        try {
            $stmt = $pdo->query($query);
            if ($stmt) {
                $stmt->closeCursor();
            }
            $executed++;
            // Show progress every 20 queries
            if ($executed % 20 === 0) {
                echo "[CHECK] $executed / $total processed...\n";
            }
        } catch (Exception $e) {
            $errors++;
            // We ignore errors like "Column already exists" because our SQL 
            // uses information_schema checks that sometimes skip the ALTER.
            // But we log it anyway.
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "[ERROR] Query failed: " . substr($query, 0, 100) . "...\n";
                echo "        Message: " . $e->getMessage() . "\n\n";
            }
        }
    }

    echo "\n----------------------------------------\n";
    echo "COMPLETED!\n";
    echo "Statements Processed: $executed\n";
    echo "Errors encountered: $errors (Note: 'Duplicate column' errors are expected and safe)\n";
    echo "----------------------------------------\n";

} catch (Exception $e) {
    echo "\n[FATAL ERROR] " . $e->getMessage() . "\n";
}
