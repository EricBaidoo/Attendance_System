<?php

$provider = getSystemSetting($pdo, 'sms_provider', 'bulksmsgh');
$sender_id = getSystemSetting($pdo, 'sms_sender_id', 'BRIDGE MIN.');
$api_key = getSystemSetting($pdo, 'sms_api_key', '');
$unit_cost = (float)getSystemSetting($pdo, 'sms_unit_cost', '0.02');
$currency = getSystemSetting($pdo, 'sms_currency', 'GHS');

return [
    'provider' => $provider,
    'sender_id' => $sender_id,
    'bulksmsgh' => [
        'api_key' => $api_key,
        'sender_id' => $sender_id,
        'send_endpoint' => 'https://clientlogin.bulksmsgh.com/smsapi',
        'balance_endpoints' => [
            'https://clientlogin.bulksmsgh.com/api/smsapibalance',
            'https://clientlogin.bulksmsgh.com/api/balance/sms',
        ],
    ],
    'arkesel' => [
        'api_key' => $api_key,
        'sender_id' => $sender_id,
        'send_endpoint' => 'https://sms.arkesel.com/sms/api',
        'balance_endpoint' => 'https://sms.arkesel.com/sms/api',
    ],
    'twilio' => [
        'sid' => getenv('TWILIO_ACCOUNT_SID') ?: '',
        'token' => getenv('TWILIO_AUTH_TOKEN') ?: '',
        'from' => getenv('TWILIO_FROM') ?: '',
    ],
    'pricing' => [
        'unit_cost' => $unit_cost,
        'currency' => $currency,
    ],
];
