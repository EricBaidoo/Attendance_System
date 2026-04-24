<?php
require_once '../../includes/security.php';
requireLogin('../../login');
require_once '../../config/database.php';

$page_title = 'Communication Logs - ' . getInstitutionName($pdo);
$page_heading = 'Communication Delivery Logs';
$page_header = false;

$channels = ['all', 'sms', 'email', 'whatsapp', 'announcement', 'other'];
$statuses = ['all', 'queued', 'sent', 'delivered', 'failed'];

$filter_channel = strtolower(trim($_GET['channel'] ?? 'all'));
$filter_status = strtolower(trim($_GET['status'] ?? 'all'));
$filter_from = trim($_GET['from_date'] ?? date('Y-m-01'));
$filter_to = trim($_GET['to_date'] ?? date('Y-m-t'));
$error = '';

if (!in_array($filter_channel, $channels, true)) {
    $filter_channel = 'all';
}
if (!in_array($filter_status, $statuses, true)) {
    $filter_status = 'all';
}
if ($filter_from === '' || strtotime($filter_from) === false) {
    $filter_from = date('Y-m-01');
}
if ($filter_to === '' || strtotime($filter_to) === false) {
    $filter_to = date('Y-m-t');
}
if (strtotime($filter_from) > strtotime($filter_to)) {
    $tmp = $filter_from;
    $filter_from = $filter_to;
    $filter_to = $tmp;
}

$total_logs = 0;
$delivered_logs = 0;
$failed_logs = 0;
$queued_logs = 0;
$log_rows = [];

try {
    $where = ['DATE(COALESCE(l.sent_at, l.created_at)) BETWEEN ? AND ?'];
    $params = [$filter_from, $filter_to];

    if ($filter_channel !== 'all') {
        $where[] = 'l.channel = ?';
        $params[] = $filter_channel;
    }
    if ($filter_status !== 'all') {
        $where[] = 'l.status = ?';
        $params[] = $filter_status;
    }

    $where_sql = implode(' AND ', $where);

    $summary_stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_logs,
            SUM(CASE WHEN l.status IN ('sent', 'delivered') THEN 1 ELSE 0 END) AS delivered_logs,
            SUM(CASE WHEN l.status = 'failed' THEN 1 ELSE 0 END) AS failed_logs,
            SUM(CASE WHEN l.status = 'queued' THEN 1 ELSE 0 END) AS queued_logs
         FROM communication_logs l
         WHERE {$where_sql}"
    );
    $summary_stmt->execute($params);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $total_logs = (int)($summary['total_logs'] ?? 0);
    $delivered_logs = (int)($summary['delivered_logs'] ?? 0);
    $failed_logs = (int)($summary['failed_logs'] ?? 0);
    $queued_logs = (int)($summary['queued_logs'] ?? 0);

    // SQL for fetching logs (Shared by List and Export)
    $logs_query = "SELECT l.id, l.channel, l.recipient, l.status, l.message_preview, l.error_detail, l.sent_at, l.created_at,
                          c.name AS campaign_name
                   FROM communication_logs l
                   LEFT JOIN communication_campaigns c ON c.id = l.campaign_id
                   WHERE {$where_sql}
                   ORDER BY COALESCE(l.sent_at, l.created_at) DESC, l.id DESC";

    // Handle CSV Export
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        $export_stmt = $pdo->prepare($logs_query);
        $export_stmt->execute($params);
        $export_rows = $export_stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=communication_logs_' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Date', 'Campaign', 'Channel', 'Recipient', 'Status', 'Message Preview', 'Error Detail']);
        foreach ($export_rows as $row) {
            fputcsv($output, [
                $row['id'],
                date('Y-m-d H:i', strtotime((string)($row['sent_at'] ?: $row['created_at']))),
                $row['campaign_name'] ?: 'Manual',
                strtoupper((string)$row['channel']),
                $row['recipient'],
                $row['status'],
                $row['message_preview'],
                $row['error_detail']
            ]);
        }
        fclose($output);
        exit;
    }

    $list_stmt = $pdo->prepare($logs_query . " LIMIT 150");
    $list_stmt->execute($params);
    $log_rows = $list_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Communication logs table is unavailable. Please run database/db_updates.sql.';
}

include '../../includes/header.php';
?>
<link href="../../assets/css/communication.css?v=<?php echo @filemtime('../../assets/css/communication.css'); ?>" rel="stylesheet">

<div class="container-fluid communication-page py-4">
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <nav class="nav nav-pills communication-subnav mb-3">
        <a class="nav-link" href="dashboard">Overview</a>
        <a class="nav-link" href="campaigns">Campaigns</a>
        <a class="nav-link" href="sms">SMS Center</a>
        <a class="nav-link active" href="logs">Logs</a>
        <a class="nav-link" href="system_logs">System Logs</a>
    </nav>

    <section class="row g-3 communication-stats mb-1">
        <div class="col-12 col-md-6 col-xl-3">
            <article class="communication-stat-card">
                <div class="communication-stat-icon icon-blue"><i class="bi bi-list-check"></i></div>
                <div class="communication-stat-content">
                    <span class="communication-stat-label">Total Logs</span>
                    <h3 class="communication-stat-value"><?php echo number_format($total_logs); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <article class="communication-stat-card">
                <div class="communication-stat-icon icon-green"><i class="bi bi-check2-circle"></i></div>
                <div class="communication-stat-content">
                    <span class="communication-stat-label">Delivered</span>
                    <h3 class="communication-stat-value"><?php echo number_format($delivered_logs); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <article class="communication-stat-card">
                <div class="communication-stat-icon icon-red"><i class="bi bi-x-circle-fill"></i></div>
                <div class="communication-stat-content">
                    <span class="communication-stat-label">Failed</span>
                    <h3 class="communication-stat-value"><?php echo number_format($failed_logs); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <article class="communication-stat-card">
                <div class="communication-stat-icon icon-orange"><i class="bi bi-clock-fill"></i></div>
                <div class="communication-stat-content">
                    <span class="communication-stat-label">Queued</span>
                    <h3 class="communication-stat-value"><?php echo number_format($queued_logs); ?></h3>
                </div>
            </article>
        </div>
    </section>

    <div class="communication-panel mt-3">
        <div class="communication-panel-head">
            <h2><i class="bi bi-funnel-fill"></i> Filter Logs</h2>
            <p>Track delivery outcomes by channel, status, and date range.</p>
        </div>

        <form method="GET" class="row g-2 mt-2 communication-filter">
            <div class="col-12 col-md-3">
                <label class="form-label">From</label>
                <input type="date" class="form-control" name="from_date" value="<?php echo htmlspecialchars($filter_from); ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">To</label>
                <input type="date" class="form-control" name="to_date" value="<?php echo htmlspecialchars($filter_to); ?>">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Channel</label>
                <select name="channel" class="form-select">
                    <?php foreach ($channels as $channel): ?>
                        <option value="<?php echo htmlspecialchars($channel); ?>"<?php echo $filter_channel === $channel ? ' selected' : ''; ?>><?php echo htmlspecialchars(strtoupper($channel)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?php echo htmlspecialchars($status); ?>"<?php echo $filter_status === $status ? ' selected' : ''; ?>><?php echo htmlspecialchars(strtoupper($status)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Apply</button>
            </div>
            <div class="col-12 col-md-2 d-flex align-items-end">
                <button type="submit" name="export" value="csv" class="btn btn-success w-100"><i class="bi bi-download me-1"></i>Export</button>
            </div>
            <div class="col-12 col-md-2 d-flex align-items-end">
                <a href="logs" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>

    <div class="communication-panel mt-3">
        <div class="communication-panel-head">
            <h2><i class="bi bi-clock-history"></i> Delivery Log List</h2>
            <p>Grouped by date of transmission.</p>
        </div>

        <div class="communication-table-wrap mt-3">
            <table class="table communication-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="communication-col-date">Time</th>
                        <th class="communication-col-title">Campaign</th>
                        <th>Channel</th>
                        <th>Recipient</th>
                        <th>Status</th>
                        <th class="communication-col-content">Preview / Error</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($log_rows)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No logs found for selected filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $last_date = '';
                        foreach ($log_rows as $row): 
                            $curr_date = date('d M Y', strtotime((string)($row['sent_at'] ?: $row['created_at'])));
                            if ($curr_date !== $last_date):
                                $last_date = $curr_date;
                        ?>
                            <tr class="table-light">
                                <td colspan="6" class="p-2 ps-3 fw-bold text-secondary bg-body-tertiary">
                                    <i class="bi bi-calendar3 me-2"></i><?php echo $curr_date; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                            <tr>
                                <td class="communication-col-date ps-4"><?php echo htmlspecialchars(date('H:i', strtotime((string)($row['sent_at'] ?: $row['created_at'])))); ?></td>
                                <td class="communication-col-title"><?php echo htmlspecialchars((string)($row['campaign_name'] ?: 'Manual Entry')); ?></td>
                                <td><?php echo htmlspecialchars(strtoupper((string)$row['channel'])); ?></td>
                                <td><?php echo htmlspecialchars((string)($row['recipient'] ?: '-')); ?></td>
                                <td><span class="communication-badge status-<?php echo htmlspecialchars(strtolower((string)$row['status'])); ?>"><?php echo htmlspecialchars((string)$row['status']); ?></span></td>
                                <td class="communication-col-content">
                                    <?php echo htmlspecialchars((string)($row['message_preview'] ?: '-')); ?>
                                    <?php if (!empty($row['error_detail'])): ?>
                                        <div class="text-danger mt-1 small"><i class="bi bi-info-circle me-1"></i><?php echo htmlspecialchars((string)$row['error_detail']); ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

