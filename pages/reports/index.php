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
$sql = "SELECT COUNT(*) as total_attendance FROM attendance WHERE date BETWEEN ? AND ?";
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
    WHERE a.date BETWEEN ? AND ?";
$params = [$start_date, $end_date];

if ($department_filter) {
    $sql .= " AND a.member_id IN (SELECT id FROM members WHERE department_id = ?)";
    $params[] = $department_filter;
}

$sql .= " GROUP BY a.service_id, a.date) as service_attendance";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendance_stats['average'] = round($stmt->fetch()['avg_attendance'] ?? 0, 1);

// Attendance by service
$sql = "SELECT s.name as service_name, s.status, COUNT(a.id) as attendance_count
    FROM services s
    LEFT JOIN attendance a ON s.id = a.service_id AND a.date BETWEEN ? AND ?
    WHERE s.template_status = 'active'";
$params = [$start_date, $end_date];

if ($department_filter) {
    $sql .= " AND a.member_id IN (SELECT id FROM members WHERE department_id = ?)";
    $params[] = $department_filter;
}

$sql .= " GROUP BY s.id, s.name, s.status ORDER BY attendance_count DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$service_attendance = $stmt->fetchAll();

// Monthly attendance trend (last 12 months)
$sql = "SELECT 
    DATE_FORMAT(a.date, '%Y-%m') as month,
    COUNT(*) as attendance_count
    FROM attendance a
    WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";

if ($department_filter) {
    $sql .= " AND a.member_id IN (SELECT id FROM members WHERE department_id = ?)";
    $params_trend = [$department_filter];
} else {
    $params_trend = [];
}

$sql .= " GROUP BY DATE_FORMAT(a.date, '%Y-%m') ORDER BY month";
$stmt = $pdo->prepare($sql);
$stmt->execute($params_trend);
$monthly_trend = $stmt->fetchAll();

// Fill in missing months with zero attendance and add month names
$trend_data = [];
for ($i = 11; $i >= 0; $i--) {
    $month_key = date('Y-m', strtotime("-$i months"));
    $month_name = date('M Y', strtotime("-$i months"));
    $found = false;
    foreach ($monthly_trend as $trend) {
        if ($trend['month'] == $month_key) {
            $trend_data[] = ['month' => $month_key, 'month_name' => $month_name, 'attendance_count' => $trend['attendance_count']];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $trend_data[] = ['month' => $month_key, 'month_name' => $month_name, 'attendance_count' => 0];
    }
}
$monthly_trend = $trend_data;

// Department attendance comparison
$sql = "SELECT 
    d.name as department_name,
    COUNT(DISTINCT m.id) as total_members,
    COUNT(a.id) as attendance_count,
    ROUND((COUNT(a.id) / COUNT(DISTINCT m.id)), 2) as avg_attendance_per_member
    FROM departments d
    LEFT JOIN members m ON d.id = m.department_id AND m.status = 'active'
    LEFT JOIN attendance a ON m.id = a.member_id AND a.date BETWEEN ? AND ?
    GROUP BY d.id, d.name
    ORDER BY avg_attendance_per_member DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start_date, $end_date]);
$department_stats = $stmt->fetchAll();

// Top attending members
$sql = "SELECT 
    m.name,
    m.phone,
    d.name as department,
    COUNT(a.id) as attendance_count
    FROM members m
    LEFT JOIN attendance a ON m.id = a.member_id AND a.date BETWEEN ? AND ?
    LEFT JOIN departments d ON m.department_id = d.id
    WHERE m.status = 'active'";
$params = [$start_date, $end_date];

if ($department_filter) {
    $sql .= " AND m.department_id = ?";
    $params[] = $department_filter;
}

$sql .= " GROUP BY m.id ORDER BY attendance_count DESC LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$top_members = $stmt->fetchAll();

// Get departments for filter
$dept_stmt = $pdo->query("SELECT * FROM departments ORDER BY name");
$departments = $dept_stmt->fetchAll();

// Member attendance patterns
$sql = "SELECT 
    DAYNAME(a.date) as day_name,
    COUNT(*) as attendance_count
    FROM attendance a
    WHERE a.date BETWEEN ? AND ?";
$params = [$start_date, $end_date];

if ($department_filter) {
    $sql .= " AND a.member_id IN (SELECT id FROM members WHERE department_id = ?)";
    $params[] = $department_filter;
}

$sql .= " GROUP BY DAYOFWEEK(a.date), DAYNAME(a.date) ORDER BY DAYOFWEEK(a.date)";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$day_patterns = $stmt->fetchAll();

$page_title = "Detailed Reports - Bridge Ministries International";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Using Bootstrap classes only -->
    <!-- Using Bootstrap classes only -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php include '../../includes/header.php'; ?>

<div class="reports-container">
    <div class="container-fluid">
        <!-- Header Section -->
        <div class="reports-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="bi bi-graph-up"></i> Detailed Reports & Analytics</h1>
                    <p class="lead">Comprehensive attendance and membership insights</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="export-actions">
                        <button class="btn btn-outline-primary me-2" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print Report
                        </button>
                        <button class="btn btn-primary" onclick="exportData()">
                            <i class="bi bi-download"></i> Export Data
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-card">
            <div class="card">
                <div class="card-body">
                    <h5><i class="bi bi-funnel"></i> Report Filters</h5>
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="department" class="form-label">Department</label>
                            <select class="form-control" id="department" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Key Metrics Row -->
        <div class="metrics-row">
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="metric-card metric-total">
                        <div class="metric-icon">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-number"><?php echo number_format($attendance_stats['total']); ?></div>
                            <div class="metric-label">Total Attendance</div>
                            <div class="metric-period">For selected period</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="metric-card metric-average">
                        <div class="metric-icon">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-number"><?php echo $attendance_stats['average']; ?></div>
                            <div class="metric-label">Avg per Service</div>
                            <div class="metric-period">Average attendance</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="metric-card metric-departments">
                        <div class="metric-icon">
                            <i class="bi bi-diagram-3"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-number"><?php echo count($department_stats); ?></div>
                            <div class="metric-label">Active Departments</div>
                            <div class="metric-period">With attendance data</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="metric-card metric-services">
                        <div class="metric-icon">
                            <i class="bi bi-calendar-event"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-number"><?php echo count($service_attendance); ?></div>
                            <div class="metric-label">Services Tracked</div>
                            <div class="metric-period">In selected period</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Monthly Trend Chart -->
            <div class="col-md-8 mb-4">
                <div class="chart-card">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-graph-up"></i> Monthly Attendance Trend</h5>
                            <small class="text-muted">Last 12 months attendance pattern</small>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyTrendChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Day Pattern Chart -->
            <div class="col-md-4 mb-4">
                <div class="chart-card">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-calendar-week"></i> Weekly Patterns</h5>
                            <small class="text-muted">Attendance by day of week</small>
                        </div>
                        <div class="card-body">
                            <canvas id="dayPatternChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Service Performance -->
            <div class="col-md-6 mb-4">
                <div class="table-card">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-calendar-event"></i> Service Performance</h5>
                            <small class="text-muted">Attendance by service type</small>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Service Name</th>
                                            <th>Status</th>
                                            <th class="text-center">Attendance</th>
                                            <th class="text-center">Performance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($service_attendance as $service): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($service['service_name']); ?></strong>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_class = $service['status'] == 'open' ? 'bg-success' : ($service['status'] == 'scheduled' ? 'bg-warning' : 'bg-secondary');
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($service['status']); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="attendance-count"><?php echo $service['attendance_count']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php 
                                                $percentage = $attendance_stats['total'] > 0 ? ($service['attendance_count'] / $attendance_stats['total']) * 100 : 0;
                                                $bar_class = $percentage > 70 ? 'bg-success' : ($percentage > 40 ? 'bg-warning' : 'bg-danger');
                                                ?>
                                                <div class="performance-bar">
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar <?php echo $bar_class; ?>" style="width: <?php echo $percentage; ?>%"></div>
                                                    </div>
                                                    <small><?php echo round($percentage, 1); ?>%</small>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Department Statistics -->
            <div class="col-md-6 mb-4">
                <div class="table-card">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-diagram-3"></i> Department Analysis</h5>
                            <small class="text-muted">Attendance by department</small>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Department</th>
                                            <th class="text-center">Members</th>
                                            <th class="text-center">Attendance</th>
                                            <th class="text-center">Avg/Member</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($department_stats as $dept): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($dept['department_name']); ?></strong>
                                            </td>
                                            <td class="text-center">
                                                <?php echo $dept['total_members']; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="attendance-count"><?php echo $dept['attendance_count']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary"><?php echo $dept['avg_attendance_per_member']; ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Attending Members -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="top-members-card">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-trophy"></i> Top Attending Members</h5>
                            <small class="text-muted">Most active members in selected period</small>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($top_members as $index => $member): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="member-card">
                                        <div class="member-rank">
                                            <?php if ($index < 3): ?>
                                                <i class="bi bi-award-fill rank-<?php echo $index + 1; ?>"></i>
                                            <?php else: ?>
                                                <span class="rank-number"><?php echo $index + 1; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="member-info">
                                            <h6><?php echo htmlspecialchars($member['name']); ?></h6>
                                            <p class="member-dept"><?php echo htmlspecialchars($member['department']); ?></p>
                                            <div class="attendance-badge">
                                                <i class="bi bi-check-circle"></i>
                                                <?php echo $member['attendance_count']; ?> attendances
                                            </div>
                                        </div>
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
</div>

<script>
// Monthly Trend Chart
const monthlyCtx = document.getElementById('monthlyTrendChart').getContext('2d');
const monthlyData = <?php echo json_encode(array_column($monthly_trend, 'attendance_count')); ?>;
const monthlyLabels = <?php echo json_encode(array_column($monthly_trend, 'month_name')); ?>;

new Chart(monthlyCtx, {
    type: 'line',
    data: {
        labels: monthlyLabels,
        datasets: [{
            label: 'Monthly Attendance',
            data: monthlyData,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#3b82f6',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleColor: '#ffffff',
                bodyColor: '#ffffff',
                borderColor: '#3b82f6',
                borderWidth: 1
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0,0,0,0.1)'
                },
                ticks: {
                    stepSize: 1
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

// Day Pattern Chart
const dayCtx = document.getElementById('dayPatternChart').getContext('2d');
const dayLabels = <?php echo json_encode(array_column($day_patterns, 'day_name')); ?>;
const dayData = <?php echo json_encode(array_column($day_patterns, 'attendance_count')); ?>;

// If no data, show a placeholder
if (dayData.length === 0 || dayData.every(val => val === 0)) {
    dayCtx.fillStyle = '#e2e8f0';
    dayCtx.font = '14px Arial';
    dayCtx.textAlign = 'center';
    dayCtx.fillText('No attendance data available', dayCtx.canvas.width / 2, dayCtx.canvas.height / 2);
} else {
    new Chart(dayCtx, {
        type: 'doughnut',
        data: {
            labels: dayLabels,
            datasets: [{
                data: dayData,
                backgroundColor: [
                    '#3b82f6', '#10b981', '#f59e0b', '#ef4444', 
                    '#8b5cf6', '#06b6d4', '#84cc16'
                ],
                borderWidth: 0,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 15
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff'
                }
            }
        }
    });
}

function exportData() {
    // Simple CSV export functionality
    const data = [
        ['Period', '<?php echo $start_date; ?> to <?php echo $end_date; ?>'],
        ['Total Attendance', '<?php echo $attendance_stats['total']; ?>'],
        ['Average per Service', '<?php echo $attendance_stats['average']; ?>'],
        [''],
        ['Service Name', 'Status', 'Attendance Count'],
        <?php foreach ($service_attendance as $service): ?>
        ['<?php echo addslashes($service['service_name']); ?>', '<?php echo $service['status']; ?>', '<?php echo $service['attendance_count']; ?>'],
        <?php endforeach; ?>
    ];
    
    let csvContent = "data:text/csv;charset=utf-8,";
    data.forEach(function(rowArray) {
        let row = rowArray.join(",");
        csvContent += row + "\r\n";
    });
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "attendance_report_<?php echo date('Y-m-d'); ?>.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include '../../includes/footer.php'; ?>
</body>
</html>