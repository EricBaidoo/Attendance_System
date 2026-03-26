<?php

return [
    'provider' => getenv('SMS_PROVIDER') ?: 'bulksmsgh',
    'sender_id' => getenv('SMS_SENDER_ID') ?: 'BRIDGE MIN.',
    'bulksmsgh' => [
        'api_key' => getenv('BULKSMSGH_API_KEY') ?: '',
        'sender_id' => getenv('BULKSMSGH_SENDER_ID') ?: (getenv('SMS_SENDER_ID') ?: 'BRIDGE MIN.'),
        'send_endpoint' => getenv('BULKSMSGH_SEND_ENDPOINT') ?: 'https://clientlogin.bulksmsgh.com/smsapi',
        'balance_endpoints' => [
            getenv('BULKSMSGH_BALANCE_ENDPOINT_1') ?: 'https://clientlogin.bulksmsgh.com/api/smsapibalance',
            getenv('BULKSMSGH_BALANCE_ENDPOINT_2') ?: 'https://clientlogin.bulksmsgh.com/api/balance/sms',
        ],
    ],
    'twilio' => [
        'sid' => getenv('TWILIO_ACCOUNT_SID') ?: '',
        'token' => getenv('TWILIO_AUTH_TOKEN') ?: '',
        'from' => getenv('TWILIO_FROM') ?: '',
    ],
    'pricing' => [
        'unit_cost' => (float)(getenv('SMS_UNIT_COST') ?: '0.00'),
        'currency' => getenv('SMS_CURRENCY') ?: 'GHS',
    ],
];
