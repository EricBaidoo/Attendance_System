<?php
/**
 * System Settings Utilities
 * Fetches and manages global configuration from the database
 */

/**
 * Get a specific system setting by its key
 * 
 * @param PDO $pdo Database connection
 * @param string $key The setting key to fetch
 * @param mixed $default Fallback value if setting is not found
 * @return mixed The setting value or default
 */
function getSystemSetting($pdo, string $key, $default = null) {
    static $settings_cache = [];
    
    if (isset($settings_cache[$key])) {
        return $settings_cache[$key];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        
        $value = $row ? $row['setting_value'] : $default;
        $settings_cache[$key] = $value;
        
        return $value;
    } catch (Exception $e) {
        error_log("Error fetching system setting ($key): " . $e->getMessage());
        return $default;
    }
}

/**
 * Fetch all settings of a specific category
 * 
 * @param PDO $pdo Database connection
 * @param string $category The category to filter by
 * @return array Key-value pairs of settings
 */
function getSettingsByCategory($pdo, string $category) {
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE category = ?");
        $stmt->execute([$category]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        error_log("Error fetching settings for category ($category): " . $e->getMessage());
        return [];
    }
}

/**
 * Get the institution branding name
 * Falls back to Bridge Ministries International if not set
 */
function getInstitutionName($pdo) {
    return getSystemSetting($pdo, 'church_name', 'Bridge Ministries International');
}
?>
