<?php
if (PHP_SAPI !== 'cli' || getenv('APP_ENABLE_MAINTENANCE_TOOLS') !== '1') {
	http_response_code(403);
	exit('Forbidden');
}

$config = require 'config/sms_config.php';
echo 'Provider: ' . $config['provider'] . PHP_EOL;
echo 'Sender ID: ' . $config['sender_id'] . PHP_EOL;
echo 'BulkSMSGH Sender ID: ' . $config['bulksmsgh']['sender_id'] . PHP_EOL;
echo 'API Key (first 20 chars): ' . substr($config['bulksmsgh']['api_key'], 0, 20) . '...' . PHP_EOL;
echo 'Send Endpoint: ' . $config['bulksmsgh']['send_endpoint'] . PHP_EOL;
echo 'API Key Length: ' . strlen($config['bulksmsgh']['api_key']) . PHP_EOL;
?>
