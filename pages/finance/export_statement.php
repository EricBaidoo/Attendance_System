<?php
require_once '../../includes/security.php';
requireLogin('../../login');
require_once '../../config/database.php';
require_once '../../includes/pdf_export.php';

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$statement_period = $_GET['statement_period'] ?? 'monthly';
$format = strtolower((string)($_GET['format'] ?? 'csv'));

$allowed_periods = ['weekly', 'monthly', 'quarterly', 'annual'];
if (!in_array($statement_period, $allowed_periods, true)) {
    $statement_period = 'monthly';
}

if (!in_array($format, ['csv', 'pdf'], true)) {
    $format = 'csv';
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

$statement_rows = [];

try {
    $parts = $periodSqlParts($statement_period);
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
    $stmt->execute([$start_date, $end_date]);
    $statement_rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unable to generate statement export.';
    exit;
}

$base_filename = sprintf(
    'financial_statement_%s_%s_to_%s',
    $statement_period,
    str_replace('-', '', $start_date),
    str_replace('-', '', $end_date)
);

$total_income = 0.0;
$total_expense = 0.0;
$total_transactions = 0;

foreach ($statement_rows as $row) {
    $income = (float)($row['income_total'] ?? 0);
    $expense = (float)($row['expense_total'] ?? 0);
    $net = $income - $expense;
    $expense_ratio = $income > 0 ? ($expense / $income) * 100 : 0;

    $total_income += $income;
    $total_expense += $expense;
    $total_transactions += (int)($row['transactions_total'] ?? 0);

}

$total_net = $total_income - $total_expense;
$total_expense_ratio = $total_income > 0 ? ($total_expense / $total_income) * 100 : 0;

if ($format === 'pdf') {
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Financial Statement</title><style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #1f2937; }
        .header { margin-bottom: 12px; }
        .header h1 { margin: 0 0 4px; font-size: 18px; }
        .meta { margin-bottom: 12px; font-size: 10px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 6px; }
        th { background: #f3f4f6; text-align: left; }
        .text-end { text-align: right; }
        tfoot th { background: #eef2ff; }
    </style></head><body>';

    $html .= '<div class="header"><h1>Bridge Ministries International</h1><div>Financial Statement</div></div>';
    $html .= '<div class="meta">Period Grouping: ' . htmlspecialchars(ucfirst($statement_period)) . '<br>';
    $html .= 'Date Range: ' . htmlspecialchars($start_date . ' to ' . $end_date) . '<br>';
    $html .= 'Generated: ' . htmlspecialchars(date('Y-m-d H:i:s')) . '</div>';
    $html .= '<table><thead><tr><th>Period</th><th class="text-end">Revenue (GHS)</th><th class="text-end">Expenditure (GHS)</th><th class="text-end">Net (GHS)</th><th class="text-end">Transactions</th><th class="text-end">Expense Ratio (%)</th></tr></thead><tbody>';

    foreach ($statement_rows as $row) {
        $income = (float)($row['income_total'] ?? 0);
        $expense = (float)($row['expense_total'] ?? 0);
        $net = $income - $expense;
        $expense_ratio = $income > 0 ? ($expense / $income) * 100 : 0;

        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars((string)($row['period_label'] ?? '')) . '</td>';
        $html .= '<td class="text-end">' . number_format($income, 2) . '</td>';
        $html .= '<td class="text-end">' . number_format($expense, 2) . '</td>';
        $html .= '<td class="text-end">' . number_format($net, 2) . '</td>';
        $html .= '<td class="text-end">' . number_format((int)($row['transactions_total'] ?? 0)) . '</td>';
        $html .= '<td class="text-end">' . number_format($expense_ratio, 1) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody><tfoot><tr>';
    $html .= '<th>Total</th>';
    $html .= '<th class="text-end">' . number_format($total_income, 2) . '</th>';
    $html .= '<th class="text-end">' . number_format($total_expense, 2) . '</th>';
    $html .= '<th class="text-end">' . number_format($total_net, 2) . '</th>';
    $html .= '<th class="text-end">' . number_format($total_transactions) . '</th>';
    $html .= '<th class="text-end">' . number_format($total_expense_ratio, 1) . '</th>';
    $html .= '</tr></tfoot></table></body></html>';

    exportHtmlAsPdf($html, $base_filename . '.pdf', ['paper' => 'A4', 'orientation' => 'portrait']);
}

$filename = $base_filename . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

fputcsv($output, ['Bridge Ministries International']);
fputcsv($output, ['Financial Statement']);
fputcsv($output, ['Period Grouping', ucfirst($statement_period)]);
fputcsv($output, ['Date Range', $start_date . ' to ' . $end_date]);
fputcsv($output, []);
fputcsv($output, ['Period', 'Revenue (GHS)', 'Expenditure (GHS)', 'Net (GHS)', 'Transactions', 'Expense Ratio (%)']);

foreach ($statement_rows as $row) {
    $income = (float)($row['income_total'] ?? 0);
    $expense = (float)($row['expense_total'] ?? 0);
    $net = $income - $expense;
    $expense_ratio = $income > 0 ? ($expense / $income) * 100 : 0;

    fputcsv($output, [
        (string)($row['period_label'] ?? ''),
        number_format($income, 2, '.', ''),
        number_format($expense, 2, '.', ''),
        number_format($net, 2, '.', ''),
        (int)($row['transactions_total'] ?? 0),
        number_format($expense_ratio, 1, '.', ''),
    ]);
}

fputcsv($output, []);
fputcsv($output, [
    'Total',
    number_format($total_income, 2, '.', ''),
    number_format($total_expense, 2, '.', ''),
    number_format($total_net, 2, '.', ''),
    $total_transactions,
    number_format($total_expense_ratio, 1, '.', ''),
]);

fclose($output);
exit;

