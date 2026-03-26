<?php
if (PHP_SAPI !== 'cli' || getenv('APP_ENABLE_MAINTENANCE_TOOLS') !== '1') {
    http_response_code(403);
    exit('Forbidden');
}

require 'config/database.php';

$sql = file_get_contents('database/db_updates.sql');

try {
    $pdo->exec($sql);
    echo "Database updates applied successfully.\n";
    exit(0);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
