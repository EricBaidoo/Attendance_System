<?php
require_once '../../includes/security.php';
requireLogin('../../login');
require_once '../../config/database.php';

$page_title = 'Expenditure - Bridge Ministries International';
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
            if ($transaction_id <= 0) {
                $error = 'Invalid expense entry selected.';
            } else {
                try {
                    $delete_stmt = $pdo->prepare('DELETE FROM finance_transactions WHERE id = ? AND type = ?');
                    $delete_stmt->execute([$transaction_id, 'expense']);
                    if ($delete_stmt->rowCount() > 0) {
                        $success = 'Expenditure entry deleted successfully.';
                    } else {
                        $error = 'Expenditure entry not found or already removed.';
                    }
                } catch (Exception $e) {
                    $error = 'Unable to delete expenditure entry right now.';
                }
            }
        }

        $transaction_date = trim($_POST['transaction_date'] ?? date('Y-m-d'));
        $category = trim($_POST['category'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $payment_method = trim($_POST['payment_method'] ?? '');
        $reference_no = trim($_POST['reference_no'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($action !== 'delete_expense') {
            $form_values = [
                'transaction_date' => $transaction_date,
                'category' => $category,
                'amount' => (string)($_POST['amount'] ?? ''),
                'payment_method' => $payment_method,
                'reference_no' => $reference_no,
                'description' => $description,
            ];
        }

        if ($action !== 'delete_expense' && !DateTime::createFromFormat('Y-m-d', $transaction_date)) {
            $error = 'Please provide a valid transaction date.';
        } elseif ($action !== 'delete_expense' && !in_array($category, $expense_categories, true)) {
            $error = 'Please select a valid expenditure category.';
        } elseif ($action !== 'delete_expense' && $amount <= 0) {
            $error = 'Amount must be greater than zero.';
        } elseif ($action !== 'delete_expense' && $payment_method !== '' && !in_array($payment_method, $payment_methods, true)) {
            $error = 'Please select a valid payment method.';
        } elseif ($action === 'add_expense') {
            try {
                $insert_stmt = $pdo->prepare(
                    'INSERT INTO finance_transactions (transaction_date, type, category, amount, payment_method, reference_no, description, recorded_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $insert_stmt->execute([
                    $transaction_date,
                    'expense',
                    $category,
                    $amount,
                    $payment_method !== '' ? $payment_method : null,
                    $reference_no !== '' ? mb_substr($reference_no, 0, 100) : null,
                    $description !== '' ? $description : null,
                    (int)($_SESSION['user_id'] ?? 0) ?: null,
                ]);
                $success = 'Expenditure entry recorded successfully.';
                $form_values = [
                    'transaction_date' => date('Y-m-d'),
                    'category' => '',
                    'amount' => '',
                    'payment_method' => '',
                    'reference_no' => '',
                    'description' => '',
                ];
            } catch (Exception $e) {
                $error = 'Unable to save expenditure entry right now.';
            }
        } elseif ($action === 'update_expense') {
            $transaction_id = (int)($_POST['transaction_id'] ?? 0);
            if ($transaction_id <= 0) {
                $error = 'Invalid expenditure entry selected for update.';
            } else {
                try {
                    $update_stmt = $pdo->prepare(
                        'UPDATE finance_transactions SET transaction_date = ?, category = ?, amount = ?, payment_method = ?, reference_no = ?, description = ?, recorded_by_user_id = ? WHERE id = ? AND type = ?'
                    );
                    $update_stmt->execute([
                        $transaction_date,
                        $category,
                        $amount,
                        $payment_method !== '' ? $payment_method : null,
                        $reference_no !== '' ? mb_substr($reference_no, 0, 100) : null,
                        $description !== '' ? $description : null,
                        (int)($_SESSION['user_id'] ?? 0) ?: null,
                        $transaction_id,
                        'expense',
                    ]);
                    if ($update_stmt->rowCount() > 0) {
                        $success = 'Expenditure entry updated successfully.';
                    } else {
                        $success = 'No changes were made to this expenditure entry.';
                    }
                    $form_values = [
                        'transaction_date' => date('Y-m-d'),
                        'category' => '',
                        'amount' => '',
                        'payment_method' => '',
                        'reference_no' => '',
                        'description' => '',
                    ];
                } catch (Exception $e) {
                    $error = 'Unable to update expenditure entry right now.';
                }
            }
        }
    }
}

if ($success !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['finance_flash_success'] = $success;
    header('Location: dashboard');
    exit;
}

if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    if ($edit_id > 0) {
        try {
            $edit_stmt = $pdo->prepare('SELECT id, transaction_date, category, amount, payment_method, reference_no, description FROM finance_transactions WHERE id = ? AND type = ? LIMIT 1');
            $edit_stmt->execute([$edit_id, 'expense']);
            $edit_row = $edit_stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($edit_row) {
                $form_action = 'update_expense';
                $submit_label = 'Update Expenditure Entry';
                $form_values = [
                    'transaction_date' => (string)$edit_row['transaction_date'],
                    'category' => (string)$edit_row['category'],
                    'amount' => (string)$edit_row['amount'],
                    'payment_method' => (string)($edit_row['payment_method'] ?? ''),
                    'reference_no' => (string)($edit_row['reference_no'] ?? ''),
                    'description' => (string)($edit_row['description'] ?? ''),
                ];
            }
        } catch (Exception $e) {
            $error = $error !== '' ? $error : 'Unable to load expenditure entry for editing.';
        }
    }
}

$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

$expense_month = 0;
$expense_today = 0;
$entries_month = 0;
$expense_rows = [];

try {
    $summary_stmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(amount), 0) AS expense_month,
            COALESCE(SUM(CASE WHEN transaction_date = CURDATE() THEN amount END), 0) AS expense_today,
            COUNT(*) AS entries_month
         FROM finance_transactions
         WHERE type = 'expense' AND transaction_date BETWEEN ? AND ?"
    );
    $summary_stmt->execute([$month_start, $month_end]);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $expense_month = (float)($summary['expense_month'] ?? 0);
    $expense_today = (float)($summary['expense_today'] ?? 0);
    $entries_month = (int)($summary['entries_month'] ?? 0);

    $list_stmt = $pdo->prepare(
        "SELECT ft.id, ft.transaction_date, ft.category, ft.amount, ft.payment_method, ft.reference_no, ft.description, u.username
         FROM finance_transactions ft
         LEFT JOIN users u ON u.id = ft.recorded_by_user_id
         WHERE ft.type = 'expense'
         ORDER BY ft.transaction_date DESC, ft.id DESC
         LIMIT 30"
    );
    $list_stmt->execute();
    $expense_rows = $list_stmt->fetchAll(PDO::FETCH_ASSOC);
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
                <div class="finance-stat-icon icon-expense"><i class="bi bi-calendar-check-fill"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Expenditure (This Month)</span>
                    <h3 class="finance-stat-value">GHS <?php echo number_format($expense_month, 2); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-4">
            <article class="finance-stat-card">
                <div class="finance-stat-icon icon-balance"><i class="bi bi-sun-fill"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Expenditure (Today)</span>
                    <h3 class="finance-stat-value">GHS <?php echo number_format($expense_today, 2); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-4">
            <article class="finance-stat-card">
                <div class="finance-stat-icon icon-entries"><i class="bi bi-journal-minus"></i></div>
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
                    <h2><i class="bi bi-plus-circle-fill"></i> Record Expenditure</h2>
                    <p>Capture a new expenditure transaction with complete payment details.</p>
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
                        <label class="form-label">Expense Category</label>
                        <select class="form-select" name="category" required>
                            <option value="">Select expense category</option>
                            <?php foreach ($expense_categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>"<?php echo $form_values['category'] === $category ? ' selected' : ''; ?>><?php echo htmlspecialchars($category); ?></option>
                            <?php endforeach; ?>
                        </select>
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
                        <a href="expenses" class="btn btn-outline-secondary w-100 mt-2">Cancel Update</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="finance-panel h-100">
                <div class="finance-panel-head">
                    <h2><i class="bi bi-clock-history"></i> Recent Expenditure Transactions</h2>
                    <p>Most recent expense records and payment activity.</p>
                </div>

                <div class="table-responsive finance-table-wrap mt-3">
                    <table class="table finance-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="finance-col-date">Date</th>
                                <th>Category</th>
                                <th>Payment Method</th>
                                <th>Reference</th>
                                <th class="finance-col-description">Narration</th>
                                <th class="text-end finance-col-amount">Amount (GHS)</th>
                                <th class="text-end finance-col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($expense_rows)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No expenditure transactions recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($expense_rows as $row): ?>
                                    <tr>
                                        <td class="finance-col-date"><?php echo htmlspecialchars(date('d M Y', strtotime((string)$row['transaction_date']))); ?></td>
                                        <td><?php echo htmlspecialchars((string)$row['category']); ?></td>
                                        <td><?php echo htmlspecialchars((string)($row['payment_method'] ?: '-')); ?></td>
                                        <td><?php echo htmlspecialchars((string)($row['reference_no'] ?: '-')); ?></td>
                                        <td class="finance-col-description"><?php echo htmlspecialchars((string)($row['description'] ?: '-')); ?></td>
                                        <td class="text-end fw-semibold finance-col-amount"><?php echo number_format((float)$row['amount'], 2); ?></td>
                                        <td class="text-end finance-col-actions">
                                            <div class="finance-row-actions">
                                                <a href="expenses?edit=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this expenditure transaction?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="delete_expense">
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

<?php include '../../includes/footer.php'; ?>

