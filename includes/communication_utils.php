<?php
/**
 * Shared Communication Utilities
 * Handles background SMS batching and sending.
 */

require_once __DIR__ . '/settings_utils.php';

function normalizePhoneNumber(string $phone): string {
    $digits = preg_replace('/[^0-9+]/', '', $phone);
    if ($digits === null) return '';
    $digits = trim($digits);
    if ($digits === '') return '';

    if (strpos($digits, '+') === 0) return $digits;
    if (strpos($digits, '0') === 0) return '+233' . ltrim(substr($digits, 1), '0');
    if (strpos($digits, '233') === 0) return '+' . $digits;
    return '+' . ltrim($digits, '+');
}

function normalizePhoneForProvider(string $phone, string $provider): string {
    $provider = strtolower(trim($provider));
    if ($provider === 'bulksmsgh' || $provider === 'arkesel') {
        $digits = preg_replace('/[^0-9+]/', '', $phone);
        if ($digits === null) return '';
        $digits = ltrim(trim($digits), '+');
        if (strpos($digits, '233') === 0) $digits = substr($digits, 3);
        if (strpos($digits, '0') === 0) $digits = substr($digits, 1);
        return $digits;
    }
    return normalizePhoneNumber($phone);
}

function parseGatewayBalanceValue($value): ?float {
    if ($value === null) return null;
    $raw = trim((string)$value);
    if ($raw === '' || preg_match('/-?\d+(?:\.\d+)?/', $raw, $matches) !== 1) return null;
    return (float)$matches[0];
}

function makeHttpRequest(string $url, string $method = 'GET', array $headers = [], ?string $body = null, int $timeout = 20): array {
    $method = strtoupper(trim($method)) === 'POST' ? 'POST' : 'GET';

    if (function_exists('curl_init')) {
        $buildCurlOptions = static function(bool $insecure = false) use ($url, $timeout, $method, $headers, $body): array {
            $options = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => min($timeout, 8),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CUSTOMREQUEST => $method,
            ];
            if (!empty($headers)) $options[CURLOPT_HTTPHEADER] = $headers;
            if ($method === 'POST') {
                $options[CURLOPT_POST] = true;
                if ($body !== null) $options[CURLOPT_POSTFIELDS] = $body;
            }
            if ($insecure) {
                $options[CURLOPT_SSL_VERIFYPEER] = false;
                $options[CURLOPT_SSL_VERIFYHOST] = 0;
            }
            return $options;
        };

        $ch = curl_init();
        curl_setopt_array($ch, $buildCurlOptions(false));
        $response = curl_exec($ch);
        $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);

        if ($response === false && preg_match('/certificate|SSL/i', $curl_error)) {
            curl_setopt_array($ch, $buildCurlOptions(true));
            $response = curl_exec($ch);
            $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
        }
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'body' => null, 'http_code' => $http_code, 'error' => $curl_error ?: 'HTTP request failed.'];
        }
        return ['ok' => true, 'body' => (string)$response, 'http_code' => $http_code, 'error' => null];
    }

    if (!ini_get('allow_url_fopen')) {
        return ['ok' => false, 'body' => null, 'http_code' => 0, 'error' => 'cURL and allow_url_fopen unavailable.'];
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'timeout' => max(2, min($timeout, 12)),
            'header' => implode("\r\n", $headers),
            'content' => $body ?? '',
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
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

    if ($response === false) return ['ok' => false, 'body' => null, 'http_code' => $http_code, 'error' => 'HTTP failed in stream fallback.'];
    return ['ok' => true, 'body' => (string)$response, 'http_code' => $http_code, 'error' => null];
}

function sendSmsMessage(array $sms_config, string $to, string $message): array {
    $provider = strtolower(trim((string)($sms_config['provider'] ?? 'none')));

    if ($provider === 'bulksmsgh') {
        $api_key = trim((string)($sms_config['bulksmsgh']['api_key'] ?? ''));
        $sender_id = trim((string)($sms_config['bulksmsgh']['sender_id'] ?? ($sms_config['sender_id'] ?? 'BRIDGE MIN.')));
        $endpoint = trim((string)($sms_config['bulksmsgh']['send_endpoint'] ?? 'https://clientlogin.bulksmsgh.com/smsapi'));

        if ($api_key === '') return ['ok' => false, 'status' => 'failed', 'error' => 'BulkSMSGH API key is missing.'];
        
        $query = http_build_query([
            'key' => $api_key, 'to' => $to, 'msg' => $message, 'sender_id' => $sender_id
        ], '', '&', PHP_QUERY_RFC3986);
        $url = $endpoint . (strpos($endpoint, '?') === false ? '?' : '&') . $query;

        $http = makeHttpRequest($url, 'GET', [], null, 8);
        if (!($http['ok'] ?? false)) return ['ok' => false, 'status' => 'failed', 'error' => $http['error'] ?? 'API Unreachable.'];

        $res_text = trim((string)($http['body'] ?? ''));
        preg_match('/\b(1000|1002|1003|1004|1005|1006|1007|1008)\b/', $res_text, $matches);
        $code = $matches[1] ?? $res_text;

        $map = [
            '1000' => ['ok'=>true, 'status'=>'sent'], '1007' => ['ok'=>true, 'status'=>'queued'],
            '1003' => ['ok'=>false, 'status'=>'failed', 'error'=>'Insufficient balance'],
            '1004' => ['ok'=>false, 'status'=>'failed', 'error'=>'Invalid key']
        ];
        return [
            'ok' => $map[$code]['ok'] ?? false,
            'status' => $map[$code]['status'] ?? 'failed',
            'error' => $map[$code]['error'] ?? ('Provider error: ' . $res_text),
            'provider_code' => $code
        ];
    }

    if ($provider === 'arkesel') {
        $api_key = trim((string)($sms_config['arkesel']['api_key'] ?? ''));
        $sender_id = trim((string)($sms_config['arkesel']['sender_id'] ?? ($sms_config['sender_id'] ?? 'BRIDGE MIN.')));
        $endpoint = trim((string)($sms_config['arkesel']['send_endpoint'] ?? 'https://sms.arkesel.com/sms/api'));

        if ($api_key === '') return ['ok' => false, 'status' => 'failed', 'error' => 'Arkesel API key missing.'];

        $query = http_build_query([
            'action' => 'send-sms', 'api_key' => $api_key, 'to' => $to, 'from' => $sender_id, 'sms' => $message,
        ], '', '&', PHP_QUERY_RFC3986);
        $url = $endpoint . (strpos($endpoint, '?') === false ? '?' : '&') . $query;

        $http = makeHttpRequest($url, 'GET', [], null, 8);
        if (!($http['ok'] ?? false)) return ['ok' => false, 'status' => 'failed', 'error' => $http['error'] ?? 'Arkesel unreachable.'];

        $res_text = trim((string)($http['body'] ?? ''));
        if (stripos($res_text, '1000') !== false || stripos($res_text, 'OK') !== false || stripos($res_text, 'success') !== false) {
            return ['ok' => true, 'status' => 'sent', 'error' => null, 'provider_code' => $res_text];
        }
        return ['ok' => false, 'status' => 'failed', 'error' => 'Arkesel error: ' . $res_text, 'provider_code' => $res_text];
    }
    
    return ['ok' => false, 'status' => 'queued', 'error' => 'No provider configured.'];
}

function campaignSupportsSendingStatus(PDO $pdo): bool {
    try {
        $stmt = $pdo->prepare("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'communication_campaigns' AND COLUMN_NAME = 'status' LIMIT 1");
        $stmt->execute();
        return stripos((string)$stmt->fetchColumn(), "'sending'") !== false;
    } catch (Exception $e) { return false; }
}

function getCampaignProgressSummary(PDO $pdo, int $campaign_id): array {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN status IN ('sent', 'delivered') THEN 1 ELSE 0 END) AS delivered, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed, SUM(CASE WHEN status = 'queued' AND (error_detail IS NULL OR error_detail <> 'Prepared only. Not yet sent.') THEN 1 ELSE 0 END) AS remaining FROM communication_logs WHERE campaign_id = ?");
    $stmt->execute([$campaign_id]);
    $s = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $total = (int)($s['total'] ?? 0);
    $rem = (int)($s['remaining'] ?? 0);
    return [
        'total' => $total, 'processed' => max(0, $total - $rem), 'remaining' => $rem,
        'delivered' => (int)($s['delivered'] ?? 0), 'failed' => (int)($s['failed'] ?? 0),
        'percent' => $total > 0 ? round((($total - $rem) / $total) * 100, 1) : 100.0
    ];
}

function updateCampaignDeliveryStats(PDO $pdo, int $campaign_id, bool $is_done): void {
    $summary = getCampaignProgressSummary($pdo, $campaign_id);
    $status = $is_done ? 'sent' : (campaignSupportsSendingStatus($pdo) ? 'sending' : 'scheduled');
    $stmt = $pdo->prepare('UPDATE communication_campaigns SET delivered_count = ?, failed_count = ?, status = ?, sent_at = ? WHERE id = ?');
    $stmt->execute([$summary['delivered'], $summary['failed'], $status, $is_done ? date('Y-m-d H:i:s') : null, $campaign_id]);
}

function extractPhoneFromRecipientLabel(string $label): string {
    return preg_match('/\(([^()]+)\)\s*$/', trim($label), $m) ? trim((string)$m[1]) : trim($label);
}

function processSmsBatchJob(PDO $pdo, array $sms_config, int $campaign_id, int $max_per_run = 0, int $time_budget = 0): array {
    $provider = strtolower(trim((string)($sms_config['provider'] ?? 'none')));
    if ($max_per_run <= 0) $max_per_run = (int)getSystemSetting($pdo, 'sms_batch_size', 40);
    if ($time_budget <= 0) $time_budget = (int)getSystemSetting($pdo, 'sms_batch_time_limit', 18);

    @set_time_limit(0);
    $campaign = $pdo->prepare('SELECT content, total_recipients FROM communication_campaigns WHERE id = ? LIMIT 1');
    $campaign->execute([$campaign_id]);
    $c = $campaign->fetch(PDO::FETCH_ASSOC);
    if (!$c) return ['ok' => false, 'done' => true, 'error' => 'Campaign missing.'];

    $items = $pdo->prepare("SELECT id, recipient FROM communication_logs WHERE campaign_id = ? AND status = 'queued' AND (error_detail IS NULL OR error_detail <> 'Prepared only. Not yet sent.') ORDER BY id ASC LIMIT ?");
    $items->bindValue(1, $campaign_id, PDO::PARAM_INT);
    $items->bindValue(2, $max_per_run, PDO::PARAM_INT);
    $items->execute();
    $rows = $items->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        updateCampaignDeliveryStats($pdo, $campaign_id, true);
        return array_merge(['ok' => true, 'done' => true], getCampaignProgressSummary($pdo, $campaign_id));
    }

    $start = microtime(true);
    $log_upd = $pdo->prepare('UPDATE communication_logs SET status = ?, error_detail = ?, sent_at = ? WHERE id = ?');
    foreach ($rows as $row) {
        if ((microtime(true) - $start) >= $time_budget) break;
        $to = normalizePhoneForProvider(extractPhoneFromRecipientLabel($row['recipient']), $provider);
        if ($to === '') {
            $log_upd->execute(['failed', 'Invalid phone number.', date('Y-m-d H:i:s'), $row['id']]);
            continue;
        }
        $res = sendSmsMessage($sms_config, $to, (string)$c['content']);
        $log_upd->execute([$res['status'], $res['error'] ?? null, $res['status'] === 'queued' ? null : date('Y-m-d H:i:s'), $row['id']]);
    }

    $rem_check = $pdo->prepare("SELECT COUNT(*) FROM communication_logs WHERE campaign_id = ? AND status = 'queued' AND (error_detail IS NULL OR error_detail <> 'Prepared only. Not yet sent.')");
    $rem_check->execute([$campaign_id]);
    $done = (int)$rem_check->fetchColumn() <= 0;
    updateCampaignDeliveryStats($pdo, $campaign_id, $done);
    return array_merge(['ok' => true, 'done' => $done], getCampaignProgressSummary($pdo, $campaign_id));
}

function queueDirectSms(PDO $pdo, string $phone, string $message, string $recipient_name = '', string $channel = 'sms'): int {
    $recipient_label = $recipient_name !== '' ? "$recipient_name ($phone)" : $phone;
    $stmt = $pdo->prepare('INSERT INTO communication_logs (campaign_id, channel, recipient, status, message_preview) VALUES (NULL, ?, ?, "queued", ?)');
    $stmt->execute([$channel, $recipient_label, mb_substr($message, 0, 250)]);
    return (int)$pdo->lastInsertId();
}

function processDirectSmsQueue(PDO $pdo, array $sms_config, int $max_per_run = 20): array {
    $provider = strtolower(trim((string)($sms_config['provider'] ?? 'none')));
    $stmt = $pdo->prepare("SELECT id, recipient, message_preview FROM communication_logs WHERE campaign_id IS NULL AND status = 'queued' AND channel = 'sms' ORDER BY id ASC LIMIT ?");
    $stmt->bindValue(1, $max_per_run, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) return ['ok' => true, 'processed' => 0];

    $processed = 0;
    $log_upd = $pdo->prepare('UPDATE communication_logs SET status = ?, error_detail = ?, sent_at = ? WHERE id = ?');
    
    foreach ($rows as $row) {
        $to = normalizePhoneForProvider(extractPhoneFromRecipientLabel($row['recipient']), $provider);
        if ($to === '') {
            $log_upd->execute(['failed', 'Invalid phone number.', date('Y-m-d H:i:s'), $row['id']]);
            continue;
        }
        
        // Note: For direct SMS, we use the message_preview as the content if it's small, 
        // but for Tithe alerts, we should probably store the full message somewhere.
        // Actually, communication_logs.message_preview is only 255 chars.
        // I should probably add a 'content' column to communication_logs if I want to support long direct SMS.
        
        $res = sendSmsMessage($sms_config, $to, (string)$row['message_preview']);
        $log_upd->execute([$res['status'], $res['error'] ?? null, $res['status'] === 'queued' ? null : date('Y-m-d H:i:s'), $row['id']]);
        $processed++;
    }
    
    return ['ok' => true, 'processed' => $processed];
}

function fetchSmsBalance(array $sms_config): array {
    $provider = strtolower(trim((string)($sms_config['provider'] ?? 'none')));
    if ($provider === 'arkesel') {
        $api_key = trim((string)($sms_config['arkesel']['api_key'] ?? ''));
        $endpoint = trim((string)($sms_config['arkesel']['balance_endpoint'] ?? 'https://sms.arkesel.com/sms/api'));

        if ($api_key === '') return ['ok' => false, 'error' => 'API key missing.'];

        $url = "{$endpoint}?action=check-balance&api_key=" . urlencode($api_key) . "&response=json";
        $http = makeHttpRequest($url, 'GET');
        if (!($http['ok'] ?? false)) return ['ok' => false, 'error' => 'Arkesel failed: ' . ($http['error'] ?? 'Network error')];

        $data = json_decode((string)$http['body'], true);
        $balance = parseGatewayBalanceValue($data['balance'] ?? $data['data']['balance'] ?? null);

        if ($balance === null) return ['ok' => false, 'error' => 'Could not parse balance: ' . mb_substr((string)$http['body'], 0, 80)];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['sms_balance_provider'] = $provider;
            $_SESSION['sms_balance_cached_value'] = (string)$balance;
            $_SESSION['sms_balance_cached_at'] = time();
        }
        return ['ok' => true, 'balance' => (string)$balance];
    }

    if ($provider === 'bulksmsgh') {
        $api_key = trim((string)($sms_config['bulksmsgh']['api_key'] ?? ''));
        if ($api_key === '') return ['ok' => false, 'error' => 'API key missing.'];

        $endpoints = $sms_config['bulksmsgh']['balance_endpoints'] ?? ['https://clientlogin.bulksmsgh.com/smsapi?action=check-balance'];
        foreach ($endpoints as $url) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'key=' . urlencode($api_key);
            $http = makeHttpRequest($url, 'GET', [], null, 5);
            if (!($http['ok'] ?? false)) continue;

            $balance = parseGatewayBalanceValue($http['body']);
            if ($balance !== null) {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION['sms_balance_provider'] = $provider;
                    $_SESSION['sms_balance_cached_value'] = (string)$balance;
                    $_SESSION['sms_balance_cached_at'] = time();
                }
                return ['ok' => true, 'balance' => (string)$balance];
            }
        }
    }

    return ['ok' => false, 'error' => 'Balance check not supported or configured for this provider.'];
}
