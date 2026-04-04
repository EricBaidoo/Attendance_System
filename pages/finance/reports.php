<?php
require_once '../../includes/security.php';
requireLogin('../../login');
require_once '../../config/database.php';

$page_title = 'Financial Reports - Bridge Ministries International';
$page_heading = 'Financial Reports';
$page_header = false;

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$comparison_period = $_GET['comparison_period'] ?? 'monthly';
$statement_period = $_GET['statement_period'] ?? 'monthly';

$allowed_periods = ['weekly', 'monthly', 'quarterly', 'annual'];
if (!in_array($comparison_period, $allowed_periods, true)) {
    $comparison_period = 'monthly';
}
if (!in_array($statement_period, $allowed_periods, true)) {
    $statement_period = 'monthly';
}

$start_dt = DateTime::createFromFormat('Y-m-d', $start_date);
$end_dt = DateTime::createFromFormat('Y-m-d', $end_date);

if (!$start_dt || $start_dt->format('Y-m-d') !== $start_date) {
    $start_date = date('Y-m-01');
}
if (!$end_dt || $end_dt->format('Y-m-d') !== $end_date) {
    $end_date = date('Y-m-t');
}
if ($start_date > $end_date) {
    $tmp = $start_date;
    $start_date = $end_date;
    $end_date = $tmp;
}

$range_start = new DateTime($start_date);
$range_end = new DateTime($end_date);
$range_days = (int)$range_start->diff($range_end)->days + 1;

$previous_end = (clone $range_start)->modify('-1 day');
$previous_start = (clone $previous_end)->modify('-' . ($range_days - 1) . ' days');
$previous_start_date = $previous_start->format('Y-m-d');
$previous_end_date = $previous_end->format('Y-m-d');

$periodSqlParts = static function (string $period): array {
    switch ($period) {
        case 'weekly':
            return [
                'group_key' => "DATE_FORMAT(transaction_date, '%x-W%v')",
                'label' => "CONCAT('W', DATE_FORMAT(transaction_date, '%v'), ' ', DATE_FORMAT(transaction_date, '%x'))",
                'order_key' => "DATE_FORMAT(transaction_date, '%x%v')",
            ];
        case 'quarterly':
            return [
                'group_key' => "CONCAT(YEAR(transaction_date), '-Q', QUARTER(transaction_date))",
                'label' => "CONCAT('Q', QUARTER(transaction_date), ' ', YEAR(transaction_date))",
                'order_key' => "CONCAT(YEAR(transaction_date), LPAD(QUARTER(transaction_date), 2, '0'))",
            ];
        case 'annual':
            return [
                'group_key' => 'YEAR(transaction_date)',
                'label' => 'CAST(YEAR(transaction_date) AS CHAR)',
                'order_key' => 'YEAR(transaction_date)',
            ];
        case 'monthly':
        default:
            return [
                'group_key' => "DATE_FORMAT(transaction_date, '%Y-%m')",
                'label' => "DATE_FORMAT(transaction_date, '%b %Y')",
                'order_key' => "DATE_FORMAT(transaction_date, '%Y-%m')",
            ];
    }
};

$buildPeriodSeries = static function (PDO $pdo, string $start, string $end, string $period) use ($periodSqlParts): array {
    $parts = $periodSqlParts($period);
    $sql = "SELECT
                {$parts['group_key']} AS period_key,
                {$parts['label']} AS period_label,
                {$parts['order_key']} AS order_key,
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount END), 0) AS income_total,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) AS expense_total,
                COUNT(*) AS transactions_total
            FROM finance_transactions
            WHERE transaction_date BETWEEN ? AND ?
            GROUP BY period_key, period_label, order_key
            ORDER BY order_key ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start, $end]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
};

$summary = [
    'income_total' => 0,
    'expense_total' => 0,
    'net_total' => 0,
    'transactions_total' => 0,
];

$previous_summary = [
    'income_total' => 0,
    'expense_total' => 0,
    'net_total' => 0,
    'transactions_total' => 0,
];

$income_categories = [];
$expense_categories = [];
$comparison_rows = [];
$statement_rows = [];
$error = '';

try {
    $summary_stmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN type = 'income' THEN amount END), 0) AS income_total,
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) AS expense_total,
            COUNT(*) AS transactions_total
         FROM finance_transactions
         WHERE transaction_date BETWEEN ? AND ?"
    );
    $summary_stmt->execute([$start_date, $end_date]);
    $sum = $summary_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $summary['income_total'] = (float)($sum['income_total'] ?? 0);
    $summary['expense_total'] = (float)($sum['expense_total'] ?? 0);
    $summary['transactions_total'] = (int)($sum['transactions_total'] ?? 0);
    $summary['net_total'] = $summary['income_total'] - $summary['expense_total'];

    $previous_stmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN type = 'income' THEN amount END), 0) AS income_total,
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) AS expense_total,
            COUNT(*) AS transactions_total
         FROM finance_transactions
         WHERE transaction_date BETWEEN ? AND ?"
    );
    $previous_stmt->execute([$previous_start_date, $previous_end_date]);
    $prev = $previous_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $previous_summary['income_total'] = (float)($prev['income_total'] ?? 0);
    $previous_summary['expense_total'] = (float)($prev['expense_total'] ?? 0);
    $previous_summary['transactions_total'] = (int)($prev['transactions_total'] ?? 0);
    $previous_summary['net_total'] = $previous_summary['income_total'] - $previous_summary['expense_total'];

    $category_stmt = $pdo->prepare(
        "SELECT type, category, COALESCE(SUM(amount), 0) AS total_amount
         FROM finance_transactions
         WHERE transaction_date BETWEEN ? AND ?
         GROUP BY type, category
         ORDER BY type ASC, total_amount DESC"
    );
    $category_stmt->execute([$start_date, $end_date]);
    $category_rows = $category_stmt->fetchAll(PDO::FETCH_ASSOC);

    $income_categories = array_values(array_filter($category_rows, static fn($row) => ($row['type'] ?? '') === 'income'));
    $expense_categories = array_values(array_filter($category_rows, static fn($row) => ($row['type'] ?? '') === 'expense'));

    $comparison_rows = $buildPeriodSeries($pdo, $start_date, $end_date, $comparison_period);
    $statement_rows = $buildPeriodSeries($pdo, $start_date, $end_date, $statement_period);
} catch (Exception $e) {
    $error = 'Financial reports are unavailable. Please run database updates first.';
}

$safePercentChange = static function (float $current, float $previous): ?float {
    if ($previous == 0.0) {
        return $current == 0.0 ? 0.0 : null;
    }

    return (($current - $previous) / abs($previous)) * 100;
};

$formatChange = static function (?float $value): string {
    if ($value === null) {
        return 'New';
    }

    return ($value >= 0 ? '+' : '') . number_format($value, 1) . '%';
};

$income_change = $safePercentChange($summary['income_total'], $previous_summary['income_total']);
$expense_change = $safePercentChange($summary['expense_total'], $previous_summary['expense_total']);
$net_change = $safePercentChange($summary['net_total'], $previous_summary['net_total']);

$avg_income_daily = $range_days > 0 ? $summary['income_total'] / $range_days : 0;
$avg_expense_daily = $range_days > 0 ? $summary['expense_total'] / $range_days : 0;
$expense_ratio = $summary['income_total'] > 0 ? ($summary['expense_total'] / $summary['income_total']) * 100 : 0;
$net_margin_ratio = $summary['income_total'] > 0 ? ($summary['net_total'] / $summary['income_total']) * 100 : 0;
$avg_transaction_value = $summary['transactions_total'] > 0 ? (($summary['income_total'] + $summary['expense_total']) / $summary['transactions_total']) : 0;

$income_top = $income_categories[0] ?? null;
$expense_top = $expense_categories[0] ?? null;
$income_distribution = array_slice($income_categories, 0, 5);
$expense_distribution = array_slice($expense_categories, 0, 5);

$comparison_labels = array_map(static fn($row) => (string)$row['period_label'], $comparison_rows);
$comparison_income_data = array_map(static fn($row) => (float)($row['income_total'] ?? 0), $comparison_rows);
$comparison_expense_data = array_map(static fn($row) => (float)($row['expense_total'] ?? 0), $comparison_rows);
$comparison_net_data = array_map(
    static fn($row) => (float)($row['income_total'] ?? 0) - (float)($row['expense_total'] ?? 0),
    $comparison_rows
);

$statement_income_total = array_reduce($statement_rows, static fn($carry, $row) => $carry + (float)($row['income_total'] ?? 0), 0.0);
$statement_expense_total = array_reduce($statement_rows, static fn($carry, $row) => $carry + (float)($row['expense_total'] ?? 0), 0.0);
$statement_net_total = $statement_income_total - $statement_expense_total;

$performance_status = 'Stable';
if ($net_margin_ratio >= 20) {
    $performance_status = 'Strong Surplus';
} elseif ($net_margin_ratio < 0) {
    $performance_status = 'Deficit';
}

$cost_control_status = 'Controlled';
if ($expense_ratio > 85) {
    $cost_control_status = 'High Cost Pressure';
} elseif ($expense_ratio > 70) {
    $cost_control_status = 'Watch Closely';
}

$revenue_momentum_status = 'Flat';
if ($income_change !== null && $income_change >= 10) {
    $revenue_momentum_status = 'Growing';
} elseif ($income_change !== null && $income_change <= -10) {
    $revenue_momentum_status = 'Declining';
}

include '../../includes/header.php';
?>
<link href="../../assets/css/finance.css?v=<?php echo @filemtime('../../assets/css/finance.css'); ?>" rel="stylesheet">
<link href="../../assets/css/reports.css?v=<?php echo @filemtime('../../assets/css/reports.css'); ?>" rel="stylesheet">

<div class="container-fluid finance-page py-4">
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="finance-panel finance-report-hero mb-3">
        <div class="finance-panel-head d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <h2><i class="bi bi-clipboard2-data-fill"></i> Financial Reporting</h2>
                <p>Clear, accountant-style reporting with period comparisons and statement-ready outputs.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="finance-chip"><i class="bi bi-calendar3 me-1"></i><?php echo htmlspecialchars(date('d M Y', strtotime($start_date))); ?> - <?php echo htmlspecialchars(date('d M Y', strtotime($end_date))); ?></span>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print();"><i class="bi bi-printer me-1"></i>Print</button>
                <a href="<?php echo htmlspecialchars(moduleScopedPageUrl('finance', 'statement_export', ['start_date' => $start_date, 'end_date' => $end_date, 'statement_period' => $statement_period, 'format' => 'pdf'])); ?>" class="btn btn-primary btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>Statement PDF</a>
                <a href="<?php echo htmlspecialchars(moduleScopedPageUrl('finance', 'statement_export', ['start_date' => $start_date, 'end_date' => $end_date, 'statement_period' => $statement_period, 'format' => 'csv'])); ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-filetype-csv me-1"></i>Statement CSV</a>
            </div>
        </div>

        <form method="GET" class="finance-filter-grid finance-report-filter mt-3">
            <div>
                <label class="form-label">Start Date</label>
                <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div>
                <label class="form-label">End Date</label>
                <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div>
                <label class="form-label">Comparison Period</label>
                <select class="form-select" name="comparison_period">
                    <option value="weekly" <?php echo $comparison_period === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                    <option value="monthly" <?php echo $comparison_period === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                    <option value="quarterly" <?php echo $comparison_period === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                    <option value="annual" <?php echo $comparison_period === 'annual' ? 'selected' : ''; ?>>Annual</option>
                </select>
            </div>
            <div>
                <label class="form-label">Statement Period</label>
                <select class="form-select" name="statement_period">
                    <option value="weekly" <?php echo $statement_period === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                    <option value="monthly" <?php echo $statement_period === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                    <option value="quarterly" <?php echo $statement_period === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                    <option value="annual" <?php echo $statement_period === 'annual' ? 'selected' : ''; ?>>Annual</option>
                </select>
            </div>
            <div class="finance-filter-action">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Apply</button>
            </div>
        </form>

        <?php $finance_report_base = moduleScopedPageUrl('finance', 'reports'); ?>
        <div class="finance-quick-range mt-3">
            <a href="<?php echo htmlspecialchars($finance_report_base . '?' . http_build_query(['start_date' => date('Y-m-01'), 'end_date' => date('Y-m-t'), 'comparison_period' => $comparison_period, 'statement_period' => $statement_period])); ?>" class="finance-chip-link">This Month</a>
            <a href="<?php echo htmlspecialchars($finance_report_base . '?' . http_build_query(['start_date' => date('Y-m-d', strtotime('-29 days')), 'end_date' => date('Y-m-d'), 'comparison_period' => $comparison_period, 'statement_period' => $statement_period])); ?>" class="finance-chip-link">Last 30 Days</a>
            <a href="<?php echo htmlspecialchars($finance_report_base . '?' . http_build_query(['start_date' => date('Y-01-01'), 'end_date' => date('Y-m-d'), 'comparison_period' => $comparison_period, 'statement_period' => $statement_period])); ?>" class="finance-chip-link">Year to Date</a>
        </div>
    </div>

    <section class="row g-3 mb-1">
        <div class="col-12 col-md-6 col-xl-3">
            <article class="finance-stat-card">
                <div class="finance-stat-icon icon-income"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Revenue</span>
                    <h3 class="finance-stat-value">GHS <?php echo number_format($summary['income_total'], 2); ?></h3>
                    <small class="finance-muted-line"><?php echo htmlspecialchars($formatChange($income_change)); ?> vs prior period</small>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <article class="finance-stat-card">
                <div class="finance-stat-icon icon-expense"><i class="bi bi-graph-down-arrow"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Expenditure</span>
                    <h3 class="finance-stat-value">GHS <?php echo number_format($summary['expense_total'], 2); ?></h3>
                    <small class="finance-muted-line"><?php echo htmlspecialchars($formatChange($expense_change)); ?> vs prior period</small>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <article class="finance-stat-card">
                <div class="finance-stat-icon icon-balance"><i class="bi bi-bank2"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Net Result</span>
                    <h3 class="finance-stat-value <?php echo $summary['net_total'] >= 0 ? 'text-success-emphasis' : 'text-danger-emphasis'; ?>">GHS <?php echo number_format($summary['net_total'], 2); ?></h3>
                    <small class="finance-muted-line"><?php echo htmlspecialchars($formatChange($net_change)); ?> vs prior period</small>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <article class="finance-stat-card">
                <div class="finance-stat-icon icon-entries"><i class="bi bi-journal-check"></i></div>
                <div class="finance-stat-content">
                    <span class="finance-stat-label">Transactions</span>
                    <h3 class="finance-stat-value"><?php echo number_format($summary['transactions_total']); ?></h3>
                    <small class="finance-muted-line">Avg value: GHS <?php echo number_format($avg_transaction_value, 2); ?></small>
                </div>
            </article>
        </div>
    </section>

    <section class="row g-3 mt-1">
        <div class="col-12">
            <div class="finance-panel finance-summary-panel">
                <div class="finance-panel-head d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h2><i class="bi bi-briefcase-fill"></i> Management Summary</h2>
                        <p>Accountant-style interpretation for quick executive review.</p>
                    </div>
                    <span class="finance-chip"><i class="bi bi-check2-square me-1"></i>Status: <?php echo htmlspecialchars($performance_status); ?></span>
                </div>
                <div class="management-grid mt-3">
                    <article class="management-note">
                        <h3>Profitability</h3>
                        <p>
                            Net margin is <strong><?php echo number_format($net_margin_ratio, 1); ?>%</strong>
                            with a net result of <strong>GHS <?php echo number_format($summary['net_total'], 2); ?></strong>
                            for the selected period.
                        </p>
                    </article>
                    <article class="management-note">
                        <h3>Cost Control</h3>
                        <p>
                            Expense ratio is <strong><?php echo number_format($expense_ratio, 1); ?>%</strong>
                            of total revenue, currently marked as
                            <strong><?php echo htmlspecialchars($cost_control_status); ?></strong>.
                        </p>
                    </article>
                    <article class="management-note">
                        <h3>Revenue Momentum</h3>
                        <p>
                            Revenue change is <strong><?php echo htmlspecialchars($formatChange($income_change)); ?></strong>
                            versus the prior period, indicating
                            <strong><?php echo htmlspecialchars($revenue_momentum_status); ?></strong> momentum.
                        </p>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <section class="row g-3 mt-1">
        <div class="col-12 col-xl-8">
            <div class="finance-panel h-100 finance-chart-panel">
                <div class="finance-panel-head">
                    <h2><i class="bi bi-bar-chart-line-fill"></i> Period Comparison</h2>
                    <p>Revenue and expenditure by <?php echo htmlspecialchars($comparison_period); ?> period.</p>
                </div>
                <div class="chart-container mt-3">
                    <?php if (empty($comparison_rows)): ?>
                        <div class="text-center text-muted py-5">No chart data in the selected date range.</div>
                    <?php else: ?>
                        <canvas id="financePeriodChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="finance-panel h-100 finance-insight-panel">
                <div class="finance-panel-head">
                    <h2><i class="bi bi-clipboard-check-fill"></i> Accounting Insights</h2>
                    <p>High-level interpretation of current financial performance.</p>
                </div>
                <ul class="finance-insight-list mt-3">
                    <li><span>Net margin</span><strong class="<?php echo $net_margin_ratio >= 0 ? 'text-success-emphasis' : 'text-danger-emphasis'; ?>"><?php echo number_format($net_margin_ratio, 1); ?>%</strong></li>
                    <li><span>Expense ratio</span><strong><?php echo number_format($expense_ratio, 1); ?>%</strong></li>
                    <li><span>Avg daily revenue</span><strong>GHS <?php echo number_format($avg_income_daily, 2); ?></strong></li>
                    <li><span>Avg daily expenditure</span><strong>GHS <?php echo number_format($avg_expense_daily, 2); ?></strong></li>
                    <li><span>Top revenue source</span><strong><?php echo htmlspecialchars((string)($income_top['category'] ?? 'N/A')); ?></strong></li>
                    <li><span>Top expenditure driver</span><strong><?php echo htmlspecialchars((string)($expense_top['category'] ?? 'N/A')); ?></strong></li>
                </ul>
                <div class="chart-container chart-container-compact mt-3">
                    <?php if (!empty($comparison_rows)): ?>
                        <canvas id="financeNetChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="row g-3 mt-1">
        <div class="col-12">
            <div class="finance-panel">
                <div class="finance-panel-head d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h2><i class="bi bi-file-earmark-text-fill"></i> Periodic Financial Statement</h2>
                        <p>Statement grouped by <?php echo htmlspecialchars($statement_period); ?> period for periodic reporting.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="finance-chip">Periods: <?php echo number_format(count($statement_rows)); ?></span>
                        <span class="finance-chip">Revenue: GHS <?php echo number_format($statement_income_total, 2); ?></span>
                        <span class="finance-chip">Net: GHS <?php echo number_format($statement_net_total, 2); ?></span>
                    </div>
                </div>

                <div class="table-responsive finance-table-wrap mt-3">
                    <table class="table finance-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th class="text-end">Revenue (GHS)</th>
                                <th class="text-end">Expenditure (GHS)</th>
                                <th class="text-end">Net (GHS)</th>
                                <th class="text-end">Transactions</th>
                                <th class="text-end">Expense Ratio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($statement_rows)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No statement rows available for the selected period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($statement_rows as $row): ?>
                                    <?php
                                        $row_income = (float)($row['income_total'] ?? 0);
                                        $row_expense = (float)($row['expense_total'] ?? 0);
                                        $row_net = $row_income - $row_expense;
                                        $row_expense_ratio = $row_income > 0 ? ($row_expense / $row_income) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)$row['period_label']); ?></td>
                                        <td class="text-end"><?php echo number_format($row_income, 2); ?></td>
                                        <td class="text-end"><?php echo number_format($row_expense, 2); ?></td>
                                        <td class="text-end fw-semibold <?php echo $row_net >= 0 ? 'text-success-emphasis' : 'text-danger-emphasis'; ?>"><?php echo number_format($row_net, 2); ?></td>
                                        <td class="text-end"><?php echo number_format((int)($row['transactions_total'] ?? 0)); ?></td>
                                        <td class="text-end"><?php echo number_format($row_expense_ratio, 1); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($statement_rows)): ?>
                            <tfoot>
                                <tr>
                                    <th>Total</th>
                                    <th class="text-end"><?php echo number_format($statement_income_total, 2); ?></th>
                                    <th class="text-end"><?php echo number_format($statement_expense_total, 2); ?></th>
                                    <th class="text-end <?php echo $statement_net_total >= 0 ? 'text-success-emphasis' : 'text-danger-emphasis'; ?>"><?php echo number_format($statement_net_total, 2); ?></th>
                                    <th class="text-end"><?php echo number_format((int)$summary['transactions_total']); ?></th>
                                    <th class="text-end"><?php echo $statement_income_total > 0 ? number_format(($statement_expense_total / $statement_income_total) * 100, 1) : '0.0'; ?>%</th>
                                </tr>
                            </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <section class="row g-3 mt-1">
        <div class="col-12 col-xl-6">
            <div class="finance-panel h-100">
                <div class="finance-panel-head">
                    <h2><i class="bi bi-list-columns-reverse"></i> Revenue Category Statement</h2>
                    <p>Detailed revenue categories for the selected period.</p>
                </div>
                <div class="table-responsive finance-table-wrap mt-3">
                    <?php if (empty($income_distribution)): ?>
                        <div class="text-center text-muted py-4">No revenue records in this period.</div>
                    <?php else: ?>
                        <table class="table finance-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-end">Amount (GHS)</th>
                                    <th class="text-end">Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($income_distribution as $row): ?>
                                    <?php $share = $summary['income_total'] > 0 ? (((float)$row['total_amount'] / $summary['income_total']) * 100) : 0; ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)$row['category']); ?></td>
                                        <td class="text-end"><?php echo number_format((float)$row['total_amount'], 2); ?></td>
                                        <td class="text-end"><?php echo number_format($share, 1); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="finance-panel h-100">
                <div class="finance-panel-head">
                    <h2><i class="bi bi-list-columns"></i> Expenditure Category Statement</h2>
                    <p>Detailed expenditure categories for the selected period.</p>
                </div>
                <div class="table-responsive finance-table-wrap mt-3">
                    <?php if (empty($expense_distribution)): ?>
                        <div class="text-center text-muted py-4">No expenditure records in this period.</div>
                    <?php else: ?>
                        <table class="table finance-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-end">Amount (GHS)</th>
                                    <th class="text-end">Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expense_distribution as $row): ?>
                                    <?php $share = $summary['expense_total'] > 0 ? (((float)$row['total_amount'] / $summary['expense_total']) * 100) : 0; ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)$row['category']); ?></td>
                                        <td class="text-end"><?php echo number_format((float)$row['total_amount'], 2); ?></td>
                                        <td class="text-end"><?php echo number_format($share, 1); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(() => {
    const labels = <?php echo json_encode($comparison_labels, JSON_UNESCAPED_UNICODE); ?>;
    const incomeData = <?php echo json_encode($comparison_income_data, JSON_NUMERIC_CHECK); ?>;
    const expenseData = <?php echo json_encode($comparison_expense_data, JSON_NUMERIC_CHECK); ?>;
    const netData = <?php echo json_encode($comparison_net_data, JSON_NUMERIC_CHECK); ?>;

    const hasData = Array.isArray(labels) && labels.length > 0;

    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: (value) => `GHS ${Number(value).toLocaleString()}`
                }
            }
        }
    };

    const periodCtx = document.getElementById('financePeriodChart');
    if (periodCtx && hasData) {
        new Chart(periodCtx, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Revenue',
                        data: incomeData,
                        backgroundColor: 'rgba(25, 135, 84, 0.75)',
                        borderColor: 'rgba(25, 135, 84, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Expenditure',
                        data: expenseData,
                        backgroundColor: 'rgba(220, 53, 69, 0.75)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: chartOptions
        });
    }

    const netCtx = document.getElementById('financeNetChart');
    if (netCtx && hasData) {
        new Chart(netCtx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Net',
                        data: netData,
                        borderColor: 'rgba(13, 110, 253, 0.95)',
                        backgroundColor: 'rgba(13, 110, 253, 0.2)',
                        tension: 0.3,
                        fill: true,
                        pointRadius: 3,
                        pointHoverRadius: 4
                    }
                ]
            },
            options: chartOptions
        });
    }
})();
</script>

<?php include '../../includes/footer.php'; ?>

