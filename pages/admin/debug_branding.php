<?php
require_once '../../config/database.php';
require_once '../../includes/settings_utils.php';

echo "<h2>Branding Diagnostic Tool</h2>";

// 1. Check Function Returns
echo "<h4>Current Values in Code:</h4>";
echo "getInstitutionName(): " . getInstitutionName($pdo) . "<br>";
echo "getInstitutionLogo(): " . getInstitutionLogo($pdo) . "<br>";

// 2. Dump Database table
echo "<h4>Full Identity Table Dump:</h4>";
try {
    $stmt = $pdo->query("SELECT * FROM system_settings WHERE category='branding' OR setting_key LIKE '%church%' OR setting_key LIKE '%inst%'");
    echo "<table border='1'><tr><th>Key</th><th>Value</th><th>Category</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr><td>{$row['setting_key']}</td><td>{$row['setting_value']}</td><td>{$row['category']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) { echo "DB Error: " . $e->getMessage(); }

?>
