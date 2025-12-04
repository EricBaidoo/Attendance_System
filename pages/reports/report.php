<?php
// pages/reports/report.php - Complete System Reports

// Handle includes gracefully
$base_dir = dirname(__DIR__) . '/..';

// Try to include security - fall back to basic session handling if not available
if (file_exists($base_dir . '/includes/security.php')) {
    require_once $base_dir . '/includes/security.php';
} else {
    // Basic session handling if security.php doesn't exist
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../../login.php');
        exit;
    }
    
    // Basic validation function
    function validateAndSanitize($data, $rules) {
        $result = ['data' => []];
        foreach ($rules as $key => $rule) {
            $result['data'][$key] = isset($data[$key]) ? htmlspecialchars(trim($data[$key])) : '';
        }
        return $result;
    }
}

// Include database connection
require_once $base_dir . '/config/database.php';

// Try to include error handler
if (file_exists($base_dir . '/includes/error_handler.php')) {
    require_once $base_dir . '/includes/error_handler.php';
} else {
    // Basic error logging function
    function logDatabaseError($message) {
        error_log("Database Error: " . $message);
    }
}

// Require login if function exists
if (function_exists('requireLogin')) {
    requireLogin();
}

// Get filter parameters and validate
$validation_rules = [
    'start_date' => ['required' => false, 'max_length' => 10],
    'end_date' => ['required' => false, 'max_length' => 10],
    'department_filter' => ['required' => false, 'max_length' => 10],
    'service_filter' => ['required' => false, 'max_length' => 10],
    'report_type' => ['required' => false, 'max_length' => 20]
];

$validation_result = validateAndSanitize($_GET, $validation_rules);
$filters = $validation_result['data'];

// Set default date range
$start_date = $filters['start_date'] ?: date('Y-m-01'); // First day of current month
$end_date = $filters['end_date'] ?: date('Y-m-d'); // Today
$department_filter = $filters['department_filter'] ?: '';
$service_filter = $filters['service_filter'] ?: '';
$report_type = $filters['report_type'] ?: 'overview';

try {
    // Try to temporarily disable ONLY_FULL_GROUP_BY for compatibility
    try {
        $pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
    } catch (Exception $sql_mode_error) {
        // If we can't change SQL mode, continue with strict-compliant queries
    }
    
    // Get system overview statistics
    $overview_sql = "SELECT 
        (SELECT COUNT(*) FROM members WHERE status = 'active') as total_members,
        (SELECT COUNT(*) FROM visitors WHERE DATE(created_at) BETWEEN ? AND ?) as total_visitors,
        (SELECT COUNT(*) FROM new_converts WHERE DATE(date_converted) BETWEEN ? AND ?) as new_converts,
        (SELECT COUNT(*) FROM service_sessions WHERE session_date BETWEEN ? AND ?) as total_sessions,
        (SELECT COUNT(*) FROM departments WHERE status = 'active') as active_departments,
        (SELECT COUNT(*) FROM services WHERE template_status = 'active') as active_services";
    
    $overview_stmt = $pdo->prepare($overview_sql);
    $overview_stmt->execute([$start_date, $end_date, $start_date, $end_date, $start_date, $end_date]);
    $overview_stats = $overview_stmt->fetch();

    // Get attendance statistics
    $attendance_sql = "SELECT 
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_present,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as total_absent,
        COUNT(DISTINCT a.session_id) as sessions_with_attendance,
        COUNT(DISTINCT a.member_id) as unique_attendees,
        ROUND(AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100, 1) as attendance_percentage
        FROM attendance a
        JOIN service_sessions ss ON a.session_id = ss.id
        WHERE ss.session_date BETWEEN ? AND ?";
    
    if ($service_filter) {
        $attendance_sql .= " AND ss.service_id = ?";
        $attendance_params = [$start_date, $end_date, $service_filter];
    } else {
        $attendance_params = [$start_date, $end_date];
    }
    
    $attendance_stmt = $pdo->prepare($attendance_sql);
    $attendance_stmt->execute($attendance_params);
    $attendance_stats = $attendance_stmt->fetch();

    // Get member demographics
    $demographics_sql = "SELECT 
        COUNT(*) as total_members,
        COUNT(CASE WHEN gender = 'male' THEN 1 END) as male_count,
        COUNT(CASE WHEN gender = 'female' THEN 1 END) as female_count,
        COUNT(CASE WHEN baptized = 'yes' THEN 1 END) as baptized_count,
        COUNT(CASE WHEN congregation_group = 'Adult' THEN 1 END) as adult_count,
        COUNT(CASE WHEN congregation_group = 'Youth' THEN 1 END) as youth_count
        FROM members WHERE status = 'active'";
    
    if ($department_filter) {
        $demographics_sql .= " AND department_id = ?";
        $demographics_stmt = $pdo->prepare($demographics_sql);
        $demographics_stmt->execute([$department_filter]);
    } else {
        $demographics_stmt = $pdo->query($demographics_sql);
    }
    $demographics = $demographics_stmt->fetch();

    // Get top services by attendance
    $top_services_sql = "SELECT 
        s.id as service_id,
        s.name as service_name,
        COUNT(DISTINCT ss.id) as session_count,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_attendance,
        ROUND(AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100, 1) as avg_attendance_rate
        FROM services s
        LEFT JOIN service_sessions ss ON s.id = ss.service_id AND ss.session_date BETWEEN ? AND ?
        LEFT JOIN attendance a ON ss.id = a.session_id
        WHERE s.template_status = 'active'
        GROUP BY s.id, s.name
        ORDER BY total_attendance DESC, s.name
        LIMIT 5";
    
    $top_services_stmt = $pdo->prepare($top_services_sql);
    $top_services_stmt->execute([$start_date, $end_date]);
    $top_services = $top_services_stmt->fetchAll();

    // Get attendance trends (last 30 days) - SQL strict mode compliant
    $trends_sql = "SELECT 
        session_date as date,
        service_name,
        service_id,
        SUM(present_count) as present_count,
        SUM(total_marked) as total_marked
        FROM (
            SELECT 
                DATE(ss.session_date) as session_date,
                s.name as service_name,
                s.id as service_id,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                COUNT(a.id) as total_marked
            FROM service_sessions ss
            JOIN services s ON ss.service_id = s.id
            LEFT JOIN attendance a ON ss.id = a.session_id
            WHERE ss.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(ss.session_date), s.id, s.name
        ) as daily_attendance
        GROUP BY session_date, service_id, service_name
        ORDER BY session_date DESC, service_name";
    
    $trends_stmt = $pdo->query($trends_sql);
    $trends_data = $trends_stmt->fetchAll();

    // Get department performance analytics
    $dept_performance_sql = "SELECT 
        d.id as department_id,
        d.name as department_name,
        COUNT(DISTINCT m.id) as total_members,
        COUNT(DISTINCT CASE WHEN a.status = 'present' THEN m.id END) as active_attendees,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_present,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as total_absent,
        ROUND(AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100, 1) as attendance_rate
        FROM departments d
        LEFT JOIN members m ON d.id = m.department_id AND m.status = 'active'
        LEFT JOIN attendance a ON m.id = a.member_id
        LEFT JOIN service_sessions ss ON a.session_id = ss.id AND ss.session_date BETWEEN ? AND ?
        WHERE d.status = 'active'
        GROUP BY d.id, d.name
        ORDER BY attendance_rate DESC, total_members DESC";
    
    $dept_stmt = $pdo->prepare($dept_performance_sql);
    $dept_stmt->execute([$start_date, $end_date]);
    $department_performance = $dept_stmt->fetchAll();

    // Get individual member attendance tracking
    $member_tracking_sql = "SELECT 
        m.id as member_id,
        m.name as member_name,
        d.name as department_name,
        m.phone as phone,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as times_present,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as times_absent,
        COUNT(a.id) as total_sessions_marked,
        ROUND(AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100, 1) as attendance_percentage,
        MAX(ss.session_date) as last_attendance,
        DATEDIFF(CURDATE(), MAX(ss.session_date)) as days_since_last_attendance
        FROM members m
        LEFT JOIN departments d ON m.department_id = d.id
        LEFT JOIN attendance a ON m.id = a.member_id
        LEFT JOIN service_sessions ss ON a.session_id = ss.id AND ss.session_date BETWEEN ? AND ?
        WHERE m.status = 'active'
        GROUP BY m.id, m.name, d.name, m.phone
        HAVING total_sessions_marked > 0
        ORDER BY attendance_percentage DESC, times_present DESC
        LIMIT 50";
    
    $member_stmt = $pdo->prepare($member_tracking_sql);
    $member_stmt->execute([$start_date, $end_date]);
    $member_tracking = $member_stmt->fetchAll();

    // Get frequently absent members for follow-up
    $absent_members_sql = "SELECT 
        m.id as member_id,
        m.name as member_name,
        d.name as department_name,
        m.phone as phone,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as times_absent,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as times_present,
        COUNT(a.id) as total_sessions_marked,
        ROUND(AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100, 1) as attendance_percentage,
        MAX(ss.session_date) as last_attendance
        FROM members m
        LEFT JOIN departments d ON m.department_id = d.id
        LEFT JOIN attendance a ON m.id = a.member_id
        LEFT JOIN service_sessions ss ON a.session_id = ss.id AND ss.session_date BETWEEN ? AND ?
        WHERE m.status = 'active'
        GROUP BY m.id, m.name, d.name, m.phone
        HAVING total_sessions_marked > 0 AND attendance_percentage < 50
        ORDER BY attendance_percentage ASC, times_absent DESC
        LIMIT 20";
    
    $absent_stmt = $pdo->prepare($absent_members_sql);
    $absent_stmt->execute([$start_date, $end_date]);
    $absent_members = $absent_stmt->fetchAll();

    // Get departments for filter
    $departments_stmt = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name");
    $departments = $departments_stmt->fetchAll();

    // Get services for filter
    $services_stmt = $pdo->query("SELECT id, name FROM services WHERE template_status = 'active' ORDER BY name");
    $services = $services_stmt->fetchAll();

} catch (Exception $e) {
    // Log the error if error handler exists
    if (function_exists('logDatabaseError')) {
        logDatabaseError($e->getMessage());
    }
    
    // Provide more detailed error for debugging
    $error_message = "Database Error: " . $e->getMessage();
    
    // Set default values to prevent further errors
    $overview_stats = [
        'total_members' => 0,
        'total_visitors' => 0,
        'new_converts' => 0,
        'total_sessions' => 0,
        'active_departments' => 0,
        'active_services' => 0
    ];
    
    $attendance_stats = [
        'total_present' => 0,
        'total_absent' => 0,
        'sessions_with_attendance' => 0,
        'unique_attendees' => 0,
        'attendance_percentage' => 0
    ];
    
    $demographics = [
        'total_members' => 0,
        'male_count' => 0,
        'female_count' => 0,
        'baptized_count' => 0,
        'adult_count' => 0,
        'youth_count' => 0
    ];
    
    $top_services = [];
    $trends_data = [];
    $departments = [];
    $services = [];
    $department_performance = [];
    $member_tracking = [];
    $absent_members = [];
}

$page_title = "System Reports - Bridge Ministries International";
include '../../includes/header.php';
?>
<link href="../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* Modern Report Styling */
.report-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.report-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px -3px rgba(0, 0, 0, 0.1);
}

.stat-card {
    background: linear-gradient(135deg, var(--bs-primary) 0%, var(--bs-primary-dark, #0056b3) 100%);
    border: none;
    border-radius: 12px;
    color: white;
    overflow: hidden;
    position: relative;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    transform: translate(30%, -30%);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 0.875rem;
    opacity: 0.9;
    font-weight: 500;
}

.chart-container {
    position: relative;
    height: 300px;
}

.filter-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid #dee2e6;
    border-radius: 12px;
}

.export-btn {
    border-radius: 8px;
    padding: 8px 16px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.export-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.trend-item {
    padding: 12px 16px;
    border-left: 4px solid var(--bs-primary);
    background: rgba(var(--bs-primary-rgb), 0.05);
    border-radius: 0 8px 8px 0;
    margin-bottom: 8px;
}

/* Custom Scrollbar Styling */
.table-responsive::-webkit-scrollbar,
div[style*="overflow-y: auto"]::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.table-responsive::-webkit-scrollbar-track,
div[style*="overflow-y: auto"]::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.table-responsive::-webkit-scrollbar-thumb,
div[style*="overflow-y: auto"]::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
    transition: background 0.3s ease;
}

.table-responsive::-webkit-scrollbar-thumb:hover,
div[style*="overflow-y: auto"]::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}

/* Fade overlay for scrollable content */
.scrollable-table::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 20px;
    background: linear-gradient(transparent, rgba(255,255,255,0.8));
    pointer-events: none;
}

.scrollable-content {
    position: relative;
}

@media (max-width: 768px) {
    .stat-number {
        font-size: 2rem;
    }
    
    .chart-container {
        height: 250px;
    }
}
</style>

<div class="container-fluid py-4">
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card report-card">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="h2 text-primary mb-2 fw-bold">
                                <i class="bi bi-graph-up"></i> System Reports & Analytics
                            </h1>
                            <p class="text-muted mb-0">
                                Comprehensive insights for <?php echo date('F j, Y', strtotime($start_date)); ?> 
                                to <?php echo date('F j, Y', strtotime($end_date)); ?>
                            </p>
                        </div>
                        <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                            <div class="btn-group me-2">
                                <button class="btn btn-outline-primary export-btn" onclick="exportData('csv')">
                                    <i class="bi bi-file-earmark-spreadsheet"></i> CSV
                                </button>
                                <button class="btn btn-outline-success export-btn" onclick="exportData('excel')">
                                    <i class="bi bi-file-earmark-excel"></i> Excel
                                </button>
                                <button class="btn btn-outline-danger export-btn" onclick="exportData('pdf')">
                                    <i class="bi bi-file-earmark-pdf"></i> PDF
                                </button>
                            </div>
                            <button class="btn btn-primary export-btn" onclick="window.print()">
                                <i class="bi bi-printer"></i> Print
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card filter-card">
                <div class="card-body p-4">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-calendar-range text-primary"></i> Start Date
                            </label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-calendar-check text-primary"></i> End Date
                            </label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-building text-primary"></i> Department
                            </label>
                            <select name="department_filter" class="form-select">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-calendar-event text-primary"></i> Service
                            </label>
                            <select name="service_filter" class="form-select">
                                <option value="">All Services</option>
                                <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['id']; ?>" <?php echo $service_filter == $service['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($service['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-funnel"></i> Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Key Metrics Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card stat-card h-100" style="background: linear-gradient(135deg, #0d6efd 0%, #0056b3 100%);">
                <div class="card-body p-4 position-relative">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?php echo number_format($overview_stats['total_members']); ?></div>
                            <div class="stat-label">Active Members</div>
                        </div>
                        <div class="text-white-50">
                            <i class="bi bi-people fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card stat-card h-100" style="background: linear-gradient(135deg, #198754 0%, #146c43 100%);">
                <div class="card-body p-4 position-relative">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?php echo number_format($attendance_stats['total_present']); ?></div>
                            <div class="stat-label">Total Attendance</div>
                        </div>
                        <div class="text-white-50">
                            <i class="bi bi-check-circle fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card stat-card h-100" style="background: linear-gradient(135deg, #fd7e14 0%, #e55a0d 100%);">
                <div class="card-body p-4 position-relative">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?php echo number_format($overview_stats['total_visitors']); ?></div>
                            <div class="stat-label">New Visitors</div>
                        </div>
                        <div class="text-white-50">
                            <i class="bi bi-person-plus fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card stat-card h-100" style="background: linear-gradient(135deg, #6f42c1 0%, #59369c 100%);">
                <div class="card-body p-4 position-relative">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?php echo $attendance_stats['attendance_percentage'] ?: '0'; ?>%</div>
                            <div class="stat-label">Attendance Rate</div>
                        </div>
                        <div class="text-white-50">
                            <i class="bi bi-graph-up fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Analytics Row -->
    <div class="row g-4 mb-4">
        <!-- Attendance Trends Chart -->
        <div class="col-lg-8">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-graph-up text-primary"></i> Attendance Trends (Last 30 Days)
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="chart-container">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Demographics Breakdown -->
        <div class="col-lg-4">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-pie-chart text-success"></i> Member Demographics
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="chart-container">
                        <canvas id="demographicsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Service Performance and Recent Activity -->
    <div class="row g-4 mb-4">
        <!-- Top Services -->
        <div class="col-lg-6">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-star text-warning"></i> Top Performing Services
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($top_services)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x text-muted fs-1"></i>
                            <p class="text-muted mt-3">No service data available for this period.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($top_services as $index => $service): ?>
                        <div class="d-flex align-items-center mb-3">
                            <div class="badge bg-primary rounded-pill me-3"><?php echo $index + 1; ?></div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><?php echo htmlspecialchars($service['service_name']); ?></div>
                                <small class="text-muted">
                                    <?php echo $service['total_attendance']; ?> attendees across <?php echo $service['session_count']; ?> sessions
                                </small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-primary"><?php echo $service['avg_attendance_rate']; ?>%</div>
                                <small class="text-muted">avg rate</small>
                            </div>
                        </div>
                        <?php if ($index < count($top_services) - 1): ?>
                        <hr class="my-3">
                        <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="col-lg-6">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-speedometer2 text-info"></i> Quick Statistics
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 mb-1 text-primary"><?php echo $overview_stats['total_sessions']; ?></div>
                                <small class="text-muted">Total Sessions</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 mb-1 text-success"><?php echo $overview_stats['new_converts']; ?></div>
                                <small class="text-muted">New Converts</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 mb-1 text-warning"><?php echo $overview_stats['active_departments']; ?></div>
                                <small class="text-muted">Active Departments</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 mb-1 text-info"><?php echo $attendance_stats['unique_attendees']; ?></div>
                                <small class="text-muted">Unique Attendees</small>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h6 class="fw-semibold mb-3">Gender Distribution</h6>
                        <div class="d-flex align-items-center mb-2">
                            <div class="me-3" style="width: 80px;">
                                <small class="text-muted">Male</small>
                            </div>
                            <div class="flex-grow-1">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-primary" style="width: <?php echo $demographics['total_members'] > 0 ? ($demographics['male_count'] / $demographics['total_members']) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                            <div class="ms-3">
                                <small class="fw-semibold"><?php echo $demographics['male_count']; ?></small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="me-3" style="width: 80px;">
                                <small class="text-muted">Female</small>
                            </div>
                            <div class="flex-grow-1">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $demographics['total_members'] > 0 ? ($demographics['female_count'] / $demographics['total_members']) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                            <div class="ms-3">
                                <small class="fw-semibold"><?php echo $demographics['female_count']; ?></small>
                            </div>
                        </div>

                        <h6 class="fw-semibold mb-3">Baptism Status</h6>
                        <div class="d-flex align-items-center">
                            <div class="me-3" style="width: 80px;">
                                <small class="text-muted">Baptized</small>
                            </div>
                            <div class="flex-grow-1">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-info" style="width: <?php echo $demographics['total_members'] > 0 ? ($demographics['baptized_count'] / $demographics['total_members']) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                            <div class="ms-3">
                                <small class="fw-semibold"><?php echo round(($demographics['baptized_count'] / max($demographics['total_members'], 1)) * 100); ?>%</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Individual Member Tracking and Department Analytics -->
    <div class="row g-4 mb-4">
        <!-- Department Performance -->
        <div class="col-lg-6">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-building text-primary"></i> Department Performance
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($department_performance)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-building-x text-muted fs-1"></i>
                            <p class="text-muted mt-3">No department data available for this period.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 350px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px;">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Department</th>
                                        <th class="text-center">Members</th>
                                        <th class="text-center">Rate</th>
                                        <th class="text-center">Present</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($department_performance as $dept): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($dept['department_name']); ?></div>
                                            <small class="text-muted"><?php echo $dept['active_attendees']; ?> active attendees</small>
                                        </td>
                                        <td class="text-center"><?php echo $dept['total_members']; ?></td>
                                        <td class="text-center">
                                            <span class="badge <?php echo ($dept['attendance_rate'] >= 70) ? 'bg-success' : (($dept['attendance_rate'] >= 50) ? 'bg-warning' : 'bg-danger'); ?>">
                                                <?php echo $dept['attendance_rate'] ?: '0'; ?>%
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <strong class="text-primary"><?php echo $dept['total_present']; ?></strong>
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

        <!-- Top Attending Members -->
        <div class="col-lg-6">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-trophy text-success"></i> Top Attending Members
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($member_tracking)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-person-x text-muted fs-1"></i>
                            <p class="text-muted mt-3">No attendance data available for this period.</p>
                        </div>
                    <?php else: ?>
                        <div style="max-height: 350px; overflow-y: auto; padding-right: 8px; border: 1px solid #dee2e6; border-radius: 8px; padding: 16px;">
                            <?php foreach (array_slice($member_tracking, 0, 10) as $index => $member): ?>
                            <div class="d-flex align-items-center mb-3 p-2 rounded <?php echo $index % 2 == 0 ? 'bg-light' : ''; ?>">
                                <div class="badge bg-primary rounded-pill me-3"><?php echo $index + 1; ?></div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold"><?php echo htmlspecialchars($member['member_name']); ?></div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($member['department_name'] ?? 'No Department'); ?> • 
                                        <?php echo $member['times_present']; ?> sessions attended
                                    </small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-success"><?php echo $member['attendance_percentage']; ?>%</div>
                                    <small class="text-muted">
                                        <?php echo $member['days_since_last_attendance'] ? $member['days_since_last_attendance'] . 'd ago' : 'Recent'; ?>
                                    </small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Follow-up Tracking -->
    <div class="row g-4 mb-4">
        <!-- Members Needing Follow-up -->
        <div class="col-12">
            <div class="card report-card">
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4 pb-0">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-exclamation-triangle text-warning"></i> Members Needing Follow-up
                            </h5>
                            <p class="text-muted mb-0 mt-1">Members with low attendance rates (below 50%)</p>
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-outline-primary btn-sm" onclick="exportFollowUpList()">
                                <i class="bi bi-download"></i> Export Follow-up List
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($absent_members)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-check-circle text-success fs-1"></i>
                            <p class="text-muted mt-3">Great! No members need immediate follow-up.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px;">
                            <table class="table table-hover mb-0" id="followUpTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Member Name</th>
                                        <th>Department</th>
                                        <th>Phone</th>
                                        <th class="text-center">Attendance Rate</th>
                                        <th class="text-center">Sessions Missed</th>
                                        <th class="text-center">Last Attendance</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($absent_members as $member): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($member['member_name']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($member['department_name'] ?? 'No Department'); ?></td>
                                        <td>
                                            <?php if ($member['phone']): ?>
                                                <a href="tel:<?php echo $member['phone']; ?>" class="text-decoration-none">
                                                    <i class="bi bi-telephone"></i> <?php echo $member['phone']; ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">No phone</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-danger"><?php echo $member['attendance_percentage']; ?>%</span>
                                        </td>
                                        <td class="text-center">
                                            <strong class="text-danger"><?php echo $member['times_absent']; ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <small class="text-muted">
                                                <?php echo $member['last_attendance'] ? date('M j', strtotime($member['last_attendance'])) : 'Never'; ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($member['phone']): ?>
                                                <a href="tel:<?php echo $member['phone']; ?>" class="btn btn-outline-primary">
                                                    <i class="bi bi-telephone"></i>
                                                </a>
                                                <a href="sms:<?php echo $member['phone']; ?>" class="btn btn-outline-success">
                                                    <i class="bi bi-chat-text"></i>
                                                </a>
                                                <?php endif; ?>
                                                <button class="btn btn-outline-info" onclick="markForFollowUp(<?php echo $member['member_id']; ?>)">
                                                    <i class="bi bi-bookmark"></i>
                                                </button>
                                            </div>
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
    </div>

    <!-- All Members Tracking -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card report-card">
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4 pb-0">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-people text-info"></i> Individual Member Tracking
                            </h5>
                            <p class="text-muted mb-0 mt-1">Detailed attendance tracking for all active members</p>
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-outline-success btn-sm me-2" onclick="exportMemberTracking()">
                                <i class="bi bi-download"></i> Export List
                            </button>
                            <div class="input-group input-group-sm" style="width: 250px;">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="memberSearch" placeholder="Search members...">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($member_tracking)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x text-muted fs-1"></i>
                            <p class="text-muted mt-3">No member tracking data available for this period.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px;">
                            <table class="table table-hover table-sm mb-0" id="memberTrackingTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Rank</th>
                                        <th>Member Name</th>
                                        <th>Department</th>
                                        <th class="text-center">Attendance Rate</th>
                                        <th class="text-center">Present</th>
                                        <th class="text-center">Absent</th>
                                        <th class="text-center">Total Sessions</th>
                                        <th class="text-center">Last Seen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($member_tracking as $index => $member): ?>
                                    <tr class="member-row">
                                        <td>
                                            <span class="badge <?php echo ($index < 5) ? 'bg-success' : (($index < 15) ? 'bg-primary' : 'bg-secondary'); ?>">
                                                #<?php echo $index + 1; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($member['member_name']); ?></div>
                                            <?php if ($member['phone']): ?>
                                                <small class="text-muted">
                                                    <i class="bi bi-telephone"></i> <?php echo $member['phone']; ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($member['department_name'] ?? 'No Department'); ?></td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center justify-content-center">
                                                <div class="progress me-2" style="width: 50px; height: 6px;">
                                                    <div class="progress-bar <?php echo ($member['attendance_percentage'] >= 80) ? 'bg-success' : (($member['attendance_percentage'] >= 60) ? 'bg-warning' : 'bg-danger'); ?>" 
                                                         style="width: <?php echo $member['attendance_percentage']; ?>%"></div>
                                                </div>
                                                <span class="fw-bold"><?php echo $member['attendance_percentage']; ?>%</span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success"><?php echo $member['times_present']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-danger"><?php echo $member['times_absent']; ?></span>
                                        </td>
                                        <td class="text-center"><?php echo $member['total_sessions_marked']; ?></td>
                                        <td class="text-center">
                                            <small class="text-muted">
                                                <?php 
                                                if ($member['last_attendance']) {
                                                    echo date('M j, Y', strtotime($member['last_attendance']));
                                                    echo '<br><small>(' . $member['days_since_last_attendance'] . ' days ago)</small>';
                                                } else {
                                                    echo 'Never';
                                                }
                                                ?>
                                            </small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($member_tracking) >= 50): ?>
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle"></i> 
                            Showing top 50 members. Use filters to see different date ranges or departments.
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Member search functionality
document.addEventListener('DOMContentLoaded', function() {
    const memberSearch = document.getElementById('memberSearch');
    if (memberSearch) {
        memberSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#memberTrackingTable .member-row');
            
            rows.forEach(row => {
                const memberName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const department = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                
                if (memberName.includes(searchTerm) || department.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});

// Export follow-up list
function exportFollowUpList() {
    const table = document.getElementById('followUpTable');
    if (!table) return;
    
    let csv = 'Member Name,Department,Phone,Attendance Rate,Sessions Missed,Last Attendance\n';
    
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const memberName = cells[0].textContent.trim();
        const department = cells[1].textContent.trim();
        const phone = cells[2].textContent.replace(/[^\d\s\+\-\(\)]/g, '').trim();
        const attendanceRate = cells[3].textContent.trim();
        const sessionsMissed = cells[4].textContent.trim();
        const lastAttendance = cells[5].textContent.trim();
        
        csv += `"${memberName}","${department}","${phone}","${attendanceRate}","${sessionsMissed}","${lastAttendance}"\n`;
    });
    
    // Create and download CSV
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'follow_up_list_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Mark member for follow-up
function markForFollowUp(memberId) {
    if (confirm('Mark this member for follow-up? This will add them to your follow-up list.')) {
        // In a real implementation, this would make an AJAX call to save the follow-up status
        console.log('Marking member ' + memberId + ' for follow-up');
        
        // Visual feedback
        const button = event.target.closest('button');
        button.innerHTML = '<i class="bi bi-bookmark-check"></i>';
        button.className = 'btn btn-success';
        button.disabled = true;
        
        // Show success message
        showToast('Member marked for follow-up!', 'success');
    }
}

// Show toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.remove();
        }
    }, 3000);
}

// Enhanced export functionality
function exportMemberTracking() {
    const table = document.getElementById('memberTrackingTable');
    if (!table) return;
    
    let csv = 'Rank,Member Name,Department,Phone,Attendance Rate,Present,Absent,Total Sessions,Last Seen\n';
    
    const visibleRows = Array.from(table.querySelectorAll('tbody .member-row'))
        .filter(row => row.style.display !== 'none');
    
    visibleRows.forEach((row, index) => {
        const cells = row.querySelectorAll('td');
        const rank = index + 1;
        const memberName = cells[1].querySelector('.fw-semibold').textContent.trim();
        const phone = cells[1].querySelector('small') ? 
            cells[1].querySelector('small').textContent.replace(/[^\d\s\+\-\(\)]/g, '').trim() : '';
        const department = cells[2].textContent.trim();
        const attendanceRate = cells[3].querySelector('span').textContent.trim();
        const present = cells[4].textContent.trim();
        const absent = cells[5].textContent.trim();
        const totalSessions = cells[6].textContent.trim();
        const lastSeen = cells[7].textContent.replace(/\n/g, ' ').trim();
        
        csv += `"${rank}","${memberName}","${department}","${phone}","${attendanceRate}","${present}","${absent}","${totalSessions}","${lastSeen}"\n`;
    });
    
    // Create and download CSV
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'member_tracking_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Chart.js Configuration
Chart.defaults.font.family = 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
Chart.defaults.color = '#6b7280';

// Prepare data from PHP
const trendsData = <?php echo json_encode($trends_data); ?>;
const demographics = <?php echo json_encode($demographics); ?>;

// Process trends data for chart
const last7Days = [];
const attendanceByDay = {};

// Get last 7 days
for (let i = 6; i >= 0; i--) {
    const date = new Date();
    date.setDate(date.getDate() - i);
    const dateStr = date.toISOString().split('T')[0];
    last7Days.push(dateStr);
    attendanceByDay[dateStr] = 0;
}

// Fill in actual attendance data
trendsData.forEach(item => {
    if (attendanceByDay.hasOwnProperty(item.date)) {
        attendanceByDay[item.date] += parseInt(item.present_count);
    }
});

const chartData = last7Days.map(date => attendanceByDay[date]);
const chartLabels = last7Days.map(date => {
    return new Date(date + 'T12:00:00').toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
});

// Attendance Trends Chart
const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
new Chart(attendanceCtx, {
    type: 'line',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'Attendance',
            data: chartData,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#0d6efd',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 6,
            pointHoverRadius: 8
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
                borderColor: '#0d6efd',
                borderWidth: 1,
                cornerRadius: 8,
                displayColors: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.1)',
                    drawBorder: false
                },
                ticks: {
                    font: {
                        size: 12
                    },
                    color: '#6b7280'
                }
            },
            x: {
                grid: {
                    display: false,
                    drawBorder: false
                },
                ticks: {
                    font: {
                        size: 12
                    },
                    color: '#6b7280'
                }
            }
        }
    }
});

// Demographics Chart
const demographicsCtx = document.getElementById('demographicsChart').getContext('2d');
new Chart(demographicsCtx, {
    type: 'doughnut',
    data: {
        labels: ['Male', 'Female'],
        datasets: [{
            data: [demographics.male_count, demographics.female_count],
            backgroundColor: ['#0d6efd', '#198754'],
            borderWidth: 0,
            cutout: '65%'
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
                    usePointStyle: true,
                    font: {
                        size: 12
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleColor: '#ffffff',
                bodyColor: '#ffffff',
                cornerRadius: 8,
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.raw / total) * 100).toFixed(1);
                        return context.label + ': ' + context.raw + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// Export functionality
function exportData(format) {
    const startDate = document.querySelector('input[name="start_date"]').value;
    const endDate = document.querySelector('input[name="end_date"]').value;
    const department = document.querySelector('select[name="department_filter"]').value;
    const service = document.querySelector('select[name="service_filter"]').value;
    
    // Create download URL
    let url = 'export.php?format=' + format;
    url += '&start_date=' + encodeURIComponent(startDate);
    url += '&end_date=' + encodeURIComponent(endDate);
    if (department) url += '&department_filter=' + encodeURIComponent(department);
    if (service) url += '&service_filter=' + encodeURIComponent(service);
    
    // Trigger download
    window.location.href = url;
}

// Auto-refresh functionality
let refreshInterval;

function startAutoRefresh() {
    refreshInterval = setInterval(() => {
        // In a real implementation, this would use AJAX to update data
        console.log('Auto-refreshing report data...');
    }, 300000); // 5 minutes
}

// Initialize auto-refresh
startAutoRefresh();

// Print optimization
window.addEventListener('beforeprint', function() {
    // Hide interactive elements when printing
    document.querySelectorAll('.btn, .dropdown, .form-control').forEach(el => {
        el.style.display = 'none';
    });
});

window.addEventListener('afterprint', function() {
    // Restore interactive elements after printing
    document.querySelectorAll('.btn, .dropdown, .form-control').forEach(el => {
        el.style.display = '';
    });
});
</script>

<?php include '../../includes/footer.php'; ?>