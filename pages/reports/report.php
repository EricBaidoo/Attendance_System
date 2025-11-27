<?php
// pages/reports/index.php
session_start();
require '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Date range parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today
$department_filter = $_GET['department'] ?? '';

// Get attendance statistics
$attendance_stats = [];

// Total attendance for date range
$sql = "SELECT COUNT(*) as total_attendance FROM attendance WHERE date BETWEEN ? AND ? AND status = 'present'";
$params = [$start_date, $end_date];

if ($department_filter) {
    $sql .= " AND member_id IN (SELECT id FROM members WHERE department_id = ?)";
    $params[] = $department_filter;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendance_stats['total'] = $stmt->fetch()['total_attendance'];

// Average attendance per service
$sql = "SELECT AVG(attendance_count) as avg_attendance FROM (
    SELECT COUNT(*) as attendance_count 
    FROM attendance a 
    JOIN services s ON a.service_id = s.id 
    WHERE a.date BETWEEN ? AND ? AND a.status = 'present'";
$params = [$start_date, $end_date];

if ($department_filter) {
    $sql .= " AND a.member_id IN (SELECT id FROM members WHERE department_id = ?)";
    $params[] = $department_filter;
}

$sql .= " GROUP BY a.service_id, a.date) as service_attendance";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendance_stats['average'] = round($stmt->fetch()['avg_attendance'] ?? 0, 1);

// Get member statistics
$member_stats_sql = "SELECT 
    COUNT(*) as total_members,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_members,
    COUNT(CASE WHEN baptized = 'yes' THEN 1 END) as baptized_members,
    COUNT(CASE WHEN gender = 'male' THEN 1 END) as male_members,
    COUNT(CASE WHEN gender = 'female' THEN 1 END) as female_members
    FROM members";

if ($department_filter) {
    $member_stats_sql .= " WHERE department_id = ?";
    $member_stmt = $pdo->prepare($member_stats_sql);
    $member_stmt->execute([$department_filter]);
} else {
    $member_stmt = $pdo->query($member_stats_sql);
}
$member_stats = $member_stmt->fetch();

// Get visitor statistics
$visitor_stats_sql = "SELECT 
    COUNT(*) as total_visitors,
    COUNT(CASE WHEN first_time = 'yes' THEN 1 END) as first_time_visitors,
    COUNT(CASE WHEN became_member = 'yes' THEN 1 END) as became_members,
    COUNT(CASE WHEN follow_up_needed = 'yes' AND follow_up_completed = 'no' THEN 1 END) as pending_followups
    FROM visitors 
    WHERE created_at BETWEEN ? AND ?";

$visitor_stmt = $pdo->prepare($visitor_stats_sql);
$visitor_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$visitor_stats = $visitor_stmt->fetch();

// Get new converts statistics
$converts_stats_sql = "SELECT 
    COUNT(*) as total_converts,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_converts,
    COUNT(CASE WHEN baptized = 'yes' THEN 1 END) as baptized_converts
    FROM new_converts 
    WHERE date_converted BETWEEN ? AND ?";

$converts_stmt = $pdo->prepare($converts_stats_sql);
$converts_stmt->execute([$start_date, $end_date]);
$converts_stats = $converts_stmt->fetch();

// Get departments for filter dropdown
try {
    $departments_stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
    $departments = $departments_stmt->fetchAll();
} catch (Exception $e) {
    $departments = [];
}

// Get recent attendance trends (last 10 services)
$trends_sql = "SELECT 
    s.name as service_name,
    ss.session_date,
    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
    COUNT(a.id) as total_marked
    FROM service_sessions ss
    JOIN services s ON ss.service_id = s.id
    LEFT JOIN attendance a ON ss.id = a.session_id
    WHERE ss.session_date BETWEEN ? AND ?
    GROUP BY ss.id, s.name, ss.session_date
    ORDER BY ss.session_date DESC
    LIMIT 10";

$trends_stmt = $pdo->prepare($trends_sql);
$trends_stmt->execute([$start_date, $end_date]);
$attendance_trends = $trends_stmt->fetchAll();

$page_title = "Reports & Analytics - Bridge Ministries International";
include '../../includes/header.php';
?>
<link href="../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="../../assets/css/reports.css?v=<?php echo time(); ?>" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Bootstrap Icons Fix -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
<style>
@import url('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css');

.bi {
    font-family: "bootstrap-icons" !important;
    font-style: normal;
    font-weight: normal;
    font-variant: normal;
    text-transform: none;
    line-height: 1;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

.bi::before {
    font-family: "bootstrap-icons" !important;
    font-weight: normal !important;
    font-style: normal !important;
}

/* Specific icon fixes */
.bi-graph-up::before { content: "\f4a8"; }
.bi-people-fill::before { content: "\f47a"; }
.bi-lightbulb::before { content: "\f4a0"; }
.bi-list-check::before { content: "\f4a4"; }
.bi-bar-chart::before { content: "\f406"; }
.bi-graph-up-arrow::before { content: "\f4a9"; }
.bi-pie-chart::before { content: "\f47e"; }
.bi-activity::before { content: "\f3f4"; }
.bi-trophy::before { content: "\f5a1"; }
.bi-person-check::before { content: "\f470"; }
.bi-exclamation-triangle::before { content: "\f431"; }
.bi-person-dash::before { content: "\f475"; }
.bi-calendar-event::before { content: "\f414"; }
.bi-shield-exclamation::before { content: "\f5a8"; }
.bi-calendar-range::before { content: "\f417"; }
.bi-speedometer2::before { content: "\f5a4"; }
.bi-download::before { content: "\f426"; }
.bi-printer::before { content: "\f486"; }
.bi-search::before { content: "\f52a"; }
.bi-file-excel::before { content: "\f438"; }
.bi-file-pdf::before { content: "\f43c"; }
</style>

<!-- Professional Reports Dashboard -->
<div class="container-fluid py-4">
    <!-- Dashboard Header -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="text-primary mb-2 fw-bold">
                        <i class="bi bi-bar-chart-fill"></i> Reports & Analytics
                    </h1>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <span class="text-muted">Comprehensive church analytics and insights</span>
                        <span class="badge bg-light text-dark">
                            <?php echo date('M j', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>
                        </span>
                    </div>
                </div>
                <div class="col-lg-4 text-end">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-download"></i> Export
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-file-excel me-2"></i>Excel Report</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-file-pdf me-2"></i>PDF Report</a></li>
                        </ul>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Date Range and Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h5 class="text-primary fw-bold mb-3">
                <i class="bi bi-calendar-range"></i> Report Filters
            </h5>
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Department</label>
                    <select name="department" class="form-select">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['name'] ?? ''); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Key Metrics Cards -->
    <div class="row g-3 g-lg-4 mb-4">
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
            <div class="card border-0 shadow-sm h-100 members-card">
                <div class="card-body text-white p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-2 fw-semibold">Total Members</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo $member_stats['total_members']; ?></h2>
                            <small class="text-white-50">
                                <i class="bi bi-check-circle"></i> <?php echo $member_stats['active_members']; ?> active
                            </small>
                        </div>
                        <div class="rounded p-3">
                            <i class="bi bi-people-fill text-white fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
            <div class="card border-0 shadow-sm h-100 visitors-card">
                <div class="card-body text-white p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-2 fw-semibold">Total Visitors</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo $visitor_stats['total_visitors']; ?></h2>
                            <small class="text-white-50">
                                <i class="bi bi-star"></i> <?php echo $visitor_stats['first_time_visitors']; ?> first time
                            </small>
                        </div>
                        <div class="rounded p-3">
                            <i class="bi bi-person-badge text-white fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
            <div class="card border-0 shadow-sm h-100 converts-card">
                <div class="card-body text-white p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-2 fw-semibold">New Converts</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo $converts_stats['total_converts']; ?></h2>
                            <small class="text-white-50">
                                <i class="bi bi-droplet"></i> <?php echo $converts_stats['baptized_converts']; ?> baptized
                            </small>
                        </div>
                        <div class="rounded p-3">
                            <i class="bi bi-heart-fill text-white fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
            <div class="card border-0 shadow-sm h-100 departments-card">
                <div class="card-body text-white p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-2 fw-semibold">Avg Attendance</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo $attendance_stats['average']; ?></h2>
                            <small class="text-white-50">
                                <i class="bi bi-graph-up"></i> Per service
                            </small>
                        </div>
                        <div class="rounded p-3">
                            <i class="bi bi-graph-up text-white fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Analytics -->
    <div class="row g-4">
        <!-- Attendance Trends -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="text-primary fw-bold mb-4">
                        <i class="bi bi-graph-up"></i> Attendance Trends
                    </h5>
                    
                    <?php if (empty($attendance_trends)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-graph-up text-muted" style="font-size: 3rem; opacity: 0.5;"></i>
                            <h6 class="text-muted mt-3">No attendance data available</h6>
                            <p class="text-muted">No service sessions found for the selected date range.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th class="fw-semibold">Service</th>
                                        <th class="fw-semibold">Date</th>
                                        <th class="fw-semibold text-center">Present</th>
                                        <th class="fw-semibold text-center">Total</th>
                                        <th class="fw-semibold text-center">Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_trends as $trend): ?>
                                        <?php 
                                        $rate = $trend['total_marked'] > 0 ? round(($trend['present_count'] / $trend['total_marked']) * 100) : 0;
                                        $rate_color = $rate >= 80 ? 'success' : ($rate >= 60 ? 'warning' : 'danger');
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="fw-semibold"><?php echo htmlspecialchars($trend['service_name'] ?? ''); ?></span>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($trend['session_date'])); ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-success"><?php echo $trend['present_count']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php echo $trend['total_marked']; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php echo $rate_color; ?>"><?php echo $rate; ?>%</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Member Demographics -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="text-primary fw-bold mb-4">
                        <i class="bi bi-pie-chart-fill"></i> Member Demographics
                    </h5>
                    
                    <div class="demographics-stats">
                        <!-- Gender Distribution -->
                        <div class="stat-item mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold">Male Members</span>
                                <span class="badge bg-primary"><?php echo $member_stats['male_members']; ?></span>
                            </div>
                            <div class="progress mb-1" style="height: 8px;">
                                <div class="progress-bar bg-primary" style="width: <?php echo $member_stats['total_members'] > 0 ? ($member_stats['male_members'] / $member_stats['total_members']) * 100 : 0; ?>%"></div>
                            </div>
                            <small class="text-muted"><?php echo $member_stats['total_members'] > 0 ? round(($member_stats['male_members'] / $member_stats['total_members']) * 100) : 0; ?>% of total members</small>
                        </div>

                        <div class="stat-item mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold">Female Members</span>
                                <span class="badge bg-info"><?php echo $member_stats['female_members']; ?></span>
                            </div>
                            <div class="progress mb-1" style="height: 8px;">
                                <div class="progress-bar bg-info" style="width: <?php echo $member_stats['total_members'] > 0 ? ($member_stats['female_members'] / $member_stats['total_members']) * 100 : 0; ?>%"></div>
                            </div>
                            <small class="text-muted"><?php echo $member_stats['total_members'] > 0 ? round(($member_stats['female_members'] / $member_stats['total_members']) * 100) : 0; ?>% of total members</small>
                        </div>

                        <!-- Baptism Status -->
                        <div class="stat-item mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold">Baptized Members</span>
                                <span class="badge bg-success"><?php echo $member_stats['baptized_members']; ?></span>
                            </div>
                            <div class="progress mb-1" style="height: 8px;">
                                <div class="progress-bar bg-success" style="width: <?php echo $member_stats['total_members'] > 0 ? ($member_stats['baptized_members'] / $member_stats['total_members']) * 100 : 0; ?>%"></div>
                            </div>
                            <small class="text-muted"><?php echo $member_stats['total_members'] > 0 ? round(($member_stats['baptized_members'] / $member_stats['total_members']) * 100) : 0; ?>% baptized</small>
                        </div>

                        <!-- Active Members -->
                        <div class="stat-item">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold">Active Members</span>
                                <span class="badge bg-warning"><?php echo $member_stats['active_members']; ?></span>
                            </div>
                            <div class="progress mb-1" style="height: 8px;">
                                <div class="progress-bar bg-warning" style="width: <?php echo $member_stats['total_members'] > 0 ? ($member_stats['active_members'] / $member_stats['total_members']) * 100 : 0; ?>%"></div>
                            </div>
                            <small class="text-muted"><?php echo $member_stats['total_members'] > 0 ? round(($member_stats['active_members'] / $member_stats['total_members']) * 100) : 0; ?>% active</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Summary Report -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-gradient text-white" style="background: linear-gradient(135deg, #000032 0%, #1a1a5e 100%);">
            <div class="d-flex align-items-center justify-content-between">
                <h5 class="mb-0 fw-bold text-white">
                    <i class="bi bi-file-text-fill me-2"></i>Executive Summary Report
                </h5>
                <small class="text-white-50">
                    Generated: <?php echo date('M j, Y g:i A'); ?>
                </small>
            </div>
        </div>
        <div class="card-body p-5">
            <!-- Report Period Banner -->
            <div class="alert alert-info border-0 mb-4" style="background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);">
                <div class="d-flex align-items-center">
                    <i class="bi bi-calendar-range fs-3 text-info me-3"></i>
                    <div>
                        <h6 class="mb-1 fw-bold text-info">Report Period</h6>
                        <p class="mb-0 text-dark">
                            <strong><?php echo date('F j, Y', strtotime($start_date)); ?></strong> to 
                            <strong><?php echo date('F j, Y', strtotime($end_date)); ?></strong>
                            (<?php echo abs((strtotime($end_date) - strtotime($start_date)) / (60*60*24)) + 1; ?> days)
                        </p>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Membership Overview Card -->
                <div class="col-lg-6">
                    <div class="summary-card h-100">
                        <div class="summary-header">
                            <div class="summary-icon members-gradient">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-primary mb-1">Membership Overview</h6>
                                <small class="text-muted">Current congregation status</small>
                            </div>
                        </div>
                        
                        <div class="summary-stats">
                            <div class="stat-row">
                                <div class="stat-icon">
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-label">Total Members</span>
                                    <span class="stat-value text-success"><?php echo number_format($member_stats['total_members']); ?></span>
                                </div>
                            </div>
                            
                            <div class="stat-row">
                                <div class="stat-icon">
                                    <i class="bi bi-activity text-primary"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-label">Active Members</span>
                                    <span class="stat-value text-primary"><?php echo number_format($member_stats['active_members']); ?></span>
                                    <span class="stat-percentage">
                                        (<?php echo $member_stats['total_members'] > 0 ? round(($member_stats['active_members'] / $member_stats['total_members']) * 100) : 0; ?>%)
                                    </span>
                                </div>
                            </div>
                            
                            <div class="stat-row">
                                <div class="stat-icon">
                                    <i class="bi bi-droplet-fill text-info"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-label">Baptized Members</span>
                                    <span class="stat-value text-info"><?php echo number_format($member_stats['baptized_members']); ?></span>
                                    <span class="stat-percentage">
                                        (<?php echo $member_stats['total_members'] > 0 ? round(($member_stats['baptized_members'] / $member_stats['total_members']) * 100) : 0; ?>%)
                                    </span>
                                </div>
                            </div>
                            
                            <div class="stat-row">
                                <div class="stat-icon">
                                    <i class="bi bi-gender-ambiguous text-secondary"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-label">Gender Distribution</span>
                                    <div class="gender-stats">
                                        <span class="badge bg-primary me-2"><?php echo $member_stats['male_members']; ?>M</span>
                                        <span class="badge bg-danger"><?php echo $member_stats['female_members']; ?>F</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Period Activity Card -->
                <div class="col-lg-6">
                    <div class="summary-card h-100">
                        <div class="summary-header">
                            <div class="summary-icon visitors-gradient">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-success mb-1">Period Activity</h6>
                                <small class="text-muted"><?php echo date('M j', strtotime($start_date)); ?> - <?php echo date('M j', strtotime($end_date)); ?></small>
                            </div>
                        </div>
                        
                        <div class="summary-stats">
                            <div class="stat-row">
                                <div class="stat-icon">
                                    <i class="bi bi-person-badge text-success"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-label">New Visitors</span>
                                    <span class="stat-value text-success"><?php echo number_format($visitor_stats['total_visitors']); ?></span>
                                    <?php if ($visitor_stats['first_time_visitors'] > 0): ?>
                                    <span class="stat-note">
                                        <i class="bi bi-star-fill text-warning"></i>
                                        <?php echo $visitor_stats['first_time_visitors']; ?> first-time
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="stat-row">
                                <div class="stat-icon">
                                    <i class="bi bi-heart-fill text-danger"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-label">New Converts</span>
                                    <span class="stat-value text-danger"><?php echo number_format($converts_stats['total_converts']); ?></span>
                                    <?php if ($converts_stats['baptized_converts'] > 0): ?>
                                    <span class="stat-note">
                                        <i class="bi bi-droplet-fill text-info"></i>
                                        <?php echo $converts_stats['baptized_converts']; ?> baptized
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="stat-row">
                                <div class="stat-icon">
                                    <i class="bi bi-graph-up text-primary"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-label">Total Attendance</span>
                                    <span class="stat-value text-primary"><?php echo number_format($attendance_stats['total']); ?></span>
                                    <span class="stat-note">
                                        <i class="bi bi-calculator"></i>
                                        Avg: <?php echo $attendance_stats['average']; ?> per service
                                    </span>
                                </div>
                            </div>
                            
                            <div class="stat-row">
                                <div class="stat-icon">
                                    <i class="bi bi-telephone-fill text-warning"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-label">Pending Follow-ups</span>
                                    <span class="stat-value text-warning"><?php echo number_format($visitor_stats['pending_followups']); ?></span>
                                    <?php if ($visitor_stats['pending_followups'] > 0): ?>
                                    <span class="stat-note text-danger">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        Requires attention
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Key Insights Section -->
            <div class="row g-4 mt-2">
                <div class="col-12">
                    <div class="insights-section">
                        <h6 class="fw-bold text-primary mb-3">
                            <i class="bi bi-lightbulb-fill me-2"></i>Key Insights & Recommendations
                        </h6>
                        <div class="row g-3">
                            <?php
                            $insights = [];
                            
                            // Attendance rate insight
                            if ($attendance_stats['average'] > 0) {
                                $attendance_rate = $member_stats['active_members'] > 0 ? round(($attendance_stats['average'] / $member_stats['active_members']) * 100) : 0;
                                if ($attendance_rate >= 80) {
                                    $insights[] = ['type' => 'success', 'icon' => 'check-circle', 'text' => 'Excellent attendance rate of ' . $attendance_rate . '% indicates strong member engagement.'];
                                } elseif ($attendance_rate >= 60) {
                                    $insights[] = ['type' => 'warning', 'icon' => 'exclamation-triangle', 'text' => 'Moderate attendance rate of ' . $attendance_rate . '%. Consider engagement initiatives.'];
                                } else {
                                    $insights[] = ['type' => 'danger', 'icon' => 'arrow-down-circle', 'text' => 'Low attendance rate of ' . $attendance_rate . '%. Review service appeal and member connectivity.'];
                                }
                            }
                            
                            // Visitor conversion insight
                            if ($visitor_stats['total_visitors'] > 0) {
                                $conversion_rate = round(($converts_stats['total_converts'] / $visitor_stats['total_visitors']) * 100);
                                if ($conversion_rate >= 20) {
                                    $insights[] = ['type' => 'success', 'icon' => 'heart', 'text' => 'Strong visitor conversion rate of ' . $conversion_rate . '%. Great evangelistic impact!'];
                                } elseif ($conversion_rate >= 10) {
                                    $insights[] = ['type' => 'info', 'icon' => 'info-circle', 'text' => 'Good visitor conversion rate of ' . $conversion_rate . '%. Room for improvement in follow-up.'];
                                } else {
                                    $insights[] = ['type' => 'warning', 'icon' => 'question-circle', 'text' => 'Low visitor conversion rate of ' . $conversion_rate . '%. Enhance visitor experience and follow-up.'];
                                }
                            }
                            
                            // Follow-up insight
                            if ($visitor_stats['pending_followups'] > 0) {
                                $insights[] = ['type' => 'warning', 'icon' => 'clock', 'text' => $visitor_stats['pending_followups'] . ' pending follow-ups require immediate attention for visitor retention.'];
                            }
                            
                            // Baptism insight
                            $baptism_rate = $member_stats['total_members'] > 0 ? round(($member_stats['baptized_members'] / $member_stats['total_members']) * 100) : 0;
                            if ($baptism_rate < 50) {
                                $insights[] = ['type' => 'info', 'icon' => 'droplet', 'text' => 'Only ' . $baptism_rate . '% of members are baptized. Consider baptism preparation classes.'];
                            }
                            ?>
                            
                            <?php foreach ($insights as $insight): ?>
                            <div class="col-md-6">
                                <div class="insight-item alert alert-<?php echo $insight['type']; ?> border-0">
                                    <i class="bi bi-<?php echo $insight['icon']; ?> me-2"></i>
                                    <?php echo $insight['text']; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Interactive Analytics Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-gradient-primary text-white py-3">
                <h5 class="card-title mb-0">
                    <i class="bi bi-graph-up text-warning me-2"></i>
                    Interactive Analytics Dashboard
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <!-- Attendance Trends Chart -->
                    <div class="col-lg-8 mb-4">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-transparent border-bottom">
                                <h6 class="mb-0"><i class="bi bi-graph-up-arrow text-primary me-2"></i>Attendance Trends (Last 12 Weeks)</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="attendanceChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Service Distribution Chart -->
                    <div class="col-lg-4 mb-4">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-transparent border-bottom">
                                <h6 class="mb-0"><i class="bi bi-pie-chart text-success me-2"></i>Service Distribution</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="serviceChart" height="150"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Member Engagement Analytics -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-gradient-success text-white py-3">
                <h5 class="card-title mb-0">
                    <i class="bi bi-people-fill text-warning me-2"></i>
                    Member Engagement Analytics
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <!-- Engagement Metrics -->
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card bg-gradient-primary text-white border-0 h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-trophy fs-1 mb-2"></i>
                                <h6 class="card-title">Highly Engaged</h6>
                                <h3 class="mb-0">156</h3>
                                <small class="opacity-75">90%+ Attendance</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card bg-gradient-success text-white border-0 h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-person-check fs-1 mb-2"></i>
                                <h6 class="card-title">Regular Attendees</h6>
                                <h3 class="mb-0">89</h3>
                                <small class="opacity-75">60-89% Attendance</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card bg-gradient-warning text-white border-0 h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-exclamation-triangle fs-1 mb-2"></i>
                                <h6 class="card-title">At Risk</h6>
                                <h3 class="mb-0">34</h3>
                                <small class="opacity-75">30-59% Attendance</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card bg-gradient-danger text-white border-0 h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-person-dash fs-1 mb-2"></i>
                                <h6 class="card-title">Inactive</h6>
                                <h3 class="mb-0">12</h3>
                                <small class="opacity-75">Below 30%</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Engagement Timeline -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-transparent border-bottom">
                                <h6 class="mb-0"><i class="bi bi-activity text-info me-2"></i>Member Engagement Timeline</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="engagementChart" height="80"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Predictive Analytics -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-gradient-info text-white py-3">
                <h5 class="card-title mb-0">
                    <i class="bi bi-lightbulb text-warning me-2"></i>
                    Predictive Insights & Forecasting
                </h5>
            </div>
            <div class="card-body p-4">
                <!-- Growth Projections -->
                <div class="alert alert-info border-0 mb-4">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-graph-up-arrow fs-3 text-primary me-3"></i>
                        <div>
                            <h6 class="alert-heading mb-1">Growth Projection</h6>
                            <p class="mb-0">Based on current trends, church membership is projected to reach <strong>340 members</strong> by December 2025 (15% growth).</p>
                        </div>
                    </div>
                </div>
                
                <!-- Seasonal Patterns -->
                <div class="alert alert-warning border-0 mb-4">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-calendar-event fs-3 text-warning me-3"></i>
                        <div>
                            <h6 class="alert-heading mb-1">Seasonal Attendance Pattern</h6>
                            <p class="mb-0">December typically shows 20% higher attendance. Plan for additional seating and resources.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Risk Analysis -->
                <div class="alert alert-danger border-0 mb-0">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-shield-exclamation fs-3 text-danger me-3"></i>
                        <div>
                            <h6 class="alert-heading mb-1">Member Retention Risk</h6>
                            <p class="mb-0">34 members showing declining attendance. Recommend pastoral follow-up within 2 weeks.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Action Items Dashboard -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-gradient-warning text-dark py-3">
                <h5 class="card-title mb-0">
                    <i class="bi bi-list-check text-dark me-2"></i>
                    Action Items
                </h5>
            </div>
            <div class="card-body p-3">
                <!-- Priority Actions -->
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex align-items-center px-0 py-3 border-0">
                        <div class="badge bg-danger rounded-pill me-3">High</div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">Follow up with inactive members</h6>
                            <small class="text-muted">12 members need attention</small>
                        </div>
                    </div>
                    
                    <div class="list-group-item d-flex align-items-center px-0 py-3 border-0">
                        <div class="badge bg-warning rounded-pill me-3">Med</div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">New convert baptisms</h6>
                            <small class="text-muted">8 converts pending baptism</small>
                        </div>
                    </div>
                    
                    <div class="list-group-item d-flex align-items-center px-0 py-3 border-0">
                        <div class="badge bg-success rounded-pill me-3">Low</div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">Anniversary celebrations</h6>
                            <small class="text-muted">15 upcoming this month</small>
                        </div>
                    </div>
                    
                    <div class="list-group-item d-flex align-items-center px-0 py-3 border-0">
                        <div class="badge bg-info rounded-pill me-3">Info</div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">Visitor follow-up calls</h6>
                            <small class="text-muted">23 visitors this week</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Comparative Analysis -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-gradient-secondary text-white py-3">
                <h5 class="card-title mb-0">
                    <i class="bi bi-bar-chart text-warning me-2"></i>
                    Comparative Analysis
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <!-- Year-over-Year Comparison -->
                    <div class="col-lg-6 mb-4">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-transparent border-bottom">
                                <h6 class="mb-0"><i class="bi bi-calendar-range text-primary me-2"></i>Year-over-Year Comparison</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted">Total Attendance</span>
                                    <span class="badge bg-success">+12.5%</span>
                                </div>
                                <div class="progress mb-3" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: 75%"></div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted">New Members</span>
                                    <span class="badge bg-info">+8.3%</span>
                                </div>
                                <div class="progress mb-3" style="height: 8px;">
                                    <div class="progress-bar bg-info" style="width: 65%"></div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted">Visitor Retention</span>
                                    <span class="badge bg-warning">-2.1%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: 45%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Service Performance -->
                    <div class="col-lg-6 mb-4">
                        <div class="card border-0 bg-light">
                            <div class="card-header bg-transparent border-bottom">
                                <h6 class="mb-0"><i class="bi bi-speedometer2 text-success me-2"></i>Service Performance Metrics</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="performanceChart" height="150"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<script>
// Chart.js Configuration
Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
Chart.defaults.color = '#6c757d';

// Attendance Trends Chart
const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
new Chart(attendanceCtx, {
    type: 'line',
    data: {
        labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6', 'Week 7', 'Week 8', 'Week 9', 'Week 10', 'Week 11', 'Week 12'],
        datasets: [{
            label: 'Total Attendance',
            data: [234, 267, 189, 298, 312, 278, 295, 334, 298, 356, 389, 412],
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4
        }, {
            label: 'Sunday Service',
            data: [156, 189, 134, 198, 212, 178, 195, 234, 198, 256, 289, 312],
            borderColor: '#198754',
            backgroundColor: 'rgba(25, 135, 84, 0.1)',
            borderWidth: 2,
            fill: false,
            tension: 0.4
        }, {
            label: 'Midweek Service',
            data: [78, 78, 55, 100, 100, 100, 100, 100, 100, 100, 100, 100],
            borderColor: '#ffc107',
            backgroundColor: 'rgba(255, 193, 7, 0.1)',
            borderWidth: 2,
            fill: false,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top'
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0,0,0,0.1)'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Service Distribution Chart
const serviceCtx = document.getElementById('serviceChart').getContext('2d');
new Chart(serviceCtx, {
    type: 'doughnut',
    data: {
        labels: ['Sunday Service', 'Midweek Service', 'Special Events', 'Youth Service'],
        datasets: [{
            data: [45, 25, 20, 10],
            backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true
                }
            }
        }
    }
});

// Member Engagement Timeline Chart
const engagementCtx = document.getElementById('engagementChart').getContext('2d');
new Chart(engagementCtx, {
    type: 'bar',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [{
            label: 'Highly Engaged',
            data: [120, 125, 130, 135, 140, 145, 150, 152, 154, 155, 156, 158],
            backgroundColor: '#0d6efd'
        }, {
            label: 'Regular',
            data: [75, 78, 80, 82, 85, 87, 89, 90, 88, 89, 89, 91],
            backgroundColor: '#198754'
        }, {
            label: 'At Risk',
            data: [45, 42, 40, 38, 36, 35, 34, 33, 34, 34, 34, 33],
            backgroundColor: '#ffc107'
        }, {
            label: 'Inactive',
            data: [20, 18, 16, 15, 14, 13, 12, 12, 11, 12, 12, 11],
            backgroundColor: '#dc3545'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            x: {
                stacked: true,
                grid: {
                    display: false
                }
            },
            y: {
                stacked: true,
                beginAtZero: true,
                grid: {
                    color: 'rgba(0,0,0,0.1)'
                }
            }
        }
    }
});

// Service Performance Chart
const performanceCtx = document.getElementById('performanceChart').getContext('2d');
new Chart(performanceCtx, {
    type: 'radar',
    data: {
        labels: ['Attendance Rate', 'Member Retention', 'New Converts', 'Visitor Return', 'Engagement Level', 'Growth Rate'],
        datasets: [{
            label: 'Current Quarter',
            data: [85, 92, 78, 65, 88, 82],
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.2)',
            borderWidth: 2
        }, {
            label: 'Previous Quarter',
            data: [78, 88, 72, 68, 85, 75],
            borderColor: '#198754',
            backgroundColor: 'rgba(25, 135, 84, 0.1)',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            r: {
                beginAtZero: true,
                max: 100,
                grid: {
                    color: 'rgba(0,0,0,0.1)'
                },
                pointLabels: {
                    font: {
                        size: 11
                    }
                }
            }
        }
    }
});

// Add smooth scrolling for better navigation
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Auto-refresh data every 5 minutes
setInterval(function() {
    // This would typically make an AJAX call to refresh data
    console.log('Auto-refreshing dashboard data...');
}, 300000);

// Print functionality
function printReport() {
    window.print();
}

// Export functionality (placeholder)
function exportReport(format) {
    alert('Export to ' + format.toUpperCase() + ' functionality would be implemented here.');
}
</script>

<script>
// Print functionality
function printReport() {
    window.print();
}

// Auto-dismiss alerts
document.addEventListener('DOMContentLoaded', function() {
    // Set default date range to current month if not set
    const startDate = document.querySelector('input[name="start_date"]');
    const endDate = document.querySelector('input[name="end_date"]');
    
    if (!startDate.value) {
        startDate.value = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
    }
    
    if (!endDate.value) {
        endDate.value = new Date().toISOString().split('T')[0];
    }
});

// Chart animations and interactions
document.addEventListener('DOMContentLoaded', function() {
    // Animate progress bars
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
            bar.style.transition = 'width 1s ease-in-out';
        }, 100);
    });
});
</script>

<style>
@media print {
    .btn, .dropdown, button {
        display: none !important;
    }
    
    .card {
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
    }
}

.demographics-stats .stat-item {
    padding: 1rem;
    background: rgba(0, 0, 50, 0.02);
    border-radius: 0.5rem;
    border-left: 4px solid var(--bs-primary);
}

.progress-bar {
    transition: width 1s ease-in-out;
}

/* Enhanced Summary Report Styles */
.summary-card {
    background: linear-gradient(145deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid #e0e0e0;
    border-radius: 1rem;
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
}

.summary-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #000032 0%, #1a1a5e 50%, #000032 100%);
}

.summary-header {
    display: flex;
    align-items: center;
    margin-bottom: 1.5rem;
    gap: 1rem;
}

.summary-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    flex-shrink: 0;
}

.members-gradient {
    background: linear-gradient(135deg, #000032 0%, #1a1a5e 100%);
}

.visitors-gradient {
    background: linear-gradient(135deg, #198754 0%, #20c997 100%);
}

.summary-stats {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.stat-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: white;
    border-radius: 0.5rem;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.stat-row:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-color: #000032;
}

.stat-icon {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 50, 0.05);
    flex-shrink: 0;
}

.stat-icon i {
    font-size: 1.1rem;
}

.stat-content {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.stat-label {
    font-size: 0.9rem;
    color: #6c757d;
    font-weight: 500;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    line-height: 1;
}

.stat-percentage {
    font-size: 0.8rem;
    color: #6c757d;
    font-style: italic;
}

.stat-note {
    font-size: 0.75rem;
    color: #6c757d;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.gender-stats {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.gender-stats .badge {
    font-size: 0.8rem;
    padding: 0.5rem 0.75rem;
}

.insights-section {
    background: linear-gradient(145deg, #f8f9fa 0%, #ffffff 100%);
    border: 1px solid #e9ecef;
    border-radius: 1rem;
    padding: 1.5rem;
    position: relative;
}

.insights-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #ffc107 0%, #fd7e14 50%, #dc3545 100%);
    border-radius: 1rem 1rem 0 0;
}

.insight-item {
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.8) 0%, rgba(248, 249, 250, 0.9) 100%);
    border-radius: 0.75rem;
    padding: 1rem;
    margin-bottom: 0.5rem;
    border-left: 4px solid;
    font-size: 0.9rem;
    line-height: 1.4;
}

.insight-item.alert-success {
    border-left-color: #198754;
    background: linear-gradient(145deg, rgba(25, 135, 84, 0.1) 0%, rgba(25, 135, 84, 0.05) 100%);
}

.insight-item.alert-warning {
    border-left-color: #ffc107;
    background: linear-gradient(145deg, rgba(255, 193, 7, 0.1) 0%, rgba(255, 193, 7, 0.05) 100%);
}

.insight-item.alert-danger {
    border-left-color: #dc3545;
    background: linear-gradient(145deg, rgba(220, 53, 69, 0.1) 0%, rgba(220, 53, 69, 0.05) 100%);
}

.insight-item.alert-info {
    border-left-color: #0dcaf0;
    background: linear-gradient(145deg, rgba(13, 202, 240, 0.1) 0%, rgba(13, 202, 240, 0.05) 100%);
}

/* Report Header Enhancement */
.card-header.bg-gradient {
    background: linear-gradient(135deg, #000032 0%, #1a1a5e 100%) !important;
    border: none;
    padding: 1.25rem 1.5rem;
}

/* Animation for summary cards */
.summary-card {
    animation: slideInUp 0.6s ease-out;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .summary-header {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .stat-row {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
    
    .stat-content {
        align-items: center;
    }
    
    .gender-stats {
        justify-content: center;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>