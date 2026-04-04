<?php
require_once '../../includes/security.php';
requireLogin('../../login');

$user_role = getUserRole();
if (!canAccessModule('finance', $user_role)) {
    header('Location: ../../index');
    exit;
}

require_once '../../includes/system_logs.php';

$log_type = strtolower(trim($_GET['log'] ?? 'error'));
$available_logs = appLogFiles();
if (!isset($available_logs[$log_type])) {
    $log_type = 'error';
}

$log_path = $available_logs[$log_type];
$filter_keyword = trim($_GET['q'] ?? '');
$filter_from = trim($_GET['from_date'] ?? '');
$filter_to = trim($_GET['to_date'] ?? '');
$log_entries_all = appLogTail($log_path, 300);
$log_entries = appLogFilterEntries($log_entries_all, $filter_keyword, $filter_from, $filter_to);
$log_meta = appLogFileMeta($log_path);

$page_title = 'Finance Logs - Bridge Ministries International';
$page_heading = 'Finance Logs';
$page_header = false;

include '../../includes/header.php';
?>
<link href="../../assets/css/finance.css?v=<?php echo @filemtime('../../assets/css/finance.css'); ?>" rel="stylesheet">

<div class="container-fluid finance-page py-4">
    <div class="finance-hero mb-3">
        <div class="finance-hero-main">
            <p class="finance-eyebrow">FINANCE MODULE</p>
            <h1>System Logs</h1>
            <p>Inspect finance-related errors and application activity from the shared log files.</p>
        </div>
        <div class="finance-date-wrap">
            <span class="finance-date-label">Updated</span>
            <div class="finance-date"><?php echo !empty($log_meta['modified']) ? date('M d, Y H:i', (int)$log_meta['modified']) : 'Unavailable'; ?></div>
        </div>
    </div>

    <nav class="nav nav-pills mb-3">
        <a class="nav-link" href="dashboard">Dashboard</a>
        <a class="nav-link" href="income">Income</a>
        <a class="nav-link" href="expenses">Expenses</a>
        <a class="nav-link" href="tithers">Tithers</a>
        <a class="nav-link" href="reports">Reports</a>
        <a class="nav-link active" href="logs">Logs</a>
    </nav>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="text-muted small text-uppercase">Active Log</div>
                <div class="h5 mb-0"><?php echo htmlspecialchars(appLogFileLabel($log_type)); ?></div>
            </div>
            <div class="btn-group">
                <a class="btn btn-outline-primary <?php echo $log_type === 'error' ? 'active' : ''; ?>" href="logs?log=error">Error Log</a>
                <a class="btn btn-outline-primary <?php echo $log_type === 'activity' ? 'active' : ''; ?>" href="logs?log=activity">Activity Log</a>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3 p-md-4">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="log" value="<?php echo htmlspecialchars($log_type); ?>">
                <div class="col-12 col-md-5">
                    <label class="form-label">Keyword</label>
                    <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($filter_keyword); ?>" placeholder="Search error text, user, module, or endpoint">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">From</label>
                    <input type="date" class="form-control" name="from_date" value="<?php echo htmlspecialchars($filter_from); ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">To</label>
                    <input type="date" class="form-control" name="to_date" value="<?php echo htmlspecialchars($filter_to); ?>">
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                    <a href="logs?log=<?php echo htmlspecialchars($log_type); ?>" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i><?php echo htmlspecialchars(appLogFileLabel($log_type)); ?></h5>
            <small class="text-muted"><?php echo htmlspecialchars($log_path); ?></small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 90px;">Line</th>
                            <th>Entry</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($log_entries)): ?>
                            <tr>
                                <td colspan="2" class="text-center text-muted py-4">No log entries found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($log_entries as $index => $entry): ?>
                                <tr>
                                    <td class="text-muted"><?php echo number_format($index + 1); ?></td>
                                    <td><pre class="mb-0" style="white-space: pre-wrap; word-break: break-word;"><?php echo htmlspecialchars($entry); ?></pre></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

