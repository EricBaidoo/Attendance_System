<?php
/**
 * Secure Session Management
 * Include this file at the top of pages that require authentication
 */

// Start secure session if not already started
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/settings_utils.php';

if (session_status() === PHP_SESSION_NONE) {
    // Set secure session configuration
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start();
}

// Session timeout (default 30 minutes)
$timeout_duration = (int)getSystemSetting($pdo, 'session_timeout', 1800);

function normalizeRole($role) {
    $role = strtolower(trim((string)$role));

    $role_map = [
        'general_admin' => 'admin',
        'admin' => 'admin',
        'data_staff' => 'staff',
        'data staff' => 'staff',
        'staff' => 'staff',
        'accountant' => 'accountant',
        'communication_team' => 'communication_team',
        'communication team' => 'communication_team',
        'communication' => 'communication_team',
    ];

    return $role_map[$role] ?? ($role !== '' ? $role : 'guest');
}

function canAccessModule($module, $role = null) {
    $role = normalizeRole($role ?? getUserRole());

    if ($role === 'admin') {
        return true;
    }

    $module = strtolower(trim((string)$module));

    if ($module === 'people_attendance') {
        return $role === 'staff';
    }

    if ($module === 'finance') {
        return $role === 'accountant';
    }

    if ($module === 'communication') {
        return $role === 'communication_team';
    }

    if ($module === 'administration') {
        return false;
    }

    return true;
}

function getAssignableRoles() {
    return [
        'data_staff' => 'Data Staff',
        'accountant' => 'Accountant',
        'communication_team' => 'Communication Team',
        'general_admin' => 'General Admin',
    ];
}

function getRoleLabel($role) {
    $role = strtolower(trim((string)$role));

    $labels = [
        'staff' => 'Data Staff',
        'data_staff' => 'Data Staff',
        'accountant' => 'Accountant',
        'communication_team' => 'Communication Team',
        'communication team' => 'Communication Team',
        'admin' => 'General Admin',
        'general_admin' => 'General Admin',
    ];

    return $labels[$role] ?? ucwords(str_replace('_', ' ', $role));
}

function getModuleLabel($module) {
    $module = strtolower(trim((string)$module));

    $labels = [
        'people_attendance' => 'People & Attendance',
        'finance' => 'Finance',
        'communication' => 'Communication',
        'administration' => 'Administration',
    ];

    return $labels[$module] ?? ucwords(str_replace('_', ' ', $module));
}

function setSystemNotice($type, $message) {
    $_SESSION['system_notice'] = [
        'type' => $type,
        'message' => $message,
    ];
}

if (!function_exists('reportDatabaseException')) {
    function reportDatabaseException(Exception $e, string $fallbackMessage = 'Database operation failed. Please try again later.'): string {
        if (function_exists('logDatabaseError')) {
            logDatabaseError($e->getMessage());
        } else {
            error_log('Database error: ' . $e->getMessage());
        }

        return $fallbackMessage;
    }
}

function pullSystemNotice() {
    if (!isset($_SESSION['system_notice']) || !is_array($_SESSION['system_notice'])) {
        return null;
    }

    $notice = $_SESSION['system_notice'];
    unset($_SESSION['system_notice']);

    return $notice;
}

function detectCurrentModule($path = null) {
    $path = strtolower((string)($path ?? parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH)));

    if (strpos($path, '/pages/finance/') !== false) {
        return 'finance';
    }

    if (strpos($path, '/pages/communication/') !== false) {
        return 'communication';
    }

    if (strpos($path, '/pages/admin/') !== false) {
        return 'administration';
    }

    $people_paths = [
        '/pages/people_attendance/',
        '/pages/members/',
        '/pages/visitors/',
        '/pages/services/',
        '/pages/attendance/',
        '/pages/checkin/',
        '/pages/reports/',
    ];

    foreach ($people_paths as $people_path) {
        if (strpos($path, $people_path) !== false) {
            return 'people_attendance';
        }
    }

    return null;
}

function getAppBasePath() {
    $script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $pages_pos = strpos($script_name, '/pages/');

    if ($pages_pos === false) {
        return '';
    }

    return rtrim(substr($script_name, 0, $pages_pos), '/');
}

function moduleScopedPageUrl($module, $target, $query = []) {
    $module = strtolower(trim((string)$module));
    $target = strtolower(trim((string)$target));

    $route_map = [
        'finance' => [
            'reports' => '/pages/finance/reports.php',
            'statement_export' => '/pages/finance/export_statement.php',
        ],
        'people_attendance' => [
            'reports' => '/pages/people_attendance/reports/report.php',
            'export' => '/pages/people_attendance/reports/export.php',
        ],
    ];

    if (!isset($route_map[$module][$target])) {
        return '#';
    }

    $base_path = getAppBasePath();
    $url = $base_path . $route_map[$module][$target];

    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    return $url;
}

function enforceCurrentModuleAccess() {
    $module = detectCurrentModule();
    if ($module === null) {
        return;
    }

    if (!canAccessModule($module)) {
        $request_path = strtolower((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
        if (strpos($request_path, '/pages/people_attendance/') !== false) {
            $home = '../../../index';
        } elseif (strpos($request_path, '/pages/') !== false) {
            $home = '../../index';
        } else {
            $home = 'index';
        }
        $role = getUserRole();
        $module_label = getModuleLabel($module);
        $role_label = getRoleLabel($role);

        $allowed_modules = [];
        foreach (['people_attendance', 'finance', 'communication', 'administration'] as $candidate_module) {
            if (canAccessModule($candidate_module, $role)) {
                $allowed_modules[] = getModuleLabel($candidate_module);
            }
        }

        $allowed_text = empty($allowed_modules)
            ? 'no modules currently'
            : implode(', ', $allowed_modules);

        setSystemNotice(
            'warning',
            "Access denied: You tried to open {$module_label}. Your role ({$role_label}) currently allows: {$allowed_text}."
        );
        header("Location: $home?access_denied=1");
        exit;
    }
}

// Check if user is logged in
function requireLogin($redirect_to = 'login') {
    if (!isset($_SESSION['user_id'])) {
        header("Location: $redirect_to");
        exit;
    }
    
    // Check session timeout
    global $timeout_duration;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        session_destroy();
        header("Location: $redirect_to?timeout=1");
        exit;
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['regenerated']) || time() - $_SESSION['regenerated'] > 300) {
        session_regenerate_id(true);
        $_SESSION['regenerated'] = time();
    }

    enforceCurrentModuleAccess();
}

// CSRF Protection
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Input sanitization
// $is_password: set to true to skip trimming and HTML escaping for password fields
function sanitizeInput($input, $is_password = false) {
    if ($is_password) {
        // Don't trim or escape passwords - they can contain special characters including spaces
        return $input;
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Validate and sanitize POST data
function validateAndSanitize($data, $rules = []) {
    $cleaned = [];
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? '';
        
        // Check if this field is a password (skip trimming/escaping)
        $is_password = isset($rule['password']) && $rule['password'];
        
        // Basic sanitization (skip for passwords)
        if (!$is_password) {
            $value = sanitizeInput($value);
        }
        
        // Required field check
        if (isset($rule['required']) && $rule['required'] && empty($value)) {
            $errors[$field] = "Field is required";
            continue;
        }
        
        // Length validation
        if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
            $errors[$field] = "Minimum length is {$rule['min_length']} characters";
        }
        
        if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
            $errors[$field] = "Maximum length is {$rule['max_length']} characters";
        }
        
        // Email validation
        if (isset($rule['email']) && $rule['email'] && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[$field] = "Invalid email format";
        }
        
        // Phone validation
        if (isset($rule['phone']) && $rule['phone'] && !preg_match('/^[\+]?[\d\s\-\(\)]+$/', $value)) {
            $errors[$field] = "Invalid phone number format";
        }
        
        $cleaned[$field] = $value;
    }
    
    return ['data' => $cleaned, 'errors' => $errors];
}

// Get user role safely
function getUserRole() {
    return normalizeRole($_SESSION['role'] ?? 'guest');
}

// Check if user has required role
function hasRole($required_roles) {
    $user_role = getUserRole();
    if (is_string($required_roles)) {
        $required_roles = [$required_roles];
    }
    return in_array($user_role, $required_roles);
}

// Require specific role
function requireRole($required_roles, $redirect_to = 'index') {
    if (!hasRole($required_roles)) {
        $role_label = getRoleLabel(getUserRole());
        setSystemNotice('warning', "Access denied: Your role ({$role_label}) is not allowed to open this page.");
        header("Location: $redirect_to?access_denied=1");
        exit;
    }
}
?>