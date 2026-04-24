<?php
require_once '../../includes/security.php';
requireLogin('../../login');
require_once '../../config/database.php';

echo "<h2>Synchronizing Branding Identity...</h2>";

try {
    // 1. Identify which keys exist
    $keys_to_fix = ['church_name', 'church_address', 'church_email', 'church_phone', 'institution_logo'];
    
    // 2. Force category to 'branding'
    $in = "'" . implode("','", $keys_to_fix) . "'";
    $pdo->exec("UPDATE system_settings SET category = 'branding' WHERE setting_key IN ($in)");
    
    // 3. Ensure they actually exist! (If they were missing entirely)
    $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, category, description) VALUES (?, ?, ?, ?)");
    $stmt->execute(['church_name', 'Bridge Ministries International', 'branding', 'The official name of the church.']);
    $stmt->execute(['church_address', 'Main St, Accra', 'branding', 'Official physical address.']);
    $stmt->execute(['institution_logo', 'assets/images/logo.png', 'branding', 'Path to logo.']);

    echo "<p style='color: green;'>✅ Logic Patched! Your church info has been moved to the Branding section.</p>";
    echo "<script>window.location.href = 'settings?group=branding';</script>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ ERROR: " . $e->getMessage() . "</p>";
}
?>
