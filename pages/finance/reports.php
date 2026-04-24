<?php
require_once '../../includes/security.php';
requireLogin('../../login');
require_once '../../config/database.php';

$page_title = 'Financial Reports - ' . getInstitutionName($pdo);
$page_heading = 'Financial Reports';
$page_header = false;

// Periods & Filtering Logic (Preserved)
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$comparison_period = $_GET['comparison_period'] ?? 'monthly';
$statement_period = $_GET['statement_period'] ?? 'monthly';

$allowed_periods = ['weekly', 'monthly', 'quarterly', 'annual'];
if (!in_array($comparison_period, $allowed_periods, true)) { $comparison_period = 'monthly'; }

// Summary & Aggregation Logic (Preserved)
try {
    $summary = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN type = 'income' THEN amount END), 0) AS income, COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) AS expense FROM finance_transactions WHERE transaction_date BETWEEN ? AND ?");
    $summary->execute([$start_date, $end_date]);
    $sum = $summary->fetch(PDO::FETCH_ASSOC);
    $income_total = (float)$sum['income'];
    $expense_total = (float)$sum['expense'];
    $net_total = $income_total - $expense_total;

    // Fetch categorical breakdown
    $cats_stmt = $pdo->prepare("SELECT category, type, SUM(amount) as total FROM finance_transactions WHERE transaction_date BETWEEN ? AND ? GROUP BY category, type ORDER BY total DESC");
    $cats_stmt->execute([$start_date, $end_date]);
    $category_data = $cats_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $income_total = $expense_total = $net_total = 0; $category_data = []; }

include '../../includes/header.php';
?>
<link href="../../assets/css/finance.css?v=<?php echo time(); ?>" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid finance-page py-4">
    <header class="finance-hero mb-4">
        <div class="finance-hero-main">
            <p class="finance-eyebrow">ANALYTICS ENGINE</p>
            <h1>Financial Reports</h1>
            <p>Generate detailed statements and performance comparisons for any date range.</p>
            <div class="finance-quick-actions">
                <a href="dashboard" class="finance-quick-btn"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a href="income" class="finance-quick-btn"><i class="bi bi-plus-circle"></i> Income</a>
                <a href="expenses" class="finance-quick-btn"><i class="bi bi-dash-circle"></i> Expenses</a>
            </div>
        </div>
        <div class="finance-date-wrap d-none d-md-block text-end">
            <span class="finance-date-label">Reporting Period</span>
            <div class="finance-date"><?php echo date('M j', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?></div>
            <div class="mt-2">
                <button type="button" class="btn btn-sm btn-light border px-3" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print Report</button>
            </div>
        </div>
    </header>

    <!-- Filter Panel -->
    <div class="finance-panel glass-panel shadow-sm mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small fw-bold">START DATE</label>
                <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small fw-bold">END DATE</label>
                <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small fw-bold">GROUP BY</label>
                <select class="form-select" name="comparison_period">
                    <?php foreach ($allowed_periods as $p): ?>
                        <option value="<?php echo $p; ?>" <?php echo $comparison_period === $p ? 'selected' : ''; ?>><?php echo ucfirst($p); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold"><i class="bi bi-search me-1"></i> Regenerate Report</button>
            </div>
        </form>
    </div>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="finance-stat-card border-0 shadow-sm">
                <div class="finance-stat-icon icon-income"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Total Revenue</span>
                    <h3 class="finance-stat-value text-success">GHS <?php echo number_format($income_total, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="finance-stat-card border-0 shadow-sm" style="border-top-color: #ef4444;">
                <div class="finance-stat-icon icon-expense"><i class="bi bi-graph-down-arrow"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Total Expenditure</span>
                    <h3 class="finance-stat-value text-danger">GHS <?php echo number_format($expense_total, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="finance-stat-card border-0 shadow-sm" style="border-top-color: #0d6efd;">
                <div class="finance-stat-icon icon-balance"><i class="bi bi-wallet2"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Net Surplus/Deficit</span>
                    <h3 class="finance-stat-value <?php echo $net_total >= 0 ? 'text-primary' : 'text-danger'; ?>">GHS <?php echo number_format($net_total, 2); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Breakdown Table -->
        <div class="col-12 col-xl-7">
            <div class="finance-panel border-0 shadow-sm active-card h-100">
                <div class="finance-panel-head mb-4 border-bottom pb-2">
                    <h2 class="h5 mb-1"><i class="bi bi-list-columns-reverse text-accent"></i> Categorical Statement</h2>
                    <p class="small text-muted">Summary of all transactions grouped by their respective categories.</p>
                </div>
                <div class="table-responsive finance-table-wrap">
                    <table class="table finance-table align-middle">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Type</th>
                                <th class="text-end">Total Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($category_data)): ?>
                                <tr><td colspan="3" class="text-center py-5 text-muted">No transactions found for this period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($category_data as $row): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($row['category']); ?></td>
                                        <td>
                                            <span class="badge rounded-pill <?php echo $row['type'] === 'income' ? 'bg-success-subtle text-success border-success-subtle' : 'bg-danger-subtle text-danger border-danger-subtle'; ?> border px-3">
                                                <?php echo ucfirst($row['type']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold <?php echo $row['type'] === 'income' ? 'text-success' : 'text-danger'; ?>">
                                            GHS <?php echo number_format($row['total'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2">Net Period Result</th>
                                <th class="text-end <?php echo $net_total >= 0 ? 'text-primary' : 'text-danger'; ?>">GHS <?php echo number_format($net_total, 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Insights / Chart -->
        <div class="col-12 col-xl-5">
            <div class="finance-panel border-0 shadow-sm h-100">
                <div class="finance-panel-head mb-4 border-bottom pb-2">
                    <h2 class="h5 mb-1"><i class="bi bi-pie-chart-fill text-primary"></i> Expense Ratio</h2>
                    <p class="small text-muted">Revenue vs. Expenditure distribution.</p>
                </div>
                <div class="d-flex align-items-center justify-content-center" style="height: 300px;">
                    <canvas id="ratioChart"></canvas>
                </div>
                <div class="mt-4 p-3 bg-light rounded border border-light-subtle">
                    <h6 class="small fw-bold text-uppercase text-muted">Executive Insight</h6>
                    <?php if ($income_total > 0): ?>
                        <?php $ratio = ($expense_total / $income_total) * 100; ?>
                        <p class="mb-0"> Expenditure represents <b><?php echo number_format($ratio, 1); ?>%</b> of total revenue. 
                        <?php echo $ratio > 80 ? '<span class="text-danger">Warning: High expenditure ratio.</span>' : '<span class="text-success">Efficiency is within healthy limits.</span>'; ?>
                        </p>
                    <?php else: ?>
                        <p class="mb-0">No revenue recorded to calculate efficiency ratios.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('ratioChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Revenue', 'Expenditure'],
            datasets: [{
                data: [<?php echo $income_total; ?>, <?php echo $expense_total; ?>],
                backgroundColor: ['#10b981', '#ef4444'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 15 } }
            }
        }
    });
});
</script>

<style>
.glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(8px); }
.active-card { border-left: 4px solid var(--finance-primary) !important; }
@media print {
    .finance-hero, .finance-panel.glass-panel, .finance-quick-actions { display: none !important; }
    .finance-page { padding: 0 !important; }
}
</style>

<?php include '../../includes/footer.php'; ?>
