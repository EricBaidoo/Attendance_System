<?php
if (PHP_SAPI !== 'cli' || getenv('APP_ENABLE_MAINTENANCE_TOOLS') !== '1') {
    http_response_code(403);
    exit('Forbidden');
}
require_once 'config/database.php';
require_once 'includes/settings_utils.php';

$config = require 'config/sms_config.php';

$api_key = $config['bulksmsgh']['api_key'];
$endpoint = $config['bulksmsgh']['send_endpoint'];
$sender_id = $config['bulksmsgh']['sender_id'];

echo "Testing BulkSMSGH API...\n";
echo "======================\n";
echo "API Key: " . substr($api_key, 0, 20) . "...\n";
echo "Sender ID: $sender_id\n";
echo "Endpoint: $endpoint\n\n";

// Test with a dummy number - BulkSMS format (no leading 0, no country code)
$test_phone = '243838490';
$test_message = 'Test SMS from Bridge Ministries Attendance System';

$query = http_build_query([
    'key' => $api_key,
    'to' => $test_phone,
    'msg' => $test_message,
    'sender_id' => $sender_id,
], '', '&', PHP_QUERY_RFC3986);

$url = $endpoint . '?' . $query;

echo "Request URL:\n";
echo "============\n";
echo substr($url, 0, 100) . "...[API_KEY_HIDDEN]...\n\n";

echo "Sending test SMS...\n";
echo "===================\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
flush();

if (function_exists('curl_init')) {
    echo "Using: curl_init\n";
    flush();
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    
    echo "Executing request...\n";
    flush();
    
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $curl_info = curl_getinfo($ch);
    curl_close($ch);

    echo "Request completed at: " . date('Y-m-d H:i:s') . "\n";
    echo "HTTP Status: " . ($curl_info['http_code'] ?? 'unknown') . "\n";
    flush();

    if ($response === false) {
        echo "ERROR: " . $curl_error . "\n";
    } else {
        echo "Response length: " . strlen($response) . " bytes\n";
        echo "Response from BulkSMSGH:\n";
        echo "----------\n";
        echo $response . "\n";
        echo "----------\n\n";
        
        // Parse response
        if (strpos($response, '1000') !== false || strpos($response, '1007') !== false) {
            echo "✓ API ACCEPTED: SMS request was successful\n";
        } elseif (strpos($response, '1003') !== false) {
            echo "✗ ERROR: Insufficient SMS balance\n";
            echo "  Please top up at https://bulksmsgh.com\n";
        } elseif (strpos($response, '1004') !== false) {
            echo "✗ ERROR: Invalid API key\n";
            echo "  Please check your BulkSMSGH credentials\n";
        } elseif (strpos($response, '1005') !== false) {
            echo "✗ ERROR: Invalid phone number format\n";
        } else {
            echo "? UNKNOWN RESPONSE\n";
        }
    }
} elseif (ini_get('allow_url_fopen')) {
    echo "cURL not available, using file_get_contents\n";
    $response = @file_get_contents($url);
    if ($response === false) {
        echo "ERROR: file_get_contents failed\n";
    } else {
        echo "Response: " . $response . "\n";
    }
} else {
    echo "ERROR: Neither cURL nor allow_url_fopen available\n";
}
?>

