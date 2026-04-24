<?php
require_once '../../includes/security.php';
requireLogin('../../login');
require_once '../../config/database.php';

$page_title = 'Finance Dashboard - ' . getInstitutionName($pdo);
$page_heading = 'Finance Dashboard';
$page_header = false;

// 1. Fetch Summary Stats for Current Month
$current_month = date('Y-m');
$first_day = date('Y-m-01');
$last_day = date('Y-m-t');

try {
    // Current Month Income
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM finance_transactions WHERE type = 'income' AND transaction_date BETWEEN ? AND ?");
    $stmt->execute([$first_day, $last_day]);
    $month_income = (float)$stmt->fetchColumn();

    // Current Month Expenses
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM finance_transactions WHERE type = 'expense' AND transaction_date BETWEEN ? AND ?");
    $stmt->execute([$first_day, $last_day]);
    $month_expense = (float)$stmt->fetchColumn();

    // Total Active Tithers
    $stmt = $pdo->query("SELECT COUNT(*) FROM tithers WHERE status = 'active'");
    $total_tithers = (int)$stmt->fetchColumn();

    // 2. Chart Data: Income vs Expenses (Last 6 Months)
    $chart_labels = [];
    $income_data = [];
    $expense_data = [];

    for ($i = 5; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("-$i months"));
        $label = date('M Y', strtotime("-$i months"));
        $chart_labels[] = $label;

        $stmt = $pdo->prepare("SELECT SUM(amount) FROM finance_transactions WHERE type = 'income' AND DATE_FORMAT(transaction_date, '%Y-%m') = ?");
        $stmt->execute([$m]);
        $income_data[] = (float)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT SUM(amount) FROM finance_transactions WHERE type = 'expense' AND DATE_FORMAT(transaction_date, '%Y-%m') = ?");
        $stmt->execute([$m]);
        $expense_data[] = (float)$stmt->fetchColumn();
    }

    // 3. Chart Data: Revenue Breakdown (This Month)
    $stmt = $pdo->prepare("SELECT category, SUM(amount) as total FROM finance_transactions WHERE type = 'income' AND transaction_date BETWEEN ? AND ? GROUP BY category");
    $stmt->execute([$first_day, $last_day]);
    $revenue_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Recent Transactions
    $stmt = $pdo->query("SELECT id, type, category, amount, transaction_date, description FROM finance_transactions ORDER BY transaction_date DESC, id DESC LIMIT 6");
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Finance Dashboard Error: " . $e->getMessage());
    $month_income = $month_expense = $total_tithers = 0;
    $chart_labels = $income_data = $expense_data = $revenue_breakdown = $recent_transactions = [];
}

include '../../includes/header.php';
?>

<link href="../../assets/css/finance.css?v=<?php echo time(); ?>" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid finance-page py-4">
    <!-- Hero Header -->
    <header class="finance-hero">
        <div class="finance-hero-main">
            <p class="finance-eyebrow">FINANCIAL OVERVIEW</p>
            <h1>Finance Insights</h1>
            <p>Monitor your church's financial health, revenue trends, and expenditure at a glance. Data shown is for <strong><?php echo date('F Y'); ?></strong>.</p>
            
            <div class="finance-quick-actions">
                <a href="income" class="finance-quick-btn"><i class="bi bi-plus-circle"></i> Add Income</a>
                <a href="expenses" class="finance-quick-btn"><i class="bi bi-dash-circle"></i> Add Expense</a>
                <a href="reports" class="finance-quick-btn"><i class="bi bi-file-earmark-bar-graph"></i> Full Reports</a>
            </div>
        </div>
        <div class="finance-date-wrap d-none d-md-block">
            <span class="finance-date-label">System Date</span>
            <div class="finance-date"><?php echo date('l, jS F Y'); ?></div>
        </div>
    </header>

    <!-- Stat Cards -->
    <section class="row g-3 finance-stats">
        <div class="col-12 col-md-6 col-xl-3">
            <article class="finance-stat-card">
                <div class="finance-stat-icon icon-income"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Income (Month)</span>
                    <h3 class="finance-stat-value">GHS <?php echo number_format($month_income, 2); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <article class="finance-stat-card" style="border-top-color: #ef4444;">
                <div class="finance-stat-icon icon-expense"><i class="bi bi-graph-down-arrow"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Expenses (Month)</span>
                    <h3 class="finance-stat-value">GHS <?php echo number_format($month_expense, 2); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <?php $net = $month_income - $month_expense; ?>
            <article class="finance-stat-card">
                <div class="finance-stat-icon icon-balance"><i class="bi bi-wallet2"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Net Surplus</span>
                    <h3 class="finance-stat-value <?php echo $net >= 0 ? 'text-success' : 'text-danger'; ?>">
                        GHS <?php echo number_format($net, 2); ?>
                    </h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <article class="finance-stat-card">
                <div class="finance-stat-icon icon-entries"><i class="bi bi-people-fill"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Active Tithers</span>
                    <h3 class="finance-stat-value"><?php echo number_format($total_tithers); ?></h3>
                </div>
            </article>
        </div>
    </section>

    <!-- Main Content Grid -->
    <div class="row g-4 mt-1">
        <!-- Income vs Expenses Chart -->
        <div class="col-12 col-xl-8">
            <div class="finance-panel">
                <div class="finance-panel-head">
                    <h2><i class="bi bi-bar-chart-line-fill"></i> Cash Flow Trend</h2>
                    <p>Income vs Expenditure performance over the last 6 months.</p>
                </div>
                <div class="mt-4" style="height: 350px;">
                    <canvas id="cashFlowChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Revenue Breakdown Donut -->
        <div class="col-12 col-xl-4">
            <div class="finance-panel h-100">
                <div class="finance-panel-head">
                    <h2><i class="bi bi-pie-chart-fill"></i> Revenue Split</h2>
                    <p>Breakdown by category (Current Month).</p>
                </div>
                <div class="mt-4 d-flex align-items-center justify-content-center" style="height: 300px;">
                    <?php if (empty($revenue_breakdown)): ?>
                        <div class="text-center text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            No revenue recorded this month.
                        </div>
                    <?php else: ?>
                        <canvas id="revenuePieChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="col-12 col-xl-12">
            <div class="finance-panel">
                <div class="finance-panel-head d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="bi bi-clock-history"></i> Recent Transactions</h2>
                        <p>A quick view of the latest financial entries.</p>
                    </div>
                    <a href="logs" class="btn btn-sm btn-outline-primary">View All Logs</a>
                </div>
                
                <div class="table-responsive finance-table-wrap mt-3">
                    <table class="table finance-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Description</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_transactions)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No recent transactions found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent_transactions as $tx): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($tx['transaction_date'])); ?></td>
                                        <td>
                                            <span class="finance-type-badge <?php echo $tx['type'] === 'income' ? 'type-income' : 'type-expense'; ?>">
                                                <?php echo ucfirst($tx['type']); ?>
                                            </span>
                                        </td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($tx['category']); ?></td>
                                        <td class="fw-bold <?php echo $tx['type'] === 'income' ? 'text-success' : 'text-danger'; ?>">
                                            GHS <?php echo number_format($tx['amount'], 2); ?>
                                        </td>
                                        <td class="small text-muted"><?php echo htmlspecialchars(mb_substr($tx['description'] ?? '', 0, 50)); ?>...</td>
                                        <td class="text-end">
                                            <a href="<?php echo $tx['type'] === 'income' ? 'income' : 'expenses'; ?>?view=<?php echo $tx['id']; ?>" class="btn btn-sm btn-light">Details</a>
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
document.addEventListener('DOMContentLoaded', function() {
    // 1. Cash Flow Trend Chart
    const ctxFlow = document.getElementById('cashFlowChart').getContext('2d');
    new Chart(ctxFlow, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [
                {
                    label: 'Income',
                    data: <?php echo json_encode($income_data); ?>,
                    backgroundColor: 'rgba(11, 143, 77, 0.7)',
                    borderRadius: 6,
                },
                {
                    label: 'Expenses',
                    data: <?php echo json_encode($expense_data); ?>,
                    backgroundColor: 'rgba(185, 28, 28, 0.7)',
                    borderRadius: 6,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: {
                y: { beginAtZero: true, grid: { display: false } },
                x: { grid: { display: false } }
            }
        }
    });

    // 2. Revenue Breakdown Pie Chart
    <?php if (!empty($revenue_breakdown)): ?>
    const ctxPie = document.getElementById('revenuePieChart').getContext('2d');
    new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($revenue_breakdown, 'category')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($revenue_breakdown, 'total')); ?>,
                backgroundColor: [
                    '#000080', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'
                ],
                borderWidth: 0
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
    <?php endif; ?>
});
</script>

<style>
/* Dashboard Specific Tweaks */
.finance-type-badge { width: 70px; text-align: center; }
.finance-panel canvas { width: 100% !important; }
</style>
