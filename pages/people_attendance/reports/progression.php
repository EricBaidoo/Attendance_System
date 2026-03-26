<?php
// pages/people_attendance/reports/progression.php
require_once '../../../includes/security.php';
requireLogin('../../../login.php');

require_once '../../../config/database.php';

$page_title = 'Progression Report - Bridge Ministries International';

$start_date = isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date'])
    ? $_GET['start_date']
    : date('Y-m-01');
$end_date = isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date'])
    ? $_GET['end_date']
    : date('Y-m-d');

if ($start_date > $end_date) {
    $tmp = $start_date;
    $start_date = $end_date;
    $end_date = $tmp;
}

$range_start = $start_date . ' 00:00:00';
$range_end = $end_date . ' 23:59:59';

$metrics = [
    'visitors_in_period' => 0,
    'new_converts_in_period' => 0,
    'became_members_in_period' => 0,
    'visitor_to_new_convert_in_period' => 0,
    'new_convert_to_member_in_period' => 0,
    'visitor_to_member_in_period' => 0,
];

$current_stage = [
    'visitor' => 0,
    'new_convert' => 0,
    'member' => 0,
];

$daily_funnel = [];
$journey_rows = [];
$error_message = null;

try {
    // Stage-entry counts in selected period.
    $funnel_stmt = $pdo->prepare(
        "SELECT
            COUNT(DISTINCT CASE WHEN event_type = 'visitor_checked_in' THEN person_id END) AS visitors_in_period,
            COUNT(DISTINCT CASE WHEN event_type = 'became_new_convert' THEN person_id END) AS new_converts_in_period,
            COUNT(DISTINCT CASE WHEN event_type = 'became_member' THEN person_id END) AS became_members_in_period
         FROM person_lifecycle_events
         WHERE event_date BETWEEN ? AND ?"
    );
    $funnel_stmt->execute([$range_start, $range_end]);
    $funnel_row = $funnel_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $metrics['visitors_in_period'] = (int)($funnel_row['visitors_in_period'] ?? 0);
    $metrics['new_converts_in_period'] = (int)($funnel_row['new_converts_in_period'] ?? 0);
    $metrics['became_members_in_period'] = (int)($funnel_row['became_members_in_period'] ?? 0);

    // Source-path tracking: people who converted this period and had prior source events.
    $path_stmt = $pdo->prepare(
        "SELECT
            COUNT(DISTINCT CASE
                WHEN nc.event_type = 'became_new_convert'
                     AND EXISTS (
                         SELECT 1
                         FROM person_lifecycle_events v
                         WHERE v.person_id = nc.person_id
                           AND v.event_type = 'visitor_checked_in'
                           AND v.event_date <= nc.event_date
                     )
                THEN nc.person_id END
            ) AS visitor_to_new_convert_in_period,
            COUNT(DISTINCT CASE
                WHEN m.event_type = 'became_member'
                     AND EXISTS (
                         SELECT 1
                         FROM person_lifecycle_events nc2
                         WHERE nc2.person_id = m.person_id
                           AND nc2.event_type = 'became_new_convert'
                           AND nc2.event_date <= m.event_date
                     )
                THEN m.person_id END
            ) AS new_convert_to_member_in_period,
            COUNT(DISTINCT CASE
                WHEN m.event_type = 'became_member'
                     AND EXISTS (
                         SELECT 1
                         FROM person_lifecycle_events v2
                         WHERE v2.person_id = m.person_id
                           AND v2.event_type = 'visitor_checked_in'
                           AND v2.event_date <= m.event_date
                     )
                THEN m.person_id END
            ) AS visitor_to_member_in_period
         FROM person_lifecycle_events nc
         LEFT JOIN person_lifecycle_events m
           ON m.person_id = nc.person_id
          AND m.event_type = 'became_member'
          AND m.event_date BETWEEN ? AND ?
         WHERE nc.event_date BETWEEN ? AND ?"
    );
    $path_stmt->execute([$range_start, $range_end, $range_start, $range_end]);
    $path_row = $path_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $metrics['visitor_to_new_convert_in_period'] = (int)($path_row['visitor_to_new_convert_in_period'] ?? 0);
    $metrics['new_convert_to_member_in_period'] = (int)($path_row['new_convert_to_member_in_period'] ?? 0);
    $metrics['visitor_to_member_in_period'] = (int)($path_row['visitor_to_member_in_period'] ?? 0);

    // Current stage snapshot from canonical people table.
    $stage_stmt = $pdo->query(
        "SELECT current_stage, COUNT(*) AS cnt
         FROM people
         GROUP BY current_stage"
    );
    foreach ($stage_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $stage = (string)($row['current_stage'] ?? '');
        if (array_key_exists($stage, $current_stage)) {
            $current_stage[$stage] = (int)$row['cnt'];
        }
    }

    // Daily funnel trend for chart/table.
    $daily_stmt = $pdo->prepare(
        "SELECT
            DATE(event_date) AS event_day,
            COUNT(DISTINCT CASE WHEN event_type = 'visitor_checked_in' THEN person_id END) AS visitors,
            COUNT(DISTINCT CASE WHEN event_type = 'became_new_convert' THEN person_id END) AS new_converts,
            COUNT(DISTINCT CASE WHEN event_type = 'became_member' THEN person_id END) AS members
         FROM person_lifecycle_events
         WHERE event_date BETWEEN ? AND ?
         GROUP BY DATE(event_date)
         ORDER BY event_day ASC"
    );
    $daily_stmt->execute([$range_start, $range_end]);
    $daily_funnel = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Person-level progression rows in selected period.
    $journey_stmt = $pdo->prepare(
        "SELECT
            p.id AS person_id,
            p.full_name,
            p.current_stage,
            MIN(CASE WHEN e.event_type = 'visitor_checked_in' THEN e.event_date END) AS first_visitor_at,
            MIN(CASE WHEN e.event_type = 'became_new_convert' THEN e.event_date END) AS first_new_convert_at,
            MIN(CASE WHEN e.event_type = 'became_member' THEN e.event_date END) AS first_member_at,
            MAX(e.event_date) AS latest_event_at
         FROM people p
         JOIN person_lifecycle_events e ON e.person_id = p.id
         WHERE e.event_date BETWEEN ? AND ?
         GROUP BY p.id, p.full_name, p.current_stage
         ORDER BY latest_event_at DESC, p.full_name ASC
         LIMIT 200"
    );
    $journey_stmt->execute([$range_start, $range_end]);
    $journey_rows = $journey_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = 'Failed to load progression report: ' . $e->getMessage();
}

include '../../../includes/header.php';
?>
<link href="../../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="../../../assets/css/reports.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<div class="container-fluid py-4">
    <?php if ($error_message): ?>
        <div class="alert alert-danger border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <div class="row mb-3">
        <div class="col-12">
            <div class="card report-card">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h1 class="h3 text-primary mb-1 fw-bold">
                                <i class="bi bi-signpost-split"></i> Progression & Journey Report
                            </h1>
                            <p class="text-muted mb-0">
                                Visitor -> New Convert -> Member tracking from lifecycle events
                            </p>
                        </div>
                        <div>
                            <a href="report.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card filter-card">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold mb-1 small">Start Date</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold mb-1 small">End Date</label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                        <div class="col-md-4 d-grid">
                            <button class="btn btn-primary btn-sm" type="submit">
                                <i class="bi bi-funnel"></i> Apply Range
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card stat-card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg,#0d6efd,#0056b3);">
                <div class="card-body">
                    <div class="small text-white-50">Visitors In Period</div>
                    <div class="h4 mb-0 text-white"><?php echo number_format($metrics['visitors_in_period']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card stat-card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg,#17a2b8,#0f6f80);">
                <div class="card-body">
                    <div class="small text-white-50">New Converts In Period</div>
                    <div class="h4 mb-0 text-white"><?php echo number_format($metrics['new_converts_in_period']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card stat-card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg,#198754,#0f5f3a);">
                <div class="card-body">
                    <div class="small text-white-50">Became Members</div>
                    <div class="h4 mb-0 text-white"><?php echo number_format($metrics['became_members_in_period']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card stat-card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg,#6f42c1,#4b2b84);">
                <div class="card-body">
                    <div class="small text-white-50">Visitor -> Convert</div>
                    <div class="h4 mb-0 text-white"><?php echo number_format($metrics['visitor_to_new_convert_in_period']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card stat-card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg,#fd7e14,#aa4d06);">
                <div class="card-body">
                    <div class="small text-white-50">Convert -> Member</div>
                    <div class="h4 mb-0 text-white"><?php echo number_format($metrics['new_convert_to_member_in_period']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card stat-card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg,#dc3545,#7f1d28);">
                <div class="card-body">
                    <div class="small text-white-50">Visitor -> Member</div>
                    <div class="h4 mb-0 text-white"><?php echo number_format($metrics['visitor_to_member_in_period']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="card report-card h-100">
                <div class="card-header"><strong>Current Stage Snapshot</strong></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>Visitor</span><span class="fw-bold"><?php echo number_format($current_stage['visitor']); ?></span></div>
                    <div class="d-flex justify-content-between mb-2"><span>New Convert</span><span class="fw-bold"><?php echo number_format($current_stage['new_convert']); ?></span></div>
                    <div class="d-flex justify-content-between"><span>Member</span><span class="fw-bold"><?php echo number_format($current_stage['member']); ?></span></div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card report-card h-100">
                <div class="card-header"><strong>Daily Funnel (Distinct People)</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th class="text-end">Visitors</th>
                                    <th class="text-end">New Converts</th>
                                    <th class="text-end">Members</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($daily_funnel)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">No lifecycle events in this date range.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($daily_funnel as $day): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($day['event_day']); ?></td>
                                            <td class="text-end"><?php echo number_format((int)$day['visitors']); ?></td>
                                            <td class="text-end"><?php echo number_format((int)$day['new_converts']); ?></td>
                                            <td class="text-end"><?php echo number_format((int)$day['members']); ?></td>
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

    <div class="row">
        <div class="col-12">
            <div class="card report-card">
                <div class="card-header">
                    <strong>Person-Level Journey Rows (Period Activity)</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Person ID</th>
                                    <th>Full Name</th>
                                    <th>Current Stage</th>
                                    <th>First Visitor Date</th>
                                    <th>First Convert Date</th>
                                    <th>First Member Date</th>
                                    <th>Latest Event</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($journey_rows)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-3">No journey activity found for this range.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($journey_rows as $row): ?>
                                        <tr>
                                            <td><?php echo (int)$row['person_id']; ?></td>
                                            <td><?php echo htmlspecialchars((string)$row['full_name']); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars((string)$row['current_stage']); ?></span></td>
                                            <td><?php echo $row['first_visitor_at'] ? htmlspecialchars((string)$row['first_visitor_at']) : '-'; ?></td>
                                            <td><?php echo $row['first_new_convert_at'] ? htmlspecialchars((string)$row['first_new_convert_at']) : '-'; ?></td>
                                            <td><?php echo $row['first_member_at'] ? htmlspecialchars((string)$row['first_member_at']) : '-'; ?></td>
                                            <td><?php echo $row['latest_event_at'] ? htmlspecialchars((string)$row['latest_event_at']) : '-'; ?></td>
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
</div>

<?php include '../../../includes/footer.php'; ?>
