<?php
/**
 * Error Handling and Logging System
 */

// Set error reporting based on environment
if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
    // Development environment
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
} else {
    // Production environment
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

// Create logs directory if it doesn't exist
$log_dir = __DIR__ . '/../logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Custom error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $log_dir = __DIR__ . '/../logs';
    
    $error_types = [
        E_ERROR => 'Fatal Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];
    
    $error_type = $error_types[$errno] ?? 'Unknown Error';
    $timestamp = date('Y-m-d H:i:s');
    $user_info = isset($_SESSION['user_id']) ? " (User: {$_SESSION['username']})" : " (Anonymous)";
    
    $error_message = "[$timestamp] $error_type: $errstr in $errfile on line $errline$user_info\n";
    
    // Create logs directory if it doesn't exist
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    // Log to file with proper error handling
    if (is_writable($log_dir)) {
        error_log($error_message, 3, $log_dir . '/error.log');
    } else {
        // Fallback to system error log
        error_log($error_message);
    }
    
    // In development, also display the error
    if (ini_get('display_errors')) {
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px;'>";
        echo "<strong>$error_type:</strong> $errstr<br>";
        echo "<strong>File:</strong> $errfile<br>";
        echo "<strong>Line:</strong> $errline";
        echo "</div>";
    }
}

// Set custom error handler
set_error_handler('customErrorHandler');

// Exception handler
function customExceptionHandler($exception) {
    $timestamp = date('Y-m-d H:i:s');
    $user_info = isset($_SESSION['user_id']) ? " (User: {$_SESSION['username']})" : " (Anonymous)";
    
    $error_message = "[$timestamp] Uncaught Exception: " . $exception->getMessage() . 
                    " in " . $exception->getFile() . " on line " . $exception->getLine() . $user_info . "\n" .
                    "Stack trace:\n" . $exception->getTraceAsString() . "\n";
    
    // Log to file
    error_log($error_message, 3, __DIR__ . '/../logs/error.log');
    
    // Show user-friendly error page
    if (!ini_get('display_errors')) {
        http_response_code(500);
        include __DIR__ . '/error_pages/500.php';
        exit;
    } else {
        // In development, show detailed error
        echo "<div style='color: red; padding: 20px; border: 2px solid red; margin: 20px;'>";
        echo "<h3>Uncaught Exception</h3>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($exception->getFile()) . "</p>";
        echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>";
        echo "<p><strong>Stack Trace:</strong></p>";
        echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        echo "</div>";
    }
}

// Set custom exception handler
set_exception_handler('customExceptionHandler');

// Application logging functions
function logActivity($message, $level = 'INFO') {
    $log_dir = __DIR__ . '/../logs';
    $timestamp = date('Y-m-d H:i:s');
    $user_info = isset($_SESSION['user_id']) ? " (User: {$_SESSION['username']})" : " (Anonymous)";
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    $log_message = "[$timestamp] [$level] $message$user_info (IP: $ip)\n";
    
    // Create logs directory if it doesn't exist
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    // Log with proper error handling
    if (is_writable($log_dir)) {
        error_log($log_message, 3, $log_dir . '/activity.log');
    } else {
        // Fallback to system error log
        error_log($log_message);
    }
}

function logSecurityEvent($message) {
    logActivity("SECURITY: $message", 'WARNING');
}

function logDatabaseError($error, $query = '') {
    $message = "Database Error: " . $error;
    if ($query) {
        $message .= " | Query: " . $query;
    }
    logActivity($message, 'ERROR');
}

// Database error handling wrapper
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        logDatabaseError($e->getMessage(), $sql);
        throw new Exception('Database operation failed. Please try again.');
    }
}

// User-friendly error display
function displayError($message, $type = 'danger') {
    $icons = [
        'danger' => 'bi-exclamation-triangle-fill',
        'warning' => 'bi-exclamation-circle-fill',
        'info' => 'bi-info-circle-fill',
        'success' => 'bi-check-circle-fill'
    ];
    
    $icon = $icons[$type] ?? $icons['danger'];
    
    return "<div class='alert alert-$type border-0 shadow-sm' role='alert'>
                <i class='bi $icon me-2'></i>
                " . htmlspecialchars($message) . "
            </div>";
}
?>