<?php
/**
 * System Settings Utilities
 */

/**
 * Get a specific system setting by its key
 */
function getSystemSetting($pdo, string $key, $default = null) {
    // We clear the cache periodically to ensure freshness in the same request
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE LOWER(setting_key) = LOWER(?) LIMIT 1");
        $stmt->execute([trim($key)]); $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch (Exception $e) { return $default; }
}

/**
 * Get the institution branding name
 */
function getInstitutionName($pdo) {
    // Check in order of priority
    $keys = ['church_name', 'institution_name', 'system_name'];
    foreach ($keys as $k) {
        $val = getSystemSetting($pdo, $k);
        if ($val) return $val;
    }
    return 'Church System';
}

/**
 * Get the path to the official institution logo
 */
function getInstitutionLogo($pdo) {
    $keys = ['institution_logo', 'church_logo', 'logo_path'];
    foreach ($keys as $k) {
        $val = getSystemSetting($pdo, $k);
        if ($val) return $val;
    }
    return 'assets/images/logo.png';
}
?>
