<?php
require_once '../../includes/security.php';
requireLogin('../../login');

$user_role = getUserRole();
if (!canAccessModule('people_attendance', $user_role)) {
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

$page_title = 'People & Attendance Logs - ' . getInstitutionName($pdo);
$page_heading = 'People & Attendance Logs';
$page_header = false;

include '../../includes/header.php';
?>
<link href="../../assets/css/dashboard_enhanced.css?v=<?php echo @filemtime('../../assets/css/dashboard_enhanced.css'); ?>" rel="stylesheet">

<div class="container-fluid py-4">
    <div class="card border-0 shadow-lg mb-3">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h1 class="text-primary mb-1"><i class="bi bi-journal-text"></i> People &amp; Attendance Logs</h1>
                    <p class="text-muted mb-0">Trace errors and application activity from the shared log files.</p>
                </div>
                <div class="btn-group">
                    <a class="btn btn-outline-primary <?php echo $log_type === 'error' ? 'active' : ''; ?>" href="logs?log=error">Error Log</a>
                    <a class="btn btn-outline-primary <?php echo $log_type === 'activity' ? 'active' : ''; ?>" href="logs?log=activity">Activity Log</a>
                </div>
            </div>
        </div>
    </div>

    <nav class="nav nav-pills mb-3">
        <a class="nav-link" href="attendance/dashboard">Dashboard</a>
        <a class="nav-link" href="members/list">Members</a>
        <a class="nav-link" href="visitors/list">Visitors</a>
        <a class="nav-link" href="checkin/checkin">Check-in</a>
        <a class="nav-link" href="services/list">Services</a>
        <a class="nav-link" href="reports/report">Reports</a>
        <a class="nav-link active" href="logs">Logs</a>
    </nav>

    <div class="card border-0 shadow-lg mb-3">
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

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Current Log</div>
                    <div class="h5 mb-0"><?php echo htmlspecialchars(appLogFileLabel($log_type)); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Entries Filtered</div>
                    <div class="h5 mb-0"><?php echo number_format(count($log_entries)); ?> / <?php echo number_format(count($log_entries_all)); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Last Modified</div>
                    <div class="h5 mb-0"><?php echo !empty($log_meta['modified']) ? date('Y-m-d H:i:s', (int)$log_meta['modified']) : 'Unavailable'; ?></div>
                </div>
            </div>
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
