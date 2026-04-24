<?php
require 'config/database.php';

echo "<h2>Executing Permanent Branding Alignment...</h2>";

try {
    // 1. Move existing keys to branding category
    $keys = ['church_name', 'church_address', 'church_phone', 'church_email'];
    $pdo->exec("UPDATE system_settings SET category = 'branding' WHERE setting_key IN ('" . implode("','", $keys) . "')");

    // 2. Ensure institution_logo EXISTS
    $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, category, description) VALUES (?, ?, ?, ?)");
    $stmt->execute(['institution_logo', 'assets/images/logo.png', 'branding', 'The official institution logo path.']);

    // 3. Double Check church_name value
    $check = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='church_name'")->fetchColumn();
    echo "Current Church Name in DB: <b>" . ($check ?: 'NOT SET') . "</b><br>";
    
    echo "<p style='color: green;'>✅ DATABASE ALIGNED. All info moved to Branding section.</p>";
} catch (Exception $e) { echo "Error: " . $e->getMessage(); }
?>
