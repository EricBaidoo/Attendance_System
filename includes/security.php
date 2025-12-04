<?php
/**
 * Secure Session Management
 * Include this file at the top of pages that require authentication
 */

// Start secure session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session configuration
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start();
}

// Session timeout (30 minutes)
$timeout_duration = 1800;

// Check if user is logged in
function requireLogin($redirect_to = 'login.php') {
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
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Validate and sanitize POST data
function validateAndSanitize($data, $rules = []) {
    $cleaned = [];
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? '';
        
        // Basic sanitization
        $value = sanitizeInput($value);
        
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
    return $_SESSION['role'] ?? 'guest';
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
function requireRole($required_roles, $redirect_to = 'index.php') {
    if (!hasRole($required_roles)) {
        header("Location: $redirect_to?access_denied=1");
        exit;
    }
}
?>