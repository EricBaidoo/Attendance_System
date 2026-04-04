<?php
require_once '../../includes/security.php';
requireLogin('../../login');

$user_role = getUserRole();
if (!canAccessModule('communication', $user_role)) {
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

$page_title = 'Communication System Logs - Bridge Ministries International';
$page_heading = 'Communication System Logs';
$page_header = false;

include '../../includes/header.php';
?>
<link href="../../assets/css/communication.css?v=<?php echo @filemtime('../../assets/css/communication.css'); ?>" rel="stylesheet">

<div class="container-fluid communication-page py-4">
    <nav class="nav nav-pills communication-subnav mb-3">
        <a class="nav-link" href="dashboard">Overview</a>
        <a class="nav-link" href="campaigns">Campaigns</a>
        <a class="nav-link" href="sms">SMS Center</a>
        <a class="nav-link" href="logs">Delivery Logs</a>
        <a class="nav-link active" href="system_logs">System Logs</a>
    </nav>

    <div class="communication-panel mb-3">
        <div class="communication-panel-head">
            <h2><i class="bi bi-journal-text"></i> System Log Viewer</h2>
            <p>Trace application errors and background activity for the communication module.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <a class="btn btn-outline-primary <?php echo $log_type === 'error' ? 'active' : ''; ?>" href="system_logs?log=error">Error Log</a>
            <a class="btn btn-outline-primary <?php echo $log_type === 'activity' ? 'active' : ''; ?>" href="system_logs?log=activity">Activity Log</a>
        </div>
    </div>

    <div class="communication-panel mb-3">
        <div class="communication-panel-head">
            <h2><i class="bi bi-funnel-fill"></i> Filter Logs</h2>
            <p>Search by keyword or narrow entries by date range.</p>
        </div>
        <form method="GET" class="row g-2 mt-2">
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
            <div class="col-12 col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                <a href="system_logs?log=<?php echo htmlspecialchars($log_type); ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="communication-panel h-100">
                <div class="communication-panel-head">
                    <h2>Current Log</h2>
                    <p><?php echo htmlspecialchars(appLogFileLabel($log_type)); ?></p>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="communication-panel h-100">
                <div class="communication-panel-head">
                    <h2>Entries Filtered</h2>
                    <p><?php echo number_format(count($log_entries)); ?> / <?php echo number_format(count($log_entries_all)); ?></p>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="communication-panel h-100">
                <div class="communication-panel-head">
                    <h2>Last Modified</h2>
                    <p><?php echo !empty($log_meta['modified']) ? date('Y-m-d H:i:s', (int)$log_meta['modified']) : 'Unavailable'; ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="communication-panel">
        <div class="communication-panel-head">
            <h2><i class="bi bi-file-earmark-text"></i> <?php echo htmlspecialchars(appLogFileLabel($log_type)); ?></h2>
            <p><?php echo htmlspecialchars($log_path); ?></p>
        </div>
        <div class="communication-table-wrap mt-3">
            <table class="table communication-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="communication-col-date" style="width: 90px;">Line</th>
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
                                <td class="communication-col-date"><?php echo number_format($index + 1); ?></td>
                                <td><pre class="mb-0" style="white-space: pre-wrap; word-break: break-word;"><?php echo htmlspecialchars($entry); ?></pre></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

