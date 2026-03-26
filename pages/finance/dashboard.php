<?php
require_once '../../includes/security.php';
requireLogin('../../login.php');
require_once '../../config/database.php';

$page_title = 'Finance Operations Dashboard - Bridge Ministries International';
$page_heading = 'Finance Operations';
$page_header = false;

$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

$stats = [
    'income_month' => 0,
    'expense_month' => 0,
    'net_month' => 0,
    'entries_month' => 0,
];

$finance_data_ready = false;
$flash_success = '';

if (!empty($_SESSION['finance_flash_success'])) {
    $flash_success = (string)$_SESSION['finance_flash_success'];
    unset($_SESSION['finance_flash_success']);
}

try {
    $table_exists_stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'finance_transactions'");
    $table_exists_stmt->execute();
    $has_finance_table = (int)$table_exists_stmt->fetchColumn() > 0;

    if ($has_finance_table) {
        $finance_data_ready = true;

        $stats_stmt = $pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount END), 0) AS income_month,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) AS expense_month,
                COUNT(*) AS entries_month
             FROM finance_transactions
             WHERE transaction_date BETWEEN ? AND ?"
        );
        $stats_stmt->execute([$month_start, $month_end]);
        $row = $stats_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stats['income_month'] = (float)($row['income_month'] ?? 0);
        $stats['expense_month'] = (float)($row['expense_month'] ?? 0);
        $stats['entries_month'] = (int)($row['entries_month'] ?? 0);
        $stats['net_month'] = $stats['income_month'] - $stats['expense_month'];
    }
} catch (Exception $e) {
    $finance_data_ready = false;
}

include '../../includes/header.php';
?>
<link href="../../assets/css/finance.css?v=<?php echo @filemtime('../../assets/css/finance.css'); ?>" rel="stylesheet">

<div class="container-fluid finance-page py-4">
    <?php if ($flash_success !== ''): ?>
    <div class="alert alert-success border-0 shadow-sm mb-3" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?php echo htmlspecialchars($flash_success); ?>
    </div>
    <?php endif; ?>

    <section class="finance-hero">
        <div class="finance-hero-main">
            <p class="finance-eyebrow">FINANCE MODULE</p>
            <h1>Finance Operations Dashboard</h1>
            <p>Manage revenue, expenditure, and reporting from one secure workspace.</p>
        </div>
        <div class="finance-date-wrap">
            <span class="finance-date-label">Today</span>
            <div class="finance-date"><?php echo date('l, F j, Y'); ?></div>
        </div>
    </section>

    <section class="row g-3 finance-stats">
        <div class="col-12 col-md-6 col-xl-3">
            <article class="finance-stat-card">
                <div class="finance-stat-icon icon-income"><i class="bi bi-arrow-down-circle-fill"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Revenue (This Month)</span>
                    <h3 class="finance-stat-value">GHS <?php echo number_format($stats['income_month'], 2); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <article class="finance-stat-card">
                <div class="finance-stat-icon icon-expense"><i class="bi bi-arrow-up-circle-fill"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Expenses (This Month)</span>
                    <h3 class="finance-stat-value">GHS <?php echo number_format($stats['expense_month'], 2); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <article class="finance-stat-card">
                <div class="finance-stat-icon icon-balance"><i class="bi bi-bank2"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Net Position (Month)</span>
                    <h3 class="finance-stat-value">GHS <?php echo number_format($stats['net_month'], 2); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <article class="finance-stat-card">
                <div class="finance-stat-icon icon-entries"><i class="bi bi-journal-check"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Transactions (This Month)</span>
                    <h3 class="finance-stat-value"><?php echo number_format($stats['entries_month']); ?></h3>
                </div>
            </article>
        </div>
    </section>

    <?php if (!$finance_data_ready): ?>
    <div class="alert alert-info border-0 shadow-sm mt-3 mb-0">
        <i class="bi bi-info-circle-fill me-2"></i>
        Financial summary is available, but no finance transactions table exists yet. Once finance transactions are created, these metrics will auto-populate.
    </div>
    <?php endif; ?>

    <section class="row g-3 mt-3 finance-utilities">
        <div class="col-12">
            <div class="finance-panel h-100 finance-panel-actions">
                <div class="finance-panel-head">
                    <h2><i class="bi bi-lightning-charge-fill"></i> Quick Actions</h2>
                    <p>Execute core finance tasks with one click from one unified section.</p>
                </div>
                <div class="finance-actions-grid">
                    <a href="income.php" class="finance-action-btn"><i class="bi bi-plus-circle-fill"></i><span>Post Revenue</span></a>
                    <a href="expenses.php" class="finance-action-btn"><i class="bi bi-dash-circle-fill"></i><span>Record Expenditure</span></a>
                    <a href="tithers.php" class="finance-action-btn"><i class="bi bi-people-fill"></i><span>Manage Tithers</span></a>
                    <a href="<?php echo htmlspecialchars(moduleScopedPageUrl('finance', 'reports')); ?>" class="finance-action-btn"><i class="bi bi-file-earmark-bar-graph-fill"></i><span>Generate Reports</span></a>
                    <a href="../../index.php" class="finance-action-btn"><i class="bi bi-house-door-fill"></i><span>Back to Hub</span></a>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include '../../includes/footer.php'; ?>
