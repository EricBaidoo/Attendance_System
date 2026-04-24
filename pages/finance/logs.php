<?php
require_once '../../includes/security.php';
requireLogin('../../login');

$user_role = getUserRole();
if (!canAccessModule('finance', $user_role)) { header('Location: ../../index'); exit; }

require_once '../../includes/system_logs.php';

$log_type = strtolower(trim($_GET['log'] ?? 'error'));
$available_logs = appLogFiles();
if (!isset($available_logs[$log_type])) { $log_type = 'error'; }

$log_path = $available_logs[$log_type];
$filter_keyword = trim($_GET['q'] ?? '');
$filter_from = trim($_GET['from_date'] ?? '');
$filter_to = trim($_GET['to_date'] ?? '');
$log_entries_all = appLogTail($log_path, 300);
$log_entries = appLogFilterEntries($log_entries_all, $filter_keyword, $filter_from, $filter_to);
$log_meta = appLogFileMeta($log_path);

$page_title = 'Finance Logs - ' . getInstitutionName($pdo);
$page_heading = 'Finance Logs';
$page_header = false;

include '../../includes/header.php';
?>
<link href="../../assets/css/finance.css?v=<?php echo time(); ?>" rel="stylesheet">

<div class="container-fluid finance-page py-4">
    <header class="finance-hero mb-4">
        <div class="finance-hero-main">
            <p class="finance-eyebrow">SYSTEM MONITORING</p>
            <h1>Application Logs</h1>
            <p>Inspect system activity and error reports for the finance module. Showing <b><?php echo ucfirst($log_type); ?></b> logs.</p>
            <div class="finance-quick-actions">
                <a href="dashboard" class="finance-quick-btn"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a href="reports" class="finance-quick-btn"><i class="bi bi-file-earmark-bar-graph"></i> Financial Reports</a>
            </div>
        </div>
        <div class="finance-date-wrap d-none d-md-block text-end">
            <span class="finance-date-label">Last Modified</span>
            <div class="finance-date"><?php echo !empty($log_meta['modified']) ? date('M d, H:i', (int)$log_meta['modified']) : 'Unknown'; ?></div>
            <div class="btn-group mt-3 bg-white p-1 rounded-pill shadow-sm border">
                <a class="btn btn-sm rounded-pill <?php echo $log_type === 'error' ? 'btn-danger px-3' : 'btn-light'; ?>" href="logs?log=error">Errors</a>
                <a class="btn btn-sm rounded-pill <?php echo $log_type === 'activity' ? 'btn-primary px-3' : 'btn-light'; ?>" href="logs?log=activity">Activity</a>
            </div>
        </div>
    </header>

    <div class="row g-4">
        <!-- Search & Filter -->
        <div class="col-12">
            <div class="finance-panel glass-panel border-0 shadow-sm">
                <form method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="log" value="<?php echo htmlspecialchars($log_type); ?>">
                    <div class="col-12 col-md-5">
                        <label class="form-label small fw-bold">SEARCH KEYWORD</label>
                        <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($filter_keyword); ?>" placeholder="Search errors, users, or modules...">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-bold">FROM</label>
                        <input type="date" class="form-control" name="from_date" value="<?php echo htmlspecialchars($filter_from); ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-bold">TO</label>
                        <input type="date" class="form-control" name="to_date" value="<?php echo htmlspecialchars($filter_to); ?>">
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-filter me-1"></i> Apply Filter</button>
                            <a href="logs?log=<?php echo $log_type; ?>" class="btn btn-light border"><i class="bi bi-x-circle"></i></a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Log Content -->
        <div class="col-12">
            <div class="finance-panel border-0 shadow-sm active-card">
                <div class="finance-panel-head mb-4 border-bottom pb-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h5 mb-1"><i class="bi bi-file-earmark-text text-accent"></i> Raw Log Data</h2>
                        <code class="small text-muted"><?php echo htmlspecialchars($log_path); ?></code>
                    </div>
                    <span class="badge bg-light text-dark border px-3"><?php echo count($log_entries); ?> Entries Found</span>
                </div>
                
                <div class="table-responsive finance-table-wrap">
                    <table class="table finance-table align-middle">
                        <thead>
                            <tr class="table-light">
                                <th style="width: 80px;">Line</th>
                                <th>Log Entry</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($log_entries)): ?>
                                <tr><td colspan="2" class="text-center py-5 text-muted">No matching logs found in this timeframe.</td></tr>
                            <?php else: ?>
                                <?php foreach (array_reverse($log_entries, true) as $index => $entry): ?>
                                    <tr>
                                        <td class="text-muted small"><?php echo number_format($index + 1); ?></td>
                                        <td>
                                            <div class="log-entry-text p-2 rounded <?php echo strpos(strtolower($entry), 'error') !== false ? 'bg-danger-subtle text-danger' : 'bg-light'; ?>">
                                                <pre class="mb-0 small" style="white-space: pre-wrap; font-family: 'Courier New', Courier, monospace; line-height: 1.4;"><?php echo htmlspecialchars($entry); ?></pre>
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

<style>
.glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(8px); border: 1px solid rgba(255, 255, 255, 0.4) !important; }
.active-card { border-left: 4px solid var(--finance-primary) !important; }
.log-entry-text { border: 1px solid rgba(0,0,0,0.05); }
pre { max-height: 200px; overflow-y: auto; }
</style>

<?php include '../../includes/footer.php'; ?>
