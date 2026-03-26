<?php
require_once '../../includes/security.php';
requireLogin('../../login.php');
require_once '../../config/database.php';

$page_title = 'Finance Revenue - Bridge Ministries International';
$page_heading = 'Revenue';
$page_header = false;

$income_categories = [
    'Tithe',
    'Offering',
    'Donation',
    'Special Seed',
    'Fundraising',
    'Pledge',
    'Other',
];

$payment_methods = [
    'Cash',
    'Mobile Money',
    'Bank Transfer',
    'Cheque',
    'Card',
    'Other',
];

$sms_config = require '../../config/sms_config.php';

function normalizePhoneForSms(string $phone): string {
    $digits = preg_replace('/[^0-9+]/', '', $phone);
    if ($digits === null) {
        return '';
    }

    $digits = trim($digits);
    if ($digits === '') {
        return '';
    }

    // Remove leading + and country code prefixes
    $digits = ltrim($digits, '+');
    
    // Remove 233 prefix if present (Ghana country code)
    if (strpos($digits, '233') === 0) {
        $digits = substr($digits, 3);
    }
    
    // Remove leading 0 if present
    if (strpos($digits, '0') === 0) {
        $digits = substr($digits, 1);
    }

    return $digits;
}

function sendBulkSmsGhMessage(array $sms_config, string $to, string $message): array {
    $provider = strtolower(trim((string)($sms_config['provider'] ?? 'none')));
    if ($provider !== 'bulksmsgh') {
        return ['ok' => false, 'error' => 'SMS provider is not configured for BulkSMSGH.'];
    }

    $api_key = trim((string)($sms_config['bulksmsgh']['api_key'] ?? ''));
    $sender_id = trim((string)($sms_config['bulksmsgh']['sender_id'] ?? ($sms_config['sender_id'] ?? 'BRIDGE MIN.')));
    $endpoint = trim((string)($sms_config['bulksmsgh']['send_endpoint'] ?? 'https://clientlogin.bulksmsgh.com/smsapi'));

    if ($api_key === '') {
        return ['ok' => false, 'error' => 'BulkSMSGH API key is missing.'];
    }

    if ($sender_id === '' || mb_strlen($sender_id) > 11) {
        return ['ok' => false, 'error' => 'BulkSMSGH sender id must be 1 to 11 characters.'];
    }

    $query = http_build_query([
        'key' => $api_key,
        'to' => $to,
        'msg' => $message,
        'sender_id' => $sender_id,
    ], '', '&', PHP_QUERY_RFC3986);

    $url = $endpoint . (strpos($endpoint, '?') === false ? '?' : '&') . $query;

    $response = null;

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'error' => $curl_error !== '' ? $curl_error : 'Failed to reach BulkSMSGH endpoint.'];
        }
    } elseif (ini_get('allow_url_fopen')) {
        $response = @file_get_contents($url);
        if ($response === false) {
            return ['ok' => false, 'error' => 'HTTP request failed while contacting BulkSMSGH endpoint.'];
        }
    } else {
        return ['ok' => false, 'error' => 'SMS transport unavailable: enable cURL or allow_url_fopen.'];
    }

    $response_text = trim((string)$response);

    $parsed_response = json_decode($response_text, true);
    $campaign_id = null;
    if (is_array($parsed_response)) {
        $code = isset($parsed_response['code']) ? (string)$parsed_response['code'] : $response_text;
        $campaign_id = isset($parsed_response['data']['campaign_id']) ? (string)$parsed_response['data']['campaign_id'] : null;
    } else {
        preg_match('/\b(1000|1002|1003|1004|1005|1006|1007|1008)\b/', $response_text, $matches);
        $code = $matches[1] ?? $response_text;
    }

    if ($code === '1000' || $code === '1007') {
        return ['ok' => true, 'error' => null, 'provider_code' => $code, 'campaign_id' => $campaign_id];
    }

    $code_errors = [
        '1002' => 'SMS sending failed.',
        '1003' => 'Insufficient SMS balance.',
        '1004' => 'Invalid API key.',
        '1005' => 'Invalid phone number.',
        '1006' => 'Invalid sender id.',
        '1008' => 'Empty message body.',
    ];

    return [
        'ok' => false,
        'error' => $code_errors[$code] ?? ('Unexpected SMS response: ' . mb_substr($response_text, 0, 120)),
        'provider_code' => $code,
        'campaign_id' => $campaign_id,
    ];
}

$tithers = [];
$tither_lookup = [];
$tithe_books = [];

try {
    $tither_stmt = $pdo->query(
        "SELECT t.id, t.full_name, t.phone, t.status, t.tithe_book_id, b.book_number, p.phone AS member_phone
         FROM tithers t
         LEFT JOIN tithe_books b ON b.id = t.tithe_book_id
         LEFT JOIN member_roles m ON m.id = t.member_id
         LEFT JOIN people p ON p.id = m.person_id
         WHERE t.status = 'active'
         ORDER BY t.full_name ASC"
    );
    $tithers = $tither_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tithers as $tither) {
        $tither_lookup[(int)$tither['id']] = $tither;
    }

    $book_stmt = $pdo->query("SELECT id, book_number, status FROM tithe_books WHERE status IN ('available', 'assigned') ORDER BY book_number ASC");
    $tithe_books = $book_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tithers = [];
    $tithe_books = [];
}

$success = '';
$error = '';

$edit_row = null;
$form_values = [
    'transaction_date' => date('Y-m-d'),
    'category' => '',
    'amount' => '',
    'payment_method' => '',
    'reference_no' => '',
    'description' => '',
    'tither_id' => '',
    'tithe_book_id' => '',
    'paid_for_month' => date('Y-m'),
];
$form_action = 'add_income';
$submit_label = 'Post Revenue Entry';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['add_income', 'update_income', 'delete_income'], true)) {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        if ($action === 'delete_income') {
            $transaction_id = (int)($_POST['transaction_id'] ?? 0);
            if ($transaction_id <= 0) {
                $error = 'Invalid income entry selected.';
            } else {
                try {
                    $delete_stmt = $pdo->prepare('DELETE FROM finance_transactions WHERE id = ? AND type = ?');
                    $delete_stmt->execute([$transaction_id, 'income']);
                    if ($delete_stmt->rowCount() > 0) {
                        $success = 'Revenue entry deleted successfully.';
                    } else {
                        $error = 'Revenue entry not found or already removed.';
                    }
                } catch (Exception $e) {
                    $error = 'Unable to delete revenue entry right now.';
                }
            }
        }

        $transaction_date = trim($_POST['transaction_date'] ?? date('Y-m-d'));
        $category = trim($_POST['category'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $payment_method = trim($_POST['payment_method'] ?? '');
        $reference_no = trim($_POST['reference_no'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $tither_id = (int)($_POST['tither_id'] ?? 0);
        $tithe_book_id = (int)($_POST['tithe_book_id'] ?? 0);
        $paid_for_month = trim($_POST['paid_for_month'] ?? '');

        if ($action !== 'delete_income') {
            $form_values = [
                'transaction_date' => $transaction_date,
                'category' => $category,
                'amount' => (string)($_POST['amount'] ?? ''),
                'payment_method' => $payment_method,
                'reference_no' => $reference_no,
                'description' => $description,
                'tither_id' => $tither_id > 0 ? (string)$tither_id : '',
                'tithe_book_id' => $tithe_book_id > 0 ? (string)$tithe_book_id : '',
                'paid_for_month' => $paid_for_month,
            ];
        }

        if ($action !== 'delete_income' && !DateTime::createFromFormat('Y-m-d', $transaction_date)) {
            $error = 'Please provide a valid transaction date.';
        } elseif ($action !== 'delete_income' && !in_array($category, $income_categories, true)) {
            $error = 'Please select a valid revenue category.';
        } elseif ($action !== 'delete_income' && $amount <= 0) {
            $error = 'Amount must be greater than zero.';
        } elseif ($action !== 'delete_income' && $payment_method !== '' && !in_array($payment_method, $payment_methods, true)) {
            $error = 'Please select a valid payment method.';
        } elseif ($action !== 'delete_income' && $category === 'Tithe' && $tither_id <= 0) {
            $error = 'Please select a tither for tithe revenue.';
        } elseif ($action !== 'delete_income' && $category === 'Tithe' && !preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $paid_for_month)) {
            $error = 'Please select a valid month this tithe is paying for.';
        } elseif ($action === 'add_income') {
            try {
                if ($category === 'Tithe') {
                    if (!isset($tither_lookup[$tither_id])) {
                        throw new Exception('Selected tither was not found.');
                    }

                    if ($tithe_book_id <= 0) {
                        $assigned_book_id = (int)($tither_lookup[$tither_id]['tithe_book_id'] ?? 0);
                        if ($assigned_book_id > 0) {
                            $tithe_book_id = $assigned_book_id;
                        }
                    }

                    if ($tithe_book_id > 0 && !empty($tither_lookup[$tither_id]['tithe_book_id']) && (int)$tither_lookup[$tither_id]['tithe_book_id'] !== $tithe_book_id) {
                        throw new Exception('Selected tithe book does not match assigned tither book.');
                    }
                }

                $paid_for_month_date = null;
                if ($category === 'Tithe' && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $paid_for_month)) {
                    $paid_for_month_date = $paid_for_month . '-01';
                }

                if ($category !== 'Tithe') {
                    $tither_id = 0;
                    $tithe_book_id = 0;
                    $paid_for_month_date = null;
                }

                $insert_stmt = $pdo->prepare(
                    'INSERT INTO finance_transactions (transaction_date, type, category, amount, payment_method, reference_no, description, recorded_by_user_id, tither_id, tithe_book_id, paid_for_month) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $insert_stmt->execute([
                    $transaction_date,
                    'income',
                    $category,
                    $amount,
                    $payment_method !== '' ? $payment_method : null,
                    $reference_no !== '' ? mb_substr($reference_no, 0, 100) : null,
                    $description !== '' ? $description : null,
                    (int)($_SESSION['user_id'] ?? 0) ?: null,
                    $tither_id > 0 ? $tither_id : null,
                    $tithe_book_id > 0 ? $tithe_book_id : null,
                    $paid_for_month_date,
                ]);
                $transaction_id = (int)$pdo->lastInsertId();

                $sms_notice = '';
                $sms_provider_code = null;
                $sms_campaign_id = null;
                $sms_status = null;
                $sms_error = null;
                $sms_sent_at = null;
                if ($category === 'Tithe' && $tither_id > 0 && isset($tither_lookup[$tither_id])) {
                    $tither_name = (string)($tither_lookup[$tither_id]['full_name'] ?? 'Tither');
                    $raw_phone = (string)($tither_lookup[$tither_id]['phone'] ?? $tither_lookup[$tither_id]['member_phone'] ?? '');
                    $normalized_phone = normalizePhoneForSms($raw_phone);
                    $month_label = $paid_for_month_date !== null ? date('F Y', strtotime($paid_for_month_date)) : date('F Y', strtotime($transaction_date));

                    if ($normalized_phone !== '') {
                        $sms_message = "Dear {$tither_name}, we have received your tithe payment of GHS " . number_format($amount, 2) . " for {$month_label}. God bless you. - Bridge Ministries";
                        $sms_result = sendBulkSmsGhMessage($sms_config, $normalized_phone, $sms_message);
                        $sms_provider_code = !empty($sms_result['provider_code']) ? (string)$sms_result['provider_code'] : null;
                        $sms_campaign_id = !empty($sms_result['campaign_id']) ? (string)$sms_result['campaign_id'] : null;
                        if (!($sms_result['ok'] ?? false)) {
                            $sms_status = 'failed';
                            $sms_error = (string)($sms_result['error'] ?? 'Unknown error');
                            $sms_notice = ' Tithe saved, but SMS submission failed: ' . (string)($sms_result['error'] ?? 'Unknown error') . '.';
                        } else {
                            $sms_status = 'submitted';
                            $sms_sent_at = date('Y-m-d H:i:s');
                            $sms_notice = ' SMS request submitted for delivery.';
                        }
                    } else {
                        $sms_status = 'skipped';
                        $sms_error = 'No valid phone number found for SMS receipt.';
                        $sms_notice = ' Tithe saved, but no valid phone number found for SMS receipt.';
                    }

                    $sms_update_stmt = $pdo->prepare(
                        'UPDATE finance_transactions SET sms_provider_code = ?, sms_campaign_id = ?, sms_status = ?, sms_error = ?, sms_sent_at = ? WHERE id = ?'
                    );
                    $sms_update_stmt->execute([
                        $sms_provider_code,
                        $sms_campaign_id,
                        $sms_status,
                        $sms_error,
                        $sms_sent_at,
                        $transaction_id,
                    ]);
                }

                $success = 'Revenue entry recorded successfully.' . $sms_notice;
                $form_values = [
                    'transaction_date' => date('Y-m-d'),
                    'category' => '',
                    'amount' => '',
                    'payment_method' => '',
                    'reference_no' => '',
                    'description' => '',
                    'tither_id' => '',
                    'tithe_book_id' => '',
                    'paid_for_month' => date('Y-m'),
                ];
            } catch (Exception $e) {
                $error = $e->getMessage() ?: 'Unable to save revenue entry right now.';
            }
        } elseif ($action === 'update_income') {
            $transaction_id = (int)($_POST['transaction_id'] ?? 0);
            if ($transaction_id <= 0) {
                $error = 'Invalid revenue entry selected for update.';
            } else {
                try {
                    if ($category === 'Tithe') {
                        if (!isset($tither_lookup[$tither_id])) {
                            throw new Exception('Selected tither was not found.');
                        }

                        if ($tithe_book_id <= 0) {
                            $assigned_book_id = (int)($tither_lookup[$tither_id]['tithe_book_id'] ?? 0);
                            if ($assigned_book_id > 0) {
                                $tithe_book_id = $assigned_book_id;
                            }
                        }

                        if ($tithe_book_id > 0 && !empty($tither_lookup[$tither_id]['tithe_book_id']) && (int)$tither_lookup[$tither_id]['tithe_book_id'] !== $tithe_book_id) {
                            throw new Exception('Selected tithe book does not match assigned tither book.');
                        }
                    }

                    $paid_for_month_date = null;
                    if ($category === 'Tithe' && preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $paid_for_month)) {
                        $paid_for_month_date = $paid_for_month . '-01';
                    }

                    if ($category !== 'Tithe') {
                        $tither_id = 0;
                        $tithe_book_id = 0;
                        $paid_for_month_date = null;
                    }

                    $update_stmt = $pdo->prepare(
                        'UPDATE finance_transactions SET transaction_date = ?, category = ?, amount = ?, payment_method = ?, reference_no = ?, description = ?, recorded_by_user_id = ?, tither_id = ?, tithe_book_id = ?, paid_for_month = ? WHERE id = ? AND type = ?'
                    );
                    $update_stmt->execute([
                        $transaction_date,
                        $category,
                        $amount,
                        $payment_method !== '' ? $payment_method : null,
                        $reference_no !== '' ? mb_substr($reference_no, 0, 100) : null,
                        $description !== '' ? $description : null,
                        (int)($_SESSION['user_id'] ?? 0) ?: null,
                        $tither_id > 0 ? $tither_id : null,
                        $tithe_book_id > 0 ? $tithe_book_id : null,
                        $paid_for_month_date,
                        $transaction_id,
                        'income',
                    ]);
                    if ($update_stmt->rowCount() > 0) {
                        $success = 'Revenue entry updated successfully.';
                    } else {
                        $success = 'No changes were made to this revenue entry.';
                    }
                    $form_values = [
                        'transaction_date' => date('Y-m-d'),
                        'category' => '',
                        'amount' => '',
                        'payment_method' => '',
                        'reference_no' => '',
                        'description' => '',
                        'tither_id' => '',
                        'tithe_book_id' => '',
                        'paid_for_month' => date('Y-m'),
                    ];
                } catch (Exception $e) {
                    $error = $e->getMessage() ?: 'Unable to update revenue entry right now.';
                }
            }
        }
    }
}

if ($success !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['finance_flash_success'] = $success;
    header('Location: dashboard.php');
    exit;
}

if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    if ($edit_id > 0) {
        try {
            $edit_stmt = $pdo->prepare('SELECT id, transaction_date, category, amount, payment_method, reference_no, description, tither_id, tithe_book_id, paid_for_month FROM finance_transactions WHERE id = ? AND type = ? LIMIT 1');
            $edit_stmt->execute([$edit_id, 'income']);
            $edit_row = $edit_stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($edit_row) {
                $form_action = 'update_income';
                $submit_label = 'Update Revenue Entry';
                $form_values = [
                    'transaction_date' => (string)$edit_row['transaction_date'],
                    'category' => (string)$edit_row['category'],
                    'amount' => (string)$edit_row['amount'],
                    'payment_method' => (string)($edit_row['payment_method'] ?? ''),
                    'reference_no' => (string)($edit_row['reference_no'] ?? ''),
                    'description' => (string)($edit_row['description'] ?? ''),
                    'tither_id' => (string)($edit_row['tither_id'] ?? ''),
                    'tithe_book_id' => (string)($edit_row['tithe_book_id'] ?? ''),
                    'paid_for_month' => !empty($edit_row['paid_for_month']) ? date('Y-m', strtotime((string)$edit_row['paid_for_month'])) : date('Y-m'),
                ];
            }
        } catch (Exception $e) {
            $error = $error !== '' ? $error : 'Unable to load revenue entry for editing.';
        }
    }
}

$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

$income_month = 0;
$income_today = 0;
$entries_month = 0;
$income_rows = [];

try {
    $summary_stmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(amount), 0) AS income_month,
            COALESCE(SUM(CASE WHEN transaction_date = CURDATE() THEN amount END), 0) AS income_today,
            COUNT(*) AS entries_month
         FROM finance_transactions
         WHERE type = 'income' AND transaction_date BETWEEN ? AND ?"
    );
    $summary_stmt->execute([$month_start, $month_end]);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $income_month = (float)($summary['income_month'] ?? 0);
    $income_today = (float)($summary['income_today'] ?? 0);
    $entries_month = (int)($summary['entries_month'] ?? 0);

    $list_stmt = $pdo->prepare(
        "SELECT ft.id, ft.transaction_date, ft.category, ft.amount, ft.payment_method, ft.reference_no, ft.description, ft.paid_for_month, ft.sms_status, ft.sms_provider_code, u.username, t.full_name AS tither_name, b.book_number AS tithe_book_number
         FROM finance_transactions ft
         LEFT JOIN users u ON u.id = ft.recorded_by_user_id
         LEFT JOIN tithers t ON t.id = ft.tither_id
         LEFT JOIN tithe_books b ON b.id = ft.tithe_book_id
         WHERE ft.type = 'income'
         ORDER BY ft.transaction_date DESC, ft.id DESC
         LIMIT 30"
    );
    $list_stmt->execute();
    $income_rows = $list_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = $error !== '' ? $error : 'Finance table is unavailable. Please run database updates.';
}

include '../../includes/header.php';
?>
<link href="../../assets/css/finance.css?v=<?php echo @filemtime('../../assets/css/finance.css'); ?>" rel="stylesheet">

<div class="container-fluid finance-page py-4">
    <?php if ($success): ?>
        <div class="alert alert-success border-0 shadow-sm mb-3"><i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <section class="row g-3 finance-stats mb-1">
        <div class="col-12 col-md-4">
            <article class="finance-stat-card">
                <div class="finance-stat-icon icon-income"><i class="bi bi-calendar-check-fill"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Revenue (This Month)</span>
                    <h3 class="finance-stat-value">GHS <?php echo number_format($income_month, 2); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-4">
            <article class="finance-stat-card">
                <div class="finance-stat-icon icon-balance"><i class="bi bi-sun-fill"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Revenue (Today)</span>
                    <h3 class="finance-stat-value">GHS <?php echo number_format($income_today, 2); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-4">
            <article class="finance-stat-card">
                <div class="finance-stat-icon icon-entries"><i class="bi bi-journal-plus"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Transactions (This Month)</span>
                    <h3 class="finance-stat-value"><?php echo number_format($entries_month); ?></h3>
                </div>
            </article>
        </div>
    </section>

    <div class="row g-3 mt-1">
        <div class="col-12 col-xl-4">
            <div class="finance-panel h-100">
                <div class="finance-panel-head">
                    <h2><i class="bi bi-plus-circle-fill"></i> Record Revenue</h2>
                    <p>Capture a new revenue transaction with complete payment details.</p>
                </div>

                <form method="POST" class="finance-form mt-3">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="<?php echo htmlspecialchars($form_action); ?>">
                    <?php if ($edit_row): ?>
                        <input type="hidden" name="transaction_id" value="<?php echo (int)$edit_row['id']; ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Transaction Date</label>
                        <input type="date" class="form-control" name="transaction_date" value="<?php echo htmlspecialchars($form_values['transaction_date']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Income Category</label>
                        <select class="form-select" name="category" id="incomeCategory" required>
                            <option value="">Select revenue category</option>
                            <?php foreach ($income_categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>"<?php echo $form_values['category'] === $category ? ' selected' : ''; ?>><?php echo htmlspecialchars($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 tithing-only-field" id="titherField" style="display:none;">
                        <label class="form-label">Tither / Contributor</label>
                        <select class="form-select" name="tither_id" id="titherSelect">
                            <option value="">Select tither</option>
                            <?php foreach ($tithers as $tither): ?>
                                <option value="<?php echo (int)$tither['id']; ?>" data-book-id="<?php echo (int)($tither['tithe_book_id'] ?? 0); ?>"<?php echo $form_values['tither_id'] === (string)$tither['id'] ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string)$tither['full_name']); ?><?php echo !empty($tither['book_number']) ? ' - Book ' . htmlspecialchars((string)$tither['book_number']) : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 tithing-only-field" id="titheBookField" style="display:none;">
                        <label class="form-label">Assigned Tithe Book</label>
                        <select class="form-select" name="tithe_book_id" id="titheBookSelect">
                            <option value="">Select book (optional)</option>
                            <?php foreach ($tithe_books as $book): ?>
                                <option value="<?php echo (int)$book['id']; ?>"<?php echo $form_values['tithe_book_id'] === (string)$book['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars((string)$book['book_number']); ?> (<?php echo htmlspecialchars((string)$book['status']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 tithing-only-field" id="paidForMonthField" style="display:none;">
                        <label class="form-label">Coverage Month</label>
                        <input type="month" class="form-control" name="paid_for_month" id="paidForMonthInput" value="<?php echo htmlspecialchars($form_values['paid_for_month']); ?>">
                        <small class="text-muted">Select the month this tithe payment is intended for.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Amount (GHS)</label>
                        <input type="number" class="form-control" name="amount" min="0.01" step="0.01" value="<?php echo htmlspecialchars($form_values['amount']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select class="form-select" name="payment_method">
                            <option value="">Select payment method</option>
                            <?php foreach ($payment_methods as $method): ?>
                                <option value="<?php echo htmlspecialchars($method); ?>"<?php echo $form_values['payment_method'] === $method ? ' selected' : ''; ?>><?php echo htmlspecialchars($method); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Reference (Optional)</label>
                        <input type="text" class="form-control" name="reference_no" maxlength="100" value="<?php echo htmlspecialchars($form_values['reference_no']); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Narration / Notes (Optional)</label>
                        <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($form_values['description']); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save2-fill me-1"></i> <?php echo htmlspecialchars($submit_label); ?>
                    </button>
                    <?php if ($edit_row): ?>
                        <a href="income.php" class="btn btn-outline-secondary w-100 mt-2">Cancel Update</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="finance-panel h-100">
                <div class="finance-panel-head">
                    <h2><i class="bi bi-clock-history"></i> Recent Revenue Transactions</h2>
                    <p>Most recent revenue records and payment activity.</p>
                </div>

                <div class="table-responsive finance-table-wrap mt-3">
                    <table class="table finance-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="finance-col-date">Date</th>
                                <th>Category</th>
                                <th>Contributor</th>
                                <th>Book No.</th>
                                <th>Coverage Month</th>
                                <th>Payment Method</th>
                                <th>SMS Status</th>
                                <th>Reference</th>
                                <th class="finance-col-description">Narration</th>
                                <th class="text-end finance-col-amount">Amount (GHS)</th>
                                <th class="text-end finance-col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($income_rows)): ?>
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-4">No revenue transactions recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($income_rows as $row): ?>
                                    <tr>
                                        <td class="finance-col-date"><?php echo htmlspecialchars(date('d M Y', strtotime((string)$row['transaction_date']))); ?></td>
                                        <td><?php echo htmlspecialchars((string)$row['category']); ?></td>
                                        <td><?php echo htmlspecialchars((string)($row['tither_name'] ?: '-')); ?></td>
                                        <td><?php echo htmlspecialchars((string)($row['tithe_book_number'] ?: '-')); ?></td>
                                        <td><?php echo !empty($row['paid_for_month']) ? htmlspecialchars(date('M Y', strtotime((string)$row['paid_for_month']))) : '-'; ?></td>
                                        <td><?php echo htmlspecialchars((string)($row['payment_method'] ?: '-')); ?></td>
                                        <td>
                                            <?php if ((string)($row['category'] ?? '') === 'Tithe'): ?>
                                                <?php $sms_status = (string)($row['sms_status'] ?? ''); ?>
                                                <?php $sms_code = (string)($row['sms_provider_code'] ?? ''); ?>
                                                <?php
                                                    $sms_status_label = 'Pending';
                                                    if ($sms_status === 'submitted') {
                                                        $sms_status_label = 'Submitted to Gateway';
                                                    } elseif ($sms_status === 'failed') {
                                                        $sms_status_label = 'Submission Failed';
                                                    } elseif ($sms_status === 'skipped') {
                                                        $sms_status_label = 'Not Sent';
                                                    } elseif ($sms_status !== '') {
                                                        $sms_status_label = ucfirst($sms_status);
                                                    }
                                                ?>
                                                <?php echo htmlspecialchars($sms_status_label); ?>
                                                <?php if ($sms_code !== ''): ?>
                                                    <small class="d-block text-muted">Code: <?php echo htmlspecialchars($sms_code); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars((string)($row['reference_no'] ?: '-')); ?></td>
                                        <td class="finance-col-description"><?php echo htmlspecialchars((string)($row['description'] ?: '-')); ?></td>
                                        <td class="text-end fw-semibold finance-col-amount"><?php echo number_format((float)$row['amount'], 2); ?></td>
                                        <td class="text-end finance-col-actions">
                                            <div class="finance-row-actions">
                                                <a href="income.php?edit=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this revenue transaction?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="delete_income">
                                                    <input type="hidden" name="transaction_id" value="<?php echo (int)$row['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const categorySelect = document.getElementById('incomeCategory');
    const titherField = document.getElementById('titherField');
    const titheBookField = document.getElementById('titheBookField');
    const paidForMonthField = document.getElementById('paidForMonthField');
    const titherSelect = document.getElementById('titherSelect');
    const titheBookSelect = document.getElementById('titheBookSelect');
    const paidForMonthInput = document.getElementById('paidForMonthInput');

    if (!categorySelect || !titherField || !titheBookField || !paidForMonthField || !titherSelect || !titheBookSelect || !paidForMonthInput) {
        return;
    }

    if (!paidForMonthInput.value) {
        const now = new Date();
        const monthStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
        paidForMonthInput.value = monthStr;
    }

    function syncTithingFields() {
        const isTithe = categorySelect.value === 'Tithe';
        titherField.style.display = isTithe ? '' : 'none';
        titheBookField.style.display = isTithe ? '' : 'none';
        paidForMonthField.style.display = isTithe ? '' : 'none';
        titherSelect.required = isTithe;
        paidForMonthInput.required = isTithe;

        if (!isTithe) {
            titherSelect.value = '';
            titheBookSelect.value = '';
            paidForMonthInput.value = '';
        } else if (!paidForMonthInput.value) {
            const now = new Date();
            paidForMonthInput.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
        }
    }

    function syncBookFromTither() {
        const selectedOption = titherSelect.options[titherSelect.selectedIndex];
        if (!selectedOption) return;
        const assignedBook = selectedOption.getAttribute('data-book-id');
        if (assignedBook && assignedBook !== '0') {
            titheBookSelect.value = assignedBook;
        }
    }

    categorySelect.addEventListener('change', syncTithingFields);
    titherSelect.addEventListener('change', syncBookFromTither);

    syncTithingFields();
    if (categorySelect.value === 'Tithe' && titherSelect.value) {
        syncBookFromTither();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
