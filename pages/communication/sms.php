<?php
require_once '../../includes/security.php';
requireLogin('../../login.php');
require_once '../../config/database.php';

$page_title = 'Communication SMS Center - Bridge Ministries International';
$page_heading = 'Communication SMS Center';
$page_header = false;

$sms_config = require '../../config/sms_config.php';
$provider = strtolower(trim((string)($sms_config['provider'] ?? 'none')));
$sms_batch_size = max(10, min(250, (int)(getenv('SMS_BATCH_SIZE') ?: 40)));
$sms_batch_time_budget = max(8, min(25, (int)(getenv('SMS_BATCH_TIME_BUDGET') ?: 18)));

function normalizePhoneNumber(string $phone): string {
    $digits = preg_replace('/[^0-9+]/', '', $phone);
    if ($digits === null) {
        return '';
    }

    $digits = trim($digits);

    if ($digits === '') {
        return '';
    }

    if (strpos($digits, '+') === 0) {
        return $digits;
    }

    if (strpos($digits, '0') === 0) {
        return '+233' . ltrim(substr($digits, 1), '0');
    }

    if (strpos($digits, '233') === 0) {
        return '+' . $digits;
    }

    return '+' . ltrim($digits, '+');
}

function normalizePhoneForProvider(string $phone, string $provider): string {
    $provider = strtolower(trim($provider));

    if ($provider === 'bulksmsgh') {
        $digits = preg_replace('/[^0-9+]/', '', $phone);
        if ($digits === null) {
            return '';
        }

        $digits = trim($digits);
        if ($digits === '') {
            return '';
        }

        $digits = ltrim($digits, '+');

        if (strpos($digits, '233') === 0) {
            $digits = substr($digits, 3);
        }

        if (strpos($digits, '0') === 0) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    return normalizePhoneNumber($phone);
}

function parseGatewayBalanceValue($value): ?float {
    if ($value === null) {
        return null;
    }

    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }

    if (preg_match('/-?\d+(?:\.\d+)?/', $raw, $matches) !== 1) {
        return null;
    }

    return (float)$matches[0];
}

function makeHttpRequest(string $url, string $method = 'GET', array $headers = [], ?string $body = null, int $timeout = 20): array {
    $method = strtoupper(trim($method));
    $method = $method === 'POST' ? 'POST' : 'GET';

    if (function_exists('curl_init')) {
        $buildCurlOptions = static function(bool $insecure = false) use ($url, $timeout, $method, $headers, $body): array {
            $options = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => min($timeout, 8),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CUSTOMREQUEST => $method,
            ];

            if (!empty($headers)) {
                $options[CURLOPT_HTTPHEADER] = $headers;
            }

            if ($method === 'POST') {
                $options[CURLOPT_POST] = true;
                if ($body !== null) {
                    $options[CURLOPT_POSTFIELDS] = $body;
                }
            }

            if ($insecure) {
                $options[CURLOPT_SSL_VERIFYPEER] = false;
                $options[CURLOPT_SSL_VERIFYHOST] = 0;
            }

            return $options;
        };

        $ch = curl_init();
        $curl_options = $buildCurlOptions(false);

        curl_setopt_array($ch, $curl_options);
        $response = curl_exec($ch);
        $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);

        // Retry once in insecure mode when local CA certificates are missing.
        if ($response === false && preg_match('/certificate|SSL/i', $curl_error)) {
            curl_setopt_array($ch, $buildCurlOptions(true));
            $response = curl_exec($ch);
            $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
        }

        curl_close($ch);

        if ($response === false) {
            return [
                'ok' => false,
                'body' => null,
                'http_code' => $http_code,
                'error' => $curl_error !== '' ? $curl_error : 'HTTP request failed.',
            ];
        }

        return [
            'ok' => true,
            'body' => (string)$response,
            'http_code' => $http_code,
            'error' => null,
        ];
    }

    if (!ini_get('allow_url_fopen')) {
        return [
            'ok' => false,
            'body' => null,
            'http_code' => 0,
            'error' => 'cURL is unavailable and allow_url_fopen is disabled.',
        ];
    }

    $stream_headers = $headers;
    if ($method === 'POST' && $body !== null) {
        $has_content_type = false;
        foreach ($stream_headers as $header) {
            if (stripos((string)$header, 'Content-Type:') === 0) {
                $has_content_type = true;
                break;
            }
        }
        if (!$has_content_type) {
            $stream_headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
    }

    $bounded_timeout = max(2, min($timeout, 12));
    @ini_set('default_socket_timeout', (string)$bounded_timeout);

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'timeout' => $bounded_timeout,
            'header' => implode("\r\n", $stream_headers),
            'content' => $body ?? '',
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    $http_code = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string)$line, $matches)) {
                $http_code = (int)$matches[1];
                break;
            }
        }
    }

    if ($response === false) {
        return [
            'ok' => false,
            'body' => null,
            'http_code' => $http_code,
            'error' => 'HTTP request failed in stream fallback.',
        ];
    }

    return [
        'ok' => true,
        'body' => (string)$response,
        'http_code' => $http_code,
        'error' => null,
    ];
}

function sendSmsMessage(array $sms_config, string $to, string $message): array {
    $provider = strtolower(trim((string)($sms_config['provider'] ?? 'none')));

    if ($provider === 'bulksmsgh') {
        $api_key = trim((string)($sms_config['bulksmsgh']['api_key'] ?? ''));
        $sender_id = trim((string)($sms_config['bulksmsgh']['sender_id'] ?? ($sms_config['sender_id'] ?? 'BRIDGE MIN.')));
        $endpoint = trim((string)($sms_config['bulksmsgh']['send_endpoint'] ?? 'https://clientlogin.bulksmsgh.com/smsapi'));

        if ($api_key === '') {
            return [
                'ok' => false,
                'status' => 'failed',
                'error' => 'BulkSMSGH API key is missing.',
            ];
        }

        if ($sender_id === '' || mb_strlen($sender_id) > 11) {
            return [
                'ok' => false,
                'status' => 'failed',
                'error' => 'Invalid sender id. It must be 1 to 11 characters.',
            ];
        }

        $query = http_build_query([
            'key' => $api_key,
            'to' => $to,
            'msg' => $message,
            'sender_id' => $sender_id,
        ], '', '&', PHP_QUERY_RFC3986);

        $url = $endpoint . (strpos($endpoint, '?') === false ? '?' : '&') . $query;

        $http = makeHttpRequest($url, 'GET', [], null, 8);
        if (($http['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'status' => 'failed',
                'error' => (string)($http['error'] ?? 'Failed to reach BulkSMSGH endpoint.'),
            ];
        }

        $response_text = trim((string)($http['body'] ?? ''));
        preg_match('/\b(1000|1002|1003|1004|1005|1006|1007|1008)\b/', $response_text, $matches);
        $code = $matches[1] ?? $response_text;

        $code_map = [
            '1000' => ['ok' => true, 'status' => 'sent', 'error' => null, 'message' => 'Message submitted successfully.'],
            '1002' => ['ok' => false, 'status' => 'failed', 'error' => 'SMS sending failed.'],
            '1003' => ['ok' => false, 'status' => 'failed', 'error' => 'Insufficient SMS balance.'],
            '1004' => ['ok' => false, 'status' => 'failed', 'error' => 'Invalid BulkSMSGH API key.'],
            '1005' => ['ok' => false, 'status' => 'failed', 'error' => 'Invalid phone number.'],
            '1006' => ['ok' => false, 'status' => 'failed', 'error' => 'Invalid sender id (max 11 chars).'],
            '1007' => ['ok' => true, 'status' => 'queued', 'error' => null, 'message' => 'Message scheduled for later delivery.'],
            '1008' => ['ok' => false, 'status' => 'failed', 'error' => 'Empty message body.'],
        ];

        if (isset($code_map[$code])) {
            return [
                'ok' => $code_map[$code]['ok'],
                'status' => $code_map[$code]['status'],
                'error' => $code_map[$code]['error'],
                'provider_code' => $code,
            ];
        }

        return [
            'ok' => false,
            'status' => 'failed',
            'error' => 'Unexpected BulkSMSGH response: ' . mb_substr($response_text, 0, 120),
            'provider_code' => $code,
        ];
    }

    if ($provider === 'twilio') {
        $sid = (string)($sms_config['twilio']['sid'] ?? '');
        $token = (string)($sms_config['twilio']['token'] ?? '');
        $from = (string)($sms_config['twilio']['from'] ?? '');

        if ($sid === '' || $token === '' || $from === '') {
            return [
                'ok' => false,
                'status' => 'failed',
                'error' => 'Twilio configuration is incomplete.',
            ];
        }

        $twilio_url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json';
        $twilio_body = http_build_query([
            'To' => $to,
            'From' => $from,
            'Body' => $message,
        ]);
        $twilio_headers = [
            'Authorization: Basic ' . base64_encode($sid . ':' . $token),
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $http = makeHttpRequest($twilio_url, 'POST', $twilio_headers, $twilio_body, 12);
        if (($http['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'status' => 'failed',
                'error' => (string)($http['error'] ?? 'Failed to send SMS via Twilio.'),
            ];
        }

        $http_code = (int)($http['http_code'] ?? 0);

        if ($http_code >= 200 && $http_code < 300) {
            return [
                'ok' => true,
                'status' => 'sent',
                'error' => null,
            ];
        }

        return [
            'ok' => false,
            'status' => 'failed',
            'error' => 'Twilio API error (' . $http_code . ').',
        ];
    }

    return [
        'ok' => false,
        'status' => 'queued',
        'error' => 'No SMS provider configured. Message queued only.',
    ];
}

function fetchSmsBalance(array $sms_config): array {
    $provider = strtolower(trim((string)($sms_config['provider'] ?? 'none')));
    if ($provider !== 'bulksmsgh') {
        return ['ok' => false, 'balance' => null, 'error' => 'Balance not supported for current provider.'];
    }

    $api_key = trim((string)($sms_config['bulksmsgh']['api_key'] ?? ''));
    if ($api_key === '') {
        return ['ok' => false, 'balance' => null, 'error' => 'BulkSMSGH API key is missing.'];
    }

    $endpoints = $sms_config['bulksmsgh']['balance_endpoints'] ?? [];
    if (!is_array($endpoints) || empty($endpoints)) {
        return ['ok' => false, 'balance' => null, 'error' => 'BulkSMSGH balance endpoint is not configured.'];
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        $cached_at = (int)($_SESSION['sms_balance_cached_at'] ?? 0);
        $cached_provider = (string)($_SESSION['sms_balance_provider'] ?? '');
        $cached_value = $_SESSION['sms_balance_cached_value'] ?? null;

        if ($cached_provider === $provider && $cached_at > 0 && (time() - $cached_at) < 60) {
            if (is_string($cached_value) && $cached_value !== '') {
                return ['ok' => true, 'balance' => $cached_value, 'error' => null];
            }
        }
    }

    $provider_code_errors = [
        '1002' => 'SMS operation failed.',
        '1003' => 'Insufficient SMS balance.',
        '1004' => 'Invalid API key.',
        '1005' => 'Invalid phone number format.',
        '1006' => 'Invalid sender id.',
        '1008' => 'Invalid request payload.',
    ];

    foreach ($endpoints as $endpoint) {
        $endpoint = trim((string)$endpoint);
        if ($endpoint === '') {
            continue;
        }

        $url = $endpoint . (strpos($endpoint, '?') === false ? '?' : '&') . http_build_query(['key' => $api_key], '', '&', PHP_QUERY_RFC3986);

        $http = makeHttpRequest($url, 'GET', [], null, 4);
        if (($http['ok'] ?? false) !== true) {
            continue;
        }

        $response_text = trim((string)($http['body'] ?? ''));
        if ($response_text === '') {
            continue;
        }

        $json = json_decode($response_text, true);
        if (is_array($json)) {
            foreach (['balance', 'sms_balance', 'data'] as $key) {
                if (isset($json[$key]) && is_scalar($json[$key])) {
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        $_SESSION['sms_balance_provider'] = $provider;
                        $_SESSION['sms_balance_cached_value'] = (string)$json[$key];
                        $_SESSION['sms_balance_cached_at'] = time();
                    }
                    return ['ok' => true, 'balance' => (string)$json[$key], 'error' => null];
                }
            }
        }

        // Some 10xx responses are true error codes; others (e.g. 1036) can be numeric balances on some accounts.
        // Only treat known error codes as errors and allow other numeric responses as balance values.
        if (preg_match('/^\s*(1002|1003|1004|1005|1006|1008)\s*$/', $response_text, $matches)) {
            $code = (string)$matches[1];
            $message = $provider_code_errors[$code] ?? ('Gateway returned status code ' . $code . '.');
            return ['ok' => false, 'balance' => null, 'error' => $message];
        }

        if (preg_match('/-?\d+(?:\.\d+)?/', $response_text, $matches)) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['sms_balance_provider'] = $provider;
                $_SESSION['sms_balance_cached_value'] = (string)$matches[0];
                $_SESSION['sms_balance_cached_at'] = time();
            }
            return ['ok' => true, 'balance' => (string)$matches[0], 'error' => null];
        }

        if (preg_match('/\b(1004)\b/', $response_text)) {
            return ['ok' => false, 'balance' => null, 'error' => 'Invalid BulkSMSGH API key.'];
        }
    }

    return ['ok' => false, 'balance' => null, 'error' => 'Could not fetch SMS balance from provider endpoints.'];
}

function campaignSupportsSendingStatus(PDO $pdo): bool {
    static $supports_sending = null;

    if ($supports_sending !== null) {
        return $supports_sending;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT COLUMN_TYPE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'communication_campaigns'
               AND COLUMN_NAME = 'status'
             LIMIT 1"
        );
        $stmt->execute();
        $column_type = (string)$stmt->fetchColumn();
        $supports_sending = stripos($column_type, "'sending'") !== false;
    } catch (Exception $e) {
        $supports_sending = false;
    }

    return $supports_sending;
}

function getCampaignProgressSummary(PDO $pdo, int $campaign_id): array {
    $summary_stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_count,
            SUM(CASE WHEN status IN ('sent', 'delivered') THEN 1 ELSE 0 END) AS delivered_count,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
            SUM(CASE WHEN status = 'queued' AND (error_detail IS NULL OR error_detail <> 'Prepared only. Not yet sent.') THEN 1 ELSE 0 END) AS remaining_count
         FROM communication_logs
         WHERE campaign_id = ?"
    );
    $summary_stmt->execute([$campaign_id]);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $total = (int)($summary['total_count'] ?? 0);
    $remaining = (int)($summary['remaining_count'] ?? 0);
    $processed = max(0, $total - $remaining);
    $percent = $total > 0 ? round(($processed / $total) * 100, 1) : 100.0;

    return [
        'total' => $total,
        'processed' => $processed,
        'remaining' => max(0, $remaining),
        'delivered' => (int)($summary['delivered_count'] ?? 0),
        'failed' => (int)($summary['failed_count'] ?? 0),
        'percent' => $percent,
    ];
}

function updateCampaignDeliveryStats(PDO $pdo, int $campaign_id, bool $is_done): void {
    $stats_stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_count,
            SUM(CASE WHEN status IN ('sent', 'delivered') THEN 1 ELSE 0 END) AS delivered_count,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
            SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) AS queued_count
         FROM communication_logs
         WHERE campaign_id = ?"
    );
    $stats_stmt->execute([$campaign_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $update_stmt = $pdo->prepare(
        'UPDATE communication_campaigns SET total_recipients = ?, delivered_count = ?, failed_count = ?, status = ?, sent_at = ? WHERE id = ?'
    );
    $in_progress_status = campaignSupportsSendingStatus($pdo) ? 'sending' : 'scheduled';
    $update_stmt->execute([
        (int)($stats['total_count'] ?? 0),
        (int)($stats['delivered_count'] ?? 0),
        (int)($stats['failed_count'] ?? 0),
        $is_done ? 'sent' : $in_progress_status,
        $is_done ? date('Y-m-d H:i:s') : null,
        $campaign_id,
    ]);
}

function extractPhoneFromRecipientLabel(string $recipient_label): string {
    $recipient_label = trim($recipient_label);
    if ($recipient_label === '') {
        return '';
    }

    if (preg_match('/\(([^()]+)\)\s*$/', $recipient_label, $matches)) {
        return trim((string)$matches[1]);
    }

    return $recipient_label;
}

function processSmsBatchJob(PDO $pdo, array $sms_config, int $campaign_id, int $max_per_run = 0, int $time_budget_seconds = 0): array {
    global $provider, $sms_batch_size, $sms_batch_time_budget;

    if ($max_per_run <= 0) {
        $max_per_run = $sms_batch_size;
    }
    if ($time_budget_seconds <= 0) {
        $time_budget_seconds = $sms_batch_time_budget;
    }

    @set_time_limit(0);
    @ini_set('max_execution_time', '0');

    $campaign_stmt = $pdo->prepare('SELECT content, total_recipients FROM communication_campaigns WHERE id = ? AND channel = ? LIMIT 1');
    $campaign_stmt->execute([$campaign_id, 'sms']);
    $campaign = $campaign_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) {
        return ['ok' => false, 'done' => true, 'processed' => 0, 'error' => 'Campaign not found for batch send.'];
    }

    $message_text = (string)($campaign['content'] ?? '');
    $total = (int)($campaign['total_recipients'] ?? 0);

    $pending_stmt = $pdo->prepare(
        "SELECT id, recipient
         FROM communication_logs
         WHERE campaign_id = ?
           AND channel = 'sms'
           AND status = 'queued'
           AND (error_detail IS NULL OR error_detail <> 'Prepared only. Not yet sent.')
         ORDER BY id ASC
         LIMIT ?"
    );
    $pending_stmt->bindValue(1, $campaign_id, PDO::PARAM_INT);
    $pending_stmt->bindValue(2, $max_per_run, PDO::PARAM_INT);
    $pending_stmt->execute();
    $items = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        updateCampaignDeliveryStats($pdo, $campaign_id, true);
        $summary = getCampaignProgressSummary($pdo, $campaign_id);
        return [
            'ok' => true,
            'done' => true,
            'processed' => (int)($summary['processed'] ?? 0),
            'remaining' => (int)($summary['remaining'] ?? 0),
            'total' => (int)($summary['total'] ?? $total),
            'delivered' => (int)($summary['delivered'] ?? 0),
            'failed' => (int)($summary['failed'] ?? 0),
            'percent' => (float)($summary['percent'] ?? 100.0),
        ];
    }

    $processed = 0;
    $started_at = microtime(true);

    $log_update_stmt = $pdo->prepare('UPDATE communication_logs SET status = ?, error_detail = ?, sent_at = ? WHERE id = ?');

    foreach ($items as $item) {
        if ((microtime(true) - $started_at) >= $time_budget_seconds) {
            break;
        }

        $to_phone = normalizePhoneForProvider(extractPhoneFromRecipientLabel((string)($item['recipient'] ?? '')), $provider);
        if ($to_phone === '') {
            $log_update_stmt->execute([
                'failed',
                'Invalid or missing recipient phone in log entry.',
                date('Y-m-d H:i:s'),
                (int)($item['id'] ?? 0),
            ]);
            $processed++;
            continue;
        }

        $send_result = sendSmsMessage($sms_config, $to_phone, $message_text);
        $log_status = (string)($send_result['status'] ?? 'failed');
        $error_detail = isset($send_result['error']) ? (string)$send_result['error'] : null;
        $sent_at = $log_status === 'queued' ? null : date('Y-m-d H:i:s');

        $log_update_stmt->execute([
            $log_status,
            $error_detail,
            $sent_at,
            (int)($item['id'] ?? 0),
        ]);

        $processed++;
    }

    $remaining_stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM communication_logs
         WHERE campaign_id = ?
           AND channel = 'sms'
           AND status = 'queued'
           AND (error_detail IS NULL OR error_detail <> 'Prepared only. Not yet sent.')"
    );
    $remaining_stmt->execute([$campaign_id]);
    $remaining = (int)$remaining_stmt->fetchColumn();
    $done = $remaining <= 0;

    updateCampaignDeliveryStats($pdo, $campaign_id, $done);
    $summary = getCampaignProgressSummary($pdo, $campaign_id);

    return [
        'ok' => true,
        'done' => $done,
        'processed' => (int)($summary['processed'] ?? $processed),
        'remaining' => (int)($summary['remaining'] ?? max(0, $remaining)),
        'total' => (int)($summary['total'] ?? $total),
        'delivered' => (int)($summary['delivered'] ?? 0),
        'failed' => (int)($summary['failed'] ?? 0),
        'percent' => (float)($summary['percent'] ?? 0.0),
    ];
}

$success = '';
$error = '';

$message_text = '';
$campaign_name = '';
$audience_mode = $_POST['audience_mode'] ?? 'all_active';
$department_filter = $_POST['department_id'] ?? '';
$ministerial_filter = $_POST['ministerial_status'] ?? '';
$target_phone = $_POST['target_phone'] ?? '';
$sms_unit_cost = max(0.0, (float)($sms_config['pricing']['unit_cost'] ?? 0.0));
$sms_currency = strtoupper(trim((string)($sms_config['pricing']['currency'] ?? 'GHS')));
$last_estimated_recipients = 0;
$last_estimated_segments = 0;
$last_estimated_cost = 0.0;

$departments = [];
$ministerial_statuses = ['Levite', 'Shepherd', 'Minister', 'Junior Pastor', 'Senior Pastor', 'General Overseer'];
$recipients_preview = [];
$preview_count = 0;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['continue_campaign'])) {
    $continue_campaign_id = (int)($_GET['continue_campaign'] ?? 0);
    if ($continue_campaign_id > 0) {
        $batch_result = processSmsBatchJob($pdo, $sms_config, $continue_campaign_id);
        if (($batch_result['ok'] ?? false) === true) {
            if (($batch_result['done'] ?? false) === true) {
                $success = 'SMS sending completed. Processed ' . (int)($batch_result['processed'] ?? 0) . '/' . (int)($batch_result['total'] ?? 0) . ' (' . (float)($batch_result['percent'] ?? 100.0) . '%). Delivered: ' . (int)($batch_result['delivered'] ?? 0) . ', Failed: ' . (int)($batch_result['failed'] ?? 0) . '.';
            } else {
                $success = 'SMS batch running: ' . (int)($batch_result['processed'] ?? 0) . '/' . (int)($batch_result['total'] ?? 0) . ' processed (' . (float)($batch_result['percent'] ?? 0.0) . '%). Remaining: ' . (int)($batch_result['remaining'] ?? 0) . '. Delivered: ' . (int)($batch_result['delivered'] ?? 0) . ', Failed: ' . (int)($batch_result['failed'] ?? 0) . '.';
                header('Refresh: 1; url=sms.php?continue_campaign=' . $continue_campaign_id);
            }
        } else {
            error_log('[sms.php] Batch continuation failed for campaign ' . $continue_campaign_id . ': ' . (string)($batch_result['error'] ?? 'Unknown error'));
            $error = (string)($batch_result['error'] ?? 'Unable to continue SMS batch send.');
        }
    }
}

try {
    $departments_stmt = $pdo->query('SELECT id, name FROM departments ORDER BY name');
    $departments = $departments_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $departments = [];
}

function fetchSmsRecipients(PDO $pdo, string $audience_mode, string $department_filter, string $ministerial_filter, string $target_phone = '', int $limit = 200): array {
    $fetchMembers = function() use ($pdo, $audience_mode, $department_filter, $ministerial_filter, $limit): array {
        $where = ["m.status = 'active'", "COALESCE(TRIM(p.phone), '') <> ''"];
        $params = [];

        if ($audience_mode === 'department') {
            if ($department_filter !== '') {
                $where[] = 'm.department_id = ?';
                $params[] = (int)$department_filter;
            } else {
                $where[] = '1 = 0';
            }
        }

        if ($audience_mode === 'tithers') {
            $where[] = 'EXISTS (SELECT 1 FROM tithers t WHERE t.member_id = m.id AND t.status = \"active\")';
        }

        if ($audience_mode === 'ministerial') {
            if ($ministerial_filter !== '') {
                $where[] = 'm.ministerial_status = ?';
                $params[] = $ministerial_filter;
            } else {
                $where[] = '1 = 0';
            }
        }

        if ($ministerial_filter !== '' && $audience_mode !== 'ministerial') {
            $where[] = 'm.ministerial_status = ?';
            $params[] = $ministerial_filter;
        }

        $where_sql = implode(' AND ', $where);

        $limit_sql = $limit > 0 ? ' LIMIT ' . (int)$limit : '';

        $sql = "SELECT m.id, p.full_name AS name, p.phone, d.name AS department_name, m.ministerial_status, 'member' AS recipient_type
            FROM member_roles m
                JOIN people p ON p.id = m.person_id
                LEFT JOIN departments d ON d.id = m.department_id
                WHERE {$where_sql}
            ORDER BY p.full_name ASC" . $limit_sql;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    $fetchVisitors = function() use ($pdo, $limit): array {
        $limit_sql = $limit > 0 ? ' LIMIT ' . (int)$limit : '';

        $sql = "SELECT v.id, p.full_name AS name, p.phone, 'Visitor' AS department_name, NULL AS ministerial_status, 'visitor' AS recipient_type
            FROM visitor_roles v
                JOIN people p ON p.id = v.person_id
                WHERE COALESCE(TRIM(p.phone), '') <> ''
            ORDER BY v.date DESC, v.id DESC" . $limit_sql;

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    if ($audience_mode === 'visitors') {
        return $fetchVisitors();
    }

    if ($audience_mode === 'single_number') {
        $manual_phone = trim($target_phone);
        if ($manual_phone === '') {
            return [];
        }

        return [[
            'id' => 0,
            'name' => 'Manual Recipient',
            'phone' => $manual_phone,
            'department_name' => 'Manual Entry',
            'ministerial_status' => null,
            'recipient_type' => 'manual',
        ]];
    }

    if ($audience_mode === 'members_and_visitors') {
        return array_merge($fetchMembers(), $fetchVisitors());
    }

    return $fetchMembers();
}

function prepareValidSmsRecipients(array $all_recipients, string $provider): array {
    $valid_recipients = [];
    foreach ($all_recipients as $recipient) {
        $phone = normalizePhoneForProvider((string)($recipient['phone'] ?? ''), $provider);
        if ($phone === '') {
            continue;
        }

        $recipient['normalized_phone'] = $phone;
        $valid_recipients[] = $recipient;
    }

    $deduped_recipients = [];
    $seen_phone_keys = [];
    foreach ($valid_recipients as $recipient) {
        $phone_key = (string)($recipient['normalized_phone'] ?? '');
        if ($phone_key === '' || isset($seen_phone_keys[$phone_key])) {
            continue;
        }

        $seen_phone_keys[$phone_key] = true;
        $deduped_recipients[] = $recipient;
    }

    return $deduped_recipients;
}

function calculateSmsUnitsPerMessage(string $message_text): int {
    $length = mb_strlen($message_text);
    if ($length <= 0) {
        return 0;
    }

    // Provider billing model: 1 unit up to 159 chars, then +1 unit per additional 141 chars.
    if ($length <= 159) {
        return 1;
    }

    return (int)ceil(($length - 159) / 141) + 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['estimate_live']) && $_GET['estimate_live'] === '1') {
    header('Content-Type: application/json');

    $live_audience_mode = trim((string)($_GET['audience_mode'] ?? 'all_active'));
    $live_department_filter = trim((string)($_GET['department_id'] ?? ''));
    $live_ministerial_filter = trim((string)($_GET['ministerial_status'] ?? ''));
    $live_target_phone = trim((string)($_GET['target_phone'] ?? ''));
    $live_message_text = trim((string)($_GET['message_text'] ?? ''));

    $valid_audience_modes = ['all_active', 'tithers', 'department', 'ministerial', 'visitors', 'members_and_visitors', 'single_number'];
    if (!in_array($live_audience_mode, $valid_audience_modes, true)) {
        $live_audience_mode = 'all_active';
    }

    if ($live_message_text === '') {
        echo json_encode([
            'ok' => true,
            'recipients' => 0,
            'segments' => 0,
            'units' => 0,
            'cost' => 0,
            'currency' => $sms_currency,
            'unit_cost' => $sms_unit_cost,
            'unit_cost_configured' => $sms_unit_cost > 0,
            'message' => 'Type your message to see live cost.',
        ]);
        exit;
    }

    try {
        $live_recipients = fetchSmsRecipients($pdo, $live_audience_mode, $live_department_filter, $live_ministerial_filter, $live_target_phone, 0);
        $live_valid_recipients = prepareValidSmsRecipients($live_recipients, $provider);

        $live_recipient_count = count($live_valid_recipients);
        $live_segments = calculateSmsUnitsPerMessage($live_message_text);
        $live_units = $live_recipient_count * $live_segments;
        $live_cost = $live_units * $sms_unit_cost;

        echo json_encode([
            'ok' => true,
            'recipients' => $live_recipient_count,
            'segments' => $live_segments,
            'units' => $live_units,
            'cost' => $live_cost,
            'currency' => $sms_currency,
            'unit_cost' => $sms_unit_cost,
            'unit_cost_configured' => $sms_unit_cost > 0,
            'message' => $live_recipient_count > 0
                ? ('Estimated cost updated for ' . number_format($live_recipient_count) . ' recipients.')
                : 'No recipients matched current filters.',
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'ok' => false,
            'error' => 'Unable to estimate SMS cost right now.',
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($csrf_token)) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        $campaign_name = trim($_POST['campaign_name'] ?? '');
        $message_text = trim($_POST['message_text'] ?? '');
        $audience_mode = trim($_POST['audience_mode'] ?? 'all_active');
        $department_filter = trim($_POST['department_id'] ?? '');
        $ministerial_filter = trim($_POST['ministerial_status'] ?? '');
        $target_phone = trim($_POST['target_phone'] ?? '');

        $valid_audience_modes = ['all_active', 'tithers', 'department', 'ministerial', 'visitors', 'members_and_visitors', 'single_number'];
        if (!in_array($audience_mode, $valid_audience_modes, true)) {
            $audience_mode = 'all_active';
        }

        try {
            $all_recipients = fetchSmsRecipients($pdo, $audience_mode, $department_filter, $ministerial_filter, $target_phone, 0);
        } catch (Exception $e) {
            $all_recipients = [];
            error_log('[sms.php] Recipient fetch failed: ' . $e->getMessage());
            $error = 'Unable to load recipients right now.';
        }

        if ($error === '' && $audience_mode === 'single_number' && $target_phone === '') {
            $error = 'Please enter a phone number for Single Number audience.';
        }

        if ($action === 'preview') {
            $recipients_preview = array_slice($all_recipients, 0, 200);
            $preview_count = count($all_recipients);
            if ($preview_count === 0 && $error === '') {
                $error = 'No recipients matched your SMS audience filters.';
            }
        } elseif ($action === 'estimate_sms') {
            if ($message_text === '') {
                $error = 'Message text is required to estimate SMS cost.';
            } elseif (mb_strlen($message_text) > 1000) {
                $error = 'Message text is too long. Maximum is 1000 characters.';
            } elseif (empty($all_recipients)) {
                $error = 'No recipients matched your SMS audience filters.';
            } else {
                $valid_recipients = prepareValidSmsRecipients($all_recipients, $provider);

                if (empty($valid_recipients)) {
                    $error = 'No valid recipient phone numbers were found for this audience.';
                } else {
                    $message_segments = calculateSmsUnitsPerMessage($message_text);
                    $estimated_recipients = count($valid_recipients);
                    $estimated_sms_units = $estimated_recipients * $message_segments;
                    $estimated_cost = $estimated_sms_units * $sms_unit_cost;

                    $last_estimated_recipients = $estimated_recipients;
                    $last_estimated_segments = $message_segments;
                    $last_estimated_cost = $estimated_cost;

                    if ($sms_unit_cost > 0) {
                        $success = 'Estimated SMS cost: ' . $sms_currency . ' ' . number_format($estimated_cost, 2) . '.';
                    } else {
                        $success = 'Estimated SMS units: ' . number_format($estimated_sms_units) . '. Configure SMS_UNIT_COST to show monetary amount.';
                    }
                    $recipients_preview = array_slice($valid_recipients, 0, 200);
                    $preview_count = count($valid_recipients);
                }
            }
        } elseif (in_array($action, ['prepare_sms', 'send_sms'], true)) {
            if ($campaign_name === '') {
                $error = 'Campaign name is required.';
            } elseif ($message_text === '') {
                $error = 'Message text is required.';
            } elseif (mb_strlen($message_text) > 1000) {
                $error = 'Message text is too long. Maximum is 1000 characters.';
            } elseif (empty($all_recipients)) {
                $error = 'No recipients matched your SMS audience filters.';
            } else {
                $valid_recipients = prepareValidSmsRecipients($all_recipients, $provider);

                if (empty($valid_recipients)) {
                    $error = 'No valid recipient phone numbers were found for this audience.';
                } else {
                    $message_segments = calculateSmsUnitsPerMessage($message_text);
                    $estimated_recipients = count($valid_recipients);
                    $estimated_sms_units = $estimated_recipients * $message_segments;
                    $estimated_cost = $estimated_sms_units * $sms_unit_cost;

                    $last_estimated_recipients = $estimated_recipients;
                    $last_estimated_segments = $message_segments;
                    $last_estimated_cost = $estimated_cost;

                    if ($action === 'send_sms' && $provider === 'bulksmsgh' && $sms_unit_cost > 0) {
                        $balance_result = fetchSmsBalance($sms_config);
                        $current_balance = null;

                        if (($balance_result['ok'] ?? false) === true) {
                            $current_balance = parseGatewayBalanceValue($balance_result['balance'] ?? null);
                        }

                        // Fallback to session cache when provider check does not return a parseable value.
                        if ($current_balance === null && session_status() === PHP_SESSION_ACTIVE) {
                            $cached_provider = (string)($_SESSION['sms_balance_provider'] ?? '');
                            if ($cached_provider === $provider) {
                                $current_balance = parseGatewayBalanceValue($_SESSION['sms_balance_cached_value'] ?? null);
                            }
                        }

                        if ($current_balance !== null && $current_balance < $estimated_cost) {
                            $top_up_needed = $estimated_cost - $current_balance;
                            $error = 'Insufficient SMS balance. Current balance: ' . $sms_currency . ' ' . number_format($current_balance, 2) . '. Estimated required for this send: ' . $sms_currency . ' ' . number_format($estimated_cost, 2) . ' (' . number_format($estimated_recipients) . ' recipients x ' . $message_segments . ' SMS unit(s) x ' . $sms_currency . ' ' . number_format($sms_unit_cost, 2) . '). Please top up at least ' . $sms_currency . ' ' . number_format($top_up_needed, 2) . ' and try again.';
                        } elseif ($current_balance === null) {
                            $balance_error = trim((string)($balance_result['error'] ?? ''));
                            $error = 'Unable to verify SMS balance right now' . ($balance_error !== '' ? ' (' . $balance_error . ')' : '') . '. Please refresh balance and try again.';
                        }
                    }

                    if ($error !== '') {
                        $recipients_preview = array_slice($valid_recipients, 0, 200);
                        $preview_count = count($valid_recipients);
                    } else {
                    if ($action === 'send_sms') {
                        // Sending many recipients can exceed default 30s request limits.
                        @set_time_limit(0);
                        @ini_set('max_execution_time', '0');
                    }

                    $is_immediate_send = $action === 'send_sms' && $provider !== 'none';
                    $scheduled_at = date('Y-m-d H:i:s');
                    $campaign_status = $is_immediate_send && campaignSupportsSendingStatus($pdo) ? 'sending' : 'scheduled';
                    $department_name = '';
                    foreach ($departments as $department_row) {
                        if ((string)($department_row['id'] ?? '') === (string)$department_filter) {
                            $department_name = (string)($department_row['name'] ?? '');
                            break;
                        }
                    }

                    if ($audience_mode === 'tithers') {
                        $audience_label = 'Active Tithers';
                    } elseif ($audience_mode === 'visitors') {
                        $audience_label = 'Visitors';
                    } elseif ($audience_mode === 'members_and_visitors') {
                        $audience_label = 'Members + Visitors';
                    } elseif ($audience_mode === 'single_number') {
                        $audience_label = 'Single Number: ' . $target_phone;
                    } elseif ($audience_mode === 'department' && $department_filter !== '') {
                        $audience_label = $department_name !== '' ? 'Department: ' . $department_name : 'Department ID: ' . $department_filter;
                    } elseif ($audience_mode === 'ministerial' && $ministerial_filter !== '') {
                        $audience_label = 'Ministerial: ' . $ministerial_filter;
                    } elseif ($ministerial_filter !== '') {
                        $audience_label = 'Filtered Members (' . $ministerial_filter . ')';
                    } else {
                        $audience_label = 'All Active Members';
                    }

                    try {
                        $pdo->beginTransaction();

                        $campaign_stmt = $pdo->prepare(
                            'INSERT INTO communication_campaigns (name, channel, audience, content, status, scheduled_at, sent_at, total_recipients, delivered_count, failed_count, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                        );
                        $campaign_stmt->execute([
                            mb_substr($campaign_name, 0, 180),
                            'sms',
                            mb_substr($audience_label, 0, 120),
                            $message_text,
                            $campaign_status,
                            $scheduled_at,
                            null,
                            count($valid_recipients),
                            0,
                            0,
                            (int)($_SESSION['user_id'] ?? 0) ?: null,
                        ]);

                        $campaign_id = (int)$pdo->lastInsertId();
                        $message_preview = mb_substr($message_text, 0, 255);

                        $log_stmt = $pdo->prepare(
                            'INSERT INTO communication_logs (campaign_id, channel, recipient, status, message_preview, error_detail, sent_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
                        );

                        foreach ($valid_recipients as $recipient) {
                            $recipient_label = mb_substr(((string)$recipient['name']) . ' (' . $recipient['normalized_phone'] . ')', 0, 190);
                            $log_stmt->execute([
                                $campaign_id,
                                'sms',
                                $recipient_label,
                                'queued',
                                $message_preview,
                                $is_immediate_send ? 'Pending batch send.' : 'Prepared only. Not yet sent.',
                                null,
                            ]);
                        }

                        $pdo->commit();

                        if ($is_immediate_send) {
                            $batch_result = processSmsBatchJob($pdo, $sms_config, $campaign_id);
                            if (($batch_result['ok'] ?? false) === true && ($batch_result['done'] ?? false) === false) {
                                header('Location: sms.php?continue_campaign=' . $campaign_id);
                                exit;
                            }

                            if (($batch_result['ok'] ?? false) === true) {
                                $success = 'SMS sending completed. Processed ' . (int)($batch_result['processed'] ?? 0) . '/' . (int)($batch_result['total'] ?? 0) . ' (' . (float)($batch_result['percent'] ?? 100.0) . '%). Delivered: ' . (int)($batch_result['delivered'] ?? 0) . ', Failed: ' . (int)($batch_result['failed'] ?? 0) . '.';
                            } else {
                                error_log('[sms.php] Initial batch send failed for campaign ' . $campaign_id . ': ' . (string)($batch_result['error'] ?? 'Unknown error'));
                                $error = (string)($batch_result['error'] ?? 'Unable to process SMS batch send.');
                            }
                        } else {
                            updateCampaignDeliveryStats($pdo, $campaign_id, false);
                            if ($action === 'send_sms' && $provider === 'none') {
                                $success = 'SMS provider is not configured. Campaign was queued for ' . count($valid_recipients) . ' recipients instead of sending now.';
                            } else {
                                $success = 'SMS campaign prepared for ' . count($valid_recipients) . ' recipients and queued.';
                            }
                        }

                        $recipients_preview = array_slice($valid_recipients, 0, 200);
                        $preview_count = count($valid_recipients);

                        $campaign_name = '';
                        $message_text = '';
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        error_log('[sms.php] Campaign processing failed. action=' . $action . '; campaign_name=' . $campaign_name . '; message=' . $e->getMessage());
                        $error = 'Unable to process SMS campaign right now.';
                    }
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $preview_count === 0 && empty($recipients_preview)) {
    try {
        $recipients_preview = fetchSmsRecipients($pdo, 'all_active', '', '', '', 20);
        $preview_count = count($recipients_preview);
    } catch (Exception $e) {
        $recipients_preview = [];
        $preview_count = 0;
    }
}

$today_sms = 0;
$month_sms = 0;
$queued_sms = 0;
$gateway_balance = null;
$gateway_balance_error = '';
$refresh_balance_requested = isset($_GET['refresh_balance']) && $_GET['refresh_balance'] === '1';

if ($refresh_balance_requested && session_status() === PHP_SESSION_ACTIVE) {
    unset($_SESSION['sms_balance_cached_value'], $_SESSION['sms_balance_cached_at']);
}

try {
    $sms_stats_stmt = $pdo->query(
        "SELECT
            SUM(CASE WHEN l.channel = 'sms' AND DATE(COALESCE(l.sent_at, l.created_at)) = CURDATE() THEN 1 ELSE 0 END) AS today_sms,
            SUM(CASE WHEN l.channel = 'sms' AND DATE(COALESCE(l.sent_at, l.created_at)) >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 ELSE 0 END) AS month_sms,
            SUM(CASE WHEN l.channel = 'sms' AND l.status = 'queued' THEN 1 ELSE 0 END) AS queued_sms
         FROM communication_logs l"
    );
    $sms_stats = $sms_stats_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $today_sms = (int)($sms_stats['today_sms'] ?? 0);
    $month_sms = (int)($sms_stats['month_sms'] ?? 0);
    $queued_sms = (int)($sms_stats['queued_sms'] ?? 0);
} catch (Exception $e) {
    $today_sms = 0;
    $month_sms = 0;
    $queued_sms = 0;
}

if (!$refresh_balance_requested && session_status() === PHP_SESSION_ACTIVE) {
    $cached_provider = (string)($_SESSION['sms_balance_provider'] ?? '');
    $cached_value = (string)($_SESSION['sms_balance_cached_value'] ?? '');
    if ($cached_provider === $provider && $cached_value !== '') {
        $gateway_balance = $cached_value;
    }
}

if ($refresh_balance_requested) {
    $gateway_balance = null;
    $balance_result = fetchSmsBalance($sms_config);
    if (($balance_result['ok'] ?? false) === true) {
        $gateway_balance = (string)($balance_result['balance'] ?? '');
    } else {
        $gateway_balance_error = (string)($balance_result['error'] ?? '');
    }
}

include '../../includes/header.php';
?>
<link href="../../assets/css/communication.css?v=<?php echo @filemtime('../../assets/css/communication.css'); ?>" rel="stylesheet">

<div class="container-fluid communication-page py-4">
    <?php if ($success): ?>
        <div class="alert alert-success border-0 shadow-sm mb-3"><i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <nav class="nav nav-pills communication-subnav mb-3">
        <a class="nav-link" href="dashboard.php">Overview</a>
        <a class="nav-link" href="campaigns.php">Campaigns</a>
        <a class="nav-link active" href="sms.php">SMS Center</a>
        <a class="nav-link" href="logs.php">Logs</a>
    </nav>

    <section class="row g-3 communication-stats mb-1">
        <div class="col-12 col-md-6 col-xl-3">
            <article class="communication-stat-card">
                <div class="communication-stat-icon icon-green"><i class="bi bi-chat-dots-fill"></i></div>
                <div class="communication-stat-content">
                    <span class="communication-stat-label">SMS Today</span>
                    <h3 class="communication-stat-value"><?php echo number_format($today_sms); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <article class="communication-stat-card">
                <div class="communication-stat-icon icon-blue"><i class="bi bi-calendar3"></i></div>
                <div class="communication-stat-content">
                    <span class="communication-stat-label">SMS This Month</span>
                    <h3 class="communication-stat-value"><?php echo number_format($month_sms); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <article class="communication-stat-card">
                <div class="communication-stat-icon icon-orange"><i class="bi bi-hourglass-split"></i></div>
                <div class="communication-stat-content">
                    <span class="communication-stat-label">Queued</span>
                    <h3 class="communication-stat-value"><?php echo number_format($queued_sms); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <article class="communication-stat-card">
                <div class="communication-stat-icon icon-blue"><i class="bi bi-wallet2"></i></div>
                <div class="communication-stat-content">
                    <span class="communication-stat-label">Gateway Balance</span>
                    <h3 class="communication-stat-value"><?php echo $gateway_balance !== null && $gateway_balance !== '' ? htmlspecialchars($gateway_balance) : 'N/A'; ?></h3>
                    <a class="small text-decoration-none" href="sms.php?refresh_balance=1">Refresh balance</a>
                </div>
            </article>
        </div>
    </section>

    <div class="row g-3 mt-1">
        <div class="col-12 col-xl-5">
            <div class="communication-panel h-100">
                <div class="communication-panel-head">
                    <h2><i class="bi bi-envelope-paper-fill"></i> Prepare SMS</h2>
                    <p>Select recipients, compose message, then prepare or send.</p>
                </div>

                <form method="POST" class="communication-form mt-3" id="sms-compose-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="mb-3">
                        <label class="form-label">Campaign Name</label>
                        <input type="text" class="form-control" name="campaign_name" maxlength="180" value="<?php echo htmlspecialchars($campaign_name); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Audience</label>
                        <select class="form-select" name="audience_mode">
                            <option value="all_active"<?php echo $audience_mode === 'all_active' ? ' selected' : ''; ?>>All Active Members</option>
                            <option value="tithers"<?php echo $audience_mode === 'tithers' ? ' selected' : ''; ?>>Active Tithers</option>
                            <option value="department"<?php echo $audience_mode === 'department' ? ' selected' : ''; ?>>Department Members</option>
                            <option value="ministerial"<?php echo $audience_mode === 'ministerial' ? ' selected' : ''; ?>>Ministerial Status</option>
                            <option value="visitors"<?php echo $audience_mode === 'visitors' ? ' selected' : ''; ?>>Visitors</option>
                            <option value="members_and_visitors"<?php echo $audience_mode === 'members_and_visitors' ? ' selected' : ''; ?>>Members + Visitors</option>
                            <option value="single_number"<?php echo $audience_mode === 'single_number' ? ' selected' : ''; ?>>Single Number (Manual)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Single Number (for Single Number audience)</label>
                        <input type="text" class="form-control" name="target_phone" maxlength="30" placeholder="e.g. 0243838490 or +233243838490" value="<?php echo htmlspecialchars($target_phone); ?>">
                        <small class="text-muted">Use this when audience is set to Single Number (Manual).</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Department (for Department audience)</label>
                        <select class="form-select" name="department_id">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?php echo (int)$department['id']; ?>"<?php echo $department_filter === (string)$department['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars((string)$department['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Ministerial Status (for Ministerial audience)</label>
                        <select class="form-select" name="ministerial_status">
                            <option value="">All / Not Selected</option>
                            <?php foreach ($ministerial_statuses as $ministerial_status): ?>
                                <option value="<?php echo htmlspecialchars($ministerial_status); ?>"<?php echo $ministerial_filter === $ministerial_status ? ' selected' : ''; ?>><?php echo htmlspecialchars($ministerial_status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">SMS Message</label>
                        <textarea class="form-control" name="message_text" rows="6" maxlength="1000" required><?php echo htmlspecialchars($message_text); ?></textarea>
                        <small class="text-muted">Keep SMS concise for better delivery. Max 1000 characters.</small>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" name="action" value="preview" class="btn btn-outline-primary">
                            <i class="bi bi-search me-1"></i> Preview Recipients
                        </button>
                        <button type="button" class="btn btn-outline-info" id="estimate-cost-popup-btn">
                            <i class="bi bi-calculator me-1"></i> Estimate SMS Cost (Popup)
                        </button>
                        <button type="submit" name="action" value="prepare_sms" class="btn btn-outline-secondary">
                            <i class="bi bi-save2 me-1"></i> Prepare & Queue SMS
                        </button>
                        <button type="submit" name="action" value="send_sms" class="btn btn-primary">
                            <i class="bi bi-send-fill me-1"></i> Send SMS Now
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-12 col-xl-7">
            <div class="communication-panel h-100">
                <div class="communication-panel-head">
                    <h2><i class="bi bi-people-fill"></i> Recipient Preview</h2>
                    <p><?php echo number_format($preview_count); ?> recipients match current filters.</p>
                </div>

                <div class="communication-table-wrap mt-3">
                    <table class="table communication-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th class="communication-col-title">Member</th>
                                <th>Department</th>
                                <th>Ministerial</th>
                                <th>Phone</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recipients_preview)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No recipient preview available.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recipients_preview as $index => $recipient): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td class="communication-col-title"><?php echo htmlspecialchars((string)$recipient['name']); ?></td>
                                        <td><?php echo htmlspecialchars((string)($recipient['department_name'] ?: '-')); ?></td>
                                        <td><?php echo htmlspecialchars((string)($recipient['ministerial_status'] ?: '-')); ?></td>
                                        <td><?php echo htmlspecialchars((string)($recipient['phone'] ?? $recipient['normalized_phone'] ?? '-')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="smsCostModal" tabindex="-1" aria-labelledby="smsCostModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="smsCostModalLabel">SMS Cost Estimate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" id="sms-cost-modal-summary">Calculating...</p>
                <div class="small text-muted" id="sms-cost-modal-breakdown"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('sms-compose-form');
    const popupButton = document.getElementById('estimate-cost-popup-btn');
    const modalElement = document.getElementById('smsCostModal');
    const modalSummary = document.getElementById('sms-cost-modal-summary');
    const modalBreakdown = document.getElementById('sms-cost-modal-breakdown');

    if (!form || !popupButton || !modalElement || !modalSummary || !modalBreakdown) {
        return;
    }

    const unitCost = <?php echo json_encode((float)$sms_unit_cost); ?>;
    const currency = <?php echo json_encode((string)$sms_currency); ?>;

    const audienceInput = form.querySelector('select[name="audience_mode"]');
    const departmentInput = form.querySelector('select[name="department_id"]');
    const ministerialInput = form.querySelector('select[name="ministerial_status"]');
    const targetPhoneInput = form.querySelector('input[name="target_phone"]');
    const messageInput = form.querySelector('textarea[name="message_text"]');

    if (!audienceInput || !departmentInput || !ministerialInput || !targetPhoneInput || !messageInput) {
        return;
    }

    function requestEstimate() {
        const messageText = (messageInput.value || '').trim();
        if (messageText === '') {
            return Promise.resolve(null);
        }

        const params = new URLSearchParams({
            estimate_live: '1',
            audience_mode: audienceInput.value || 'all_active',
            department_id: departmentInput.value || '',
            ministerial_status: ministerialInput.value || '',
            target_phone: targetPhoneInput.value || '',
            message_text: messageInput.value || ''
        });

        return fetch('sms.php?' + params.toString(), { method: 'GET', credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) { return data; })
            .catch(function () {
                return null;
            });
    }

    function openEstimateModal() {
        modalSummary.textContent = 'Calculating...';
        modalBreakdown.textContent = '';

        requestEstimate().then(function (data) {
            if (!data || data.ok !== true) {
                modalSummary.textContent = 'Unable to estimate SMS cost right now.';
                modalBreakdown.textContent = 'Please check your inputs and try again.';
            } else {
                const recipients = Number(data.recipients || 0);
                const segments = Number(data.segments || 0);
                const units = Number(data.units || 0);
                const configured = data.unit_cost_configured === true;
                const cost = Number(data.cost || 0);

                if (recipients <= 0 || segments <= 0) {
                    modalSummary.textContent = 'No recipients matched current filters.';
                    modalBreakdown.textContent = 'Adjust audience filters and try again.';
                } else if (configured && unitCost > 0) {
                    modalSummary.textContent = 'Estimated Cost: ' + currency + ' ' + cost.toFixed(2);
                    modalBreakdown.textContent = 'Based on current audience and message.';
                } else {
                    modalSummary.textContent = 'Estimated SMS units: ' + units.toLocaleString();
                    modalBreakdown.textContent = '';
                }
            }

            if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
                const modal = new window.bootstrap.Modal(modalElement);
                modal.show();
            } else {
                alert(modalSummary.textContent + '\n' + modalBreakdown.textContent);
            }
        });
    }

    popupButton.addEventListener('click', openEstimateModal);
});
</script>

<?php include '../../includes/footer.php'; ?>
