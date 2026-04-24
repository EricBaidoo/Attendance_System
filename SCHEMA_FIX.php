<?php
require 'config/database.php';
try {
    // 1. Change category column to VARCHAR to allow any section name
    $pdo->exec("ALTER TABLE system_settings MODIFY COLUMN category VARCHAR(50)");
    echo "✅ Schema Updated.<br>";

    // 2. Move church info to branding
    $pdo->exec("UPDATE system_settings SET category = 'branding' WHERE setting_key IN ('church_name', 'church_address', 'church_phone', 'church_email')");
    echo "✅ Church Info moved to Branding.<br>";

    // 3. Ensure Logo exists
    $pdo->exec("INSERT IGNORE INTO system_settings (setting_key, setting_value, category) VALUES ('institution_logo', 'assets/images/logo.png', 'branding')");
    echo "✅ Logo setup complete.";
} catch(Exception $e) { echo "Error: " . $e->getMessage(); }
?>
