<?php
require_once '../../includes/security.php';
requireLogin('../../login');
require_once '../../config/database.php';

$page_title = 'Expenditure - ' . getInstitutionName($pdo);
$page_heading = 'Expenditure';
$page_header = false;

$expense_categories = [
    'Utilities',
    'Maintenance',
    'Welfare',
    'Evangelism',
    'Administration',
    'Transport',
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
];
$form_action = 'add_expense';
$submit_label = 'Post Expenditure Entry';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['add_expense', 'update_expense', 'delete_expense'], true)) {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        if ($action === 'delete_expense') {
            $transaction_id = (int)($_POST['transaction_id'] ?? 0);
            if ($transaction_id > 0) {
                try {
                    $pdo->prepare("DELETE FROM finance_transactions WHERE id = ? AND type = 'expense'")->execute([$transaction_id]);
                    $success = 'Expenditure entry deleted successfully.';
                } catch (Exception $e) { $error = 'Unable to delete entry.'; }
            }
        } else {
            $transaction_date = trim($_POST['transaction_date'] ?? date('Y-m-d'));
            $category = trim($_POST['category'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $payment_method = trim($_POST['payment_method'] ?? '');
            $reference_no = trim($_POST['reference_no'] ?? '');
            $description = trim($_POST['description'] ?? '');

            $form_values = [
                'transaction_date' => $transaction_date,
                'category' => $category,
                'amount' => (string)$amount,
                'payment_method' => $payment_method,
                'reference_no' => $reference_no,
                'description' => $description,
            ];

            if ($amount <= 0) {
                $error = 'Amount must be greater than zero.';
            } elseif (!in_array($category, $expense_categories, true)) {
                $error = 'Please select a valid category.';
            } else {
                try {
                    if ($action === 'add_expense') {
                        $stmt = $pdo->prepare("INSERT INTO finance_transactions (transaction_date, type, category, amount, payment_method, reference_no, description, recorded_by_user_id) VALUES (?, 'expense', ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$transaction_date, $category, $amount, $payment_method ?: null, $reference_no ?: null, $description ?: null, $_SESSION['user_id']]);
                        $success = 'Expenditure recorded successfully.';
                    } else {
                        $transaction_id = (int)($_POST['transaction_id'] ?? 0);
                        $stmt = $pdo->prepare("UPDATE finance_transactions SET transaction_date = ?, category = ?, amount = ?, payment_method = ?, reference_no = ?, description = ? WHERE id = ? AND type = 'expense'");
                        $stmt->execute([$transaction_date, $category, $amount, $payment_method ?: null, $reference_no ?: null, $description ?: null, $transaction_id]);
                        $success = 'Expenditure updated successfully.';
                    }
                    if ($action === 'add_expense') {
                        $form_values['amount'] = ''; $form_values['description'] = ''; $form_values['reference_no'] = '';
                    }
                } catch (Exception $e) { $error = 'Database error.'; }
            }
        }
    }
}

if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM finance_transactions WHERE id = ? AND type = 'expense' LIMIT 1");
    $stmt->execute([$edit_id]);
    $edit_row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($edit_row) {
        $form_action = 'update_expense'; $submit_label = 'Update Expenditure';
        $form_values = [
            'transaction_date' => $edit_row['transaction_date'],
            'category' => $edit_row['category'],
            'amount' => (string)$edit_row['amount'],
            'payment_method' => $edit_row['payment_method'] ?? '',
            'reference_no' => $edit_row['reference_no'] ?? '',
            'description' => $edit_row['description'] ?? '',
        ];
    }
}

$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$expense_month = (float)$pdo->query("SELECT SUM(amount) FROM finance_transactions WHERE type = 'expense' AND transaction_date BETWEEN '$month_start' AND '$month_end'")->fetchColumn();

include '../../includes/header.php';
?>
<link href="../../assets/css/finance.css?v=<?php echo time(); ?>" rel="stylesheet">

<div class="container-fluid finance-page py-4">
    <header class="finance-hero mb-4">
        <div class="finance-hero-main">
            <p class="finance-eyebrow">EXPENDITURE MANAGEMENT</p>
            <h1>Church Expenses</h1>
            <p>Track all outflows, utility payments, and maintaining costs. Data shown is for <strong><?php echo date('F Y'); ?></strong>.</p>
            <div class="finance-quick-actions">
                <a href="dashboard" class="finance-quick-btn"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a href="income" class="finance-quick-btn"><i class="bi bi-plus-circle"></i> Record Income</a>
            </div>
        </div>
        <div class="finance-date-wrap d-none d-md-block text-end">
            <span class="finance-date-label">System Date</span>
            <div class="finance-date"><?php echo date('l, jS F Y'); ?></div>
            <div class="mt-2">
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3">
                    Month Total: GHS <?php echo number_format($expense_month, 2); ?>
                </span>
            </div>
        </div>
    </header>

    <?php if ($success): ?><div class="alert alert-success border-0 shadow-sm mb-4"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger border-0 shadow-sm mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <div class="finance-panel glass-panel shadow-sm sticky-top" style="top: 2rem;">
                <div class="finance-panel-head border-bottom pb-3">
                    <h2 class="h5 mb-1"><i class="bi bi-dash-circle-fill text-danger"></i> <?php echo $edit_row ? 'Edit Expense' : 'Post Expense'; ?></h2>
                    <p class="small text-muted mb-0">Record a new expenditure entry.</p>
                </div>
                <form method="POST" class="finance-form mt-4">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="<?php echo $form_action; ?>">
                    <?php if ($edit_row): ?><input type="hidden" name="transaction_id" value="<?php echo $edit_row['id']; ?>"><?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">DATE</label>
                        <input type="date" class="form-control" name="transaction_date" value="<?php echo $form_values['transaction_date']; ?>" required>
                    </div>

                    <div class="row g-2">
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold">CATEGORY</label>
                            <select class="form-select" name="category" required>
                                <option value="">Select...</option>
                                <?php foreach ($expense_categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo $form_values['category'] === $cat ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold">AMOUNT</label>
                            <input type="number" step="0.01" class="form-control fw-bold text-danger h-100" name="amount" value="<?php echo $form_values['amount']; ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">PAYMENT METHOD</label>
                        <select class="form-select" name="payment_method">
                            <option value="">Standard Cash</option>
                            <?php foreach ($payment_methods as $m): ?>
                                <option value="<?php echo $m; ?>" <?php echo $form_values['payment_method'] === $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">REFERENCE #</label>
                        <input type="text" class="form-control" name="reference_no" value="<?php echo $form_values['reference_no']; ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">DESCRIPTION</label>
                        <textarea class="form-control" name="description" rows="3"><?php echo $form_values['description']; ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-danger w-100 py-2 fw-bold shadow-sm"><?php echo $submit_label; ?></button>
                    <?php if ($edit_row): ?><a href="expenses" class="btn btn-outline-secondary w-100 mt-2">Cancel Edit</a><?php endif; ?>
                </form>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="finance-panel h-100 border-0 shadow-sm shadow-hover active-card-red text-end">
                <div class="finance-panel-head d-flex justify-content-between align-items-center mb-4 text-start">
                    <div>
                        <h2 class="h5 mb-1"><i class="bi bi-clock-history text-danger"></i> Expense Logs</h2>
                        <p class="small text-muted mb-0">Most recent outflows.</p>
                    </div>
                </div>
                <div class="table-responsive finance-table-wrap">
                    <table class="table finance-table align-middle mb-0 text-start">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description / Ref</th>
                                <th>Amount</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("SELECT * FROM finance_transactions WHERE type = 'expense' ORDER BY transaction_date DESC, id DESC LIMIT 50");
                            while ($row = $stmt->fetch()):
                            ?>
                                <tr>
                                    <td><div class="fw-bold"><?php echo date('M j', strtotime($row['transaction_date'])); ?></div></td>
                                    <td><span class="badge rounded-pill bg-light text-dark border px-3"><?php echo $row['category']; ?></span></td>
                                    <td class="text-muted small">
                                        <div class="fw-semibold text-dark"><?php echo mb_substr($row['description'] ?: 'No description', 0, 45); ?></div>
                                        <div>Ref: <?php echo $row['reference_no'] ?: '-'; ?></div>
                                    </td>
                                    <td class="fw-bold text-danger">GHS <?php echo number_format($row['amount'], 2); ?></td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <a href="expenses?edit=<?php echo $row['id']; ?>" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i></a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="action" value="delete_expense"><input type="hidden" name="transaction_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-light border text-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
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
.active-card-red { border-left: 4px solid #ef4444 !important; }
</style>

<?php include '../../includes/footer.php'; ?>
