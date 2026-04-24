<?php
require_once '../../includes/security.php';
requireLogin('../../login');
require_once '../../config/database.php';
require_once '../../includes/communication_utils.php';

$page_title = 'Income & Tithes - ' . getInstitutionName($pdo);
$page_heading = 'Income & Tithes';
$page_header = false;

$income_categories = ['Tithe', 'Offertory', 'Welfare', 'Building Fund', 'Thanksgiving', 'Missionary', 'Other'];
$payment_methods = ['Cash', 'Mobile Money', 'Bank Transfer', 'Cheque', 'Other'];

$success = '';
$error = '';

// --- Form Logic ---
$edit_row = null;
$form_values = [
    'transaction_date' => date('Y-m-d'),
    'category' => 'Tithe',
    'tither_id' => '',
    'amount' => '',
    'payment_method' => 'Cash',
    'reference_no' => '',
    'description' => '',
    'send_sms_receipt' => 'no'
];
$form_action = 'add_income';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['add_income', 'update_income', 'delete_income'], true)) {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $error = 'Security token mismatch. Please refresh.';
    } else {
        if ($action === 'delete_income') {
            $id = (int)($_POST['transaction_id'] ?? 0);
            $pdo->prepare("DELETE FROM finance_transactions WHERE id = ? AND type = 'income'")->execute([$id]);
            $success = 'Record deleted.';
        } else {
            $t_date = $_POST['transaction_date'] ?? date('Y-m-d');
            $category = $_POST['category'] ?? 'Tithe';
            $tither_id = (int)($_POST['tither_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);
            $pay_method = $_POST['payment_method'] ?? 'Cash';
            $ref = $_POST['reference_no'] ?? '';
            $desc = $_POST['description'] ?? '';
            $send_sms = ($_POST['send_sms_receipt'] ?? 'no') === 'yes';

            if ($amount <= 0) {
                $error = 'Positive amount required.';
            } else {
                try {
                    $pdo->beginTransaction();
                    if ($action === 'add_income') {
                        $stmt = $pdo->prepare("INSERT INTO finance_transactions (transaction_date, type, category, tither_id, amount, payment_method, reference_no, description, recorded_by_user_id) VALUES (?, 'income', ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$t_date, $category, $tither_id ?: null, $amount, $pay_method, $ref, $desc, $_SESSION['user_id']]);
                        $transaction_id = $pdo->lastInsertId();
                        $success = 'Income recorded successfully.';
                    } else {
                        $transaction_id = (int)$_POST['transaction_id'];
                        $stmt = $pdo->prepare("UPDATE finance_transactions SET transaction_date = ?, category = ?, tither_id = ?, amount = ?, payment_method = ?, reference_no = ?, description = ? WHERE id = ? AND type = 'income'");
                        $stmt->execute([$t_date, $category, $tither_id ?: null, $amount, $pay_method, $ref, $desc, $transaction_id]);
                        $success = 'Record updated.';
                    }

                    // Background SMS Logic (Preserved)
                    if ($send_sms && $category === 'Tithe' && $tither_id > 0) {
                        $tither = $pdo->query("SELECT * FROM tithers WHERE id = $tither_id")->fetch();
                        if ($tither && !empty($tither['phone'])) {
                            $msg = "Dear " . explode(' ', $tither['full_name'])[0] . ", thank you for your Tithe of GHS " . number_format($amount, 2) . ". May God bless you.";
                            queueDirectSms($pdo, $tither['phone'], $msg, 'tithe_receipt', $transaction_id);
                        }
                    }
                    $pdo->commit();
                } catch (Exception $e) { $pdo->rollBack(); $error = $e->getMessage(); }
            }
        }
    }
}

// Fetch Tithers (Individuals + Companies)
$tithers = $pdo->query("SELECT t.*, b.book_number FROM tithers t LEFT JOIN tithe_books b ON b.id = t.tithe_book_id ORDER BY t.full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// UI Totals
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$income_month = (float)$pdo->query("SELECT SUM(amount) FROM finance_transactions WHERE type = 'income' AND transaction_date BETWEEN '$month_start' AND '$month_end'")->fetchColumn();

include '../../includes/header.php';
?>
<link href="../../assets/css/finance.css?v=<?php echo time(); ?>" rel="stylesheet">
<div class="container-fluid finance-page py-4">
    <header class="finance-hero mb-4">
        <div class="finance-hero-main">
            <p class="finance-eyebrow">REVENUE OVERSIGHT</p>
            <h1>Income & Tithes</h1>
            <p>Record all inflows including Tithes from Members and Corporate Entities.</p>
            <div class="finance-quick-actions">
                <a href="dashboard" class="finance-quick-btn"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a href="tithers" class="finance-quick-btn"><i class="bi bi-people"></i> Manage Tithers</a>
            </div>
        </div>
        <div class="finance-date-wrap d-none d-md-block text-end">
            <span class="finance-date-label">Current Month Revenue</span>
            <div class="finance-date">GHS <?php echo number_format($income_month, 2); ?></div>
        </div>
    </header>

    <?php if ($success): ?><div class="alert alert-success border-0 shadow-sm mb-4"><i class="bi bi-check-circle me-2"></i><?php echo $success; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger border-0 shadow-sm mb-4"><i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?></div><?php endif; ?>

    <div class="row g-4">
        <!-- Entry Form -->
        <div class="col-12 col-xl-4">
            <div class="finance-panel glass-panel sticky-top" style="top: 2rem;">
                <div class="finance-panel-head border-bottom pb-3">
                    <h2 class="h5 mb-1"><i class="bi bi-plus-circle-fill text-success"></i> <?php echo $edit_row ? 'Edit Entry' : 'New Income Entry'; ?></h2>
                </div>
                <form method="POST" class="finance-form mt-4">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="<?php echo $form_action; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">TRANSACTION DATE</label>
                        <input type="date" class="form-control" name="transaction_date" value="<?php echo $form_values['transaction_date']; ?>" required>
                    </div>

                    <div class="row g-2">
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold">CATEGORY</label>
                            <select class="form-select" name="category" onchange="this.value === 'Tithe' ? document.getElementById('tither_wrap').style.display='block' : document.getElementById('tither_wrap').style.display='none'">
                                <?php foreach ($income_categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo $form_values['category'] === $cat ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold">AMOUNT</label>
                            <input type="number" step="0.01" class="form-control fw-bold text-success h-100" name="amount" value="<?php echo $form_values['amount']; ?>" required>
                        </div>
                    </div>

                    <div id="tither_wrap" class="mb-3">
                        <label class="form-label small fw-bold">CONTRIBUTOR (MEMBER / COMPANY)</label>
                        <select class="form-select" name="tither_id">
                            <option value="">-- Generic Contributor --</option>
                            <?php foreach ($tithers as $t): ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo (int)($form_values['tither_id'] ?? 0) === (int)$t['id'] ? 'selected' : ''; ?>>
                                    <?php echo ($t['tither_type'] === 'company' ? '🏢 ' : '👤 ') . htmlspecialchars($t['full_name']); ?> 
                                    (<?php echo $t['book_number'] ?: 'No Book'; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">PAYMENT METHOD</label>
                        <select class="form-select" name="payment_method">
                            <?php foreach ($payment_methods as $m): ?>
                                <option value="<?php echo $m; ?>" <?php echo $form_values['payment_method'] === $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">REFERENCE #</label>
                        <input type="text" class="form-control" name="reference_no" value="<?php echo $form_values['reference_no']; ?>">
                    </div>

                    <div class="form-check form-switch mb-4 bg-light p-3 rounded border">
                        <input class="form-check-input ms-0 me-2" type="checkbox" name="send_sms_receipt" value="yes" checked>
                        <label class="form-check-label fw-bold">Send SMS Receipt</label>
                    </div>

                    <button type="submit" class="btn btn-success w-100 py-2 fw-bold shadow-sm">Post Transaction</button>
                </form>
            </div>
        </div>

        <!-- History -->
        <div class="col-12 col-xl-8">
            <div class="finance-panel border-0 shadow-sm active-card">
                <div class="finance-panel-head mb-4 border-bottom pb-2">
                    <h2 class="h5 mb-1"><i class="bi bi-clock-history text-accent"></i> Recent Transactions</h2>
                </div>
                <div class="table-responsive finance-table-wrap">
                    <table class="table finance-table align-middle">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Contributor</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("SELECT ft.*, t.full_name, t.tither_type FROM finance_transactions ft LEFT JOIN tithers t ON t.id = ft.tither_id WHERE ft.type = 'income' ORDER BY transaction_date DESC, id DESC LIMIT 50");
                            while ($row = $stmt->fetch()):
                            ?>
                                <tr>
                                    <td><div class="fw-bold"><?php echo date('M j', strtotime($row['transaction_date'])); ?></div></td>
                                    <td>
                                        <div class="fw-semibold">
                                            <?php if ($row['tither_type'] === 'company'): ?><i class="bi bi-building me-1"></i><?php endif; ?>
                                            <?php echo htmlspecialchars($row['full_name'] ?: 'Generic'); ?>
                                        </div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($row['payment_method']); ?></div>
                                    </td>
                                    <td><span class="badge bg-light text-dark border px-3"><?php echo $row['category']; ?></span></td>
                                    <td class="fw-bold text-success">GHS <?php echo number_format($row['amount'], 2); ?></td>
                                    <td class="text-end">
                                        <form method="POST" onsubmit="return confirm('Delete?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="delete_income">
                                            <input type="hidden" name="transaction_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-light border text-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(8px); }
.active-card { border-left: 4px solid var(--finance-primary) !important; }
</style>

<?php include '../../includes/footer.php'; ?>
