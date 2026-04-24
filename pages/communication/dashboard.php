<?php
require_once '../../includes/security.php';
requireLogin('../../login');
require_once '../../config/database.php';

$page_title = 'Communication Dashboard - ' . getInstitutionName($pdo);
$page_heading = 'Communication';
$page_header = false;

$scheduled_campaigns = 0;
$messages_sent_month = 0;
$failed_messages_month = 0;
$queued_messages = 0;
$recent_campaigns = [];
$recent_logs = [];
$error = '';

try {
    $summary_stmt = $pdo->query(
        "SELECT
            (SELECT COUNT(*) FROM communication_campaigns WHERE status = 'scheduled') AS scheduled_campaigns,
            (SELECT COUNT(*) FROM communication_logs WHERE status IN ('sent','delivered') AND sent_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS messages_sent_month,
            (SELECT COUNT(*) FROM communication_logs WHERE status = 'failed' AND sent_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS failed_messages_month,
            (SELECT COUNT(*) FROM communication_logs WHERE status = 'queued') AS queued_messages"
    );
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $scheduled_campaigns = (int)($summary['scheduled_campaigns'] ?? 0);
    $messages_sent_month = (int)($summary['messages_sent_month'] ?? 0);
    $failed_messages_month = (int)($summary['failed_messages_month'] ?? 0);
    $queued_messages = (int)($summary['queued_messages'] ?? 0);

    // Distribution for Donut Chart
    $dist_stmt = $pdo->query(
        "SELECT status, COUNT(*) AS count FROM communication_logs GROUP BY status"
    );
    $status_distribution = $dist_stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    // Monthly Volume for Bar Chart (Last 6 Months)
    $volume_stmt = $pdo->query(
        "SELECT DATE_FORMAT(COALESCE(sent_at, created_at), '%b %Y') AS month,
                SUM(CASE WHEN channel = 'sms' THEN 1 ELSE 0 END) AS sms_count,
                SUM(CASE WHEN channel <> 'sms' THEN 1 ELSE 0 END) AS other_count
         FROM communication_logs
         WHERE COALESCE(sent_at, created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY month
         ORDER BY MIN(COALESCE(sent_at, created_at)) ASC"
    );
    $volume_trends = $volume_stmt->fetchAll(PDO::FETCH_ASSOC);

    $campaigns_stmt = $pdo->query(
        "SELECT id, name, channel, status, scheduled_at, sent_at, created_at
         FROM communication_campaigns
         ORDER BY COALESCE(scheduled_at, sent_at, created_at) DESC, id DESC
         LIMIT 8"
    );
    $recent_campaigns = $campaigns_stmt->fetchAll(PDO::FETCH_ASSOC);

    $logs_stmt = $pdo->query(
        "SELECT l.channel, l.status, l.recipient, l.message_preview, l.sent_at, l.created_at,
                c.name AS campaign_name
         FROM communication_logs l
         LEFT JOIN communication_campaigns c ON c.id = l.campaign_id
         ORDER BY COALESCE(l.sent_at, l.created_at) DESC, l.id DESC
         LIMIT 8"
    );
    $recent_logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Communication tables are unavailable. Please run database/db_updates.sql.';
}

include '../../includes/header.php';
?>

<link href="../../assets/css/communication.css?v=<?php echo @filemtime('../../assets/css/communication.css'); ?>" rel="stylesheet">

<div class="container-fluid communication-page py-4">
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <nav class="nav nav-pills communication-subnav mb-3">
        <a class="nav-link active" href="dashboard">Overview</a>
        <a class="nav-link" href="campaigns">Campaigns</a>
        <a class="nav-link" href="sms">SMS Center</a>
        <a class="nav-link" href="logs">Logs</a>
    </nav>

    <section class="row g-3 communication-stats mb-1">
        <div class="col-12 col-md-6 col-xl-3">
            <article class="communication-stat-card">
                <div class="communication-stat-icon icon-orange"><i class="bi bi-calendar-check-fill"></i></div>
                <div class="communication-stat-content">
                    <span class="communication-stat-label">Scheduled Campaigns</span>
                    <h3 class="communication-stat-value"><?php echo number_format($scheduled_campaigns); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <article class="communication-stat-card">
                <div class="communication-stat-icon icon-green"><i class="bi bi-send-check-fill"></i></div>
                <div class="communication-stat-content">
                    <span class="communication-stat-label">Messages Sent (Month)</span>
                    <h3 class="communication-stat-value"><?php echo number_format($messages_sent_month); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <article class="communication-stat-card">
                <div class="communication-stat-icon icon-red"><i class="bi bi-x-octagon-fill"></i></div>
                <div class="communication-stat-content">
                    <span class="communication-stat-label">Failed Deliveries (Month)</span>
                    <h3 class="communication-stat-value"><?php echo number_format($failed_messages_month); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <article class="communication-stat-card">
                <div class="communication-stat-icon icon-blue"><i class="bi bi-hourglass-split"></i></div>
                <div class="communication-stat-content">
                    <span class="communication-stat-label">Queued Messages</span>
                    <h3 class="communication-stat-value"><?php echo number_format($queued_messages); ?></h3>
                </div>
            </article>
        </div>
    </section>

    <section class="row g-3 mt-1">
        <div class="col-12 col-xl-4">
            <div class="communication-panel h-100">
                <div class="communication-panel-head">
                    <h2><i class="bi bi-pie-chart-fill"></i> Delivery Success</h2>
                    <p>Outcome distribution of all messages.</p>
                </div>
                <div class="p-3" style="min-height: 250px;">
                    <canvas id="deliveryOutcomeChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-8">
            <div class="communication-panel h-100">
                <div class="communication-panel-head">
                    <h2><i class="bi bi-bar-chart-fill"></i> Monthly Volume</h2>
                    <p>Total messages sent per month (Last 6 months).</p>
                </div>
                <div class="p-3" style="min-height: 250px;">
                    <canvas id="monthlyVolumeChart"></canvas>
                </div>
            </div>
        </div>
    </section>

    <div class="communication-panel mt-3">
        <div class="communication-panel-head">
            <h2><i class="bi bi-lightning-charge-fill"></i> Quick Actions</h2>
            <p>Go directly to communication operations.</p>
        </div>
        <div class="communication-quick-links mt-3">
            <a class="communication-quick-link" href="sms"><i class="bi bi-chat-square-text-fill"></i> Open SMS Center</a>
            <a class="communication-quick-link" href="campaigns"><i class="bi bi-broadcast-pin"></i> Manage Campaigns</a>
            <a class="communication-quick-link" href="announcements"><i class="bi bi-megaphone-fill"></i> Manage Announcements</a>
            <a class="communication-quick-link" href="logs"><i class="bi bi-clock-history"></i> View Delivery Logs</a>
            <a class="communication-quick-link" href="system_logs"><i class="bi bi-journal-text"></i> View System Logs</a>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12 col-xl-6">
            <div class="communication-panel h-100">
                <div class="communication-panel-head">
                    <h2><i class="bi bi-broadcast"></i> Recent Campaigns</h2>
                    <p>Latest campaign activity.</p>
                </div>

                <div class="communication-table-wrap mt-3">
                    <table class="table communication-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="communication-col-date">Date</th>
                                <th class="communication-col-title">Campaign</th>
                                <th>Channel</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_campaigns)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No campaigns yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_campaigns as $campaign): ?>
                                    <tr>
                                        <td class="communication-col-date"><?php echo htmlspecialchars(date('d M Y', strtotime((string)($campaign['scheduled_at'] ?: $campaign['sent_at'] ?: $campaign['created_at'])))); ?></td>
                                        <td class="communication-col-title"><?php echo htmlspecialchars((string)$campaign['name']); ?></td>
                                        <td><?php echo htmlspecialchars(strtoupper((string)$campaign['channel'])); ?></td>
                                        <td><span class="communication-badge status-<?php echo htmlspecialchars(strtolower((string)$campaign['status'])); ?>"><?php echo htmlspecialchars((string)$campaign['status']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="communication-panel h-100">
                <div class="communication-panel-head">
                    <h2><i class="bi bi-clock-history"></i> Recent SMS Logs</h2>
                    <p>Latest delivery outcomes.</p>
                </div>

                <div class="communication-table-wrap mt-3">
                    <table class="table communication-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="communication-col-date">Date</th>
                                <th class="communication-col-title">Campaign</th>
                                <th>Channel</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_logs)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No delivery logs yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_logs as $log): ?>
                                    <tr>
                                        <td class="communication-col-date"><?php echo htmlspecialchars(date('d M Y', strtotime((string)($log['sent_at'] ?: $log['created_at'])))); ?></td>
                                        <td class="communication-col-title"><?php echo htmlspecialchars((string)($log['campaign_name'] ?: 'Manual')); ?></td>
                                        <td><?php echo htmlspecialchars(strtoupper((string)$log['channel'])); ?></td>
                                        <td><span class="communication-badge status-<?php echo htmlspecialchars(strtolower((string)$log['status'])); ?>"><?php echo htmlspecialchars((string)$log['status']); ?></span></td>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delivery Outcome Chart (Donut)
    const outcomeCtx = document.getElementById('deliveryOutcomeChart').getContext('2d');
    const outcomeData = {
        labels: ['Delivered', 'Failed', 'Queued'],
        datasets: [{
            data: [
                <?php echo (int)($status_distribution['sent'] ?? 0) + (int)($status_distribution['delivered'] ?? 0); ?>,
                <?php echo (int)($status_distribution['failed'] ?? 0); ?>,
                <?php echo (int)($status_distribution['queued'] ?? 0); ?>
            ],
            backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
            borderWidth: 0,
            hoverOffset: 10
        }]
    };
    new Chart(outcomeCtx, {
        type: 'doughnut',
        data: outcomeData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            },
            cutout: '70%'
        }
    });

    // Monthly Volume Chart (Bar)
    const volumeCtx = document.getElementById('monthlyVolumeChart').getContext('2d');
    const volumeLabels = <?php echo json_encode(array_column($volume_trends, 'month')); ?>;
    new Chart(volumeCtx, {
        type: 'bar',
        data: {
            labels: volumeLabels,
            datasets: [
                {
                    label: 'SMS Messages',
                    data: <?php echo json_encode(array_column($volume_trends, 'sms_count')); ?>,
                    backgroundColor: '#0d6efd',
                    borderRadius: 5
                },
                {
                    label: 'Other Channels',
                    data: <?php echo json_encode(array_column($volume_trends, 'other_count')); ?>,
                    backgroundColor: '#e9ecef',
                    borderRadius: 5
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { display: false } },
                x: { grid: { display: false } }
            },
            plugins: {
                legend: { position: 'top' }
            }
        }
    });
});
</script>
<?php include '../../includes/footer.php'; ?>

